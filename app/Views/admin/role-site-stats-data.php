<?php
/**
 * Role Site Stats data view.
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
<?php if ( 'uploaded' === $view_context ) : ?>
<a href="<?php echo esc_url( $download_url ); ?>" class="page-title-action">
<span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: -2px;"></span>
<?php esc_html_e( 'Download File', 'labor-intel' ); ?>
</a>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&file_type=role_site_stats' ) ); ?>" class="page-title-action">
<?php esc_html_e( 'Re-upload', 'labor-intel' ); ?>
</a>
<?php endif; ?>
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
<p><?php esc_html_e( 'No data found. Upload a Role Site Stats file to get started.', 'labor-intel' ); ?></p>
</div>
<?php else : ?>
<div style="overflow-x: auto;">
<table class="wp-list-table striped li-data-table" style="table-layout: auto; width: 100%;">
<thead>
<tr>
<th scope="col" style="width:160px;"><?php esc_html_e( 'Key Site Role', 'labor-intel' ); ?></th>
<th scope="col" style="width:100px;"><?php esc_html_e( 'Location ID', 'labor-intel' ); ?></th>
<th scope="col" style="width:120px;"><?php esc_html_e( 'Job Title', 'labor-intel' ); ?></th>
<th scope="col" style="width:110px;"><?php esc_html_e( 'NewHire Rate Avg', 'labor-intel' ); ?></th>
<th scope="col" style="width:110px;"><?php esc_html_e( 'Incumbent Rate Avg', 'labor-intel' ); ?></th>
<th scope="col" style="width:100px;"><?php esc_html_e( 'Role Rate Avg', 'labor-intel' ); ?></th>
<?php if ( 'processed' === $view_context ) : ?>
<th scope="col" style="width:110px;"><?php esc_html_e( 'Role OT Benchmark', 'labor-intel' ); ?></th>
<th scope="col" style="width:110px;"><?php esc_html_e( 'Live NewHire Rate', 'labor-intel' ); ?></th>
<th scope="col" style="width:110px;"><?php esc_html_e( 'Live Incumbent Rate', 'labor-intel' ); ?></th>
<th scope="col" style="width:100px;"><?php esc_html_e( 'Live Role Rate', 'labor-intel' ); ?></th>
<?php endif; ?>
</tr>
</thead>
<tbody>
<?php foreach ( $rows as $row ) : ?>
<tr>
<td><strong><?php echo esc_html( ( $row->location_id ?? '' ) . ' | ' . ( $row->job_title ?? '' ) ); ?></strong></td>
<td><?php echo esc_html( $row->location_id ?? '—' ); ?></td>
<td><?php echo esc_html( $row->job_title ?? '—' ); ?></td>
<td><?php echo null !== $row->newhire_rate_avg ? esc_html( '$' . number_format( (float) $row->newhire_rate_avg, 2 ) ) : '$0.00'; ?></td>
<td><?php echo null !== $row->incumbent_rate_avg ? esc_html( '$' . number_format( (float) $row->incumbent_rate_avg, 2 ) ) : '$0.00'; ?></td>
<td><?php echo null !== $row->rolerate_avg ? esc_html( '$' . number_format( (float) $row->rolerate_avg, 2 ) ) : '$0.00'; ?></td>
<?php if ( 'processed' === $view_context ) : ?>
<td><?php echo null !== $row->role_ot_benchmark ? esc_html( '$' . number_format( (float) $row->role_ot_benchmark, 2 ) ) : '—'; ?></td>
<td><?php echo null !== $row->live_newhire_rate ? esc_html( '$' . number_format( (float) $row->live_newhire_rate, 2 ) ) : '—'; ?></td>
<td><?php echo null !== $row->live_incumbent_rate ? esc_html( '$' . number_format( (float) $row->live_incumbent_rate, 2 ) ) : '—'; ?></td>
<td><?php echo null !== $row->live_role_rate ? esc_html( '$' . number_format( (float) $row->live_role_rate, 2 ) ) : '—'; ?></td>
<?php endif; ?>
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
$base_url = admin_url( 'admin.php?page=labor-intel&workspace_id=' . $workspace->id . '&view_data=role_site_stats' . ( 'processed' === $view_context ? '&view_context=processed' : '' ) );
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
