<?php
/**
 * Fired during plugin uninstall.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

// Abort if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
$tables = array(
	$wpdb->prefix . 'labor_intel_pricing',
	$wpdb->prefix . 'labor_intel_control_panel',
	$wpdb->prefix . 'labor_intel_role_site_stats',
	$wpdb->prefix . 'labor_intel_raw_time',
	$wpdb->prefix . 'labor_intel_raw_comp',
	$wpdb->prefix . 'labor_intel_raw_employees',
	$wpdb->prefix . 'labor_intel_dim_roles',
	$wpdb->prefix . 'labor_intel_dim_sites',
	$wpdb->prefix . 'labor_intel_workspaces',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete plugin options.
delete_option( 'labor_intel_db_version' );
