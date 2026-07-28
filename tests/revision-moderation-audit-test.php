<?php
/**
 * Production contracts for the Community Manager revision workflow.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$schema     = (string) file_get_contents( $root . '/includes/managers/class-schema.php' );
$service    = (string) file_get_contents( $root . '/includes/managers/class-service.php' );
$policy     = (string) file_get_contents( $root . '/includes/managers/class-policy.php' );
$portal     = (string) file_get_contents( $root . '/includes/managers/class-portal.php' );
$admin      = (string) file_get_contents( $root . '/includes/managers/class-admin.php' );
$admin_js   = (string) file_get_contents( $root . '/assets/js/admin.js' );
$public_js  = (string) file_get_contents( $root . '/assets/js/public.js' );
$fields     = (string) file_get_contents( $root . '/includes/fields/class-repository.php' );
$directory  = (string) file_get_contents( $root . '/includes/directory/class-repository.php' );
$experience = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$moderation_component = (string) file_get_contents( $root . '/includes/experience/class-moderation-component.php' );

foreach ( array( 'base_payload longtext', 'active_key varchar', 'published_at datetime', 'UNIQUE KEY active_entity' ) as $contract ) {
	$assert( str_contains( $schema, $contract ), 'Missing revision audit schema contract: ' . $contract );
}
$assert( str_contains( $schema, 'migration_140_revision_audit' ), 'Existing installations cannot migrate to the audited revision schema.' );
$assert( str_contains( $schema, "status IN ('pending','needs_info','processing')" ) && str_contains( $schema, "SET r.status='superseded'" ), 'Legacy conflicting revisions are not normalized safely.' );
$assert( str_contains( $schema, 'function index_is_unique' ) && str_contains( $schema, 'Non_unique' ) && str_contains( $schema, "'active_entity'" ), 'The active revision uniqueness constraint is not verified.' );

foreach ( array( 'function active_revision', 'function revision_baseline', 'function revision_changes', 'function revision_has_conflict', 'function revision_conflicts', 'function revision_history' ) as $method ) {
	$assert( str_contains( $service, $method ), 'Missing generic revision service method: ' . $method );
}
$assert( str_contains( $service, "'base_payload'=> wp_json_encode( \$baseline )" ), 'A revision does not preserve its published baseline.' );
$assert( str_contains( $service, "'active_key' => \$type . ':' . \$id" ), 'One active revision per entity is not enforced at insertion.' );
$assert( str_contains( $service, 'FOR UPDATE' ) && str_contains( $service, "'START TRANSACTION'" ) && str_contains( $service, "'ROLLBACK'" ), 'Revision replacement is not concurrency safe.' );
$assert( str_contains( $service, "'revision_conflict'" ) && str_contains( $service, "'revision_processing'" ), 'Conflicting manager proposals are not handled explicitly.' );
$assert( str_contains( $service, "'published_version_changed'" ) && str_contains( $service, '$force_conflict' ), 'Published changes cannot be detected and reviewed safely.' );
$assert( str_contains( $service, 'function lock_revision_entity' ) && str_contains( $service, "FOR UPDATE" ), 'The published entity can change while a moderation decision is being applied.' );
$assert( str_contains( $service, "'published_at' => 'approved' === \$status ? \$now : null" ), 'Publication time is not recorded in the audit trail.' );
$assert( str_contains( $service, "'reviewed_by'" ) && str_contains( $service, "'reviewed_at'" ) && str_contains( $service, "'admin_note'" ), 'Moderation accountability fields are not recorded.' );
$assert( str_contains( $policy, 'adam_comunidade_manager_revision_entity_types' ) && str_contains( $service, 'adam_comunidade_manager_revision_validate' ) && str_contains( $service, 'adam_comunidade_apply_manager_revision' ), 'Future Community entities cannot reuse the moderation workflow.' );

$assert( str_contains( $portal, 'active_revisions_for_manager' ) && str_contains( $portal, 'Editar proposta pendente' ), 'Managers cannot understand or continue a pending proposal.' );
$assert( str_contains( $portal, 'Alterações pedidas pela ADAM:' ) && str_contains( $portal, 'O registo público mantém-se inalterado' ), 'Manager moderation status is not explained clearly.' );
$assert( str_contains( $portal, 'remove_cover' ) && str_contains( $portal, 'remove_logo' ) && str_contains( $portal, 'keep_gallery_ids[]' ), 'Image removals are not preserved in proposals.' );
$assert( str_contains( $public_js, '[data-adam-current-gallery]' ) && str_contains( $public_js, 'dragstart' ) && str_contains( $public_js, 'ArrowLeft' ), 'Proposed image ordering is not accessible or draggable.' );

$assert( str_contains( $admin, 'adam-revision-comparison' ) && str_contains( $admin, 'Versão publicada' ) && str_contains( $admin, 'Versão proposta' ), 'Administrators do not receive a side-by-side comparison.' );
$assert( str_contains( $admin, 'adam-revision-conflict-comparison' ) && str_contains( $admin, 'Publicado agora' ) && str_contains( $admin, 'Proposta do Gestor' ), 'Administrators cannot inspect the current published value before forcing a conflicting proposal.' );
$assert( str_contains( $admin, 'render_revision_value' ) && str_contains( $admin, 'wp_get_attachment_image' ), 'Image proposals are not rendered for moderation.' );
$assert( str_contains( $admin, 'render_revision_history' ) && str_contains( $admin, 'Histórico de moderação' ) && str_contains( $admin, 'adam-revision-history-changes' ) && str_contains( $admin, 'Decidida em' ), 'Moderation history is not available to administrators with its decisions and values.' );
$assert( str_contains( $admin, 'Moderation_Component::render' ) && str_contains( $moderation_component, 'data-adam-moderation-dialog' ) && str_contains( $admin_js, 'confirm_conflict' ), 'Revision moderation does not use the shared focused action workflow or preserve conflict protection.' );
$assert( str_contains( $experience, 'adam_comunidade_moderation_pending_count' ), 'Manager revisions are missing from the unified approval count.' );

$assert( str_contains( $fields, 'public function sync_gallery' ) && str_contains( $fields, 'public function sync_amenities' ) && str_contains( $fields, 'return false;' ), 'Field relationship updates cannot report transactional failures.' );
$assert( str_contains( $directory, 'public function sync_gallery' ) && str_contains( $directory, 'return false;' ), 'Directory image updates cannot report transactional failures.' );

echo "Revision moderation audit tests passed.\n";
