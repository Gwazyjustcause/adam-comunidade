<?php
/**
 * Community API v2.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Directory\Router as Directory_Router;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Router as Field_Router;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Router as Team_Router;
use ADAM\Comunidade\Public_Privacy;

/**
 * Filterable read-only endpoints for the ADAM Bot and external applications.
 */
final class Api_V2 {
	public function __construct(
		private Discovery $discovery,
		private Team_Repository $teams,
		private Field_Repository $fields,
		private Directory_Repository $directory
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^api/v2/(teams|fields|partners|brands|institutions|news|search|map|statistics)/?$', 'index.php?rest_route=/adam-comunidade/v2/$matches[1]', 'top' );
		add_rewrite_rule( '^api/(teams|fields|partners|brands|institutions|news)/?$', 'index.php?rest_route=/adam-comunidade/v2/$matches[1]', 'top' );
	}

	public function routes(): void {
		foreach ( array( 'teams', 'fields', 'partners', 'brands', 'institutions', 'news', 'search', 'map', 'statistics' ) as $endpoint ) {
			register_rest_route(
				'adam-comunidade/v2',
				'/' . $endpoint,
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn( \WP_REST_Request $request ): \WP_REST_Response => $this->response( $endpoint, $request ),
					'permission_callback' => fn( \WP_REST_Request $request ): bool => (bool) apply_filters( 'adam_comunidade_api_permission', true, $endpoint, $request ),
					'args'                => $this->args(),
				)
			);
		}
	}

	public function response( string $endpoint, \WP_REST_Request $request ): \WP_REST_Response {
		$filters = array(
			'district' => (string) $request['district'], 'municipality' => (string) $request['municipality'], 'playing_style' => (string) $request['playing_style'], 'facility' => (string) $request['facility'],
			'partner_category' => (string) $request['category'], 'institution_category' => (string) $request['category'], 'recruitment' => (string) $request['recruitment'], 'featured' => $request['featured'] ? 1 : '',
		);
		if ( 'search' === $endpoint ) {
			$data = $this->discovery->search( (string) $request['search'], $filters, (int) $request['per_page'] );
			$data = apply_filters( 'adam_comunidade_api_v2_response', $data, $endpoint, $request );
			return new \WP_REST_Response( Public_Privacy::without_direct_contacts( is_array( $data ) ? $data : array() ) );
		}
		if ( 'map' === $endpoint ) {
			return new \WP_REST_Response( Public_Privacy::without_direct_contacts( $this->discovery->map_records( $filters ) ) );
		}
		if ( 'statistics' === $endpoint ) {
			$stats = $this->discovery->statistics( (string) $request['district'] );
			foreach ( array( 'newest_team' => 'team', 'newest_field' => 'field', 'largest_team' => 'team', 'newest_partner' => 'partner' ) as $key => $type ) {
				$item = $stats[ $key ];
				if ( $item ) {
					$url = 'team' === $type ? Team_Router::team_url( $item ) : ( 'field' === $type ? Field_Router::field_url( $item ) : Directory_Router::entry_url( $item ) );
					$stats[ $key ] = array( 'id' => (int) $item->id, 'name' => $item->name, 'url' => $url );
				}
			}
			return new \WP_REST_Response( $stats );
		}
		$page = max( 1, (int) $request['page'] );
		$per_page = max( 1, min( 100, (int) $request['per_page'] ) );
		$sorts = array( 'name' => 'name', 'newest' => 'created_at', 'updated' => 'updated_at', 'largest' => 'members' );
		$orderby = $sorts[ (string) $request['sort'] ] ?? 'name';
		$args = array( 'status' => 'published', 'search' => (string) $request['search'], 'district' => (string) $request['district'], 'page' => $page, 'per_page' => $per_page, 'orderby' => $orderby, 'order' => 'name' === $orderby ? 'ASC' : 'DESC' );
		if ( 'teams' === $endpoint ) {
			$result = $this->teams->query( $args + array( 'municipality' => (string) $request['municipality'], 'playing_style' => (string) $request['playing_style'], 'recruitment' => (string) $request['recruitment'], 'featured' => $request['featured'] ? 1 : '' ) );
			$data = array_map( fn( object $item ): array => $this->item( $item, 'team', Team_Router::team_url( $item ) ), $result['items'] );
		} elseif ( 'fields' === $endpoint ) {
			$facility_results = $this->discovery->search( '', $filters, 100 )['fields'];
			if ( $request['facility'] ) {
				$page_items = array_slice( $facility_results, ( $page - 1 ) * $per_page, $per_page );
				$data = $page_items;
				$result = array( 'total' => count( $facility_results ), 'pages' => (int) ceil( count( $facility_results ) / $per_page ) );
			} else {
				$result = $this->fields->query( $args + array( 'municipality' => (string) $request['municipality'], 'playing_style' => (string) $request['playing_style'], 'featured' => $request['featured'] ? 1 : '' ) );
				$data = array_map( fn( object $item ): array => $this->item( $item, 'field', Field_Router::field_url( $item ) ), $result['items'] );
			}
		} elseif ( 'news' === $endpoint ) {
			$posts = News::latest( $per_page, (string) $request['search'], (string) $request['district'], (bool) $request['featured'], false );
			$data = array_map( static fn( \WP_Post $post ): array => array( 'id' => $post->ID, 'type' => 'news', 'title' => get_the_title( $post ), 'summary' => get_the_excerpt( $post ), 'content' => apply_filters( 'the_content', $post->post_content ), 'url' => get_permalink( $post ), 'published_at' => get_post_time( DATE_ATOM, true, $post ) ), $posts );
			$result = array( 'total' => count( $data ), 'pages' => 1 );
		} else {
			$type = array( 'partners' => 'partner', 'brands' => 'brand', 'institutions' => 'institution' )[ $endpoint ];
			$result = $this->directory->query( $type, $args + array( 'category' => (string) $request['category'], 'featured' => $request['featured'] ? 1 : '' ) );
			$data = array_map( fn( object $item ): array => $this->item( $item, $type, Directory_Router::entry_url( $item ) ), $result['items'] );
		}
		$data = apply_filters( 'adam_comunidade_api_v2_response', $data, $endpoint, $request );
		$data = Public_Privacy::without_direct_contacts( is_array( $data ) ? $data : array() );
		$response = new \WP_REST_Response( $data );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );
		$response->header( 'Cache-Control', 'public, max-age=120, stale-while-revalidate=300' );
		return $response;
	}

	private function args(): array {
		return array(
			'page' => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint' ),
			'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100, 'sanitize_callback' => 'absint' ),
			'search' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'district' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'municipality' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'playing_style' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
			'facility' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'category' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
			'recruitment' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
			'featured' => array( 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'sort' => array( 'type' => 'string', 'default' => 'name', 'enum' => array( 'name', 'newest', 'updated', 'largest' ) ),
		);
	}

	private function item( object $item, string $type, string $url ): array {
		return array(
			'id' => (int) $item->id, 'type' => $type, 'name' => $item->name, 'slug' => $item->slug, 'url' => $url, 'summary' => $item->short_description ?? '', 'description' => wp_strip_all_tags( $item->full_description ?? '' ),
			'district' => $item->district ?? '', 'municipality' => $item->municipality ?? '', 'featured' => (bool) ( $item->featured ?? false ), 'latitude' => isset( $item->latitude ) ? (float) $item->latitude : null, 'longitude' => isset( $item->longitude ) ? (float) $item->longitude : null,
		);
	}
}
