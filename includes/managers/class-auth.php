<?php
/**
 * Isolated Community Manager authentication.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

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
				'SELECT m.* FROM ' . Schema::sessions_table() . ' s INNER JOIN ' . Schema::managers_table()
				. ' m ON m.id = s.manager_id WHERE s.token_hash = %s AND s.expires_at > %s AND m.status = %s',
				hash( 'sha256', $raw ),
				$now,
				'active'
			)
		);
		if ( ! $row ) {
			return null;
		}
		$this->session_token = $raw;
		$this->manager       = $row;
		return $row;
	}

	public function login( string $email, string $password ): bool {
		global $wpdb;
		$email = sanitize_email( $email );
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::managers_table() . ' WHERE email = %s', $email ) );
		if ( ! $row || 'active' !== $row->status || ! password_verify( $password, (string) $row->password_hash ) ) {
			return false;
		}
		$this->create_session( (int) $row->id );
		$wpdb->update(
			Schema::managers_table(),
			array( 'last_login_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ),
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

	private function create_session( int $manager_id ): void {
		global $wpdb;
		$raw = bin2hex( random_bytes( 32 ) );
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Schema::sessions_table(),
			array(
				'manager_id'  => $manager_id,
				'token_hash'  => hash( 'sha256', $raw ),
				'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + 14 * DAY_IN_SECONDS ),
				'last_seen_at'=> $now,
				'created_at'  => $now,
			)
		);
		$this->session_token = $raw;
		$this->set_cookie( $raw, time() + 14 * DAY_IN_SECONDS );
	}

	private function set_cookie( string $value, int $expires ): void {
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN ?: '',
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
	}
}
