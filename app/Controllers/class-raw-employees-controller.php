<?php
/**
 * Raw Employees Controller.
 *
 * Handles upload processing and data view rendering for the raw_employee file type.
 * Validates Site_ID against dim_sites and Job_Family against dim_roles.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Raw_Employees_Controller {

	/**
	 * Raw Employees model instance.
	 *
	 * @var Labor_Intel_Raw_Employees_Model
	 */
	private $model;

	/**
	 * Dim Sites model instance — for FK lookup.
	 *
	 * @var Labor_Intel_Dim_Sites_Model
	 */
	private $dim_sites_model;

	/**
	 * Dim Roles model instance — for FK lookup.
	 *
	 * @var Labor_Intel_Dim_Roles_Model
	 */
	private $dim_roles_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->model           = new Labor_Intel_Raw_Employees_Model();
		$this->dim_sites_model = new Labor_Intel_Dim_Sites_Model();
		$this->dim_roles_model = new Labor_Intel_Dim_Roles_Model();
	}

	/**
	 * Process an uploaded Raw Employees CSV file.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $file_path    Full path to the uploaded CSV file.
	 * @return array|WP_Error Array with 'count' and 'errors' on success, WP_Error on failure.
	 */
	public function process_upload( $workspace_id, $file_path ) {
		// Build lookup maps from dimension tables.
		$site_map = $this->dim_sites_model->get_lookup_map( $workspace_id );
		$role_map = $this->dim_roles_model->get_lookup_map( $workspace_id );

		if ( empty( $site_map ) ) {
			return new WP_Error(
				'missing_dim_sites',
				__( 'No Dim Sites data found for this workspace. Please upload Dim Sites before uploading Raw Employees.', 'labor-intel' )
			);
		}

		if ( empty( $role_map ) ) {
			return new WP_Error(
				'missing_dim_roles',
				__( 'No Dim Roles data found for this workspace. Please upload Dim Roles before uploading Raw Employees.', 'labor-intel' )
			);
		}

		$parsed = $this->model->parse_csv( $file_path, $site_map, $role_map );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		// Delete existing raw_employees for this workspace.
		$this->model->delete_by_workspace( $workspace_id );

		// Insert new rows.
		$inserted = $this->model->bulk_insert( $workspace_id, $parsed['rows'] );

		return array(
			'count'  => $inserted,
			'errors' => array(),
		);
	}

	/**
	 * Render the data view page for raw employees.
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
			admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace_id . '&download_file=raw_employee' ),
			'labor_intel_download_' . $workspace_id . '_raw_employee'
		);

		include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/raw-employees-data.php';
	}

	/**
	 * Get the model instance for external access.
	 *
	 * @return Labor_Intel_Raw_Employees_Model
	 */
	public function get_model() {
		return $this->model;
	}
}
