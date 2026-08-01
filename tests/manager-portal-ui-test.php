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
$uploads = $read( 'assets/css/upload.css' );
$script = $read( 'assets/js/public.js' );
$assets = $read( 'includes/class-assets.php' );

$assert(
	str_contains( $portal, 'adam-manager-portal--' )
	&& str_contains( $portal, 'adam-manager-shell--auth' )
	&& str_contains( $portal, 'class="adam-manager-content"' )
	&& str_contains( $styles, '.adam-manager-portal--auth' )
	&& str_contains( $styles, '.adam-manager-shell--auth .adam-manager-content' )
	&& str_contains( $styles, 'justify-items: center' ),
	'Authentication and dashboard layouts do not have balanced route-aware centring.'
);
$assert(
	str_contains( $portal, "Public_Hero::root( 'adam-manager-header', 'dark' )" )
	&& str_contains( $portal, "Public_Hero::element( 'title' )" )
	&& str_contains( $styles, '.adam-public-hero--dark' )
	&& str_contains( $styles, '.adam-public-hero--light' ),
	'The portal and shared Hero system do not enforce background-aware title colours.'
);
foreach ( array( 'Criar Palavra-passe', 'Recuperar Palavra-passe', 'Gestor da Comunidade' ) as $title ) {
	$assert( str_contains( $portal, $title ), 'Missing route-aware portal heading: ' . $title );
}
$assert(
	str_contains( $portal, 'Gerir equipas, campos, parceiros e outras organizações da Comunidade ADAM.' ),
	'The login Hero does not describe the purpose of the Community Manager portal.'
);
$hero_start  = strpos( $portal, '<header class=' );
$hero_length = strpos( $portal, '</header>' ) - $hero_start;
$hero_markup = substr( $portal, $hero_start, $hero_length );
$assert(
	! str_contains( $hero_markup, 'recovery_url()' )
	&& str_contains( $portal, '<p><a href="<?php echo esc_url( self::recovery_url() ); ?>"' ),
	'Password recovery must appear below the login form, not in the Hero.'
);
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
$assert(
	str_contains( $styles, 'background: var(--adam-bg, var(--theme-palette-color-8, #f4f7f3));' )
	&& str_contains( $styles, 'body.adam-theme-dark .adam-manager-portal' )
	&& str_contains( $uploads, 'body.adam-theme-dark .adam-manager-portal .adam-upload' )
	&& ! str_contains( $styles . $uploads, '@media (prefers-color-scheme: dark)' ),
	'The manager portal must inherit the shared Light canvas and activate its dark presentation only through ADAM UI.'
);

echo "Manager portal UI tests passed.\n";
