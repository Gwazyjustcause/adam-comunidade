<?php
/**
 * Settings API integration.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;

/**
 * Registers and validates plugin settings.
 */
final class Settings {
	public const OPTION_NAME = 'adam_comunidade_settings';

	/**
	 * Registers settings hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_adam_comunidade_reset_cache', array( $this, 'reset_cache' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'settings_updated' ), 10, 2 );
	}

	/**
	 * Registers fields and sections through the Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'adam_comunidade_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'adam_comunidade_general',
			__( 'Geral', 'adam-comunidade' ),
			'__return_false',
			'adam-comunidade-settings'
		);
		$this->add_field(
			'plugin_version',
			__( 'Versão do plugin', 'adam-comunidade' ),
			'render_plugin_version',
			'adam_comunidade_general'
		);
		$this->add_field(
			'contact_email',
			__( 'E-mail oficial da ADAM', 'adam-comunidade' ),
			'render_email',
			'adam_comunidade_general'
		);
		$this->add_field(
			'email_from_name',
			__( 'Nome do remetente', 'adam-comunidade' ),
			'render_email_from_name',
			'adam_comunidade_general'
		);
		add_settings_section(
			'adam_comunidade_appearance',
			__( 'Aspeto', 'adam-comunidade' ),
			'__return_false',
			'adam-comunidade-settings'
		);
		$this->add_field(
			'primary_colour',
			__( 'Cor principal', 'adam-comunidade' ),
			'render_colour',
			'adam_comunidade_appearance',
			array( 'key' => 'primary_colour' )
		);
		$this->add_field(
			'secondary_colour',
			__( 'Cor secundária', 'adam-comunidade' ),
			'render_colour',
			'adam_comunidade_appearance',
			array( 'key' => 'secondary_colour' )
		);
		$this->add_field(
			'accent_colour',
			__( 'Cor de destaque', 'adam-comunidade' ),
			'render_colour',
			'adam_comunidade_appearance',
			array( 'key' => 'accent_colour' )
		);

	}

	/**
	 * Adds a field to the plugin settings page.
	 *
	 * @param string               $id       Field identifier.
	 * @param string               $title    Field label.
	 * @param string               $callback Renderer method.
	 * @param string               $section  Section identifier.
	 * @param array<string,string> $args     Renderer arguments.
	 * @return void
	 */
	private function add_field(
		string $id,
		string $title,
		string $callback,
		string $section,
		array $args = array()
	): void {
		add_settings_field(
			$id,
			$title,
			array( $this, $callback ),
			'adam-comunidade-settings',
			$section,
			$args
		);
	}

	/**
	 * Sanitizes the settings collection.
	 *
	 * @param mixed $input Untrusted settings input.
	 * @return array<string,string|int>
	 */
	public function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$current  = wp_parse_args( get_option( self::OPTION_NAME, array() ), $defaults );

