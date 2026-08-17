<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$schema    = (string) file_get_contents( $root . '/includes/fields/class-schema.php' );
$validator = (string) file_get_contents( $root . '/includes/fields/class-validator.php' );
$forms     = (string) file_get_contents( $root . '/includes/forms/class-manager.php' );
$public    = (string) file_get_contents( $root . '/templates/fields/single.php' );
$manager   = (string) file_get_contents( $root . '/includes/managers/class-portal.php' );
$admin     = (string) file_get_contents( $root . '/admin/views/fields/editor.php' );
$labels    = (string) file_get_contents( $root . '/includes/managers/class-admin.php' );

$assert( str_contains( $schema, 'opening_hours longtext' ), 'The existing opening_hours storage is missing.' );
$assert( str_contains( $validator, "'opening_hours'       => sanitize_textarea_field" ), 'Existing opening_hours content is not preserved by validation.' );
$assert( str_contains( $public, "esc_html_e( 'Funcionamento'" ) && ! str_contains( $public, "esc_html_e( 'Horários'" ), 'The public Field heading was not renamed.' );
$assert( substr_count( $forms, "'opening_hours' => \$this->field( 'opening_hours'" ) === 1, 'The public form has a duplicated or missing Funcionamento field.' );
$assert( str_contains( $forms, "__( 'Funcionamento', 'adam-comunidade' )" ), 'The public form label is missing.' );
$assert( str_contains( $forms, 'Indica quando costumam realizar-se jogos' ) && str_contains( $forms, 'Ex.: Jogos normalmente aos domingos' ), 'The public form help/placeholder is missing.' );
$assert( str_contains( $forms, "'opening_hours' ) as \$optional_key" ), 'Funcionamento is not included in the optional form fields.' );
$assert( str_contains( $forms, "'opening_hours' === \$key" ) && str_contains( $forms, "\$merged[ \$key ]['required'] = false" ), 'Existing form configurations do not keep Funcionamento optional.' );
$assert( str_contains( $manager, "esc_html_e( 'Funcionamento'" ) && str_contains( $manager, 'Indica quando costumam realizar-se jogos' ), 'Manager edit was not updated.' );
$assert( str_contains( $admin, "esc_html_e( 'Funcionamento'" ) && str_contains( $admin, 'Indica quando costumam realizar-se jogos' ), 'Admin edit was not updated.' );
$assert( str_contains( $labels, "'opening_hours'          => __( 'Funcionamento'" ), 'Admin revision label was not updated.' );

foreach ( array( $public, $manager, $admin ) as $source ) {
	$assert( ! str_contains( $source, 'Sábado e domingo, 09:00–18:00' ), 'The old example placeholder remains in a Field interface.' );
}

echo "Field operation tests passed.\n";
