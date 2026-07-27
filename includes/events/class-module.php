<?php
/**
 * Events module bootstrap.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Module_Interface;
use ADAM\Comunidade\Events\Admin\Controller;

/**
 * Registers the single Events implementation.
 */
final class Module implements Module_Interface {
	public function id(): string { return 'events'; }

	public function register(): void {
		Migration::run();
		$repository = new Repository();
		( new Router( $repository ) )->register();
		( new Rest_API( Api::instance() ) )->register();
		if ( is_admin() ) {
			( new Controller( $repository ) )->register();
		}
		add_filter( 'adam_bot_dynamic_events', array( $this, 'bot_items' ), 10, 2 );
		add_filter( 'adam_bot_knowledge_event_items', array( $this, 'bot_items' ), 10, 2 );
		add_filter( 'adam_comunidade_search_results', array( $this, 'search_results' ), 10, 3 );
	}

	/** @param mixed $items @return array<int,array<string,mixed>> */
	public function bot_items( mixed $items, mixed $query = '' ): array {
		$items = is_array( $items ) ? $items : array();
		unset( $query );
		foreach ( Api::instance()->upcoming_events( 12 ) as $event ) {
			$items[] = array(
				'id' => $event->id(),
				'object_id' => $event->id(),
				'title' => $event->title(),
				'summary' => $event->short_description(),
				'content' => $event->full_description() ?: $event->short_description(),
				'date' => $event->event_date(),
				'location' => $event->location(),
				'price' => $event->price(),
				'category' => __( 'Eventos', 'adam-comunidade' ),
				'url' => Api::instance()->event_url( $event ),
				'status' => $event->status(),
				'public' => true,
				'enabled' => true,
				'priority' => 80,
			);
		}
		return $items;
	}

	/**
	 * Adds canonical events to universal Community search.
	 *
	 * @param array<string,array<int,array<string,mixed>>> $groups Existing groups.
	 * @param string                                      $term   Search term.
	 * @param array<string,mixed>                         $filters Search filters.
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public function search_results( array $groups, string $term, array $filters ): array {
		unset( $filters );
		$events = array_slice(
			Api::instance()->get_events(
				array(
					'status' => Event::STATUS_PUBLISHED,
					'search' => $term,
				)
			),
			0,
			8
		);
		$groups['events'] = array_map(
			static fn( Event $event ): array => array(
				'id' => $event->id(),
				'type' => 'event',
				'name' => $event->title(),
				'description' => $event->short_description(),
				'url' => Api::instance()->event_url( $event ),
				'icon' => 'calendar-alt',
				'district' => '',
				'municipality' => '',
				'latitude' => null,
				'longitude' => null,
				'event_date' => $event->event_date(),
				'location' => $event->location(),
			),
			$events
		);
		return $groups;
	}
}
