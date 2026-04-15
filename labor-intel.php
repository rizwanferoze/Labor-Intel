<?php
/**
 * Plugin Name:       Labor Intel
 * Plugin URI:        https://example.com/labor-intel
 * Description:       Workspace-based data analysis plugin. Upload Excel files, process data, and visualize with charts and graphs.
 * Version:           1.0.0
 * Author:            Labor Intel Team
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       labor-intel
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package LaborIntel
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'LABOR_INTEL_VERSION', '1.0.0' );
define( 'LABOR_INTEL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LABOR_INTEL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LABOR_INTEL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Activation hook.
 */
function labor_intel_activate() {
	require_once LABOR_INTEL_PLUGIN_DIR . 'includes/class-labor-intel-activator.php';
	Labor_Intel_Activator::activate();
}
register_activation_hook( __FILE__, 'labor_intel_activate' );

/**
 * Deactivation hook.
 */
function labor_intel_deactivate() {
	require_once LABOR_INTEL_PLUGIN_DIR . 'includes/class-labor-intel-deactivator.php';
	Labor_Intel_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'labor_intel_deactivate' );

/**
 * Load the core plugin class.
 */
require_once LABOR_INTEL_PLUGIN_DIR . 'includes/class-labor-intel-loader.php';
require_once LABOR_INTEL_PLUGIN_DIR . 'includes/class-labor-intel.php';

/**
 * Begin plugin execution.
 *
 * @since 1.0.0
 */
function labor_intel_run() {
	$plugin = new Labor_Intel();
	$plugin->run();
}
labor_intel_run();
