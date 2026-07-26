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
	__( 'Events', 'adam-comunidade' ),
	__( 'Bookings', 'adam-comunidade' ),
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

	<nav class="adam-header-actions" aria-label="<?php esc_attr_e( 'Quick actions', 'adam-comunidade' ); ?>">
		<?php
		$adam_quick_actions = array(
			'teams'        => __( 'Add Team', 'adam-comunidade' ),
			'fields'       => __( 'Add Field', 'adam-comunidade' ),
			'partners'     => __( 'Add Partner', 'adam-comunidade' ),
			'institutions' => __( 'Add Institution', 'adam-comunidade' ),
			'brands'       => __( 'Add Brand', 'adam-comunidade' ),
		);
		foreach ( $adam_quick_actions as $adam_module => $adam_label ) :
			?>
			<a class="button button-primary adam-button" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( $adam_module, 'add' ) ); ?>"><?php echo esc_html( $adam_label ); ?></a>
		<?php endforeach; ?>
	</nav>

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

	<section aria-labelledby="adam-directory-statistics-heading">
		<div class="adam-section-heading">
			<h2 id="adam-directory-statistics-heading"><?php esc_html_e( 'Community Ecosystem', 'adam-comunidade' ); ?></h2>
		</div>
		<div class="adam-stat-grid adam-stat-grid--five">
			<?php
			$adam_directory_statistics = array(
				__( 'Teams', 'adam-comunidade' )        => $team_counts['all'],
				__( 'Fields', 'adam-comunidade' )       => $field_counts['all'],
				__( 'Partners', 'adam-comunidade' )     => $directory_counts['partner']['all'],
				__( 'Brands', 'adam-comunidade' )       => $directory_counts['brand']['all'],
				__( 'Institutions', 'adam-comunidade' ) => $directory_counts['institution']['all'],
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
		<div class="adam-section-heading"><h2 id="adam-community-insights-heading"><?php esc_html_e( 'Community Insights', 'adam-comunidade' ); ?></h2><a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::page_url( 'moderation' ) ); ?>"><?php esc_html_e( 'Open moderation', 'adam-comunidade' ); ?></a></div>
		<div class="adam-stat-grid">
			<?php foreach ( array( __( 'Pending submissions', 'adam-comunidade' ) => $community_insights['pending'], __( 'Pending claims', 'adam-comunidade' ) => $community_insights['claims'], __( 'Verified owners', 'adam-comunidade' ) => $community_insights['owners'], __( 'Upcoming calendar entries', 'adam-comunidade' ) => $community_insights['calendar'] ) as $adam_label => $adam_value ) : ?><div class="adam-stat-card"><span class="adam-stat-card__value"><?php echo esc_html( (string) $adam_value ); ?></span><span class="adam-stat-card__label"><?php echo esc_html( $adam_label ); ?></span></div><?php endforeach; ?>
		</div>
	</section>

	<div class="adam-dashboard-columns">
		<section class="adam-card">
			<div class="adam-card__header"><h2><?php esc_html_e( 'Recently Added', 'adam-comunidade' ); ?></h2></div>
			<?php if ( $recent_directory ) : ?><ul class="adam-dashboard-team-list"><?php foreach ( $recent_directory as $adam_entry ) : ?>
				<li><?php echo wp_get_attachment_image( (int) $adam_entry->logo_id, array( 42, 42 ) ); ?><div><strong><?php echo esc_html( $adam_entry->name ); ?></strong><small><?php echo esc_html( ucfirst( $adam_entry->entity_type ) ); ?></small></div></li>
			<?php endforeach; ?></ul><?php else : ?><div class="adam-empty-state"><?php esc_html_e( 'No directory content yet.', 'adam-comunidade' ); ?></div><?php endif; ?>
		</section>
		<section class="adam-card">
			<div class="adam-card__header"><h2><?php esc_html_e( 'Featured Content', 'adam-comunidade' ); ?></h2></div>
			<?php if ( $featured_directory ) : ?><ul class="adam-dashboard-team-list"><?php foreach ( array_slice( $featured_directory, 0, 6 ) as $adam_entry ) : ?>
				<li><?php echo wp_get_attachment_image( (int) $adam_entry->logo_id, array( 42, 42 ) ); ?><div><strong><?php echo esc_html( $adam_entry->name ); ?></strong><small><?php echo esc_html( ucfirst( $adam_entry->entity_type ) ); ?></small></div></li>
			<?php endforeach; ?></ul><?php else : ?><div class="adam-empty-state"><?php esc_html_e( 'No featured content yet.', 'adam-comunidade' ); ?></div><?php endif; ?>
		</section>
	</div>

	<section aria-labelledby="adam-field-statistics-heading">
		<div class="adam-section-heading">
			<h2 id="adam-field-statistics-heading"><?php esc_html_e( 'Field Statistics', 'adam-comunidade' ); ?></h2>
			<a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'fields' ) ); ?>"><?php esc_html_e( 'Manage fields', 'adam-comunidade' ); ?></a>
		</div>
		<div class="adam-stat-grid adam-stat-grid--five">
			<?php
			$adam_field_statistics = array(
				__( 'Fields', 'adam-comunidade' )           => $field_counts['all'],
				__( 'Published', 'adam-comunidade' )        => $field_counts['published'],
				__( 'Draft', 'adam-comunidade' )            => $field_counts['draft'],
				__( 'Hidden', 'adam-comunidade' )           => $field_counts['hidden'],
				__( 'Average Capacity', 'adam-comunidade' ) => $field_counts['average_capacity'],
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
			<h2><?php esc_html_e( 'Most Recently Updated Fields', 'adam-comunidade' ); ?></h2>
			<a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'fields' ) ); ?>"><?php esc_html_e( 'View all', 'adam-comunidade' ); ?></a>
		</div>
		<?php if ( $updated_fields ) : ?><ul class="adam-dashboard-team-list">
			<?php foreach ( $updated_fields as $adam_field ) : ?><li>
				<?php echo wp_get_attachment_image( (int) $adam_field->cover_id, array( 52, 38 ) ); ?>
				<div><a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'fields', 'edit', array( 'id' => absint( $adam_field->id ) ) ) ); ?>"><?php echo esc_html( $adam_field->name ); ?></a><small><?php echo esc_html( $adam_field->municipality ?: $adam_field->district ); ?></small></div>
			</li><?php endforeach; ?>
		</ul><?php else : ?><div class="adam-empty-state"><?php esc_html_e( 'No fields yet.', 'adam-comunidade' ); ?></div><?php endif; ?>
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
					<a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'teams' ) ); ?>">
						<?php esc_html_e( 'View all', 'adam-comunidade' ); ?>
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
			<span class="adam-badge"><?php esc_html_e( 'Phase 6', 'adam-comunidade' ); ?></span>
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
