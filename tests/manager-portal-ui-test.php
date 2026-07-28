<?php
/**
 * Contracts for the polished Community Manager portal interface.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$read = static fn( string $path ): string => (string) file_get_contents( $root . '/' . $path );
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$portal = $read( 'includes/managers/class-portal.php' );
$hero   = $read( 'includes/class-public-hero.php' );
$styles = $read( 'assets/css/public.css' );
$script = $read( 'assets/js/public.js' );
$assets = $read( 'includes/class-assets.php' );

$assert(
	str_contains( $portal, 'adam-manager-portal--' )
	&& str_contains( $portal, 'adam-manager-shell--auth' )
	&& str_contains( $styles, '.adam-manager-portal--auth' )
	&& str_contains( $styles, '.adam-manager-shell--auth' ),
	'Authentication and dashboard layouts do not have balanced route-aware centring.'
);
$assert(
	str_contains( $portal, "Public_Hero::root( 'adam-manager-header', 'dark' )" )
	&& str_contains( $portal, "Public_Hero::element( 'title' )" )
	&& str_contains( $styles, '.adam-public-hero--dark' )
	&& str_contains( $styles, '.adam-public-hero--light' ),
	'The portal and shared Hero system do not enforce background-aware title colours.'
);
foreach ( array( 'Iniciar Sessão', 'Criar Palavra-passe', 'Recuperar Palavra-passe', 'Gestor da Comunidade' ) as $title ) {
	$assert( str_contains( $portal, $title ), 'Missing route-aware portal heading: ' . $title );
}
$assert(
	str_contains( $portal, 'aria-labelledby="adam-manager-page-title"' )
	&& str_contains( $portal, 'id="adam-manager-page-title"' ),
	'Authentication forms are not associated with their visible page heading.'
);
$assert(
	str_contains( $script, 'function passwordIcon' )
	&& str_contains( $script, "toggle.setAttribute( 'aria-label'" )
	&& str_contains( $script, 'toggle.replaceChildren' )
	&& str_contains( $script, "toggle.setAttribute( 'aria-controls'" )
	&& ! str_contains( $script, 'toggle.textContent = labels.showPassword' ),
	'Password visibility is not implemented as an accessible icon-only control.'
);
$assert(
	str_contains( $styles, 'padding-right: 52px' )
	&& str_contains( $styles, '.adam-password-toggle svg' )
	&& ! str_contains( $styles, 'padding-right: 132px' ),
	'The password icon does not fit safely inside its input.'
);
$assert(
	str_contains( $assets, "'showPassword'" )
	&& str_contains( $assets, "'hidePassword'" ),
	'Password control accessible labels are not localized.'
);
$assert( str_contains( $hero, "'adam-public-hero--' . sanitize_html_class( \$variant )" ), 'Hero variants are not provided by the shared component.' );

echo "Manager portal UI tests passed.\n";
