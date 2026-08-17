<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

$submission_portal = file_get_contents( $root . '/includes/experience/class-portal.php' );
$manager_portal    = file_get_contents( $root . '/includes/managers/class-portal.php' );
$field_template    = file_get_contents( $root . '/templates/fields/single.php' );
$team_template     = file_get_contents( $root . '/templates/teams/single.php' );

foreach ( array( $submission_portal, $manager_portal, $field_template, $team_template ) as $source ) {
	if ( false === $source ) {
		throw new RuntimeException( 'Não foi possível ler um ficheiro de privacidade.' );
	}
}

$notice_text = 'Redes e contactos públicos';
$privacy_text = 'não são publicados';
$public_links_text = 'O Website e os links de WhatsApp, Instagram e Facebook';

foreach ( array( $submission_portal, $manager_portal ) as $portal ) {
	if ( ! str_contains( $portal, $notice_text ) || ! str_contains( $portal, $privacy_text ) ) {
		throw new RuntimeException( 'Falta o aviso de privacidade num formulário relevante.' );
	}
	if ( ! str_contains( $portal, $public_links_text ) || ! str_contains( $portal, 'Todos estes campos são opcionais' ) ) {
		throw new RuntimeException( 'O aviso não identifica o Website e a opcionalidade dos contactos públicos.' );
	}
	if ( ! str_contains( $portal, "array( 'field', 'team' )" ) ) {
		throw new RuntimeException( 'O aviso não está limitado a Campos e Equipas.' );
	}
}

foreach ( array( 'templates/fields/single.php' => $field_template, 'templates/teams/single.php' => $team_template ) as $file => $template ) {
	foreach ( array( 'contact_email', '->email', '->phone', '->telephone', '->mobile' ) as $private_field ) {
		if ( str_contains( $template, $private_field ) ) {
			throw new RuntimeException( sprintf( 'O template público %s referencia o contacto privado %s.', $file, $private_field ) );
		}
	}
}

if ( ! str_contains( $field_template, 'Public_Privacy::social_links' ) || ! str_contains( $team_template, 'Public_Privacy::social_links' ) ) {
	throw new RuntimeException( 'Os templates públicos não usam a camada de links sociais públicos.' );
}

echo "Social privacy checks passed\n";
