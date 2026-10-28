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
				'district' => $args['district'], 'municipality' => 'Concelho',
				'latitude' => 1 === $args['page'] ? 40.2 : null, 'longitude' => 1 === $args['page'] ? -8.4 : null,
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
	class Managed_Pages { public static function is_current( string $type ): bool { return $GLOBALS['community']; } public static function url( string $type ): string { return 'https://example.org/' . $type . '/'; } }
	class Helpers { public static function url( string $path ): string { return $path; } }
}
namespace ADAM\Comunidade\Experience {
	class Discovery {}
	class Templates { public static function locate( string $path ): string { return $path; } }
	class Portal { public static function submission_url( string $type ): string { return 'https://example.org/submeter-' . $type . '/'; } }
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
	check( count( array_filter( $records, static fn( array $record ): bool => $record['approximate'] ) ) === 12, 'Records without coordinates must remain on the map.' );
	$coimbra = array_values( array_filter( $records, static fn( array $record ): bool => 'Coimbra' === $record['district'] && $record['approximate'] ) );
	check( 40.2033 === $coimbra[0]['latitude'] && -8.4103 === $coimbra[0]['longitude'], 'Coimbra district fallback must be in Coimbra.' );
	check( ! $records[0]['approximate'] && 40.2 === $records[0]['latitude'], 'Precise coordinates must be preserved.' );
	foreach ( array( null, '', 'invalid', 0, 90 ) as $latitude ) {
		check( ! Community_Map::has_location( (object) array( 'district' => 'Coimbra', 'latitude' => $latitude, 'longitude' => -8.4 ) ), 'Invalid coordinates accepted.' );
		check( Community_Map::location( (object) array( 'district' => 'Coimbra', 'latitude' => $latitude, 'longitude' => -8.4 ) )['approximate'], 'Invalid coordinates must fall back to the district.' );
	}
	check( ! Community_Map::has_location( (object) array( 'district' => 'Lisboa', 'latitude' => 40.2, 'longitude' => -8.4 ) ), 'Outside district accepted.' );
	check( null === Community_Map::location( (object) array( 'district' => 'Lisboa' ) ), 'Outside district must not receive a fallback.' );
	check( Community_Map::has_location( (object) array( 'district' => 'Leiria', 'latitude' => 39.267, 'longitude' => -9.15 ) ), 'Southern Leiria excluded.' );
	$html = Community_Map::render();
	check( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ), 'Names must be escaped.' );
	check( str_contains( $html, 'data-map-results' ) && str_contains( $html, 'https://example.org/team/2' ), 'Server fallback must include later pages.' );
	check( str_contains( $html, 'Localização aproximada' ), 'Approximate locations must be explained without JavaScript.' );
	function get_header(): void {}
	function get_footer(): void {}
	function do_shortcode( string $shortcode ): string { return Community_Map::render(); }
	ob_start();
	include ADAM_COMUNIDADE_PATH . 'templates/experience/community.php';
	$hub = (string) ob_get_clean();
	check( substr_count( $hub, 'data-adam-map=' ) === 1, 'The community hub must render exactly one map.' );
	check( strpos( $hub, 'data-adam-map=' ) < strpos( $hub, 'adam-community-hub__contribute' ), 'The map must precede contribution content.' );
	check( str_contains( $hub, 'https://example.org/teams/' ) && str_contains( $hub, 'https://example.org/submeter-team/' ), 'Directory and submission links missing.' );
	check( ! str_contains( $hub, 'wp-block-cover' ) && ! str_contains( $hub, 'adam-live-stats' ), 'Promotional hero and statistics must not precede the map.' );
	function get_query_var( string $key ): mixed { return $GLOBALS['hub_query'][$key] ?? ''; }
	require ADAM_COMUNIDADE_PATH . 'includes/experience/class-router.php';
	$router = new \ADAM\Comunidade\Experience\Router( new \ADAM\Comunidade\Experience\Discovery() );
	$GLOBALS['community'] = true;
	check( $router->template( 'theme.php' ) === 'experience/community.php', 'Managed community route must show the redesigned page.' );
	$GLOBALS['hub_query'] = array( 'adam_compare' => 1 );
	check( $router->template( 'theme.php' ) === 'experience/compare.php', 'Comparison route must retain precedence.' );
	$GLOBALS['hub_query'] = array( 'adam_region' => 'coimbra' );
	check( $router->template( 'theme.php' ) === 'experience/region.php', 'Region route must retain precedence.' );
	$GLOBALS['hub_query'] = array(); $GLOBALS['community'] = false;
	check( $router->template( 'theme.php' ) === 'theme.php', 'Unrelated pages must keep their theme template.' );
	echo "Community map tests passed.\n";
}
