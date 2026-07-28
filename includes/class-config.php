<?php
/**
 * Central runtime policies.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Provides bounded, filterable operational defaults without storing secrets.
 */
final class Config {
	public const STATUS_DRAFT     = 'draft';
	public const STATUS_PUBLISHED = 'published';
	public const STATUS_HIDDEN    = 'hidden';
	public const STATUS_ACTIVE    = 'active';
	public const STATUS_DISABLED  = 'disabled';

	public const DEFAULT_PAGE_SIZE = 20;
	public const PUBLIC_PAGE_SIZE  = 12;
	public const MAX_PAGE_SIZE     = 100;

	/**
	 * Returns a bounded cache lifetime.
	 */
	public static function cache_ttl( string $context = 'default', int $default = 300 ): int {
		return max(
			1,
			min(
				DAY_IN_SECONDS,
				(int) apply_filters( 'adam_comunidade_cache_ttl', $default, sanitize_key( $context ) )
			)
		);
	}

	/**
	 * Returns the media policy for a known upload context.
	 *
	 * @return array{extensions:string[],max_files:int,max_size_mb:int}
	 */
	public static function upload_policy( string $context ): array {
		$policies = array(
			'manager_image'   => array( 'extensions' => array( 'jpg', 'jpeg', 'png', 'webp' ), 'max_files' => 1, 'max_size_mb' => 10 ),
			'manager_gallery' => array( 'extensions' => array( 'jpg', 'jpeg', 'png', 'webp' ), 'max_files' => 20, 'max_size_mb' => 10 ),
		);
		$context = sanitize_key( $context );
		$policy  = $policies[ $context ] ?? $policies['manager_image'];
		$policy  = (array) apply_filters( 'adam_comunidade_upload_policy', $policy, $context );

		$extensions = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( mixed $extension ): string => strtolower( ltrim( sanitize_key( (string) $extension ), '.' ) ),
						(array) ( $policy['extensions'] ?? array() )
					)
				)
			)
		);

		return array(
			'extensions'  => $extensions ?: $policies['manager_image']['extensions'],
			'max_files'   => max( 1, min( 100, absint( $policy['max_files'] ?? 1 ) ) ),
			'max_size_mb' => max( 1, min( 100, absint( $policy['max_size_mb'] ?? 10 ) ) ),
		);
	}

	/**
	 * Returns bounded Community Manager security and retention settings.
	 *
	 * @return array<string,int>
	 */
	public static function manager_security(): array {
		$defaults = array(
			'invitation_ttl'             => 14 * DAY_IN_SECONDS,
			'password_reset_ttl'         => HOUR_IN_SECONDS,
			'session_ttl'                => 14 * DAY_IN_SECONDS,
			'session_touch_interval'     => 5 * MINUTE_IN_SECONDS,
			'max_sessions_per_manager'   => 5,
			'login_attempt_limit'        => 8,
			'login_lockout_ttl'          => 15 * MINUTE_IN_SECONDS,
			'reset_request_limit'        => 3,
			'reset_rate_window'          => HOUR_IN_SECONDS,
			'invitation_history_ttl'     => 90 * DAY_IN_SECONDS,
			'password_reset_history_ttl' => 7 * DAY_IN_SECONDS,
			'unclaimed_manager_ttl'      => 180 * DAY_IN_SECONDS,
			'processing_recovery_ttl'    => HOUR_IN_SECONDS,
		);
		$settings = (array) apply_filters( 'adam_comunidade_manager_security_policy', $defaults );

		$count_keys = array( 'max_sessions_per_manager', 'login_attempt_limit', 'reset_request_limit' );
		foreach ( $defaults as $key => $default ) {
			$value = max( 1, absint( $settings[ $key ] ?? $default ) );
			$settings[ $key ] = in_array( $key, $count_keys, true )
				? min( 100, $value )
				: min( 5 * YEAR_IN_SECONDS, $value );
		}
		$settings['max_sessions_per_manager'] = min( 20, $settings['max_sessions_per_manager'] );
		$settings['session_touch_interval']   = min( $settings['session_touch_interval'], $settings['session_ttl'] );

		return $settings;
	}

	private function __construct() {}
}
