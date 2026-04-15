<?php
/**
 * Core plugin class.
 *
 * Loads dependencies, registers hooks via the Loader, and boots controllers.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel {

	/**
	 * Hook loader instance.
	 *
	 * @var Labor_Intel_Loader
	 */
	protected $loader;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Initialize the plugin.
	 */
	public function __construct() {
		$this->version = LABOR_INTEL_VERSION;
		$this->loader  = new Labor_Intel_Loader();

		$this->load_dependencies();
		$this->define_admin_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Models/class-workspace-model.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Models/class-dim-sites-model.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Models/class-dim-roles-model.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Models/class-raw-employees-model.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Models/class-raw-comp-model.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Models/class-raw-time-model.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Models/class-role-site-stats-model.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Models/class-clean-data-model.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Models/class-control-panel-model.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Models/class-pricing-model.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Controllers/class-dim-sites-controller.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Controllers/class-dim-roles-controller.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Controllers/class-raw-employees-controller.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Controllers/class-raw-comp-controller.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Controllers/class-raw-time-controller.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Controllers/class-role-site-stats-controller.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Controllers/class-clean-data-controller.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Controllers/class-control-panel-controller.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Controllers/class-pricing-controller.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Controllers/class-workspace-controller.php';
		require_once LABOR_INTEL_PLUGIN_DIR . 'app/Services/class-workspace-processor.php';
	}

	/**
	 * Register admin-side hooks.
	 */
	private function define_admin_hooks() {
		$workspace_controller = new Labor_Intel_Workspace_Controller();

		// Admin menu.
		$this->loader->add_action( 'admin_menu', $workspace_controller, 'register_menu' );

		// Enqueue admin assets.
		$this->loader->add_action( 'admin_enqueue_scripts', $workspace_controller, 'enqueue_assets' );

		// File download handler (runs early on admin_init).
		$this->loader->add_action( 'admin_init', $workspace_controller, 'handle_file_download' );

		// AJAX handlers.
		$this->loader->add_action( 'wp_ajax_labor_intel_create_workspace', $workspace_controller, 'ajax_create_workspace' );
		$this->loader->add_action( 'wp_ajax_labor_intel_delete_workspace', $workspace_controller, 'ajax_delete_workspace' );
		$this->loader->add_action( 'wp_ajax_labor_intel_update_workspace', $workspace_controller, 'ajax_update_workspace' );
		$this->loader->add_action( 'wp_ajax_labor_intel_upload_file', $workspace_controller, 'ajax_upload_file' );
		$this->loader->add_action( 'wp_ajax_labor_intel_start_processing', $workspace_controller, 'ajax_start_processing' );

		// Config AJAX handlers.
		$control_panel_controller = new Labor_Intel_Control_Panel_Controller();
		$pricing_controller       = new Labor_Intel_Pricing_Controller();
		$this->loader->add_action( 'wp_ajax_labor_intel_save_control_panel', $control_panel_controller, 'ajax_save' );
		$this->loader->add_action( 'wp_ajax_labor_intel_save_pricing', $pricing_controller, 'ajax_save' );

		// Cron hook for workspace processing.
		$this->loader->add_action( 'labor_intel_process_workspace', $this, 'handle_workspace_processing' );
	}

	/**
	 * Handle workspace processing cron job.
	 *
	 * @param int $workspace_id Workspace ID.
	 */
	public function handle_workspace_processing( $workspace_id ) {
		$workspace_model = new Labor_Intel_Workspace_Model();
		$workspace       = $workspace_model->get_workspace( $workspace_id );

		if ( ! $workspace || $workspace->status !== 'processing' ) {
			return;
		}

		// Run the workspace processor.
		$processor = new Labor_Intel_Workspace_Processor( $workspace_id );
		$result    = $processor->process();

		// Log result if debugging is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $result['log'] ) ) {
			error_log( 'Labor Intel Processing Log: ' . wp_json_encode( $result['log'] ) );
		}

		// Mark processing as complete.
		$workspace_model->mark_processing_complete( $workspace_id );
	}

	/**
	 * Run the loader to register all hooks.
	 */
	public function run() {
		$this->loader->run();
	}
}
