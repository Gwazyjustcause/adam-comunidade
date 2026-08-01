<?php
/**
 * Approval attachment persistence and rendering contracts.
 *
 * @package ADAM_Comunidade
 */

$root   = dirname( __DIR__ );
$portal = file_get_contents( $root . '/includes/experience/class-portal.php' );
$css    = file_get_contents( $root . '/assets/css/admin.css' );
$manager_service = file_get_contents( $root . '/includes/managers/class-service.php' );
$manager_admin   = file_get_contents( $root . '/includes/managers/class-admin.php' );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
};

foreach ( array(
	"\$payload['authorization_document_id'] = absint( \$result )",
	"\$payload['gallery_ids'] = array_map( 'absint', (array) \$result )",
	"\$payload['logo_id'] = absint( \$result )",
	"\$payload['cover_id'] = absint( \$result )",
	"\$payload['gallery'] = \\ADAM\\Comunidade\\Teams\\Options::decode_ids( \$result )",
) as $storage_contract ) {
	$assert( str_contains( $portal, $storage_contract ), 'A public attachment ID is not persisted in the pending submission payload.' );
}

foreach ( array( 'logo_id', 'cover_id', 'gallery', 'gallery_ids', 'authorization_document_id' ) as $attachment_key ) {
	$assert( str_contains( $portal, "'key' => '" . $attachment_key . "'" ) || str_contains( $portal, "array( '" . $attachment_key ), 'The approval renderer does not resolve ' . $attachment_key . '.' );
}

$assert( str_contains( $portal, "'attachment' === get_post_type( \$id )" ) && str_contains( $portal, 'wp_get_attachment_url( $id )' ), 'Approval attachments must be resolved as valid WordPress Media Library items.' );
$assert( str_contains( $portal, 'wp_get_attachment_image' ) && str_contains( $portal, 'Ver em tamanho completo' ), 'Approval images need a thumbnail and a full-size action.' );
$assert( str_contains( $portal, 'get_attached_file' ) && str_contains( $portal, 'Abrir documento' ), 'Approval documents need a filename and open action.' );
$assert( str_contains( $portal, 'array_merge( $attachment_keys' ), 'Internal attachment fields must be excluded from submitted information.' );
$assert( str_contains( $portal, '<?php if ( $attachments ) : ?>' ) && str_contains( $portal, 'Não foram anexados ficheiros.' ), 'The empty attachment message must depend on resolved valid attachments.' );
$assert( str_contains( $css, '.adam-approval-image img' ) && str_contains( $css, 'max-height: 260px' ), 'Approval thumbnails must be large enough to review.' );
$assert( str_contains( $manager_service, '$next_payload   = array_merge( is_array( $stored_payload ) ? $stored_payload : array(), $changes )' ), 'A response to requested changes must preserve previously submitted attachment IDs.' );
$assert( str_contains( $manager_admin, 'Ver em tamanho completo' ) && str_contains( $manager_admin, 'Abrir documento' ), 'Manager change requests must resolve images and documents without exposing attachment IDs.' );

echo "Approval attachment tests passed.\n";
