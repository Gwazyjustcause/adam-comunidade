<?php
/**
 * Events REST API.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Read API for all consumers and delegated member interactions.
 */
final class Rest_API {
	private Api $api;

	public function __construct( Api $api ) {
		$this->api = $api;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'adam-comunidade/v1',
			'/events',
			array(
				'methods' => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'events' ),
				'permission_callback' => '__return_true',
				'args' => array(
					'search' => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'upcoming' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ),
				),
			)
		);
		register_rest_route(
			'adam-comunidade/v1',
			'/events/(?P<id>\d+)',
			array(
				'methods' => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'event' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'adam-comunidade/v1',
			'/events/(?P<id>\d+)/registrations',
			array(
				'methods' => \WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'register_attendee' ),
				'permission_callback' => array( $this, 'can_register_attendee' ),
			)
		);
		register_rest_route(
			'adam-comunidade/v1',
			'/events/(?P<id>\d+)/attendance/(?P<member_id>\d+)',
			array(
				'methods' => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'attendance' ),
				'permission_callback' => array( $this, 'can_read_attendance' ),
			)
		);
	}

	public function events( \WP_REST_Request $request ): \WP_REST_Response {
		$filters = array(
			'status' => Event::STATUS_PUBLISHED,
			'search' => (string) $request->get_param( 'search' ),
			'upcoming' => (bool) $request->get_param( 'upcoming' ),
		);
		return rest_ensure_response( array_map( array( $this, 'prepare' ), $this->api->get_events( $filters ) ) );
	}

	public function event( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$event = $this->api->get_event( absint( $request['id'] ) );
		if ( ! $event || ! $event->is_visible() ) {
			return new \WP_Error( 'adam_event_not_found', __( 'Evento não encontrado.', 'adam-comunidade' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->prepare( $event ) );
	}

	public function register_attendee( \WP_REST_Request $request ): mixed {
		$event = $this->api->get_event( absint( $request['id'] ) );
		if ( ! $event || ! $event->is_visible() ) {
			return new \WP_Error( 'adam_event_not_found', __( 'Evento não encontrado.', 'adam-comunidade' ), array( 'status' => 404 ) );
		}
		$result = $this->api->register_attendee( absint( $request['id'] ), (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( is_object( $result ) && method_exists( $result, 'data' ) ? $result->data() : $result );
	}

	public function attendance( \WP_REST_Request $request ): mixed {
		return rest_ensure_response( $this->api->attendance_status( absint( $request['id'] ), absint( $request['member_id'] ) ) );
	}

	public function can_register_attendee( \WP_REST_Request $request ): bool {
		return (bool) apply_filters(
			'adam_comunidade_events_registration_permission',
			is_user_logged_in(),
			absint( $request['id'] ),
			$request
		);
	}

	public function can_read_attendance( \WP_REST_Request $request ): bool {
		$member_id = absint( $request['member_id'] ?: $request->get_param( 'member_id' ) );
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$allowed = is_user_logged_in() && $member_id > 0 && get_current_user_id() === $member_id;
		return (bool) apply_filters(
			'adam_comunidade_events_attendance_permission',
			$allowed,
			absint( $request['id'] ),
			$member_id,
			$request
		);
	}

	/** @return array<string,mixed> */
	private function prepare( Event $event ): array {
		$data = $event->data();
		unset( $data['checkin_token'], $data['notes'] );
		$data['url'] = $this->api->event_url( $event );
		return $data;
	}
}
