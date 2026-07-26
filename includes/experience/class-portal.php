<?php
/**
 * Public contribution, ownership and moderation portal.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Directory\Validator as Directory_Validator;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Validator as Field_Validator;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Validator as Team_Validator;

/**
 * Keeps community contributions outside wp-admin and behind moderation.
 */
final class Portal {
	private const TYPES = array(
		'team'        => 'equipa',
		'field'       => 'campo',
		'partner'     => 'parceiro',
		'institution' => 'instituicao',
	);

	public function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template' ), 50 );
		add_filter( 'pre_get_document_title', array( $this, 'title' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 40 );
		add_action( 'admin_post_nopriv_adam_public_submission', array( $this, 'submit' ) );
		add_action( 'admin_post_adam_public_submission', array( $this, 'submit' ) );
		add_action( 'admin_post_adam_claim_listing', array( $this, 'claim' ) );
		add_action( 'admin_post_adam_owner_edit', array( $this, 'owner_edit' ) );
		add_action( 'admin_post_adam_moderate_submission', array( $this, 'moderate' ) );
		add_action( 'admin_post_adam_notification_read', array( $this, 'read_notification' ) );
		Admin_Router::register_page( 'moderation', array( 'title' => __( 'Moderation', 'adam-comunidade' ), 'controller' => $this, 'method' => 'moderation_page' ) );
		add_action( 'adam_comunidade_team_after_content', array( $this, 'claim_team' ) );
		add_action( 'adam_comunidade_field_after_content', array( $this, 'claim_field' ) );
	}

	public static function add_rewrite_rules(): void {
		foreach ( self::TYPES as $type => $slug ) {
			add_rewrite_rule( '^submeter-' . $slug . '/?$', 'index.php?adam_submission=' . $type, 'top' );
		}
		add_rewrite_rule( '^painel-comunidade/?$', 'index.php?adam_owner_dashboard=1', 'top' );
	}

	/**
	 * Returns the public submission URL for one supported object type.
	 *
	 * @param string $type Object type.
	 * @return string
	 */
	public static function submission_url( string $type ): string {
		$type = sanitize_key( $type );
		return isset( self::TYPES[ $type ] )
			? home_url( '/submeter-' . self::TYPES[ $type ] . '/' )
			: home_url( '/' );
	}

	public function query_vars( array $vars ): array {
		return array_merge( $vars, array( 'adam_submission', 'adam_owner_dashboard' ) );
	}

	public function template( string $template ): string {
		if ( get_query_var( 'adam_submission' ) || get_query_var( 'adam_owner_dashboard' ) ) {
			return Templates::locate( 'experience/portal.php' );
		}
		return $template;
	}

	public function title( string $title ): string {
		if ( get_query_var( 'adam_owner_dashboard' ) ) {
			return __( 'Community Dashboard', 'adam-comunidade' );
		}
		if ( 'field' === get_query_var( 'adam_submission' ) ) {
			return __( 'Submeter Campo', 'adam-comunidade' );
		}
		return get_query_var( 'adam_submission' ) ? __( 'Submit to ADAM Comunidade', 'adam-comunidade' ) : $title;
	}

	public function assets(): void {
		if ( ! get_query_var( 'adam_submission' ) && ! get_query_var( 'adam_owner_dashboard' ) ) {
			return;
		}
		wp_enqueue_style( 'adam-comunidade' );
		wp_enqueue_style( 'adam-experience', Helpers::url( 'assets/css/experience.css' ), array( 'adam-comunidade' ), ADAM_COMUNIDADE_VERSION );
		wp_enqueue_style( 'adam-comunidade-directory', Helpers::url( 'assets/css/directory-public.css' ), array( 'adam-experience' ), ADAM_COMUNIDADE_VERSION );
	}

