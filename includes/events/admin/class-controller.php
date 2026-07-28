<?php
/**
 * Events administration.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events\Admin;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Events\Api;
use ADAM\Comunidade\Events\Event;
use ADAM\Comunidade\Events\Repository;
use ADAM\Comunidade\Uploads\Component as Upload_Component;

/**
 * Manages events, the calendar, categories and reusable locations.
 */
final class Controller {
	private Repository $repository;

	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		Admin_Router::register_module(
			'events',
			array(
				'title' => __( 'Eventos', 'adam-comunidade' ),
				'singular' => __( 'Evento', 'adam-comunidade' ),
				'singular_slug' => 'event',
				'controller' => $this,
				'methods' => array( 'list' => 'index', 'create' => 'create', 'edit' => 'edit' ),
			)
		);
		Admin_Router::register_page(
			'event-calendar',
			array(
				'title' => __( 'Calendário de eventos', 'adam-comunidade' ),
				'controller' => $this,
				'method' => 'calendar',
				'visible' => false,
			)
		);
		Admin_Router::register_page(
			'event-vocabularies',
			array(
				'title' => __( 'Categorias e locais de eventos', 'adam-comunidade' ),
				'controller' => $this,
				'method' => 'vocabularies',
				'visible' => false,
			)
		);
		add_action( 'admin_post_adam_comunidade_save_event', array( $this, 'handle_save' ) );
		add_action( 'admin_post_adam_comunidade_delete_event', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_adam_comunidade_save_event_vocabularies', array( $this, 'handle_vocabularies' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( str_contains( $hook_suffix, 'adam-comunidade-event' ) ) {
			wp_enqueue_media();
		}
	}

	public function index(): void {
		$events = $this->repository->query(
			array(
				'search' => sanitize_text_field( (string) ( $_GET['s'] ?? '' ) ),
				'status' => sanitize_key( (string) ( $_GET['status'] ?? '' ) ),
			)
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Eventos', 'adam-comunidade' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( Admin_Router::module_url( 'events', 'add' ) ); ?>"><?php esc_html_e( 'Adicionar evento', 'adam-comunidade' ); ?></a>
			<?php $this->tabs(); ?>
			<form method="get">
				<input type="hidden" name="page" value="adam-comunidade-events">
				<p class="search-box">
					<label class="screen-reader-text" for="event-search"><?php esc_html_e( 'Pesquisar eventos', 'adam-comunidade' ); ?></label>
					<input id="event-search" name="s" type="search" value="<?php echo esc_attr( (string) ( $_GET['s'] ?? '' ) ); ?>">
					<?php submit_button( __( 'Pesquisar eventos', 'adam-comunidade' ), '', '', false ); ?>
				</p>
			</form>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th><?php esc_html_e( 'Evento', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Data', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Local', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Estado', 'adam-comunidade' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $events ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Ainda não existem eventos.', 'adam-comunidade' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $events as $event ) : ?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( Admin_Router::module_url( 'events', 'edit', array( 'id' => $event->id() ) ) ); ?>"><?php echo esc_html( $event->title() ); ?></a></strong>
							<div class="row-actions">
								<span><a href="<?php echo esc_url( Api::instance()->event_url( $event ) ); ?>" target="_blank"><?php esc_html_e( 'Ver', 'adam-comunidade' ); ?></a> | </span>
								<span class="trash"><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'adam_comunidade_delete_event', 'id' => $event->id() ), admin_url( 'admin-post.php' ) ), 'adam_comunidade_delete_event_' . $event->id() ) ); ?>"><?php esc_html_e( 'Eliminar', 'adam-comunidade' ); ?></a></span>
							</div>
						</td>
						<td><?php echo esc_html( $event->event_date() . ' ' . $event->start_time() ); ?></td>
						<td><?php echo esc_html( $event->location() ); ?></td>
						<td><?php echo esc_html( $this->status_label( $event->status() ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function create(): void {
		$this->editor( new Event( array() ) );
	}

	public function edit( int $id ): void {
		$event = $this->repository->find( $id );
		if ( ! $event ) {
			wp_die( esc_html__( 'Evento não encontrado.', 'adam-comunidade' ) );
		}
		$this->editor( $event );
	}

	public function calendar(): void {
		$events = $this->repository->query();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Calendário de eventos', 'adam-comunidade' ); ?></h1>
			<?php $this->tabs( 'calendar' ); ?>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Data', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Evento', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Horário', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Local', 'adam-comunidade' ); ?></th></tr></thead>
				<tbody><?php foreach ( $events as $event ) : ?><tr><td><?php echo esc_html( $event->event_date() ); ?></td><td><a href="<?php echo esc_url( Admin_Router::module_url( 'events', 'edit', array( 'id' => $event->id() ) ) ); ?>"><?php echo esc_html( $event->title() ); ?></a></td><td><?php echo esc_html( $event->start_time() . ( $event->end_time() ? '–' . $event->end_time() : '' ) ); ?></td><td><?php echo esc_html( $event->location() ); ?></td></tr><?php endforeach; ?></tbody>
			</table>
		</div>
		<?php
	}

	public function vocabularies(): void {
		$categories = implode( "\n", array_map( static fn( array $item ): string => (string) ( $item['name'] ?? '' ), $this->repository->categories() ) );
		$locations = implode( "\n", array_map( static fn( array $item ): string => (string) ( $item['name'] ?? '' ), $this->repository->locations() ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Categorias e locais', 'adam-comunidade' ); ?></h1>
			<?php $this->tabs( 'vocabularies' ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="adam_comunidade_save_event_vocabularies">
				<?php wp_nonce_field( 'adam_comunidade_save_event_vocabularies' ); ?>
				<table class="form-table">
					<tr><th><label for="event-categories"><?php esc_html_e( 'Categorias', 'adam-comunidade' ); ?></label></th><td><textarea id="event-categories" class="large-text" rows="10" name="categories"><?php echo esc_textarea( $categories ); ?></textarea><p class="description"><?php esc_html_e( 'Uma categoria por linha.', 'adam-comunidade' ); ?></p></td></tr>
					<tr><th><label for="event-locations"><?php esc_html_e( 'Locais', 'adam-comunidade' ); ?></label></th><td><textarea id="event-locations" class="large-text" rows="10" name="locations"><?php echo esc_textarea( $locations ); ?></textarea><p class="description"><?php esc_html_e( 'Um local reutilizável por linha.', 'adam-comunidade' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Guardar categorias e locais', 'adam-comunidade' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save(): never {
		Admin_Router::authorize();
		check_admin_referer( 'adam_comunidade_save_event' );
		$id = absint( $_POST['event_id'] ?? 0 );
		$existing = $id ? $this->repository->find( $id ) : null;
		$input = isset( $_POST['event'] ) && is_array( $_POST['event'] ) ? wp_unslash( $_POST['event'] ) : array();
		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		$date = sanitize_text_field( (string) ( $input['event_date'] ?? '' ) );
		if ( ! $title || ! $date ) {
			wp_die( esc_html__( 'O título e a data do evento são obrigatórios.', 'adam-comunidade' ) );
		}
		$data = $this->sanitize( $input, $existing );
		$data['slug'] = $this->repository->unique_slug( $title, $id );
		$data['created_at'] = $existing ? $existing->created_at() : current_time( 'mysql', true );
		$data['updated_at'] = current_time( 'mysql', true );
		$event = Api::instance()->save_event( $data, $id );
		if ( is_wp_error( $event ) ) {
			wp_die( esc_html( $event->get_error_message() ) );
		}
		wp_safe_redirect( Admin_Router::module_url( 'events', 'edit', array( 'id' => $event->id(), 'updated' => 1 ) ) );
		exit;
	}

	public function handle_delete(): never {
		Admin_Router::authorize();
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'adam_comunidade_delete_event_' . $id );
		Api::instance()->delete_event( $id );
		wp_safe_redirect( Admin_Router::module_url( 'events' ) );
		exit;
	}

	public function handle_vocabularies(): never {
		Admin_Router::authorize();
		check_admin_referer( 'adam_comunidade_save_event_vocabularies' );
		foreach ( array( 'categories', 'locations' ) as $type ) {
			$lines = preg_split( '/\R/', sanitize_textarea_field( (string) ( $_POST[ $type ] ?? '' ) ) ) ?: array();
			$items = array();
			foreach ( array_filter( array_map( 'trim', $lines ) ) as $index => $name ) {
				$items[] = array( 'id' => $index + 1, 'name' => $name, 'slug' => sanitize_title( $name ) );
			}
			if ( ! $this->repository->save_taxonomy( $type, $items ) ) {
				wp_die( esc_html__( 'Não foi possível guardar as categorias e localizações dos eventos.', 'adam-comunidade' ) );
			}
		}
		wp_safe_redirect( Admin_Router::page_url( 'event-vocabularies', array( 'updated' => 1 ) ) );
		exit;
	}

	private function editor( Event $event ): void {
		$data = $event->data();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $event->id() ? __( 'Editar evento', 'adam-comunidade' ) : __( 'Adicionar evento', 'adam-comunidade' ) ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="adam_comunidade_save_event">
				<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event->id() ); ?>">
				<?php wp_nonce_field( 'adam_comunidade_save_event' ); ?>
				<table class="form-table">
					<?php $this->text_row( 'title', __( 'Título', 'adam-comunidade' ), $event->title(), true ); ?>
					<?php $this->text_row( 'short_description', __( 'Descrição curta', 'adam-comunidade' ), $event->short_description() ); ?>
					<tr><th><?php esc_html_e( 'Descrição completa', 'adam-comunidade' ); ?></th><td><?php wp_editor( $event->full_description(), 'adam-event-description', array( 'textarea_name' => 'event[full_description]', 'textarea_rows' => 10 ) ); ?></td></tr>
					<?php $this->text_row( 'event_date', __( 'Data', 'adam-comunidade' ), $event->event_date(), true, 'date' ); ?>
					<?php $this->text_row( 'start_time', __( 'Hora de início', 'adam-comunidade' ), $event->start_time(), false, 'time' ); ?>
					<?php $this->text_row( 'end_time', __( 'Hora de fim', 'adam-comunidade' ), $event->end_time(), false, 'time' ); ?>
					<?php $this->text_row( 'location', __( 'Local', 'adam-comunidade' ), $event->location(), false, 'text', array_map( static fn( array $item ): string => (string) $item['name'], $this->repository->locations() ) ); ?>
					<?php $this->text_row( 'map_link', __( 'Ligação do mapa', 'adam-comunidade' ), $event->map_link(), false, 'url' ); ?>
					<tr><th><?php esc_html_e( 'Imagem de capa', 'adam-comunidade' ); ?></th><td>
						<input type="hidden" name="event[cover_image]" value="<?php echo esc_attr( $event->cover_image() ); ?>">
						<?php Upload_Component::render( array( 'mode' => 'library', 'kind' => 'image', 'name' => 'event[cover_id]', 'items' => $event->cover_id() ? array( Upload_Component::attachment( $event->cover_id() ) ) : array() ) ); ?>
						<?php if ( ! $event->cover_id() && $event->cover_image() ) : ?><p class="description"><?php esc_html_e( 'A imagem legada foi preservada. Selecione outra apenas se a pretender substituir.', 'adam-comunidade' ); ?></p><img src="<?php echo esc_url( $event->cover_image() ); ?>" alt="" style="max-width:320px;height:auto"><?php endif; ?>
					</td></tr>
					<?php $this->text_row( 'external_registration_url', __( 'Ligação de inscrição', 'adam-comunidade' ), $event->external_registration_url(), false, 'url' ); ?>
					<?php $this->text_row( 'external_provider_name', __( 'Plataforma de inscrição', 'adam-comunidade' ), $event->external_provider_name() ); ?>
					<?php if ( $this->repository->categories() ) : ?><tr><th><?php esc_html_e( 'Categorias', 'adam-comunidade' ); ?></th><td><?php foreach ( $this->repository->categories() as $category ) : ?><label style="display:block"><input type="checkbox" name="event[category_ids][]" value="<?php echo esc_attr( (string) $category['id'] ); ?>" <?php checked( in_array( absint( $category['id'] ), $event->category_ids(), true ) ); ?>> <?php echo esc_html( (string) $category['name'] ); ?></label><?php endforeach; ?></td></tr><?php endif; ?>
					<?php $this->text_row( 'price', __( 'Preço', 'adam-comunidade' ), $event->price() ); ?>
					<?php $this->text_row( 'player_limit', __( 'Limite de participantes', 'adam-comunidade' ), (string) $event->player_limit(), false, 'number' ); ?>
					<?php $this->text_row( 'waiting_list_limit', __( 'Limite da lista de espera', 'adam-comunidade' ), (string) $event->waiting_list_limit(), false, 'number' ); ?>
					<?php $this->text_row( 'registration_deadline', __( 'Prazo de inscrição', 'adam-comunidade' ), $event->registration_deadline(), false, 'datetime-local' ); ?>
					<?php $this->text_row( 'priority_deadline', __( 'Fim da prioridade de sócios', 'adam-comunidade' ), $event->priority_deadline(), false, 'datetime-local' ); ?>
					<?php $this->text_row( 'checkin_open_at', __( 'Abertura do check-in', 'adam-comunidade' ), $event->checkin_open_at(), false, 'datetime-local' ); ?>
					<?php $this->text_row( 'checkin_close_at', __( 'Fecho do check-in', 'adam-comunidade' ), $event->checkin_close_at(), false, 'datetime-local' ); ?>
					<?php $this->text_row( 'checkin_points', __( 'Pontos de check-in', 'adam-comunidade' ), (string) $event->checkin_points(), false, 'number' ); ?>
					<?php $this->text_row( 'checkin_bonus_trigger_position', __( 'Posição que ativa o bónus', 'adam-comunidade' ), (string) $event->checkin_bonus_trigger_position(), false, 'number' ); ?>
					<?php $this->text_row( 'checkin_bonus_points', __( 'Pontos do bónus', 'adam-comunidade' ), (string) $event->checkin_bonus_points(), false, 'number' ); ?>
					<?php $this->text_row( 'checkin_bonus_template', __( 'Modelo da mensagem de bónus', 'adam-comunidade' ), $event->checkin_bonus_template() ); ?>
					<tr><th><label for="event-bonus-message"><?php esc_html_e( 'Mensagem personalizada do bónus', 'adam-comunidade' ); ?></label></th><td><textarea class="large-text" id="event-bonus-message" name="event[checkin_bonus_custom_message]" rows="4"><?php echo esc_textarea( $event->checkin_bonus_custom_message() ); ?></textarea></td></tr>
					<tr><th><?php esc_html_e( 'Acesso', 'adam-comunidade' ); ?></th><td><select name="event[access_mode]"><?php foreach ( Event::access_modes() as $mode ) : ?><option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $event->access_mode(), $mode ); ?>><?php echo esc_html( $this->access_label( $mode ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><?php esc_html_e( 'Estado', 'adam-comunidade' ); ?></th><td><select name="event[status]"><?php foreach ( Event::statuses() as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $event->status(), $status ); ?>><?php echo esc_html( $this->status_label( $status ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><?php esc_html_e( 'Opções', 'adam-comunidade' ); ?></th><td>
						<label><input type="checkbox" name="event[is_paid]" value="1" <?php checked( $event->is_paid() ); ?>> <?php esc_html_e( 'Evento pago', 'adam-comunidade' ); ?></label><br>
						<label><input type="checkbox" name="event[waiting_list_enabled]" value="1" <?php checked( $event->waiting_list_enabled() ); ?>> <?php esc_html_e( 'Lista de espera', 'adam-comunidade' ); ?></label><br>
						<label><input type="checkbox" name="event[checkin_enabled]" value="1" <?php checked( $event->checkin_enabled() ); ?>> <?php esc_html_e( 'Check-in de sócios', 'adam-comunidade' ); ?></label><br>
						<label><input type="checkbox" name="event[checkin_bonus_enabled]" value="1" <?php checked( $event->checkin_bonus_enabled() ); ?>> <?php esc_html_e( 'Bónus automático de check-in', 'adam-comunidade' ); ?></label><br>
						<label><input type="checkbox" name="event[checkin_bonus_count_manual]" value="1" <?php checked( $event->checkin_bonus_count_manual() ); ?>> <?php esc_html_e( 'Contar presenças manuais para o bónus', 'adam-comunidade' ); ?></label><br>
						<label><input type="checkbox" name="event[image_video_notice_disabled]" value="1" <?php checked( $event->image_video_notice_disabled() ); ?>> <?php esc_html_e( 'Desativar aviso de imagem e vídeo', 'adam-comunidade' ); ?></label>
					</td></tr>
					<tr><th><label for="event-notes"><?php esc_html_e( 'Notas internas', 'adam-comunidade' ); ?></label></th><td><textarea class="large-text" id="event-notes" name="event[notes]" rows="5"><?php echo esc_textarea( $event->notes() ); ?></textarea></td></tr>
				</table>
				<?php submit_button( __( 'Guardar evento', 'adam-comunidade' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** @param string[] $suggestions */
	private function text_row( string $name, string $label, string $value, bool $required = false, string $type = 'text', array $suggestions = array() ): void {
		$list_id = $suggestions ? 'adam-event-' . $name . '-list' : '';
		printf( '<tr><th><label for="adam-event-%1$s">%2$s</label></th><td><input class="regular-text" id="adam-event-%1$s" name="event[%1$s]" type="%3$s" value="%4$s"%5$s%6$s></td></tr>', esc_attr( $name ), esc_html( $label ), esc_attr( $type ), esc_attr( $value ), $required ? ' required' : '', $list_id ? ' list="' . esc_attr( $list_id ) . '"' : '' );
		if ( $list_id ) {
			echo '<datalist id="' . esc_attr( $list_id ) . '">';
			foreach ( $suggestions as $suggestion ) {
				echo '<option value="' . esc_attr( $suggestion ) . '">';
			}
			echo '</datalist>';
		}
	}

	/** @param array<string,mixed> $input @return array<string,mixed> */
	private function sanitize( array $input, ?Event $existing ): array {
		$text = array( 'title', 'event_date', 'start_time', 'end_time', 'location', 'external_provider_name', 'price', 'registration_deadline', 'priority_deadline', 'checkin_open_at', 'checkin_close_at' );
		$data = array();
		foreach ( $text as $key ) {
			$data[ $key ] = sanitize_text_field( (string) ( $input[ $key ] ?? '' ) );
		}
		$data['short_description'] = sanitize_textarea_field( (string) ( $input['short_description'] ?? '' ) );
		$data['full_description'] = wp_kses_post( (string) ( $input['full_description'] ?? '' ) );
		$data['notes'] = sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) );
		foreach ( array( 'map_link', 'cover_image', 'external_registration_url' ) as $key ) {
			$data[ $key ] = esc_url_raw( (string) ( $input[ $key ] ?? '' ) );
		}
		foreach ( array( 'cover_id', 'player_limit', 'waiting_list_limit', 'checkin_points', 'checkin_bonus_trigger_position', 'checkin_bonus_points' ) as $key ) {
			$data[ $key ] = absint( $input[ $key ] ?? 0 );
		}
		foreach ( array( 'is_paid', 'waiting_list_enabled', 'checkin_enabled', 'image_video_notice_disabled', 'checkin_bonus_enabled', 'checkin_bonus_count_manual' ) as $key ) {
			$data[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
		$data['status'] = in_array( sanitize_key( (string) ( $input['status'] ?? '' ) ), Event::statuses(), true ) ? sanitize_key( (string) $input['status'] ) : Event::STATUS_DRAFT;
		$data['access_mode'] = in_array( sanitize_key( (string) ( $input['access_mode'] ?? '' ) ), Event::access_modes(), true ) ? sanitize_key( (string) $input['access_mode'] ) : Event::ACCESS_OPEN;
		$data['checkin_token'] = $existing && $existing->checkin_token() ? $existing->checkin_token() : wp_generate_password( 32, false, false );
		$data['checkin_bonus_template'] = sanitize_key( (string) ( $input['checkin_bonus_template'] ?? ( $existing ? $existing->checkin_bonus_template() : 'bonus_unlocked' ) ) );
		$data['checkin_bonus_custom_message'] = sanitize_textarea_field( (string) ( $input['checkin_bonus_custom_message'] ?? ( $existing ? $existing->checkin_bonus_custom_message() : '' ) ) );
		$data['category_ids'] = array_values( array_filter( array_map( 'absint', (array) ( $input['category_ids'] ?? array() ) ) ) );
		$data['location_id'] = 0;
		foreach ( $this->repository->locations() as $location ) {
			if ( sanitize_title( (string) ( $location['name'] ?? '' ) ) === sanitize_title( $data['location'] ) ) {
				$data['location_id'] = absint( $location['id'] ?? 0 );
				break;
			}
		}
		if ( $data['cover_id'] ) {
			$data['cover_image'] = (string) wp_get_attachment_image_url( $data['cover_id'], 'full' );
		}
		return $data;
	}

	private function tabs( string $active = 'list' ): void {
		$tabs = array(
			'list' => array( __( 'Todos os eventos', 'adam-comunidade' ), Admin_Router::module_url( 'events' ) ),
			'calendar' => array( __( 'Calendário', 'adam-comunidade' ), Admin_Router::page_url( 'event-calendar' ) ),
			'vocabularies' => array( __( 'Categorias e locais', 'adam-comunidade' ), Admin_Router::page_url( 'event-vocabularies' ) ),
		);
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $tab ) {
			echo '<a class="nav-tab ' . ( $active === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $tab[1] ) . '">' . esc_html( $tab[0] ) . '</a>';
		}
		echo '</nav>';
	}

	private function status_label( string $status ): string {
		return array(
			Event::STATUS_DRAFT => __( 'Rascunho', 'adam-comunidade' ),
			Event::STATUS_PUBLISHED => __( 'Publicado', 'adam-comunidade' ),
			Event::STATUS_CANCELLED => __( 'Cancelado', 'adam-comunidade' ),
			Event::STATUS_COMPLETED => __( 'Concluído', 'adam-comunidade' ),
		)[ $status ] ?? $status;
	}

	private function access_label( string $mode ): string {
		return array(
			Event::ACCESS_OPEN => __( 'Aberto a todos', 'adam-comunidade' ),
			Event::ACCESS_MEMBERS_ONLY => __( 'Apenas sócios', 'adam-comunidade' ),
			Event::ACCESS_MEMBER_PRIORITY => __( 'Prioridade para sócios', 'adam-comunidade' ),
		)[ $mode ] ?? $mode;
	}
}
