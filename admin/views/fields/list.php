<?php
/**
 * Fields admin list.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

$adam_status       = sanitize_key( (string) filter_input( INPUT_GET, 'field_status' ) );
$adam_district     = sanitize_text_field( (string) filter_input( INPUT_GET, 'district' ) );
$adam_municipality = sanitize_text_field( (string) filter_input( INPUT_GET, 'municipality' ) );
$adam_base_url     = \ADAM\Comunidade\Admin\Router::module_url( 'fields' );
?>
<div class="wrap adam-comunidade-admin adam-fields-admin">
	<header class="adam-page-header">
		<div>
			<h1><?php esc_html_e( 'Campos', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Maintain the directory of legally authorised airsoft fields. Association is managed as a distinction on each field.', 'adam-comunidade' ); ?></p>
		</div>
		<div class="adam-header-actions">
			<a class="button adam-button adam-button--secondary" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::page_url( 'field-amenities' ) ); ?>">
				<?php esc_html_e( 'Manage Amenities', 'adam-comunidade' ); ?>
			</a>
			<a class="button button-primary adam-button" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'fields', 'add' ) ); ?>">
				<?php esc_html_e( 'Add Field', 'adam-comunidade' ); ?>
			</a>
		</div>
	</header>

	<nav class="adam-status-nav" aria-label="<?php esc_attr_e( 'Filter fields by status', 'adam-comunidade' ); ?>">
		<?php foreach ( array( 'all', 'published', 'draft', 'hidden' ) as $adam_key ) : ?>
			<?php
			$adam_url   = 'all' === $adam_key ? $adam_base_url : add_query_arg( 'field_status', $adam_key, $adam_base_url );
			$adam_label = 'all' === $adam_key
				? __( 'All', 'adam-comunidade' )
				: ( \ADAM\Comunidade\Fields\Options::statuses()[ $adam_key ] ?? $adam_key );
			$adam_current = ( ! $adam_status && 'all' === $adam_key ) || $adam_status === $adam_key;
			?>
			<a href="<?php echo esc_url( $adam_url ); ?>" class="<?php echo $adam_current ? 'current' : ''; ?>">
				<?php echo esc_html( $adam_label ); ?>
				<span><?php echo esc_html( (string) ( $counts[ $adam_key ] ?? 0 ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<form method="get">
		<input type="hidden" name="page" value="adam-comunidade-fields">
		<div class="adam-table-toolbar">
			<select name="district" aria-label="<?php esc_attr_e( 'Filter by district', 'adam-comunidade' ); ?>">
				<option value=""><?php esc_html_e( 'All districts', 'adam-comunidade' ); ?></option>
				<?php foreach ( $districts as $value ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $adam_district, $value ); ?>><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="municipality" aria-label="<?php esc_attr_e( 'Filter by municipality', 'adam-comunidade' ); ?>">
				<option value=""><?php esc_html_e( 'All municipalities', 'adam-comunidade' ); ?></option>
				<?php foreach ( $municipalities as $value ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $adam_municipality, $value ); ?>><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( $adam_status ) : ?>
				<input type="hidden" name="field_status" value="<?php echo esc_attr( $adam_status ); ?>">
			<?php endif; ?>
			<button class="button" type="submit"><?php esc_html_e( 'Filter', 'adam-comunidade' ); ?></button>
		</div>
		<?php
		$table->search_box( __( 'Search Fields', 'adam-comunidade' ), 'adam-field-search' );
		$table->display();
		?>
	</form>
</div>
