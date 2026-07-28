<?php
/**
 * Events persistence boundary.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Allows the Events repository to move to indexed storage without API changes.
 */
interface Store_Interface {
	/** @return array<int,array<string,mixed>> */
	public function events(): array;

	/** @return array<string,mixed>|null */
	public function find_event( int $id ): ?array;

	/** @return array<string,mixed>|null */
	public function find_event_by_slug( string $slug ): ?array;

	/** @param array<string,mixed> $filters @return array<int,array<string,mixed>> */
	public function query_events( array $filters ): array;

	/** @param array<string,mixed> $event */
	public function save_event( array $event ): bool;

	public function delete_event( int $id ): bool;

	public function slug_exists( string $slug, int $ignore_id = 0 ): bool;

	public function next_id(): int;

	public function save_next_id( int $next_id ): bool;

	/** @return array<int,array<string,mixed>> */
	public function taxonomy( string $type ): array;

	/** @param array<int,array<string,mixed>> $items */
	public function save_taxonomy( string $type, array $items ): bool;
}
