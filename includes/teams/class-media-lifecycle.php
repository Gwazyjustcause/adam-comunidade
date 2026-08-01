<?php
/**
 * Safe lifecycle management for Team-owned Media Library attachments.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Schema as Directory_Schema;
use ADAM\Comunidade\Events\Repository as Event_Repository;
use ADAM\Comunidade\Fields\Schema as Field_Schema;

/** Deletes media only when it is owned exclusively by one Team. */
final class Media_Lifecycle {
	private const OWNER_META = '_adam_comunidade_team_id';

	/** Marks attachments created specifically for a Team. */
	public static function claim( int $team_id, array $attachment_ids ): void {
		foreach ( self::ids( $attachment_ids ) as $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) || get_post_meta( $attachment_id, self::OWNER_META, true ) || self::used_elsewhere( $attachment_id, $team_id ) ) {
				continue;
			}
			update_post_meta( $attachment_id, self::OWNER_META, $team_id );
		}
	}

	/** Deletes attachments removed by a successfully saved Team update. */
	public static function delete_removed( int $team_id, array $before, array $after ): void {
		foreach ( array_diff( self::ids( $before ), self::ids( $after ) ) as $attachment_id ) {
			self::delete_if_exclusive( $team_id, $attachment_id );
		}
	}

	/** Deletes every exclusive image after the Team row was deleted. */
	public static function delete_team_media( object $team ): void {
		self::delete_removed( (int) $team->id, self::team_ids( $team ), array() );
	}

	/** Returns logo, cover and gallery attachment IDs. */
	public static function team_ids( object|array $team ): array {
		$team = (array) $team;
		return self::ids( array_merge( array( $team['logo_id'] ?? 0, $team['cover_id'] ?? 0 ), Options::decode_ids( $team['gallery'] ?? array() ) ) );
	}

	private static function delete_if_exclusive( int $team_id, int $attachment_id ): bool {
		if ( ! current_user_can( 'upload_files' ) || (int) get_post_meta( $attachment_id, self::OWNER_META, true ) !== $team_id || self::used_elsewhere( $attachment_id, $team_id ) ) {
			return false;
		}
		return (bool) wp_delete_attachment( $attachment_id, true );
	}

	/** Checks all first-party stores that can reference an attachment. */
	private static function used_elsewhere( int $attachment_id, int $team_id ): bool {
		global $wpdb;
		$teams = $wpdb->get_results( $wpdb->prepare( 'SELECT id,logo_id,cover_id,gallery FROM ' . Schema::teams_table() . ' WHERE id <> %d', $team_id ) ) ?: array();
		foreach ( $teams as $team ) {
			if ( in_array( $attachment_id, self::team_ids( $team ), true ) ) {
				return true;
			}
		}

		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Field_Schema::fields_table() . ' WHERE cover_id=%d OR authorization_document_id=%d LIMIT 1', $attachment_id, $attachment_id ) )
			|| $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Field_Schema::galleries_table() . ' WHERE attachment_id=%d LIMIT 1', $attachment_id ) )
			|| $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Directory_Schema::entries_table() . ' WHERE logo_id=%d OR cover_id=%d OR promo_pdf_id=%d OR catalogue_id=%d LIMIT 1', $attachment_id, $attachment_id, $attachment_id, $attachment_id ) )
			|| $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Directory_Schema::galleries_table() . ' WHERE attachment_id=%d LIMIT 1', $attachment_id ) )
			|| $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_thumbnail_id' AND meta_value=%d LIMIT 1", $attachment_id ) ) ) {
			return true;
		}

		foreach ( ( new Event_Repository() )->raw() as $event ) {
			if ( $attachment_id === absint( $event['cover_id'] ?? 0 ) ) {
				return true;
			}
		}
		foreach ( array( Hero_Carousel::OPTION, \ADAM\Comunidade\Fields\Hero_Carousel::OPTION ) as $option ) {
			$settings = (array) get_option( $option, array() );
			foreach ( (array) ( $settings['images'] ?? array() ) as $image ) {
				if ( $attachment_id === absint( ( (array) $image )['id'] ?? 0 ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function ids( array $ids ): array {
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private function __construct() {}
}
