<?php
/**
 * Standalone checks for the adaptive, privacy-focused Field page.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$template = (string) file_get_contents( $root . '/templates/fields/single.php' );
$portal   = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$style    = (string) file_get_contents( $root . '/assets/css/fields-public.css' );
$related  = (string) file_get_contents( $root . '/includes/experience/class-related-content.php' );
$links    = (string) file_get_contents( $root . '/includes/directory/class-components.php' );

foreach ( array( '$contacts', 'mailto:', 'tel:', "'Contact'", "'Upcoming Events'", 'Integração disponível numa fase futura.' ) as $removed ) {
	$assert( ! str_contains( $template, $removed ), 'Private or placeholder Field-page output remains: ' . $removed );
}

foreach ( array( '$capacity = array_filter', 'if ( $capacity )', '$has_description', '$has_rules', 'wp_attachment_is_image', '$has_mobile_actions' ) as $condition ) {
	$assert( str_contains( $template, $condition ), 'Missing adaptive Field-page condition: ' . $condition );
}

foreach ( array( 'admin_post_adam_claim_listing', 'claim_field', 'claim_team', 'claim_box', 'Esta é a sua organização? Peça a gestão desta página' ) as $claim_contract ) {
	$assert( ! str_contains( $portal, $claim_contract ), 'Public page claiming remains enabled: ' . $claim_contract );
}

$assert( str_contains( $style, 'repeat(auto-fit, minmax(150px, 1fr))' ), 'Capacity must reflow for the number of available values.' );
$assert( str_contains( $style, '.adam-associated-team-card--without-logo' ), 'Associated teams without a logo need a complete one-column layout.' );
$assert( str_contains( $style, '.adam-field-single.has-mobile-actions .adam-field-content' ), 'Mobile spacing must depend on whether actions exist.' );
$assert( str_contains( $related, 'return $cards ?' ) && str_contains( $links, 'return $cards ?' ), 'Hook-injected optional sections must suppress empty output.' );

echo "Field page privacy tests passed.\n";
