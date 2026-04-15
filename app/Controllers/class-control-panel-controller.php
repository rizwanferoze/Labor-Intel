<?php
/**
 * Control Panel Controller.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Control_Panel_Controller {

	private $model;

	public function __construct() {
		$this->model = new Labor_Intel_Control_Panel_Model();
	}

	public function render_form( $workspace_id, $workspace ) {
		$record = $this->model->get_by_workspace( $workspace_id );
		include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/control-panel-form.php';
	}

	public function has_data( $workspace_id ) {
		return null !== $this->model->get_by_workspace( $workspace_id );
	}

	public function ajax_save() {
		check_ajax_referer( 'labor_intel_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'labor-intel' ) ) );
		}

		$workspace_id = isset( $_POST['workspace_id'] ) ? absint( $_POST['workspace_id'] ) : 0;
		if ( ! $workspace_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid workspace.', 'labor-intel' ) ) );
		}

		$fields = array(
			'contribution_margin_pct',
			'leakage_recovery_pct',
			'retention_intervention_pct',
			'compression_risk_weight',
			'compression_prevention_pct',
			'replacement_cost_default',
			'ot_benchmark_default',
			'ot_premium_factor',
			'scheduling_flex_band_pct',
			'scheduling_coverage_capture_pct',
		);

		$data   = array();
		$errors = array();

		foreach ( $fields as $field ) {
			$value = isset( $_POST[ $field ] ) ? trim( $_POST[ $field ] ) : '';
			if ( $value === '' ) {
				$errors[] = sprintf( __( '%s is required.', 'labor-intel' ), $field );
				continue;
			}
			$cleaned = str_replace( array( '$', ',', '%', ' ' ), '', $value );
			if ( ! is_numeric( $cleaned ) ) {
				$errors[] = sprintf( __( '%s must be a valid number.', 'labor-intel' ), $field );
				continue;
			}
			$data[ $field ] = floatval( $cleaned );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( "\n", $errors ) ) );
		}

		// Get current workspace status before saving.
		$workspace_model = new Labor_Intel_Workspace_Model();
		$old_workspace   = $workspace_model->get_workspace( $workspace_id );
		$old_status      = $old_workspace ? $old_workspace->status : '';

		$result = $this->model->save( $workspace_id, $data );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save control panel data.', 'labor-intel' ) ) );
		}

		// Mark control panel as complete (this triggers status recalculation).
		$workspace_model->mark_component_complete( $workspace_id, 'control_panel' );

		// Reset status to ready_for_processing if already processed.
		$workspace_model->reset_status_for_reprocessing( $workspace_id );

		$response = array( 'message' => __( 'Control Panel saved successfully.', 'labor-intel' ) );

		// Check if status changed.
		$workspace = $workspace_model->get_workspace( $workspace_id );
		if ( $workspace && $workspace->status !== $old_status ) {
			$response['status_changed'] = $workspace->status;
		}

		wp_send_json_success( $response );
	}
}
