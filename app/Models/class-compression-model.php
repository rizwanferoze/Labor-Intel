<?php
/**
 * Compression Model.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Compression_Model {

	private $db;
	private $table;
	private $raw_employees_table;
	private $dim_sites_table;
	private $dim_roles_table;
	private $raw_comp_table;
	private $raw_time_table;
	private $role_site_stats_table;
	private $control_panel_table;

	public function __construct() {
		global $wpdb;

		$this->db                     = $wpdb;
		$this->table                  = $wpdb->prefix . 'labor_intel_compression_model';
		$this->raw_employees_table    = $wpdb->prefix . 'labor_intel_raw_employees';
		$this->dim_sites_table        = $wpdb->prefix . 'labor_intel_dim_sites';
		$this->dim_roles_table        = $wpdb->prefix . 'labor_intel_dim_roles';
		$this->raw_comp_table         = $wpdb->prefix . 'labor_intel_raw_comp';
		$this->raw_time_table         = $wpdb->prefix . 'labor_intel_raw_time';
		$this->role_site_stats_table  = $wpdb->prefix . 'labor_intel_role_site_stats';
		$this->control_panel_table    = $wpdb->prefix . 'labor_intel_control_panel';
	}

	/**
	 * Delete all compression model rows for a workspace.
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
	 * Bulk insert compression model rows for a workspace.
	 *
	 * For each raw_employee:
	 * - compression_gap = MAX(newhire_rate_avg - pay_rate, 0)
	 * - compression_exposure = compression_gap * total_paid_hours * (compression_risk_weight / 100)
	 * - compressed_flag = 1 if compression_gap > 0, else 0
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return int Number of rows inserted.
	 */
	public function bulk_insert_from_employees( $workspace_id ) {
		$query = $this->db->prepare(
			"INSERT INTO {$this->table}
				(workspace_id, raw_employee_id, dim_site_id, dim_role_id, compression_gap, compression_exposure, compressed_flag)
			SELECT
				re.workspace_id,
				re.id,
				re.dim_site_id,
				re.dim_role_id,
				ROUND(GREATEST(COALESCE(rss.newhire_rate_avg, 0) - COALESCE(rc.pay_rate, 0), 0), 2) AS compression_gap,
				ROUND(
					GREATEST(COALESCE(rss.newhire_rate_avg, 0) - COALESCE(rc.pay_rate, 0), 0)
					* COALESCE(rt.total_paid_hours, 0)
					* (COALESCE(cp.compression_risk_weight, 0) / 100),
				2) AS compression_exposure,
				CASE
					WHEN (COALESCE(rss.newhire_rate_avg, 0) - COALESCE(rc.pay_rate, 0)) > 0 THEN 1
					ELSE 0
				END AS compressed_flag
			FROM {$this->raw_employees_table} AS re
			LEFT JOIN {$this->raw_comp_table} AS rc
				ON re.id = rc.raw_employee_id
			LEFT JOIN {$this->role_site_stats_table} AS rss
				ON rss.dim_site_id = re.dim_site_id
				AND rss.dim_role_id = re.dim_role_id
				AND rss.workspace_id = %d
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
	 * Get compression model rows with joined fields for display, paginated.
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
					cm.id,
					re.employee_id,
					ds.location_id AS site,
					dr.job_title,
					re.tenure_months,
					rc.pay_rate,
					COALESCE(rt.total_paid_hours, 0) AS total_paid_hours,
					COALESCE(rss.newhire_rate_avg, 0) AS newhire_rate_avg,
					COALESCE(rss.incumbent_rate_avg, 0) AS incumbent_rate_avg,
					cm.compression_gap,
					cm.compression_exposure,
					cm.compressed_flag
				FROM {$this->table} AS cm
				INNER JOIN {$this->raw_employees_table} AS re ON cm.raw_employee_id = re.id
				INNER JOIN {$this->dim_sites_table} AS ds ON cm.dim_site_id = ds.id
				INNER JOIN {$this->dim_roles_table} AS dr ON cm.dim_role_id = dr.id
				LEFT JOIN {$this->raw_comp_table} AS rc ON re.id = rc.raw_employee_id
				LEFT JOIN (
					SELECT raw_employee_id,
						SUM(total_paid_hours) AS total_paid_hours
					FROM {$this->raw_time_table}
					WHERE workspace_id = %d
					GROUP BY raw_employee_id
				) AS rt ON re.id = rt.raw_employee_id
				LEFT JOIN {$this->role_site_stats_table} AS rss
					ON rss.dim_site_id = cm.dim_site_id
					AND rss.dim_role_id = cm.dim_role_id
					AND rss.workspace_id = %d
				WHERE cm.workspace_id = %d
				ORDER BY cm.id ASC
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
	 * Get total compression exposure for a workspace.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return float
	 */
	public function sum_exposure_by_workspace( $workspace_id ) {
		return (float) $this->db->get_var(
			$this->db->prepare(
				"SELECT SUM(compression_exposure) FROM {$this->table} WHERE workspace_id = %d",
				$workspace_id
			)
		);
	}

	/**
	 * Count total compression model rows for a workspace.
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
