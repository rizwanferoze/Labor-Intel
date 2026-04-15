<?php
/**
 * Raw Time Model.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Raw_Time_Model {

	private $db;
	private $table;

	private static $column_map = array(
		'employee_id'      => 'employee_id',
		'emp_id'           => 'employee_id',
		'employee'         => 'employee_id',
		'id'               => 'employee_id',
		'period_end_date'  => 'period_end_date',
		'period_end'       => 'period_end_date',
		'period_date'      => 'period_end_date',
		'end_date'         => 'period_end_date',
		'regular_hours'    => 'regular_hours',
		'regular'          => 'regular_hours',
		'reg_hours'        => 'regular_hours',
		'overtime_hours'   => 'overtime_hours',
		'ot'               => 'overtime_hours',
		'ot_hours'         => 'overtime_hours',
		'overtime'         => 'overtime_hours',
		'premium_hours'    => 'premium_hours',
		'premium'          => 'premium_hours',
		'total_paid_hours' => 'total_paid_hours',
		'total_hours'      => 'total_paid_hours',
		'total_paid'       => 'total_paid_hours',
		'paid_hours'       => 'total_paid_hours',
	);

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'labor_intel_raw_time';
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
					'workspace_id'     => $workspace_id,
					'raw_employee_id'  => $row['raw_employee_id'],
					'period_end_date'  => $row['period_end_date'],
					'regular_hours'    => $row['regular_hours'],
					'overtime_hours'   => $row['overtime_hours'],
					'premium_hours'    => $row['premium_hours'],
					'total_paid_hours' => $row['total_paid_hours'],
				),
				array( '%d', '%d', '%s', '%f', '%f', '%f', '%f' )
			);
			if ( $result ) {
				$inserted++;
			}
		}
		return $inserted;
	}

	public function get_by_workspace( $workspace_id, $per_page = 25, $page = 1, $orderby = 'id', $order = 'ASC' ) {
		$allowed_orderby = array( 'id', 'period_end_date', 'regular_hours', 'overtime_hours', 'premium_hours', 'total_paid_hours', 'created_at' );
		$order           = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order ) : 'ASC';
		$offset          = absint( ( $page - 1 ) * $per_page );
		$per_page        = absint( $per_page );
		$raw_employees   = $this->db->prefix . 'labor_intel_raw_employees';

		if ( in_array( $orderby, $allowed_orderby, true ) ) {
			$order_clause = "t.{$orderby} {$order}";
		} elseif ( $orderby === 'employee_id' ) {
			$order_clause = "re.employee_id {$order}";
		} else {
			$order_clause = "t.id {$order}";
		}

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT t.*, re.employee_id
				FROM {$this->table} t
				LEFT JOIN {$raw_employees} re ON t.raw_employee_id = re.id
				WHERE t.workspace_id = %d
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

	public function parse_csv( $file_path, $employee_map ) {
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

		if ( ! in_array( 'employee_id', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Employee_ID (or emp_id, employee, id).', 'labor-intel' );
		}
		if ( ! in_array( 'period_end_date', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Period_End_Date (or period_end, period_date, end_date).', 'labor-intel' );
		}
		if ( ! in_array( 'regular_hours', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Regular_Hours (or regular, reg_hours).', 'labor-intel' );
		}
		if ( ! in_array( 'overtime_hours', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Overtime_Hours (or ot, ot_hours, overtime).', 'labor-intel' );
		}
		if ( ! in_array( 'premium_hours', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Premium_Hours (or premium).', 'labor-intel' );
		}
		if ( ! in_array( 'total_paid_hours', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Total_Paid_Hours (or total_hours, total_paid, paid_hours).', 'labor-intel' );
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
				'employee_id_raw'  => '',
				'raw_employee_id'  => null,
				'period_end_date'  => null,
				'regular_hours'    => null,
				'overtime_hours'   => null,
				'premium_hours'    => null,
				'total_paid_hours' => null,
			);

			foreach ( $mapped_headers as $index => $db_col ) {
				$value = isset( $csv_row[ $index ] ) ? trim( $csv_row[ $index ] ) : '';

				switch ( $db_col ) {
					case 'employee_id':
						$row['employee_id_raw'] = sanitize_text_field( $value );
						break;

					case 'period_end_date':
						if ( $value !== '' ) {
							$timestamp = strtotime( $value );
							if ( false === $timestamp ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: period_end_date "%2$s" is not a valid date.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['period_end_date'] = gmdate( 'Y-m-d', $timestamp );
							}
						}
						break;

					case 'regular_hours':
						if ( $value !== '' ) {
							$cleaned = str_replace( array( ',', ' ' ), '', $value );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: regular_hours "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['regular_hours'] = floatval( $cleaned );
							}
						}
						break;

					case 'overtime_hours':
						if ( $value !== '' ) {
							$cleaned = str_replace( array( ',', ' ' ), '', $value );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: overtime_hours "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['overtime_hours'] = floatval( $cleaned );
							}
						}
						break;

					case 'premium_hours':
						if ( $value !== '' ) {
							$cleaned = str_replace( array( ',', ' ' ), '', $value );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: premium_hours "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['premium_hours'] = floatval( $cleaned );
							}
						}
						break;

					case 'total_paid_hours':
						if ( $value !== '' ) {
							$cleaned = str_replace( array( ',', ' ' ), '', $value );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: total_paid_hours "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['total_paid_hours'] = floatval( $cleaned );
							}
						}
						break;
				}
			}

			// Validate required: employee_id.
			if ( empty( $row['employee_id_raw'] ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: employee_id is required.', 'labor-intel' ),
					$line_num
				);
			} else {
				$emp_key = strtolower( $row['employee_id_raw'] );
				if ( isset( $employee_map[ $emp_key ] ) ) {
					$row['raw_employee_id'] = $employee_map[ $emp_key ];
				} else {
					$errors[] = sprintf(
						/* translators: %1$d: line number, %2$s: employee id value */
						__( 'Row %1$d: Employee_ID "%2$s" not found in Raw Employees. Please upload Raw Employees first.', 'labor-intel' ),
						$line_num,
						$row['employee_id_raw']
					);
				}
			}

			// Validate required: overtime_hours.
			if ( null === $row['overtime_hours'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: overtime_hours" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: overtime_hours is required.', 'labor-intel' ),
					$line_num
				);
			}

			// Validate required: period_end_date.
			if ( null === $row['period_end_date'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: period_end_date" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: period_end_date is required.', 'labor-intel' ),
					$line_num
				);
			}

			// Validate required: regular_hours.
			if ( null === $row['regular_hours'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: regular_hours" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: regular_hours is required.', 'labor-intel' ),
					$line_num
				);
			}

			// Validate required: premium_hours.
			if ( null === $row['premium_hours'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: premium_hours" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: premium_hours is required.', 'labor-intel' ),
					$line_num
				);
			}

			// Validate required: total_paid_hours.
			if ( null === $row['total_paid_hours'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: total_paid_hours" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: total_paid_hours is required.', 'labor-intel' ),
					$line_num
				);
			}

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
