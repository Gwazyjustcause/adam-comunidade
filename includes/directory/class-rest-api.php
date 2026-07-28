<?php
/**
 * Read-only community REST API.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Router as Field_Router;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Router as Team_Router;
use ADAM\Comunidade\Public_Privacy;

/**
 * Exposes published community content for apps, bots, maps, and integrations.
 */
final class Rest_API {
	public function __construct( private Repository $repository ) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^api/(equipas|campos|parceiros|marcas|instituicoes)/?$', 'index.php?rest_route=/adam-comunidade/v1/$matches[1]', 'top' );
	}

	public function register_routes(): void {
		foreach ( array( 'equipas', 'campos', 'parceiros', 'marcas', 'instituicoes' ) as $endpoint ) {
			register_rest_route(
				'adam-comunidade/v1',
				'/' . $endpoint,
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn( \WP_REST_Request $request ) => $this->collection( $endpoint, $request ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1, 'sanitize_callback' => 'absint' ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20, 'sanitize_callback' => 'absint' ),
						'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					),
				)
			);
		}
	}

	public function collection( string $endpoint, \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'status'   => 'published',
			'search'   => (string) $request->get_param( 'search' ),
			'page'     => (int) $request->get_param( 'page' ),
			'per_page' => (int) $request->get_param( 'per_page' ),
			'orderby'  => 'name',
			'order'    => 'ASC',
		);
		if ( 'equipas' === $endpoint ) {
			$result = ( new Team_Repository() )->query( $args );
			$data   = array_map( fn( object $item ): array => $this->core_item( $item, Team_Router::team_url( $item ), 'team', (int) $item->logo_id, (int) $item->cover_id ), $result['items'] );
		} elseif ( 'campos' === $endpoint ) {
			$result = ( new Field_Repository() )->query( $args );
			$data   = array_map( fn( object $item ): array => $this->core_item( $item, Field_Router::field_url( $item ), 'field', 0, (int) $item->cover_id ), $result['items'] );
		} else {
			$type   = 'instituicoes' === $endpoint ? 'institution' : 'partner';
			if ( 'marcas' === $endpoint ) {
				$args['category'] = 'brand';
			}
			$result = $this->repository->query( $type, $args );
			$this->repository->prime_galleries( array_map( static fn( object $item ): int => (int) $item->id, $result['items'] ) );
			$data   = array_map( fn( object $item ): array => $this->directory_item( $item ), $result['items'] );
		}
		$response = new \WP_REST_Response( $data );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );
		return $response;
	}

	private function core_item( object $item, string $url, string $type, int $logo_id, int $cover_id ): array {
		return Public_Privacy::without_direct_contacts( array(
			'id'                => (int) $item->id,
			'type'              => $type,
			'name'              => $item->name,
			'slug'              => $item->slug,
			'url'               => $url,
			'short_description' => $item->short_description,
			'description'       => wp_strip_all_tags( $item->full_description ),
			'district'          => $item->district,
			'municipality'      => $item->municipality,
			'address'           => $item->address,
			'coordinates'       => null !== $item->latitude && null !== $item->longitude ? array( 'latitude' => (float) $item->latitude, 'longitude' => (float) $item->longitude ) : null,
			'website'           => $item->website,
			'playing_styles'    => isset( $item->playing_styles ) ? ( json_decode( $item->playing_styles, true ) ?: array() ) : array(),
			'members'           => isset( $item->members ) ? (int) $item->members : null,
			'recruitment_status'=> $item->recruitment_status ?? null,
			'capacity'          => isset( $item->max_players ) ? array( 'maximum' => (int) $item->max_players, 'minimum' => (int) $item->min_players, 'recommended' => (int) $item->recommended_players ) : null,
			'logo'              => $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : null,
			'cover'             => $cover_id ? wp_get_attachment_image_url( $cover_id, 'full' ) : null,
			'updated_at'        => mysql_to_rfc3339( $item->updated_at ),
		) );
	}

	private function directory_item( object $item ): array {
		$definition = Types::get( $item->entity_type );
		return Public_Privacy::without_direct_contacts( array(
			'id'                => (int) $item->id,
			'type'              => $item->entity_type,
			'name'              => $item->name,
			'slug'              => $item->slug,
			'url'               => Router::entry_url( $item ),
			'short_description' => $item->short_description,
			'description'       => wp_strip_all_tags( $item->full_description ),
			'category'          => array( 'key' => $item->category, 'label' => $definition['categories'][ $item->category ] ?? '' ),
			'district'          => $item->district,
			'country'           => $item->country,
			'coordinates'       => null !== $item->latitude && null !== $item->longitude ? array( 'latitude' => (float) $item->latitude, 'longitude' => (float) $item->longitude ) : null,
			'featured'          => (bool) $item->featured,
			'homepage_featured' => (bool) $item->homepage_featured,
			'benefits'          => wp_strip_all_tags( $item->benefits ),
			'popular_products'  => $item->popular_products,
			'official_distributor' => (bool) $item->official_distributor,
			'website'           => $item->website,
			'facebook'          => $item->facebook,
			'instagram'         => $item->instagram,
			'address'           => $item->address,
			'logo'              => $item->logo_id ? wp_get_attachment_image_url( $item->logo_id, 'full' ) : null,
			'cover'             => $item->cover_id ? wp_get_attachment_image_url( $item->cover_id, 'full' ) : null,
			'gallery'           => array_values(
				array_filter(
					array_map(
						static fn( object $image ): string|false => wp_get_attachment_image_url( (int) $image->attachment_id, 'full' ),
						$this->repository->gallery( (int) $item->id )
					)
				)
			),
			'updated_at'        => mysql_to_rfc3339( $item->updated_at ),
		) );
	}
}
