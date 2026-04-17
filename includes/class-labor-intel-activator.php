<?php
/**
 * Plugin activator.
 *
 * Creates custom database tables and sets default options.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Activator {

	/**
	 * Run activation routines.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = '';

		// Workspaces table.
		$table_workspaces = $wpdb->prefix . 'labor_intel_workspaces';
		$sql .= "CREATE TABLE {$table_workspaces} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY created_by (created_by),
			KEY status (status)
		) {$charset_collate};";

		// Dim Sites table.
		$table_dim_sites = $wpdb->prefix . 'labor_intel_dim_sites';
		$sql .= "CREATE TABLE {$table_dim_sites} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			location_id varchar(50) NOT NULL,
			region varchar(100) NOT NULL,
			revenue_annual decimal(15,2) DEFAULT NULL,
			contribution_margin_pct decimal(5,2) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ws_location (workspace_id, location_id),
			KEY workspace_id (workspace_id),
			KEY region (region)
		) {$charset_collate};";

		// Dim Roles table.
		$table_dim_roles = $wpdb->prefix . 'labor_intel_dim_roles';
		$sql .= "CREATE TABLE {$table_dim_roles} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			job_title varchar(100) NOT NULL,
			job_level varchar(20) NOT NULL,
			base_rate_target decimal(10,2) DEFAULT NULL,
			ot_benchmark decimal(5,2) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ws_job_title (workspace_id, job_title),
			KEY workspace_id (workspace_id),
			KEY job_level (job_level)
		) {$charset_collate};";

		// Raw Employees table.
		$table_raw_employees = $wpdb->prefix . 'labor_intel_raw_employees';
		$sql .= "CREATE TABLE {$table_raw_employees} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			employee_id varchar(50) NOT NULL,
			dim_site_id bigint(20) unsigned NOT NULL,
			dim_role_id bigint(20) unsigned NOT NULL,
			hire_date date NOT NULL,
			status varchar(50) DEFAULT NULL,
			termination_date date DEFAULT NULL,
			manager_id varchar(50) DEFAULT NULL,
			tenure_months int DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ws_employee (workspace_id, employee_id),
			KEY workspace_id (workspace_id),
			KEY dim_site_id (dim_site_id),
			KEY dim_role_id (dim_role_id),
			KEY hire_date (hire_date)
		) {$charset_collate};";

		// Raw Comp table.
		$table_raw_comp = $wpdb->prefix . 'labor_intel_raw_comp';
		$sql .= "CREATE TABLE {$table_raw_comp} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			raw_employee_id bigint(20) unsigned NOT NULL,
			pay_rate decimal(12,2) NOT NULL,
			pay_type varchar(50) DEFAULT NULL,
			incentive_pay decimal(12,2) NOT NULL,
			shift_diff decimal(12,2) DEFAULT NULL,
			bonus_ytd decimal(12,2) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ws_employee_comp (workspace_id, raw_employee_id),
			KEY workspace_id (workspace_id),
			KEY raw_employee_id (raw_employee_id)
		) {$charset_collate};";

		// Raw Time table.
		$table_raw_time = $wpdb->prefix . 'labor_intel_raw_time';
		$sql .= "CREATE TABLE {$table_raw_time} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			raw_employee_id bigint(20) unsigned NOT NULL,
			period_end_date date DEFAULT NULL,
			regular_hours decimal(10,2) DEFAULT NULL,
			overtime_hours decimal(10,2) NOT NULL,
			premium_hours decimal(10,2) DEFAULT NULL,
			total_paid_hours decimal(10,2) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY workspace_id (workspace_id),
			KEY raw_employee_id (raw_employee_id),
			KEY period_end_date (period_end_date)
		) {$charset_collate};";

		// Role Site Stats table.
		$table_role_site_stats = $wpdb->prefix . 'labor_intel_role_site_stats';
		$sql .= "CREATE TABLE {$table_role_site_stats} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			dim_site_id bigint(20) unsigned NOT NULL,
			dim_role_id bigint(20) unsigned NOT NULL,
			newhire_rate_avg decimal(12,2) DEFAULT NULL,
			incumbent_rate_avg decimal(12,2) DEFAULT NULL,
			rolerate_avg decimal(12,2) DEFAULT NULL,
			role_ot_benchmark decimal(12,2) DEFAULT NULL,
			live_newhire_rate decimal(12,2) DEFAULT NULL,
			live_incumbent_rate decimal(12,2) DEFAULT NULL,
			live_role_rate decimal(12,2) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ws_site_role (workspace_id, dim_site_id, dim_role_id),
			KEY workspace_id (workspace_id),
			KEY dim_site_id (dim_site_id),
			KEY dim_role_id (dim_role_id)
		) {$charset_collate};";

		// Control Panel table (one row per workspace).
		$table_control_panel = $wpdb->prefix . 'labor_intel_control_panel';
		$sql .= "CREATE TABLE {$table_control_panel} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			contribution_margin_pct decimal(8,4) NOT NULL,
			leakage_recovery_pct decimal(8,4) NOT NULL,
			retention_intervention_pct decimal(8,4) NOT NULL,
			compression_risk_weight decimal(8,4) NOT NULL,
			compression_prevention_pct decimal(8,4) NOT NULL,
			replacement_cost_default decimal(12,2) NOT NULL,
			ot_benchmark_default decimal(8,4) NOT NULL,
			ot_premium_factor decimal(8,4) NOT NULL,
			scheduling_flex_band_pct decimal(8,4) NOT NULL,
			scheduling_coverage_capture_pct decimal(8,4) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY workspace_id (workspace_id)
		) {$charset_collate};";

		// Pricing table (one row per workspace).
		$table_pricing = $wpdb->prefix . 'labor_intel_pricing';
		$sql .= "CREATE TABLE {$table_pricing} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			employee_count int(11) unsigned NOT NULL,
			site_count int(11) unsigned NOT NULL,
			pricing_model varchar(20) NOT NULL DEFAULT 'PEPM',
			pepm decimal(10,2) NOT NULL,
			annual_site_fee decimal(12,2) NOT NULL,
			value_fee_pct decimal(8,2) NOT NULL,
			value_fee_cap decimal(14,2) NOT NULL,
			annual_platform_fee decimal(14,2) DEFAULT NULL,
			modeled_ebitda_lift decimal(14,2) DEFAULT NULL,
			roi_multiple decimal(10,2) DEFAULT NULL,
			breakeven_months decimal(10,2) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY workspace_id (workspace_id)
		) {$charset_collate};";

		// Clean Data table (denormalized employee-level data, generated during processing).
		$table_clean_data = $wpdb->prefix . 'labor_intel_clean_data';
		$sql .= "CREATE TABLE {$table_clean_data} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			raw_employee_id bigint(20) unsigned NOT NULL,
			dim_site_id bigint(20) unsigned NOT NULL,
			dim_role_id bigint(20) unsigned NOT NULL,
			ot_ratio decimal(10,5) DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ws_employee (workspace_id, raw_employee_id),
			KEY workspace_id (workspace_id),
			KEY raw_employee_id (raw_employee_id),
			KEY dim_site_id (dim_site_id),
			KEY dim_role_id (dim_role_id)
		) {$charset_collate};";

		// Compression Model table (per-employee compression analysis, generated during processing).
		$table_compression_model = $wpdb->prefix . 'labor_intel_compression_model';
		$sql .= "CREATE TABLE {$table_compression_model} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			raw_employee_id bigint(20) unsigned NOT NULL,
			dim_site_id bigint(20) unsigned NOT NULL,
			dim_role_id bigint(20) unsigned NOT NULL,
			compression_gap decimal(12,2) DEFAULT 0,
			compression_exposure decimal(14,2) DEFAULT 0,
			compressed_flag tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ws_employee (workspace_id, raw_employee_id),
			KEY workspace_id (workspace_id),
			KEY raw_employee_id (raw_employee_id),
			KEY dim_site_id (dim_site_id),
			KEY dim_role_id (dim_role_id),
			KEY compressed_flag (compressed_flag)
		) {$charset_collate};";

		// Leakage Model table (per-employee OT leakage analysis, generated during processing).
		$table_leakage_model = $wpdb->prefix . 'labor_intel_leakage_model';
		$sql .= "CREATE TABLE {$table_leakage_model} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			raw_employee_id bigint(20) unsigned NOT NULL,
			dim_site_id bigint(20) unsigned NOT NULL,
			dim_role_id bigint(20) unsigned NOT NULL,
			role_ot_benchmark decimal(8,4) DEFAULT 0,
			excess_ot decimal(8,4) DEFAULT 0,
			ot_premium_factor decimal(8,4) DEFAULT 0,
			ot_leakage decimal(14,2) DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ws_employee (workspace_id, raw_employee_id),
			KEY workspace_id (workspace_id),
			KEY raw_employee_id (raw_employee_id),
			KEY dim_site_id (dim_site_id),
			KEY dim_role_id (dim_role_id)
		) {$charset_collate};";

		// Workspace Completion tracking table (one row per workspace).
		$table_completion = $wpdb->prefix . 'labor_intel_workspace_completion';
		$sql .= "CREATE TABLE {$table_completion} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workspace_id bigint(20) unsigned NOT NULL,
			dim_sites_complete tinyint(1) NOT NULL DEFAULT 0,
			dim_roles_complete tinyint(1) NOT NULL DEFAULT 0,
			raw_employee_complete tinyint(1) NOT NULL DEFAULT 0,
			raw_comp_complete tinyint(1) NOT NULL DEFAULT 0,
			raw_time_complete tinyint(1) NOT NULL DEFAULT 0,
			role_site_stats_complete tinyint(1) NOT NULL DEFAULT 0,
			control_panel_complete tinyint(1) NOT NULL DEFAULT 0,
			pricing_complete tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY workspace_id (workspace_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'labor_intel_db_version', LABOR_INTEL_VERSION );
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		add_option( 'labor_intel_db_version', LABOR_INTEL_VERSION );
	}
}
