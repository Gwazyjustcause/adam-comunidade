<?php
/**
 * REST response cache.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Caches successful public API v2 GET responses with versioned keys.
 */
final class Rest_Cache {
	public function register(): void {
		add_filter( 'rest_pre_dispatch', array( $this, 'read' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'write' ), 10, 3 );
	}

	public function read( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ): mixed {
		unset( $server );
		if ( null !== $result || 'GET' !== $request->get_method() || ! str_starts_with( $request->get_route(), '/adam-comunidade/v2/' ) ) {
			return $result;
		}
		$cached = get_transient( $this->key( $request ) );
		if ( ! is_array( $cached ) ) {
			return $result;
		}
		$response = new \WP_REST_Response( $cached['data'], $cached['status'], $cached['headers'] );
		$response->header( 'X-ADAM-Cache', 'HIT' );
		return $response;
	}

	public function write( \WP_HTTP_Response $response, \WP_REST_Server $server, \WP_REST_Request $request ): \WP_HTTP_Response {
		unset( $server );
		if ( 'GET' === $request->get_method() && str_starts_with( $request->get_route(), '/adam-comunidade/v2/' ) && $response->get_status() < 300 ) {
			set_transient(
				$this->key( $request ),
				array( 'data' => $response->get_data(), 'status' => $response->get_status(), 'headers' => $response->get_headers() ),
				120
			);
			$response->header( 'X-ADAM-Cache', 'MISS' );
		}
		return $response;
	}

	private function key( \WP_REST_Request $request ): string {
		$version = (string) get_option( 'adam_comunidade_cache_version', '1' );
		return 'adam_rest_' . md5( $version . '|' . $request->get_route() . '|' . wp_json_encode( $request->get_query_params() ) );
	}
}
