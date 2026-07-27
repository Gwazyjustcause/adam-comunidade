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
use ADAM\Comunidade\Fields\Amenity_Repository;
use ADAM\Comunidade\Fields\Options as Field_Options;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Router as Field_Router;
use ADAM\Comunidade\Fields\Schema as Field_Schema;
use ADAM\Comunidade\Fields\Validator as Field_Validator;
use ADAM\Comunidade\Forms\Manager as Forms_Manager;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Managed_Pages;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Validator as Team_Validator;
use ADAM\Comunidade\Uploads\Component as Upload_Component;

/**
 * Keeps community contributions outside wp-admin and behind moderation.
 */
final class Portal {
	private static ?Forms_Manager $forms = null;
	private static ?Email_Service $emails = null;
	private const ROUTES_VERSION = '1.1.0';

	private const TYPES = array(
		'team'        => 'equipa',
		'field'       => 'campo',
		'partner'     => 'parceiro',
		'institution' => 'instituicao',
	);

	public function __construct( Forms_Manager $forms, ?Email_Service $emails = null ) {
		self::$forms = $forms;
		self::$emails = $emails ?? new Email_Service();
	}

	public function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_action( 'init', array( self::class, 'maybe_flush_rewrite_rules' ), 999 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template' ), 50 );
		add_filter( 'pre_get_document_title', array( $this, 'title' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 40 );
		add_action( 'admin_post_nopriv_adam_public_submission', array( $this, 'submit' ) );
		add_action( 'admin_post_adam_public_submission', array( $this, 'submit' ) );
		add_action( 'admin_post_adam_owner_edit', array( $this, 'owner_edit' ) );
		add_action( 'admin_post_adam_moderate_submission', array( $this, 'moderate' ) );
		add_action( 'admin_post_adam_notification_read', array( $this, 'read_notification' ) );
		Admin_Router::register_page( 'moderation', array( 'title' => __( 'Aprovações', 'adam-comunidade' ), 'controller' => $this, 'method' => 'moderation_page' ) );
	}

	public static function add_rewrite_rules(): void {
		foreach ( self::TYPES as $type => $slug ) {
			add_rewrite_rule( '^submeter-' . $slug . '/?$', 'index.php?adam_submission=' . $type, 'top' );
			add_rewrite_rule( '^submeter-' . $slug . '/sucesso/?$', 'index.php?adam_submission_success=' . $type, 'top' );
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

	/**
	 * Returns the clean success URL for one submission type.
	 */
	public static function success_url( string $type ): string {
		$type = sanitize_key( $type );
		return isset( self::TYPES[ $type ] )
			? trailingslashit( self::submission_url( $type ) ) . 'sucesso/'
			: home_url( '/' );
	}

	/**
	 * Flushes rewrite rules once when the submission route contract changes.
	 */
	public static function maybe_flush_rewrite_rules(): void {
		if ( self::ROUTES_VERSION === get_option( 'adam_comunidade_submission_routes_version' ) ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'adam_comunidade_submission_routes_version', self::ROUTES_VERSION, false );
	}

	public function query_vars( array $vars ): array {
		return array_merge( $vars, array( 'adam_submission', 'adam_submission_success', 'adam_owner_dashboard' ) );
	}

	public function template( string $template ): string {
		if ( get_query_var( 'adam_submission' ) || get_query_var( 'adam_submission_success' ) || get_query_var( 'adam_owner_dashboard' ) ) {
			return Templates::locate( 'experience/portal.php' );
		}
		return $template;
	}

	public function title( string $title ): string {
		if ( get_query_var( 'adam_owner_dashboard' ) ) {
			return __( 'Painel da Comunidade', 'adam-comunidade' );
		}
		$type = sanitize_key( (string) ( get_query_var( 'adam_submission_success' ) ?: get_query_var( 'adam_submission' ) ) );
		$form = self::forms()->get( $type );
		if ( get_query_var( 'adam_submission_success' ) && $form ) {
			return __( 'Submissão Recebida', 'adam-comunidade' );
		}
		return $form ? (string) $form['title'] : $title;
	}

	public function assets(): void {
		if ( ! get_query_var( 'adam_submission' ) && ! get_query_var( 'adam_submission_success' ) && ! get_query_var( 'adam_owner_dashboard' ) ) {
			return;
		}
		wp_enqueue_style( 'adam-comunidade' );
		wp_enqueue_style( 'adam-experience', Helpers::url( 'assets/css/experience.css' ), array( 'adam-comunidade' ), ADAM_COMUNIDADE_VERSION );
		wp_enqueue_style( 'adam-comunidade-directory', Helpers::url( 'assets/css/directory-public.css' ), array( 'adam-experience' ), ADAM_COMUNIDADE_VERSION );
		wp_enqueue_script( 'adam-experience', Helpers::url( 'assets/js/experience.js' ), array(), ADAM_COMUNIDADE_VERSION, true );
		Upload_Component::enqueue_assets();
		if ( 'field' === sanitize_key( (string) get_query_var( 'adam_submission' ) ) && function_exists( 'wp_enqueue_editor' ) ) {
			wp_enqueue_editor();
		}
	}

	/**
	 * Renders the public route selected by WordPress.
	 */
	public static function render(): void {
		if ( get_query_var( 'adam_owner_dashboard' ) ) {
			self::render_dashboard();
			return;
		}
		if ( get_query_var( 'adam_submission_success' ) ) {
			self::render_submission_success( sanitize_key( (string) get_query_var( 'adam_submission_success' ) ) );
			return;
		}
		self::render_submission_form( sanitize_key( (string) get_query_var( 'adam_submission' ) ) );
	}

	private static function render_submission_success( string $type ): void {
		$form = self::forms()->get( $type );
		if ( ! isset( self::TYPES[ $type ] ) || ! $form ) {
			return;
		}
		$labels = self::success_labels( $type );
		$fields = (array) ( $form['fields'] ?? array() );
		?>
		<section class="adam-community-panel adam-portal-panel adam-submission-success">
			<div class="adam-submission-success__icon" aria-hidden="true">✓</div>
			<p class="adam-submission-success__eyebrow"><?php esc_html_e( 'Pedido registado com sucesso', 'adam-comunidade' ); ?></p>
			<h1><?php esc_html_e( 'Submissão Recebida', 'adam-comunidade' ); ?></h1>
			<p class="adam-submission-success__lead"><?php echo esc_html( sprintf( __( 'Obrigado por submeter %s à ADAM.', 'adam-comunidade' ), $labels['subject'] ) ); ?></p>
			<p><?php echo esc_html( $form['success_message'] ); ?></p>

			<div class="adam-submission-success__review">
				<h2><?php esc_html_e( 'A nossa equipa irá agora analisar', 'adam-comunidade' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'As informações fornecidas', 'adam-comunidade' ); ?></li>
					<?php if ( ! empty( $fields['field_photos']['visible'] ) ) : ?><li><?php esc_html_e( 'As fotografias enviadas', 'adam-comunidade' ); ?></li><?php endif; ?>
					<?php if ( ! empty( $fields['authorization_document']['visible'] ) ) : ?><li><?php esc_html_e( 'O comprovativo de autorização legal', 'adam-comunidade' ); ?></li><?php endif; ?>
				</ul>
				<p><?php esc_html_e( 'Receberá um email a confirmar a receção da submissão e será novamente contactado quando a análise estiver concluída.', 'adam-comunidade' ); ?></p>
				<div class="adam-submission-success__time">
					<span><?php esc_html_e( 'Tempo médio de análise', 'adam-comunidade' ); ?></span>
					<strong><?php echo esc_html( (string) $form['review_time'] ); ?></strong>
				</div>
			</div>

			<ol class="adam-submission-timeline" aria-label="<?php esc_attr_e( 'Estado da submissão', 'adam-comunidade' ); ?>">
				<li class="is-complete"><span>✓</span><strong><?php esc_html_e( 'Submissão recebida', 'adam-comunidade' ); ?></strong></li>
				<li class="is-current"><span>⏳</span><strong><?php esc_html_e( 'Em análise', 'adam-comunidade' ); ?></strong></li>
				<li><span>○</span><strong><?php esc_html_e( 'Aguarda decisão', 'adam-comunidade' ); ?></strong></li>
				<li><span>○</span><strong><?php esc_html_e( 'Publicação', 'adam-comunidade' ); ?></strong></li>
			</ol>

			<div class="adam-submission-success__actions">
				<a class="adam-community-button adam-community-button--ghost" href="<?php echo esc_url( $labels['back_url'] ); ?>">← <?php echo esc_html( $labels['back_label'] ); ?></a>
				<a class="adam-community-button" href="<?php echo esc_url( self::submission_url( $type ) ); ?>"><?php echo esc_html( $labels['another_label'] ); ?></a>
			</div>
		</section>
		<?php
	}

	private static function render_submission_form( string $type ): void {
		$form = self::forms()->get( $type );
		if ( ! isset( self::TYPES[ $type ] ) || ! $form ) {
			return;
		}
		$state  = self::form_state( $type );
		$values = (array) ( $state['values'] ?? array() );
		$errors = (array) ( $state['errors'] ?? array() );
		?>
		<section class="adam-community-panel adam-portal-panel">
			<h1><?php echo esc_html( $form['title'] ); ?></h1>
			<?php if ( ! empty( $form['notice_title'] ) || ! empty( $form['notice_text'] ) ) : ?>
				<div class="adam-legal-submission-warning" role="alert">
					<strong><?php echo esc_html( $form['notice_title'] ); ?></strong>
					<p><?php echo nl2br( esc_html( $form['notice_text'] ) ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $form['description'] ) ) : ?><p><?php echo nl2br( esc_html( $form['description'] ) ); ?></p><?php endif; ?>
			<?php self::status_notice( $type ); ?>
			<?php if ( $errors ) : ?>
				<div class="adam-form-error-summary" id="adam-first-error" role="alert" tabindex="-1" data-adam-error-summary>
					<strong><?php esc_html_e( 'Corrija os campos assinalados e tente novamente.', 'adam-comunidade' ); ?></strong>
				</div>
			<?php endif; ?>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data" class="adam-portal-form" novalidate>
				<input type="hidden" name="action" value="adam_public_submission">
				<input type="hidden" name="object_type" value="<?php echo esc_attr( $type ); ?>">
				<?php wp_nonce_field( 'adam_public_submission', 'adam_nonce' ); ?>
				<?php foreach ( $form['fields'] as $key => $field ) : ?>
					<?php if ( ! empty( $field['visible'] ) ) : ?>
						<?php if ( 'field' === $type && 'recommended_players' === $key ) : ?><div class="adam-portal-form__wide adam-portal-section-heading"><h2><?php esc_html_e( 'Capacidade', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Indique valores aproximados para ajudar visitantes e o planeamento de eventos.', 'adam-comunidade' ); ?></p></div><?php endif; ?>
						<?php self::render_field( $key, $field, $values[ $key ] ?? '', (string) ( $errors[ $key ] ?? '' ), $type ); ?>
					<?php endif; ?>
				<?php endforeach; ?>
				<label class="adam-portal-consent adam-portal-form__wide<?php echo isset( $errors['consent'] ) ? ' has-error' : ''; ?>">
					<input name="consent" type="checkbox" value="1" required <?php checked( ! empty( $values['consent'] ) ); ?> <?php echo isset( $errors['consent'] ) ? 'aria-invalid="true" aria-describedby="adam-error-consent"' : ''; ?>>
					<span><?php echo esc_html( $form['confirmation_message'] ); ?></span>
					<?php if ( isset( $errors['consent'] ) ) : ?><span class="adam-field-error" id="adam-error-consent"><?php echo esc_html( $errors['consent'] ); ?></span><?php endif; ?>
				</label>
				<button class="adam-community-button" type="submit"><?php echo esc_html( $form['submit_label'] ); ?></button>
			</form>
		</section>
		<?php
	}

	/**
	 * Renders one field from the shared schema.
	 *
	 * @param string              $key Field key.
	 * @param array<string,mixed> $field Field configuration.
	 */
	private static function render_field( string $key, array $field, mixed $value = '', string $error = '', string $object_type = '' ): void {
		$type     = (string) $field['type'];
		$required = ! empty( $field['required'] );
		$is_wide  = in_array( $type, array( 'textarea', 'richtext', 'playing_styles', 'amenities', 'file' ), true );
		$multiple = 'file' === $type && absint( $field['max_files'] ) > 1;
		$name     = $multiple ? $key . '[]' : $key;
		if ( 'file' === $type ) {
			?>
			<div class="<?php echo esc_attr( 'adam-portal-form__wide adam-portal-upload-field ' . ( $error ? 'has-error' : '' ) ); ?>">
				<strong><?php echo esc_html( $field['label'] ); ?><?php echo $required ? ' *' : ''; ?></strong>
				<?php if ( ! empty( $field['description'] ) ) : ?><span class="adam-portal-field-description"><?php echo esc_html( $field['description'] ); ?></span><?php endif; ?>
				<?php
				Upload_Component::render(
					array(
						'id'          => 'adam-upload-' . $key,
						'mode'        => 'file',
						'kind'        => str_contains( (string) $field['accept'], '.pdf' ) ? 'document' : 'image',
						'name'        => $name,
						'label'       => (string) $field['label'],
						'accept'      => (string) $field['accept'],
						'multiple'    => $multiple,
						'max'         => absint( $field['max_files'] ),
						'max_size_mb' => absint( $field['max_size_mb'] ),
						'required'    => $required,
						'error'       => $error,
					)
				);
				?>
				<?php if ( ! empty( $field['help_text'] ) ) : ?><small><?php echo esc_html( $field['help_text'] ); ?></small><?php endif; ?>
			</div>
			<?php
			return;
		}
		if ( in_array( $type, array( 'playing_styles', 'amenities' ), true ) ) {
			$selected = array_map( 'strval', (array) $value );
			$options  = 'playing_styles' === $type
				? ( 'team' === $object_type ? \ADAM\Comunidade\Teams\Options::playing_styles() : Field_Options::playing_styles() )
				: array_column( ( new Amenity_Repository() )->all( 'field', true ), 'label', 'id' );
			?>
			<fieldset class="adam-portal-form__wide adam-portal-options<?php echo $error ? ' has-error' : ''; ?>">
				<legend><?php echo esc_html( $field['label'] ); ?><?php echo $required ? ' *' : ''; ?></legend>
				<?php if ( ! empty( $field['description'] ) ) : ?><span class="adam-portal-field-description"><?php echo esc_html( $field['description'] ); ?></span><?php endif; ?>
				<div class="adam-portal-options__grid">
					<?php foreach ( $options as $option_key => $option_label ) : ?>
						<label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>[]" value="<?php echo esc_attr( (string) $option_key ); ?>" <?php checked( in_array( (string) $option_key, $selected, true ) ); ?>> <span><?php echo esc_html( (string) $option_label ); ?></span></label>
					<?php endforeach; ?>
				</div>
				<?php if ( $error ) : ?><span class="adam-field-error" id="adam-error-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $error ); ?></span><?php endif; ?>
			</fieldset>
			<?php
			return;
		}
		if ( 'richtext' === $type ) {
			?>
			<div class="adam-portal-form__wide adam-portal-richtext<?php echo $error ? ' has-error' : ''; ?>">
				<h2><?php echo esc_html( $field['label'] ); ?><?php echo $required ? ' *' : ''; ?></h2>
				<?php if ( ! empty( $field['description'] ) ) : ?><span class="adam-portal-field-description"><?php echo esc_html( $field['description'] ); ?></span><?php endif; ?>
				<?php wp_editor( wp_kses_post( (string) $value ), 'adam_public_' . sanitize_key( $key ), array( 'textarea_name' => $key, 'textarea_rows' => 9, 'media_buttons' => false, 'teeny' => true ) ); ?>
				<?php if ( $error ) : ?><span class="adam-field-error" id="adam-error-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $error ); ?></span><?php endif; ?>
			</div>
			<?php
			return;
		}
		if ( 'team_recruitment' === $type ) {
			?>
			<label class="<?php echo esc_attr( $error ? 'has-error' : '' ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<select name="<?php echo esc_attr( $name ); ?>">
					<option value=""><?php esc_html_e( 'Não indicado', 'adam-comunidade' ); ?></option>
					<?php foreach ( \ADAM\Comunidade\Teams\Options::recruitment_statuses() as $option_key => $option_label ) : ?>
						<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( (string) $value, $option_key ); ?>><?php echo esc_html( $option_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $error ) : ?><span class="adam-field-error" id="adam-error-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $error ); ?></span><?php endif; ?>
			</label>
			<?php
			return;
		}
		?>
		<label class="<?php echo esc_attr( ( $is_wide ? 'adam-portal-form__wide ' : '' ) . ( $error ? 'has-error' : '' ) ); ?>">
			<?php echo esc_html( $field['label'] ); ?><?php echo $required ? ' *' : ''; ?>
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="adam-portal-field-description"><?php echo esc_html( $field['description'] ); ?></span><?php endif; ?>
			<?php if ( 'textarea' === $type ) : ?>
				<textarea name="<?php echo esc_attr( $name ); ?>" rows="4" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $error ? 'aria-invalid="true" aria-describedby="adam-error-' . esc_attr( $key ) . '"' : ''; ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php else : ?>
				<input name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" <?php echo 'number' === $type ? 'min="0" step="1"' : ''; ?> <?php echo $required ? 'required' : ''; ?> <?php echo $error ? 'aria-invalid="true" aria-describedby="adam-error-' . esc_attr( $key ) . '"' : ''; ?>>
			<?php endif; ?>
			<?php if ( ! empty( $field['help_text'] ) ) : ?><small><?php echo esc_html( $field['help_text'] ); ?></small><?php endif; ?>
			<?php if ( $error ) : ?><span class="adam-field-error" id="adam-error-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $error ); ?></span><?php endif; ?>
		</label>
		<?php
	}

	private static function render_dashboard(): void {
		if ( ! is_user_logged_in() ) {
			echo '<div class="adam-empty-state"><h1>' . esc_html__( 'Painel da Comunidade', 'adam-comunidade' ) . '</h1><p>' . esc_html__( 'Inicie sessão para gerir os registos verificados.', 'adam-comunidade' ) . '</p><a class="adam-community-button" href="' . esc_url( wp_login_url( home_url( '/painel-comunidade/' ) ) ) . '">' . esc_html__( 'Iniciar sessão', 'adam-comunidade' ) . '</a></div>';
			return;
		}
		global $wpdb;
		$user_id = get_current_user_id();
		$owners  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::owners_table() . ' WHERE user_id = %d AND status = %s', $user_id, 'verified' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$notices = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::notifications_table() . ' WHERE user_id = %d ORDER BY created_at DESC LIMIT 20', $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<section class="adam-community-panel adam-owner-dashboard">
			<h1><?php esc_html_e( 'Painel da Comunidade', 'adam-comunidade' ); ?></h1>
			<?php self::status_notice(); ?>
			<h2><?php esc_html_e( 'Os meus registos', 'adam-comunidade' ); ?></h2>
			<?php if ( ! $owners ) : ?><div class="adam-empty-state"><?php esc_html_e( 'Ainda não existem registos verificados associados à sua conta.', 'adam-comunidade' ); ?></div><?php endif; ?>
			<?php foreach ( $owners as $owner ) : $record = self::record( $owner->object_type, (int) $owner->object_id ); if ( ! $record ) { continue; } ?>
				<article class="adam-card adam-owner-card">
					<h3><?php echo esc_html( $record->name ); ?></h3>
					<p><?php echo esc_html( ucfirst( $owner->object_type ) ); ?> · <?php echo esc_html( Quality::score( $record ) ); ?>% <?php esc_html_e( 'completo', 'adam-comunidade' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="adam-portal-form">
						<input type="hidden" name="action" value="adam_owner_edit"><input type="hidden" name="object_type" value="<?php echo esc_attr( $owner->object_type ); ?>"><input type="hidden" name="object_id" value="<?php echo esc_attr( $owner->object_id ); ?>">
						<?php wp_nonce_field( 'adam_owner_edit_' . $owner->object_type . '_' . $owner->object_id, 'adam_nonce' ); ?>
						<label><?php esc_html_e( 'Website', 'adam-comunidade' ); ?><input name="website" type="url" value="<?php echo esc_attr( $record->website ?? '' ); ?>"></label>
						<label><?php esc_html_e( 'Telefone', 'adam-comunidade' ); ?><input name="phone" value="<?php echo esc_attr( $record->phone ?? '' ); ?>"></label>
						<label class="adam-portal-form__wide"><?php esc_html_e( 'Descrição breve', 'adam-comunidade' ); ?><textarea name="short_description"><?php echo esc_textarea( $record->short_description ?? '' ); ?></textarea></label>
						<button class="adam-community-button" type="submit"><?php esc_html_e( 'Enviar alterações para revisão', 'adam-comunidade' ); ?></button>
					</form>
				</article>
			<?php endforeach; ?>
			<h2><?php esc_html_e( 'Notificações', 'adam-comunidade' ); ?></h2>
			<?php if ( ! $notices ) : ?><div class="adam-empty-state"><?php esc_html_e( 'Não tem notificações.', 'adam-comunidade' ); ?></div><?php endif; ?>
			<?php foreach ( $notices as $notice ) : ?><div class="adam-notice <?php echo $notice->is_read ? '' : 'adam-notice--unread'; ?>"><strong><?php echo esc_html( $notice->title ); ?></strong><p><?php echo esc_html( $notice->message ); ?></p><?php if ( $notice->action_url ) : ?><a href="<?php echo esc_url( $notice->action_url ); ?>"><?php esc_html_e( 'Abrir', 'adam-comunidade' ); ?></a><?php endif; ?><?php if ( ! $notice->is_read ) : ?> · <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=adam_notification_read&notification_id=' . absint( $notice->id ) ), 'adam_notification_' . $notice->id ) ); ?>"><?php esc_html_e( 'Marcar como lida', 'adam-comunidade' ); ?></a><?php endif; ?></div><?php endforeach; ?>
		</section>
		<?php
	}

	public function submit(): void {
		check_admin_referer( 'adam_public_submission', 'adam_nonce' );
		$type = sanitize_key( wp_unslash( $_POST['object_type'] ?? '' ) );
		if ( ! isset( self::TYPES[ $type ] ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		$form    = self::forms()->get( $type );
		$payload = array();
		$email   = '';
		$values  = array( 'consent' => empty( $_POST['consent'] ) ? '' : '1' );
		$errors  = array();

		foreach ( $form['fields'] as $key => $field ) {
			if ( empty( $field['visible'] ) ) {
				continue;
			}
			if ( 'file' === $field['type'] ) {
				$result = $this->validate_form_upload( $key, $field );
				if ( is_wp_error( $result ) ) {
					$errors[ $key ] = $result->get_error_message();
				}
				continue;
			}
			if ( in_array( $field['type'], array( 'playing_styles', 'amenities' ), true ) ) {
				$raw = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] )
					? wp_unslash( $_POST[ $key ] )
					: array();
				if ( 'playing_styles' === $field['type'] ) {
					$allowed = array_keys( 'team' === $type ? \ADAM\Comunidade\Teams\Options::playing_styles() : Field_Options::playing_styles() );
					$value   = array_values( array_intersect( array_map( 'sanitize_key', $raw ), $allowed ) );
					$payload['playing_styles'] = $value;
				} else {
					$allowed = array_map(
						static fn( object $amenity ): int => (int) $amenity->id,
						( new Amenity_Repository() )->all( 'field', true )
					);
					$value = array_values( array_intersect( array_map( 'absint', $raw ), $allowed ) );
					$payload['amenity_ids'] = $value;
				}
				$values[ $key ] = $value;
				if ( ! empty( $field['required'] ) && ! $value ) {
					$errors[ $key ] = sprintf( __( 'Preencha o campo obrigatório: %s.', 'adam-comunidade' ), $field['label'] );
				}
				continue;
			}

			$value = trim( (string) wp_unslash( $_POST[ $key ] ?? '' ) );
			$values[ $key ] = $value;
			if ( ! empty( $field['required'] ) && '' === $value ) {
				$errors[ $key ] = sprintf( __( 'Preencha o campo obrigatório: %s.', 'adam-comunidade' ), $field['label'] );
				continue;
			}
			$value = $this->sanitize_form_value( $value, (string) $field['type'] );
			if ( is_wp_error( $value ) ) {
				$errors[ $key ] = $value->get_error_message();
				continue;
			}
			if ( 'contact_email' === $key ) {
				$email            = (string) $value;
				$payload['email'] = $email;
			} elseif ( 'verification_details' !== $key ) {
				$payload[ $key ] = $value;
			}
		}
		if (
			'field' === $type
			&& ! empty( $payload['max_players'] )
			&& absint( $payload['recommended_players'] ?? 0 ) > absint( $payload['max_players'] )
		) {
			$errors['recommended_players'] = __( 'O número recomendado de jogadores não pode exceder o máximo.', 'adam-comunidade' );
		}

		if ( empty( $_POST['consent'] ) ) {
			$errors['consent'] = __( 'Confirme a declaração antes de enviar.', 'adam-comunidade' );
		}
		if ( $errors ) {
			$this->redirect_form_errors( $type, $values, $errors );
		}

		$duplicate = $this->field_duplicate_error( $type, (string) $payload['name'], $email );
		if ( $duplicate ) {
			$this->redirect_form_errors( $type, $values, array( 'name' => $duplicate ) );
		}

		$uploaded_ids = array();
		foreach ( $form['fields'] as $key => $field ) {
			if ( empty( $field['visible'] ) || 'file' !== $field['type'] ) {
				continue;
			}
			$result = $this->process_form_upload( $key, $field );
			if ( is_wp_error( $result ) ) {
				$this->delete_uploaded_attachments( $uploaded_ids );
				$this->redirect_form_errors( $type, $values, array( $key => $result->get_error_message() ) );
			}
			if ( ! $result ) {
				continue;
			}
			if ( 'authorization_document' === $key ) {
				$payload['authorization_document_id'] = absint( $result );
			} elseif ( 'field_photos' === $key ) {
				$payload['gallery_ids'] = array_map( 'absint', (array) $result );
				$payload['cover_id']    = $payload['gallery_ids'][0] ?? 0;
			} elseif ( 'team_logo' === $key ) {
				$payload['logo_id'] = absint( $result );
			} elseif ( 'team_cover' === $key ) {
				$payload['cover_id'] = absint( $result );
			} elseif ( 'team_photos' === $key ) {
				$payload['gallery'] = array_map( 'absint', (array) $result );
			} else {
				$payload[ $key ] = $result;
			}
			$uploaded_ids = array_merge( $uploaded_ids, array_map( 'absint', (array) $result ) );
		}

		$payload['slug'] = sanitize_title( $payload['name'] );
		if ( 'field' === $type ) {
			$payload['verification']  = 'verified_field';
			$payload['is_associated'] = 0;
		}
		if ( 'team' === $type ) {
			$recruitment = sanitize_key( (string) ( $payload['recruitment_status'] ?? '' ) );
			$payload['recruitment_status'] = isset( \ADAM\Comunidade\Teams\Options::recruitment_statuses()[ $recruitment ] )
				? $recruitment
				: 'closed';
			$payload['is_associated'] = 0;
		}
		$submission_id = $this->insert_submission( 'new', $type, 0, $payload, $email, sanitize_textarea_field( wp_unslash( $_POST['verification_details'] ?? '' ) ) );
		if ( ! $submission_id ) {
			$this->delete_uploaded_attachments( $uploaded_ids );
			$this->redirect_form_errors( $type, $values, array( 'form' => __( 'Não foi possível guardar a submissão. Tente novamente.', 'adam-comunidade' ) ) );
		}
		if ( 'field' === $type ) {
			self::emails()->send(
				'field_received',
				$email,
				array(
					'field_name' => (string) $payload['name'],
					'field_url'  => '',
					'admin_note' => '',
				)
			);
		}
		wp_safe_redirect( self::success_url( $type ) );
		exit;
	}

	/**
	 * Sanitizes a submitted scalar using the configured control type.
	 *
	 * @return string|\WP_Error
	 */
	private function sanitize_form_value( string $value, string $type ): string|\WP_Error {
		if ( 'email' === $type ) {
			$email = sanitize_email( $value );
			return $value && ! is_email( $email ) ? new \WP_Error( 'invalid_email', __( 'Introduza um endereço de e-mail válido.', 'adam-comunidade' ) ) : $email;
		}
		if ( 'url' === $type ) {
			$url = esc_url_raw( $value, array( 'http', 'https' ) );
			return $value && ( ! $url || ! wp_http_validate_url( $url ) ) ? new \WP_Error( 'invalid_url', __( 'Introduza um endereço Web válido.', 'adam-comunidade' ) ) : $url;
		}
		if ( 'textarea' === $type ) {
			return sanitize_textarea_field( $value );
		}
		if ( 'richtext' === $type ) {
			return wp_kses_post( $value );
		}
		if ( 'number' === $type ) {
			if ( '' === $value ) {
				return '';
			}
			if ( ! ctype_digit( $value ) ) {
				return new \WP_Error( 'invalid_number', __( 'Introduza um número inteiro igual ou superior a zero.', 'adam-comunidade' ) );
			}
			return (string) absint( $value );
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Validates upload metadata before anything is added to the Media Library.
	 *
	 * @param string              $key Field key.
	 * @param array<string,mixed> $field Field configuration.
	 */
	private function validate_form_upload( string $key, array $field ): true|\WP_Error {
		$names = $_FILES[ $key ]['name'] ?? array();
		$sizes = $_FILES[ $key ]['size'] ?? array();
		$codes = $_FILES[ $key ]['error'] ?? array();
		$names = is_array( $names ) ? $names : array( $names );
		$sizes = is_array( $sizes ) ? $sizes : array( $sizes );
		$codes = is_array( $codes ) ? $codes : array( $codes );
		$files = array();

		foreach ( $names as $index => $name ) {
			if ( '' === (string) $name || UPLOAD_ERR_NO_FILE === (int) ( $codes[ $index ] ?? UPLOAD_ERR_NO_FILE ) ) {
				continue;
			}
			$files[] = array(
				'name'  => (string) $name,
				'size'  => absint( $sizes[ $index ] ?? 0 ),
				'error' => (int) ( $codes[ $index ] ?? UPLOAD_ERR_OK ),
			);
		}

		if ( ! $files ) {
			return ! empty( $field['required'] )
				? new \WP_Error( 'upload_required', sprintf( __( 'É obrigatório anexar: %s.', 'adam-comunidade' ), $field['label'] ) )
				: true;
		}

		$limit = max( 1, absint( $field['max_files'] ) );
		if ( count( $files ) > $limit ) {
			return new \WP_Error( 'too_many_uploads', sprintf( __( 'Pode selecionar no máximo %d fotografias.', 'adam-comunidade' ), $limit ) );
		}

		$extensions = array_values(
			array_filter(
				array_map(
					static fn( string $extension ): string => ltrim( strtolower( trim( $extension ) ), '.' ),
					explode( ',', (string) $field['accept'] )
				)
			)
		);
		$max_size = max( 1, absint( $field['max_size_mb'] ) ) * MB_IN_BYTES;
		foreach ( $files as $file ) {
			if ( UPLOAD_ERR_OK !== $file['error'] ) {
				return new \WP_Error( 'upload_failed', __( 'Não foi possível receber um dos ficheiros. Selecione-o novamente.', 'adam-comunidade' ) );
			}
			$extension = strtolower( (string) pathinfo( sanitize_file_name( wp_unslash( $file['name'] ) ), PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, $extensions, true ) ) {
				return new \WP_Error( 'invalid_upload_type', __( 'O tipo de ficheiro enviado não é permitido.', 'adam-comunidade' ) );
			}
			if ( $file['size'] > $max_size ) {
				return new \WP_Error( 'upload_too_large', sprintf( __( 'Cada ficheiro pode ter no máximo %d MB.', 'adam-comunidade' ), max( 1, absint( $field['max_size_mb'] ) ) ) );
			}
		}

		return true;
	}

	/**
	 * Blocks active duplicate field submissions and already published fields.
	 */
	private function field_duplicate_error( string $type, string $name, string $email ): string {
		if ( 'field' !== $type ) {
			return '';
		}

		global $wpdb;
		$published = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . Field_Schema::fields_table() . ' WHERE name = %s AND status = %s LIMIT 1',
				$name,
				'published'
			)
		);
		if ( $published ) {
			return sprintf(
				__( 'Este campo já está publicado. Para atualizar a informação existente, contacte a ADAM através de %s.', 'adam-comunidade' ),
				self::emails()->contact_email()
			);
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT payload FROM ' . Schema::submissions_table() . " WHERE submission_type = 'new' AND object_type = 'field' AND contact_email = %s AND status IN ('pending','changes_requested','awaiting_information','under_review')",
				$email
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $rows as $row ) {
			$payload = json_decode( (string) $row->payload, true );
			if ( is_array( $payload ) && 0 === strcasecmp( trim( (string) ( $payload['name'] ?? '' ) ), trim( $name ) ) ) {
				return __( 'Já existe uma submissão deste campo, com este e-mail, em análise. Aguarde a revisão ou contacte a ADAM se precisar de acrescentar informação.', 'adam-comunidade' );
			}
		}

		return '';
	}

	/**
	 * Stores scalar form state briefly and returns to the public form.
	 *
	 * @param array<string,string> $values Submitted scalar values.
	 * @param array<string,string> $errors Field errors.
	 * @return never
	 */
	private function redirect_form_errors( string $type, array $values, array $errors ): never {
		$token = strtolower( wp_generate_password( 32, false, false ) );
		set_transient(
			'adam_submission_state_' . $token,
			array(
				'type'   => $type,
				'values' => $values,
				'errors' => $errors,
			),
			15 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'adam_form_state', rawurlencode( $token ), self::submission_url( $type ) ) . '#adam-first-error' );
		exit;
	}

	/**
	 * Removes uploads created by an otherwise failed submission.
	 *
	 * @param int[] $ids Attachment IDs.
	 */
	private function delete_uploaded_attachments( array $ids ): void {
		foreach ( array_filter( array_map( 'absint', $ids ) ) as $id ) {
			wp_delete_attachment( $id, true );
		}
	}

	/**
	 * Processes one configurable upload field.
	 *
	 * @param string              $key Field key.
	 * @param array<string,mixed> $field Field configuration.
	 * @return int|int[]|\WP_Error
	 */
	private function process_form_upload( string $key, array $field ): int|array|\WP_Error {
		$extensions = array_values(
			array_filter(
				array_map(
					static fn( string $extension ): string => ltrim( strtolower( trim( $extension ) ), '.' ),
					explode( ',', (string) $field['accept'] )
				),
				static fn( string $extension ): bool => (bool) preg_match( '/^[a-z0-9]+$/', $extension )
			)
		);
		if ( empty( $extensions ) ) {
			$extensions = array( 'pdf', 'jpg', 'jpeg', 'png' );
		}
		if ( absint( $field['max_files'] ) > 1 ) {
			$files = $this->upload_photos( $key, absint( $field['max_files'] ), $extensions, absint( $field['max_size_mb'] ) );
			if ( is_wp_error( $files ) ) {
				return $files;
			}
			return ! empty( $field['required'] ) && ! $files
				? new \WP_Error( 'upload_required', sprintf( __( 'É obrigatório anexar: %s.', 'adam-comunidade' ), $field['label'] ) )
				: $files;
		}
		return $this->upload_file( $key, $extensions, ! empty( $field['required'] ), absint( $field['max_size_mb'] ) );
	}

	public function owner_edit(): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$type = sanitize_key( wp_unslash( $_POST['object_type'] ?? '' ) );
		$id   = absint( $_POST['object_id'] ?? 0 );
		check_admin_referer( 'adam_owner_edit_' . $type . '_' . $id, 'adam_nonce' );
		if ( ! $this->is_owner( get_current_user_id(), $type, $id ) ) {
			wp_die( esc_html__( 'Não pode editar este registo.', 'adam-comunidade' ) );
		}
		$raw_website = trim( (string) wp_unslash( $_POST['website'] ?? '' ) );
		$website     = esc_url_raw( $raw_website, array( 'http', 'https' ) );
		if ( $raw_website && ( ! $website || ! wp_http_validate_url( $website ) ) ) {
			wp_die( esc_html__( 'Introduza um endereço Web válido.', 'adam-comunidade' ) );
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
		<div class="wrap adam-comunidade-admin adam-approvals">
			<header class="adam-page-header">
				<div>
					<h1><?php esc_html_e( 'Aprovações', 'adam-comunidade' ); ?></h1>
					<p><?php esc_html_e( 'Reveja a informação e os documentos antes de publicar conteúdos na Comunidade.', 'adam-comunidade' ); ?></p>
				</div>
				<span class="adam-approval-count"><?php echo esc_html( sprintf( _n( '%d pedido pendente', '%d pedidos pendentes', count( $rows ), 'adam-comunidade' ), count( $rows ) ) ); ?></span>
			</header>
			<?php if ( ! $rows ) : ?>
				<div class="adam-card adam-empty-state"><h2><?php esc_html_e( 'Tudo tratado', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Não existem submissões a aguardar revisão.', 'adam-comunidade' ); ?></p></div>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<?php
				$payload = json_decode( $row->payload, true ) ?: array();
				$form    = self::forms()->get( (string) $row->object_type );
				$labels  = array();
				foreach ( (array) ( $form['fields'] ?? array() ) as $key => $field ) {
					$labels[ $key ] = $field['label'];
				}
				$labels['amenity_ids'] = $labels['amenities'] ?? __( 'Comodidades', 'adam-comunidade' );
				$playing_style_labels  = Field_Options::playing_styles();
				$amenity_labels        = array_column( ( new Amenity_Repository() )->all( 'field' ), 'label', 'id' );
				?>
				<article class="adam-card adam-approval-card">
					<header class="adam-approval-card__header">
						<div>
							<span class="adam-card__eyebrow"><?php echo esc_html( self::submission_type_label( (string) $row->submission_type ) ); ?></span>
							<h2><?php echo esc_html( $payload['name'] ?? sprintf( __( 'Pedido #%d', 'adam-comunidade' ), $row->id ) ); ?></h2>
							<p><?php echo esc_html( self::object_type_label( (string) $row->object_type ) ); ?> · <?php echo esc_html( mysql2date( get_option( 'date_format' ), $row->created_at ) ); ?></p>
						</div>
						<span class="adam-status-pill"><?php echo esc_html( 'changes_requested' === $row->status ? __( 'Alterações pedidas', 'adam-comunidade' ) : __( 'Pendente', 'adam-comunidade' ) ); ?></span>
					</header>

					<div class="adam-approval-card__layout">
						<section>
							<h3><?php esc_html_e( 'Informação submetida', 'adam-comunidade' ); ?></h3>
							<dl class="adam-approval-details">
								<div><dt><?php esc_html_e( 'E-mail de contacto', 'adam-comunidade' ); ?></dt><dd><a href="mailto:<?php echo esc_attr( $row->contact_email ); ?>"><?php echo esc_html( $row->contact_email ); ?></a></dd></div>
								<?php foreach ( $payload as $key => $value ) : ?>
									<?php
									if ( in_array( $key, array( 'authorization_document_id', 'gallery_ids', 'cover_id', 'verification', 'is_associated', 'slug', 'email' ), true ) ) {
										continue;
									}
									if ( is_array( $value ) ) {
										$display_values = 'playing_styles' === $key
											? array_map( static fn( string $style ): string => (string) ( $playing_style_labels[ $style ] ?? $style ), array_map( 'strval', $value ) )
											: ( 'amenity_ids' === $key
												? array_map( static fn( int $amenity_id ): string => (string) ( $amenity_labels[ $amenity_id ] ?? $amenity_id ), array_map( 'absint', $value ) )
												: array_map( 'strval', $value ) );
										$value = implode( ', ', array_filter( $display_values ) );
									}
									if ( '' === (string) $value ) {
										continue;
									}
									?>
									<div><dt><?php echo esc_html( $labels[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) ) ); ?></dt><dd><?php echo 'rules' === $key ? wp_kses_post( wpautop( (string) $value ) ) : nl2br( esc_html( (string) $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
								<?php endforeach; ?>
								<?php if ( $row->verification_details ) : ?><div><dt><?php esc_html_e( 'Informação para verificação', 'adam-comunidade' ); ?></dt><dd><?php echo nl2br( esc_html( $row->verification_details ) ); ?></dd></div><?php endif; ?>
							</dl>
						</section>

						<aside>
							<h3><?php esc_html_e( 'Documentos e imagens', 'adam-comunidade' ); ?></h3>
							<?php if ( ! empty( $payload['authorization_document_id'] ) ) : ?>
								<?php $document_id = absint( $payload['authorization_document_id'] ); ?>
								<div class="adam-approval-document">
									<span class="dashicons dashicons-media-document"></span>
									<div><strong><?php esc_html_e( 'Comprovativo de autorização', 'adam-comunidade' ); ?></strong><br><a href="<?php echo esc_url( wp_get_attachment_url( $document_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Pré-visualizar', 'adam-comunidade' ); ?></a> · <a href="<?php echo esc_url( wp_get_attachment_url( $document_id ) ); ?>" download><?php esc_html_e( 'Descarregar', 'adam-comunidade' ); ?></a></div>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $payload['gallery_ids'] ) ) : ?>
								<div class="adam-moderation-photos"><?php foreach ( array_map( 'absint', (array) $payload['gallery_ids'] ) as $photo_id ) : ?><a href="<?php echo esc_url( wp_get_attachment_url( $photo_id ) ); ?>" target="_blank" rel="noopener"><?php echo wp_get_attachment_image( $photo_id, 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endforeach; ?></div>
							<?php endif; ?>
							<?php if ( empty( $payload['authorization_document_id'] ) && empty( $payload['gallery_ids'] ) ) : ?><p class="description"><?php esc_html_e( 'Não foram anexados ficheiros.', 'adam-comunidade' ); ?></p><?php endif; ?>
						</aside>
					</div>

					<form class="adam-approval-actions" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<?php wp_nonce_field( 'adam_moderate_' . $row->id, 'adam_nonce' ); ?>
						<input type="hidden" name="action" value="adam_moderate_submission">
						<input type="hidden" name="submission_id" value="<?php echo esc_attr( $row->id ); ?>">
						<label><?php esc_html_e( 'Notas internas', 'adam-comunidade' ); ?><textarea name="admin_note" rows="3" placeholder="<?php esc_attr_e( 'Registe observações apenas visíveis para a administração.', 'adam-comunidade' ); ?>"><?php echo esc_textarea( $row->admin_note ); ?></textarea></label>
						<div>
							<button class="button button-primary" name="decision" value="approve"><?php esc_html_e( 'Aprovar e publicar', 'adam-comunidade' ); ?></button>
							<button class="button" name="decision" value="changes"><?php esc_html_e( 'Pedir alterações', 'adam-comunidade' ); ?></button>
							<button class="button button-link-delete" name="decision" value="reject" onclick="return confirm('<?php echo esc_js( __( 'Tem a certeza de que pretende rejeitar esta submissão?', 'adam-comunidade' ) ); ?>');"><?php esc_html_e( 'Rejeitar', 'adam-comunidade' ); ?></button>
						</div>
					</form>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function moderate(): void {
		Admin_Router::authorize();
		global $wpdb;
		$id = absint( $_POST['submission_id'] ?? 0 );
		check_admin_referer( 'adam_moderate_' . $id, 'adam_nonce' );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::submissions_table() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $row || ! in_array( $row->status, array( 'pending', 'changes_requested' ), true ) ) {
			wp_die( esc_html__( 'A submissão já não está disponível.', 'adam-comunidade' ) );
		}
		$decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) );
		$status   = array( 'approve' => 'published', 'changes' => 'changes_requested', 'reject' => 'rejected' )[ $decision ] ?? '';
		if ( ! $status ) {
			wp_die( esc_html__( 'A decisão selecionada não é válida.', 'adam-comunidade' ) );
		}
		$object_id = (int) $row->object_id;
		if ( 'approve' === $decision ) {
			$result = $this->apply_approval( $row );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}
			$object_id = (int) $result;
		}
		$admin_note = sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ?? '' ) );
		$updated = $wpdb->update( Schema::submissions_table(), array( 'status' => $status, 'object_id' => $object_id, 'admin_note' => $admin_note, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		if ( false === $updated ) {
			wp_die( esc_html__( 'Não foi possível guardar a decisão. Tente novamente.', 'adam-comunidade' ) );
		}
		$this->notify( (int) $row->user_id, __( 'Submissão revista', 'adam-comunidade' ), __( 'A administração da ADAM concluiu a revisão da sua submissão.', 'adam-comunidade' ) );
		if ( 'field' === $row->object_type && 'new' === $row->submission_type && in_array( $decision, array( 'approve', 'reject' ), true ) ) {
			$email_payload = json_decode( (string) $row->payload, true ) ?: array();
			$field         = 'approve' === $decision ? ( new Field_Repository() )->find( $object_id ) : null;
			self::emails()->send(
				'approve' === $decision ? 'field_approved' : 'field_rejected',
				(string) $row->contact_email,
				array(
					'field_name' => (string) ( $email_payload['name'] ?? '' ),
					'field_url'  => $field ? Field_Router::field_url( $field ) : '',
					'admin_note' => '' !== $admin_note ? $admin_note : __( 'A submissão não reuniu, nesta fase, as condições necessárias para publicação.', 'adam-comunidade' ),
				)
			);
		}
		do_action( 'adam_comunidade_submission_moderated', $id, $status, $object_id );
		wp_safe_redirect( Admin_Router::page_url( 'moderation' ) );
		exit;
	}

	private function apply_approval( object $row ): int|\WP_Error {
		global $wpdb;
		if ( 'claim' === $row->submission_type ) {
			if ( ! $row->user_id ) {
				return new \WP_Error( 'login_required', __( 'Os pedidos de gestão exigem uma conta de utilizador.', 'adam-comunidade' ) );
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
				__( 'Esta submissão de campo não pode ser aprovada sem o documento de autorização legal.', 'adam-comunidade' )
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
			return new \WP_Error( 'invalid_type', __( 'O tipo de conteúdo não é suportado.', 'adam-comunidade' ) );
		}
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$result = $record ? $repo->update( (int) $row->object_id, $data ) : $repo->create( $data );
		if ( ! $result ) {
			return new \WP_Error( 'save_failed', __( 'Não foi possível guardar o registo.', 'adam-comunidade' ) );
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
		if ( 'field' === $row->object_type && array_key_exists( 'amenity_ids', $payload ) ) {
			$repo->sync_amenities(
				$result_id,
				array_values( array_filter( array_map( 'absint', (array) $payload['amenity_ids'] ) ) )
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
	private function upload_file( string $field_name, array $extensions, bool $required = false, int $max_size_mb = 10 ): int|\WP_Error {
		if (
			empty( $_FILES[ $field_name ]['name'] )
			|| ! is_string( $_FILES[ $field_name ]['name'] )
		) {
			return $required
				? new \WP_Error( 'authorization_required', __( 'É obrigatório apresentar um comprovativo de autorização legal.', 'adam-comunidade' ) )
				: 0;
		}

		$extension = strtolower( (string) pathinfo( sanitize_file_name( wp_unslash( $_FILES[ $field_name ]['name'] ) ), PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, $extensions, true ) ) {
			return new \WP_Error( 'invalid_upload_type', __( 'O tipo de ficheiro enviado não é permitido.', 'adam-comunidade' ) );
		}
		if ( absint( $_FILES[ $field_name ]['size'] ?? 0 ) > max( 1, $max_size_mb ) * MB_IN_BYTES ) {
			return new \WP_Error( 'upload_too_large', sprintf( __( 'O ficheiro excede o limite de %d MB.', 'adam-comunidade' ), max( 1, $max_size_mb ) ) );
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
	 * @return int[]|\WP_Error
	 */
	private function upload_photos( string $field_name, int $limit, array $extensions = array( 'jpg', 'jpeg', 'png', 'webp' ), int $max_size_mb = 10 ): array|\WP_Error {
		if ( empty( $_FILES[ $field_name ]['name'] ) || ! is_array( $_FILES[ $field_name ]['name'] ) ) {
			return array();
		}

		$original = $_FILES[ $field_name ];
		$ids      = array();
		if ( count( array_filter( $original['name'] ) ) > $limit ) {
			return new \WP_Error( 'too_many_uploads', sprintf( __( 'Pode anexar no máximo %d ficheiros neste campo.', 'adam-comunidade' ), $limit ) );
		}
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
			$attachment_id = $this->upload_file( $field_name, $extensions, false, $max_size_mb );
			if ( is_wp_error( $attachment_id ) ) {
				$_FILES[ $field_name ] = $original;
				foreach ( $ids as $uploaded_id ) {
					wp_delete_attachment( $uploaded_id, true );
				}
				return $attachment_id;
			}
			if ( $attachment_id ) {
				$ids[] = $attachment_id;
			}
		}
		$_FILES[ $field_name ] = $original;

		return $ids;
	}

	private function insert_submission( string $submission_type, string $object_type, int $object_id, array $payload, string $email, string $verification ): int {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$result = $wpdb->insert( Schema::submissions_table(), array( 'submission_type' => $submission_type, 'object_type' => $object_type, 'object_id' => $object_id, 'user_id' => get_current_user_id(), 'contact_email' => $email, 'payload' => wp_json_encode( $payload ), 'verification_details' => $verification, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now ) );
		if ( false === $result ) {
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		do_action( 'adam_comunidade_submission_received', $id, $object_type );
		return $id;
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

	private static function record( string $type, int $id ): ?object {
		return match ( $type ) {
			'team'  => ( new Team_Repository() )->find( $id ),
			'field' => ( new Field_Repository() )->find( $id ),
			'partner', 'institution' => ( new Directory_Repository() )->find( $id, $type ),
			default => null,
		};
	}

	private static function status_notice( string $type = '' ): void {
		$status = sanitize_key( wp_unslash( $_GET['adam_status'] ?? '' ) );
		if ( $status ) {
			$form    = self::forms()->get( $type );
			$message = $form['success_message'] ?? __( 'Obrigado. O pedido foi recebido e está a aguardar revisão.', 'adam-comunidade' );
			echo '<div class="adam-notice adam-notice--success">' . esc_html( $message ) . '</div>';
		}
	}

	/**
	 * Retrieves a short-lived validation state only for its original form.
	 *
	 * @return array<string,mixed>
	 */
	private static function form_state( string $type ): array {
		$token = sanitize_key( wp_unslash( $_GET['adam_form_state'] ?? '' ) );
		if ( '' === $token ) {
			return array();
		}
		$state = get_transient( 'adam_submission_state_' . $token );
		if ( ! is_array( $state ) || $type !== ( $state['type'] ?? '' ) ) {
			return array();
		}
		return $state;
	}

	private static function forms(): Forms_Manager {
		if ( null === self::$forms ) {
			self::$forms = new Forms_Manager();
		}
		return self::$forms;
	}

	private static function emails(): Email_Service {
		if ( null === self::$emails ) {
			self::$emails = new Email_Service();
		}
		return self::$emails;
	}

	private static function object_type_label( string $type ): string {
		return array(
			'field'       => __( 'Campo', 'adam-comunidade' ),
			'team'        => __( 'Equipa', 'adam-comunidade' ),
			'partner'     => __( 'Parceiro', 'adam-comunidade' ),
			'institution' => __( 'Instituição', 'adam-comunidade' ),
		)[ $type ] ?? __( 'Conteúdo', 'adam-comunidade' );
	}

	/**
	 * Returns type-specific copy and navigation for the shared success page.
	 *
	 * @return array{subject:string,back_url:string,back_label:string,another_label:string}
	 */
	private static function success_labels( string $type ): array {
		$labels = array(
			'field' => array(
				'subject'       => __( 'o seu campo', 'adam-comunidade' ),
				'back_url'      => Managed_Pages::url( 'fields' ),
				'back_label'    => __( 'Voltar aos Campos', 'adam-comunidade' ),
				'another_label' => __( 'Submeter outro Campo', 'adam-comunidade' ),
			),
			'team' => array(
				'subject'       => __( 'a sua equipa', 'adam-comunidade' ),
				'back_url'      => Managed_Pages::url( 'teams' ),
				'back_label'    => __( 'Voltar às Equipas', 'adam-comunidade' ),
				'another_label' => __( 'Submeter outra Equipa', 'adam-comunidade' ),
			),
			'partner' => array(
				'subject'       => __( 'o parceiro', 'adam-comunidade' ),
				'back_url'      => Managed_Pages::url( 'partners' ),
				'back_label'    => __( 'Voltar aos Parceiros', 'adam-comunidade' ),
				'another_label' => __( 'Submeter outro Parceiro', 'adam-comunidade' ),
			),
			'institution' => array(
				'subject'       => __( 'a instituição', 'adam-comunidade' ),
				'back_url'      => Managed_Pages::url( 'institutions' ),
				'back_label'    => __( 'Voltar às Instituições', 'adam-comunidade' ),
				'another_label' => __( 'Submeter outra Instituição', 'adam-comunidade' ),
			),
		);
		return $labels[ $type ] ?? array(
			'subject'       => __( 'o conteúdo', 'adam-comunidade' ),
			'back_url'      => home_url( '/' ),
			'back_label'    => __( 'Voltar à Comunidade', 'adam-comunidade' ),
			'another_label' => __( 'Submeter outro pedido', 'adam-comunidade' ),
		);
	}

	private static function submission_type_label( string $type ): string {
		return array(
			'new'   => __( 'Nova submissão', 'adam-comunidade' ),
			'edit'  => __( 'Alteração proposta', 'adam-comunidade' ),
			'claim' => __( 'Pedido de gestão', 'adam-comunidade' ),
		)[ $type ] ?? __( 'Submissão', 'adam-comunidade' );
	}
}
