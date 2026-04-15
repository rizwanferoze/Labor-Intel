<?php
/**
 * Single workspace table row partial.
 *
 * @package LaborIntel
 * @since   1.0.0
 *
 * @var object $ws Workspace row object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<tr data-workspace-id="<?php echo esc_attr( $ws->id ); ?>">
	<td class="column-name">
		<strong>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $ws->id ) ); ?>">
				<?php echo esc_html( $ws->name ); ?>
			</a>
		</strong>
	</td>
	<td class="column-description">
		<?php echo esc_html( wp_trim_words( $ws->description, 15, '…' ) ); ?>
	</td>
	<td class="column-status">
		<span class="li-status li-status--<?php echo esc_attr( $ws->status ); ?>">
			<?php
			$status_labels = array(
				'pending'    => __( 'Pending', 'labor-intel' ),
				'ready_for_processing' => __( 'Ready for Processing', 'labor-intel' ),
				'processing' => __( 'Processing', 'labor-intel' ),
				'processed'  => __( 'Processed', 'labor-intel' ),
			);
			echo esc_html( isset( $status_labels[ $ws->status ] ) ? $status_labels[ $ws->status ] : ucfirst( $ws->status ) );
			?>
		</span>
	</td>
	<td class="column-date">
		<?php
		echo esc_html(
			date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ws->created_at ) )
		);
		?>
	</td>
	<td class="column-actions">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=labor-intel&workspace_id=' . $ws->id ) ); ?>"
			class="button button-small button-primary">
			<?php esc_html_e( 'Open', 'labor-intel' ); ?>
		</a>
		<button type="button" class="button button-small li-edit-workspace"
			data-id="<?php echo esc_attr( $ws->id ); ?>"
			data-name="<?php echo esc_attr( $ws->name ); ?>"
			data-description="<?php echo esc_attr( $ws->description ); ?>">
			<?php esc_html_e( 'Edit', 'labor-intel' ); ?>
		</button>
		<button type="button" class="button button-small button-link-delete li-delete-workspace"
			data-id="<?php echo esc_attr( $ws->id ); ?>">
			<?php esc_html_e( 'Delete', 'labor-intel' ); ?>
		</button>
	</td>
</tr>
