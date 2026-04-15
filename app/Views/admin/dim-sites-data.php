<?php
/**
 * Dim Sites data view — paginated table of imported site data.
 *
 * @package LaborIntel
 * @since   1.0.0
 *
 * @var object $workspace        The workspace object.
 * @var array  $file_type_config File type config (label, icon, etc.).
 * @var array  $rows             Current page of dim_sites rows.
 * @var int    $total            Total row count.
 * @var int    $total_pages      Total pages.
 * @var int    $page             Current page.
 * @var int    $per_page         Items per page.
 * @var string $download_url     Nonce-protected download URL.
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
			esc_html__( '%1$s — %2$s', 'labor-intel' ),
			esc_html( $file_type_config['label'] ),
			esc_html( $workspace->name )
		);
		?>
	</h1>
	<a href="<?php echo esc_url( $download_url ); ?>" class="page-title-action">
		<span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: -2px;"></span>
		<?php esc_html_e( 'Download File', 'labor-intel' ); ?>
	</a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&file_type=dim_sites' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Re-upload', 'labor-intel' ); ?>
	</a>
	<hr class="wp-header-end">

	<div class="li-data-summary">
		<?php
		printf(
			/* translators: %s: total rows */
			esc_html__( 'Total records: %s', 'labor-intel' ),
			'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>'
		);
		?>
	</div>

	<?php if ( empty( $rows ) ) : ?>
		<div class="li-empty-state">
			<p><?php esc_html_e( 'No data found. Upload a Dim Sites file to get started.', 'labor-intel' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped li-data-table">
			<thead>
				<tr>
					<th scope="col" class="column-id" style="width:5%;">#</th>
					<th scope="col" class="column-location-id" style="width:15%;"><?php esc_html_e( 'Location ID', 'labor-intel' ); ?></th>
					<th scope="col" class="column-region" style="width:15%;"><?php esc_html_e( 'Region', 'labor-intel' ); ?></th>
					<th scope="col" class="column-revenue" style="width:20%;"><?php esc_html_e( 'Revenue (Annual)', 'labor-intel' ); ?></th>
					<th scope="col" class="column-margin" style="width:20%;"><?php esc_html_e( 'Contribution Margin %', 'labor-intel' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$row_num = ( $page - 1 ) * $per_page;
				foreach ( $rows as $row ) :
					$row_num++;
				?>
					<tr>
						<td><?php echo esc_html( $row_num ); ?></td>
						<td><strong><?php echo esc_html( $row->location_id ); ?></strong></td>
						<td><?php echo esc_html( $row->region ); ?></td>
						<td>
							<?php
							if ( null !== $row->revenue_annual && '' !== $row->revenue_annual ) {
								echo esc_html( '$' . number_format( (float) $row->revenue_annual, 2 ) );
							} else {
								echo '—';
							}
							?>
						</td>
						<td>
							<?php
							if ( null !== $row->contribution_margin_pct && '' !== $row->contribution_margin_pct ) {
								echo esc_html( number_format( (float) $row->contribution_margin_pct, 1 ) . '%' );
							} else {
								echo '—';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="li-pagination">
				<div class="li-pagination__info">
					<?php
					$first_item = ( $page - 1 ) * $per_page + 1;
					$last_item  = min( $page * $per_page, $total );
					printf(
						/* translators: %1$s: first item, %2$s: last item, %3$s: total items */
						esc_html__( 'Showing %1$s–%2$s of %3$s items', 'labor-intel' ),
						'<strong>' . esc_html( number_format_i18n( $first_item ) ) . '</strong>',
						'<strong>' . esc_html( number_format_i18n( $last_item ) ) . '</strong>',
						'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>'
					);
					?>
				</div>
				<div class="li-pagination__links">
					<?php
					$base_url = admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=dim_sites' );
					echo wp_kses_post(
						paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%', $base_url ),
							'format'    => '',
							'prev_text' => '&laquo; ' . __( 'Prev', 'labor-intel' ),
							'next_text' => __( 'Next', 'labor-intel' ) . ' &raquo;',
							'total'     => $total_pages,
							'current'   => $page,
							'mid_size'  => 2,
							'end_size'  => 1,
						) )
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
