<?php
/**
 * Stable Events API.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Supported cross-plugin boundary for community events.
 */
final class Api {
	private static ?self $instance = null;
	private Repository $repository;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self( new Repository() );
		}
		return self::$instance;
	}

	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	public function get_event( int|string $identifier ): ?Event {
		return is_numeric( $identifier )
			? $this->repository->find( absint( $identifier ) )
			: $this->repository->find_by_slug( (string) $identifier );
	}

	/** @param array<string,mixed> $filters @return Event[] */
	public function get_events( array $filters = array() ): array {
		return $this->repository->query( $filters );
	}

	/** @return Event[] */
	public function upcoming_events( int $limit = 0 ): array {
		$events = $this->repository->query( array( 'status' => Event::STATUS_PUBLISHED, 'upcoming' => true ) );
		return $limit > 0 ? array_slice( $events, 0, $limit ) : $events;
	}

	/**
	 * Creates or updates canonical event content.
	 *
	 * @param array<string,mixed> $data Event data.
	 * @return Event|\WP_Error
	 */
	public function save_event( array $data, int $id = 0 ): Event|\WP_Error {
		$title = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
		$date = sanitize_text_field( (string) ( $data['event_date'] ?? '' ) );
		if ( ! $title || ! $date ) {
			return new \WP_Error( 'adam_event_invalid', __( 'O título e a data do evento são obrigatórios.', 'adam-comunidade' ) );
		}
		$existing = $id ? $this->repository->find( $id ) : null;
		$data['title'] = $title;
		$data['event_date'] = $date;
		$slug_source = $existing
			? (string) ( $data['slug'] ?? $existing->slug() )
			: (string) ( $data['slug'] ?? $title );
		$data['slug'] = $this->repository->unique_slug( $slug_source, $id );
		$data['created_at'] = $existing ? $existing->created_at() : ( (string) ( $data['created_at'] ?? current_time( 'mysql', true ) ) );
		$data['updated_at'] = current_time( 'mysql', true );
		return $this->repository->save( $data, $id );
	}

	public function delete_event( int $id ): void {
		$this->repository->delete( $id );
	}

	public function event_url( Event|int|string $event ): string {
		$record = $event instanceof Event ? $event : $this->get_event( $event );
		return $record ? home_url( '/eventos/' . $record->slug() . '/' ) : '';
	}

	public function checkin_url( Event|int|string $event ): string {
		$record = $event instanceof Event ? $event : $this->get_event( $event );
		return $record && $record->checkin_token()
			? home_url( '/eventos/check-in/' . rawurlencode( $record->checkin_token() ) . '/' )
			: '';
	}

	/** @param array<string,mixed> $attendee */
	public function register_attendee( int $event_id, array $attendee ): mixed {
		return apply_filters( 'adam_comunidade_events_register_attendee', new \WP_Error( 'events_interaction_unavailable', __( 'As inscrições de sócios não estão disponíveis.', 'adam-comunidade' ) ), $event_id, $attendee );
	}

	public function attendance_status( int $event_id, int $member_id ): mixed {
		return apply_filters( 'adam_comunidade_events_attendance_status', null, $event_id, $member_id );
	}

	private function __clone() {}
}
