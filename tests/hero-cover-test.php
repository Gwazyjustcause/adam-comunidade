<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$teams_css  = (string) file_get_contents( $root . '/assets/css/teams-public.css' );
$fields_css = (string) file_get_contents( $root . '/assets/css/fields-public.css' );
$team_view  = (string) file_get_contents( $root . '/templates/teams/single.php' );
$field_view = (string) file_get_contents( $root . '/templates/fields/single.php' );

foreach ( array( $teams_css, $fields_css, $team_view, $field_view ) as $source ) {
	if ( '' === $source ) {
		throw new RuntimeException( 'Não foi possível ler os ficheiros do hero.' );
	}
}

foreach ( array( $teams_css, $fields_css ) as $css ) {
	$assert( str_contains( $css, 'overflow: hidden' ), 'O hero não protege o conteúdo visual fora dos limites.' );
	$assert( str_contains( $css, 'position: static' ), 'A camada de imagem ainda está confinada ao bloco de media.' );
	$assert( str_contains( $css, 'inset: 0' ) && str_contains( $css, 'position: absolute' ) && str_contains( $css, 'object-fit: cover' ), 'A imagem não cobre todo o hero.' );
	$assert( str_contains( $css, 'z-index: 2' ) && str_contains( $css, 'z-index: 1' ), 'A ordem entre imagem, overlay e conteúdo do hero está incompleta.' );
	$assert( str_contains( $css, 'linear-gradient' ), 'O hero não tem overlay de legibilidade.' );
}

foreach ( array( $team_view, $field_view ) as $template ) {
	$assert( str_contains( $template, 'Placeholder_Image::cover' ), 'O fallback sem imagem foi removido.' );
	$assert( str_contains( $template, 'adam-hero-social-links' ), 'Os contactos públicos deixaram de estar no hero.' );
}

echo "Hero cover tests passed.\n";
