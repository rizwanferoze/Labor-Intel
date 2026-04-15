<?php
/**
 * Raw Comp Model.
 *
 * Handles all database interactions for the raw_comp table.
 * Validates Employee_ID against raw_employees.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Raw_Comp_Model {

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
		// employee_id aliases → resolved to raw_employee_id.
		'employee_id' => 'employee_id',
		'emp_id'      => 'employee_id',
		'employee'    => 'employee_id',
		'id'          => 'employee_id',
		// pay_rate aliases.
		'base_rate'   => 'pay_rate',
		'pay_rate'    => 'pay_rate',
		'rate'        => 'pay_rate',
		'hourly_rate' => 'pay_rate',
		// pay_type.
		'pay_type'    => 'pay_type',
		// incentive_pay.
		'incentive_pay' => 'incentive_pay',
		'incentive'     => 'incentive_pay',
		// shift_diff.
		'shift_diff'    => 'shift_diff',
		'shift'         => 'shift_diff',
		// bonus_ytd.
		'bonus_ytd'     => 'bonus_ytd',
		'bonus'         => 'bonus_ytd',
	);

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'labor_intel_raw_comp';
	}

	/**
	 * Delete all raw_comp rows for a workspace.
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
					'raw_employee_id'  => $row['raw_employee_id'],
					'pay_rate'         => $row['pay_rate'],
					'pay_type'         => $row['pay_type'],
					'incentive_pay'    => $row['incentive_pay'],
					'shift_diff'       => $row['shift_diff'],
					'bonus_ytd'        => $row['bonus_ytd'],
				),
				array( '%d', '%d', '%f', '%s', '%f', '%f', '%f' )
			);

			if ( $result ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Get paginated raw_comp for a workspace with joined employee data.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param int    $per_page     Items per page.
	 * @param int    $page         Current page.
	 * @param string $orderby      Column to order by.
	 * @param string $order        ASC or DESC.
	 * @return array
	 */
	public function get_by_workspace( $workspace_id, $per_page = 25, $page = 1, $orderby = 'id', $order = 'ASC' ) {
		$allowed_orderby = array( 'id', 'pay_rate', 'pay_type', 'incentive_pay', 'shift_diff', 'bonus_ytd', 'created_at' );
		$order           = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order ) : 'ASC';
		$offset          = absint( ( $page - 1 ) * $per_page );
		$per_page        = absint( $per_page );

		$raw_employees = $this->db->prefix . 'labor_intel_raw_employees';

		if ( in_array( $orderby, $allowed_orderby, true ) ) {
			$order_clause = "c.{$orderby} {$order}";
		} elseif ( $orderby === 'employee_id' ) {
			$order_clause = "re.employee_id {$order}";
		} else {
			$order_clause = "c.id {$order}";
		}

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT c.*, re.employee_id
				FROM {$this->table} c
				LEFT JOIN {$raw_employees} re ON c.raw_employee_id = re.id
				WHERE c.workspace_id = %d
				ORDER BY {$order_clause}
				LIMIT %d OFFSET %d",
				$workspace_id,
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Count raw_comp rows for a workspace.
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
	 * Parse a CSV file and return validated rows.
	 *
	 * @param string $file_path    Absolute path to the CSV file.
	 * @param array  $employee_map Lookup map: employee_id (lowercase) => raw_employees.id
	 * @return array|WP_Error Array with 'rows' and 'count' on success, WP_Error on failure.
	 */
	public function parse_csv( $file_path, $employee_map ) {
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
		if ( ! in_array( 'pay_rate', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Base_Rate (or pay_rate, rate, hourly_rate).', 'labor-intel' );
		}
		if ( ! in_array( 'pay_type', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Pay_Type.', 'labor-intel' );
		}
		if ( ! in_array( 'incentive_pay', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Incentive_Pay.', 'labor-intel' );
		}
		if ( ! in_array( 'shift_diff', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Shift_Diff (or shift).', 'labor-intel' );
		}
		if ( ! in_array( 'bonus_ytd', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Bonus_YTD (or bonus).', 'labor-intel' );
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
				'employee_id_raw' => '',
				'raw_employee_id' => null,
				'pay_rate'        => null,
				'pay_type'        => null,
				'incentive_pay'   => null,
				'shift_diff'      => null,
				'bonus_ytd'       => null,
			);

			foreach ( $mapped_headers as $index => $db_col ) {
				$value = isset( $csv_row[ $index ] ) ? trim( $csv_row[ $index ] ) : '';

				switch ( $db_col ) {
					case 'employee_id':
						$row['employee_id_raw'] = sanitize_text_field( $value );
						break;

					case 'pay_rate':
						if ( $value !== '' ) {
							$cleaned = str_replace( array( '$', ',', ' ' ), '', trim( $value ) );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									__( 'Row %1$d: base_rate "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['pay_rate'] = floatval( $cleaned );
							}
						}
						break;

					case 'pay_type':
						if ( $value !== '' ) {
							$row['pay_type'] = sanitize_text_field( $value );
						}
						break;

					case 'incentive_pay':
						if ( $value !== '' ) {
							$cleaned = str_replace( array( '$', ',', ' ' ), '', trim( $value ) );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									__( 'Row %1$d: incentive_pay "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['incentive_pay'] = floatval( $cleaned );
							}
						}
						break;

					case 'shift_diff':
						if ( $value !== '' ) {
							$cleaned = str_replace( array( '$', ',', ' ' ), '', trim( $value ) );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									__( 'Row %1$d: shift_diff "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['shift_diff'] = floatval( $cleaned );
							}
						}
						break;

					case 'bonus_ytd':
						if ( $value !== '' ) {
							$cleaned = str_replace( array( '$', ',', ' ' ), '', trim( $value ) );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									__( 'Row %1$d: bonus_ytd "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['bonus_ytd'] = floatval( $cleaned );
							}
						}
						break;
				}
			}

			// Validate required fields.
			if ( empty( $row['employee_id_raw'] ) ) {
				$errors[] = sprintf(
					__( 'Row %d: employee_id is required.', 'labor-intel' ),
					$line_num
				);
			} else {
				$emp_key = strtolower( $row['employee_id_raw'] );
				if ( isset( $employee_map[ $emp_key ] ) ) {
					$row['raw_employee_id'] = $employee_map[ $emp_key ];
				} else {
					$errors[] = sprintf(
						__( 'Row %1$d: Employee_ID "%2$s" not found in Raw Employees. Please upload Raw Employees first.', 'labor-intel' ),
						$line_num,
						$row['employee_id_raw']
					);
				}
			}

			if ( null === $row['pay_rate'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: base_rate" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					__( 'Row %d: base_rate is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( null === $row['incentive_pay'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: incentive_pay" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					__( 'Row %d: incentive_pay is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( null === $row['pay_type'] || $row['pay_type'] === '' ) {
				$errors[] = sprintf(
					__( 'Row %d: pay_type is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( null === $row['shift_diff'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: shift_diff" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					__( 'Row %d: shift_diff is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( null === $row['bonus_ytd'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: bonus_ytd" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					__( 'Row %d: bonus_ytd is required.', 'labor-intel' ),
					$line_num
				);
			}

			// Remove temporary raw field.
			unset( $row['employee_id_raw'] );

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
