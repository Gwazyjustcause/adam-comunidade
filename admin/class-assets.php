<?php
/**
 * Admin asset loader.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Admin;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Uploads\Component as Upload_Component;

/**
 * Loads admin assets only on plugin screens.
 */
final class Assets {
	/**
	 * Registers asset hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues assets for ADAM Comunidade screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'adam-comunidade' ) ) {
			return;
		}

		wp_enqueue_style( 'adam-comunidade-admin', Helpers::url( 'assets/css/admin.css' ), array(), ADAM_COMUNIDADE_VERSION );
		Upload_Component::enqueue_assets();

		$script_dependencies = array( 'jquery' );
		if ( str_contains( $hook_suffix, 'adam-comunidade-settings' ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			$script_dependencies[] = 'wp-color-picker';
		}
		wp_enqueue_script(
			'adam-comunidade-admin',
			Helpers::url( 'assets/js/admin.js' ),
			$script_dependencies,
			ADAM_COMUNIDADE_VERSION,
			true
		);
		wp_localize_script(
			'adam-comunidade-admin',
			'adamAdminUx',
			array(
				'processing'       => __( 'A processar…', 'adam-comunidade' ),
				'confirmTitle'     => __( 'Confirmar ação', 'adam-comunidade' ),
				'confirmAction'    => __( 'Confirmar', 'adam-comunidade' ),
				'cancel'           => __( 'Cancelar', 'adam-comunidade' ),
				'transferRequired' => __( 'Selecione o Gestor que irá receber as organizações.', 'adam-comunidade' ),
				'noteRequired'     => __( 'Indique ao Gestor o motivo da decisão ou a informação necessária.', 'adam-comunidade' ),
				'moderationReasonRequired' => __( 'Selecione pelo menos um motivo para comunicar esta decisão.', 'adam-comunidade' ),
				'conflictRequired' => __( 'Confirme que reviu o conflito com a versão publicada.', 'adam-comunidade' ),
				'entityRequired'   => __( 'Selecione pelo menos uma organização.', 'adam-comunidade' ),
				'selectedOne'      => __( '1 organização selecionada', 'adam-comunidade' ),
				'selectedMany'     => __( '%d organizações selecionadas', 'adam-comunidade' ),
				'noneSelected'     => __( 'Nenhuma organização selecionada', 'adam-comunidade' ),
				'noMatches'        => __( 'Nenhuma organização corresponde à pesquisa.', 'adam-comunidade' ),
			)
		);
	}
}
