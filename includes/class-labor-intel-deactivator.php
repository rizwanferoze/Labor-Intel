<?php
/**
 * Plugin deactivator.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Deactivator {

	/**
	 * Run deactivation routines.
	 */
	public static function deactivate() {
		// Clean up transients if any.
		delete_transient( 'labor_intel_workspace_count' );
	}
}
