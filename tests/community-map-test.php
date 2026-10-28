<?php
/** Runtime coverage for regional map data and editorial insertion. */
declare(strict_types=1);

namespace ADAM\Comunidade\Teams {
	class Repository {
		public function query( array $args ): array {
			if ( 'published' !== $args['status'] || ! in_array( $args['district'], \ADAM\Comunidade\Directory\Community_Map::DISTRICTS, true ) ) {
				throw new \RuntimeException( 'Map queries must restrict status and district.' );
			}
			return array( 'pages' => 2, 'items' => array( (object) array(
				'id' => $args['page'], 'name' => 'Test <script>alert(1)</script> ' . $args['district'],
				'district' => $args['district'], 'municipality' => 'Concelho', 'latitude' => 40.2, 'longitude' => -8.4,
			) ) );
		}
	}
	class Router { public static function team_url( object $item ): string { return 'https://example.org/team/' . $item->id; } }
}
namespace ADAM\Comunidade\Fields {
	class Repository extends \ADAM\Comunidade\Teams\Repository {}
	class Router { public static function field_url( object $item ): string { return 'https://example.org/field/' . $item->id; } }
}
namespace ADAM\Comunidade {
	class Managed_Pages { public static function is_current( string $type ): bool { return $GLOBALS['community']; } }
	class Helpers { public static function url( string $path ): string { return $path; } }
}
namespace ADAM\Comunidade\Directory {
	class Repository {}
	class Relationship_Repository {}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'ADAM_COMUNIDADE_PATH', dirname( __DIR__ ) . '/' );
	define( 'ADAM_COMUNIDADE_VERSION', 'test' );
	function wp_enqueue_style( ...$args ): void {}
	function wp_enqueue_script( ...$args ): void {}
	function wp_localize_script( ...$args ): void {}
	function apply_filters( string $hook, mixed $value ): mixed { return $value; }
	function __( string $value, string $domain = '' ): string { return $value; }
	function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES ); }
	function esc_attr( string $value ): string { return esc_html( $value ); }
	function esc_url( string $value ): string { return esc_attr( $value ); }
	function esc_html_e( string $value, string $domain ): void { echo esc_html( $value ); }
	function esc_attr_e( string $value, string $domain ): void { echo esc_attr( $value ); }
	function wp_json_encode( mixed $value ): string { return json_encode( $value ); }
	function wp_unique_id( string $prefix ): string { static $id = 0; return $prefix . ++$id; }
	function is_admin(): bool { return false; }
	function is_main_query(): bool { return true; }
	function in_the_loop(): bool { return true; }
	require ADAM_COMUNIDADE_PATH . 'includes/class-config.php';
	require ADAM_COMUNIDADE_PATH . 'includes/directory/class-community-map.php';
	require ADAM_COMUNIDADE_PATH . 'includes/directory/class-components.php';
	use ADAM\Comunidade\Directory\Community_Map;
	function check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
	$records = Community_Map::markers();
	check( count( $records ) === 24, 'Must read both pages of both types across all six districts.' );
	foreach ( array( null, '', 'invalid', 0, 90 ) as $latitude ) {
		check( ! Community_Map::has_location( (object) array( 'district' => 'Coimbra', 'latitude' => $latitude, 'longitude' => -8.4 ) ), 'Invalid coordinates accepted.' );
	}
	check( ! Community_Map::has_location( (object) array( 'district' => 'Lisboa', 'latitude' => 40.2, 'longitude' => -8.4 ) ), 'Outside district accepted.' );
	check( Community_Map::has_location( (object) array( 'district' => 'Leiria', 'latitude' => 39.267, 'longitude' => -9.15 ) ), 'Southern Leiria excluded.' );
	$html = Community_Map::render();
	check( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ), 'Names must be escaped.' );
	check( str_contains( $html, 'data-map-results' ) && str_contains( $html, 'https://example.org/team/2' ), 'Server fallback must include later pages.' );
	$components = new \ADAM\Comunidade\Directory\Components( new \ADAM\Comunidade\Directory\Repository(), new \ADAM\Comunidade\Directory\Relationship_Repository() );
	$GLOBALS['community'] = true;
	check( $components->community_map_content( $html ) === $html, 'Existing map duplicated.' );
	$content = '<p>Editorial introduction</p><div class="wp-block-group alignfull adam-community-landing__cta"><p>Join us</p></div>';
	$inserted = $components->community_map_content( $content );
	check( str_starts_with( $inserted, '<p>Editorial introduction</p>' ) && str_ends_with( $inserted, '<p>Join us</p></div>' ), 'Editorial content must be preserved.' );
	check( strpos( $inserted, 'data-adam-map=' ) < strpos( $inserted, 'adam-community-landing__cta' ), 'Map must appear before the participation CTA.' );
	check( substr_count( $components->community_map_content( $inserted ), 'data-adam-map=' ) === 1, 'Automatically inserted map duplicated.' );
	$GLOBALS['community'] = false;
	check( $components->community_map_content( '<p>Existing page</p>' ) === '<p>Existing page</p>', 'Non-community content changed.' );
	echo "Community map tests passed.\n";
}
