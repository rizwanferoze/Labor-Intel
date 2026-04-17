<?php
/**
 * Leakage Model.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Leakage_Model {

	private $db;
	private $table;
	private $raw_employees_table;
	private $dim_sites_table;
	private $dim_roles_table;
	private $raw_comp_table;
	private $raw_time_table;
	private $clean_data_table;
	private $control_panel_table;

	public function __construct() {
		global $wpdb;

		$this->db                  = $wpdb;
		$this->table               = $wpdb->prefix . 'labor_intel_leakage_model';
		$this->raw_employees_table = $wpdb->prefix . 'labor_intel_raw_employees';
		$this->dim_sites_table     = $wpdb->prefix . 'labor_intel_dim_sites';
		$this->dim_roles_table     = $wpdb->prefix . 'labor_intel_dim_roles';
		$this->raw_comp_table      = $wpdb->prefix . 'labor_intel_raw_comp';
		$this->raw_time_table      = $wpdb->prefix . 'labor_intel_raw_time';
		$this->clean_data_table    = $wpdb->prefix . 'labor_intel_clean_data';
		$this->control_panel_table = $wpdb->prefix . 'labor_intel_control_panel';
	}

	/**
	 * Delete all leakage model rows for a workspace.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public function delete_by_workspace( $workspace_id ) {
		return $this->db->delete(
			$this->table,
			array( 'workspace_id' => $workspace_id ),
			array( '%d' )
		);
	}

	/**
	 * Bulk insert leakage model rows for a workspace.
	 *
	 * For each raw_employee:
	 * - role_ot_benchmark = COALESCE(dim_roles.ot_benchmark, control_panel.ot_benchmark_default)
	 * - excess_ot = GREATEST(clean_data.ot_ratio - role_ot_benchmark, 0)
	 * - ot_premium_factor = control_panel.ot_premium_factor / 100  (stored as decimal e.g. 0.50)
	 * - ot_leakage = excess_ot * total_paid_hours * pay_rate * ot_premium_factor
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return int Number of rows inserted.
	 */
	public function bulk_insert_from_employees( $workspace_id ) {
		$query = $this->db->prepare(
			"INSERT INTO {$this->table}
				(workspace_id, raw_employee_id, dim_site_id, dim_role_id, role_ot_benchmark, excess_ot, ot_premium_factor, ot_leakage)
			SELECT
				re.workspace_id,
				re.id,
				re.dim_site_id,
				re.dim_role_id,
				ROUND(COALESCE(dr.ot_benchmark, cp.ot_benchmark_default, 0), 4) AS role_ot_benchmark,
				ROUND(GREATEST(COALESCE(cd.ot_ratio, 0) - COALESCE(dr.ot_benchmark, cp.ot_benchmark_default, 0), 0), 4) AS excess_ot,
				ROUND(COALESCE(cp.ot_premium_factor, 0) / 100, 4) AS ot_premium_factor,
				ROUND(
					(GREATEST(COALESCE(cd.ot_ratio, 0) - COALESCE(dr.ot_benchmark, cp.ot_benchmark_default, 0), 0) / 100)
					* COALESCE(rt.total_paid_hours, 0)
					* COALESCE(rc.pay_rate, 0)
					* (COALESCE(cp.ot_premium_factor, 0) / 100),
				2) AS ot_leakage
			FROM {$this->raw_employees_table} AS re
			LEFT JOIN {$this->dim_roles_table} AS dr
				ON re.dim_role_id = dr.id
			LEFT JOIN {$this->raw_comp_table} AS rc
				ON re.id = rc.raw_employee_id
			LEFT JOIN {$this->clean_data_table} AS cd
				ON cd.raw_employee_id = re.id
				AND cd.workspace_id = %d
			LEFT JOIN (
				SELECT raw_employee_id,
					SUM(total_paid_hours) AS total_paid_hours
				FROM {$this->raw_time_table}
				WHERE workspace_id = %d
				GROUP BY raw_employee_id
			) AS rt ON re.id = rt.raw_employee_id
			LEFT JOIN {$this->control_panel_table} AS cp
				ON cp.workspace_id = %d
			WHERE re.workspace_id = %d",
			$workspace_id,
			$workspace_id,
			$workspace_id,
			$workspace_id
		);

		$result = $this->db->query( $query );

		return ( false === $result ) ? 0 : $result;
	}

	/**
	 * Get leakage model rows with joined fields for display, paginated.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @param int $per_page     Items per page.
	 * @param int $page         Current page.
	 * @return array
	 */
	public function get_by_workspace( $workspace_id, $per_page = 25, $page = 1 ) {
		$offset = ( $page - 1 ) * $per_page;

		return $this->db->get_results(
			$this->db->prepare(
				"SELECT
					lm.id,
					re.employee_id,
					ds.location_id AS site,
					dr.job_title,
					rc.pay_rate,
					COALESCE(rt.total_paid_hours, 0) AS total_paid_hours,
					COALESCE(cd.ot_ratio, 0) AS ot_ratio,
					lm.role_ot_benchmark,
					lm.excess_ot,
					lm.ot_premium_factor,
					lm.ot_leakage
				FROM {$this->table} AS lm
				INNER JOIN {$this->raw_employees_table} AS re ON lm.raw_employee_id = re.id
				INNER JOIN {$this->dim_sites_table} AS ds ON lm.dim_site_id = ds.id
				INNER JOIN {$this->dim_roles_table} AS dr ON lm.dim_role_id = dr.id
				LEFT JOIN {$this->raw_comp_table} AS rc ON re.id = rc.raw_employee_id
				LEFT JOIN (
					SELECT raw_employee_id,
						SUM(total_paid_hours) AS total_paid_hours
					FROM {$this->raw_time_table}
					WHERE workspace_id = %d
					GROUP BY raw_employee_id
				) AS rt ON re.id = rt.raw_employee_id
				LEFT JOIN {$this->clean_data_table} AS cd
					ON cd.raw_employee_id = re.id
					AND cd.workspace_id = %d
				WHERE lm.workspace_id = %d
				ORDER BY lm.id ASC
				LIMIT %d OFFSET %d",
				$workspace_id,
				$workspace_id,
				$workspace_id,
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Get total OT leakage for a workspace.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return float
	 */
	public function sum_leakage_by_workspace( $workspace_id ) {
		return (float) $this->db->get_var(
			$this->db->prepare(
				"SELECT SUM(ot_leakage) FROM {$this->table} WHERE workspace_id = %d",
				$workspace_id
			)
		);
	}

	/**
	 * Get recoverable leakage for a workspace (total_leakage * leakage_recovery_pct / 100).
	 *
	 * @param int   $workspace_id  Workspace ID.
	 * @param float $total_leakage Pre-computed total leakage.
	 * @return float
	 */
	public function get_recoverable_leakage( $workspace_id, $total_leakage ) {
		$recovery_pct = (float) $this->db->get_var(
			$this->db->prepare(
				"SELECT leakage_recovery_pct FROM {$this->control_panel_table} WHERE workspace_id = %d",
				$workspace_id
			)
		);

		return $total_leakage * ( $recovery_pct / 100 );
	}

	/**
	 * Count total leakage model rows for a workspace.
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
}
