<?php
/**
 * Raw Time Controller.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class Labor_Intel_Raw_Time_Controller {

private $model;
private $raw_employees_model;

public function __construct() {
$this->model               = new Labor_Intel_Raw_Time_Model();
$this->raw_employees_model = new Labor_Intel_Raw_Employees_Model();
}

public function process_upload( $workspace_id, $file_path ) {
$employee_map = $this->raw_employees_model->get_lookup_map( $workspace_id );

if ( empty( $employee_map ) ) {
return new WP_Error(
'missing_raw_employees',
__( 'No Raw Employee data found for this workspace. Please upload Raw Employees before uploading Raw Time.', 'labor-intel' )
);
}

$parsed = $this->model->parse_csv( $file_path, $employee_map );

if ( is_wp_error( $parsed ) ) {
return $parsed;
}

$this->model->delete_by_workspace( $workspace_id );

$inserted = $this->model->bulk_insert( $workspace_id, $parsed['rows'] );

return array(
'count'  => $inserted,
'errors' => array(),
);
}

public function render_data_view( $workspace_id, $workspace, $file_type_config ) {
$page        = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page    = 25;
$rows        = $this->model->get_by_workspace( $workspace_id, $per_page, $page );
$total       = $this->model->count_by_workspace( $workspace_id );
$total_pages = (int) ceil( $total / $per_page );

$download_url = wp_nonce_url(
admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace_id . '&download_file=raw_time' ),
'labor_intel_download_' . $workspace_id . '_raw_time'
);

include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/raw-time-data.php';
}

/**
 * Get the model instance for external access.
 *
 * @return Labor_Intel_Raw_Time_Model
 */
public function get_model() {
return $this->model;
}
}
