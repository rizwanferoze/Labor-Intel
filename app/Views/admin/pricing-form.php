<?php
/**
 * Pricing form view.
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
		<a href="<?php echo esc_url( $back_url ); ?>" class="li-back-link" title="<?php esc_attr_e( 'Back to Workspace', 'labor-intel' ); ?>">&larr;</a>
		<?php printf( esc_html__( 'Pricing — %s', 'labor-intel' ), esc_html( $workspace->name ) ); ?>
	</h1>
	<hr class="wp-header-end">

	<div id="li-notices"></div>

	<form id="li-pricing-form" class="li-settings-form">
		<input type="hidden" name="workspace_id" value="<?php echo esc_attr( $workspace->id ); ?>">

		<table class="form-table li-form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="employee_count"><?php esc_html_e( 'Employee Count', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="1" min="1" id="employee_count" name="employee_count" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->employee_count ) : ''; ?>"
							placeholder="2500">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="site_count"><?php esc_html_e( 'Site Count', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="1" min="1" id="site_count" name="site_count" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->site_count ) : ''; ?>"
							placeholder="50">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="pricing_model"><?php esc_html_e( 'Pricing Model', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<select id="pricing_model" name="pricing_model" class="regular-text" required>
							<option value=""><?php esc_html_e( '— Select —', 'labor-intel' ); ?></option>
							<option value="PEPM" <?php selected( $record ? $record->pricing_model : '', 'PEPM' ); ?>><?php esc_html_e( 'PEPM', 'labor-intel' ); ?></option>
							<option value="Site" <?php selected( $record ? $record->pricing_model : '', 'Site' ); ?>><?php esc_html_e( 'Site', 'labor-intel' ); ?></option>
							<option value="Value" <?php selected( $record ? $record->pricing_model : '', 'Value' ); ?>><?php esc_html_e( 'Value', 'labor-intel' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="pepm"><?php esc_html_e( 'PEPM ($/employee/month)', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" min="0" id="pepm" name="pepm" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->pepm ) : ''; ?>"
							placeholder="6.00">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="annual_site_fee"><?php esc_html_e( 'Annual Site Fee ($/site/year)', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" min="0" id="annual_site_fee" name="annual_site_fee" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->annual_site_fee ) : ''; ?>"
							placeholder="1800.00">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="value_fee_pct"><?php esc_html_e( 'Value Fee (% of EBITDA lift)', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" min="0" id="value_fee_pct" name="value_fee_pct" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->value_fee_pct ) : ''; ?>"
							placeholder="10.00">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="value_fee_cap"><?php esc_html_e( 'Value Fee Cap ($)', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" min="0" id="value_fee_cap" name="value_fee_cap" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->value_fee_cap ) : ''; ?>"
							placeholder="250000">
					</td>
				</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Calculated Metrics', 'labor-intel' ); ?></h3>
		<p class="description"><?php esc_html_e( 'These fields will be updated automatically after processing is complete.', 'labor-intel' ); ?></p>
		<table class="form-table li-form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="annual_platform_fee"><?php esc_html_e( 'Annual Platform Fee ($)', 'labor-intel' ); ?></label>
					</th>
					<td>
						<input type="text" id="annual_platform_fee" name="annual_platform_fee" class="regular-text" disabled
							value="<?php echo $record && $record->annual_platform_fee ? esc_attr( number_format( $record->annual_platform_fee, 2 ) ) : ''; ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="modeled_ebitda_lift"><?php esc_html_e( 'Modeled EBITDA Lift ($)', 'labor-intel' ); ?></label>
					</th>
					<td>
						<input type="text" id="modeled_ebitda_lift" name="modeled_ebitda_lift" class="regular-text" disabled
							value="<?php echo $record && $record->modeled_ebitda_lift ? esc_attr( number_format( $record->modeled_ebitda_lift, 2 ) ) : ''; ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="roi_multiple"><?php esc_html_e( 'ROI Multiple (x)', 'labor-intel' ); ?></label>
					</th>
					<td>
						<input type="text" id="roi_multiple" name="roi_multiple" class="regular-text" disabled
							value="<?php echo $record && $record->roi_multiple ? esc_attr( $record->roi_multiple . 'x' ) : ''; ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="breakeven_months"><?php esc_html_e( 'Break-even Months', 'labor-intel' ); ?></label>
					</th>
					<td>
						<input type="text" id="breakeven_months" name="breakeven_months" class="regular-text" disabled
							value="<?php echo $record && $record->breakeven_months ? esc_attr( $record->breakeven_months ) : ''; ?>">
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" id="li-save-pricing">
				<?php esc_html_e( 'Save Pricing', 'labor-intel' ); ?>
			</button>
		</p>
	</form>
</div>
