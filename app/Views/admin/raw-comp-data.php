<?php
/**
 * Raw Comp data view — paginated table of imported compensation data.
 *
 * @package LaborIntel
 * @since   1.0.0
 *
 * @var object $workspace        The workspace object.
 * @var array  $file_type_config File type config (label, icon, etc.).
 * @var array  $rows             Current page of raw_comp rows (with joined employee_id).
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
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&file_type=raw_comp' ) ); ?>" class="page-title-action">
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
			<p><?php esc_html_e( 'No data found. Upload a Raw Comp file to get started.', 'labor-intel' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped li-data-table">
			<thead>
				<tr>
					<th scope="col" style="width:4%;">#</th>
					<th scope="col" style="width:14%;"><?php esc_html_e( 'Employee ID', 'labor-intel' ); ?></th>
					<th scope="col" style="width:14%;"><?php esc_html_e( 'Pay Rate', 'labor-intel' ); ?></th>
					<th scope="col" style="width:14%;"><?php esc_html_e( 'Pay Type', 'labor-intel' ); ?></th>
					<th scope="col" style="width:14%;"><?php esc_html_e( 'Incentive Pay', 'labor-intel' ); ?></th>
					<th scope="col" style="width:14%;"><?php esc_html_e( 'Shift Diff', 'labor-intel' ); ?></th>
					<th scope="col" style="width:14%;"><?php esc_html_e( 'Bonus YTD', 'labor-intel' ); ?></th>
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
						<td><strong><?php echo esc_html( $row->employee_id ?? '—' ); ?></strong></td>
						<td><?php echo null !== $row->pay_rate ? esc_html( '$' . number_format( (float) $row->pay_rate, 2 ) ) : '—'; ?></td>
						<td><?php echo ! empty( $row->pay_type ) ? esc_html( $row->pay_type ) : '—'; ?></td>
						<td><?php echo null !== $row->incentive_pay ? esc_html( '$' . number_format( (float) $row->incentive_pay, 2 ) ) : '—'; ?></td>
						<td><?php echo null !== $row->shift_diff ? esc_html( '$' . number_format( (float) $row->shift_diff, 2 ) ) : '—'; ?></td>
						<td><?php echo null !== $row->bonus_ytd ? esc_html( '$' . number_format( (float) $row->bonus_ytd, 2 ) ) : '—'; ?></td>
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
					$base_url = admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=raw_comp' );
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
