<?php
/**
 * Runtime smoke test for the controlled Events migration and public API.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

$GLOBALS['adam_options'] = array(
	'adam_membership_events' => array(
		4 => array(
			'id' => 4,
			'slug' => 'jogo-floresta',
			'title' => 'Jogo Floresta',
			'event_date' => '2099-05-20',
			'start_time' => '09:00',
			'location' => 'Lousã',
			'cover_image' => 'https://example.test/cover.jpg',
			'checkin_token' => 'token-four',
			'checkin_enabled' => 1,
			'checkin_points' => 5,
			'access_mode' => 'open',
			'status' => 'published',
			'created_at' => '2025-01-01 10:00:00',
			'updated_at' => '2025-01-01 10:00:00',
		),
		7 => array(
			'id' => 7,
			'slug' => 'evento-concluido',
			'title' => 'Evento Concluído',
			'event_date' => '2020-01-01',
			'status' => 'completed',
		),
	),
	'adam_membership_event_next_id' => 8,
	'adam_membership_event_registrations' => array( 2 => array( 'id' => 2, 'event_id' => 4 ) ),
	'adam_membership_event_checkins' => array( 3 => array( 'id' => 3, 'event_id' => 4 ) ),
);
$GLOBALS['adam_actions'] = array();

function get_option( string $key, mixed $default = false ): mixed {
	return $GLOBALS['adam_options'][ $key ] ?? $default;
}
function update_option( string $key, mixed $value, bool $autoload = true ): bool {
	unset( $autoload );
	$GLOBALS['adam_options'][ $key ] = $value;
	return true;
}
function absint( mixed $value ): int { return abs( (int) $value ); }
function sanitize_title( string $value ): string { return trim( strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $value ) ?? '' ), '-' ); }
function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
function sanitize_textarea_field( string $value ): string { return trim( strip_tags( $value ) ); }
function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $value ) ?? '' ); }
function esc_url_raw( string $value ): string { return $value; }
function current_time( string $type, bool $gmt = false ): int|string {
	unset( $gmt );
	return 'timestamp' === $type ? strtotime( '2030-01-01 00:00:00' ) : '2030-01-01 00:00:00';
}
function home_url( string $path = '' ): string { return 'https://example.test' . $path; }
function attachment_url_to_postid( string $url ): int { return str_contains( $url, 'cover.jpg' ) ? 42 : 0; }
function __( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
function do_action( string $hook, mixed ...$args ): void { $GLOBALS['adam_actions'][] = array( $hook, $args ); }
function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed { unset( $hook, $args ); return $value; }

class WP_Error {
	public function __construct( public string $code = '', public string $message = '' ) {}
}

require dirname( __DIR__ ) . '/includes/events/class-event.php';
require dirname( __DIR__ ) . '/includes/events/class-repository.php';
require dirname( __DIR__ ) . '/includes/events/class-migration.php';
require dirname( __DIR__ ) . '/includes/events/class-api.php';

use ADAM\Comunidade\Events\Api;
use ADAM\Comunidade\Events\Migration;
use ADAM\Comunidade\Events\Repository;

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

Migration::run();
$canonical = get_option( Repository::OPTION_EVENTS, array() );
$assert( array( 4, 7 ) === array_keys( $canonical ), 'Legacy event IDs were not preserved.' );
$assert( 8 === get_option( Repository::OPTION_NEXT_ID ), 'The legacy next ID was not preserved.' );
$assert( 'https://example.test/cover.jpg' === $canonical[4]['cover_image'], 'Featured image was not preserved.' );
$assert( 42 === $canonical[4]['cover_id'], 'Featured image attachment was not resolved.' );
$assert( 'token-four' === $canonical[4]['checkin_token'], 'QR check-in token was not preserved.' );
$assert( 1 === $canonical[4]['checkin_enabled'], 'Check-in permission was not preserved.' );
$assert( 1 === $canonical[4]['location_id'], 'Legacy location was not migrated to the shared vocabulary.' );
$assert( 'Lousã' === ( new Repository() )->locations()[0]['name'], 'Shared event location was not created.' );
$assert( 1 === count( get_option( 'adam_membership_event_registrations' ) ), 'Member registrations were changed.' );
$assert( 1 === count( get_option( 'adam_membership_event_checkins' ) ), 'Member attendance was changed.' );
$assert( true === get_option( 'adam_comunidade_events_migration_report' )['legacy_events_preserved'], 'Rollback store is not marked as preserved.' );

$api = new Api( new Repository() );
$event = $api->get_event( 4 );
$assert( null !== $event && 'Jogo Floresta' === $event->title(), 'API lookup by ID failed.' );
$assert( 'jogo-floresta' === $api->get_event( 'jogo-floresta' )->slug(), 'API lookup by slug failed.' );
$assert( 'https://example.test/eventos/jogo-floresta/' === $api->event_url( $event ), 'Existing event URL changed.' );
$assert( 'https://example.test/eventos/check-in/token-four/' === $api->checkin_url( $event ), 'Existing check-in URL changed.' );
$assert( 1 === count( $api->upcoming_events() ), 'Upcoming-events query is incorrect.' );

$created = $api->save_event( array( 'title' => 'Novo Evento', 'event_date' => '2099-06-01', 'status' => 'draft' ) );
$assert( 8 === $created->id(), 'New events did not continue the legacy ID sequence.' );
$updated = $api->save_event( array_merge( $created->data(), array( 'title' => 'Evento Atualizado' ) ), 8 );
$assert( 'Evento Atualizado' === $updated->title(), 'Event update failed.' );
$assert( 'novo-evento' === $updated->slug(), 'Editing an event changed its established URL.' );
$api->delete_event( 8 );
$assert( null === $api->get_event( 8 ), 'Event deletion failed.' );

$before = get_option( Repository::OPTION_EVENTS );
Migration::run();
$assert( $before === get_option( Repository::OPTION_EVENTS ), 'Migration is not idempotent.' );

echo "Events migration tests passed.\n";
