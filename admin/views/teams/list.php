<?php
/**
 * Teams list view.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

$adam_current_status       = sanitize_key( (string) filter_input( INPUT_GET, 'team_status' ) );
$adam_current_district     = sanitize_text_field( (string) filter_input( INPUT_GET, 'district' ) );
$adam_current_municipality = sanitize_text_field( (string) filter_input( INPUT_GET, 'municipality' ) );
$adam_base_url             = \ADAM\Comunidade\Admin\Router::module_url( 'teams' );
?>
<div class="wrap adam-comunidade-admin adam-teams-admin">
	<header class="adam-page-header">
		<div>
			<h1><?php esc_html_e( 'Equipas', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Crie, publique e mantenha as páginas das equipas associadas.', 'adam-comunidade' ); ?></p>
		</div>
		<a class="button adam-button adam-button--secondary" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::page_url( 'team-hero' ) ); ?>">
			<?php esc_html_e( 'Gerir hero', 'adam-comunidade' ); ?>
		</a>
		<a class="button button-primary adam-button" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'teams', 'add' ) ); ?>">
			<?php esc_html_e( 'Adicionar equipa', 'adam-comunidade' ); ?>
		</a>
	</header>

	<nav class="adam-status-nav" aria-label="<?php esc_attr_e( 'Filtrar equipas por estado', 'adam-comunidade' ); ?>">
		<?php foreach ( array( 'all', 'published', 'draft', 'hidden' ) as $adam_status_key ) : ?>
			<?php
			$adam_status_url = 'all' === $adam_status_key
				? $adam_base_url
				: add_query_arg( 'team_status', $adam_status_key, $adam_base_url );
			$adam_status_label = 'all' === $adam_status_key
				? __( 'Todos', 'adam-comunidade' )
				: ( \ADAM\Comunidade\Teams\Options::statuses()[ $adam_status_key ] ?? $adam_status_key );
			?>
			<a
				href="<?php echo esc_url( $adam_status_url ); ?>"
				class="<?php echo esc_attr( ( ! $adam_current_status && 'all' === $adam_status_key ) || $adam_current_status === $adam_status_key ? 'current' : '' ); ?>"
			>
				<?php echo esc_html( $adam_status_label ); ?>
				<span><?php echo esc_html( (string) ( $counts[ $adam_status_key ] ?? 0 ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<form method="get">
		<input type="hidden" name="page" value="adam-comunidade-teams">
		<div class="adam-table-toolbar">
			<label class="screen-reader-text" for="adam-team-district-filter">
				<?php esc_html_e( 'Filtrar por distrito', 'adam-comunidade' ); ?>
			</label>
			<select id="adam-team-district-filter" name="district">
				<option value=""><?php esc_html_e( 'Todos os distritos', 'adam-comunidade' ); ?></option>
				<?php foreach ( $districts as $adam_district ) : ?>
					<option value="<?php echo esc_attr( $adam_district ); ?>" <?php selected( $adam_current_district, $adam_district ); ?>>
						<?php echo esc_html( $adam_district ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<label class="screen-reader-text" for="adam-team-municipality-filter">
				<?php esc_html_e( 'Filtrar por concelho', 'adam-comunidade' ); ?>
			</label>
			<select id="adam-team-municipality-filter" name="municipality">
				<option value=""><?php esc_html_e( 'Todos os concelhos', 'adam-comunidade' ); ?></option>
				<?php foreach ( $municipalities as $adam_municipality ) : ?>
					<option value="<?php echo esc_attr( $adam_municipality ); ?>" <?php selected( $adam_current_municipality, $adam_municipality ); ?>>
						<?php echo esc_html( $adam_municipality ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ( $adam_current_status ) : ?>
				<input type="hidden" name="team_status" value="<?php echo esc_attr( $adam_current_status ); ?>">
			<?php endif; ?>
			<button class="button" type="submit"><?php esc_html_e( 'Filtrar', 'adam-comunidade' ); ?></button>
		</div>
		<?php
		$table->search_box( __( 'Pesquisar equipas', 'adam-comunidade' ), 'adam-team-search' );
		$table->display();
		?>
	</form>
</div>