		return array(
			'debug_mode'           => empty( $input['debug_mode'] ) ? 0 : 1,
			'enable_logs'          => empty( $input['enable_logs'] ) ? 0 : 1,
			'primary_colour'       => sanitize_hex_color( $input['primary_colour'] ?? '' ) ?: $defaults['primary_colour'],
			'secondary_colour'     => sanitize_hex_color( $input['secondary_colour'] ?? '' ) ?: $defaults['secondary_colour'],
			'accent_colour'        => sanitize_hex_color( $input['accent_colour'] ?? '' ) ?: $defaults['accent_colour'],
			'contact_email'        => sanitize_email( (string) ( $input['contact_email'] ?? '' ) ),
			'email_from_name'      => sanitize_text_field( (string) ( $input['email_from_name'] ?? $defaults['email_from_name'] ) ),
			'community_page_id'    => absint( $current['community_page_id'] ),
			'teams_page_id'        => absint( $current['teams_page_id'] ),
			'fields_page_id'       => absint( $current['fields_page_id'] ),
			'partners_page_id'     => absint( $current['partners_page_id'] ),
			'institutions_page_id' => absint( $current['institutions_page_id'] ),
			'manager_page_id'      => absint( $current['manager_page_id'] ),
			'manager_login_page_id' => absint( $current['manager_login_page_id'] ),
			'manager_activation_page_id' => absint( $current['manager_activation_page_id'] ),
			'manager_recovery_page_id' => absint( $current['manager_recovery_page_id'] ),
			'brands_page_id'       => absint( $current['brands_page_id'] ),
		);
	}

	/**
	 * Renders the plugin version field.
	 *
	 * @return void
	 */
	public function render_plugin_version(): void {
		printf( '<code>%s</code>', esc_html( ADAM_COMUNIDADE_VERSION ) );
	}

	/**
	 * Renders a checkbox field.
	 *
	 * @param array<string,string> $args Field arguments.
	 * @return void
	 */
	public function render_checkbox( array $args ): void {
		$key = sanitize_key( $args['key'] ?? '' );
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>"
				value="1"
				<?php checked( 1, self::get( $key ) ); ?>
			>
			<?php esc_html_e( 'Ativado', 'adam-comunidade' ); ?>
		</label>
		<?php
	}

	/**
	 * Renders a colour picker field.
	 *
	 * @param array<string,string> $args Field arguments.
	 * @return void
	 */
	public function render_colour( array $args ): void {
		$key = sanitize_key( $args['key'] ?? '' );
		printf(
			'<input class="adam-comunidade-colour" type="text" name="%1$s" value="%2$s" data-default-color="%3$s">',
			esc_attr( self::OPTION_NAME . '[' . $key . ']' ),
			esc_attr( (string) self::get( $key ) ),
			esc_attr( (string) self::defaults()[ $key ] )
		);
	}

	/**
	 * Renders the official public contact email setting.
	 */
	public function render_email(): void {
		printf(
			'<input class="regular-text" type="email" name="%1$s" value="%2$s" placeholder="%3$s"><p class="description">%4$s</p>',
			esc_attr( self::OPTION_NAME . '[contact_email]' ),
			esc_attr( (string) self::get( 'contact_email' ) ),
			esc_attr( (string) get_option( 'admin_email', '' ) ),
			esc_html__( 'Usado nos emails públicos. Se ficar vazio, será utilizado o email de administração do WordPress.', 'adam-comunidade' )
		);
	}

	/**
	 * Renders the sender name used by every Community email.
	 */
	public function render_email_from_name(): void {
		printf(
			'<input class="regular-text" type="text" name="%1$s" value="%2$s" placeholder="%3$s"><p class="description">%4$s</p>',
			esc_attr( self::OPTION_NAME . '[email_from_name]' ),
			esc_attr( (string) self::get( 'email_from_name' ) ),
			esc_attr__( 'ADAM Comunidade', 'adam-comunidade' ),
			esc_html__( 'Nome apresentado como remetente em todas as mensagens da Comunidade.', 'adam-comunidade' )
		);
	}

	/**
	 * Renders the database version field.
	 *
	 * @return void
	 */
	public function render_data_version(): void {
		$data_version = get_option( 'adam_comunidade_db_version', ADAM_COMUNIDADE_DB_VERSION );

		printf( '<code>%s</code>', esc_html( (string) $data_version ) );
	}

	/**
	 * Handles the cache reset placeholder action securely.
	 *
	 * @return void
	 */
	public function reset_cache(): void {
		Admin_Router::authorize();

		check_admin_referer( 'adam_comunidade_reset_cache' );

		do_action( 'adam_comunidade_reset_cache' );
		Helpers::add_admin_notice(
			__( 'Community object, archive, discovery, and REST caches were invalidated.', 'adam-comunidade' ),
			'success'
		);

		wp_safe_redirect( Admin_Router::page_url( 'settings' ) );
		exit;
	}

	/**
	 * Logs settings changes when logging is enabled.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $value     New settings.
	 * @return void
	 */
	public function settings_updated( mixed $old_value, mixed $value ): void {
		unset( $old_value );
		Logger::info(
			'Settings changed',
			array( 'user_id' => get_current_user_id() ),
			is_array( $value ) ? $value : array()
		);
	}

	/**
	 * Gets one setting.
	 *
	 * @param string $key Setting key.
	 * @return string|int|null
	 */
	public static function get( string $key ): string|int|null {
		$settings = wp_parse_args( get_option( self::OPTION_NAME, array() ), self::defaults() );

		return $settings[ $key ] ?? null;
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,string|int>
	 */
	public static function defaults(): array {
		return array(
			'debug_mode'           => 0,
			'enable_logs'          => 0,
			'primary_colour'       => '#1d4ed8',
			'secondary_colour'     => '#0f172a',
			'accent_colour'        => '#f59e0b',
			'contact_email'        => '',
			'email_from_name'      => 'ADAM Comunidade',
			'community_page_id'    => 0,
			'teams_page_id'        => 0,
			'fields_page_id'       => 0,
			'partners_page_id'     => 0,
			'institutions_page_id' => 0,
			'manager_page_id'      => 0,
			'manager_login_page_id' => 0,
			'manager_activation_page_id' => 0,
			'manager_recovery_page_id' => 0,
			'brands_page_id'       => 0,
		);
	}
}
