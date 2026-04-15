<?php
/**
 * Control Panel Model.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Control_Panel_Model {

	private $db;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'labor_intel_control_panel';
	}

	public function get_by_workspace( $workspace_id ) {
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE workspace_id = %d",
				$workspace_id
			)
		);
	}

	public function save( $workspace_id, $data ) {
		$existing = $this->get_by_workspace( $workspace_id );

		$fields = array(
			'workspace_id'                  => $workspace_id,
			'contribution_margin_pct'       => $data['contribution_margin_pct'],
			'leakage_recovery_pct'          => $data['leakage_recovery_pct'],
			'retention_intervention_pct'    => $data['retention_intervention_pct'],
			'compression_risk_weight'       => $data['compression_risk_weight'],
			'compression_prevention_pct'    => $data['compression_prevention_pct'],
			'replacement_cost_default'      => $data['replacement_cost_default'],
			'ot_benchmark_default'          => $data['ot_benchmark_default'],
			'ot_premium_factor'             => $data['ot_premium_factor'],
			'scheduling_flex_band_pct'      => $data['scheduling_flex_band_pct'],
			'scheduling_coverage_capture_pct' => $data['scheduling_coverage_capture_pct'],
		);

		$formats = array( '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f' );

		if ( $existing ) {
			unset( $fields['workspace_id'] );
			array_shift( $formats );
			return $this->db->update(
				$this->table,
				$fields,
				array( 'workspace_id' => $workspace_id ),
				$formats,
				array( '%d' )
			);
		}

		return $this->db->insert( $this->table, $fields, $formats );
	}

	public function delete_by_workspace( $workspace_id ) {
		return $this->db->delete(
			$this->table,
			array( 'workspace_id' => $workspace_id ),
			array( '%d' )
		);
	}
}
