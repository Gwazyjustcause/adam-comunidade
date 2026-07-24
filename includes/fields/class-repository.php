<?php
/**
 * Fields data access layer.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates field, gallery, amenity, and team relationship queries.
 */
final class Repository {
	/**
	 * Finds one field by ID.
	 *
	 * @param int $id Field ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		$sql = $wpdb->prepare( 'SELECT * FROM ' . Schema::fields_table() . ' WHERE id = %d', $id );
		$row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $row ?: null;
	}

	/**
	 * Finds one field by slug.
	 *
	 * @param string $slug           Field slug.
	 * @param bool   $published_only Whether to require published status.
	 * @return object|null
	 */
	public function find_by_slug( string $slug, bool $published_only = true ): ?object {
		global $wpdb;

		$sql  = 'SELECT * FROM ' . Schema::fields_table() . ' WHERE slug = %s';
		$args = array( $slug );

		if ( $published_only ) {
			$sql   .= ' AND status = %s';
			$args[] = 'published';
		}

		$row = $wpdb->get_row( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $row ?: null;
	}

	/**
	 * Creates a field.
	 *
	 * @param array<string,mixed> $data Validated field data.
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

		$result = $wpdb->insert( Schema::fields_table(), $data );

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Updates a field.
	 *
	 * @param int                 $id   Field ID.
	 * @param array<string,mixed> $data Validated or status-only data.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$current            = $this->find( $id );
		$data['updated_at'] = current_time( 'mysql', true );
		$data['updated_by'] = get_current_user_id();

		if (
			$current
			&& isset( $data['status'] )
			&& 'published' !== $current->status
			&& 'published' === $data['status']
		) {
			$data['published_at'] = current_time( 'mysql', true );
		}

		return false !== $wpdb->update( Schema::fields_table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Deletes a field and its relationships while retaining Media Library files.
	 *
	 * @param int $id Field ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$wpdb->delete( Schema::field_teams_table(), array( 'field_id' => $id ), array( '%d' ) );
		$wpdb->delete( Schema::field_amenities_table(), array( 'field_id' => $id ), array( '%d' ) );
		$wpdb->delete( Schema::galleries_table(), array( 'field_id' => $id ), array( '%d' ) );

		return false !== $wpdb->delete( Schema::fields_table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Changes statuses for a collection of fields.
	 *
	 * @param int[]  $ids    Field IDs.
	 * @param string $status New status.
	 * @return void
	 */
	public function set_status( array $ids, string $status ): void {
		if ( ! isset( Options::statuses()[ $status ] ) ) {
			return;
		}

		foreach ( array_map( 'absint', $ids ) as $id ) {
			if ( $id ) {
				$this->update( $id, array( 'status' => $status ) );
			}
		}
	}

	/**
	 * Queries fields for admin and public directories.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{items:object[],total:int,pages:int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'        => '',
				'status'        => '',
				'district'      => '',
				'municipality'  => '',
				'playing_style' => '',
				'amenity_id'    => 0,
				'team_id'       => 0,
				'orderby'       => 'updated_at',
				'order'         => 'DESC',
				'page'          => 1,
				'per_page'      => 20,
			)
		);

		$fields_table      = Schema::fields_table();
		$relations_table   = Schema::field_teams_table();
		$teams_table       = $wpdb->prefix . 'adam_teams';
		$amenities_table   = Schema::field_amenities_table();
		$amenity_definitions = Schema::amenities_table();
		$where             = array( '1=1' );
		$parameters        = array();

		if ( $args['search'] ) {
			$like         = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]      = '(f.name LIKE %s OR f.short_description LIKE %s OR f.address LIKE %s'
				. ' OR f.district LIKE %s OR f.municipality LIKE %s OR f.playing_styles LIKE %s'
				. " OR EXISTS (SELECT 1 FROM {$amenities_table} sfa"
				. " INNER JOIN {$amenity_definitions} sa ON sa.id = sfa.amenity_id"
				. " WHERE sfa.field_id = f.id AND sa.status = 'active' AND sa.label LIKE %s))";
			$parameters   = array_merge( $parameters, array_fill( 0, 7, $like ) );
		}

		foreach ( array( 'status', 'district', 'municipality' ) as $column ) {
			if ( $args[ $column ] ) {
				$where[]      = 'f.' . $column . ' = %s';
				$parameters[] = (string) $args[ $column ];
			}
		}

		if ( $args['playing_style'] ) {
			$where[]      = 'f.playing_styles LIKE %s';
			$parameters[] = '%"' . $wpdb->esc_like( (string) $args['playing_style'] ) . '"%';
		}

		if ( $args['amenity_id'] ) {
			$where[]      = "EXISTS (SELECT 1 FROM {$amenities_table} fa"
				. ' WHERE fa.field_id = f.id AND fa.amenity_id = %d)';
			$parameters[] = absint( $args['amenity_id'] );
		}

		if ( $args['team_id'] ) {
			$where[]      = "EXISTS (SELECT 1 FROM {$relations_table} fr"
				. ' WHERE fr.field_id = f.id AND fr.team_id = %d)';
			$parameters[] = absint( $args['team_id'] );
		}

		$allowed_orderby = array(
			'name',
			'district',
			'municipality',
			'status',
			'max_players',
			'updated_at',
			'created_at',
		);
		$orderby = in_array( $args['orderby'], $allowed_orderby, true )
			? $args['orderby']
			: 'updated_at';
		$order     = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$per_page  = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$page      = max( 1, absint( $args['page'] ) );
		$offset    = ( $page - 1 ) * $per_page;
		$where_sql = implode( ' AND ', $where );

		$team_columns = "(SELECT t.name FROM {$relations_table} r"
			. " INNER JOIN {$teams_table} t ON t.id = r.team_id"
			. " WHERE r.field_id = f.id AND t.status = 'published' LIMIT 1) AS associated_team_name,"
			. "(SELECT t.slug FROM {$relations_table} r"
			. " INNER JOIN {$teams_table} t ON t.id = r.team_id"
			. " WHERE r.field_id = f.id AND t.status = 'published' LIMIT 1) AS associated_team_slug";
		$count_sql = "SELECT COUNT(*) FROM {$fields_table} f WHERE {$where_sql}";
		$list_sql  = "SELECT f.*, {$team_columns} FROM {$fields_table} f"
			. " WHERE {$where_sql} ORDER BY f.{$orderby} {$order} LIMIT %d OFFSET %d";

		if ( $parameters ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$parameters ) );
		} else {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$list_parameters   = $parameters;
		$list_parameters[] = $per_page;
		$list_parameters[] = $offset;
		$prepared_list     = $wpdb->prepare( $list_sql, ...$list_parameters );
		$items             = $wpdb->get_results( $prepared_list ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Returns distinct public or admin location values.
	 *
	 * @param string $column Allowed column.
	 * @param string $status Optional status.
	 * @return string[]
	 */
	public function distinct( string $column, string $status = '' ): array {
		global $wpdb;

		if ( ! in_array( $column, array( 'district', 'municipality' ), true ) ) {
			return array();
		}

		$sql = 'SELECT DISTINCT ' . $column . ' FROM ' . Schema::fields_table()
			. ' WHERE ' . $column . " <> ''";

		if ( $status ) {
			$sql = $wpdb->prepare( $sql . ' AND status = %s', $status );
		}

		$sql .= ' ORDER BY ' . $column . ' ASC';

		return array_map( 'strval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns lightweight field choices for relationship editors.
	 *
	 * @param string $status Optional status constraint.
	 * @return object[]
	 */
	public function choices( string $status = 'published' ): array {
		global $wpdb;

		$sql = 'SELECT id, name, slug FROM ' . Schema::fields_table();
		if ( $status ) {
			$sql = $wpdb->prepare( $sql . ' WHERE status = %s', $status );
		}
		$sql .= ' ORDER BY name ASC';

		return $wpdb->get_results( $sql ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Checks unique field values.
	 *
	 * @param string $column     name or slug.
	 * @param string $value      Candidate value.
	 * @param int    $exclude_id Existing field ID.
	 * @return bool
	 */
	public function exists( string $column, string $value, int $exclude_id = 0 ): bool {
		global $wpdb;

		if ( ! in_array( $column, array( 'name', 'slug' ), true ) ) {
			return false;
		}

		$sql  = 'SELECT id FROM ' . Schema::fields_table() . ' WHERE ' . $column . ' = %s';
		$args = array( $value );

		if ( $exclude_id ) {
			$sql   .= ' AND id <> %d';
			$args[] = $exclude_id;
		}

		return (bool) $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns grouped status counts and average recommended capacity.
	 *
	 * @return array<string,int|float>
	 */
	public function statistics(): array {
		global $wpdb;

		$counts = array(
			'all'              => 0,
			'published'        => 0,
			'draft'            => 0,
			'hidden'           => 0,
			'average_capacity' => 0,
		);
		$sql = 'SELECT status, COUNT(*) total FROM ' . Schema::fields_table() . ' GROUP BY status';
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $rows as $row ) {
			$counts[ $row->status ] = (int) $row->total;
			$counts['all']         += (int) $row->total;
		}

		$average_sql = 'SELECT AVG(NULLIF(recommended_players, 0)) FROM ' . Schema::fields_table();
		$counts['average_capacity'] = round(
			(float) $wpdb->get_var( $average_sql ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			1
		);

		return $counts;
	}

	/**
	 * Returns gallery rows in display order.
	 *
	 * @param int $field_id Field ID.
	 * @return object[]
	 */
	public function gallery( int $field_id ): array {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT * FROM ' . Schema::galleries_table()
				. ' WHERE field_id = %d ORDER BY sort_order ASC, id ASC',
			$field_id
		);

		return $wpdb->get_results( $sql ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Replaces gallery rows with an ordered, captioned set.
	 *
	 * @param int                            $field_id Field ID.
	 * @param array<int,array{id:int,caption:string}> $items Gallery items.
	 * @return void
	 */
	public function sync_gallery( int $field_id, array $items ): void {
		global $wpdb;

		$wpdb->delete( Schema::galleries_table(), array( 'field_id' => $field_id ), array( '%d' ) );
		$order = 0;

		foreach ( $items as $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}

			$wpdb->insert(
				Schema::galleries_table(),
				array(
					'field_id'     => $field_id,
					'attachment_id'=> absint( $item['id'] ),
					'caption'      => sanitize_text_field( $item['caption'] ?? '' ),
					'sort_order'   => $order,
					'created_at'   => current_time( 'mysql', true ),
				)
			);
			++$order;
		}
	}

	/**
	 * Returns amenity IDs selected for a field.
	 *
	 * @param int $field_id Field ID.
	 * @return int[]
	 */
	public function amenity_ids( int $field_id ): array {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT amenity_id FROM ' . Schema::field_amenities_table() . ' WHERE field_id = %d',
			$field_id
		);

		return array_map( 'intval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns active amenities assigned to a field.
	 *
	 * @param int $field_id Field ID.
	 * @return object[]
	 */
	public function amenities( int $field_id ): array {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT a.* FROM ' . Schema::amenities_table() . ' a'
				. ' INNER JOIN ' . Schema::field_amenities_table() . ' fa ON fa.amenity_id = a.id'
				. " WHERE fa.field_id = %d AND a.status = 'active'"
				. ' ORDER BY a.sort_order ASC, a.label ASC',
			$field_id
		);

		return $wpdb->get_results( $sql ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Replaces field amenity selections.
	 *
	 * @param int   $field_id   Field ID.
	 * @param int[] $amenity_ids Amenity IDs.
	 * @return void
	 */
	public function sync_amenities( int $field_id, array $amenity_ids ): void {
		global $wpdb;

		$wpdb->delete( Schema::field_amenities_table(), array( 'field_id' => $field_id ), array( '%d' ) );

		foreach ( array_unique( array_map( 'absint', $amenity_ids ) ) as $amenity_id ) {
			if ( ! $amenity_id ) {
				continue;
			}

			$wpdb->insert(
				Schema::field_amenities_table(),
				array(
					'field_id'   => $field_id,
					'amenity_id' => $amenity_id,
					'created_at' => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Returns the associated team ID.
	 *
	 * @param int $field_id Field ID.
	 * @return int
	 */
	public function associated_team_id( int $field_id ): int {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT team_id FROM ' . Schema::field_teams_table() . ' WHERE field_id = %d LIMIT 1',
			$field_id
		);

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Replaces the field's optional owning-team relationship.
	 *
	 * @param int $field_id Field ID.
	 * @param int $team_id  Published team ID or zero.
	 * @return void
	 */
	public function sync_team( int $field_id, int $team_id ): void {
		global $wpdb;

		$wpdb->delete( Schema::field_teams_table(), array( 'field_id' => $field_id ), array( '%d' ) );

		if ( $team_id ) {
			$wpdb->insert(
				Schema::field_teams_table(),
				array(
					'field_id'   => $field_id,
					'team_id'    => $team_id,
					'created_at' => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s' )
			);
		}
	}
}
