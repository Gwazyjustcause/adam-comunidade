<?php
/**
 * Community Manager module bootstrap.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Experience\Email_Service;
use ADAM\Comunidade\Logger;
use ADAM\Comunidade\Module_Interface;

/**
 * Boots the isolated manager portal and revision workflow.
 */
final class Module implements Module_Interface {
	public function id(): string {
		return 'managers';
	}

	public function register(): void {
		$previous = (string) get_option( 'adam_comunidade_managers_db_version', '' );
		$result   = Schema::maybe_upgrade();
		if ( is_wp_error( $result ) ) {
			Logger::error(
				'community_manager_module_unavailable',
				array( 'code' => sanitize_key( (string) $result->get_error_code() ) )
			);
			add_action( 'admin_notices', array( $this, 'migration_notice' ) );
			return;
		}
		if ( Schema::VERSION !== $previous ) {
			update_option( 'adam_comunidade_manager_routes_version', '', false );
		}
		( new Cleanup() )->register();
		$emails  = new Email_Service();
		$auth    = new Auth();
		$service = new Service( $emails );
		( new Portal( $auth, $service ) )->register();
		( new Admin( $service ) )->register();
	}

	/**
	 * Shows a safe diagnostic without exposing SQL or stack traces.
	 */
	public function migration_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$error = Schema::last_error();
		$code  = sanitize_key( (string) ( $error['code'] ?? 'manager_schema_unavailable' ) );
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: safe diagnostic code. */
						__( 'A atualização da base de dados dos Gestores não foi concluída. O sistema de Gestores foi temporariamente desativado para evitar erros. Código: %s.', 'adam-comunidade' ),
						$code
					)
				);
				?>
			</p>
		</div>
		<?php
	}
}
