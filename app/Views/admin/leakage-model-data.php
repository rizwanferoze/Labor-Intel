<?php
/**
 * Leakage Model data view (processed results).
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
			esc_html__( 'Total OT Leakage ($): %s', 'labor-intel' ),
			'<strong>' . esc_html( '$' . number_format( $total_leakage, 0 ) ) . '</strong>'
		);
		?>
	</div>

	<div class="li-data-summary" style="margin-top: 8px; font-size: 15px;">
		<?php
		printf(
			esc_html__( 'Recoverable Leakage ($): %s', 'labor-intel' ),
			'<strong>' . esc_html( '$' . number_format( $recoverable_leakage, 0 ) ) . '</strong>'
		);
		?>
	</div>

	<?php if ( empty( $rows ) ) : ?>
		<div class="li-empty-state">
			<p><?php esc_html_e( 'No leakage data found. Process the workspace to generate leakage analysis.', 'labor-intel' ); ?></p>
		</div>
	<?php else : ?>
		<div style="overflow-x: auto;">
			<table class="wp-list-table striped li-data-table" style="table-layout: auto; width: 100%;">
				<thead>
					<tr>
						<th scope="col" style="width:100px;"><?php esc_html_e( 'Employee ID', 'labor-intel' ); ?></th>
						<th scope="col" style="width:80px;"><?php esc_html_e( 'Site', 'labor-intel' ); ?></th>
						<th scope="col" style="width:120px;"><?php esc_html_e( 'Job Title', 'labor-intel' ); ?></th>
						<th scope="col" style="width:90px;"><?php esc_html_e( 'Pay Rate', 'labor-intel' ); ?></th>
						<th scope="col" style="width:110px;"><?php esc_html_e( 'Total Paid Hours', 'labor-intel' ); ?></th>
						<th scope="col" style="width:90px;"><?php esc_html_e( 'OT Ratio', 'labor-intel' ); ?></th>
						<th scope="col" style="width:130px;"><?php esc_html_e( 'Role OT Benchmark', 'labor-intel' ); ?></th>
						<th scope="col" style="width:90px;"><?php esc_html_e( 'Excess OT', 'labor-intel' ); ?></th>
						<th scope="col" style="width:130px;"><?php esc_html_e( 'OT Premium Factor', 'labor-intel' ); ?></th>
						<th scope="col" style="width:110px;"><?php esc_html_e( 'OT Leakage', 'labor-intel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->employee_id ?? '—' ); ?></td>
						<td><?php echo esc_html( $row->site ?? '—' ); ?></td>
						<td><?php echo esc_html( $row->job_title ?? '—' ); ?></td>
						<td><?php echo null !== $row->pay_rate ? esc_html( '$' . number_format( (float) $row->pay_rate, 2 ) ) : '—'; ?></td>
						<td><?php echo esc_html( number_format( (float) $row->total_paid_hours, 2 ) ); ?></td>
						<td><?php echo esc_html( number_format( (float) $row->ot_ratio, 1 ) . '%' ); ?></td>
						<td><?php echo esc_html( number_format( (float) $row->role_ot_benchmark, 1 ) . '%' ); ?></td>
						<td><?php echo esc_html( number_format( (float) $row->excess_ot, 1 ) . '%' ); ?></td>
						<td><?php echo esc_html( number_format( (float) $row->ot_premium_factor, 2 ) ); ?></td>
						<td><?php echo esc_html( '$' . number_format( (float) $row->ot_leakage, 0 ) ); ?></td>
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
					$base_url = admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=leakage_model' );
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
