<?php
/**
 * Clean Data Model.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Clean_Data_Model {

	private $db;
	private $table;
	private $raw_employees_table;
	private $dim_sites_table;
	private $dim_roles_table;
	private $raw_comp_table;
	private $raw_time_table;

	public function __construct() {
		global $wpdb;

		$this->db                  = $wpdb;
		$this->table               = $wpdb->prefix . 'labor_intel_clean_data';
		$this->raw_employees_table = $wpdb->prefix . 'labor_intel_raw_employees';
		$this->dim_sites_table     = $wpdb->prefix . 'labor_intel_dim_sites';
		$this->dim_roles_table     = $wpdb->prefix . 'labor_intel_dim_roles';
		$this->raw_comp_table      = $wpdb->prefix . 'labor_intel_raw_comp';
		$this->raw_time_table      = $wpdb->prefix . 'labor_intel_raw_time';
	}

	/**
	 * Delete all clean data rows for a workspace.
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
	 * Bulk insert clean data rows for a workspace.
	 *
	 * Generates one row per raw_employee, linking to their dim_site and dim_role.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return int Number of rows inserted.
	 */
	public function bulk_insert_from_employees( $workspace_id ) {
		$query = $this->db->prepare(
			"INSERT INTO {$this->table} (workspace_id, raw_employee_id, dim_site_id, dim_role_id, ot_ratio)
			SELECT
				re.workspace_id,
				re.id,
				re.dim_site_id,
				re.dim_role_id,
				COALESCE(
					CASE WHEN rt.total_paid_hours > 0
						THEN ROUND(rt.overtime_hours / rt.total_paid_hours * 100, 1)
						ELSE 0
					END,
				0)
			FROM {$this->raw_employees_table} AS re
			LEFT JOIN (
				SELECT raw_employee_id,
					SUM(overtime_hours) AS overtime_hours,
					SUM(total_paid_hours) AS total_paid_hours
				FROM {$this->raw_time_table}
				WHERE workspace_id = %d
				GROUP BY raw_employee_id
			) AS rt ON re.id = rt.raw_employee_id
			WHERE re.workspace_id = %d",
			$workspace_id,
			$workspace_id
		);

		$result = $this->db->query( $query );

		return ( false === $result ) ? 0 : $result;
	}

	/**
	 * Get clean data rows with joined fields for display, paginated.
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
					cd.id,
					re.employee_id,
					ds.location_id AS site,
					dr.job_title,
					dr.job_level,
					re.hire_date,
					re.tenure_months,
					rc.pay_rate,
					COALESCE(rt.regular_hours, 0) AS regular_hours,
					COALESCE(rt.overtime_hours, 0) AS overtime_hours,
					COALESCE(rt.premium_hours, 0) AS premium_hours,
					COALESCE(rt.total_paid_hours, 0) AS total_paid_hours,
					cd.ot_ratio,
					ds.region
				FROM {$this->table} AS cd
				INNER JOIN {$this->raw_employees_table} AS re ON cd.raw_employee_id = re.id
				INNER JOIN {$this->dim_sites_table} AS ds ON cd.dim_site_id = ds.id
				INNER JOIN {$this->dim_roles_table} AS dr ON cd.dim_role_id = dr.id
				LEFT JOIN {$this->raw_comp_table} AS rc ON re.id = rc.raw_employee_id
				LEFT JOIN (
					SELECT raw_employee_id,
						SUM(regular_hours) AS regular_hours,
						SUM(overtime_hours) AS overtime_hours,
						SUM(premium_hours) AS premium_hours,
						SUM(total_paid_hours) AS total_paid_hours
					FROM {$this->raw_time_table}
					WHERE workspace_id = %d
					GROUP BY raw_employee_id
				) AS rt ON re.id = rt.raw_employee_id
				WHERE cd.workspace_id = %d
				ORDER BY cd.id ASC
				LIMIT %d OFFSET %d",
				$workspace_id,
				$workspace_id,
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Count total clean data rows for a workspace.
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
