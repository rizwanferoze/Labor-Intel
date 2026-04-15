<?php
/**
 * Workspace Model.
 *
 * Handles all database interactions for workspaces.
 *
 * @package LaborIntel
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labor_Intel_Workspace_Model {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Full table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Completion tracking table name.
	 *
	 * @var string
	 */
	private $completion_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->db               = $wpdb;
		$this->table            = $wpdb->prefix . 'labor_intel_workspaces';
		$this->completion_table = $wpdb->prefix . 'labor_intel_workspace_completion';
	}

	/**
	 * Get all workspaces for the current user.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $status   Filter by status. Empty for all.
	 * @param string $orderby  Column to order by.
	 * @param string $order    ASC or DESC.
	 * @param int    $per_page Items per page.
	 * @param int    $page     Current page number.
	 * @return array
	 */
	public function get_workspaces( $user_id, $status = '', $orderby = 'created_at', $order = 'DESC', $per_page = 20, $page = 1 ) {
		$allowed_orderby = array( 'id', 'name', 'status', 'created_at', 'updated_at' );
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'created_at';
		$order           = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order ) : 'DESC';
		$offset          = absint( ( $page - 1 ) * $per_page );
		$per_page        = absint( $per_page );

		$where = $this->db->prepare( 'WHERE created_by = %d', $user_id );

		if ( ! empty( $status ) ) {
			$where .= $this->db->prepare( ' AND status = %s', $status );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->db->get_results(
			"SELECT * FROM {$this->table} {$where} ORDER BY {$orderby} {$order} LIMIT {$per_page} OFFSET {$offset}"
		);
	}

	/**
	 * Count workspaces for the current user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $status  Filter by status. Empty for all.
	 * @return int
	 */
	public function count_workspaces( $user_id, $status = '' ) {
		$where = $this->db->prepare( 'WHERE created_by = %d', $user_id );

		if ( ! empty( $status ) ) {
			$where .= $this->db->prepare( ' AND status = %s', $status );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->table} {$where}" );
	}

	/**
	 * Get a single workspace by ID.
	 *
	 * @param int $id Workspace ID.
	 * @return object|null
	 */
	public function get_workspace( $id ) {
		return $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id )
		);
	}

	/**
	 * Create a new workspace.
	 *
	 * @param array $data {
	 *     Workspace data.
	 *     @type string $name        Workspace name.
	 *     @type string $description Optional description.
	 *     @type int    $created_by  User ID.
	 * }
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function create( $data ) {
		$result = $this->db->insert(
			$this->table,
			array(
				'name'        => $data['name'],
				'description' => isset( $data['description'] ) ? $data['description'] : '',
				'status'      => 'pending',
				'created_by'  => $data['created_by'],
			),
			array( '%s', '%s', '%s', '%d' )
		);

		return $result ? $this->db->insert_id : false;
	}

	/**
	 * Update an existing workspace.
	 *
	 * @param int   $id   Workspace ID.
	 * @param array $data Fields to update (name, description, status).
	 * @return bool
	 */
	public function update( $id, $data ) {
		$fields  = array();
		$formats = array();

		if ( isset( $data['name'] ) ) {
			$fields['name'] = $data['name'];
			$formats[]      = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$fields['description'] = $data['description'];
			$formats[]             = '%s';
		}

		if ( isset( $data['status'] ) ) {
			$fields['status'] = $data['status'];
			$formats[]        = '%s';
		}

		if ( empty( $fields ) ) {
			return false;
		}

		return (bool) $this->db->update(
			$this->table,
			$fields,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Delete a workspace.
	 *
	 * @param int $id Workspace ID.
	 * @return bool
	 */
	public function delete( $id ) {
		// Also delete completion tracking record.
		$this->db->delete(
			$this->completion_table,
			array( 'workspace_id' => $id ),
			array( '%d' )
		);

		return (bool) $this->db->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Initialize completion tracking for a workspace.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return bool
	 */
	public function init_completion_tracking( $workspace_id ) {
		return (bool) $this->db->insert(
			$this->completion_table,
			array( 'workspace_id' => $workspace_id ),
			array( '%d' )
		);
	}

	/**
	 * Get completion status for a workspace.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return object|null
	 */
	public function get_completion_status( $workspace_id ) {
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->completion_table} WHERE workspace_id = %d",
				$workspace_id
			)
		);
	}

	/**
	 * Sync completion tracking based on existing data in tables.
	 *
	 * This checks if data exists in each table and updates completion flags accordingly.
	 * Useful for workspaces that had data uploaded before completion tracking was implemented.
	 * Only updates flags that need changing to avoid unnecessary status recalculations.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return bool Whether any changes were made.
	 */
	public function sync_completion_tracking( $workspace_id ) {
		$prefix = $this->db->prefix . 'labor_intel_';

		// Get current completion status.
		$current = $this->get_completion_status( $workspace_id );
		if ( ! $current ) {
			return false;
		}

		// Check data existence in each table.
		$checks = array(
			'dim_sites'       => $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$prefix}dim_sites WHERE workspace_id = %d", $workspace_id ) ) > 0,
			'dim_roles'       => $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$prefix}dim_roles WHERE workspace_id = %d", $workspace_id ) ) > 0,
			'raw_employee'    => $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$prefix}raw_employees WHERE workspace_id = %d", $workspace_id ) ) > 0,
			'raw_comp'        => $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$prefix}raw_comp WHERE workspace_id = %d", $workspace_id ) ) > 0,
			'raw_time'        => $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$prefix}raw_time WHERE workspace_id = %d", $workspace_id ) ) > 0,
			'role_site_stats' => $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$prefix}role_site_stats WHERE workspace_id = %d", $workspace_id ) ) > 0,
			'control_panel'   => $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$prefix}control_panel WHERE workspace_id = %d", $workspace_id ) ) > 0,
			'pricing'         => $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$prefix}pricing WHERE workspace_id = %d", $workspace_id ) ) > 0,
		);

		$changed = false;
		foreach ( $checks as $component => $has_data ) {
			$column       = $component . '_complete';
			$is_complete  = (bool) $current->$column;

			// Only update if status needs to change.
			if ( $has_data && ! $is_complete ) {
				$result = $this->mark_component_complete( $workspace_id, $component, true );
				if ( $result ) {
					$changed = true;
				}
			}
		}

		return $changed;
	}

	/**
	 * Mark a component as complete for a workspace.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $component    Component name (dim_sites, dim_roles, raw_employee, raw_comp, raw_time, role_site_stats, control_panel, pricing).
	 * @param bool   $complete     Whether complete (default true).
	 * @return bool
	 */
	public function mark_component_complete( $workspace_id, $component, $complete = true ) {
		$valid_components = array(
			'dim_sites',
			'dim_roles',
			'raw_employee',
			'raw_comp',
			'raw_time',
			'role_site_stats',
			'control_panel',
			'pricing',
		);

		if ( ! in_array( $component, $valid_components, true ) ) {
			return false;
		}

		$column = $component . '_complete';

		$result = $this->db->update(
			$this->completion_table,
			array( $column => $complete ? 1 : 0 ),
			array( 'workspace_id' => $workspace_id ),
			array( '%d' ),
			array( '%d' )
		);

		// After marking complete/incomplete, recalculate status.
		if ( $result !== false ) {
			$this->recalculate_status( $workspace_id );
		}

		return $result !== false;
	}

	/**
	 * Recalculate workspace status based on completion tracking.
	 *
	 * - If all 8 components complete and status is pending: set to ready_for_processing
	 * - If any component incomplete and status is ready_for_processing: set back to pending
	 * - Does NOT touch "processing" or "processed" status
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return bool Whether status was changed.
	 */
	public function recalculate_status( $workspace_id ) {
		$completion = $this->get_completion_status( $workspace_id );

		if ( ! $completion ) {
			return false;
		}

		// Check if all 8 components are complete.
		$all_complete = (
			$completion->dim_sites_complete &&
			$completion->dim_roles_complete &&
			$completion->raw_employee_complete &&
			$completion->raw_comp_complete &&
			$completion->raw_time_complete &&
			$completion->role_site_stats_complete &&
			$completion->control_panel_complete &&
			$completion->pricing_complete
		);

		$workspace = $this->get_workspace( $workspace_id );
		if ( ! $workspace ) {
			return false;
		}

		// If all complete and status is pending, set to ready_for_processing.
		if ( $all_complete && $workspace->status === 'pending' ) {
			return $this->update( $workspace_id, array( 'status' => 'ready_for_processing' ) );
		}

		// If not all complete and status is ready_for_processing, set back to pending.
		if ( ! $all_complete && $workspace->status === 'ready_for_processing' ) {
			return $this->update( $workspace_id, array( 'status' => 'pending' ) );
		}

		return false;
	}

	/**
	 * Check if all components are complete and update status to ready_for_processing.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return bool Whether status was updated.
	 */
	public function maybe_update_status_to_ready( $workspace_id ) {
		$completion = $this->get_completion_status( $workspace_id );

		if ( ! $completion ) {
			return false;
		}

		// Check if all 8 components are complete.
		$all_complete = (
			$completion->dim_sites_complete &&
			$completion->dim_roles_complete &&
			$completion->raw_employee_complete &&
			$completion->raw_comp_complete &&
			$completion->raw_time_complete &&
			$completion->role_site_stats_complete &&
			$completion->control_panel_complete &&
			$completion->pricing_complete
		);

		if ( ! $all_complete ) {
			return false;
		}

		// Update if current status is 'pending' or 'processed' (re-enable processing).
		$workspace = $this->get_workspace( $workspace_id );
		if ( ! $workspace || ! in_array( $workspace->status, array( 'pending', 'processed' ), true ) ) {
			return false;
		}

		return $this->update( $workspace_id, array( 'status' => 'ready_for_processing' ) );
	}

	/**
	 * Check if workspace is ready to start processing.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return bool
	 */
	public function is_ready_for_processing( $workspace_id ) {
		$workspace = $this->get_workspace( $workspace_id );
		return $workspace && $workspace->status === 'ready_for_processing';
	}

	/**
	 * Start processing for a workspace.
	 *
	 * Updates status to 'processing' and schedules the cron job.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return bool
	 */
	public function start_processing( $workspace_id ) {
		$workspace = $this->get_workspace( $workspace_id );

		if ( ! $workspace || $workspace->status !== 'ready_for_processing' ) {
			return false;
		}

		$updated = $this->update( $workspace_id, array( 'status' => 'processing' ) );

		if ( $updated ) {
			// Schedule one-time cron job for processing.
			if ( ! wp_next_scheduled( 'labor_intel_process_workspace', array( $workspace_id ) ) ) {
				wp_schedule_single_event( time(), 'labor_intel_process_workspace', array( $workspace_id ) );
			}
		}

		return $updated;
	}

	/**
	 * Mark processing as complete.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return bool
	 */
	public function mark_processing_complete( $workspace_id ) {
		return $this->update( $workspace_id, array( 'status' => 'processed' ) );
	}

	/**
	 * Reset status to ready_for_processing when data changes.
	 *
	 * If the workspace has already been processed, reset it back to
	 * ready_for_processing so user can reprocess with updated data.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return bool Whether status was reset.
	 */
	public function reset_status_for_reprocessing( $workspace_id ) {
		$workspace = $this->get_workspace( $workspace_id );

		if ( ! $workspace ) {
			return false;
		}

		// Only reset if already processed.
		if ( $workspace->status === 'processed' ) {
			return $this->update( $workspace_id, array( 'status' => 'ready_for_processing' ) );
		}

		return false;
	}

	/**
	 * Mark dependent components as incomplete when a dimension file changes.
	 *
	 * When dim_sites or dim_roles is re-uploaded, mark the 4 dependent files
	 * (raw_employee, raw_comp, raw_time, role_site_stats) as incomplete.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return bool
	 */
	public function mark_dependent_components_incomplete( $workspace_id ) {
		$dependent_components = array(
			'raw_employee',
			'raw_comp',
			'raw_time',
			'role_site_stats',
		);

		$update_data = array();
		foreach ( $dependent_components as $component ) {
			$update_data[ $component . '_complete' ] = 0;
		}

		$result = $this->db->update(
			$this->completion_table,
			$update_data,
			array( 'workspace_id' => $workspace_id ),
			array( '%d', '%d', '%d', '%d' ),
			array( '%d' )
		);

		// Recalculate status after marking components incomplete.
		if ( $result !== false ) {
			$this->recalculate_status( $workspace_id );
		}

		return $result !== false;
	}
}
