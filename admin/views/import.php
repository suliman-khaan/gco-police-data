<?php
/**
 * Import: upload, then preview and confirm.
 *
 * @var string     $token   Preview token ('' on the upload step).
 * @var array|null $preview Stored preview.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prints one preview table.
 *
 * @param string $title   Heading.
 * @param array  $rows    Prepared rows.
 * @param array  $counts  State counts.
 * @param string $period  Column title for round/quarter.
 */
$gco_pd_table = static function ( $title, array $rows, array $counts, $period ) {
	if ( ! $rows ) {
		return;
	}
	?>
	<h2><?php echo esc_html( $title ); ?></h2>
	<p class="gco-pd-counts">
		<span class="gco-pd-badge gco-pd-badge--new"><?php echo esc_html( $counts['new'] ); ?> new</span>
		<span class="gco-pd-badge gco-pd-badge--changed"><?php echo esc_html( $counts['changed'] ); ?> changed</span>
		<span class="gco-pd-badge gco-pd-badge--unchanged"><?php echo esc_html( $counts['unchanged'] ); ?> unchanged</span>
		<span class="gco-pd-badge gco-pd-badge--error"><?php echo esc_html( $counts['error'] ); ?> with errors</span>
	</p>
	<div class="gco-pd-scroll">
	<table class="widefat striped gco-pd-table">
		<thead><tr><th>Row</th><th>Force</th><th><?php echo esc_html( $period ); ?></th><th>Result</th><th>Details</th></tr></thead>
		<tbody>
		<?php foreach ( $rows as $row ) : ?>
			<tr class="gco-pd-state-<?php echo esc_attr( $row['state'] ); ?>">
				<td><?php echo esc_html( (string) $row['row_num'] ); ?></td>
				<td><?php echo esc_html( $row['force_name'] ); ?></td>
				<td><?php echo esc_html( $row['period'] ); ?></td>
				<td><span class="gco-pd-badge gco-pd-badge--<?php echo esc_attr( $row['state'] ); ?>"><?php echo esc_html( ucfirst( $row['state'] ) ); ?></span></td>
				<td>
					<?php foreach ( $row['errors'] as $message ) : ?>
						<div class="gco-pd-error"><?php echo esc_html( $message ); ?></div>
					<?php endforeach; ?>
					<?php foreach ( $row['warnings'] as $message ) : ?>
						<div class="gco-pd-warning"><?php echo esc_html( $message ); ?></div>
					<?php endforeach; ?>
					<?php if ( $row['changed'] ) : ?>
						<div class="description">Changes: <?php echo esc_html( implode( ', ', array_map( static fn( $f ) => str_replace( '_', ' ', $f ), $row['changed'] ) ) ); ?></div>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php
};
?>
<h1>Import</h1>

<?php if ( ! $preview ) : ?>
	<p>Upload the <strong>GCO Police FOI Data Template</strong> (<code>.xlsx</code>) or a <code>.csv</code> with the same column headers. The file can contain the <em>FOI Data</em> sheet, the <em>NPCC Data</em> sheet, or both. You will see a preview first; <strong>nothing is saved until you confirm</strong>, and imports only ever write to <strong>drafts</strong>.</p>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="gco_pd_import_upload">
		<?php wp_nonce_field( 'gco_pd_import_upload' ); ?>
		<p>
			<input type="file" name="file" accept=".xlsx,.csv" required>
			<button class="button button-primary">Upload and preview</button>
		</p>
		<p class="description">Maximum 2&nbsp;MB and 1,000 data rows. Keep the column headers exactly as in the template. Forces are matched by name or alias (GMP, TVP, Met Police and so on). Put each FOI letter in the media library first, then the <em>PDF File Name</em> column links it automatically.</p>
	</form>
<?php else : ?>
	<h2>Preview of <?php echo esc_html( $preview['file'] ); ?></h2>

	<?php foreach ( $preview['errors'] as $message ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $message ); ?></p></div>
	<?php endforeach; ?>
	<?php foreach ( $preview['notices'] as $message ) : ?>
		<div class="notice notice-info inline"><p><?php echo esc_html( $message ); ?></p></div>
	<?php endforeach; ?>

	<?php
	$gco_pd_table( 'FOI responses', $preview['foi'], $preview['counts']['foi'], 'Round' );
	$gco_pd_table( 'NPCC figures', $preview['npcc'], $preview['counts']['npcc'], 'Quarter' );

	$writable = $preview['counts']['foi']['new'] + $preview['counts']['foi']['changed'] + $preview['counts']['npcc']['new'] + $preview['counts']['npcc']['changed'];
	$errored  = $preview['counts']['foi']['error'] + $preview['counts']['npcc']['error'];
	?>

	<?php if ( $errored ) : ?>
		<p><strong>Rows with errors will be skipped.</strong> Fix them in your file and upload again to include them.</p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gco-pd-inline-form">
		<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
		<?php wp_nonce_field( 'gco_pd_import_commit' ); ?>
		<input type="hidden" name="action" value="gco_pd_import_commit">
		<button class="button button-primary button-large" <?php disabled( ! $writable || ! empty( $preview['errors'] ) ); ?>>
			<?php echo $writable ? esc_html( sprintf( 'Save %d row(s) as draft', $writable ) ) : 'Nothing to save'; ?>
		</button>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gco-pd-inline-form">
		<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
		<?php wp_nonce_field( 'gco_pd_import_cancel' ); ?>
		<input type="hidden" name="action" value="gco_pd_import_cancel">
		<button class="button">Cancel</button>
	</form>
<?php endif; ?>
