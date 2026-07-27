<?php
/**
 * Events persistence.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the single canonical Events store.
 */
final class Repository {
	public const OPTION_EVENTS = 'adam_comunidade_events';
	public const OPTION_NEXT_ID = 'adam_comunidade_event_next_id';
	public const OPTION_CATEGORIES = 'adam_comunidade_event_categories';
	public const OPTION_LOCATIONS = 'adam_comunidade_event_locations';

	/** @return array<int,array<string,mixed>> */
	public function raw(): array {
		$value = get_option( self::OPTION_EVENTS, array() );
		return is_array( $value ) ? $value : array();
	}

	public function find( int $id ): ?Event {
		$events = $this->raw();
		return isset( $events[ $id ] ) && is_array( $events[ $id ] ) ? new Event( $events[ $id ] ) : null;
	}

	public function find_by_slug( string $slug ): ?Event {
		$slug = sanitize_title( $slug );
		foreach ( $this->raw() as $item ) {
			if ( is_array( $item ) && sanitize_title( (string) ( $item['slug'] ?? '' ) ) === $slug ) {
				return new Event( $item );
			}
		}
		return null;
	}

	/** @param array<string,mixed> $filters @return Event[] */
	public function query( array $filters = array() ): array {
		$status = sanitize_key( (string) ( $filters['status'] ?? '' ) );
		$search = strtolower( sanitize_text_field( (string) ( $filters['search'] ?? '' ) ) );
		$upcoming = ! empty( $filters['upcoming'] );
		$events = array();
		foreach ( $this->raw() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$event = new Event( $item );
			if ( $status && $event->status() !== $status ) {
				continue;
			}
			if ( $upcoming && $event->starts_at_timestamp() < current_time( 'timestamp' ) ) {
				continue;
			}
			if ( $search && ! str_contains( strtolower( implode( ' ', array( $event->title(), $event->short_description(), $event->location() ) ) ), $search ) ) {
				continue;
			}
			$events[] = $event;
		}
		usort( $events, static fn( Event $a, Event $b ): int => $a->starts_at_timestamp() <=> $b->starts_at_timestamp() );
		return $events;
	}

	/** @param array<string,mixed> $data */
	public function save( array $data, int $id = 0 ): Event {
		$events = $this->raw();
		if ( ! $id ) {
			$id = max( 1, absint( get_option( self::OPTION_NEXT_ID, 1 ) ) );
		}
		$existing = isset( $events[ $id ] ) && is_array( $events[ $id ] ) ? $events[ $id ] : array();
		$item = array_merge( $existing, $data, array( 'id' => $id ) );
		$events[ $id ] = $item;
		update_option( self::OPTION_EVENTS, $events, false );
		update_option( self::OPTION_NEXT_ID, max( $id + 1, absint( get_option( self::OPTION_NEXT_ID, 1 ) ) ), false );
		do_action( empty( $existing ) ? 'adam_comunidade_event_created' : 'adam_comunidade_event_updated', new Event( $item ) );
		return new Event( $item );
	}

	public function delete( int $id ): void {
		$events = $this->raw();
		if ( isset( $events[ $id ] ) ) {
			$event = new Event( $events[ $id ] );
			unset( $events[ $id ] );
			update_option( self::OPTION_EVENTS, $events, false );
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
		$value = get_option( self::OPTION_CATEGORIES, array() );
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/** @return array<int,array<string,mixed>> */
	public function locations(): array {
		$value = get_option( self::OPTION_LOCATIONS, array() );
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/** @param array<int,array<string,mixed>> $items */
	public function save_taxonomy( string $type, array $items ): void {
		$option = 'locations' === $type ? self::OPTION_LOCATIONS : self::OPTION_CATEGORIES;
		$existing = get_option( $option, array() );
		$existing = is_array( $existing ) ? $existing : array();
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
		update_option( $option, $clean, false );
	}

	private function slug_exists( string $slug, int $ignore_id ): bool {
		foreach ( $this->raw() as $item ) {
			if ( is_array( $item ) && absint( $item['id'] ?? 0 ) !== $ignore_id && sanitize_title( (string) ( $item['slug'] ?? '' ) ) === $slug ) {
				return true;
			}
		}
		return false;
	}
}
