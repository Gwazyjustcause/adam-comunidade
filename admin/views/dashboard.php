<?php
/**
 * Admin dashboard view.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

$adam_comunidade_statistics = array(
	array( 'label' => __( 'Teams', 'adam-comunidade' ), 'value' => $team_counts['all'] ),
	array( 'label' => __( 'Published', 'adam-comunidade' ), 'value' => $team_counts['published'] ),
	array( 'label' => __( 'Draft', 'adam-comunidade' ), 'value' => $team_counts['draft'] ),
	array( 'label' => __( 'Hidden', 'adam-comunidade' ), 'value' => $team_counts['hidden'] ),
);

$adam_comunidade_roadmap = array(
	__( 'Fields', 'adam-comunidade' ),
	__( 'Partners', 'adam-comunidade' ),
	__( 'Brands', 'adam-comunidade' ),
	__( 'Regions', 'adam-comunidade' ),
);
?>
<div class="wrap adam-comunidade-admin">
	<header class="adam-page-header">
		<div>
			<h1><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Community management, ready to grow module by module.', 'adam-comunidade' ); ?></p>
		</div>
		<span class="adam-badge adam-badge--success"><?php esc_html_e( 'Active', 'adam-comunidade' ); ?></span>
	</header>

	<div class="adam-card adam-status-card">
		<div>
			<span class="adam-card__eyebrow"><?php esc_html_e( 'Version', 'adam-comunidade' ); ?></span>
			<strong><?php echo esc_html( ADAM_COMUNIDADE_VERSION ); ?></strong>
		</div>
		<div>
			<span class="adam-card__eyebrow"><?php esc_html_e( 'Plugin status', 'adam-comunidade' ); ?></span>
			<strong><?php esc_html_e( 'Operational', 'adam-comunidade' ); ?></strong>
		</div>
	</div>

	<section aria-labelledby="adam-statistics-heading">
		<h2 id="adam-statistics-heading"><?php esc_html_e( 'Quick Statistics', 'adam-comunidade' ); ?></h2>
		<div class="adam-stat-grid">
			<?php foreach ( $adam_comunidade_statistics as $adam_comunidade_statistic ) : ?>
				<div class="adam-stat-card">
					<span class="adam-stat-card__value"><?php echo esc_html( (string) $adam_comunidade_statistic['value'] ); ?></span>
					<span class="adam-stat-card__label"><?php echo esc_html( $adam_comunidade_statistic['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<div class="adam-dashboard-columns">
		<?php
		$adam_dashboard_lists = array(
			__( 'Recent teams', 'adam-comunidade' )    => $recent_teams,
			__( 'Recently updated', 'adam-comunidade' ) => $updated_teams,
		);
		foreach ( $adam_dashboard_lists as $adam_list_title => $adam_list_teams ) :
			?>
			<section class="adam-card">
				<div class="adam-card__header">
					<h2><?php echo esc_html( $adam_list_title ); ?></h2>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=adam-comunidade-teams' ) ); ?>">
						<?php esc_html_e( 'View all', 'adam-comunidade' ); ?>
					</a>
				</div>
				<?php if ( $adam_list_teams ) : ?>
					<ul class="adam-dashboard-team-list">
						<?php foreach ( $adam_list_teams as $adam_list_team ) : ?>
							<li>
								<?php echo wp_get_attachment_image( (int) $adam_list_team->logo_id, array( 38, 38 ) ); ?>
								<div>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=adam-comunidade-team-edit&team_id=' . absint( $adam_list_team->id ) ) ); ?>">
										<?php echo esc_html( $adam_list_team->name ); ?>
									</a>
									<small><?php echo esc_html( $adam_list_team->municipality ?: $adam_list_team->district ); ?></small>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<div class="adam-empty-state"><?php esc_html_e( 'No teams yet.', 'adam-comunidade' ); ?></div>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>

	<section class="adam-card" aria-labelledby="adam-roadmap-heading">
		<div class="adam-card__header">
			<div>
				<span class="adam-card__eyebrow"><?php esc_html_e( 'What is next', 'adam-comunidade' ); ?></span>
				<h2 id="adam-roadmap-heading"><?php esc_html_e( 'Roadmap', 'adam-comunidade' ); ?></h2>
			</div>
			<span class="adam-badge"><?php esc_html_e( 'Phase 2', 'adam-comunidade' ); ?></span>
		</div>
		<ul class="adam-roadmap">
			<?php foreach ( $adam_comunidade_roadmap as $adam_comunidade_module ) : ?>
				<li>
					<span class="adam-roadmap__marker" aria-hidden="true"></span>
					<?php echo esc_html( $adam_comunidade_module ); ?>
					<span class="adam-badge adam-badge--muted">
						<?php esc_html_e( 'Upcoming', 'adam-comunidade' ); ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>
