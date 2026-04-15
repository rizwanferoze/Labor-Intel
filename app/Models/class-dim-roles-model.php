<?php
/**
 * Dim Roles Model.
 *
 * Handles all database interactions for the dim_roles table.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Dim_Roles_Model {

	/**
	 * @var wpdb
	 */
	private $db;

	/**
	 * @var string
	 */
	private $table;

	/**
	 * Column header aliases that map to our DB column names.
	 *
	 * @var array
	 */
	private static $column_map = array(
		// job_title aliases.
		'job_family' => 'job_title',
		'job_title'  => 'job_title',
		'title'      => 'job_title',
		'role'       => 'job_title',
		'position'   => 'job_title',
		// job_level.
		'job_level'  => 'job_level',
		'level'      => 'job_level',
		// base_rate_target aliases.
		'base_rate_target' => 'base_rate_target',
		'base_rate'        => 'base_rate_target',
		'rate_target'      => 'base_rate_target',
		// ot_benchmark aliases.
		'ot_benchmark'     => 'ot_benchmark',
		'ot_bench'         => 'ot_benchmark',
		'overtime_benchmark' => 'ot_benchmark',
	);

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'labor_intel_dim_roles';
	}

	/**
	 * Delete all dim_roles rows for a workspace.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return int|false Number of rows deleted or false on error.
	 */
	public function delete_by_workspace( $workspace_id ) {
		return $this->db->delete(
			$this->table,
			array( 'workspace_id' => $workspace_id ),
			array( '%d' )
		);
	}

	/**
	 * Bulk insert rows for a workspace.
	 *
	 * @param int   $workspace_id Workspace ID.
	 * @param array $rows         Array of associative arrays with keys: job_title, job_level, base_rate_target, ot_benchmark.
	 * @return int Number of rows inserted.
	 */
	public function bulk_insert( $workspace_id, $rows ) {
		$inserted = 0;

		foreach ( $rows as $row ) {
			$result = $this->db->insert(
				$this->table,
				array(
					'workspace_id'    => $workspace_id,
					'job_title'       => $row['job_title'],
					'job_level'       => $row['job_level'],
					'base_rate_target' => $row['base_rate_target'],
					'ot_benchmark'    => $row['ot_benchmark'],
				),
				array( '%d', '%s', '%s', '%f', '%f' )
			);

			if ( $result ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Get paginated dim_roles for a workspace.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param int    $per_page     Items per page.
	 * @param int    $page         Current page.
	 * @param string $orderby      Column to order by.
	 * @param string $order        ASC or DESC.
	 * @return array
	 */
	public function get_by_workspace( $workspace_id, $per_page = 25, $page = 1, $orderby = 'id', $order = 'ASC' ) {
		$allowed_orderby = array( 'id', 'job_title', 'job_level', 'base_rate_target', 'ot_benchmark', 'created_at' );
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'id';
		$order           = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order ) : 'ASC';
		$offset          = absint( ( $page - 1 ) * $per_page );
		$per_page        = absint( $per_page );

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE workspace_id = %d ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$workspace_id,
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Count dim_roles rows for a workspace.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return int
	 */
	public function count_by_workspace( $workspace_id ) {
		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE workspace_id = %d",
				$workspace_id
			)
		);
	}

	/**
	 * Get a lookup map of job_title => id for a workspace.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return array Associative array: job_title (lowercase) => dim_roles.id
	 */
	public function get_lookup_map( $workspace_id ) {
		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, job_title FROM {$this->table} WHERE workspace_id = %d",
				$workspace_id
			)
		);

		$map = array();
		foreach ( $results as $row ) {
			$map[ strtolower( $row->job_title ) ] = (int) $row->id;
		}

		return $map;
	}

	/**
	 * Parse a CSV file and return validated rows.
	 *
	 * @param string $file_path Absolute path to the CSV file.
	 * @return array|WP_Error Array with 'rows' and 'count' on success, WP_Error on failure.
	 */
	public function parse_csv( $file_path ) {
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'file_read_error', __( 'Could not read the uploaded file.', 'labor-intel' ) );
		}

		// Read header row.
		$raw_headers = fgetcsv( $handle );
		if ( ! $raw_headers ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'empty_file', __( 'The file appears to be empty.', 'labor-intel' ) );
		}

		// Normalize headers and map to DB columns.
		$mapped_headers = array();
		foreach ( $raw_headers as $index => $header ) {
			$normalized = strtolower( trim( $header ) );
			// Remove BOM if present.
			$normalized = preg_replace( '/^\x{FEFF}/u', '', $normalized );
			if ( isset( self::$column_map[ $normalized ] ) ) {
				$mapped_headers[ $index ] = self::$column_map[ $normalized ];
			}
		}

		// Validate required columns exist.
		$mapped_columns  = array_values( $mapped_headers );
		$missing_columns = array();

		if ( ! in_array( 'job_title', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: job_title (or Job_Family, title, role, position).', 'labor-intel' );
		}
		if ( ! in_array( 'job_level', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Job_Level.', 'labor-intel' );
		}
		if ( ! in_array( 'base_rate_target', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Base_Rate_Target (or base_rate, rate_target).', 'labor-intel' );
		}
		if ( ! in_array( 'ot_benchmark', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: OT_Benchmark (or ot_bench, overtime_benchmark).', 'labor-intel' );
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

		// Parse data rows.
		$rows     = array();
		$line_num = 1;
		$errors   = array();

		while ( ( $csv_row = fgetcsv( $handle ) ) !== false ) {
			$line_num++;

			// Skip completely empty rows.
			if ( count( $csv_row ) === 1 && trim( $csv_row[0] ) === '' ) {
				continue;
			}

			$row = array(
				'job_title'        => '',
				'job_level'        => '',
				'base_rate_target' => null,
				'ot_benchmark'     => null,
			);

			foreach ( $mapped_headers as $index => $db_col ) {
				$value = isset( $csv_row[ $index ] ) ? trim( $csv_row[ $index ] ) : '';

				switch ( $db_col ) {
					case 'job_title':
						$row['job_title'] = sanitize_text_field( $value );
						break;

					case 'job_level':
						$row['job_level'] = sanitize_text_field( $value );
						break;

					case 'base_rate_target':
						if ( $value !== '' ) {
							// Strip $ sign, commas, and spaces.
							$cleaned = str_replace( array( '$', ',', ' ' ), '', trim( $value ) );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: base_rate_target "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['base_rate_target'] = floatval( $cleaned );
							}
						}
						break;

					case 'ot_benchmark':
						if ( $value !== '' ) {
							// Strip % sign and spaces.
							$cleaned = str_replace( array( '%', ' ' ), '', trim( $value ) );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: ot_benchmark "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['ot_benchmark'] = floatval( $cleaned );
							}
						}
						break;
				}
			}

			// Validate required fields.
			if ( empty( $row['job_title'] ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: job_title is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( empty( $row['job_level'] ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: job_level is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( null === $row['base_rate_target'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: base_rate_target" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: base_rate_target is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( null === $row['ot_benchmark'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: ot_benchmark" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: ot_benchmark is required.', 'labor-intel' ),
					$line_num
				);
			}

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
