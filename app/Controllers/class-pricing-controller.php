<?php
/**
 * Pricing Controller.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Pricing_Controller {

	private $model;

	public function __construct() {
		$this->model = new Labor_Intel_Pricing_Model();
	}

	public function render_form( $workspace_id, $workspace ) {
		$record = $this->model->get_by_workspace( $workspace_id );
		include LABOR_INTEL_PLUGIN_DIR . 'app/Views/admin/pricing-form.php';
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

		$numeric_fields = array(
			'employee_count',
			'site_count',
			'pepm',
			'annual_site_fee',
			'value_fee_pct',
			'value_fee_cap',
		);

		$data   = array();
		$errors = array();

		// Pricing model (dropdown).
		$pricing_model  = isset( $_POST['pricing_model'] ) ? sanitize_text_field( $_POST['pricing_model'] ) : '';
		$allowed_models = array( 'PEPM', 'Site', 'Value' );
		if ( ! in_array( $pricing_model, $allowed_models, true ) ) {
			$errors[] = __( 'Pricing Model must be PEPM, Site, or Value.', 'labor-intel' );
		} else {
			$data['pricing_model'] = $pricing_model;
		}

		foreach ( $numeric_fields as $field ) {
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

		// Calculate Annual Platform Fee.
		$annual_platform_fee = 0;
		if ( 'PEPM' === $data['pricing_model'] ) {
			$annual_platform_fee = $data['employee_count'] * $data['pepm'] * 12;
		} elseif ( 'Site' === $data['pricing_model'] ) {
			$annual_platform_fee = $data['site_count'] * $data['annual_site_fee'];
		} elseif ( 'Value' === $data['pricing_model'] ) {
			// Modeled EBITDA Lift not yet available; use value_fee_cap as ceiling.
			$annual_platform_fee = $data['value_fee_cap'];
		}
		$data['annual_platform_fee'] = $annual_platform_fee;

		// Modeled EBITDA Lift — placeholder, will be populated later.
		$data['modeled_ebitda_lift'] = isset( $_POST['modeled_ebitda_lift'] ) && is_numeric( $_POST['modeled_ebitda_lift'] )
			? floatval( $_POST['modeled_ebitda_lift'] )
			: null;

		// ROI Multiple = EBITDA Lift / Platform Fee.
		$data['roi_multiple'] = ( $data['modeled_ebitda_lift'] && $annual_platform_fee > 0 )
			? round( $data['modeled_ebitda_lift'] / $annual_platform_fee, 2 )
			: null;

		// Break-even Months = Platform Fee / (EBITDA Lift / 12).
		$data['breakeven_months'] = ( $data['modeled_ebitda_lift'] && $data['modeled_ebitda_lift'] > 0 )
			? round( $annual_platform_fee / ( $data['modeled_ebitda_lift'] / 12 ), 2 )
			: null;

		// Get current workspace status before saving.
		$workspace_model = new Labor_Intel_Workspace_Model();
		$old_workspace   = $workspace_model->get_workspace( $workspace_id );
		$old_status      = $old_workspace ? $old_workspace->status : '';

		$result = $this->model->save( $workspace_id, $data );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save pricing data.', 'labor-intel' ) ) );
		}

		// Mark pricing as complete (this triggers status recalculation).
		$workspace_model->mark_component_complete( $workspace_id, 'pricing' );

		// Reset status to ready_for_processing if already processed.
		$workspace_model->reset_status_for_reprocessing( $workspace_id );

		$response = array( 'message' => __( 'Pricing saved successfully.', 'labor-intel' ) );

		// Check if status changed.
		$workspace = $workspace_model->get_workspace( $workspace_id );
		if ( $workspace && $workspace->status !== $old_status ) {
			$response['status_changed'] = $workspace->status;
		}

		wp_send_json_success( $response );
	}
}
