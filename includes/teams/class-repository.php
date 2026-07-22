<?php
/**
 * Teams data access layer.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates all team-table queries.
 */
final class Repository {
	/**
	 * Gets one team by ID.
	 *
	 * @param int $id Team ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		$sql = $wpdb->prepare( 'SELECT * FROM ' . Schema::teams_table() . ' WHERE id = %d', $id );
		$row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.

		return $row ?: null;
	}

	/**
	 * Gets one team by slug.
	 *
	 * @param string $slug          Team slug.
	 * @param bool   $published_only Limit to published teams.
	 * @return object|null
	 */
	public function find_by_slug( string $slug, bool $published_only = true ): ?object {
		global $wpdb;

		$sql  = 'SELECT * FROM ' . Schema::teams_table() . ' WHERE slug = %s';
		$args = array( $slug );

		if ( $published_only ) {
			$sql   .= ' AND status = %s';
			$args[] = 'published';
		}

		$row = $wpdb->get_row( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $row ?: null;
	}

	/**
	 * Inserts a team.
	 *
	 * @param array<string,mixed> $data Validated team data.
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

		$result = $wpdb->insert( Schema::teams_table(), $data );

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Updates a team.
	 *
	 * @param int                 $id   Team ID.
	 * @param array<string,mixed> $data Validated team data.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$current           = $this->find( $id );
		$data['updated_at'] = current_time( 'mysql', true );
		$data['updated_by'] = get_current_user_id();

		if ( $current && 'published' !== $current->status && 'published' === $data['status'] ) {
			$data['published_at'] = current_time( 'mysql', true );
		}

		return false !== $wpdb->update( Schema::teams_table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Deletes a team and its relationships.
	 *
	 * Media attachments are deliberately retained in the Media Library.
	 *
	 * @param int $id Team ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$wpdb->delete( Schema::team_fields_table(), array( 'team_id' => $id ), array( '%d' ) );

		return false !== $wpdb->delete( Schema::teams_table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Changes one or more team statuses.
	 *
	 * @param int[]  $ids    Team IDs.
	 * @param string $status New status.
	 * @return void
	 */
	public function set_status( array $ids, string $status ): void {
		if ( ! isset( Options::statuses()[ $status ] ) ) {
			return;
		}

		foreach ( array_map( 'absint', $ids ) as $id ) {
			if ( $id > 0 ) {
				$this->update( $id, array( 'status' => $status ) );
			}
		}
	}

	/**
	 * Queries teams for admin or public lists.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{items:object[],total:int,pages:int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'      => '',
				'status'      => '',
				'district'    => '',
				'municipality' => '',
				'playing_style' => '',
				'recruitment'  => '',
				'orderby'      => 'updated_at',
				'order'        => 'DESC',
				'page'         => 1,
				'per_page'     => 20,
			)
		);

		$where      = array( '1=1' );
		$parameters = array();

		if ( $args['search'] ) {
			$like         = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]      = '(name LIKE %s OR short_name LIKE %s OR district LIKE %s OR municipality LIKE %s)';
			$parameters[] = $like;
			$parameters[] = $like;
			$parameters[] = $like;
			$parameters[] = $like;
		}

		foreach ( array( 'status', 'district', 'municipality' ) as $field ) {
			if ( $args[ $field ] ) {
				$where[]      = $field . ' = %s';
				$parameters[] = (string) $args[ $field ];
			}
		}

		if ( $args['recruitment'] ) {
			$where[]      = 'recruitment_status = %s';
			$parameters[] = (string) $args['recruitment'];
		}

		if ( $args['playing_style'] ) {
			$where[]      = 'playing_styles LIKE %s';
			$parameters[] = '%"' . $wpdb->esc_like( (string) $args['playing_style'] ) . '"%';
		}

		$allowed_orderby = array( 'name', 'district', 'municipality', 'status', 'members', 'updated_at', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'updated_at';
		$order           = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$per_page        = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$page            = max( 1, absint( $args['page'] ) );
		$offset          = ( $page - 1 ) * $per_page;
		$where_sql       = implode( ' AND ', $where );
		$table           = Schema::teams_table();

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		if ( empty( $parameters ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built from fixed clauses and an internal table name.
			$total = (int) $wpdb->get_var( $count_sql );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic clauses use placeholders prepared here.
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$parameters ) );
		}

		$list_parameters   = $parameters;
		$list_parameters[] = $per_page;
		$list_parameters[] = $offset;
		$prepared_list_sql = $wpdb->prepare( $list_sql, ...$list_parameters );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$items = $wpdb->get_results( $prepared_list_sql );

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Returns distinct values for a filter.
	 *
	 * @param string $column Allowed column name.
	 * @param string $status Optional status constraint.
	 * @return string[]
	 */
	public function distinct( string $column, string $status = '' ): array {
		global $wpdb;

		if ( ! in_array( $column, array( 'district', 'municipality' ), true ) ) {
			return array();
		}

		$sql = 'SELECT DISTINCT ' . $column . ' FROM ' . Schema::teams_table()
			. ' WHERE ' . $column . " <> ''";

		if ( $status ) {
			$sql = $wpdb->prepare( $sql . ' AND status = %s', $status );
		}

		$sql .= ' ORDER BY ' . $column . ' ASC';

		return array_map( 'strval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns grouped status counts.
	 *
	 * @return array<string,int>
	 */
	public function status_counts(): array {
		global $wpdb;

		$sql = 'SELECT status, COUNT(*) AS total FROM ' . Schema::teams_table() . ' GROUP BY status';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query contains no external input.
		$rows   = $wpdb->get_results( $sql );
		$counts = array( 'all' => 0, 'published' => 0, 'draft' => 0, 'hidden' => 0 );

		foreach ( $rows as $row ) {
			$counts[ $row->status ] = (int) $row->total;
			$counts['all']         += (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Checks whether a name or slug is already used.
	 *
	 * @param string $column name or slug.
	 * @param string $value  Candidate value.
	 * @param int    $exclude_id Team ID to exclude.
	 * @return bool
	 */
	public function exists( string $column, string $value, int $exclude_id = 0 ): bool {
		global $wpdb;

		if ( ! in_array( $column, array( 'name', 'slug' ), true ) ) {
			return false;
		}

		$sql  = 'SELECT id FROM ' . Schema::teams_table() . ' WHERE ' . $column . ' = %s';
		$args = array( $value );

		if ( $exclude_id > 0 ) {
			$sql   .= ' AND id <> %d';
			$args[] = $exclude_id;
		}

		return (bool) $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns related field IDs, ready for Phase 3.
	 *
	 * @param int $team_id Team ID.
	 * @return int[]
	 */
	public function field_ids( int $team_id ): array {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT field_id FROM ' . Schema::team_fields_table() . ' WHERE team_id = %d',
			$team_id
		);

		return array_map( 'intval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Replaces field relationships for a team.
	 *
	 * Phase 3 can call this method directly when its selector is enabled; the
	 * relationship schema and data contract are already stable.
	 *
	 * @param int   $team_id  Team ID.
	 * @param int[] $field_ids Field IDs.
	 * @return void
	 */
	public function sync_fields( int $team_id, array $field_ids ): void {
		global $wpdb;

		$team_id   = absint( $team_id );
		$field_ids = array_unique( array_filter( array_map( 'absint', $field_ids ) ) );

		if ( ! $team_id ) {
			return;
		}

		$wpdb->delete( Schema::team_fields_table(), array( 'team_id' => $team_id ), array( '%d' ) );

		foreach ( $field_ids as $field_id ) {
			$wpdb->insert(
				Schema::team_fields_table(),
				array(
					'team_id'    => $team_id,
					'field_id'   => $field_id,
					'created_at' => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s' )
			);
		}
	}
}
