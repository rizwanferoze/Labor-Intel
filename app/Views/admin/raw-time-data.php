<?php
/**
 * Raw Time data view.
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
<a href="<?php echo esc_url( $download_url ); ?>" class="page-title-action">
<span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: -2px;"></span>
<?php esc_html_e( 'Download File', 'labor-intel' ); ?>
</a>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&file_type=raw_time' ) ); ?>" class="page-title-action">
<?php esc_html_e( 'Re-upload', 'labor-intel' ); ?>
</a>
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
<p><?php esc_html_e( 'No data found. Upload a Raw Time file to get started.', 'labor-intel' ); ?></p>
</div>
<?php else : ?>
<table class="wp-list-table widefat fixed striped li-data-table">
<thead>
<tr>
<th scope="col" style="width:4%;">#</th>
<th scope="col" style="width:14%;"><?php esc_html_e( 'Employee ID', 'labor-intel' ); ?></th>
<th scope="col" style="width:14%;"><?php esc_html_e( 'Period End Date', 'labor-intel' ); ?></th>
<th scope="col" style="width:14%;"><?php esc_html_e( 'Regular Hours', 'labor-intel' ); ?></th>
<th scope="col" style="width:14%;"><?php esc_html_e( 'Overtime Hours', 'labor-intel' ); ?></th>
<th scope="col" style="width:14%;"><?php esc_html_e( 'Premium Hours', 'labor-intel' ); ?></th>
<th scope="col" style="width:14%;"><?php esc_html_e( 'Total Paid Hours', 'labor-intel' ); ?></th>
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
<td>
<?php
if ( ! empty( $row->period_end_date ) ) {
echo esc_html( date_i18n( 'm/d/Y', strtotime( $row->period_end_date ) ) );
} else {
echo '—';
}
?>
</td>
<td><?php echo null !== $row->regular_hours ? esc_html( number_format( (float) $row->regular_hours, 1 ) ) : '—'; ?></td>
<td><?php echo null !== $row->overtime_hours ? esc_html( number_format( (float) $row->overtime_hours, 1 ) ) : '—'; ?></td>
<td><?php echo null !== $row->premium_hours ? esc_html( number_format( (float) $row->premium_hours, 1 ) ) : '—'; ?></td>
<td><?php echo null !== $row->total_paid_hours ? esc_html( number_format( (float) $row->total_paid_hours, 1 ) ) : '—'; ?></td>
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
esc_html__( 'Showing %1$s–%2$s of %3$s items', 'labor-intel' ),
'<strong>' . esc_html( number_format_i18n( $first_item ) ) . '</strong>',
'<strong>' . esc_html( number_format_i18n( $last_item ) ) . '</strong>',
'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>'
);
?>
</div>
<div class="li-pagination__links">
<?php
$base_url = admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=raw_time' );
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
