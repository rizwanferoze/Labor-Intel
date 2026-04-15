<?php
/**
 * File upload view — upload a specific file type to a workspace.
 *
 * @package LaborIntel
 * @since   1.0.0
 *
 * @var object $workspace The workspace object.
 * @var string $file_slug The file type slug.
 * @var array  $file_type The file type config (label, description, icon).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$back_url = admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id );
?>
<div class="wrap labor-intel-wrap">
	<h1 class="wp-heading-inline">
		<a href="<?php echo esc_url( $back_url ); ?>" class="li-back-link" title="<?php esc_attr_e( 'Back to Workspace', 'labor-intel' ); ?>">
			&larr;
		</a>
		<?php
		printf(
			/* translators: %1$s: file type label, %2$s: workspace name */
			esc_html__( 'Upload %1$s — %2$s', 'labor-intel' ),
			esc_html( $file_type['label'] ),
			esc_html( $workspace->name )
		);
		?>
	</h1>
	<hr class="wp-header-end">

	<!-- Notices container -->
	<div id="li-notices"></div>

	<div class="li-upload-panel">
		<div class="li-upload-panel__header">
			<span class="dashicons <?php echo esc_attr( $file_type['icon'] ); ?>"></span>
			<div>
				<h2><?php echo esc_html( $file_type['label'] ); ?></h2>
				<p class="description"><?php echo esc_html( $file_type['description'] ); ?></p>
			</div>
		</div>

		<form id="li-upload-form" enctype="multipart/form-data">
			<input type="hidden" name="workspace_id" value="<?php echo esc_attr( $workspace->id ); ?>">
			<input type="hidden" name="file_type" value="<?php echo esc_attr( $file_slug ); ?>">

			<div class="li-upload-dropzone" id="li-dropzone">
				<span class="dashicons dashicons-cloud-upload"></span>
				<p><?php esc_html_e( 'Drag & drop your Excel file here, or click to browse', 'labor-intel' ); ?></p>
				<p class="description"><?php esc_html_e( 'Accepted formats: .xlsx, .xls, .csv', 'labor-intel' ); ?></p>
				<input type="file" id="li-file-input" name="file" accept=".xlsx,.xls,.csv" style="display:none;">
				<button type="button" class="button" id="li-browse-btn"><?php esc_html_e( 'Browse Files', 'labor-intel' ); ?></button>
			</div>

			<div class="li-upload-preview" id="li-upload-preview" style="display:none;">
				<div class="li-upload-preview__file">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<div class="li-upload-preview__info">
						<strong id="li-preview-filename"></strong>
						<span id="li-preview-filesize" class="description"></span>
					</div>
					<button type="button" class="button button-link-delete" id="li-remove-file">&times;</button>
				</div>
			</div>

			<div class="li-upload-progress" id="li-upload-progress" style="display:none;">
				<div class="li-upload-progress__bar">
					<div class="li-upload-progress__fill" id="li-progress-fill"></div>
				</div>
				<span class="li-upload-progress__text" id="li-progress-text">0%</span>
			</div>

			<div class="li-upload-actions">
				<a href="<?php echo esc_url( $back_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'labor-intel' ); ?></a>
				<button type="submit" class="button button-primary" id="li-upload-btn" disabled>
					<?php esc_html_e( 'Upload File', 'labor-intel' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
