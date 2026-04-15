<?php
/**
 * Dim Sites Model.
 *
 * Handles all database interactions for the dim_sites table.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Dim_Sites_Model {

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
		// location_id aliases.
		'site_id'     => 'location_id',
		'location_id' => 'location_id',
		'loc'         => 'location_id',
		'site'        => 'location_id',
		'facility'    => 'location_id',
		// region.
		'region'      => 'region',
		// revenue_annual aliases.
		'revenue_annual' => 'revenue_annual',
		'revenue'        => 'revenue_annual',
		// contribution_margin_pct aliases.
		'contribution_margin_%' => 'contribution_margin_pct',
		'contribution_margin'   => 'contribution_margin_pct',
		'margin_%'              => 'contribution_margin_pct',
		'margin'                => 'contribution_margin_pct',
	);

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'labor_intel_dim_sites';
	}

	/**
	 * Delete all dim_sites rows for a workspace.
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
	 * @param array $rows         Array of associative arrays with keys: location_id, region, revenue_annual, contribution_margin_pct.
	 * @return int Number of rows inserted.
	 */
	public function bulk_insert( $workspace_id, $rows ) {
		$inserted = 0;

		foreach ( $rows as $row ) {
			$result = $this->db->insert(
				$this->table,
				array(
					'workspace_id'            => $workspace_id,
					'location_id'             => $row['location_id'],
					'region'                  => $row['region'],
					'revenue_annual'          => $row['revenue_annual'],
					'contribution_margin_pct' => $row['contribution_margin_pct'],
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
	 * Get paginated dim_sites for a workspace.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param int    $per_page     Items per page.
	 * @param int    $page         Current page.
	 * @param string $orderby      Column to order by.
	 * @param string $order        ASC or DESC.
	 * @return array
	 */
	public function get_by_workspace( $workspace_id, $per_page = 25, $page = 1, $orderby = 'id', $order = 'ASC' ) {
		$allowed_orderby = array( 'id', 'location_id', 'region', 'revenue_annual', 'contribution_margin_pct', 'created_at' );
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
	 * Count dim_sites rows for a workspace.
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
	 * Get a lookup map of location_id => id for a workspace.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return array Associative array: location_id => dim_sites.id
	 */
	public function get_lookup_map( $workspace_id ) {
		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, location_id FROM {$this->table} WHERE workspace_id = %d",
				$workspace_id
			)
		);

		$map = array();
		foreach ( $results as $row ) {
			$map[ strtolower( $row->location_id ) ] = (int) $row->id;
		}

		return $map;
	}

	/**
	 * Parse a CSV file and return validated rows.
	 *
	 * @param string $file_path Absolute path to the CSV file.
	 * @return array|WP_Error Array of rows on success, WP_Error on failure.
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
		$mapped_columns   = array_values( $mapped_headers );
		$missing_columns  = array();

		if ( ! in_array( 'location_id', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: location_id (or Site_ID, loc, site, facility).', 'labor-intel' );
		}
		if ( ! in_array( 'region', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Region.', 'labor-intel' );
		}
		if ( ! in_array( 'revenue_annual', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Revenue_Annual (or revenue).', 'labor-intel' );
		}
		if ( ! in_array( 'contribution_margin_pct', $mapped_columns, true ) ) {
			$missing_columns[] = __( 'Required column missing: Contribution_Margin_% (or contribution_margin, margin_%, margin).', 'labor-intel' );
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
		$rows       = array();
		$line_num   = 1;
		$errors     = array();

		while ( ( $csv_row = fgetcsv( $handle ) ) !== false ) {
			$line_num++;

			// Skip completely empty rows.
			if ( count( $csv_row ) === 1 && trim( $csv_row[0] ) === '' ) {
				continue;
			}

			$row = array(
				'location_id'             => '',
				'region'                  => '',
				'revenue_annual'          => null,
				'contribution_margin_pct' => null,
			);

			foreach ( $mapped_headers as $index => $db_col ) {
				$value = isset( $csv_row[ $index ] ) ? trim( $csv_row[ $index ] ) : '';

				switch ( $db_col ) {
					case 'location_id':
						$row['location_id'] = sanitize_text_field( $value );
						break;

					case 'region':
						$row['region'] = sanitize_text_field( $value );
						break;

					case 'revenue_annual':
						if ( $value !== '' ) {
							// Strip $ sign, commas, and spaces.
							$cleaned = str_replace( array( '$', ',', ' ' ), '', trim( $value ) );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: revenue_annual "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['revenue_annual'] = floatval( $cleaned );
							}
						}
						break;

					case 'contribution_margin_pct':
						if ( $value !== '' ) {
							// Strip % sign and spaces.
							$cleaned = str_replace( array( '%', ' ' ), '', trim( $value ) );
							if ( ! is_numeric( $cleaned ) ) {
								$errors[] = sprintf(
									/* translators: %1$d: line number, %2$s: original value */
									__( 'Row %1$d: contribution_margin_%% "%2$s" is not a valid number.', 'labor-intel' ),
									$line_num,
									$value
								);
							} else {
								$row['contribution_margin_pct'] = floatval( $cleaned );
							}
						}
						break;
				}
			}

			// Validate required fields.
			if ( empty( $row['location_id'] ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: location_id is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( empty( $row['region'] ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: region is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( null === $row['revenue_annual'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: revenue_annual" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: revenue_annual is required.', 'labor-intel' ),
					$line_num
				);
			}

			if ( null === $row['contribution_margin_pct'] && ! array_filter( $errors, function( $e ) use ( $line_num ) {
				return strpos( $e, "Row {$line_num}: contribution_margin" ) !== false;
			} ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					__( 'Row %d: contribution_margin_%% is required.', 'labor-intel' ),
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
