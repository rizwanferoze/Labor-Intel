<?php
/**
 * Clean Data view (processed results).
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

	<?php if ( empty( $rows ) ) : ?>
		<div class="li-empty-state">
			<p><?php esc_html_e( 'No clean data found. Process the workspace to generate clean data.', 'labor-intel' ); ?></p>
		</div>
	<?php else : ?>
		<div style="overflow-x: auto;">
			<table class="wp-list-table striped li-data-table" style="table-layout: auto; width: 100%;">
				<thead>
					<tr>
						<th scope="col" style="width:100px;"><?php esc_html_e( 'Employee ID', 'labor-intel' ); ?></th>
						<th scope="col" style="width:80px;"><?php esc_html_e( 'Site', 'labor-intel' ); ?></th>
						<th scope="col" style="width:120px;"><?php esc_html_e( 'Job Title', 'labor-intel' ); ?></th>
						<th scope="col" style="width:80px;"><?php esc_html_e( 'Job Level', 'labor-intel' ); ?></th>
						<th scope="col" style="width:100px;"><?php esc_html_e( 'Hire Date', 'labor-intel' ); ?></th>
						<th scope="col" style="width:100px;"><?php esc_html_e( 'Tenure (Months)', 'labor-intel' ); ?></th>
						<th scope="col" style="width:90px;"><?php esc_html_e( 'Pay Rate', 'labor-intel' ); ?></th>
						<th scope="col" style="width:100px;"><?php esc_html_e( 'Regular Hours', 'labor-intel' ); ?></th>
						<th scope="col" style="width:100px;"><?php esc_html_e( 'Overtime Hours', 'labor-intel' ); ?></th>
						<th scope="col" style="width:100px;"><?php esc_html_e( 'Premium Hours', 'labor-intel' ); ?></th>
						<th scope="col" style="width:110px;"><?php esc_html_e( 'Total Paid Hours', 'labor-intel' ); ?></th>
						<th scope="col" style="width:80px;"><?php esc_html_e( 'OT Ratio', 'labor-intel' ); ?></th>
						<th scope="col" style="width:120px;"><?php esc_html_e( 'Annualized Hours', 'labor-intel' ); ?></th>
						<th scope="col" style="width:140px;"><?php esc_html_e( 'Key Site Role', 'labor-intel' ); ?></th>
						<th scope="col" style="width:100px;"><?php esc_html_e( 'Region', 'labor-intel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) :
						$total_paid   = (float) $row->total_paid_hours;
						$overtime     = (float) $row->overtime_hours;
						$ot_ratio_pct = (float) $row->ot_ratio;
					?>
					<tr>
						<td><?php echo esc_html( $row->employee_id ?? '—' ); ?></td>
						<td><?php echo esc_html( $row->site ?? '—' ); ?></td>
						<td><?php echo esc_html( $row->job_title ?? '—' ); ?></td>
						<td><?php echo esc_html( $row->job_level ?? '—' ); ?></td>
						<td><?php echo $row->hire_date ? esc_html( date( 'm/d/Y', strtotime( $row->hire_date ) ) ) : '—'; ?></td>
						<td><?php echo null !== $row->tenure_months ? esc_html( $row->tenure_months ) : '—'; ?></td>
						<td><?php echo null !== $row->pay_rate ? esc_html( '$' . number_format( (float) $row->pay_rate, 2 ) ) : '—'; ?></td>
						<td><?php echo esc_html( number_format( (float) $row->regular_hours, 2 ) ); ?></td>
						<td><?php echo esc_html( number_format( $overtime, 2 ) ); ?></td>
						<td><?php echo esc_html( number_format( (float) $row->premium_hours, 2 ) ); ?></td>
						<td><?php echo esc_html( number_format( $total_paid, 2 ) ); ?></td>
						<td><?php echo esc_html( number_format( $ot_ratio_pct, 1 ) . '%' ); ?></td>
						<td><?php echo esc_html( number_format( $total_paid, 2 ) ); ?></td>
						<td><strong><?php echo esc_html( ( $row->site ?? '' ) . ' | ' . ( $row->job_title ?? '' ) ); ?></strong></td>
						<td><?php echo esc_html( $row->region ?? '—' ); ?></td>
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
					$base_url = admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=clean_data' );
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
