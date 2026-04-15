<?php
/**
 * Role Site Stats Controller.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class Labor_Intel_Role_Site_Stats_Controller {

private $model;
private $dim_sites_model;
private $dim_roles_model;

public function __construct() {
$this->model           = new Labor_Intel_Role_Site_Stats_Model();
$this->dim_sites_model = new Labor_Intel_Dim_Sites_Model();
$this->dim_roles_model = new Labor_Intel_Dim_Roles_Model();
}

public function process_upload( $workspace_id, $file_path ) {
$site_map = $this->dim_sites_model->get_lookup_map( $workspace_id );
$role_map = $this->dim_roles_model->get_lookup_map( $workspace_id );

if ( empty( $site_map ) ) {
return new WP_Error(
'missing_dim_sites',
__( 'No Dim Sites data found for this workspace. Please upload Dim Sites before uploading Role Site Stats.', 'labor-intel' )
);
}

if ( empty( $role_map ) ) {
return new WP_Error(
'missing_dim_roles',
__( 'No Dim Roles data found for this workspace. Please upload Dim Roles before uploading Role Site Stats.', 'labor-intel' )
);
}

$parsed = $this->model->parse_csv( $file_path, $site_map, $role_map );

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

public function render_data_view( $workspace_id, $workspace, $file_type_config, $view_context = 'uploaded' ) {
$page        = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page    = 25;
$rows        = $this->model->get_by_workspace( $workspace_id, $per_page, $page );
$total       = $this->model->count_by_workspace( $workspace_id );
$total_pages = (int) ceil( $total / $per_page );

$download_url = wp_nonce_url(
admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace_id . '&download_file=role_site_stats' ),
'labor_intel_download_' . $workspace_id . '_role_site_stats'
);

include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/role-site-stats-data.php';
}

/**
 * Get the model instance for external access.
 *
 * @return Labor_Intel_Role_Site_Stats_Model
 */
public function get_model() {
return $this->model;
}
}
