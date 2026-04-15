<?php
/**
 * Raw Employees Model.
 *
 * Handles all database interactions for the raw_employees table.
 * Validates Site_ID against dim_sites and Job_Family against dim_roles.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Raw_Employees_Model {

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
		// employee_id aliases.
		'employee_id' => 'employee_id',
		'emp_id'      => 'employee_id',
		'employee'    => 'employee_id',
		'id'          => 'employee_id',
		// site_id aliases → will be resolved to dim_site_id.
		'site_id'     => 'site_id',
		'location_id' => 'site_id',
		'loc'         => 'site_id',
		'site'        => 'site_id',
		'facility'    => 'site_id',
		// job_family aliases → will be resolved to dim_role_id.
		'job_family'  => 'job_family',
		'job_title'   => 'job_family',
		'title'       => 'job_family',
		'role'        => 'job_family',
		'position'    => 'job_family',
		// hire_date aliases.
		'hire_date'   => 'hire_date',
		'hire'        => 'hire_date',
		'start_date'  => 'hire_date',
		// status.
		'status'      => 'status',
		// termination_date.
		'termination_date' => 'termination_date',
		'term_date'        => 'termination_date',
		// manager_id.
		'manager_id'  => 'manager_id',
		'manager'     => 'manager_id',
		// tenure_months.
		'tenure_months' => 'tenure_months',
		'tenure'        => 'tenure_months',
	);

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'labor_intel_raw_employees';
	}

	/**
	 * Delete all raw_employees rows for a workspace.
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
	 * @param array $rows         Array of associative arrays.
	 * @return int Number of rows inserted.
	 */
	public function bulk_insert( $workspace_id, $rows ) {
		$inserted = 0;

		foreach ( $rows as $row ) {
			$result = $this->db->insert(
				$this->table,
				array(
					'workspace_id'     => $workspace_id,
					'employee_id'      => $row['employee_id'],
					'dim_site_id'      => $row['dim_site_id'],
					'dim_role_id'      => $row['dim_role_id'],
					'hire_date'        => $row['hire_date'],
					'status'           => $row['status'],
					'termination_date' => $row['termination_date'],
					'manager_id'       => $row['manager_id'],
					'tenure_months'    => $row['tenure_months'],
				),
				array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d' )
			);

			if ( $result ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Get paginated raw_employees for a workspace with joined dimension data.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param int    $per_page     Items per page.
	 * @param int    $page         Current page.
	 * @param string $orderby      Column to order by.
	 * @param string $order        ASC or DESC.
	 * @return array
	 */
	public function get_by_workspace( $workspace_id, $per_page = 25, $page = 1, $orderby = 'id', $order = 'ASC' ) {
		$allowed_orderby = array( 'id', 'employee_id', 'hire_date', 'status', 'tenure_months', 'created_at' );
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? 'e.' . $orderby : 'e.id';
		$order           = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order ) : 'ASC';
		$offset          = absint( ( $page - 1 ) * $per_page );
		$per_page        = absint( $per_page );

		$dim_sites = $this->db->prefix . 'labor_intel_dim_sites';
		$dim_roles = $this->db->prefix . 'labor_intel_dim_roles';

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT e.*, ds.location_id, ds.region, dr.job_title, dr.job_level
				FROM {$this->table} e
				LEFT JOIN {$dim_sites} ds ON e.dim_site_id = ds.id
				LEFT JOIN {$dim_roles} dr ON e.dim_role_id = dr.id
				WHERE e.workspace_id = %d
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d",
				$workspace_id,
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Count raw_employees rows for a workspace.
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
	 * Get a lookup map of employee_id => id for a workspace.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return array Associative array: employee_id (lowercase) => raw_employees.id
	 */
	public function get_lookup_map( $workspace_id ) {
		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, employee_id FROM {$this->table} WHERE workspace_id = %d",
				$workspace_id
			)
		);

		$map = array();
		foreach ( $results as $row ) {
			$map[ strtolower( $row->employee_id ) ] = (int) $row->id;
		}

		return $map;
	}

	/**
	 * Parse a CSV file and return validated rows.
	 *
	 * Requires lookup maps from dim_sites and dim_roles for FK validation.
	 *
	 * @param string $file_path    Absolute path to the CSV file.
	 * @param array  $site_map     Lookup map: location_id (lowercase) => dim_sites.id
	 * @param array  $role_map     Lookup map: job_title (lowercase) => dim_roles.id
	 * @return array|WP_Error Array with 'rows' and 'count' on success, WP_Error on failure.
	 */
	public function parse_csv( $file_path, $site_map, $role_map ) {
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

		if ( ! in_array( 'employee_id', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Employee_ID (or emp_id, employee, id).', 'labor-intel' );
		}
		if ( ! in_array( 'site_id', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Site_ID (or location_id, loc, site, facility).', 'labor-intel' );
		}
		if ( ! in_array( 'job_family', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Job_Family (or job_title, title, role, position).', 'labor-intel' );
		}
		if ( ! in_array( 'hire_date', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Hire_Date (or hire, start_date).', 'labor-intel' );
		}
		if ( ! in_array( 'status', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Status.', 'labor-intel' );
		}
		// if ( ! in_array( 'termination_date', $mapped_columns, true ) ) {
		// 	$missing_columns[] = __( 'Required column missing: Termination_Date (or term_date).', 'labor-intel' );
		// }
		if ( ! in_array( 'manager_id', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Manager_ID (or manager).', 'labor-intel' );
		}
		if ( ! in_array( 'tenure_months', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Tenure_Months (or tenure).', 'labor-intel' );
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
				'employee_id'      => '',
				'site_id_raw'      => '',
				'job_family_raw'   => '',
				'dim_site_id'      => null,
				'dim_role_id'      => null,
				'hire_date'        => null,
				'status'           => null,
				'termination_date' => null,
				'manager_id'       => null,
				'tenure_months'    => null,
			);

			foreach ( $mapped_headers as $index => $db_col ) {
				$value = isset( $csv_row[ $index ] ) ? trim( $csv_row[ $index ] ) : '';

				switch ( $db_col ) {
					case 'employee_id':
						$row['employee_id'] = sanitize_text_field( $value );
						break;

					case 'site_id':
						$row['site_id_raw'] = sanitize_text_field( $value );
						break;

					case 'job_family':
						$row['job_family_raw'] = sanitize_text_field( $value );
						break;

					case 'hire_date':
						if ( $value !== '' ) {
							// Validate MM/DD/YYYY format.
							$date = \DateTime::createFromFormat( 'n/j/Y', $value );
							if ( ! $date ) {
								$date = \DateTime::createFromFormat( 'm/d/Y', $value );
							}
							if ( $date ) {
								$row['hire_date'] = $date->format( 'Y-m-d' );
							} else {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: hire_date "%2$s" is not a valid date (expected MM/DD/YYYY).', 'labor-intel' ),
									$line_num,
									$value
								);
							}
						}
						break;

					case 'status':
						if ( $value !== '' ) {
							$row['status'] = sanitize_text_field( $value );
						}
						break;

					case 'termination_date':
						if ( $value !== '' ) {
							$date = \DateTime::createFromFormat( 'n/j/Y', $value );
							if ( ! $date ) {
								$date = \DateTime::createFromFormat( 'm/d/Y', $value );
							}
							if ( $date ) {
								$row['termination_date'] = $date->format( 'Y-m-d' );
							} else {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: termination_date "%2$s" is not a valid date (expected MM/DD/YYYY).', 'labor-intel' ),
									$line_num,
									$value
								);
							}
						}
						break;

					case 'manager_id':
						if ( $value !== '' ) {
							$row['manager_id'] = sanitize_text_field( $value );
						}
						break;

					case 'tenure_months':
						if ( $value !== '' ) {
							if ( ! is_numeric( $value ) ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: tenure_months "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['tenure_months'] = intval( $value );
							}
						}
						break;
				}
			}

			// Validate required fields.
			if ( empty( $row['employee_id'] ) ) {
				$errors[] = sprintf(
					__( 'Row %d: employee_id is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( empty( $row['site_id_raw'] ) ) {
				$errors[] = sprintf(
					__( 'Row %d: site_id is required.', 'labor-intel' ),
					$line_num
				);
			} else {
				// Lookup dim_site_id.
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

			if ( empty( $row['job_family_raw'] ) ) {
				$errors[] = sprintf(
					__( 'Row %d: job_family is required.', 'labor-intel' ),
					$line_num
				);
			} else {
				// Lookup dim_role_id.
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

			if ( null === $row['hire_date'] && empty( array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: hire_date" ) !== false;
			} ) ) ) {
				$errors[] = sprintf(
					__( 'Row %d: hire_date is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( null === $row['status'] || $row['status'] === '' ) {
				$errors[] = sprintf(
					__( 'Row %d: status is required.', 'labor-intel' ),
					$line_num
				);
			}

			// if ( null === $row['termination_date'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
			// 	return strpos( $e, "Row {$line_num}: termination_date" ) !== false;
			// } ) ) {
			// 	$errors[] = sprintf(
			// 		__( 'Row %d: termination_date is required.', 'labor-intel' ),
			// 		$line_num
			// 	);
			// }

			if ( null === $row['manager_id'] || $row['manager_id'] === '' ) {
				$errors[] = sprintf(
					__( 'Row %d: manager_id is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( null === $row['tenure_months'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: tenure_months" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					__( 'Row %d: tenure_months is required.', 'labor-intel' ),
					$line_num
				);
			}

			// Remove temporary raw fields before adding to rows.
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
