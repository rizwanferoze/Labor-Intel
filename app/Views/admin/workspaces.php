<?php
/**
 * Workspaces admin page view.
 *
 * @package LaborIntel
 * @since   1.0.0
 *
 * @var array  $workspaces  List of workspace objects.
 * @var int    $total       Total workspace count.
 * @var int    $total_pages Total pages.
 * @var int    $page        Current page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap labor-intel-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Labor Intel — Workspaces', 'labor-intel' ); ?></h1>
	<button type="button" class="page-title-action" id="li-open-create-modal">
		<?php esc_html_e( 'Add New Workspace', 'labor-intel' ); ?>
	</button>
	<hr class="wp-header-end">

	<!-- Notices container -->
	<div id="li-notices"></div>

	<!-- Workspaces table -->
	<div class="li-workspaces-container">
		<?php if ( empty( $workspaces ) ) : ?>
			<div class="li-empty-state">
				<div class="li-empty-state__icon">
					<span class="dashicons dashicons-portfolio"></span>
				</div>
				<h2><?php esc_html_e( 'No workspaces yet', 'labor-intel' ); ?></h2>
				<p><?php esc_html_e( 'Create your first workspace to start uploading and analyzing data.', 'labor-intel' ); ?></p>
				<button type="button" class="button button-primary button-hero" id="li-empty-create-btn">
					<?php esc_html_e( 'Create Your First Workspace', 'labor-intel' ); ?>
				</button>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped li-workspaces-table">
				<thead>
					<tr>
						<th scope="col" class="column-name"><?php esc_html_e( 'Name', 'labor-intel' ); ?></th>
						<th scope="col" class="column-description"><?php esc_html_e( 'Description', 'labor-intel' ); ?></th>
						<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'labor-intel' ); ?></th>
						<th scope="col" class="column-date"><?php esc_html_e( 'Created', 'labor-intel' ); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'labor-intel' ); ?></th>
					</tr>
				</thead>
				<tbody id="li-workspaces-tbody">
					<?php foreach ( $workspaces as $ws ) : ?>
						<?php include LABOR_INTEL_PLUGIN_DIR . 'app/Views/partials/workspace-row.php'; ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links( array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $page,
							) )
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<!-- Create / Edit Modal -->
	<div class="li-modal-overlay" id="li-workspace-modal" style="display:none;">
		<div class="li-modal">
			<div class="li-modal__header">
				<h2 id="li-modal-title"><?php esc_html_e( 'Create Workspace', 'labor-intel' ); ?></h2>
				<button type="button" class="li-modal__close" id="li-close-modal">&times;</button>
			</div>
			<form id="li-workspace-form">
				<div class="li-modal__body">
					<input type="hidden" id="li-workspace-id" name="workspace_id" value="">

					<div class="li-form-group">
						<label for="li-workspace-name"><?php esc_html_e( 'Workspace Name', 'labor-intel' ); ?> <span class="required">*</span></label>
						<input type="text" id="li-workspace-name" name="name" class="regular-text" maxlength="255" required>
					</div>

					<div class="li-form-group">
						<label for="li-workspace-desc"><?php esc_html_e( 'Description', 'labor-intel' ); ?></label>
						<textarea id="li-workspace-desc" name="description" rows="4" class="large-text"></textarea>
					</div>
				</div>
				<div class="li-modal__footer">
					<button type="button" class="button" id="li-cancel-modal"><?php esc_html_e( 'Cancel', 'labor-intel' ); ?></button>
					<button type="submit" class="button button-primary" id="li-save-workspace">
						<?php esc_html_e( 'Create Workspace', 'labor-intel' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
