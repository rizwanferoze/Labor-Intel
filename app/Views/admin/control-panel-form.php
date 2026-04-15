<?php
/**
 * Control Panel form view.
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
		<?php printf( esc_html__( 'Control Panel — %s', 'labor-intel' ), esc_html( $workspace->name ) ); ?>
	</h1>
	<hr class="wp-header-end">

	<div id="li-notices"></div>

	<form id="li-control-panel-form" class="li-settings-form">
		<input type="hidden" name="workspace_id" value="<?php echo esc_attr( $workspace->id ); ?>">

		<table class="form-table li-form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="contribution_margin_pct"><?php esc_html_e( 'Contribution Margin %', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" id="contribution_margin_pct" name="contribution_margin_pct" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->contribution_margin_pct ) : ''; ?>"
							placeholder="48.00">
						<p class="description"><?php esc_html_e( 'Used to translate recoverable leakage into EBITDA.', 'labor-intel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leakage_recovery_pct"><?php esc_html_e( 'Leakage Recovery %', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" id="leakage_recovery_pct" name="leakage_recovery_pct" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->leakage_recovery_pct ) : ''; ?>"
							placeholder="65.00">
						<p class="description"><?php esc_html_e( 'Portion of modeled leakage assumed recoverable via intervention.', 'labor-intel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="retention_intervention_pct"><?php esc_html_e( 'Retention Intervention %', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" id="retention_intervention_pct" name="retention_intervention_pct" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->retention_intervention_pct ) : ''; ?>"
							placeholder="40.00">
						<p class="description"><?php esc_html_e( 'Portion of modeled retention risk assumed avoidable.', 'labor-intel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="compression_risk_weight"><?php esc_html_e( 'Compression Risk Weight', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" id="compression_risk_weight" name="compression_risk_weight" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->compression_risk_weight ) : ''; ?>"
							placeholder="70.00">
						<p class="description"><?php esc_html_e( 'Weights compression exposure ($) for risk-based prioritization.', 'labor-intel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="compression_prevention_pct"><?php esc_html_e( 'Compression Prevention %', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" id="compression_prevention_pct" name="compression_prevention_pct" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->compression_prevention_pct ) : ''; ?>"
							placeholder="50.00">
						<p class="description"><?php esc_html_e( 'Portion of compression-driven reactive adjustments avoided.', 'labor-intel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="replacement_cost_default"><?php esc_html_e( 'Replacement Cost Default ($)', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" id="replacement_cost_default" name="replacement_cost_default" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->replacement_cost_default ) : ''; ?>"
							placeholder="27000">
						<p class="description"><?php esc_html_e( 'Default fully loaded replacement cost per separation (USD).', 'labor-intel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ot_benchmark_default"><?php esc_html_e( 'OT Benchmark Default %', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" id="ot_benchmark_default" name="ot_benchmark_default" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->ot_benchmark_default ) : ''; ?>"
							placeholder="10.00">
						<p class="description"><?php esc_html_e( 'Fallback OT ratio benchmark when role benchmark not available.', 'labor-intel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ot_premium_factor"><?php esc_html_e( 'OT Premium Factor', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" id="ot_premium_factor" name="ot_premium_factor" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->ot_premium_factor ) : ''; ?>"
							placeholder="50.00">
						<p class="description"><?php esc_html_e( 'Incremental OT premium as fraction of base rate (e.g. 50 = time-and-a-half).', 'labor-intel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="scheduling_flex_band_pct"><?php esc_html_e( 'Scheduling Flex Band %', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" id="scheduling_flex_band_pct" name="scheduling_flex_band_pct" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->scheduling_flex_band_pct ) : ''; ?>"
							placeholder="10.00">
						<p class="description"><?php esc_html_e( 'Tolerance band around site-role average paid hours before schedule inefficiency is flagged.', 'labor-intel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="scheduling_coverage_capture_pct"><?php esc_html_e( 'Scheduling Coverage Capture %', 'labor-intel' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="number" step="0.01" id="scheduling_coverage_capture_pct" name="scheduling_coverage_capture_pct" class="regular-text" required
							value="<?php echo $record ? esc_attr( $record->scheduling_coverage_capture_pct ) : ''; ?>"
							placeholder="35.00">
						<p class="description"><?php esc_html_e( 'Portion of modeled coverage-gap EBITDA assumed recoverable via schedule redesign / better coverage.', 'labor-intel' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" id="li-save-control-panel">
				<?php esc_html_e( 'Save Control Panel', 'labor-intel' ); ?>
			</button>
		</p>
	</form>
</div>