	/**
	 * Renders the public route selected by WordPress.
	 */
	public static function render(): void {
		if ( get_query_var( 'adam_owner_dashboard' ) ) {
			self::render_dashboard();
			return;
		}
		self::render_submission_form( sanitize_key( (string) get_query_var( 'adam_submission' ) ) );
	}

	private static function render_submission_form( string $type ): void {
		if ( ! isset( self::TYPES[ $type ] ) ) {
			return;
		}
		?>
		<section class="adam-community-panel adam-portal-panel">
			<h1><?php echo 'field' === $type ? esc_html__( 'Submeter Campo', 'adam-comunidade' ) : esc_html__( 'Submit to ADAM Comunidade', 'adam-comunidade' ); ?></h1>
			<?php if ( 'field' === $type ) : ?>
				<div class="adam-legal-submission-warning" role="alert">
					<strong><?php esc_html_e( 'Autorização legal obrigatória', 'adam-comunidade' ); ?></strong>
					<p><?php esc_html_e( 'A ADAM apenas publica campos que tenham autorização ou permissão legal para funcionar. É obrigatório anexar uma cópia legível do documento de autorização.', 'adam-comunidade' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Submissões sem este documento não serão aprovadas.', 'adam-comunidade' ); ?></li>
						<li><?php esc_html_e( 'As informações, o documento e as fotografias são revistos manualmente pela administração da ADAM.', 'adam-comunidade' ); ?></li>
						<li><?php esc_html_e( 'O campo só aparece publicamente depois de ser aprovado.', 'adam-comunidade' ); ?></li>
					</ul>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Every submission is reviewed by ADAM before it can be published.', 'adam-comunidade' ); ?></p>
			<?php endif; ?>
			<?php self::status_notice(); ?>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data" class="adam-portal-form">
				<input type="hidden" name="action" value="adam_public_submission">
				<input type="hidden" name="object_type" value="<?php echo esc_attr( $type ); ?>">
				<?php wp_nonce_field( 'adam_public_submission', 'adam_nonce' ); ?>
				<label><?php esc_html_e( 'Name', 'adam-comunidade' ); ?><input name="name" required maxlength="190"></label>
				<label><?php esc_html_e( 'Email', 'adam-comunidade' ); ?><input name="contact_email" type="email" required></label>
				<label><?php esc_html_e( 'District', 'adam-comunidade' ); ?><input name="district" maxlength="100" <?php echo 'field' === $type ? 'required' : ''; ?>></label>
				<label><?php esc_html_e( 'Municipality', 'adam-comunidade' ); ?><input name="municipality" maxlength="100" <?php echo 'field' === $type ? 'required' : ''; ?>></label>
				<?php if ( 'field' === $type ) : ?><label class="adam-portal-form__wide"><?php esc_html_e( 'Address', 'adam-comunidade' ); ?><input name="address" maxlength="255" required></label><?php endif; ?>
				<label><?php esc_html_e( 'Website', 'adam-comunidade' ); ?><input name="website" type="url"></label>
				<?php if ( 'field' === $type ) : ?><label><?php esc_html_e( 'Phone', 'adam-comunidade' ); ?><input name="phone" type="tel" maxlength="50"></label><?php endif; ?>
				<label class="adam-portal-form__wide"><?php esc_html_e( 'Short description', 'adam-comunidade' ); ?><textarea name="short_description" rows="3" required></textarea></label>
				<label class="adam-portal-form__wide"><?php esc_html_e( 'Details for verification', 'adam-comunidade' ); ?><textarea name="verification_details" rows="4" required></textarea></label>
				<?php if ( 'field' === $type ) : ?>
					<label class="adam-portal-form__wide adam-portal-upload"><?php esc_html_e( 'Proof of legal authorisation', 'adam-comunidade' ); ?> *<input name="authorization_document" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required><small><?php esc_html_e( 'PDF, JPG or PNG. This document is required for administrative review.', 'adam-comunidade' ); ?></small></label>
					<label class="adam-portal-form__wide adam-portal-upload"><?php esc_html_e( 'Field photographs', 'adam-comunidade' ); ?><input name="field_photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple><small><?php esc_html_e( 'Optional. Upload up to five photographs.', 'adam-comunidade' ); ?></small></label>
				<?php endif; ?>
				<label class="adam-portal-form__wide"><input name="consent" type="checkbox" value="1" required> <?php esc_html_e( 'I confirm that this information is accurate and may be reviewed by ADAM.', 'adam-comunidade' ); ?></label>
				<button class="adam-community-button" type="submit"><?php esc_html_e( 'Send for review', 'adam-comunidade' ); ?></button>
			</form>
		</section>
		<?php
	}

	private static function render_dashboard(): void {
		if ( ! is_user_logged_in() ) {
			echo '<div class="adam-empty-state"><h1>' . esc_html__( 'Community Dashboard', 'adam-comunidade' ) . '</h1><p>' . esc_html__( 'Sign in to manage verified listings.', 'adam-comunidade' ) . '</p><a class="adam-community-button" href="' . esc_url( wp_login_url( home_url( '/painel-comunidade/' ) ) ) . '">' . esc_html__( 'Sign in', 'adam-comunidade' ) . '</a></div>';
			return;
		}
		global $wpdb;
		$user_id = get_current_user_id();
		$owners  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::owners_table() . ' WHERE user_id = %d AND status = %s', $user_id, 'verified' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$notices = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::notifications_table() . ' WHERE user_id = %d ORDER BY created_at DESC LIMIT 20', $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<section class="adam-community-panel adam-owner-dashboard">
			<h1><?php esc_html_e( 'Community Dashboard', 'adam-comunidade' ); ?></h1>
			<?php self::status_notice(); ?>
			<h2><?php esc_html_e( 'My listings', 'adam-comunidade' ); ?></h2>
			<?php if ( ! $owners ) : ?><div class="adam-empty-state"><?php esc_html_e( 'No verified listings are linked to your account yet.', 'adam-comunidade' ); ?></div><?php endif; ?>
			<?php foreach ( $owners as $owner ) : $record = self::record( $owner->object_type, (int) $owner->object_id ); if ( ! $record ) { continue; } ?>
				<article class="adam-card adam-owner-card">
					<h3><?php echo esc_html( $record->name ); ?></h3>
					<p><?php echo esc_html( ucfirst( $owner->object_type ) ); ?> · <?php echo esc_html( Quality::score( $record ) ); ?>% <?php esc_html_e( 'complete', 'adam-comunidade' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="adam-portal-form">
						<input type="hidden" name="action" value="adam_owner_edit"><input type="hidden" name="object_type" value="<?php echo esc_attr( $owner->object_type ); ?>"><input type="hidden" name="object_id" value="<?php echo esc_attr( $owner->object_id ); ?>">
						<?php wp_nonce_field( 'adam_owner_edit_' . $owner->object_type . '_' . $owner->object_id, 'adam_nonce' ); ?>
						<label><?php esc_html_e( 'Website', 'adam-comunidade' ); ?><input name="website" type="url" value="<?php echo esc_attr( $record->website ?? '' ); ?>"></label>
						<label><?php esc_html_e( 'Phone', 'adam-comunidade' ); ?><input name="phone" value="<?php echo esc_attr( $record->phone ?? '' ); ?>"></label>
						<label class="adam-portal-form__wide"><?php esc_html_e( 'Short description', 'adam-comunidade' ); ?><textarea name="short_description"><?php echo esc_textarea( $record->short_description ?? '' ); ?></textarea></label>
						<button class="adam-community-button" type="submit"><?php esc_html_e( 'Submit changes for review', 'adam-comunidade' ); ?></button>
					</form>
				</article>
			<?php endforeach; ?>
			<h2><?php esc_html_e( 'Notifications', 'adam-comunidade' ); ?></h2>
			<?php if ( ! $notices ) : ?><div class="adam-empty-state"><?php esc_html_e( 'You have no notifications.', 'adam-comunidade' ); ?></div><?php endif; ?>
			<?php foreach ( $notices as $notice ) : ?><div class="adam-notice <?php echo $notice->is_read ? '' : 'adam-notice--unread'; ?>"><strong><?php echo esc_html( $notice->title ); ?></strong><p><?php echo esc_html( $notice->message ); ?></p><?php if ( $notice->action_url ) : ?><a href="<?php echo esc_url( $notice->action_url ); ?>"><?php esc_html_e( 'Open', 'adam-comunidade' ); ?></a><?php endif; ?><?php if ( ! $notice->is_read ) : ?> · <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=adam_notification_read&notification_id=' . absint( $notice->id ) ), 'adam_notification_' . $notice->id ) ); ?>"><?php esc_html_e( 'Mark as read', 'adam-comunidade' ); ?></a><?php endif; ?></div><?php endforeach; ?>
		</section>
		<?php
	}

	public function submit(): void {
		check_admin_referer( 'adam_public_submission', 'adam_nonce' );
		$type = sanitize_key( wp_unslash( $_POST['object_type'] ?? '' ) );
		if ( ! isset( self::TYPES[ $type ] ) || empty( $_POST['consent'] ) ) {
			wp_die( esc_html__( 'Invalid submission.', 'adam-comunidade' ) );
		}
		$email = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			wp_die( esc_html__( 'Enter a valid email address.', 'adam-comunidade' ) );
		}
		$raw_website = trim( (string) wp_unslash( $_POST['website'] ?? '' ) );
		$website     = esc_url_raw( $raw_website, array( 'http', 'https' ) );
		if ( $raw_website && ( ! $website || ! wp_http_validate_url( $website ) ) ) {
			wp_die( esc_html__( 'Enter a valid website URL.', 'adam-comunidade' ) );
		}
		$payload = array(
			'name'              => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'slug'              => sanitize_title( wp_unslash( $_POST['name'] ?? '' ) ),
			'district'          => sanitize_text_field( wp_unslash( $_POST['district'] ?? '' ) ),
			'municipality'      => sanitize_text_field( wp_unslash( $_POST['municipality'] ?? '' ) ),
			'address'           => sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) ),
			'website'           => $website,
			'short_description' => sanitize_textarea_field( wp_unslash( $_POST['short_description'] ?? '' ) ),
			'email'             => $email,
			'phone'             => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
		);
		if (
			! $payload['name']
			|| (
				'field' === $type
				&& ( ! $payload['district'] || ! $payload['municipality'] || ! $payload['address'] )
			)
		) {
			wp_die( esc_html__( 'Complete all required field information.', 'adam-comunidade' ) );
		}
		if ( 'field' === $type ) {
			$authorization_document_id = $this->upload_file(
				'authorization_document',
				array( 'pdf', 'jpg', 'jpeg', 'png' ),
				true
			);
			if ( is_wp_error( $authorization_document_id ) ) {
				wp_die( esc_html( $authorization_document_id->get_error_message() ) );
			}
			$payload['authorization_document_id'] = $authorization_document_id;
			$payload['verification']              = 'verified_field';
			$payload['is_associated']              = 0;
			$payload['gallery_ids']                = $this->upload_photos( 'field_photos', 5 );
			$payload['cover_id']                   = $payload['gallery_ids'][0] ?? 0;
		}
		$this->insert_submission( 'new', $type, 0, $payload, $email, sanitize_textarea_field( wp_unslash( $_POST['verification_details'] ?? '' ) ) );
		wp_safe_redirect( add_query_arg( 'adam_status', 'submitted', self::submission_url( $type ) ) );
		exit;
	}

	public function claim(): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$type = sanitize_key( wp_unslash( $_POST['object_type'] ?? '' ) );
		$id   = absint( $_POST['object_id'] ?? 0 );
		check_admin_referer( 'adam_claim_' . $type . '_' . $id, 'adam_nonce' );
		if ( ! in_array( $type, array( 'team', 'field' ), true ) || ! self::record( $type, $id ) ) {
			wp_die( esc_html__( 'Invalid listing.', 'adam-comunidade' ) );
		}
		$user = wp_get_current_user();
		$this->insert_submission( 'claim', $type, $id, array(), $user->user_email, sanitize_textarea_field( wp_unslash( $_POST['verification_details'] ?? '' ) ) );
		wp_safe_redirect( add_query_arg( 'adam_status', 'claim-submitted', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	public function owner_edit(): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$type = sanitize_key( wp_unslash( $_POST['object_type'] ?? '' ) );
		$id   = absint( $_POST['object_id'] ?? 0 );
		check_admin_referer( 'adam_owner_edit_' . $type . '_' . $id, 'adam_nonce' );
		if ( ! $this->is_owner( get_current_user_id(), $type, $id ) ) {
			wp_die( esc_html__( 'You cannot edit this listing.', 'adam-comunidade' ) );
		}
		$raw_website = trim( (string) wp_unslash( $_POST['website'] ?? '' ) );
		$website     = esc_url_raw( $raw_website, array( 'http', 'https' ) );
		if ( $raw_website && ( ! $website || ! wp_http_validate_url( $website ) ) ) {
			wp_die( esc_html__( 'Enter a valid website URL.', 'adam-comunidade' ) );
		}
		$payload = array(
			'website'           => $website,
			'phone'             => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'short_description' => sanitize_textarea_field( wp_unslash( $_POST['short_description'] ?? '' ) ),
		);
		$this->insert_submission( 'edit', $type, $id, $payload, wp_get_current_user()->user_email, '' );
		wp_safe_redirect( add_query_arg( 'adam_status', 'changes-submitted', home_url( '/painel-comunidade/' ) ) );
		exit;
	}

	public function moderation_page(): void {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Schema::submissions_table() . " WHERE status IN ('pending','changes_requested') ORDER BY created_at ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Community moderation', 'adam-comunidade' ); ?></h1>
		<?php if ( ! $rows ) : ?><div class="adam-empty-state"><?php esc_html_e( 'The moderation queue is empty.', 'adam-comunidade' ); ?></div><?php endif; ?>
		<?php foreach ( $rows as $row ) : $payload = json_decode( $row->payload, true ) ?: array(); ?>
			<div class="adam-card"><h2>#<?php echo esc_html( $row->id ); ?> — <?php echo esc_html( ucfirst( $row->submission_type ) . ' / ' . ucfirst( $row->object_type ) ); ?></h2>
			<p><strong><?php esc_html_e( 'Contact', 'adam-comunidade' ); ?>:</strong> <?php echo esc_html( $row->contact_email ); ?></p>
			<pre><?php echo esc_html( wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			<p><?php echo esc_html( $row->verification_details ); ?></p>
			<?php if ( ! empty( $payload['authorization_document_id'] ) ) : ?><p><strong><?php esc_html_e( 'Legal authorisation:', 'adam-comunidade' ); ?></strong> <a href="<?php echo esc_url( wp_get_attachment_url( absint( $payload['authorization_document_id'] ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Review document', 'adam-comunidade' ); ?></a></p><?php endif; ?>
			<?php if ( ! empty( $payload['gallery_ids'] ) ) : ?><div class="adam-moderation-photos"><?php foreach ( array_map( 'absint', (array) $payload['gallery_ids'] ) as $photo_id ) : echo wp_get_attachment_image( $photo_id, 'thumbnail' ); endforeach; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><?php wp_nonce_field( 'adam_moderate_' . $row->id, 'adam_nonce' ); ?><input type="hidden" name="action" value="adam_moderate_submission"><input type="hidden" name="submission_id" value="<?php echo esc_attr( $row->id ); ?>"><textarea name="admin_note" placeholder="<?php esc_attr_e( 'Review note', 'adam-comunidade' ); ?>"></textarea> <button class="button button-primary" name="decision" value="approve"><?php esc_html_e( 'Approve and publish', 'adam-comunidade' ); ?></button> <button class="button" name="decision" value="changes"><?php esc_html_e( 'Request changes', 'adam-comunidade' ); ?></button> <button class="button button-link-delete" name="decision" value="reject"><?php esc_html_e( 'Reject', 'adam-comunidade' ); ?></button></form>
			</div>
		<?php endforeach; ?></div>
		<?php
	}

	public function moderate(): void {
		Admin_Router::authorize();
		global $wpdb;
		$id = absint( $_POST['submission_id'] ?? 0 );
		check_admin_referer( 'adam_moderate_' . $id, 'adam_nonce' );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::submissions_table() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $row || ! in_array( $row->status, array( 'pending', 'changes_requested' ), true ) ) {
			wp_die( esc_html__( 'Submission is no longer available.', 'adam-comunidade' ) );
		}
		$decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) );
		$status   = array( 'approve' => 'published', 'changes' => 'changes_requested', 'reject' => 'rejected' )[ $decision ] ?? '';
		if ( ! $status ) {
			wp_die( esc_html__( 'Invalid decision.', 'adam-comunidade' ) );
		}
		$object_id = (int) $row->object_id;
		if ( 'approve' === $decision ) {
			$result = $this->apply_approval( $row );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}
			$object_id = (int) $result;
		}
		$wpdb->update( Schema::submissions_table(), array( 'status' => $status, 'object_id' => $object_id, 'admin_note' => sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ?? '' ) ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		$this->notify( (int) $row->user_id, __( 'Submission reviewed', 'adam-comunidade' ), sprintf( __( 'Your %1$s submission is now %2$s.', 'adam-comunidade' ), $row->object_type, $status ) );
		do_action( 'adam_comunidade_submission_moderated', $id, $status, $object_id );
		wp_safe_redirect( Admin_Router::page_url( 'moderation' ) );
		exit;
	}

	private function apply_approval( object $row ): int|\WP_Error {
		global $wpdb;
		if ( 'claim' === $row->submission_type ) {
			if ( ! $row->user_id ) {
				return new \WP_Error( 'login_required', __( 'Claims require a user account.', 'adam-comunidade' ) );
			}
			$wpdb->replace( Schema::owners_table(), array( 'object_type' => $row->object_type, 'object_id' => $row->object_id, 'user_id' => $row->user_id, 'status' => 'verified', 'created_at' => current_time( 'mysql', true ) ) );
			return (int) $row->object_id;
		}
		$payload = json_decode( $row->payload, true ) ?: array();
		if (
			'field' === $row->object_type
			&& 'new' === $row->submission_type
			&& (
				empty( $payload['authorization_document_id'] )
				|| ! get_post( absint( $payload['authorization_document_id'] ) )
			)
		) {
			return new \WP_Error(
				'authorization_required',
				__( 'This field submission cannot be approved without its legal authorisation document.', 'adam-comunidade' )
			);
		}
		$record  = $row->object_id ? self::record( $row->object_type, (int) $row->object_id ) : null;
		$input   = array_merge( $record ? (array) $record : array(), $payload, array( 'status' => 'published' ) );
		if ( isset( $input['gallery'] ) && is_string( $input['gallery'] ) ) {
			$input['gallery'] = json_decode( $input['gallery'], true ) ?: array();
		}
		if ( isset( $input['playing_styles'] ) && is_string( $input['playing_styles'] ) ) {
			$input['playing_styles'] = json_decode( $input['playing_styles'], true ) ?: array();
		}
		if ( 'team' === $row->object_type ) {
			$repo = new Team_Repository();
			$data = ( new Team_Validator( $repo ) )->validate( $input, (int) $row->object_id );
		} elseif ( 'field' === $row->object_type ) {
			$repo = new Field_Repository();
			$data = ( new Field_Validator( $repo ) )->validate( $input, (int) $row->object_id );
		} elseif ( in_array( $row->object_type, array( 'partner', 'institution' ), true ) ) {
			$repo = new Directory_Repository();
			$data = Directory_Validator::sanitize( $row->object_type, $input );
		} else {
			return new \WP_Error( 'invalid_type', __( 'Unsupported content type.', 'adam-comunidade' ) );
		}
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$result = $record ? $repo->update( (int) $row->object_id, $data ) : $repo->create( $data );
		if ( ! $result ) {
			return new \WP_Error( 'save_failed', __( 'The listing could not be saved.', 'adam-comunidade' ) );
		}
		$result_id = $record ? (int) $row->object_id : (int) $result;
		if ( 'field' === $row->object_type && ! empty( $payload['gallery_ids'] ) ) {
			$repo->sync_gallery(
				$result_id,
				array_map(
					static fn( int $attachment_id ): array => array( 'id' => $attachment_id, 'caption' => '' ),
					array_slice( array_filter( array_map( 'absint', (array) $payload['gallery_ids'] ) ), 0, 5 )
				)
			);
		}
		return $result_id;
	}

	/**
	 * Stores one public upload in the Media Library.
	 *
	 * @param string   $field_name File input name.
	 * @param string[] $extensions Allowed extensions.
	 * @param bool     $required Whether the file is mandatory.
	 * @return int|\WP_Error
	 */
	private function upload_file( string $field_name, array $extensions, bool $required = false ): int|\WP_Error {
		if (
			empty( $_FILES[ $field_name ]['name'] )
			|| ! is_string( $_FILES[ $field_name ]['name'] )
		) {
			return $required
				? new \WP_Error( 'authorization_required', __( 'Proof of legal authorisation is required.', 'adam-comunidade' ) )
				: 0;
		}

		$extension = strtolower( (string) pathinfo( sanitize_file_name( wp_unslash( $_FILES[ $field_name ]['name'] ) ), PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, $extensions, true ) ) {
			return new \WP_Error( 'invalid_upload_type', __( 'The uploaded file type is not allowed.', 'adam-comunidade' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_handle_upload( $field_name, 0 );

		return is_wp_error( $attachment_id ) ? $attachment_id : absint( $attachment_id );
	}

	/**
	 * Stores a bounded set of public field photographs.
	 *
	 * @param string $field_name Multiple file input name.
	 * @param int    $limit Maximum files.
	 * @return int[]
	 */
	private function upload_photos( string $field_name, int $limit ): array {
		if ( empty( $_FILES[ $field_name ]['name'] ) || ! is_array( $_FILES[ $field_name ]['name'] ) ) {
			return array();
		}

		$original = $_FILES[ $field_name ];
		$ids      = array();
		$count    = min( $limit, count( $original['name'] ) );

		for ( $index = 0; $index < $count; ++$index ) {
			if ( UPLOAD_ERR_NO_FILE === (int) $original['error'][ $index ] ) {
				continue;
			}
			$_FILES[ $field_name ] = array(
				'name'     => $original['name'][ $index ],
				'type'     => $original['type'][ $index ],
				'tmp_name' => $original['tmp_name'][ $index ],
				'error'    => $original['error'][ $index ],
				'size'     => $original['size'][ $index ],
			);
			$attachment_id = $this->upload_file( $field_name, array( 'jpg', 'jpeg', 'png', 'webp' ) );
			if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
				$ids[] = $attachment_id;
			}
		}
		$_FILES[ $field_name ] = $original;

		return $ids;
	}

	private function insert_submission( string $submission_type, string $object_type, int $object_id, array $payload, string $email, string $verification ): void {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert( Schema::submissions_table(), array( 'submission_type' => $submission_type, 'object_type' => $object_type, 'object_id' => $object_id, 'user_id' => get_current_user_id(), 'contact_email' => $email, 'payload' => wp_json_encode( $payload ), 'verification_details' => $verification, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now ) );
		do_action( 'adam_comunidade_submission_received', (int) $wpdb->insert_id, $object_type );
	}

	private function is_owner( int $user_id, string $type, int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . Schema::owners_table() . ' WHERE user_id = %d AND object_type = %s AND object_id = %d AND status = %s', $user_id, $type, $id, 'verified' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function notify( int $user_id, string $title, string $message ): void {
		if ( ! $user_id ) {
			return;
		}
		global $wpdb;
		$wpdb->insert( Schema::notifications_table(), array( 'user_id' => $user_id, 'title' => $title, 'message' => $message, 'action_url' => home_url( '/painel-comunidade/' ), 'is_read' => 0, 'created_at' => current_time( 'mysql', true ) ) );
	}

	public function read_notification(): void {
		$id = absint( $_GET['notification_id'] ?? 0 );
		check_admin_referer( 'adam_notification_' . $id );
		global $wpdb;
		$wpdb->update( Schema::notifications_table(), array( 'is_read' => 1 ), array( 'id' => $id, 'user_id' => get_current_user_id() ) );
		wp_safe_redirect( home_url( '/painel-comunidade/' ) );
		exit;
	}

	public function claim_team( object $team ): void {
		$this->claim_box( 'team', (int) $team->id );
	}

	public function claim_field( object $field ): void {
		$this->claim_box( 'field', (int) $field->id );
	}

	private function claim_box( string $type, int $id ): void {
		if ( is_user_logged_in() && $this->is_owner( get_current_user_id(), $type, $id ) ) {
			echo '<p><a class="adam-community-button" href="' . esc_url( home_url( '/painel-comunidade/' ) ) . '">' . esc_html__( 'Manage this listing', 'adam-comunidade' ) . '</a></p>';
			return;
		}
		?>
		<details class="adam-claim-box"><summary><?php esc_html_e( 'Is this your organisation? Claim this page', 'adam-comunidade' ); ?></summary>
			<?php if ( ! is_user_logged_in() ) : ?><p><a href="<?php echo esc_url( wp_login_url( wp_get_referer() ?: home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign in to start verification.', 'adam-comunidade' ); ?></a></p>
			<?php else : ?><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="adam_claim_listing"><input type="hidden" name="object_type" value="<?php echo esc_attr( $type ); ?>"><input type="hidden" name="object_id" value="<?php echo esc_attr( $id ); ?>"><?php wp_nonce_field( 'adam_claim_' . $type . '_' . $id, 'adam_nonce' ); ?><textarea name="verification_details" required placeholder="<?php esc_attr_e( 'Explain your relationship to this listing and provide verification details.', 'adam-comunidade' ); ?>"></textarea><button class="adam-community-button" type="submit"><?php esc_html_e( 'Submit claim', 'adam-comunidade' ); ?></button></form><?php endif; ?>
		</details>
		<?php
	}

	private static function record( string $type, int $id ): ?object {
		return match ( $type ) {
			'team'  => ( new Team_Repository() )->find( $id ),
			'field' => ( new Field_Repository() )->find( $id ),
			'partner', 'institution' => ( new Directory_Repository() )->find( $id, $type ),
			default => null,
		};
	}

	private static function status_notice(): void {
		$status = sanitize_key( wp_unslash( $_GET['adam_status'] ?? '' ) );
		if ( $status ) {
			echo '<div class="adam-notice adam-notice--success">' . esc_html__( 'Thank you. Your request is safely queued for review.', 'adam-comunidade' ) . '</div>';
		}
	}
}
