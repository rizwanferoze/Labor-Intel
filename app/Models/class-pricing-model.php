<?php
/**
 * Pricing Model.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Pricing_Model {

	private $db;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'labor_intel_pricing';
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
			'workspace_id'       => $workspace_id,
			'employee_count'     => $data['employee_count'],
			'site_count'         => $data['site_count'],
			'pricing_model'      => $data['pricing_model'],
			'pepm'               => $data['pepm'],
			'annual_site_fee'    => $data['annual_site_fee'],
			'value_fee_pct'      => $data['value_fee_pct'],
			'value_fee_cap'      => $data['value_fee_cap'],
			'annual_platform_fee' => isset( $data['annual_platform_fee'] ) ? $data['annual_platform_fee'] : null,
			'modeled_ebitda_lift' => isset( $data['modeled_ebitda_lift'] ) ? $data['modeled_ebitda_lift'] : null,
			'roi_multiple'       => isset( $data['roi_multiple'] ) ? $data['roi_multiple'] : null,
			'breakeven_months'   => isset( $data['breakeven_months'] ) ? $data['breakeven_months'] : null,
		);

		$formats = array( '%d', '%d', '%d', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f' );

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
