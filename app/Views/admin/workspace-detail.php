<?php
/**
 * Workspace detail view — shows file upload cards.
 *
 * @package LaborIntel
 * @since   1.0.0
 *
 * @var object $workspace  The workspace object.
 * @var array  $file_types Registered file types with upload status.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$back_url = admin_url( 'admin.php?page=labor-intel' );
?>
<div class="wrap labor-intel-wrap">
	<h1 class="wp-heading-inline">
		<a href="<?php echo esc_url( $back_url ); ?>" class="li-back-link" title="<?php esc_attr_e( 'Back to Workspaces', 'labor-intel' ); ?>">
			&larr;
		</a>
		<?php echo esc_html( $workspace->name ); ?>
	</h1>
	<span class="li-status li-status--<?php echo esc_attr( $workspace->status ); ?>" style="vertical-align: middle; margin-left: 8px;">
		<?php
		$status_labels = array(
			'pending'    => __( 'Pending', 'labor-intel' ),
			'ready_for_processing' => __( 'Ready for Processing', 'labor-intel' ),
			'processing' => __( 'Processing', 'labor-intel' ),
			'processed'  => __( 'Processed', 'labor-intel' ),
		);
		echo esc_html( isset( $status_labels[ $workspace->status ] ) ? $status_labels[ $workspace->status ] : ucfirst( $workspace->status ) );
		?>
	</span>
	<hr class="wp-header-end">

	<?php if ( ! empty( $workspace->description ) ) : ?>
		<p class="li-workspace-desc"><?php echo esc_html( $workspace->description ); ?></p>
	<?php endif; ?>

	<!-- Notices container -->
	<div id="li-notices"></div>

	<h2><?php esc_html_e( 'Configuration', 'labor-intel' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Set up the financial assumptions and pricing parameters for this workspace.', 'labor-intel' ); ?></p>

	<div class="li-file-cards">
		<?php foreach ( $config_types as $slug => $config ) : ?>
			<div class="li-file-card li-file-card--<?php echo esc_attr( $config['status'] ); ?>">
				<div class="li-file-card__icon">
					<span class="dashicons <?php echo esc_attr( $config['icon'] ); ?>"></span>
				</div>
				<div class="li-file-card__body">
					<h3><?php echo esc_html( $config['label'] ); ?></h3>
					<p class="li-file-card__desc"><?php echo esc_html( $config['description'] ); ?></p>
					<?php if ( 'saved' === $config['status'] ) : ?>
						<span class="li-file-card__badge li-file-card__badge--uploaded">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Saved', 'labor-intel' ); ?>
						</span>
					<?php endif; ?>
				</div>
				<div class="li-file-card__action">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&config_type=' . $slug ) ); ?>"
						class="button button-primary">
						<?php echo 'saved' === $config['status'] ? esc_html__( 'Edit', 'labor-intel' ) : esc_html__( 'Configure', 'labor-intel' ); ?>
					</a>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<h2><?php esc_html_e( 'Data Files', 'labor-intel' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Upload each required file to proceed with data processing.', 'labor-intel' ); ?></p>

	<div class="li-file-cards">
		<?php foreach ( $file_types as $slug => $file_type ) : ?>
			<div class="li-file-card li-file-card--<?php echo esc_attr( $file_type['status'] ); ?>">
				<div class="li-file-card__icon">
					<span class="dashicons <?php echo esc_attr( $file_type['icon'] ); ?>"></span>
				</div>
				<div class="li-file-card__body">
					<h3><?php echo esc_html( $file_type['label'] ); ?></h3>
					<p class="li-file-card__desc"><?php echo esc_html( $file_type['description'] ); ?></p>
					<?php if ( 'uploaded' === $file_type['status'] ) : ?>
						<span class="li-file-card__badge li-file-card__badge--uploaded">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Uploaded', 'labor-intel' ); ?>
						</span>
					<?php endif; ?>
				</div>
				<div class="li-file-card__action">
					<?php if ( 'uploaded' === $file_type['status'] ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=' . $slug ) ); ?>"
							class="button button-primary">
							<?php esc_html_e( 'View Data', 'labor-intel' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&file_type=' . $slug ) ); ?>"
							class="button">
							<?php esc_html_e( 'Re-upload', 'labor-intel' ); ?>
						</a>
					<?php else : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&file_type=' . $slug ) ); ?>"
							class="button button-primary">
							<?php esc_html_e( 'Upload', 'labor-intel' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Processing Status -->
	<?php if ( 'ready_for_processing' === $workspace->status ) : ?>
		<div class="li-processing-box" style="background:#f0f6fc;border:1px solid #2271b1;border-radius:4px;padding:20px;margin-top:24px;">
			<h3 style="margin-top:0;color:#2271b1;">
				<span class="dashicons dashicons-yes-alt" style="color:#2271b1;"></span>
				<?php esc_html_e( 'All Data Uploaded', 'labor-intel' ); ?>
			</h3>
			<p><?php esc_html_e( 'All required files have been uploaded. You can now process the workspace data.', 'labor-intel' ); ?></p>
			<button type="button" class="button button-primary button-hero" id="li-start-processing" data-workspace-id="<?php echo esc_attr( $workspace->id ); ?>" style="display: inline-flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-controls-play"></span>
				<?php esc_html_e( 'Start Processing', 'labor-intel' ); ?>
			</button>
		</div>
	<?php elseif ( 'processing' === $workspace->status ) : ?>
		<div class="li-processing-box" style="background:#fff8e5;border:1px solid #dba617;border-radius:4px;padding:20px;margin-top:24px;">
			<h3 style="margin-top:0;color:#996800;">
				<span class="dashicons dashicons-update spin" style="color:#996800;"></span>
				<?php esc_html_e( 'Processing In Progress', 'labor-intel' ); ?>
			</h3>
			<p><?php esc_html_e( 'The workspace data is currently being processed. This page will update when processing is complete.', 'labor-intel' ); ?></p>
		</div>
	<?php elseif ( 'processed' === $workspace->status ) : ?>
		<div class="li-processing-box" style="background:#edfaef;border:1px solid #00a32a;border-radius:4px;padding:20px;margin-top:24px;">
			<h3 style="margin-top:0;color:#00a32a;">
				<span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
				<?php esc_html_e( 'Processing Complete', 'labor-intel' ); ?>
			</h3>
			<p><?php esc_html_e( 'All data has been processed successfully. View the results below.', 'labor-intel' ); ?></p>
		</div>

		<h2><?php esc_html_e( 'Results', 'labor-intel' ); ?></h2>
		<p class="description"><?php esc_html_e( 'View the processed data and analysis results.', 'labor-intel' ); ?></p>

		<div class="li-file-cards">
			<div class="li-file-card li-file-card--saved">
				<div class="li-file-card__icon">
					<span class="dashicons dashicons-chart-area"></span>
				</div>
				<div class="li-file-card__body">
					<h3><?php esc_html_e( 'Role Site Stats', 'labor-intel' ); ?></h3>
					<p class="li-file-card__desc"><?php esc_html_e( 'Processed role and site statistics with computed rates.', 'labor-intel' ); ?></p>
					<span class="li-file-card__badge li-file-card__badge--uploaded">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Ready', 'labor-intel' ); ?>
					</span>
				</div>
				<div class="li-file-card__action">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=role_site_stats&view_context=processed' ) ); ?>"
						class="button button-primary">
						<?php esc_html_e( 'View Data', 'labor-intel' ); ?>
					</a>
				</div>
			</div>

			<div class="li-file-card li-file-card--saved">
				<div class="li-file-card__icon">
					<span class="dashicons dashicons-database"></span>
				</div>
				<div class="li-file-card__body">
					<h3><?php esc_html_e( 'Clean Data', 'labor-intel' ); ?></h3>
					<p class="li-file-card__desc"><?php esc_html_e( 'Denormalized employee data with compensation, time, and role details.', 'labor-intel' ); ?></p>
					<span class="li-file-card__badge li-file-card__badge--uploaded">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Ready', 'labor-intel' ); ?>
					</span>
				</div>
				<div class="li-file-card__action">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=clean_data' ) ); ?>"
						class="button button-primary">
						<?php esc_html_e( 'View Data', 'labor-intel' ); ?>
					</a>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
