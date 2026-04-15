<?php
/**
 * Clean Data Controller.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Clean_Data_Controller {

	private $model;

	public function __construct() {
		$this->model = new Labor_Intel_Clean_Data_Model();
	}

	/**
	 * Render the clean data view.
	 *
	 * @param int    $workspace_id    Workspace ID.
	 * @param object $workspace       Workspace object.
	 * @param array  $file_type_config File type configuration.
	 * @param string $view_context    View context (always 'processed' for clean data).
	 */
	public function render_data_view( $workspace_id, $workspace, $file_type_config, $view_context = 'processed' ) {
		$page        = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page    = 25;
		$rows        = $this->model->get_by_workspace( $workspace_id, $per_page, $page );
		$total       = $this->model->count_by_workspace( $workspace_id );
		$total_pages = (int) ceil( $total / $per_page );

		include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/clean-data.php';
	}

	/**
	 * Get the model instance.
	 *
	 * @return Labor_Intel_Clean_Data_Model
	 */
	public function get_model() {
		return $this->model;
	}
}
