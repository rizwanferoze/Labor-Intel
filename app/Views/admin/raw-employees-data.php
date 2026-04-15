<?php
/**
 * Raw Employees data view — paginated table of imported employee data.
 *
 * @package LaborIntel
 * @since   1.0.0
 *
 * @var object $workspace        The workspace object.
 * @var array  $file_type_config File type config (label, icon, etc.).
 * @var array  $rows             Current page of raw_employees rows (with joined dim data).
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
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&file_type=raw_employee' ) ); ?>" class="page-title-action">
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
			<p><?php esc_html_e( 'No data found. Upload a Raw Employees file to get started.', 'labor-intel' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped li-data-table">
			<thead>
				<tr>
					<th scope="col" style="width:4%;">#</th>
					<th scope="col" style="width:10%;"><?php esc_html_e( 'Employee ID', 'labor-intel' ); ?></th>
					<th scope="col" style="width:10%;"><?php esc_html_e( 'Site', 'labor-intel' ); ?></th>
					<th scope="col" style="width:12%;"><?php esc_html_e( 'Job Title', 'labor-intel' ); ?></th>
					<th scope="col" style="width:8%;"><?php esc_html_e( 'Job Level', 'labor-intel' ); ?></th>
					<th scope="col" style="width:10%;"><?php esc_html_e( 'Hire Date', 'labor-intel' ); ?></th>
					<th scope="col" style="width:8%;"><?php esc_html_e( 'Status', 'labor-intel' ); ?></th>
					<th scope="col" style="width:10%;"><?php esc_html_e( 'Term Date', 'labor-intel' ); ?></th>
					<th scope="col" style="width:10%;"><?php esc_html_e( 'Manager ID', 'labor-intel' ); ?></th>
					<th scope="col" style="width:8%;"><?php esc_html_e( 'Tenure (Mo)', 'labor-intel' ); ?></th>
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
						<td><strong><?php echo esc_html( $row->employee_id ); ?></strong></td>
						<td><?php echo esc_html( $row->location_id ?? '—' ); ?></td>
						<td><?php echo esc_html( $row->job_title ?? '—' ); ?></td>
						<td><?php echo esc_html( $row->job_level ?? '—' ); ?></td>
						<td>
							<?php
							if ( ! empty( $row->hire_date ) ) {
								echo esc_html( date_i18n( 'm/d/Y', strtotime( $row->hire_date ) ) );
							} else {
								echo '—';
							}
							?>
						</td>
						<td>
							<?php if ( ! empty( $row->status ) ) : ?>
								<span class="li-status-badge li-status-badge--<?php echo esc_attr( strtolower( $row->status ) ); ?>">
									<?php echo esc_html( $row->status ); ?>
								</span>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( ! empty( $row->termination_date ) ) {
								echo esc_html( date_i18n( 'm/d/Y', strtotime( $row->termination_date ) ) );
							} else {
								echo '—';
							}
							?>
						</td>
						<td><?php echo esc_html( $row->manager_id ?? '—' ); ?></td>
						<td><?php echo null !== $row->tenure_months ? esc_html( $row->tenure_months ) : '—'; ?></td>
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
					$base_url = admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=raw_employee' );
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
