<?php
/**
 * Role Site Stats Model.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Role_Site_Stats_Model {

	private $db;
	private $table;

	private static $column_map = array(
		'site_id'             => 'site_id',
		'location_id'         => 'site_id',
		'loc'                 => 'site_id',
		'site'                => 'site_id',
		'facility'            => 'site_id',
		'job_family'          => 'job_family',
		'job_title'           => 'job_family',
		'title'               => 'job_family',
		'role'                => 'job_family',
		'position'            => 'job_family',
		'newhirerate_avg'     => 'newhire_rate_avg',
		'newhire_rate_avg'    => 'newhire_rate_avg',
		'new_hire_rate_avg'   => 'newhire_rate_avg',
		'new_hire_rate'       => 'newhire_rate_avg',
		'incumbentrate_avg'   => 'incumbent_rate_avg',
		'incumbent_rate_avg'  => 'incumbent_rate_avg',
		'incumbent_rate'      => 'incumbent_rate_avg',
		'rolerate_avg'        => 'rolerate_avg',
		'role_rate_avg'       => 'rolerate_avg',
		'rolerate'            => 'rolerate_avg',
		'role_rate'           => 'rolerate_avg',
		'roleotbenchmark'     => 'role_ot_benchmark',
		'role_ot_benchmark'   => 'role_ot_benchmark',
		'ot_benchmark'        => 'role_ot_benchmark',
		'live_newhirerate'    => 'live_newhire_rate',
		'live_newhire_rate'   => 'live_newhire_rate',
		'live_incumbentrate'  => 'live_incumbent_rate',
		'live_incumbent_rate' => 'live_incumbent_rate',
		'live_rolerate'       => 'live_role_rate',
		'live_role_rate'      => 'live_role_rate',
	);

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'labor_intel_role_site_stats';
	}

	public function delete_by_workspace( $workspace_id ) {
		return $this->db->delete(
			$this->table,
			array( 'workspace_id' => $workspace_id ),
			array( '%d' )
		);
	}

	public function bulk_insert( $workspace_id, $rows ) {
		$inserted = 0;
		foreach ( $rows as $row ) {
			$result = $this->db->insert(
				$this->table,
				array(
					'workspace_id'        => $workspace_id,
					'dim_site_id'         => $row['dim_site_id'],
					'dim_role_id'         => $row['dim_role_id'],
					'newhire_rate_avg'    => $row['newhire_rate_avg'],
					'incumbent_rate_avg'  => $row['incumbent_rate_avg'],
					'rolerate_avg'        => $row['rolerate_avg'],
					'role_ot_benchmark'   => $row['role_ot_benchmark'],
					'live_newhire_rate'   => $row['live_newhire_rate'],
					'live_incumbent_rate' => $row['live_incumbent_rate'],
					'live_role_rate'      => $row['live_role_rate'],
				),
				array( '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f' )
			);
			if ( $result ) {
				$inserted++;
			}
		}
		return $inserted;
	}

	public function get_by_workspace( $workspace_id, $per_page = 25, $page = 1, $orderby = 'id', $order = 'ASC' ) {
		$allowed_orderby = array( 'id', 'newhire_rate_avg', 'incumbent_rate_avg', 'rolerate_avg', 'created_at' );
		$order           = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order ) : 'ASC';
		$offset          = absint( ( $page - 1 ) * $per_page );
		$per_page        = absint( $per_page );
		$dim_sites       = $this->db->prefix . 'labor_intel_dim_sites';
		$dim_roles       = $this->db->prefix . 'labor_intel_dim_roles';

		if ( in_array( $orderby, $allowed_orderby, true ) ) {
			$order_clause = "rss.{$orderby} {$order}";
		} elseif ( $orderby === 'site_id' ) {
			$order_clause = "ds.location_id {$order}";
		} elseif ( $orderby === 'job_family' ) {
			$order_clause = "dr.job_title {$order}";
		} else {
			$order_clause = "rss.id {$order}";
		}

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT rss.*, ds.location_id, dr.job_title
				FROM {$this->table} rss
				LEFT JOIN {$dim_sites} ds ON rss.dim_site_id = ds.id
				LEFT JOIN {$dim_roles} dr ON rss.dim_role_id = dr.id
				WHERE rss.workspace_id = %d
				ORDER BY {$order_clause}
				LIMIT %d OFFSET %d",
				$workspace_id,
				$per_page,
				$offset
			)
		);
	}

	public function count_by_workspace( $workspace_id ) {
		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE workspace_id = %d",
				$workspace_id
			)
		);
	}

	public function parse_csv( $file_path, $site_map, $role_map ) {
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'file_read_error', __( 'Could not read the uploaded file.', 'labor-intel' ) );
		}

		$raw_headers = fgetcsv( $handle );
		if ( ! $raw_headers ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'empty_file', __( 'The file appears to be empty.', 'labor-intel' ) );
		}

		$mapped_headers = array();
		foreach ( $raw_headers as $index => $header ) {
			$normalized = strtolower( trim( $header ) );
			$normalized = preg_replace( '/^\x{FEFF}/u', '', $normalized );
			if ( isset( self::$column_map[ $normalized ] ) ) {
				$mapped_headers[ $index ] = self::$column_map[ $normalized ];
			}
		}

		$mapped_columns  = array_values( $mapped_headers );
		$missing_columns = array();

		if ( ! in_array( 'site_id', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Site_ID (or location_id, loc, site, facility).', 'labor-intel' );
		}
		if ( ! in_array( 'job_family', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Job_Family (or job_title, title, role, position).', 'labor-intel' );
		}

		if ( ! empty( $missing_columns ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error(
				'missing_column',
				sprintf(
					/* translators: %d: number of missing columns */
					__( 'File structure error: %d required column(s) missing.', 'labor-intel' ),
					count( $missing_columns )
				),
				array( 'validation_errors' => $missing_columns )
			);
		}

		$rows     = array();
		$line_num = 1;
		$errors   = array();

		while ( ( $csv_row = fgetcsv( $handle ) ) !== false ) {
			$line_num++;

			if ( count( $csv_row ) === 1 && trim( $csv_row[0] ) === '' ) {
				continue;
			}

			$row = array(
				'site_id_raw'         => '',
				'job_family_raw'      => '',
				'dim_site_id'         => null,
				'dim_role_id'         => null,
				'newhire_rate_avg'    => null,
				'incumbent_rate_avg'  => null,
				'rolerate_avg'        => null,
				'role_ot_benchmark'   => null,
				'live_newhire_rate'   => null,
				'live_incumbent_rate' => null,
				'live_role_rate'      => null,
			);

			foreach ( $mapped_headers as $index => $db_col ) {
				$value = isset( $csv_row[ $index ] ) ? trim( $csv_row[ $index ] ) : '';

				switch ( $db_col ) {
					case 'site_id':
						$row['site_id_raw'] = sanitize_text_field( $value );
						break;

					case 'job_family':
						$row['job_family_raw'] = sanitize_text_field( $value );
						break;

					case 'newhire_rate_avg':
					case 'incumbent_rate_avg':
					case 'rolerate_avg':
					case 'role_ot_benchmark':
					case 'live_newhire_rate':
					case 'live_incumbent_rate':
					case 'live_role_rate':
						if ( $value !== '' ) {
							$cleaned = str_replace( array( '$', ',', ' ' ), '', $value );
							if ( $cleaned === '' ) {
								break;
							}
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: column name, %3$s: original value */
									__( 'Row %1$d: %2$s "%3$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$db_col,
									$value
								);
							} else {
								$row[ $db_col ] = floatval( $cleaned );
							}
						}
						break;
				}
			}

			// Validate required: Site_ID.
			if ( empty( $row['site_id_raw'] ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: Site_ID is required.', 'labor-intel' ),
					$line_num
				);
			} else {
				$site_key = strtolower( $row['site_id_raw'] );
				if ( isset( $site_map[ $site_key ] ) ) {
					$row['dim_site_id'] = $site_map[ $site_key ];
				} else {
					$errors[] = sprintf(
						/* translators: %1$d: line number, %2$s: site id value */
						__( 'Row %1$d: Site_ID "%2$s" not found in Dim Sites. Please upload Dim Sites first.', 'labor-intel' ),
						$line_num,
						$row['site_id_raw']
					);
				}
			}

			// Validate required: Job_Family.
			if ( empty( $row['job_family_raw'] ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: Job_Family is required.', 'labor-intel' ),
					$line_num
				);
			} else {
				$role_key = strtolower( $row['job_family_raw'] );
				if ( isset( $role_map[ $role_key ] ) ) {
					$row['dim_role_id'] = $role_map[ $role_key ];
				} else {
					$errors[] = sprintf(
						/* translators: %1$d: line number, %2$s: job family value */
						__( 'Row %1$d: Job_Family "%2$s" not found in Dim Roles. Please upload Dim Roles first.', 'labor-intel' ),
						$line_num,
						$row['job_family_raw']
					);
				}
			}

			unset( $row['site_id_raw'], $row['job_family_raw'] );
			$rows[] = $row;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// If any validation errors, reject the entire upload.
		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'validation_failed',
				sprintf(
					/* translators: %d: number of errors */
					__( 'Validation failed with %d error(s). See popup window for details.', 'labor-intel' ),
					count( $errors )
				),
				array( 'validation_errors' => $errors )
			);
		}

		if ( empty( $rows ) ) {
			return new WP_Error( 'no_data', __( 'No data rows found in the file.', 'labor-intel' ) );
		}

		return array(
			'rows'  => $rows,
			'count' => count( $rows ),
		);
	}
}
