<?php
/**
 * Events persistence.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Logger;

/**
 * Owns the single canonical Events store.
 */
final class Repository {
	public const OPTION_EVENTS = 'adam_comunidade_events';
	public const OPTION_NEXT_ID = 'adam_comunidade_event_next_id';
	public const OPTION_CATEGORIES = 'adam_comunidade_event_categories';
	public const OPTION_LOCATIONS = 'adam_comunidade_event_locations';
	private Store_Interface $store;
	/** @var array<int,array<string,mixed>>|null */
	private ?array $events_cache = null;

	public function __construct( ?Store_Interface $store = null ) {
		$default = $store ?? new Option_Store();
		$filtered = apply_filters( 'adam_comunidade_events_store', $default );
		$this->store = $filtered instanceof Store_Interface ? $filtered : $default;
	}

	/** @return array<int,array<string,mixed>> */
	public function raw(): array {
		if ( null === $this->events_cache ) {
			$this->events_cache = $this->store->events();
		}
		return $this->events_cache;
	}

	public function find( int $id ): ?Event {
		$event = $this->store->find_event( $id );
		return $event ? new Event( $event ) : null;
	}

	public function find_by_slug( string $slug ): ?Event {
		$event = $this->store->find_event_by_slug( sanitize_title( $slug ) );
		return $event ? new Event( $event ) : null;
	}

	/** @param array<string,mixed> $filters @return Event[] */
	public function query( array $filters = array() ): array {
		return array_map(
			static fn( array $event ): Event => new Event( $event ),
			$this->store->query_events( $filters )
		);
	}

	/** @param array<string,mixed> $data */
	public function save( array $data, int $id = 0 ): Event|\WP_Error {
		if ( ! $id ) {
			$id = $this->store->next_id();
		}
		$existing = $this->store->find_event( $id ) ?? array();
		$item = array_merge( $existing, $data, array( 'id' => $id ) );
		if ( ! $this->store->save_next_id( max( $id + 1, $this->store->next_id() ) ) || ! $this->store->save_event( $item ) ) {
			Logger::error( 'community_event_save_failed', array( 'event_id' => $id ) );
			return new \WP_Error( 'adam_event_save_failed', __( 'Não foi possível guardar o evento.', 'adam-comunidade' ) );
		}
		$this->events_cache = null;
		$event = new Event( $item );
		do_action( empty( $existing ) ? 'adam_comunidade_event_created' : 'adam_comunidade_event_updated', $event );
		do_action( 'adam_comunidade_event_saved', $event, $existing );
		if ( Event::STATUS_PUBLISHED === $event->status() && Event::STATUS_PUBLISHED !== (string) ( $existing['status'] ?? '' ) ) {
			do_action( 'adam_comunidade_event_published', $event );
		}
		return $event;
	}

	public function delete( int $id ): void {
		$item = $this->store->find_event( $id );
		if ( $item ) {
			$event = new Event( $item );
			if ( ! $this->store->delete_event( $id ) ) {
				Logger::error( 'community_event_delete_failed', array( 'event_id' => $id ) );
				return;
			}
			$this->events_cache = null;
			do_action( 'adam_comunidade_event_deleted', $event );
		}
	}

	public function unique_slug( string $title, int $ignore_id = 0 ): string {
		$base = sanitize_title( $title ) ?: 'evento';
		$slug = $base;
		$counter = 2;
		while ( $this->slug_exists( $slug, $ignore_id ) ) {
			$slug = $base . '-' . $counter++;
		}
		return $slug;
	}

	/** @return array<int,array<string,mixed>> */
	public function categories(): array {
		return array_values( $this->store->taxonomy( 'categories' ) );
	}

	/** @return array<int,array<string,mixed>> */
	public function locations(): array {
		return array_values( $this->store->taxonomy( 'locations' ) );
	}

	/** @param array<int,array<string,mixed>> $items */
	public function save_taxonomy( string $type, array $items ): bool {
		$existing = $this->store->taxonomy( $type );
		$existing_ids = array();
		$next_id = 1;
		foreach ( $existing as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id = absint( $item['id'] ?? 0 );
			$slug = sanitize_title( (string) ( $item['slug'] ?? $item['name'] ?? '' ) );
			if ( $id && $slug ) {
				$existing_ids[ $slug ] = $id;
				$next_id = max( $next_id, $id + 1 );
			}
		}
		$clean = array();
		foreach ( $items as $item ) {
			$name = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
			if ( ! $name ) {
				continue;
			}
			$slug = sanitize_title( (string) ( $item['slug'] ?? $name ) );
			$id = $existing_ids[ $slug ] ?? $next_id++;
			$clean[ $id ] = array(
				'id' => $id,
				'name' => $name,
				'slug' => $slug,
				'address' => 'locations' === $type ? sanitize_text_field( (string) ( $item['address'] ?? '' ) ) : '',
				'map_link' => 'locations' === $type ? esc_url_raw( (string) ( $item['map_link'] ?? '' ) ) : '',
			);
		}
		if ( ! $this->store->save_taxonomy( $type, $clean ) ) {
			Logger::error( 'community_event_taxonomy_save_failed', array( 'taxonomy' => sanitize_key( $type ) ) );
			return false;
		}
		return true;
	}

	/**
	 * Preserves an imported ID sequence through the active store.
	 */
	public function ensure_next_id( int $next_id ): bool {
		return $this->store->save_next_id( max( $this->store->next_id(), $next_id ) );
	}

	private function slug_exists( string $slug, int $ignore_id ): bool {
		return $this->store->slug_exists( $slug, $ignore_id );
	}
}
