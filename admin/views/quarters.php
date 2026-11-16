<?php
/**
 * NPCC quarters list.
 *
 * @var object[] $quarters Quarters, newest first.
 * @var array    $counts   Quarter id => number of force rows.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<h1 class="wp-heading-inline">NPCC Quarters</h1>
<hr class="wp-header-end">
<p>One quarter is one NPCC Firearms Licensing report (all 43 forces). It drives the leaderboard and the national figures. The <strong>% within SLA</strong> is calculated, never typed. Add the force rows with <a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_IMPORT ) ); ?>">Import</a> (the <em>NPCC Data</em> sheet of the template).</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gco-pd-inline-form">
	<input type="hidden" name="action" value="gco_pd_create_quarter">
	<?php wp_nonce_field( 'gco_pd_create_quarter' ); ?>
	<label for="gco-pd-quarter-label"><strong>New quarter</strong></label>
	<input type="text" id="gco-pd-quarter-label" name="label" placeholder="2026/27 Q1" pattern="\d{4}/\d{2} Q[1-4]" maxlength="10" size="12" required>
	<label for="gco-pd-quarter-date">Data drawn on</label>
	<input type="date" id="gco-pd-quarter-date" name="data_date">
	<button class="button button-primary">Create draft quarter</button>
</form>

<table class="widefat striped gco-pd-table">
	<thead>
		<tr><th>Quarter</th><th>Data date</th><th>Status</th><th>Forces</th><th>Actions</th></tr>
	</thead>
	<tbody>
	<?php if ( ! $quarters ) : ?>
		<tr><td colspan="5">No quarters yet.</td></tr>
	<?php endif; ?>
	<?php foreach ( $quarters as $quarter ) : ?>
		<?php $is_published = 'published' === $quarter->status; ?>
		<tr>
			<td><strong><?php echo esc_html( $quarter->label ); ?></strong></td>
			<td><?php echo $quarter->data_date ? esc_html( mysql2date( 'j M Y', $quarter->data_date ) ) : '&mdash;'; ?></td>
			<td><span class="gco-pd-badge gco-pd-badge--<?php echo esc_attr( $quarter->status ); ?>"><?php echo esc_html( ucfirst( $quarter->status ) ); ?></span></td>
			<td><?php echo esc_html( $counts[ (int) $quarter->id ] . ' of 43' ); ?></td>
			<td class="gco-pd-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gco_pd_quarter_status">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $quarter->id ); ?>">
					<input type="hidden" name="to" value="<?php echo $is_published ? 'unpublish' : 'publish'; ?>">
					<?php wp_nonce_field( 'gco_pd_quarter_status' ); ?>
					<button class="button button-small <?php echo $is_published ? '' : 'button-primary'; ?>"><?php echo $is_published ? 'Unpublish' : 'Publish'; ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gco_pd_export_npcc">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $quarter->id ); ?>">
					<?php wp_nonce_field( 'gco_pd_export_npcc' ); ?>
					<button class="button button-small">Download CSV</button>
				</form>
				<?php if ( ! $is_published ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Delete draft quarter <?php echo esc_js( $quarter->label ); ?> and its force rows? This cannot be undone.');">
					<input type="hidden" name="action" value="gco_pd_delete_quarter">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $quarter->id ); ?>">
					<?php wp_nonce_field( 'gco_pd_delete_quarter' ); ?>
					<button class="button button-small button-link-delete">Delete</button>
				</form>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<?php if ( $quarters ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gco-pd-inline-form">
	<input type="hidden" name="action" value="gco_pd_export_npcc">
	<input type="hidden" name="id" value="0">
	<?php wp_nonce_field( 'gco_pd_export_npcc' ); ?>
	<button class="button">Download all quarters (CSV backup)</button>
</form>
<?php endif; ?>
