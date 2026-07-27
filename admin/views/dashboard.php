<?php
/**
 * Admin dashboard view.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

$adam_comunidade_statistics = array(
	array( 'label' => __( 'Equipas', 'adam-comunidade' ), 'value' => $team_counts['all'] ),
	array( 'label' => __( 'Publicado', 'adam-comunidade' ), 'value' => $team_counts['published'] ),
	array( 'label' => __( 'Rascunho', 'adam-comunidade' ), 'value' => $team_counts['draft'] ),
	array( 'label' => __( 'Oculto', 'adam-comunidade' ), 'value' => $team_counts['hidden'] ),
);
$adam_type_labels = array(
	'partner'     => __( 'Parceiro', 'adam-comunidade' ),
	'institution' => __( 'Instituição', 'adam-comunidade' ),
);

?>
<div class="wrap adam-comunidade-admin">
	<header class="adam-page-header">
		<div>
			<h1><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Gestão da Comunidade preparada para crescer módulo a módulo.', 'adam-comunidade' ); ?></p>
		</div>
	</header>

	<nav class="adam-header-actions" aria-label="<?php esc_attr_e( 'Ações rápidas', 'adam-comunidade' ); ?>">
		<?php
		$adam_quick_actions = array(
			'teams'        => __( 'Adicionar equipa', 'adam-comunidade' ),
			'fields'       => __( 'Adicionar campo', 'adam-comunidade' ),
			'partners'     => __( 'Adicionar parceiro', 'adam-comunidade' ),
			'institutions' => __( 'Adicionar instituição', 'adam-comunidade' ),
		);
		foreach ( $adam_quick_actions as $adam_module => $adam_label ) :
			?>
			<a class="button button-primary adam-button" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( $adam_module, 'add' ) ); ?>"><?php echo esc_html( $adam_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<section aria-labelledby="adam-statistics-heading">
		<h2 id="adam-statistics-heading"><?php esc_html_e( 'Resumo rápido', 'adam-comunidade' ); ?></h2>
		<div class="adam-stat-grid">
			<?php foreach ( $adam_comunidade_statistics as $adam_comunidade_statistic ) : ?>
				<div class="adam-stat-card">
					<span class="adam-stat-card__value"><?php echo esc_html( (string) $adam_comunidade_statistic['value'] ); ?></span>
					<span class="adam-stat-card__label"><?php echo esc_html( $adam_comunidade_statistic['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section aria-labelledby="adam-directory-statistics-heading">
		<div class="adam-section-heading">
			<h2 id="adam-directory-statistics-heading"><?php esc_html_e( 'Ecossistema da Comunidade', 'adam-comunidade' ); ?></h2>
		</div>
		<div class="adam-stat-grid">
			<?php
			$adam_directory_statistics = array(
				__( 'Equipas', 'adam-comunidade' )        => $team_counts['all'],
				__( 'Campos', 'adam-comunidade' )       => $field_counts['all'],
				__( 'Parceiros', 'adam-comunidade' )     => $directory_counts['partner']['all'],
				__( 'Instituições', 'adam-comunidade' ) => $directory_counts['institution']['all'],
			);
			foreach ( $adam_directory_statistics as $adam_label => $adam_value ) :
				?>
				<div class="adam-stat-card">
					<span class="adam-stat-card__value"><?php echo esc_html( (string) $adam_value ); ?></span>
					<span class="adam-stat-card__label"><?php echo esc_html( $adam_label ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section aria-labelledby="adam-community-insights-heading">
		<div class="adam-section-heading"><h2 id="adam-community-insights-heading"><?php esc_html_e( 'Resumo da Comunidade', 'adam-comunidade' ); ?></h2><a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::page_url( 'moderation' ) ); ?>"><?php esc_html_e( 'Abrir aprovações', 'adam-comunidade' ); ?></a></div>
		<div class="adam-stat-grid">
			<?php foreach ( array( __( 'Submissões pendentes', 'adam-comunidade' ) => $community_insights['pending'], __( 'Pedidos de gestão pendentes', 'adam-comunidade' ) => $community_insights['claims'], __( 'Responsáveis verificados', 'adam-comunidade' ) => $community_insights['owners'] ) as $adam_label => $adam_value ) : ?><div class="adam-stat-card"><span class="adam-stat-card__value"><?php echo esc_html( (string) $adam_value ); ?></span><span class="adam-stat-card__label"><?php echo esc_html( $adam_label ); ?></span></div><?php endforeach; ?>
		</div>
	</section>

	<div class="adam-dashboard-columns">
		<section class="adam-card">
			<div class="adam-card__header"><h2><?php esc_html_e( 'Adicionados recentemente', 'adam-comunidade' ); ?></h2></div>
			<?php if ( $recent_directory ) : ?><ul class="adam-dashboard-team-list"><?php foreach ( $recent_directory as $adam_entry ) : ?>
				<li><?php echo wp_get_attachment_image( (int) $adam_entry->logo_id, array( 42, 42 ) ); ?><div><strong><?php echo esc_html( $adam_entry->name ); ?></strong><small><?php echo esc_html( $adam_type_labels[ $adam_entry->entity_type ] ?? $adam_entry->entity_type ); ?></small></div></li>
			<?php endforeach; ?></ul><?php else : ?><div class="adam-empty-state"><?php esc_html_e( 'Ainda não existe conteúdo no diretório.', 'adam-comunidade' ); ?></div><?php endif; ?>
		</section>
		<section class="adam-card">
			<div class="adam-card__header"><h2><?php esc_html_e( 'Conteúdo em destaque', 'adam-comunidade' ); ?></h2></div>
			<?php if ( $featured_directory ) : ?><ul class="adam-dashboard-team-list"><?php foreach ( array_slice( $featured_directory, 0, 6 ) as $adam_entry ) : ?>
				<li><?php echo wp_get_attachment_image( (int) $adam_entry->logo_id, array( 42, 42 ) ); ?><div><strong><?php echo esc_html( $adam_entry->name ); ?></strong><small><?php echo esc_html( $adam_type_labels[ $adam_entry->entity_type ] ?? $adam_entry->entity_type ); ?></small></div></li>
			<?php endforeach; ?></ul><?php else : ?><div class="adam-empty-state"><?php esc_html_e( 'Ainda não existe conteúdo em destaque.', 'adam-comunidade' ); ?></div><?php endif; ?>
		</section>
	</div>

	<section aria-labelledby="adam-field-statistics-heading">
		<div class="adam-section-heading">
			<h2 id="adam-field-statistics-heading"><?php esc_html_e( 'Estatísticas dos campos', 'adam-comunidade' ); ?></h2>
			<a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'fields' ) ); ?>"><?php esc_html_e( 'Gerir campos', 'adam-comunidade' ); ?></a>
		</div>
		<div class="adam-stat-grid adam-stat-grid--five">
			<?php
			$adam_field_statistics = array(
				__( 'Campos', 'adam-comunidade' )           => $field_counts['all'],
				__( 'Publicado', 'adam-comunidade' )        => $field_counts['published'],
				__( 'Rascunho', 'adam-comunidade' )            => $field_counts['draft'],
				__( 'Oculto', 'adam-comunidade' )           => $field_counts['hidden'],
				__( 'Capacidade média', 'adam-comunidade' ) => $field_counts['average_capacity'],
			);
			foreach ( $adam_field_statistics as $adam_label => $adam_value ) :
				?>
				<div class="adam-stat-card">
					<span class="adam-stat-card__value"><?php echo esc_html( (string) $adam_value ); ?></span>
					<span class="adam-stat-card__label"><?php echo esc_html( $adam_label ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="adam-card">
		<div class="adam-card__header">
			<h2><?php esc_html_e( 'Campos atualizados recentemente', 'adam-comunidade' ); ?></h2>
			<a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'fields' ) ); ?>"><?php esc_html_e( 'Ver todos', 'adam-comunidade' ); ?></a>
		</div>
		<?php if ( $updated_fields ) : ?><ul class="adam-dashboard-team-list">
			<?php foreach ( $updated_fields as $adam_field ) : ?><li>
				<?php echo wp_get_attachment_image( (int) $adam_field->cover_id, array( 52, 38 ) ); ?>
				<div><a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'fields', 'edit', array( 'id' => absint( $adam_field->id ) ) ) ); ?>"><?php echo esc_html( $adam_field->name ); ?></a><small><?php echo esc_html( $adam_field->municipality ?: $adam_field->district ); ?></small></div>
			</li><?php endforeach; ?>
		</ul><?php else : ?><div class="adam-empty-state"><?php esc_html_e( 'Ainda não existem campos.', 'adam-comunidade' ); ?></div><?php endif; ?>
	</section>

	<div class="adam-dashboard-columns">
		<?php
		$adam_dashboard_lists = array(
			__( 'Equipas recentes', 'adam-comunidade' ) => $recent_teams,
			__( 'Equipas atualizadas recentemente', 'adam-comunidade' ) => $updated_teams,
		);
		foreach ( $adam_dashboard_lists as $adam_list_title => $adam_list_teams ) :
			?>
			<section class="adam-card">
				<div class="adam-card__header">
					<h2><?php echo esc_html( $adam_list_title ); ?></h2>
					<a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'teams' ) ); ?>">
						<?php esc_html_e( 'Ver todos', 'adam-comunidade' ); ?>
					</a>
				</div>
				<?php if ( $adam_list_teams ) : ?>
					<ul class="adam-dashboard-team-list">
						<?php foreach ( $adam_list_teams as $adam_list_team ) : ?>
							<li>
								<?php echo wp_get_attachment_image( (int) $adam_list_team->logo_id, array( 38, 38 ) ); ?>
								<div>
									<a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'teams', 'edit', array( 'id' => absint( $adam_list_team->id ) ) ) ); ?>">
										<?php echo esc_html( $adam_list_team->name ); ?>
									</a>
									<small><?php echo esc_html( $adam_list_team->municipality ?: $adam_list_team->district ); ?></small>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<div class="adam-empty-state"><?php esc_html_e( 'Ainda não existem equipas.', 'adam-comunidade' ); ?></div>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>

</div>
