<?php
/**
 * Safe legacy Events migration.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Logger;

/**
 * Imports ADAM Sócios event content without deleting rollback data.
 */
final class Migration {
	public const VERSION = '1.0.0';
	private const VERSION_OPTION = 'adam_comunidade_events_migration_version';
	private const LEGACY_EVENTS = 'adam_membership_events';
	private const LEGACY_NEXT_ID = 'adam_membership_event_next_id';

	public static function run(): void {
		if ( self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}
		$legacy = get_option( self::LEGACY_EVENTS, array() );
		$repository = new Repository();
		$current = $repository->raw();
		$locations = $repository->locations();
		$location_ids = array();
		$next_location_id = 1;
		foreach ( $locations as $location ) {
			$stored_location_id = absint( $location['id'] ?? 0 );
			$location_ids[ sanitize_title( (string) ( $location['name'] ?? '' ) ) ] = $stored_location_id;
			$next_location_id = max( $next_location_id, $stored_location_id + 1 );
		}
		$imported = 0;
		if ( is_array( $legacy ) ) {
			foreach ( $legacy as $legacy_id => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$id = absint( $item['id'] ?? $legacy_id );
				if ( ! $id || isset( $current[ $id ] ) ) {
					continue;
				}
				$item['migration_source'] = 'adam-membership';
				$item['legacy_id'] = $id;
				if ( empty( $item['cover_id'] ) && ! empty( $item['cover_image'] ) && function_exists( 'attachment_url_to_postid' ) ) {
					$item['cover_id'] = absint( attachment_url_to_postid( (string) $item['cover_image'] ) );
				}
				$location_name = sanitize_text_field( (string) ( $item['location'] ?? '' ) );
				if ( $location_name ) {
					$location_key = sanitize_title( $location_name );
					if ( empty( $location_ids[ $location_key ] ) ) {
						$location_id = $next_location_id++;
						$locations[] = array(
							'id' => $location_id,
							'name' => $location_name,
							'slug' => $location_key,
							'map_link' => esc_url_raw( (string) ( $item['map_link'] ?? '' ) ),
						);
						$location_ids[ $location_key ] = $location_id;
					}
					$item['location_id'] = $location_ids[ $location_key ];
				}
				$saved = $repository->save( $item, $id );
				if ( is_wp_error( $saved ) ) {
					Logger::error( 'community_event_migration_failed', array( 'legacy_event_id' => $id ) );
					return;
				}
				$current[ $id ] = $item;
				++$imported;
			}
		}
		if ( $locations && ! $repository->save_taxonomy( 'locations', $locations ) ) {
			Logger::error( 'community_event_migration_failed', array( 'operation' => 'locations' ) );
			return;
		}
		if ( ! $repository->ensure_next_id( absint( get_option( self::LEGACY_NEXT_ID, 1 ) ) ) ) {
			Logger::error( 'community_event_migration_failed', array( 'operation' => 'next_id' ) );
			return;
		}
		update_option( self::VERSION_OPTION, self::VERSION, false );
		update_option(
			'adam_comunidade_events_migration_report',
			array(
				'completed_at' => current_time( 'mysql', true ),
				'imported_events' => $imported,
				'legacy_events_preserved' => true,
				'registrations_owner' => 'adam-membership',
				'checkins_owner' => 'adam-membership',
			),
			false
		);
	}
}
