<?php
/**
 * Compression Model data view (processed results).
 *
 * @package LaborIntel
 * @since   1.0.0
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
			esc_html__( '%1$s — %2$s', 'labor-intel' ),
			esc_html( $file_type_config['label'] ),
			esc_html( $workspace->name )
		);
		?>
	</h1>
	<hr class="wp-header-end">

	<div class="li-data-summary">
		<?php
		printf(
			esc_html__( 'Total records: %s', 'labor-intel' ),
			'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>'
		);
		?>
	</div>

	<div class="li-data-summary" style="margin-top: 8px; font-size: 15px;">
		<?php
		printf(
			esc_html__( 'Total Compression Exposure ($): %s', 'labor-intel' ),
			'<strong>' . esc_html( '$' . number_format( $total_exposure, 0 ) ) . '</strong>'
		);
		?>
	</div>

	<?php if ( empty( $rows ) ) : ?>
		<div class="li-empty-state">
			<p><?php esc_html_e( 'No compression data found. Process the workspace to generate compression analysis.', 'labor-intel' ); ?></p>
		</div>
	<?php else : ?>
		<div style="overflow-x: auto;">
			<table class="wp-list-table striped li-data-table" style="table-layout: auto; width: 100%;">
				<thead>
					<tr>
						<th scope="col" style="width:100px;"><?php esc_html_e( 'Employee ID', 'labor-intel' ); ?></th>
						<th scope="col" style="width:80px;"><?php esc_html_e( 'Site', 'labor-intel' ); ?></th>
						<th scope="col" style="width:120px;"><?php esc_html_e( 'Job Title', 'labor-intel' ); ?></th>
						<th scope="col" style="width:100px;"><?php esc_html_e( 'Tenure (Months)', 'labor-intel' ); ?></th>
						<th scope="col" style="width:90px;"><?php esc_html_e( 'Pay Rate', 'labor-intel' ); ?></th>
						<th scope="col" style="width:110px;"><?php esc_html_e( 'Total Paid Hours', 'labor-intel' ); ?></th>
						<th scope="col" style="width:140px;"><?php esc_html_e( 'Key Site Role', 'labor-intel' ); ?></th>
						<th scope="col" style="width:120px;"><?php esc_html_e( 'New Hire Rate Avg', 'labor-intel' ); ?></th>
						<th scope="col" style="width:130px;"><?php esc_html_e( 'Incumbent Rate Avg', 'labor-intel' ); ?></th>
						<th scope="col" style="width:120px;"><?php esc_html_e( 'Compression Gap', 'labor-intel' ); ?></th>
						<th scope="col" style="width:140px;"><?php esc_html_e( 'Compression Exposure', 'labor-intel' ); ?></th>
						<th scope="col" style="width:120px;"><?php esc_html_e( 'Compressed Flag', 'labor-intel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->employee_id ?? '—' ); ?></td>
						<td><?php echo esc_html( $row->site ?? '—' ); ?></td>
						<td><?php echo esc_html( $row->job_title ?? '—' ); ?></td>
						<td><?php echo null !== $row->tenure_months ? esc_html( $row->tenure_months ) : '—'; ?></td>
						<td><?php echo null !== $row->pay_rate ? esc_html( '$' . number_format( (float) $row->pay_rate, 2 ) ) : '—'; ?></td>
						<td><?php echo esc_html( number_format( (float) $row->total_paid_hours, 2 ) ); ?></td>
						<td><strong><?php echo esc_html( ( $row->site ?? '' ) . ' | ' . ( $row->job_title ?? '' ) ); ?></strong></td>
						<td><?php echo esc_html( '$' . number_format( (float) $row->newhire_rate_avg, 2 ) ); ?></td>
						<td><?php echo esc_html( '$' . number_format( (float) $row->incumbent_rate_avg, 2 ) ); ?></td>
						<td><?php echo esc_html( '$' . number_format( (float) $row->compression_gap, 2 ) ); ?></td>
						<td><?php echo esc_html( '$' . number_format( (float) $row->compression_exposure, 0 ) ); ?></td>
						<td>
							 <?php echo esc_html( $row->compressed_flag ); ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="li-pagination">
				<div class="li-pagination__info">
					<?php
					$first_item = ( $page - 1 ) * $per_page + 1;
					$last_item  = min( $page * $per_page, $total );
					printf(
						esc_html__( 'Showing %1$s–%2$s of %3$s items', 'labor-intel' ),
						'<strong>' . esc_html( number_format_i18n( $first_item ) ) . '</strong>',
						'<strong>' . esc_html( number_format_i18n( $last_item ) ) . '</strong>',
						'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>'
					);
					?>
				</div>
				<div class="li-pagination__links">
					<?php
					$base_url = admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=compression_model' );
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
