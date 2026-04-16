<?php
/**
 * Workspace Controller.
 *
 * Handles admin menu registration, page rendering, and AJAX requests
 * for workspace CRUD operations.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Workspace_Controller {

	/**
	 * Workspace model instance.
	 *
	 * @var Labor_Intel_Workspace_Model
	 */
	private $model;

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix;

	/**
	 * Registered file types for workspace uploads.
	 *
	 * @var array
	 */
	private $file_types;

	/**
	 * File-type controllers registry.
	 *
	 * Maps file type slug to its controller instance.
	 *
	 * @var array
	 */
	private $file_controllers = array();

	/**
	 * Configuration controllers (Control Panel, Pricing).
	 *
	 * @var array
	 */
	private $config_controllers = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->model = new Labor_Intel_Workspace_Model();

		// Register file-type controllers.
		$this->file_controllers['dim_sites']    = new Labor_Intel_Dim_Sites_Controller();
		$this->file_controllers['dim_roles']    = new Labor_Intel_Dim_Roles_Controller();
		$this->file_controllers['raw_employee'] = new Labor_Intel_Raw_Employees_Controller();
		$this->file_controllers['raw_comp']     = new Labor_Intel_Raw_Comp_Controller();
		$this->file_controllers['raw_time']           = new Labor_Intel_Raw_Time_Controller();
		$this->file_controllers['role_site_stats']     = new Labor_Intel_Role_Site_Stats_Controller();
		$this->file_controllers['clean_data']            = new Labor_Intel_Clean_Data_Controller();
		$this->file_controllers['compression_model']     = new Labor_Intel_Compression_Model_Controller();

		// Register config controllers.
		$this->config_controllers['control_panel'] = new Labor_Intel_Control_Panel_Controller();
		$this->config_controllers['pricing']       = new Labor_Intel_Pricing_Controller();

		$this->file_types = array(
			'dim_sites' => array(
				'label'       => __( 'Dim Sites', 'labor-intel' ),
				'description' => __( 'Site dimension data — list of all sites/locations.', 'labor-intel' ),
				'icon'        => 'dashicons-building',
			),
			'dim_roles' => array(
				'label'       => __( 'Dim Roles', 'labor-intel' ),
				'description' => __( 'Role dimension data — list of all job roles.', 'labor-intel' ),
				'icon'        => 'dashicons-groups',
			),
			'raw_employee' => array(
				'label'       => __( 'Raw Employee', 'labor-intel' ),
				'description' => __( 'Raw employee records — headcount and demographics.', 'labor-intel' ),
				'icon'        => 'dashicons-id-alt',
			),
			'raw_comp' => array(
				'label'       => __( 'Raw Comp', 'labor-intel' ),
				'description' => __( 'Raw compensation data — salaries and benefits.', 'labor-intel' ),
				'icon'        => 'dashicons-money-alt',
			),
			'raw_time' => array(
				'label'       => __( 'Raw Time', 'labor-intel' ),
				'description' => __( 'Raw time data — hours worked and attendance.', 'labor-intel' ),
				'icon'        => 'dashicons-clock',
			),
			'role_site_stats' => array(
				'label'       => __( 'Role Site Stats', 'labor-intel' ),
				'description' => __( 'Role-site statistics — aggregated role data per site.', 'labor-intel' ),
				'icon'        => 'dashicons-chart-bar',
			),
		);
	}

	/**
	 * Register admin menu pages.
	 */
	public function register_menu() {
		$this->hook_suffix = add_menu_page(
			__( 'Labor Intel', 'labor-intel' ),
			__( 'Labor Intel', 'labor-intel' ),
			'manage_options',
			'labor-intel',
			array( $this, 'render_workspaces_page' ),
			'dashicons-analytics',
			30
		);
	}

	/**
	 * Enqueue admin CSS and JS on plugin pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( $this->hook_suffix !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'labor-intel-admin',
			LABOR_INTEL_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			LABOR_INTEL_VERSION
		);

		wp_enqueue_script(
			'labor-intel-admin',
			LABOR_INTEL_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			LABOR_INTEL_VERSION,
			true
		);

		wp_localize_script( 'labor-intel-admin', 'laborIntel', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'labor_intel_nonce' ),
			'i18n'    => array(
				'confirmDelete' => __( 'Are you sure you want to delete this workspace? This action cannot be undone.', 'labor-intel' ),
				'creating'      => __( 'Creating...', 'labor-intel' ),
				'deleting'      => __( 'Deleting...', 'labor-intel' ),
				'error'         => __( 'Something went wrong. Please try again.', 'labor-intel' ),
			),
			'fileTypeLabels' => array(
				'dim_sites'       => __( 'Dim Sites', 'labor-intel' ),
				'dim_roles'       => __( 'Dim Roles', 'labor-intel' ),
				'raw_employee'    => __( 'Raw Employee', 'labor-intel' ),
				'raw_comp'        => __( 'Raw Comp', 'labor-intel' ),
				'raw_time'        => __( 'Raw Time', 'labor-intel' ),
				'role_site_stats' => __( 'Role Site Stats', 'labor-intel' ),
			),
		) );
	}

	/**
	 * Render the main workspaces list page.
	 * Routes to detail or upload views based on query params.
	 */
	public function render_workspaces_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'labor-intel' ) );
		}

		$workspace_id = isset( $_GET['workspace_id'] ) ? absint( $_GET['workspace_id'] ) : 0;
		$file_type    = isset( $_GET['file_type'] ) ? sanitize_key( $_GET['file_type'] ) : '';
		$view_data    = isset( $_GET['view_data'] ) ? sanitize_key( $_GET['view_data'] ) : '';
		$config_type  = isset( $_GET['config_type'] ) ? sanitize_key( $_GET['config_type'] ) : '';
		$view_context = isset( $_GET['view_context'] ) ? sanitize_key( $_GET['view_context'] ) : 'uploaded';

		// Route: View uploaded data or processed results.
		if ( $workspace_id && $view_data && ( isset( $this->file_types[ $view_data ] ) || isset( $this->file_controllers[ $view_data ] ) ) ) {
			$this->render_data_view( $workspace_id, $view_data, $view_context );
			return;
		}

		// Route: Config form (Control Panel / Pricing).
		if ( $workspace_id && $config_type && isset( $this->config_controllers[ $config_type ] ) ) {
			$this->render_config_form( $workspace_id, $config_type );
			return;
		}

		// Route: Upload page.
		if ( $workspace_id && $file_type && isset( $this->file_types[ $file_type ] ) ) {
			$this->render_upload_page( $workspace_id, $file_type );
			return;
		}

		// Route: Workspace detail page.
		if ( $workspace_id ) {
			$this->render_workspace_detail( $workspace_id );
			return;
		}

		// Default: Workspaces list.
		$user_id    = get_current_user_id();
		$page       = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page   = 20;
		$workspaces = $this->model->get_workspaces( $user_id, '', 'created_at', 'DESC', $per_page, $page );
		$total      = $this->model->count_workspaces( $user_id );
		$total_pages = ceil( $total / $per_page );

		include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/workspaces.php';
	}

	/**
	 * Render the workspace detail page with file upload cards.
	 *
	 * @param int $workspace_id Workspace ID.
	 */
	private function render_workspace_detail( $workspace_id ) {
		$workspace = $this->model->get_workspace( $workspace_id );

		if ( ! $workspace || (int) $workspace->created_by !== get_current_user_id() ) {
			wp_die( esc_html__( 'Workspace not found.', 'labor-intel' ) );
		}

		// Sync completion tracking based on existing data.
		$this->model->sync_completion_tracking( $workspace_id );

		// Refresh workspace to get updated status after sync.
		$workspace = $this->model->get_workspace( $workspace_id );

		// Get completion status from DB — this is the single source of truth.
		$completion = $this->model->get_completion_status( $workspace_id );

		// Build file types with upload status from completion table.
		$file_types = array();

		foreach ( $this->file_types as $slug => $type ) {
			$column   = $slug . '_complete';
			$uploaded = $completion && ! empty( $completion->$column );

			$file_types[ $slug ] = array_merge( $type, array(
				'status' => $uploaded ? 'uploaded' : 'pending',
			) );
		}

		// Build config tiles status.
		$config_types = array(
			'control_panel' => array(
				'label'       => __( 'Control Panel', 'labor-intel' ),
				'description' => __( 'Financial assumptions and model parameters.', 'labor-intel' ),
				'icon'        => 'dashicons-admin-settings',
				'status'      => $this->config_controllers['control_panel']->has_data( $workspace_id ) ? 'saved' : 'pending',
			),
			'pricing' => array(
				'label'       => __( 'Pricing', 'labor-intel' ),
				'description' => __( 'Customer inputs and pricing model configuration.', 'labor-intel' ),
				'icon'        => 'dashicons-cart',
				'status'      => $this->config_controllers['pricing']->has_data( $workspace_id ) ? 'saved' : 'pending',
			),
		);

		include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/workspace-detail.php';
	}

	/**
	 * Render a config form page.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $config_type  Config type slug.
	 */
	private function render_config_form( $workspace_id, $config_type ) {
		$workspace = $this->model->get_workspace( $workspace_id );

		if ( ! $workspace || (int) $workspace->created_by !== get_current_user_id() ) {
			wp_die( esc_html__( 'Workspace not found.', 'labor-intel' ) );
		}

		$this->config_controllers[ $config_type ]->render_form( $workspace_id, $workspace );
	}

	/**
	 * Render the file upload page.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $file_slug    File type slug.
	 */
	private function render_upload_page( $workspace_id, $file_slug ) {
		$workspace = $this->model->get_workspace( $workspace_id );

		if ( ! $workspace || (int) $workspace->created_by !== get_current_user_id() ) {
			wp_die( esc_html__( 'Workspace not found.', 'labor-intel' ) );
		}

		$file_type = $this->file_types[ $file_slug ];

		include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/file-upload.php';
	}

	/**
	 * AJAX: Create a new workspace.
	 */
	public function ajax_create_workspace() {
		check_ajax_referer( 'labor_intel_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'labor-intel' ) ), 403 );
		}

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$desc = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Workspace name is required.', 'labor-intel' ) ) );
		}

		if ( mb_strlen( $name ) > 255 ) {
			wp_send_json_error( array( 'message' => __( 'Workspace name must be 255 characters or fewer.', 'labor-intel' ) ) );
		}

		$id = $this->model->create( array(
			'name'        => $name,
			'description' => $desc,
			'created_by'  => get_current_user_id(),
		) );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create workspace.', 'labor-intel' ) ) );
		}

		// Initialize completion tracking for this workspace.
		$this->model->init_completion_tracking( $id );

		$workspace = $this->model->get_workspace( $id );

		wp_send_json_success( array(
			'message'   => __( 'Workspace created successfully.', 'labor-intel' ),
			'workspace' => $workspace,
		) );
	}

	/**
	 * AJAX: Update an existing workspace.
	 */
	public function ajax_update_workspace() {
		check_ajax_referer( 'labor_intel_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'labor-intel' ) ), 403 );
		}

		$id   = isset( $_POST['workspace_id'] ) ? absint( $_POST['workspace_id'] ) : 0;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$desc = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid workspace ID.', 'labor-intel' ) ) );
		}

		$workspace = $this->model->get_workspace( $id );

		if ( ! $workspace || (int) $workspace->created_by !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Workspace not found.', 'labor-intel' ) ), 404 );
		}

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Workspace name is required.', 'labor-intel' ) ) );
		}

		$updated = $this->model->update( $id, array(
			'name'        => $name,
			'description' => $desc,
		) );

		if ( ! $updated ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update workspace.', 'labor-intel' ) ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Workspace updated successfully.', 'labor-intel' ),
		) );
	}

	/**
	 * AJAX: Delete a workspace.
	 */
	public function ajax_delete_workspace() {
		check_ajax_referer( 'labor_intel_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'labor-intel' ) ), 403 );
		}

		$id = isset( $_POST['workspace_id'] ) ? absint( $_POST['workspace_id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid workspace ID.', 'labor-intel' ) ) );
		}

		$workspace = $this->model->get_workspace( $id );

		if ( ! $workspace || (int) $workspace->created_by !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Workspace not found.', 'labor-intel' ) ), 404 );
		}

		$deleted = $this->model->delete( $id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete workspace.', 'labor-intel' ) ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Workspace deleted successfully.', 'labor-intel' ),
		) );
	}

	/**
	 * AJAX: Handle file upload for a workspace.
	 */
	public function ajax_upload_file() {
		check_ajax_referer( 'labor_intel_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'labor-intel' ) ), 403 );
		}

		$workspace_id = isset( $_POST['workspace_id'] ) ? absint( $_POST['workspace_id'] ) : 0;
		$file_type    = isset( $_POST['file_type'] ) ? sanitize_key( $_POST['file_type'] ) : '';

		if ( ! $workspace_id || ! isset( $this->file_types[ $file_type ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'labor-intel' ) ) );
		}

		$workspace = $this->model->get_workspace( $workspace_id );

		if ( ! $workspace || (int) $workspace->created_by !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Workspace not found.', 'labor-intel' ) ), 404 );
		}

		if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded or upload error.', 'labor-intel' ) ) );
		}

		$allowed_mimes = array(
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'xls'  => 'application/vnd.ms-excel',
			'csv'  => 'text/csv',
		);

		$file_info = wp_check_filetype( $_FILES['file']['name'], $allowed_mimes );

		if ( empty( $file_info['ext'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload an Excel (.xlsx, .xls) or CSV file.', 'labor-intel' ) ) );
		}

		// Build upload directory: wp-content/uploads/labor-intel/{workspace_id}/
		$upload_dir  = wp_upload_dir();
		$target_dir  = $upload_dir['basedir'] . '/labor-intel/' . $workspace_id;

		if ( ! wp_mkdir_p( $target_dir ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create upload directory.', 'labor-intel' ) ) );
		}

		// Remove any previous file of this type (different extension).
		foreach ( array( 'xlsx', 'xls', 'csv' ) as $ext ) {
			$old_file = $target_dir . '/' . $file_type . '.' . $ext;
			if ( file_exists( $old_file ) ) {
				wp_delete_file( $old_file );
			}
		}

		// Sanitize and build filename: {file_type}.{ext}
		$filename    = $file_type . '.' . $file_info['ext'];
		$target_path = $target_dir . '/' . $filename;

		// Move uploaded file.
		if ( ! move_uploaded_file( $_FILES['file']['tmp_name'], $target_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save uploaded file.', 'labor-intel' ) ) );
		}

		// Secure permissions.
		chmod( $target_path, 0644 );

		// If dim_sites or dim_roles is being uploaded, cascade delete dependent files.
		if ( in_array( $file_type, array( 'dim_sites', 'dim_roles' ), true ) ) {
			$this->cascade_delete_dependent_files( $workspace_id, $target_dir );
		}

		// Process file data into database based on file type.
		$process_result = $this->process_uploaded_file( $workspace_id, $file_type, $target_path );

		if ( is_wp_error( $process_result ) ) {
			// File saved but parsing failed — delete the file.
			wp_delete_file( $target_path );

			// Check if this is a validation error with multiple messages.
			$error_data = $process_result->get_error_data();
			if ( ! empty( $error_data['validation_errors'] ) && is_array( $error_data['validation_errors'] ) ) {
				$error_count = count( $error_data['validation_errors'] );
				wp_send_json_error( array(
					'message'           => sprintf(
						/* translators: %d: number of errors */
						__( 'Validation failed with %d error(s). See popup window for details.', 'labor-intel' ),
						$error_count
					),
					'validation_errors' => $error_data['validation_errors'],
				) );
			}

			wp_send_json_error( array( 'message' => $process_result->get_error_message() ) );
		}

		$response = array(
			'message'   => sprintf(
				/* translators: %1$s: file type label, %2$d: row count */
				__( '%1$s uploaded successfully. %2$d rows imported.', 'labor-intel' ),
				$this->file_types[ $file_type ]['label'],
				$process_result['count']
			),
			'file_type' => $file_type,
			'file_name' => $filename,
			'count'     => $process_result['count'],
		);

		if ( ! empty( $process_result['errors'] ) ) {
			$response['warnings'] = array_slice( $process_result['errors'], 0, 10 );
		}

		// Mark this file type as complete in completion tracking.
		$this->model->mark_component_complete( $workspace_id, $file_type );

		// Reset status to ready_for_processing if already processed.
		$this->model->reset_status_for_reprocessing( $workspace_id );

		// Check if status changed (could be to ready_for_processing or back to pending).
		$updated_workspace = $this->model->get_workspace( $workspace_id );
		if ( $updated_workspace && $updated_workspace->status !== $workspace->status ) {
			$response['status_changed'] = $updated_workspace->status;

			// If dim_sites or dim_roles was uploaded and dependent files were deleted, notify user.
			if ( in_array( $file_type, array( 'dim_sites', 'dim_roles' ), true ) && $updated_workspace->status === 'pending' ) {
				$response['message'] .= ' ' . __( 'Dependent files (Raw Employee, Raw Comp, Raw Time, Role Site Stats) have been removed and need to be re-uploaded.', 'labor-intel' );
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * Process uploaded file into database.
	 *
	 * Delegates to the registered file-type controller.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $file_type    File type slug.
	 * @param string $file_path    Full path to uploaded file.
	 * @return array|WP_Error
	 */
	private function process_uploaded_file( $workspace_id, $file_type, $file_path ) {
		if ( isset( $this->file_controllers[ $file_type ] ) ) {
			return $this->file_controllers[ $file_type ]->process_upload( $workspace_id, $file_path );
		}

		// File type has no controller yet — just save the file.
		return array( 'count' => 0, 'errors' => array() );
	}

	/**
	 * Render the data view page for a file type.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $data_type    File type slug.
	 */
	private function render_data_view( $workspace_id, $data_type, $view_context = 'uploaded' ) {
		$workspace = $this->model->get_workspace( $workspace_id );

		if ( ! $workspace || (int) $workspace->created_by !== get_current_user_id() ) {
			wp_die( esc_html__( 'Workspace not found.', 'labor-intel' ) );
		}

		if ( ! isset( $this->file_controllers[ $data_type ] ) ) {
			wp_die( esc_html__( 'Data view not available for this file type.', 'labor-intel' ) );
		}

		$file_type_config = isset( $this->file_types[ $data_type ] )
			? $this->file_types[ $data_type ]
			: array( 'label' => ucwords( str_replace( '_', ' ', $data_type ) ) );
		$this->file_controllers[ $data_type ]->render_data_view( $workspace_id, $workspace, $file_type_config, $view_context );
	}

	/**
	 * Handle file download requests (runs on admin_init).
	 */
	public function handle_file_download() {
		if ( ! isset( $_GET['page'], $_GET['workspace_id'], $_GET['download_file'] ) ) {
			return;
		}

		if ( 'labor-intel' !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$workspace_id = absint( $_GET['workspace_id'] );
		$file_type    = sanitize_key( $_GET['download_file'] );

		if ( ! $workspace_id || ! isset( $this->file_types[ $file_type ] ) ) {
			return;
		}

		check_admin_referer( 'labor_intel_download_' . $workspace_id . '_' . $file_type );

		$workspace = $this->model->get_workspace( $workspace_id );

		if ( ! $workspace || (int) $workspace->created_by !== get_current_user_id() ) {
			wp_die( esc_html__( 'Workspace not found.', 'labor-intel' ) );
		}

		$upload_dir = wp_upload_dir();
		$ws_dir     = $upload_dir['basedir'] . '/labor-intel/' . $workspace_id;
		$file_path  = '';

		foreach ( array( 'csv', 'xlsx', 'xls' ) as $ext ) {
			$candidate = $ws_dir . '/' . $file_type . '.' . $ext;
			if ( file_exists( $candidate ) ) {
				$file_path = $candidate;
				break;
			}
		}

		if ( empty( $file_path ) ) {
			wp_die( esc_html__( 'File not found.', 'labor-intel' ) );
		}

		$mime_types = array(
			'csv'  => 'text/csv',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'xls'  => 'application/vnd.ms-excel',
		);

		$ext       = pathinfo( $file_path, PATHINFO_EXTENSION );
		$mime      = isset( $mime_types[ $ext ] ) ? $mime_types[ $ext ] : 'application/octet-stream';
		$file_name = $file_type . '_' . $workspace->name . '.' . $ext;

		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file_name ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );

		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Get file types configuration.
	 *
	 * @return array
	 */
	public function get_file_types() {
		return $this->file_types;
	}

	/**
	 * AJAX: Start processing for a workspace.
	 */
	public function ajax_start_processing() {
		check_ajax_referer( 'labor_intel_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'labor-intel' ) ), 403 );
		}

		$workspace_id = isset( $_POST['workspace_id'] ) ? absint( $_POST['workspace_id'] ) : 0;

		if ( ! $workspace_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid workspace ID.', 'labor-intel' ) ) );
		}

		$workspace = $this->model->get_workspace( $workspace_id );

		if ( ! $workspace || (int) $workspace->created_by !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Workspace not found.', 'labor-intel' ) ), 404 );
		}

		if ( $workspace->status !== 'ready_for_processing' ) {
			wp_send_json_error( array( 'message' => __( 'Workspace is not ready for processing.', 'labor-intel' ) ) );
		}

		$started = $this->model->start_processing( $workspace_id );

		if ( ! $started ) {
			wp_send_json_error( array( 'message' => __( 'Failed to start processing.', 'labor-intel' ) ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Processing started successfully. This may take a few minutes.', 'labor-intel' ),
			'status'  => 'processing',
		) );
	}

	/**
	 * Cascade delete dependent files when dim_sites or dim_roles is re-uploaded.
	 *
	 * Deletes both the physical files and database data for:
	 * raw_employee, raw_comp, raw_time, role_site_stats
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $target_dir   Upload directory path.
	 */
	private function cascade_delete_dependent_files( $workspace_id, $target_dir ) {
		$dependent_types = array( 'raw_employee', 'raw_comp', 'raw_time', 'role_site_stats' );

		foreach ( $dependent_types as $type ) {
			// Delete physical files (any extension).
			foreach ( array( 'xlsx', 'xls', 'csv' ) as $ext ) {
				$file_path = $target_dir . '/' . $type . '.' . $ext;
				if ( file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
				}
			}

			// Delete database data via the controller's model.
			if ( isset( $this->file_controllers[ $type ] ) ) {
				$controller = $this->file_controllers[ $type ];
				if ( method_exists( $controller, 'get_model' ) ) {
					$model = $controller->get_model();
					if ( method_exists( $model, 'delete_by_workspace' ) ) {
						$model->delete_by_workspace( $workspace_id );
					}
				}
			}
		}

		// Mark these components as incomplete in completion tracking.
		$this->model->mark_dependent_components_incomplete( $workspace_id );
	}
}
