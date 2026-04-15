<?php
/**
 * Raw Comp Controller.
 *
 * Handles upload processing and data view rendering for the raw_comp file type.
 * Validates Employee_ID against raw_employees.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Raw_Comp_Controller {

	/**
	 * Raw Comp model instance.
	 *
	 * @var Labor_Intel_Raw_Comp_Model
	 */
	private $model;

	/**
	 * Raw Employees model instance — for FK lookup.
	 *
	 * @var Labor_Intel_Raw_Employees_Model
	 */
	private $raw_employees_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->model               = new Labor_Intel_Raw_Comp_Model();
		$this->raw_employees_model = new Labor_Intel_Raw_Employees_Model();
	}

	/**
	 * Process an uploaded Raw Comp CSV file.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $file_path    Full path to the uploaded CSV file.
	 * @return array|WP_Error Array with 'count' and 'errors' on success, WP_Error on failure.
	 */
	public function process_upload( $workspace_id, $file_path ) {
		$employee_map = $this->raw_employees_model->get_lookup_map( $workspace_id );

		if ( empty( $employee_map ) ) {
			return new WP_Error(
				'missing_raw_employees',
				__( 'No Raw Employee data found for this workspace. Please upload Raw Employees before uploading Raw Comp.', 'labor-intel' )
			);
		}

		$parsed = $this->model->parse_csv( $file_path, $employee_map );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		// Delete existing raw_comp for this workspace.
		$this->model->delete_by_workspace( $workspace_id );

		// Insert new rows.
		$inserted = $this->model->bulk_insert( $workspace_id, $parsed['rows'] );

		return array(
			'count'  => $inserted,
			'errors' => array(),
		);
	}

	/**
	 * Render the data view page for raw comp.
	 *
	 * @param int    $workspace_id    Workspace ID.
	 * @param object $workspace       Workspace object.
	 * @param array  $file_type_config File type configuration (label, icon, etc.).
	 */
	public function render_data_view( $workspace_id, $workspace, $file_type_config ) {
		$page        = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page    = 25;
		$rows        = $this->model->get_by_workspace( $workspace_id, $per_page, $page );
		$total       = $this->model->count_by_workspace( $workspace_id );
		$total_pages = (int) ceil( $total / $per_page );

		// Build download URL.
		$download_url = wp_nonce_url(
			admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace_id . '&download_file=raw_comp' ),
			'labor_intel_download_' . $workspace_id . '_raw_comp'
		);

		include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/raw-comp-data.php';
	}

	/**
	 * Get the model instance for external access.
	 *
	 * @return Labor_Intel_Raw_Comp_Model
	 */
	public function get_model() {
		return $this->model;
	}
}
