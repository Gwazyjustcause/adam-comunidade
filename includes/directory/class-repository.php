<?php
/**
 * Directory data access.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates organisation and media queries.
 */
final class Repository {
	/**
	 * Finds an entry by ID, optionally constrained by type.
	 *
	 * @param int    $id   Entry ID.
	 * @param string $type Entity type.
	 * @return object|null
	 */
	public function find( int $id, string $type = '' ): ?object {
		global $wpdb;
		$sql  = 'SELECT * FROM ' . Schema::entries_table() . ' WHERE id = %d';
		$args = array( $id );
		if ( $type ) {
			$sql   .= ' AND entity_type = %s';
			$args[] = $type;
		}
		$row = $wpdb->get_row( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $row ?: null;
	}

	/**
	 * Finds an entry by its type-local slug.
	 *
	 * @param string $type           Entity type.
	 * @param string $slug           Entry slug.
	 * @param bool   $published_only Require published status.
	 * @return object|null
	 */
	public function find_by_slug( string $type, string $slug, bool $published_only = true ): ?object {
		global $wpdb;
		$sql  = 'SELECT * FROM ' . Schema::entries_table() . ' WHERE entity_type = %s AND slug = %s';
		$args = array( $type, $slug );
		if ( $published_only ) {
			$sql   .= ' AND status = %s';
			$args[] = 'published';
		}
		$row = $wpdb->get_row( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $row ?: null;
	}

	/**
	 * Creates an entry.
	 *
	 * @param array<string,mixed> $data Sanitized data.
	 * @return int|false
	 */
	public function create( array $data ): int|false {
		global $wpdb;
		$now                = current_time( 'mysql', true );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$data['created_by'] = get_current_user_id();
		$data['updated_by'] = get_current_user_id();
		if ( 'published' === $data['status'] ) {
			$data['published_at'] = $now;
		}
		$result = $wpdb->insert( Schema::entries_table(), $data );
		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Updates an entry.
	 *
	 * @param int                 $id   Entry ID.
	 * @param array<string,mixed> $data Sanitized data.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$current            = $this->find( $id );
		$data['updated_at'] = current_time( 'mysql', true );
		$data['updated_by'] = get_current_user_id();
		if ( $current && isset( $data['status'] ) && 'published' !== $current->status && 'published' === $data['status'] ) {
			$data['published_at'] = current_time( 'mysql', true );
		}
		return false !== $wpdb->update( Schema::entries_table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Deletes database records while retaining Media Library attachments.
	 *
	 * @param int $id Entry ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		$entry = $this->find( $id );
		if ( ! $entry ) {
			return false;
		}
		$wpdb->delete( Schema::galleries_table(), array( 'entry_id' => $id ), array( '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . Schema::relationships_table() . ' WHERE (source_type = %s AND source_id = %d) OR (target_type = %s AND target_id = %d)',
				$entry->entity_type,
				$id,
				$entry->entity_type,
				$id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return false !== $wpdb->delete( Schema::entries_table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Queries one directory type.
	 *
	 * @param string              $type Entity type.
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{items:object[],total:int,pages:int}
	 */
	public function query( string $type, array $args = array() ): array {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => '',
				'category' => '',
				'district' => '',
				'featured' => '',
				'homepage_featured' => '',
				'orderby'  => 'updated_at',
				'order'    => 'DESC',
				'page'     => 1,
				'per_page' => 20,
			)
		);
		$cache_key = 'directory_' . $type . '_' . md5( wp_json_encode( $args ) );
		if ( ! is_admin() ) {
			$cached = wp_cache_get( $cache_key, 'adam_comunidade_archives' );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$where      = array( 'entity_type = %s' );
		$parameters = array( $type );
		if ( $args['search'] ) {
			$like         = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]      = '(name LIKE %s OR short_description LIKE %s OR full_description LIKE %s OR address LIKE %s OR district LIKE %s OR country LIKE %s OR category LIKE %s)';
			$parameters   = array_merge( $parameters, array_fill( 0, 7, $like ) );
		}
		foreach ( array( 'status', 'category', 'district' ) as $column ) {
			if ( '' !== (string) $args[ $column ] ) {
				$where[]      = $column . ' = %s';
				$parameters[] = (string) $args[ $column ];
			}
		}
		if ( '' !== (string) $args['featured'] ) {
			$where[]      = 'featured = %d';
			$parameters[] = absint( $args['featured'] );
		}
		if ( '' !== (string) $args['homepage_featured'] ) {
			$where[]      = 'homepage_featured = %d';
			$parameters[] = absint( $args['homepage_featured'] );
		}
		$allowed_orderby = array( 'name', 'created_at', 'updated_at', 'priority', 'district', 'featured' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'updated_at';
		$order           = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$per_page        = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$page            = max( 1, absint( $args['page'] ) );
		$offset          = ( $page - 1 ) * $per_page;
		$where_sql       = implode( ' AND ', $where );
		$table           = Schema::entries_table();
		$total           = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$parameters ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$list_args       = array_merge( $parameters, array( $per_page, $offset ) );
		$list_sql        = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order}, name ASC LIMIT %d OFFSET %d";
		$items           = $wpdb->get_results( $wpdb->prepare( $list_sql, ...$list_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
		if ( ! is_admin() ) {
			wp_cache_set( $cache_key, $result, 'adam_comunidade_archives', 300 );
		}
		return $result;
	}

	/**
	 * Returns lightweight published choices.
	 *
	 * @param string $type Entity type.
	 * @return object[]
	 */
	public function choices( string $type ): array {
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT id,name,slug FROM ' . Schema::entries_table() . ' WHERE entity_type = %s AND status = %s ORDER BY name', $type, 'published' );
		return $wpdb->get_results( $sql ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Checks a type-local unique name or slug.
	 */
	public function exists( string $type, string $column, string $value, int $exclude_id = 0 ): bool {
		global $wpdb;
		if ( ! in_array( $column, array( 'name', 'slug' ), true ) ) {
			return false;
		}
		$sql  = 'SELECT id FROM ' . Schema::entries_table() . ' WHERE entity_type = %s AND ' . $column . ' = %s';
		$args = array( $type, $value );
		if ( $exclude_id ) {
			$sql   .= ' AND id <> %d';
			$args[] = $exclude_id;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns type statistics.
	 *
	 * @return array<string,int>
	 */
	public function statistics( string $type ): array {
		global $wpdb;
		$stats = array( 'all' => 0, 'published' => 0, 'draft' => 0, 'hidden' => 0, 'featured' => 0 );
		$sql   = $wpdb->prepare( 'SELECT status,COUNT(*) total,SUM(featured) featured FROM ' . Schema::entries_table() . ' WHERE entity_type = %s GROUP BY status', $type );
		foreach ( $wpdb->get_results( $sql ) ?: array() as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$stats[ $row->status ] = (int) $row->total;
			$stats['all']         += (int) $row->total;
			$stats['featured']    += (int) $row->featured;
		}
		return $stats;
	}

	/**
	 * Returns distinct filter values.
	 *
	 * @return string[]
	 */
	public function distinct( string $type, string $column ): array {
		global $wpdb;
		if ( ! in_array( $column, array( 'district', 'category', 'country' ), true ) ) {
			return array();
		}
		$sql = $wpdb->prepare( 'SELECT DISTINCT ' . $column . ' FROM ' . Schema::entries_table() . ' WHERE entity_type = %s AND status = %s AND ' . $column . " <> '' ORDER BY " . $column, $type, 'published' );
		return array_map( 'strval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns ordered gallery records.
	 *
	 * @return object[]
	 */
	public function gallery( int $entry_id ): array {
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM ' . Schema::galleries_table() . ' WHERE entry_id = %d ORDER BY sort_order,id', $entry_id );
		return $wpdb->get_results( $sql ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Replaces an entry gallery.
	 *
	 * @param array<int,array{id:int,caption:string}> $items Gallery items.
	 */
	public function sync_gallery( int $entry_id, array $items ): void {
		global $wpdb;
		$wpdb->delete( Schema::galleries_table(), array( 'entry_id' => $entry_id ), array( '%d' ) );
		foreach ( $items as $order => $item ) {
			$attachment_id = absint( $item['id'] ?? 0 );
			if ( $attachment_id ) {
				$wpdb->insert(
					Schema::galleries_table(),
					array(
						'entry_id'     => $entry_id,
						'attachment_id'=> $attachment_id,
						'caption'      => sanitize_text_field( $item['caption'] ?? '' ),
						'sort_order'   => absint( $order ),
						'created_at'   => current_time( 'mysql', true ),
					)
				);
			}
		}
	}
}
