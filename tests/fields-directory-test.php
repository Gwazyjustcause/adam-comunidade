<?php
/**
 * Standalone architecture checks for the public Fields directory.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$schema     = (string) file_get_contents( $root . '/includes/fields/class-schema.php' );
$repository = (string) file_get_contents( $root . '/includes/fields/class-repository.php' );
$validator  = (string) file_get_contents( $root . '/includes/fields/class-validator.php' );
$archive    = (string) file_get_contents( $root . '/templates/fields/archive.php' );
$card       = (string) file_get_contents( $root . '/templates/fields/card.php' );
$portal     = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$uploads    = (string) file_get_contents( $root . '/includes/uploads/class-handler.php' );
$forms      = (string) file_get_contents( $root . '/includes/forms/class-manager.php' );
$editor     = (string) file_get_contents( $root . '/admin/views/fields/editor.php' );

foreach ( array( 'is_associated', 'authorization_document_id', 'associated_status' ) as $schema_contract ) {
	$assert( str_contains( $schema, $schema_contract ), 'Missing Fields schema contract: ' . $schema_contract );
}

$assert( str_contains( $repository, 'f.is_associated DESC' ), 'Associated fields must be ordered first.' );
$assert( str_contains( $repository, "verification = 'verified_field'" ), 'Public statistics must include only legally authorised fields.' );
$assert( str_contains( $validator, 'legal_authorization_required' ), 'Publishing must require verified legal authorisation.' );
$assert( str_contains( $editor, 'field[is_associated]' ), 'Admin editor is missing the Associated Field option.' );
$assert( str_contains( $editor, 'field[authorization_document_id]' ), 'Admin editor is missing the authorisation document.' );

foreach (
	array(
		'Campos',
		'Todos os campos',
		'Apenas Associados ADAM',
		'Submeter Campo',
	) as $directory_copy
) {
	$assert( str_contains( $archive, $directory_copy ), 'Missing directory UI: ' . $directory_copy );
}

$assert( str_contains( $card, 'Associado ADAM' ), 'Associated cards need the ADAM badge.' );
$assert( str_contains( $card, 'adam-field-card--associated' ), 'Associated cards need a clean visual distinction.' );
$assert( str_contains( $portal, 'authorization_document' ), 'Field submissions require an authorisation upload.' );
$assert( str_contains( $forms, "'field_photos'" ), 'Field form schema must accept review photographs.' );
$assert( str_contains( $portal, 'process_form_upload' ), 'Public uploads must be driven by the shared form schema.' );
$assert( str_contains( $portal, "status' => 'pending'" ), 'Public submissions must enter Pending Review.' );
$assert( str_contains( $portal, 'Abrir documento' ), 'Moderation must expose the legal document.' );
$assert( str_contains( $portal, 'Upload_Handler' ) && str_contains( $uploads, 'media_handle_upload' ), 'Uploads must use the shared WordPress Media handler.' );

echo "Fields directory tests passed.\n";
