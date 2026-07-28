<?php
/**
 * Isolated Community Manager authentication.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Config;
use ADAM\Comunidade\Logger;

/**
 * Authenticates managers without WordPress users or ADAM Members.
 */
final class Auth {
	private const COOKIE = 'adam_community_manager_session';
	private ?object $manager = null;
	private string $session_token = '';

	public function current(): ?object {
		if ( null !== $this->manager ) {
			return $this->manager;
		}
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $raw ) ) {
			return null;
		}

		global $wpdb;
		$now = current_time( 'mysql', true );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT m.*,s.id AS manager_session_id,s.last_seen_at AS manager_session_last_seen FROM ' . Schema::sessions_table() . ' s INNER JOIN ' . Schema::managers_table()
				. ' m ON m.id = s.manager_id WHERE s.token_hash = %s AND s.expires_at > %s AND m.status = %s',
				hash( 'sha256', $raw ),
				$now,
				'active'
			)
		);
		if ( ! $row ) {
			return null;
		}
		$security  = Config::manager_security();
		$last_seen = strtotime( (string) $row->manager_session_last_seen );
		if ( false === $last_seen || $last_seen < time() - $security['session_touch_interval'] ) {
			$wpdb->update(
				Schema::sessions_table(),
				array( 'last_seen_at' => $now ),
				array( 'id' => (int) $row->manager_session_id )
			);
			$wpdb->update(
				Schema::managers_table(),
				array( 'last_activity_at' => $now, 'updated_at' => $now ),
				array( 'id' => (int) $row->id )
			);
		}
		unset( $row->manager_session_id, $row->manager_session_last_seen );
		$this->session_token = $raw;
		$this->manager       = $row;
		return $row;
	}

	public function login( string $email, string $password ): bool {
		global $wpdb;
		$email = sanitize_email( $email );
		$row   = is_email( $email ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::managers_table() . ' WHERE email = %s', $email ) ) : null;
		$hash  = $row ? (string) $row->password_hash : '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
		$valid = password_verify( $password, $hash );
		if ( ! $row || 'active' !== $row->status || ! $valid ) {
			return false;
		}
		if ( ! $this->create_session( (int) $row->id ) ) {
			return false;
		}
		$wpdb->update(
			Schema::managers_table(),
			array( 'last_login_at' => current_time( 'mysql', true ), 'last_activity_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $row->id )
		);
		return true;
	}

	public function logout(): void {
		global $wpdb;
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( $raw ) {
			$wpdb->delete( Schema::sessions_table(), array( 'token_hash' => hash( 'sha256', $raw ) ) );
		}
		$this->set_cookie( '', time() - HOUR_IN_SECONDS );
		$this->manager       = null;
		$this->session_token = '';
	}

	public function csrf_token(): string {
		$this->current();
		return $this->session_token ? hash_hmac( 'sha256', 'adam-community-manager-csrf', $this->session_token ) : '';
	}

	public function verify_csrf( mixed $token ): bool {
		$expected = $this->csrf_token();
		return $expected && is_string( $token ) && hash_equals( $expected, $token );
	}

	private function create_session( int $manager_id ): bool {
		global $wpdb;
		try {
			$raw = bin2hex( random_bytes( 32 ) );
		} catch ( \Throwable $throwable ) {
			Logger::error( 'community_manager_session_token_failed', array( 'exception' => get_class( $throwable ) ) );
			return false;
		}
		$now = current_time( 'mysql', true );
		$security = Config::manager_security();
		$inserted = $wpdb->insert(
			Schema::sessions_table(),
			array(
				'manager_id'  => $manager_id,
				'token_hash'  => hash( 'sha256', $raw ),
				'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + $security['session_ttl'] ),
				'last_seen_at'=> $now,
				'created_at'  => $now,
			)
		);
		if ( false === $inserted ) {
			Logger::error( 'community_manager_session_create_failed', array( 'manager_id' => $manager_id ) );
			return false;
		}
		$this->session_token = $raw;
		if ( ! $this->set_cookie( $raw, time() + $security['session_ttl'] ) ) {
			$wpdb->delete( Schema::sessions_table(), array( 'token_hash' => hash( 'sha256', $raw ) ) );
			$this->session_token = '';
			return false;
		}

		$session_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . Schema::sessions_table() . ' WHERE manager_id=%d ORDER BY created_at DESC',
				$manager_id
			)
		);
		foreach ( array_slice( array_map( 'intval', is_array( $session_ids ) ? $session_ids : array() ), $security['max_sessions_per_manager'] ) as $session_id ) {
			$wpdb->delete( Schema::sessions_table(), array( 'id' => $session_id ) );
		}
		return true;
	}

	private function set_cookie( string $value, int $expires ): bool {
		if ( headers_sent() ) {
			Logger::error( 'community_manager_cookie_headers_sent' );
			return false;
		}
		$set = setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		if ( '' === $value ) {
			unset( $_COOKIE[ self::COOKIE ] );
		} else {
			$_COOKIE[ self::COOKIE ] = $value;
		}
		return $set;
	}
}
