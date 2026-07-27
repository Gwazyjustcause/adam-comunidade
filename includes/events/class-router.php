<?php
/**
 * Public Events routes.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Experience\Templates;
use ADAM\Comunidade\Helpers;

/**
 * Preserves the established /eventos URLs while moving their owner.
 */
final class Router {
	private const REWRITE_VERSION = '2.0.0';
	private const REWRITE_OPTION = 'adam_comunidade_events_rewrite_version';
	private static ?Event $current = null;
	private Repository $repository;

	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_flush' ), 30 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template_include' ), 30 );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^eventos/?$', 'index.php?adam_events=archive', 'top' );
		add_rewrite_rule( '^eventos/check-in/([^/]+)/?$', 'index.php?adam_events=checkin&adam_event_checkin=$matches[1]', 'top' );
		add_rewrite_rule( '^eventos/([^/]+)/?$', 'index.php?adam_events=detail&adam_event=$matches[1]', 'top' );
	}

	public function maybe_flush(): void {
		if ( self::REWRITE_VERSION !== get_option( self::REWRITE_OPTION ) ) {
			flush_rewrite_rules( false );
			update_option( self::REWRITE_OPTION, self::REWRITE_VERSION, false );
		}
	}

	/** @param string[] $vars @return string[] */
	public function query_vars( array $vars ): array {
		$vars[] = 'adam_events';
		$vars[] = 'adam_event';
		$vars[] = 'adam_event_checkin';
		return array_values( array_unique( $vars ) );
	}

	public function template_include( string $template ): string {
		$route = sanitize_key( (string) get_query_var( 'adam_events' ) );
		if ( 'archive' === $route ) {
			return Templates::locate( 'events/archive.php' );
		}
		if ( 'detail' === $route ) {
			self::$current = $this->repository->find_by_slug( sanitize_title( (string) get_query_var( 'adam_event' ) ) );
			if ( self::$current && self::$current->is_visible() ) {
				return Templates::locate( 'events/single.php' );
			}
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return get_404_template();
		}
		if ( 'checkin' === $route ) {
			return Templates::locate( 'events/checkin.php' );
		}
		return $template;
	}

	public static function current_event(): ?Event {
		return self::$current;
	}

	public function document_title( string $title ): string {
		if ( self::$current ) {
			return self::$current->title();
		}
		return 'archive' === get_query_var( 'adam_events' ) ? __( 'Eventos', 'adam-comunidade' ) : $title;
	}

	public function enqueue_assets(): void {
		if ( ! get_query_var( 'adam_events' ) ) {
			return;
		}
		wp_enqueue_style( 'adam-comunidade' );
		wp_enqueue_style(
			'adam-comunidade-events',
			Helpers::url( 'assets/css/events.css' ),
			array( 'adam-comunidade' ),
			ADAM_COMUNIDADE_VERSION
		);
		wp_enqueue_script(
			'adam-comunidade-events',
			Helpers::url( 'assets/js/events.js' ),
			array(),
			ADAM_COMUNIDADE_VERSION,
			true
		);
	}
}
