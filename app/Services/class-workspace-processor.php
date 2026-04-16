<?php
/**
 * Workspace Processor Service.
 *
 * Handles all data processing for a workspace after files are uploaded.
 * Designed to be extensible for processing multiple tables and columns.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Workspace_Processor {

	/**
	 * Database instance.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Workspace ID being processed.
	 *
	 * @var int
	 */
	private $workspace_id;

	/**
	 * Table prefixes.
	 *
	 * @var array
	 */
	private $tables;

	/**
	 * Processing log.
	 *
	 * @var array
	 */
	private $log = array();

	/**
	 * Constructor.
	 *
	 * @param int $workspace_id Workspace ID to process.
	 */
	public function __construct( $workspace_id ) {
		global $wpdb;

		$this->db           = $wpdb;
		$this->workspace_id = absint( $workspace_id );

		// Initialize table names.
		$this->tables = array(
			'workspaces'      => $wpdb->prefix . 'labor_intel_workspaces',
			'dim_sites'       => $wpdb->prefix . 'labor_intel_dim_sites',
			'dim_roles'       => $wpdb->prefix . 'labor_intel_dim_roles',
			'raw_employees'   => $wpdb->prefix . 'labor_intel_raw_employees',
			'raw_comp'        => $wpdb->prefix . 'labor_intel_raw_comp',
			'raw_time'        => $wpdb->prefix . 'labor_intel_raw_time',
			'role_site_stats' => $wpdb->prefix . 'labor_intel_role_site_stats',
			'clean_data'        => $wpdb->prefix . 'labor_intel_clean_data',
			'compression_model' => $wpdb->prefix . 'labor_intel_compression_model',
			'control_panel'     => $wpdb->prefix . 'labor_intel_control_panel',
			'pricing'         => $wpdb->prefix . 'labor_intel_pricing',
		);
	}

	/**
	 * Run the full processing pipeline.
	 *
	 * @return array Processing results with log.
	 */
	public function process() {
		$this->log( 'Starting workspace processing', 'info' );

		// Step 1: Process role_site_stats table.
		$this->process_role_site_stats();

		// Step 2: Generate clean_data table.
		$this->process_clean_data();

		// Step 3: Generate compression_model table.
		$this->process_compression_model();

		// Step 4: Future processing steps will be added here.
		// $this->process_raw_employees();
		// $this->process_calculations();
		// etc.

		$this->log( 'Workspace processing completed', 'info' );

		return array(
			'success' => true,
			'log'     => $this->log,
		);
	}

	/**
	 * Process the role_site_stats table.
	 *
	 * Updates computed columns based on dimension tables and control panel defaults.
	 */
	private function process_role_site_stats() {
		$this->log( 'Processing role_site_stats table', 'info' );

		// Process role_ot_benchmark column.
		$this->process_role_site_stats_ot_benchmark();

		// Process live_newhire_rate column.
		$this->process_role_site_stats_live_newhire_rate();

		// Process live_incumbent_rate column.
		$this->process_role_site_stats_live_incumbent_rate();

		// Process live_role_rate column.
		$this->process_role_site_stats_live_role_rate();

		// Future columns will be added here.
		// etc.

		$this->log( 'Finished processing role_site_stats table', 'info' );
	}

	/**
	 * Process role_ot_benchmark column in role_site_stats.
	 *
	 * Logic:
	 * - For each row, get dim_role_id
	 * - Look up dim_roles.ot_benchmark
	 * - If found (not null), use it
	 * - Otherwise, use control_panel.ot_benchmark_default
	 */
	private function process_role_site_stats_ot_benchmark() {
		$this->log( 'Processing role_site_stats.role_ot_benchmark', 'info' );

		// Get the control panel default value.
		$control_panel = $this->get_control_panel();

		if ( ! $control_panel ) {
			$this->log( 'Control panel not found for workspace', 'error' );
			return;
		}

		$ot_benchmark_default = $control_panel->ot_benchmark_default;
		$this->log( "Using ot_benchmark_default: {$ot_benchmark_default}", 'debug' );

		// Update all role_site_stats rows using a single UPDATE with JOIN.
		// This is more efficient than looping through each row.
		$query = $this->db->prepare(
			"UPDATE {$this->tables['role_site_stats']} AS rss
			LEFT JOIN {$this->tables['dim_roles']} AS dr ON rss.dim_role_id = dr.id
			SET rss.role_ot_benchmark = COALESCE(dr.ot_benchmark, %f)
			WHERE rss.workspace_id = %d",
			$ot_benchmark_default,
			$this->workspace_id
		);

		$result = $this->db->query( $query );

		if ( false === $result ) {
			$this->log( 'Failed to update role_ot_benchmark: ' . $this->db->last_error, 'error' );
		} else {
			$this->log( "Updated role_ot_benchmark for {$result} rows", 'info' );
		}
	}

	/**
	 * Process live_newhire_rate column in role_site_stats.
	 *
	 * Logic:
	 * - For each role_site_stats row (site + role combination)
	 * - Find all raw_employees where:
	 *   - dim_site_id matches
	 *   - dim_role_id matches
	 *   - tenure_months <= 6 (new hires)
	 * - Get pay_rate from raw_comp for those employees
	 * - Calculate SUM(pay_rate) / COUNT(employees)
	 * - Only update if matching employees exist
	 */
	private function process_role_site_stats_live_newhire_rate() {
		$this->log( 'Processing role_site_stats.live_newhire_rate', 'info' );

		// Update live_newhire_rate with the average pay_rate of new hires (tenure <= 6 months)
		// for each site/role combination.
		// Only updates rows where matching new hire employees exist.
		// Matches Excel: SUM(pay_rate) / COUNT(employees) - denominator counts all employees, not just those with comp.
		$query = $this->db->prepare(
			"UPDATE {$this->tables['role_site_stats']} AS rss
			SET rss.live_newhire_rate = (
				SELECT SUM(rc.pay_rate) / COUNT(re.id)
				FROM {$this->tables['raw_employees']} AS re
				LEFT JOIN {$this->tables['raw_comp']} AS rc ON re.id = rc.raw_employee_id
				WHERE re.dim_site_id = rss.dim_site_id
				AND re.dim_role_id = rss.dim_role_id
				AND re.tenure_months <= 6
				AND re.workspace_id = %d
			)
			WHERE rss.workspace_id = %d
			AND EXISTS (
				SELECT 1
				FROM {$this->tables['raw_employees']} AS re2
				WHERE re2.dim_site_id = rss.dim_site_id
				AND re2.dim_role_id = rss.dim_role_id
				AND re2.tenure_months <= 6
				AND re2.workspace_id = %d
			)",
			$this->workspace_id,
			$this->workspace_id,
			$this->workspace_id
		);

		// Log the query for debugging.
		$this->log( 'Query: ' . $query, 'debug' );

		$result = $this->db->query( $query );

		if ( false === $result ) {
			$this->log( 'Failed to update live_newhire_rate: ' . $this->db->last_error, 'error' );
		} else {
			$this->log( "Updated live_newhire_rate for {$result} rows", 'info' );
		}
	}

	/**
	 * Process live_incumbent_rate column in role_site_stats.
	 *
	 * Logic:
	 * - For each role_site_stats row (site + role combination)
	 * - Find all raw_employees where:
	 *   - dim_site_id matches
	 *   - dim_role_id matches
	 *   - tenure_months > 12 (incumbents)
	 * - Get pay_rate from raw_comp for those employees
	 * - Calculate SUM(pay_rate) / COUNT(employees)
	 * - Only update if matching employees exist
	 */
	private function process_role_site_stats_live_incumbent_rate() {
		$this->log( 'Processing role_site_stats.live_incumbent_rate', 'info' );

		// Update live_incumbent_rate with the average pay_rate of incumbents (tenure > 12 months)
		// for each site/role combination.
		// Only updates rows where matching incumbent employees exist.
		// Matches Excel: SUM(pay_rate) / COUNT(employees) - denominator counts all employees, not just those with comp.
		$query = $this->db->prepare(
			"UPDATE {$this->tables['role_site_stats']} AS rss
			SET rss.live_incumbent_rate = (
				SELECT SUM(rc.pay_rate) / COUNT(re.id)
				FROM {$this->tables['raw_employees']} AS re
				LEFT JOIN {$this->tables['raw_comp']} AS rc ON re.id = rc.raw_employee_id
				WHERE re.dim_site_id = rss.dim_site_id
				AND re.dim_role_id = rss.dim_role_id
				AND re.tenure_months > 12
				AND re.workspace_id = %d
			)
			WHERE rss.workspace_id = %d
			AND EXISTS (
				SELECT 1
				FROM {$this->tables['raw_employees']} AS re2
				WHERE re2.dim_site_id = rss.dim_site_id
				AND re2.dim_role_id = rss.dim_role_id
				AND re2.tenure_months > 12
				AND re2.workspace_id = %d
			)",
			$this->workspace_id,
			$this->workspace_id,
			$this->workspace_id
		);

		// Log the query for debugging.
		$this->log( 'Query: ' . $query, 'debug' );

		$result = $this->db->query( $query );

		if ( false === $result ) {
			$this->log( 'Failed to update live_incumbent_rate: ' . $this->db->last_error, 'error' );
		} else {
			$this->log( "Updated live_incumbent_rate for {$result} rows", 'info' );
		}
	}

	/**
	 * Process live_role_rate column in role_site_stats.
	 *
	 * Logic:
	 * - For each role_site_stats row (site + role combination)
	 * - Find all raw_employees where:
	 *   - dim_site_id matches
	 *   - dim_role_id matches
	 *   - No tenure filter (all employees)
	 * - Get pay_rate from raw_comp for those employees
	 * - Calculate SUM(pay_rate) / COUNT(employees)
	 * - Only update if matching employees exist
	 */
	private function process_role_site_stats_live_role_rate() {
		$this->log( 'Processing role_site_stats.live_role_rate', 'info' );

		// Update live_role_rate with the average pay_rate of ALL employees (no tenure filter)
		// for each site/role combination.
		// Only updates rows where matching employees exist.
		// Matches Excel: SUM(pay_rate) / COUNT(employees)
		$query = $this->db->prepare(
			"UPDATE {$this->tables['role_site_stats']} AS rss
			SET rss.live_role_rate = (
				SELECT SUM(rc.pay_rate) / COUNT(re.id)
				FROM {$this->tables['raw_employees']} AS re
				LEFT JOIN {$this->tables['raw_comp']} AS rc ON re.id = rc.raw_employee_id
				WHERE re.dim_site_id = rss.dim_site_id
				AND re.dim_role_id = rss.dim_role_id
				AND re.workspace_id = %d
			)
			WHERE rss.workspace_id = %d
			AND EXISTS (
				SELECT 1
				FROM {$this->tables['raw_employees']} AS re2
				WHERE re2.dim_site_id = rss.dim_site_id
				AND re2.dim_role_id = rss.dim_role_id
				AND re2.workspace_id = %d
			)",
			$this->workspace_id,
			$this->workspace_id,
			$this->workspace_id
		);

		// Log the query for debugging.
		$this->log( 'Query: ' . $query, 'debug' );

		$result = $this->db->query( $query );

		if ( false === $result ) {
			$this->log( 'Failed to update live_role_rate: ' . $this->db->last_error, 'error' );
		} else {
			$this->log( "Updated live_role_rate for {$result} rows", 'info' );
		}
	}

	/**
	 * Process clean_data table.
	 *
	 * Creates one row per raw_employee linking to their dim_site and dim_role.
	 * View-time JOINs pull comp, time, and dimension details.
	 */
	private function process_clean_data() {
		$this->log( 'Processing clean_data table', 'info' );

		$clean_data_model = new Labor_Intel_Clean_Data_Model();

		// Clear existing clean data for this workspace.
		$clean_data_model->delete_by_workspace( $this->workspace_id );

		// Bulk insert from raw_employees.
		$inserted = $clean_data_model->bulk_insert_from_employees( $this->workspace_id );

		$this->log( "Inserted {$inserted} rows into clean_data", 'info' );
	}

	/**
	 * Process compression_model table.
	 *
	 * For each employee, computes compression_gap, compression_exposure, and compressed_flag
	 * based on role_site_stats rates, raw_time hours, and control_panel risk weight.
	 */
	private function process_compression_model() {
		$this->log( 'Processing compression_model table', 'info' );

		$compression_model = new Labor_Intel_Compression_Model();

		// Clear existing compression data for this workspace.
		$compression_model->delete_by_workspace( $this->workspace_id );

		// Bulk insert from raw_employees with computed fields.
		$inserted = $compression_model->bulk_insert_from_employees( $this->workspace_id );

		$this->log( "Inserted {$inserted} rows into compression_model", 'info' );
	}

	/**
	 * Get the control panel record for the workspace.
	 *
	 * @return object|null
	 */
	private function get_control_panel() {
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->tables['control_panel']} WHERE workspace_id = %d",
				$this->workspace_id
			)
		);
	}

	/**
	 * Get the pricing record for the workspace.
	 *
	 * @return object|null
	 */
	private function get_pricing() {
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->tables['pricing']} WHERE workspace_id = %d",
				$this->workspace_id
			)
		);
	}

	/**
	 * Add an entry to the processing log.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (info, debug, warning, error).
	 */
	private function log( $message, $level = 'info' ) {
		$entry = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
		);

		$this->log[] = $entry;

		// Also log to error_log for debugging if WP_DEBUG is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[Labor Intel Processor] [%s] %s', strtoupper( $level ), $message ) );
		}
	}

	/**
	 * Get the processing log.
	 *
	 * @return array
	 */
	public function get_log() {
		return $this->log;
	}
}
