<?php
/**
 * Unified existing/new manager gallery contracts for Teams and Fields.
 *
 * @package ADAM_Comunidade
 */

declare(strict_types=1);

$root      = dirname( __DIR__ );
$portal    = (string) file_get_contents( $root . '/includes/managers/class-portal.php' );
$component = (string) file_get_contents( $root . '/includes/uploads/class-component.php' );
$script    = (string) file_get_contents( $root . '/assets/js/upload.js' );
$public_js = (string) file_get_contents( $root . '/assets/js/public.js' );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$assert( ! str_contains( $portal, 'Fotografias atuais' ) && ! str_contains( $portal, 'Fotografias da proposta pendente' ), 'The separate current-gallery block is still rendered.' );
$assert( ! str_contains( $portal, 'Novas fotografias para a galeria' ) && str_contains( $portal, 'Fotografias da galeria (opcional)' ), 'The unified gallery label is incorrect.' );
$assert( str_contains( $portal, "'items' => \$gallery_items" ) && str_contains( $portal, "'name' => 'manager_gallery[]'" ), 'Existing and new images are not rendered by the same upload component.' );
$assert( str_contains( $portal, "in_array( \$type, array( 'team', 'field' ), true ) ? 5" ) && str_contains( $portal, "'max' => \$gallery_max" ) && str_contains( $portal, "'existing_name' => 'keep_gallery_ids[]'" ) && str_contains( $portal, "'order_name' => 'gallery_order[]'" ), 'The unified component does not expose its shared limit and persistence fields.' );
$assert( str_contains( $component, 'data-adam-upload-existing-value' ) && str_contains( $component, 'existing:' ), 'Existing cards do not preserve their attachment IDs internally.' );
$assert( str_contains( $script, "order.value = 'new'" ) && str_contains( $script, 'replaceTarget.replaceWith' ) && str_contains( $script, "list.insertBefore( itemElement( item, 0, true ), add )" ), 'New cards cannot mix with, replace, or remain ahead of the add-photo card.' );
$assert( str_contains( $script, "upload.addEventListener( 'dragstart'" ) && str_contains( $script, 'syncFileInput()' ), 'Mixed cards do not share drag ordering and file synchronization.' );
$assert( str_contains( $portal, "str_starts_with( \$token, 'existing:' )" ) && str_contains( $portal, "'new' === \$token" ), 'The server does not reconstruct the mixed gallery order.' );
$assert( ! str_contains( $public_js, 'data-adam-current-gallery' ), 'Legacy current-gallery JavaScript can render or control the gallery twice.' );

echo "Manager unified gallery tests passed.\n";
