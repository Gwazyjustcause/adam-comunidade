<?php
/**
 * Front-end Community Manager portal.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Fields\Options as Field_Options;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Managed_Pages;
use ADAM\Comunidade\Teams\Options as Team_Options;
use ADAM\Comunidade\Uploads\Component as Upload_Component;

/**
 * Provides a dedicated, non-WordPress login and management interface.
 */
final class Portal {
	private const ROUTES_VERSION = '1.2.0';
	private static ?self $instance = null;

	public function __construct( private Auth $auth, private Service $service ) {
		self::$instance = $this;
	}

	public function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_action( 'init', array( self::class, 'maybe_flush_rewrite_rules' ), 999 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template' ) );
		add_filter( 'document_title_parts', array( $this, 'title' ) );
		add_action( 'template_redirect', array( $this, 'access_control' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		foreach ( array( 'login', 'activate', 'logout', 'revision', 'request_reset', 'reset' ) as $action ) {
			add_action( 'admin_post_nopriv_adam_manager_' . $action, array( $this, 'handle_' . $action ) );
			add_action( 'admin_post_adam_manager_' . $action, array( $this, 'handle_' . $action ) );
		}
	}

	public static function add_rewrite_rules(): void {
		$path = Managed_Pages::path( 'manager' ) ?: 'gestor';
		add_rewrite_rule( '^' . preg_quote( $path, '#' ) . '/editar/(team|field|partner|institution)/([0-9]+)/?$', 'index.php?adam_manager_route=edit&adam_manager_type=$matches[1]&adam_manager_id=$matches[2]', 'top' );
		// Keeps invitation links generated before managed pages existed valid.
		add_rewrite_rule( '^gestor/ativar/([a-f0-9]{64})/?$', 'index.php?adam_manager_route=activate&adam_manager_token=$matches[1]', 'top' );
	}

	public static function maybe_flush_rewrite_rules(): void {
		if ( self::ROUTES_VERSION === get_option( 'adam_comunidade_manager_routes_version' ) ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'adam_comunidade_manager_routes_version', self::ROUTES_VERSION, false );
	}

	public function query_vars( array $vars ): array {
		return array_merge( $vars, array( 'adam_manager_route', 'adam_manager_token', 'adam_manager_type', 'adam_manager_id' ) );
	}

	public function template( string $template ): string {
		return $this->route() ? Helpers::path( 'templates/managers/portal.php' ) : $template;
	}

	public function title( array $parts ): array {
		$route = $this->route();
		if ( $route ) {
			$titles = array(
				'login'    => __( 'Login do Gestor', 'adam-comunidade' ),
				'activate' => __( 'Definir Palavra-passe', 'adam-comunidade' ),
				'recovery' => __( 'Recuperar Palavra-passe', 'adam-comunidade' ),
			);
			$parts['title'] = $titles[ $route ] ?? __( 'Área do Gestor', 'adam-comunidade' );
		}
		return $parts;
	}

	public function assets(): void {
		if ( ! $this->route() ) {
			return;
		}
		wp_enqueue_style( 'adam-comunidade' );
		wp_enqueue_script( 'adam-comunidade' );
		Upload_Component::enqueue_assets();
	}

	public static function url(): string {
		return Managed_Pages::url( 'manager' );
	}

	public static function login_url(): string {
		return Managed_Pages::url( 'manager_login' );
	}

	public static function activation_url( string $token ): string {
		return add_query_arg( 'convite', rawurlencode( $token ), Managed_Pages::url( 'manager_activation' ) );
	}

	public static function recovery_url( string $token = '' ): string {
		$url = Managed_Pages::url( 'manager_recovery' );
		return $token ? add_query_arg( 'codigo', rawurlencode( $token ), $url ) : $url;
	}

	public static function edit_url( string $type, int $id ): string {
		return trailingslashit( self::url() ) . 'editar/' . sanitize_key( $type ) . '/' . absint( $id ) . '/';
	}

	public static function render(): void {
		if ( self::$instance ) {
			self::$instance->render_page();
		}
	}

	/**
	 * Keeps authentication pages and the private dashboard strictly separated.
	 */
	public function access_control(): void {
		$route = $this->route();
		if ( ! $route ) {
			return;
		}
		$manager = $this->auth->current();
		if ( 'login' === $route && $manager ) {
			wp_safe_redirect( self::url() );
			exit;
		}
		if ( in_array( $route, array( 'dashboard', 'edit' ), true ) && ! $manager ) {
			wp_safe_redirect( add_query_arg( 'estado', 'session-required', self::login_url() ) );
			exit;
		}
		nocache_headers();
	}

	private function render_page(): void {
		$route = $this->route();
		?>
		<main class="adam-community adam-manager-portal">
			<section class="adam-manager-shell">
				<header class="adam-manager-header">
					<p class="adam-manager-eyebrow"><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></p>
					<h1><?php esc_html_e( 'Gestor da Comunidade', 'adam-comunidade' ); ?></h1>
					<p><?php esc_html_e( 'Atualize os registos que lhe foram atribuídos. Todas as alterações são revistas pela ADAM antes da publicação.', 'adam-comunidade' ); ?></p>
					<nav class="adam-manager-nav" aria-label="<?php esc_attr_e( 'Navegação do Gestor', 'adam-comunidade' ); ?>">
						<a href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Área do Gestor', 'adam-comunidade' ); ?></a>
						<?php if ( ! $this->auth->current() ) : ?><a href="<?php echo esc_url( self::login_url() ); ?>"><?php esc_html_e( 'Iniciar sessão', 'adam-comunidade' ); ?></a><a href="<?php echo esc_url( self::recovery_url() ); ?>"><?php esc_html_e( 'Recuperar palavra-passe', 'adam-comunidade' ); ?></a><?php endif; ?>
					</nav>
				</header>
				<?php $this->notice(); ?>
				<?php
				if ( 'login' === $route ) {
					$this->login_form();
				} elseif ( 'activate' === $route ) {
					$this->activation_form();
				} elseif ( 'recovery' === $route ) {
					$this->recovery_form();
				} elseif ( 'edit' === $route ) {
					$this->edit_form();
				} else {
					$this->dashboard();
				}
				?>
			</section>
		</main>
		<?php
	}

	private function dashboard(): void {
		$manager = $this->auth->current();
		if ( ! $manager ) {
			echo '<div class="adam-community-empty"><h2>' . esc_html__( 'A sessão terminou.', 'adam-comunidade' ) . '</h2><a class="adam-community-button" href="' . esc_url( self::login_url() ) . '">' . esc_html__( 'Iniciar sessão', 'adam-comunidade' ) . '</a></div>';
			return;
		}
		$assignments = $this->service->assignments( (int) $manager->id );
		$record_names = $this->service->record_names( $assignments );
		$active_revisions = $this->service->active_revisions_for_manager( (int) $manager->id );
		?>
		<div class="adam-manager-toolbar">
			<p><?php echo esc_html( sprintf( __( 'Sessão iniciada como %s', 'adam-comunidade' ), (string) $manager->email ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="adam_manager_logout">
				<input type="hidden" name="adam_manager_csrf" value="<?php echo esc_attr( $this->auth->csrf_token() ); ?>">
				<button class="adam-community-button adam-community-button--secondary" type="submit"><?php esc_html_e( 'Terminar sessão', 'adam-comunidade' ); ?></button>
			</form>
		</div>
		<h2><?php esc_html_e( 'Os meus registos', 'adam-comunidade' ); ?></h2>
		<?php if ( ! $record_names ) : ?>
			<div class="adam-community-empty"><h3><?php esc_html_e( 'Ainda não existem registos atribuídos.', 'adam-comunidade' ); ?></h3></div>
		<?php else : ?>
			<div class="adam-manager-list">
				<?php foreach ( $assignments as $assignment ) :
					$revision_key = (string) $assignment->entity_type . ':' . (int) $assignment->entity_id;
					$record_name = $record_names[ $revision_key ] ?? '';
					$active_revision = $active_revisions[ $revision_key ] ?? null;
					?>
					<?php if ( ! $record_name ) { continue; } ?>
					<article class="adam-card adam-manager-card">
						<?php $entity_labels = array( 'field' => __( 'Campo', 'adam-comunidade' ), 'team' => __( 'Equipa', 'adam-comunidade' ), 'partner' => __( 'Parceiro', 'adam-comunidade' ), 'institution' => __( 'Instituição', 'adam-comunidade' ) ); ?>
						<span class="adam-card__eyebrow"><?php echo esc_html( $entity_labels[ $assignment->entity_type ] ?? __( 'Organização', 'adam-comunidade' ) ); ?></span>
						<h3><?php echo esc_html( $record_name ); ?></h3>
						<?php if ( $active_revision ) :
							$is_own_revision = (int) $active_revision->manager_id === (int) $manager->id;
							$status_labels = array(
								'pending'    => __( 'A aguardar revisão', 'adam-comunidade' ),
								'needs_info' => __( 'Informação adicional necessária', 'adam-comunidade' ),
								'processing' => __( 'Em análise neste momento', 'adam-comunidade' ),
							);
							?>
							<p><span class="adam-manager-revision-status adam-manager-revision-status--<?php echo esc_attr( (string) $active_revision->status ); ?>"><?php echo esc_html( $status_labels[ $active_revision->status ] ?? __( 'Em revisão', 'adam-comunidade' ) ); ?></span></p>
							<?php if ( 'needs_info' === $active_revision->status && $active_revision->admin_note ) : ?><p><strong><?php esc_html_e( 'Nota da ADAM:', 'adam-comunidade' ); ?></strong> <?php echo esc_html( (string) $active_revision->admin_note ); ?></p><?php endif; ?>
							<p><?php echo esc_html( $is_own_revision ? __( 'Pode atualizar a proposta enquanto esta não estiver em análise.', 'adam-comunidade' ) : __( 'Outro Gestor já enviou alterações para esta organização.', 'adam-comunidade' ) ); ?></p>
							<?php if ( $is_own_revision && 'processing' !== $active_revision->status ) : ?><a class="adam-community-button" href="<?php echo esc_url( self::edit_url( (string) $assignment->entity_type, (int) $assignment->entity_id ) ); ?>"><?php esc_html_e( 'Editar proposta pendente', 'adam-comunidade' ); ?></a><?php endif; ?>
						<?php else : ?>
							<p><?php esc_html_e( 'As alterações enviadas ficam pendentes até serem revistas pela administração.', 'adam-comunidade' ); ?></p>
							<a class="adam-community-button" href="<?php echo esc_url( self::edit_url( (string) $assignment->entity_type, (int) $assignment->entity_id ) ); ?>"><?php esc_html_e( 'Editar registo', 'adam-comunidade' ); ?></a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif;
	}

	private function login_form(): void {
		?>
		<form class="adam-card adam-manager-form adam-manager-login" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="adam_manager_login">
			<?php wp_nonce_field( 'adam_manager_login' ); ?>
			<h2><?php esc_html_e( 'Iniciar sessão', 'adam-comunidade' ); ?></h2>
			<label><span><?php esc_html_e( 'E-mail', 'adam-comunidade' ); ?></span><input type="email" name="email" autocomplete="email" required></label>
			<label><span><?php esc_html_e( 'Palavra-passe', 'adam-comunidade' ); ?></span><input type="password" name="password" autocomplete="current-password" required></label>
			<button class="adam-community-button" type="submit"><?php esc_html_e( 'Entrar', 'adam-comunidade' ); ?></button>
			<p><a href="<?php echo esc_url( self::recovery_url() ); ?>"><?php esc_html_e( 'Recuperar palavra-passe', 'adam-comunidade' ); ?></a></p>
			<p class="adam-manager-help"><?php esc_html_e( 'A conta de Gestor é independente da sua conta WordPress ou de Sócio ADAM.', 'adam-comunidade' ); ?></p>
		</form>
		<?php
	}

	private function activation_form(): void {
		$token = sanitize_text_field( (string) ( get_query_var( 'adam_manager_token' ) ?: wp_unslash( $_GET['convite'] ?? '' ) ) );
		?>
		<form class="adam-card adam-manager-form adam-manager-login" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="adam_manager_activate">
			<?php wp_nonce_field( 'adam_manager_activate' ); ?>
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<h2><?php esc_html_e( 'Criar conta de Gestor', 'adam-comunidade' ); ?></h2>
			<p><?php esc_html_e( 'Defina a palavra-passe desta conta. O endereço de e-mail já está associado ao convite.', 'adam-comunidade' ); ?></p>
			<label><span><?php esc_html_e( 'Palavra-passe', 'adam-comunidade' ); ?></span><input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
			<label><span><?php esc_html_e( 'Confirmar palavra-passe', 'adam-comunidade' ); ?></span><input type="password" name="password_confirm" minlength="10" autocomplete="new-password" required></label>
			<button class="adam-community-button" type="submit"><?php esc_html_e( 'Criar conta de Gestor', 'adam-comunidade' ); ?></button>
		</form>
		<?php
	}

	private function recovery_form(): void {
		$token = sanitize_text_field( (string) wp_unslash( $_GET['codigo'] ?? '' ) );
		if ( $token ) {
			?>
			<form class="adam-card adam-manager-form adam-manager-login" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="adam_manager_reset">
				<?php wp_nonce_field( 'adam_manager_reset' ); ?>
				<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
				<h2><?php esc_html_e( 'Definir nova palavra-passe', 'adam-comunidade' ); ?></h2>
				<label><span><?php esc_html_e( 'Nova palavra-passe', 'adam-comunidade' ); ?></span><input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
				<label><span><?php esc_html_e( 'Confirmar palavra-passe', 'adam-comunidade' ); ?></span><input type="password" name="password_confirm" minlength="10" autocomplete="new-password" required></label>
				<button class="adam-community-button" type="submit"><?php esc_html_e( 'Guardar nova palavra-passe', 'adam-comunidade' ); ?></button>
			</form>
			<?php
			return;
		}
		?>
		<form class="adam-card adam-manager-form adam-manager-login" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="adam_manager_request_reset">
			<?php wp_nonce_field( 'adam_manager_request_reset' ); ?>
			<h2><?php esc_html_e( 'Recuperar palavra-passe', 'adam-comunidade' ); ?></h2>
			<p><?php esc_html_e( 'Introduza o e-mail da sua conta de Gestor. Se existir uma conta ativa, receberá um endereço de recuperação.', 'adam-comunidade' ); ?></p>
			<label><span><?php esc_html_e( 'E-mail', 'adam-comunidade' ); ?></span><input type="email" name="email" autocomplete="email" required></label>
			<button class="adam-community-button" type="submit"><?php esc_html_e( 'Enviar recuperação', 'adam-comunidade' ); ?></button>
		</form>
		<?php
	}

	private function edit_form(): void {
		$manager = $this->auth->current();
		if ( ! $manager ) {
			$this->login_form();
			return;
		}
		$type   = sanitize_key( (string) get_query_var( 'adam_manager_type' ) );
		$id     = absint( get_query_var( 'adam_manager_id' ) );
		$record = $this->service->can_manage( (int) $manager->id, $type, $id ) ? $this->service->record( $type, $id ) : null;
		if ( ! $record ) {
			status_header( 403 );
			echo '<div class="adam-community-empty"><h2>' . esc_html__( 'Não tem acesso a este registo.', 'adam-comunidade' ) . '</h2></div>';
			return;
		}
		$active_revision = $this->service->active_revision( $type, $id );
		if ( $active_revision && (int) $active_revision->manager_id !== (int) $manager->id ) {
			echo '<div class="adam-community-empty"><h2>' . esc_html__( 'Já existe uma proposta em revisão.', 'adam-comunidade' ) . '</h2><p>' . esc_html__( 'Outro Gestor enviou alterações para esta organização. Poderá editar novamente depois de a ADAM concluir essa revisão.', 'adam-comunidade' ) . '</p><a class="adam-community-button" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Voltar à Área do Gestor', 'adam-comunidade' ) . '</a></div>';
			return;
		}
		if ( $active_revision && 'processing' === (string) $active_revision->status ) {
			echo '<div class="adam-community-empty"><h2>' . esc_html__( 'A proposta está a ser analisada.', 'adam-comunidade' ) . '</h2><p>' . esc_html__( 'A edição fica temporariamente indisponível enquanto a ADAM conclui a decisão.', 'adam-comunidade' ) . '</p><a class="adam-community-button" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Voltar à Área do Gestor', 'adam-comunidade' ) . '</a></div>';
			return;
		}
		$pending_payload = $active_revision ? $this->service->revision_payload( $active_revision ) : array();
		if ( $pending_payload ) {
			$record = (object) array_merge( (array) $record, $pending_payload );
		}
		$styles = 'field' === $type ? Field_Options::playing_styles() : ( 'team' === $type ? Team_Options::playing_styles() : array() );
		$selected_styles = 'field' === $type ? Field_Options::decode_list( $record->playing_styles ?? '' ) : ( 'team' === $type ? Team_Options::decode_list( $record->playing_styles ?? '' ) : array() );
		$current_gallery_ids = isset( $pending_payload['gallery_ids'] )
			? array_map( 'absint', (array) $pending_payload['gallery_ids'] )
			: ( isset( $pending_payload['gallery'] ) ? array_map( 'absint', (array) $pending_payload['gallery'] ) : $this->gallery_ids( $type, $record ) );
		?>
		<form class="adam-card adam-manager-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="adam_manager_revision">
			<input type="hidden" name="adam_manager_csrf" value="<?php echo esc_attr( $this->auth->csrf_token() ); ?>">
			<input type="hidden" name="entity_type" value="<?php echo esc_attr( $type ); ?>">
			<input type="hidden" name="entity_id" value="<?php echo esc_attr( (string) $id ); ?>">
			<div class="adam-manager-form__heading"><div><p class="adam-manager-eyebrow"><?php esc_html_e( 'Proposta de alteração', 'adam-comunidade' ); ?></p><h2><?php echo esc_html( (string) $record->name ); ?></h2></div><a href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Voltar aos meus registos', 'adam-comunidade' ); ?></a></div>
			<?php if ( $active_revision ) : ?>
				<div class="adam-manager-pending-banner" role="status">
					<strong><?php esc_html_e( 'Está a editar a proposta pendente.', 'adam-comunidade' ); ?></strong>
					<p><?php esc_html_e( 'Ao voltar a enviar, esta versão substitui a proposta anterior no trabalho pendente. A versão anterior permanece no histórico administrativo.', 'adam-comunidade' ); ?></p>
					<?php if ( 'needs_info' === $active_revision->status && $active_revision->admin_note ) : ?><p><strong><?php esc_html_e( 'Informação pedida pela ADAM:', 'adam-comunidade' ); ?></strong> <?php echo esc_html( (string) $active_revision->admin_note ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="adam-form-grid">
				<label><span><?php esc_html_e( 'Nome', 'adam-comunidade' ); ?></span><input type="text" name="manager[name]" value="<?php echo esc_attr( (string) $record->name ); ?>" required></label>
				<?php if ( in_array( $type, array( 'team', 'field' ), true ) ) : ?><label><span><?php esc_html_e( 'Concelho', 'adam-comunidade' ); ?></span><input type="text" name="manager[municipality]" value="<?php echo esc_attr( (string) ( $record->municipality ?? '' ) ); ?>"></label><?php endif; ?>
				<label><span><?php esc_html_e( 'Distrito', 'adam-comunidade' ); ?></span><input type="text" name="manager[district]" value="<?php echo esc_attr( (string) ( $record->district ?? '' ) ); ?>"></label>
				<label class="adam-field--wide"><span><?php esc_html_e( 'Morada', 'adam-comunidade' ); ?></span><input type="text" name="manager[address]" value="<?php echo esc_attr( (string) ( $record->address ?? '' ) ); ?>"></label>
				<label class="adam-field--wide"><span><?php esc_html_e( 'Descrição breve', 'adam-comunidade' ); ?></span><textarea name="manager[short_description]" rows="3"><?php echo esc_textarea( (string) ( $record->short_description ?? '' ) ); ?></textarea></label>
				<label class="adam-field--wide"><span><?php esc_html_e( 'Descrição completa', 'adam-comunidade' ); ?></span><textarea name="manager[full_description]" rows="10"><?php echo esc_textarea( wp_strip_all_tags( (string) ( $record->full_description ?? '' ) ) ); ?></textarea></label>
				<label><span><?php esc_html_e( 'Website', 'adam-comunidade' ); ?></span><input type="url" name="manager[website]" value="<?php echo esc_attr( (string) ( $record->website ?? '' ) ); ?>"></label>
				<label><span><?php esc_html_e( 'Facebook', 'adam-comunidade' ); ?></span><input type="url" name="manager[facebook]" value="<?php echo esc_attr( (string) ( $record->facebook ?? '' ) ); ?>"></label>
				<label><span><?php esc_html_e( 'Instagram', 'adam-comunidade' ); ?></span><input type="url" name="manager[instagram]" value="<?php echo esc_attr( (string) ( $record->instagram ?? '' ) ); ?>"></label>
				<label><span><?php esc_html_e( 'E-mail de contacto interno', 'adam-comunidade' ); ?></span><input type="email" name="manager[email]" value="<?php echo esc_attr( (string) ( $record->email ?? '' ) ); ?>"></label>
				<label><span><?php esc_html_e( 'Telefone de contacto interno', 'adam-comunidade' ); ?></span><input type="tel" name="manager[phone]" value="<?php echo esc_attr( (string) ( $record->phone ?? '' ) ); ?>"></label>
			</div>
			<?php if ( $styles ) : ?><fieldset class="adam-manager-choices"><legend><?php esc_html_e( 'Estilos de jogo', 'adam-comunidade' ); ?></legend><?php foreach ( $styles as $key => $label ) : ?><label><input type="checkbox" name="manager[playing_styles][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected_styles, true ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></fieldset><?php endif; ?>
			<?php if ( 'field' === $type ) { $this->field_sections( $record, $pending_payload ); } elseif ( 'team' === $type ) { $this->team_sections( $record ); } else { $this->directory_sections( $record ); } ?>
			<div class="adam-manager-media">
				<h3><?php esc_html_e( 'Imagens', 'adam-comunidade' ); ?></h3>
				<?php if ( $current_gallery_ids ) : ?>
					<fieldset class="adam-manager-current-media">
						<legend><?php echo esc_html( $active_revision ? __( 'Fotografias da proposta pendente', 'adam-comunidade' ) : __( 'Fotografias atuais', 'adam-comunidade' ) ); ?></legend>
						<p><?php esc_html_e( 'Desmarque para propor a remoção. Arraste as fotografias para alterar a ordem.', 'adam-comunidade' ); ?></p>
						<div data-adam-current-gallery><?php foreach ( $current_gallery_ids as $attachment_id ) : ?><label draggable="true" tabindex="0" data-attachment-id="<?php echo esc_attr( (string) $attachment_id ); ?>"><input type="checkbox" name="keep_gallery_ids[]" value="<?php echo esc_attr( (string) $attachment_id ); ?>" checked><?php echo wp_get_attachment_image( $attachment_id, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'Manter fotografia. Use Alt e as setas para alterar a ordem.', 'adam-comunidade' ); ?></span></label><?php endforeach; ?></div>
					</fieldset>
				<?php endif; ?>
				<input type="hidden" name="gallery_reviewed" value="1">
				<?php if ( ! empty( $record->cover_id ) && wp_attachment_is_image( (int) $record->cover_id ) ) : ?><div class="adam-manager-current-image"><strong><?php esc_html_e( 'Capa incluída na proposta', 'adam-comunidade' ); ?></strong><?php echo wp_get_attachment_image( (int) $record->cover_id, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><label><input type="checkbox" name="remove_cover" value="1"> <?php esc_html_e( 'Propor remoção da capa', 'adam-comunidade' ); ?></label></div><?php endif; ?>
				<label><?php esc_html_e( 'Nova imagem de capa (opcional)', 'adam-comunidade' ); ?></label>
				<?php Upload_Component::render( array( 'mode' => 'file', 'kind' => 'image', 'name' => 'manager_cover', 'accept' => 'image/jpeg,image/png,image/webp', 'max_size_mb' => 10 ) ); ?>
				<?php if ( 'field' !== $type && ! empty( $record->logo_id ) && wp_attachment_is_image( (int) $record->logo_id ) ) : ?><div class="adam-manager-current-image"><strong><?php esc_html_e( 'Logótipo incluído na proposta', 'adam-comunidade' ); ?></strong><?php echo wp_get_attachment_image( (int) $record->logo_id, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><label><input type="checkbox" name="remove_logo" value="1"> <?php esc_html_e( 'Propor remoção do logótipo', 'adam-comunidade' ); ?></label></div><?php endif; ?>
				<?php if ( 'field' !== $type ) : ?><label><?php esc_html_e( 'Novo logótipo (opcional)', 'adam-comunidade' ); ?></label><?php Upload_Component::render( array( 'mode' => 'file', 'kind' => 'image', 'name' => 'manager_logo', 'accept' => 'image/jpeg,image/png,image/webp', 'max_size_mb' => 10 ) ); ?><?php endif; ?>
				<label><?php esc_html_e( 'Novas fotografias para a galeria (opcional)', 'adam-comunidade' ); ?></label>
				<?php Upload_Component::render( array( 'mode' => 'file', 'kind' => 'image', 'name' => 'manager_gallery[]', 'accept' => 'image/jpeg,image/png,image/webp', 'multiple' => true, 'max' => 20, 'max_size_mb' => 10 ) ); ?>
			</div>
			<p class="adam-manager-review-note"><?php esc_html_e( 'Ao enviar, o registo público não é alterado de imediato. A ADAM irá rever esta proposta.', 'adam-comunidade' ); ?></p>
			<button class="adam-community-button" type="submit"><?php esc_html_e( 'Enviar alterações para revisão', 'adam-comunidade' ); ?></button>
		</form>
		<?php
	}

	private function field_sections( object $record, array $pending_payload = array() ): void {
		$repo = new Field_Repository();
		$selected = isset( $pending_payload['amenity_ids'] )
			? array_map( 'absint', (array) $pending_payload['amenity_ids'] )
			: $repo->amenity_ids( (int) $record->id );
		?>
		<div class="adam-form-grid">
			<label><span><?php esc_html_e( 'Jogadores recomendados', 'adam-comunidade' ); ?></span><input type="number" min="0" name="manager[recommended_players]" value="<?php echo esc_attr( (string) $record->recommended_players ); ?>"></label>
			<label><span><?php esc_html_e( 'Máximo de jogadores', 'adam-comunidade' ); ?></span><input type="number" min="0" name="manager[max_players]" value="<?php echo esc_attr( (string) $record->max_players ); ?>"></label>
			<label class="adam-field--wide"><span><?php esc_html_e( 'Regras', 'adam-comunidade' ); ?></span><textarea name="manager[rules]" rows="8"><?php echo esc_textarea( wp_strip_all_tags( (string) $record->rules ) ); ?></textarea></label>
			<label class="adam-field--wide"><span><?php esc_html_e( 'Horários', 'adam-comunidade' ); ?></span><textarea name="manager[opening_hours]" rows="6" placeholder="<?php esc_attr_e( 'Ex.: Sábado e domingo, 09:00–18:00', 'adam-comunidade' ); ?>"><?php echo esc_textarea( (string) ( $record->opening_hours ?? '' ) ); ?></textarea></label>
		</div>
		<fieldset class="adam-manager-choices"><legend><?php esc_html_e( 'Comodidades', 'adam-comunidade' ); ?></legend><?php
		global $wpdb;
		$amenities = $wpdb->get_results( 'SELECT id,label FROM ' . \ADAM\Comunidade\Fields\Schema::amenities_table() . " WHERE status = 'active' ORDER BY sort_order,label" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $amenities as $amenity ) : ?><label><input type="checkbox" name="amenity_ids[]" value="<?php echo esc_attr( (string) $amenity->id ); ?>" <?php checked( in_array( (int) $amenity->id, $selected, true ) ); ?>> <?php echo esc_html( (string) $amenity->label ); ?></label><?php endforeach; ?></fieldset>
		<?php
	}

	private function team_sections( object $record ): void {
		?>
		<div class="adam-form-grid">
			<label><span><?php esc_html_e( 'Nome abreviado', 'adam-comunidade' ); ?></span><input type="text" name="manager[short_name]" value="<?php echo esc_attr( (string) $record->short_name ); ?>"></label>
			<label><span><?php esc_html_e( 'Ano de fundação', 'adam-comunidade' ); ?></span><input type="number" min="1800" max="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" name="manager[founded]" value="<?php echo esc_attr( (string) $record->founded ); ?>"></label>
			<label><span><?php esc_html_e( 'Número de elementos', 'adam-comunidade' ); ?></span><input type="number" min="0" name="manager[members]" value="<?php echo esc_attr( (string) $record->members ); ?>"></label>
			<label><span><?php esc_html_e( 'Estado do recrutamento', 'adam-comunidade' ); ?></span><select name="manager[recruitment_status]"><?php foreach ( Team_Options::recruitment_statuses() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $record->recruitment_status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label class="adam-field--wide"><span><?php esc_html_e( 'Requisitos de experiência', 'adam-comunidade' ); ?></span><textarea name="manager[recruitment_experience]"><?php echo esc_textarea( (string) $record->recruitment_experience ); ?></textarea></label>
			<label class="adam-field--wide"><span><?php esc_html_e( 'Equipamento obrigatório', 'adam-comunidade' ); ?></span><textarea name="manager[recruitment_equipment]"><?php echo esc_textarea( (string) $record->recruitment_equipment ); ?></textarea></label>
		</div>
		<?php
	}

	private function directory_sections( object $record ): void {
		?>
		<div class="adam-form-grid">
			<label><span><?php esc_html_e( 'Categoria', 'adam-comunidade' ); ?></span><input type="text" name="manager[category]" value="<?php echo esc_attr( (string) ( $record->category ?? '' ) ); ?>"></label>
			<label><span><?php esc_html_e( 'País', 'adam-comunidade' ); ?></span><input type="text" name="manager[country]" value="<?php echo esc_attr( (string) ( $record->country ?? '' ) ); ?>"></label>
			<label class="adam-field--wide"><span><?php esc_html_e( 'Benefícios', 'adam-comunidade' ); ?></span><textarea name="manager[benefits]" rows="6"><?php echo esc_textarea( wp_strip_all_tags( (string) ( $record->benefits ?? '' ) ) ); ?></textarea></label>
			<label class="adam-field--wide"><span><?php esc_html_e( 'Benefícios para Sócios ADAM', 'adam-comunidade' ); ?></span><textarea name="manager[member_benefits]" rows="6"><?php echo esc_textarea( wp_strip_all_tags( (string) ( $record->member_benefits ?? '' ) ) ); ?></textarea></label>
		</div>
		<?php
	}

	public function handle_login(): never {
		check_admin_referer( 'adam_manager_login' );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$key   = 'adam_manager_login_' . md5( strtolower( $email ) . '|' . (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$attempts = absint( get_transient( $key ) );
		if ( $attempts >= 8 || ! $this->auth->login( $email, (string) wp_unslash( $_POST['password'] ?? '' ) ) ) {
			set_transient( $key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
			$this->redirect_login_status( 'login-failed' );
		}
		delete_transient( $key );
		$this->redirect_status( 'logged-in' );
	}

	public function handle_activate(): never {
		check_admin_referer( 'adam_manager_activate' );
		$password = (string) wp_unslash( $_POST['password'] ?? '' );
		$token    = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		if ( ! hash_equals( $password, (string) wp_unslash( $_POST['password_confirm'] ?? '' ) ) ) {
			wp_safe_redirect( add_query_arg( 'estado', 'password-mismatch', self::activation_url( $token ) ) );
			exit;
		}
		$result = $this->service->activate( $token, $password );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'estado', 'activation-failed', self::activation_url( $token ) ) );
			exit;
		}
		$this->redirect_login_status( 'activated' );
	}

	public function handle_request_reset(): never {
		check_admin_referer( 'adam_manager_request_reset' );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$key = 'adam_manager_reset_' . md5( strtolower( $email ) . '|' . (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( absint( get_transient( $key ) ) < 3 ) {
			$this->service->request_password_reset( $email );
			set_transient( $key, absint( get_transient( $key ) ) + 1, HOUR_IN_SECONDS );
		}
		wp_safe_redirect( add_query_arg( 'estado', 'reset-requested', self::recovery_url() ) );
		exit;
	}

	public function handle_reset(): never {
		check_admin_referer( 'adam_manager_reset' );
		$password = (string) wp_unslash( $_POST['password'] ?? '' );
		$token    = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		if ( ! hash_equals( $password, (string) wp_unslash( $_POST['password_confirm'] ?? '' ) ) ) {
			wp_safe_redirect( add_query_arg( 'estado', 'password-mismatch', self::recovery_url( $token ) ) );
			exit;
		}
		$result = $this->service->reset_password( $token, $password );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'estado', 'reset-failed', self::recovery_url( $token ) ) );
			exit;
		}
		$this->redirect_login_status( 'password-reset' );
	}

	public function handle_logout(): never {
		if ( ! $this->auth->verify_csrf( $_POST['adam_manager_csrf'] ?? '' ) ) {
			wp_die( esc_html__( 'O pedido de segurança expirou.', 'adam-comunidade' ), 403 );
		}
		$this->auth->logout();
		$this->redirect_login_status( 'logged-out' );
	}

	public function handle_revision(): never {
		$manager = $this->auth->current();
		if ( ! $manager || ! $this->auth->verify_csrf( $_POST['adam_manager_csrf'] ?? '' ) ) {
			wp_die( esc_html__( 'A sessão expirou. Inicie sessão novamente.', 'adam-comunidade' ), 403 );
		}
		$type  = sanitize_key( wp_unslash( $_POST['entity_type'] ?? '' ) );
		$id    = absint( $_POST['entity_id'] ?? 0 );
		if ( ! $this->service->can_manage( (int) $manager->id, $type, $id ) ) {
			wp_die( esc_html__( 'Não tem acesso a este registo.', 'adam-comunidade' ), 403 );
		}
		$active_revision = $this->service->active_revision( $type, $id );
		$pending_payload = $active_revision && (int) $active_revision->manager_id === (int) $manager->id
			? $this->service->revision_payload( $active_revision )
			: array();
		$input = isset( $_POST['manager'] ) && is_array( $_POST['manager'] ) ? wp_unslash( $_POST['manager'] ) : array();
		// Media IDs are accepted only from uploads processed in this request.
		unset( $input['cover_id'], $input['logo_id'], $input['gallery'] );
		if ( isset( $pending_payload['cover_id'] ) ) {
			$input['cover_id'] = absint( $pending_payload['cover_id'] );
		}
		if ( isset( $pending_payload['logo_id'] ) ) {
			$input['logo_id'] = absint( $pending_payload['logo_id'] );
		}
		if ( ! empty( $_POST['remove_cover'] ) ) {
			$input['cover_id'] = 0;
		}
		if ( 'field' !== $type && ! empty( $_POST['remove_logo'] ) ) {
			$input['logo_id'] = 0;
		}
		$uploaded = array();
		$cover = $this->upload_one( 'manager_cover' );
		if ( is_wp_error( $cover ) ) { wp_die( esc_html( $cover->get_error_message() ) ); }
		if ( $cover ) { $input['cover_id'] = $cover; $uploaded[] = $cover; }
		if ( 'field' !== $type ) {
			$logo = $this->upload_one( 'manager_logo' );
			if ( is_wp_error( $logo ) ) { wp_die( esc_html( $logo->get_error_message() ) ); }
			if ( $logo ) { $input['logo_id'] = $logo; $uploaded[] = $logo; }
		}
		$gallery = $this->upload_many( 'manager_gallery', 20 );
		if ( is_wp_error( $gallery ) ) { wp_die( esc_html( $gallery->get_error_message() ) ); }
		$uploaded = array_merge( $uploaded, $gallery );
		$current_record = $this->service->record( $type, $id );
		$current_gallery_ids = isset( $pending_payload['gallery_ids'] )
			? array_map( 'absint', (array) $pending_payload['gallery_ids'] )
			: ( isset( $pending_payload['gallery'] ) ? array_map( 'absint', (array) $pending_payload['gallery'] ) : ( $current_record ? $this->gallery_ids( $type, $current_record ) : array() ) );
		$kept_gallery_ids = array_values(
			array_intersect(
				$current_gallery_ids,
				array_filter( array_map( 'absint', (array) wp_unslash( $_POST['keep_gallery_ids'] ?? array() ) ) )
			)
		);
		$next_gallery_ids = array_slice( array_values( array_unique( array_merge( $kept_gallery_ids, $gallery ) ) ), 0, 20 );
		$relations = array();
		if ( isset( $_POST['gallery_reviewed'] ) ) {
			if ( 'team' === $type ) { $input['gallery'] = $next_gallery_ids; } else { $relations['gallery_ids'] = $next_gallery_ids; }
		}
		if ( 'field' === $type ) {
			$relations['amenity_ids'] = isset( $_POST['amenity_ids'] ) ? wp_unslash( $_POST['amenity_ids'] ) : array();
		}
		$result = $this->service->submit_revision( (int) $manager->id, $type, $id, $input, $relations );
		if ( is_wp_error( $result ) ) {
			foreach ( $uploaded as $attachment_id ) { wp_delete_attachment( $attachment_id, true ); }
			wp_die( esc_html( $result->get_error_message() ) );
		}
		$this->redirect_status( 'revision-sent' );
	}

	private function upload_one( string $name ): int|\WP_Error {
		$file = $_FILES[ $name ] ?? null;
		if ( ! is_array( $file ) || empty( $file['name'] ) ) {
			return 0;
		}
		if ( absint( $file['size'] ?? 0 ) > 10 * MB_IN_BYTES ) {
			return new \WP_Error( 'large_upload', __( 'A imagem excede o limite de 10 MB.', 'adam-comunidade' ) );
		}
		$extension = strtolower( pathinfo( sanitize_file_name( (string) $file['name'] ), PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
			return new \WP_Error( 'invalid_upload', __( 'O tipo de imagem não é permitido.', 'adam-comunidade' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_upload( $name, 0 );
		return is_wp_error( $id ) ? $id : absint( $id );
	}

	private function upload_many( string $name, int $limit ): array|\WP_Error {
		$original = $_FILES[ $name ] ?? null;
		if ( ! is_array( $original ) || empty( $original['name'] ) || ! is_array( $original['name'] ) ) {
			return array();
		}
		foreach ( array( 'type', 'tmp_name', 'error', 'size' ) as $property ) {
			if ( ! isset( $original[ $property ] ) || ! is_array( $original[ $property ] ) ) {
				return new \WP_Error( 'invalid_upload', __( 'Os dados do envio de imagens não são válidos.', 'adam-comunidade' ) );
			}
		}
		if ( count( array_filter( $original['name'] ) ) > $limit ) {
			return new \WP_Error( 'too_many', sprintf( __( 'Pode enviar no máximo %d fotografias.', 'adam-comunidade' ), $limit ) );
		}
		$ids = array();
		foreach ( array_keys( $original['name'] ) as $index ) {
			if ( empty( $original['name'][ $index ] ) ) { continue; }
			$_FILES[ $name ] = array(
				'name'     => (string) ( $original['name'][ $index ] ?? '' ),
				'type'     => (string) ( $original['type'][ $index ] ?? '' ),
				'tmp_name' => (string) ( $original['tmp_name'][ $index ] ?? '' ),
				'error'    => absint( $original['error'][ $index ] ?? UPLOAD_ERR_NO_FILE ),
				'size'     => absint( $original['size'][ $index ] ?? 0 ),
			);
			$id = $this->upload_one( $name );
			if ( is_wp_error( $id ) ) {
				$_FILES[ $name ] = $original;
				foreach ( $ids as $uploaded_id ) { wp_delete_attachment( $uploaded_id, true ); }
				return $id;
			}
			if ( $id ) { $ids[] = $id; }
		}
		$_FILES[ $name ] = $original;
		return $ids;
	}

	private function gallery_ids( string $type, object $record ): array {
		if ( 'field' === $type ) {
			return array_values(
				array_filter(
					array_map(
						static fn( object $item ): int => absint( $item->attachment_id ?? 0 ),
						( new Field_Repository() )->gallery( (int) $record->id )
					),
					'wp_attachment_is_image'
				)
			);
		}
		if ( in_array( $type, array( 'partner', 'institution' ), true ) ) {
			return array_values(
				array_filter(
					array_map(
						static fn( object $item ): int => absint( $item->attachment_id ?? 0 ),
						( new Directory_Repository() )->gallery( (int) $record->id )
					),
					'wp_attachment_is_image'
				)
			);
		}
		return array_values(
			array_filter(
				array_map( 'absint', (array) json_decode( (string) ( $record->gallery ?? '[]' ), true ) ),
				'wp_attachment_is_image'
			)
		);
	}

	private function notice(): void {
		$status = sanitize_key( wp_unslash( $_GET['estado'] ?? '' ) );
		$messages = array(
			'session-required'  => __( 'Inicie sessão para aceder à Área do Gestor.', 'adam-comunidade' ),
			'login-failed'      => __( 'Não foi possível iniciar sessão. Verifique os dados e tente novamente.', 'adam-comunidade' ),
			'logged-in'         => __( 'Sessão iniciada com sucesso.', 'adam-comunidade' ),
			'logged-out'        => __( 'Sessão terminada.', 'adam-comunidade' ),
			'password-mismatch' => __( 'As palavras-passe não coincidem.', 'adam-comunidade' ),
			'activation-failed' => __( 'O convite é inválido, já foi utilizado ou expirou.', 'adam-comunidade' ),
			'activated'         => __( 'Conta criada. Já pode iniciar sessão.', 'adam-comunidade' ),
			'revision-sent'     => __( 'Alterações enviadas para revisão. O registo público mantém-se inalterado até à aprovação.', 'adam-comunidade' ),
			'reset-requested'   => __( 'Se existir uma conta ativa com esse e-mail, enviámos as instruções de recuperação.', 'adam-comunidade' ),
			'reset-failed'      => __( 'O pedido de recuperação é inválido, já foi utilizado ou expirou.', 'adam-comunidade' ),
			'password-reset'    => __( 'Palavra-passe atualizada. Já pode iniciar sessão.', 'adam-comunidade' ),
		);
		if ( isset( $messages[ $status ] ) ) {
			echo '<div class="adam-manager-notice" role="status">' . esc_html( $messages[ $status ] ) . '</div>';
		}
	}

	private function redirect_status( string $status ): never {
		wp_safe_redirect( add_query_arg( 'estado', $status, self::url() ) );
		exit;
	}

	private function redirect_login_status( string $status ): never {
		wp_safe_redirect( add_query_arg( 'estado', $status, self::login_url() ) );
		exit;
	}

	private function route(): string {
		$route = sanitize_key( (string) get_query_var( 'adam_manager_route' ) );
		if ( $route ) {
			return $route;
		}
		if ( Managed_Pages::is_current( 'manager_activation' ) ) {
			return 'activate';
		}
		if ( Managed_Pages::is_current( 'manager_login' ) ) {
			return 'login';
		}
		if ( Managed_Pages::is_current( 'manager_recovery' ) ) {
			return 'recovery';
		}
		return Managed_Pages::is_current( 'manager' ) ? 'dashboard' : '';
	}
}
