<?php
/**
 * Dim Sites Controller.
 *
 * Handles upload processing, data view rendering, and any dim-sites-specific
 * logic. Called by the Workspace Controller via the file-type registry.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Dim_Sites_Controller {

	/**
	 * Dim Sites model instance.
	 *
	 * @var Labor_Intel_Dim_Sites_Model
	 */
	private $model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->model = new Labor_Intel_Dim_Sites_Model();
	}

	/**
	 * Process an uploaded Dim Sites CSV file.
	 *
	 * Parses the CSV, validates, deletes old data, and bulk-inserts new rows.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $file_path    Full path to the uploaded CSV file.
	 * @return array|WP_Error Array with 'count' and 'errors' on success, WP_Error on failure.
	 */
	public function process_upload( $workspace_id, $file_path ) {
		$parsed = $this->model->parse_csv( $file_path );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		// Delete existing dim_sites for this workspace.
		$this->model->delete_by_workspace( $workspace_id );

		// Insert new rows.
		$inserted = $this->model->bulk_insert( $workspace_id, $parsed['rows'] );

		return array(
			'count'  => $inserted,
			'errors' => array(),
		);
	}

	/**
	 * Render the data view page for dim sites.
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
			admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace_id . '&download_file=dim_sites' ),
			'labor_intel_download_' . $workspace_id . '_dim_sites'
		);

		include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/dim-sites-data.php';
	}
}
