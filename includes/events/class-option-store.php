<?php
/**
 * Backwards-compatible Events option storage.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Preserves the migrated option schema behind a replaceable store contract.
 */
final class Option_Store implements Store_Interface {
	public function events(): array {
		$value = get_option( Repository::OPTION_EVENTS, array() );
		return is_array( $value ) ? $value : array();
	}

	public function find_event( int $id ): ?array {
		$events = $this->events();
		return isset( $events[ $id ] ) && is_array( $events[ $id ] ) ? $events[ $id ] : null;
	}

	public function find_event_by_slug( string $slug ): ?array {
		$slug = sanitize_title( $slug );
		foreach ( $this->events() as $event ) {
			if ( is_array( $event ) && sanitize_title( (string) ( $event['slug'] ?? '' ) ) === $slug ) {
				return $event;
			}
		}
		return null;
	}

	public function query_events( array $filters ): array {
		$status   = sanitize_key( (string) ( $filters['status'] ?? '' ) );
		$search   = strtolower( sanitize_text_field( (string) ( $filters['search'] ?? '' ) ) );
		$upcoming = ! empty( $filters['upcoming'] );
		$events   = array();
		foreach ( $this->events() as $item ) {
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
			$events[] = $item;
		}
		usort(
			$events,
			static fn( array $first, array $second ): int => ( new Event( $first ) )->starts_at_timestamp() <=> ( new Event( $second ) )->starts_at_timestamp()
		);
		return $events;
	}

	public function save_event( array $event ): bool {
		$events = $this->events();
		$id     = absint( $event['id'] ?? 0 );
		if ( ! $id ) {
			return false;
		}
		$events[ $id ] = $event;
		return $this->save_events( $events );
	}

	public function delete_event( int $id ): bool {
		$events = $this->events();
		if ( ! isset( $events[ $id ] ) ) {
			return true;
		}
		unset( $events[ $id ] );
		return $this->save_events( $events );
	}

	public function slug_exists( string $slug, int $ignore_id = 0 ): bool {
		foreach ( $this->events() as $item ) {
			if ( is_array( $item ) && absint( $item['id'] ?? 0 ) !== $ignore_id && sanitize_title( (string) ( $item['slug'] ?? '' ) ) === $slug ) {
				return true;
			}
		}
		return false;
	}

	/** @param array<int,array<string,mixed>> $events */
	private function save_events( array $events ): bool {
		return update_option( Repository::OPTION_EVENTS, $events, false )
			|| $events === get_option( Repository::OPTION_EVENTS, array() );
	}

	public function next_id(): int {
		return max( 1, absint( get_option( Repository::OPTION_NEXT_ID, 1 ) ) );
	}

	public function save_next_id( int $next_id ): bool {
		$next_id = max( 1, $next_id );
		return update_option( Repository::OPTION_NEXT_ID, $next_id, false )
			|| $next_id === absint( get_option( Repository::OPTION_NEXT_ID, 1 ) );
	}

	public function taxonomy( string $type ): array {
		$value = get_option( $this->taxonomy_option( $type ), array() );
		return is_array( $value ) ? $value : array();
	}

	public function save_taxonomy( string $type, array $items ): bool {
		$option = $this->taxonomy_option( $type );
		return update_option( $option, $items, false ) || $items === get_option( $option, array() );
	}

	private function taxonomy_option( string $type ): string {
		return 'locations' === $type ? Repository::OPTION_LOCATIONS : Repository::OPTION_CATEGORIES;
	}
}
