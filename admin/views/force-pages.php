<?php
/**
 * Force Pages: status of every force page, and the shared defaults.
 *
 * @var array[] $rows   force, page_id, status, template, filled, updated.
 * @var array   $shared Shared defaults.
 * @var array   $fields Shared field definitions.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_view = isset( $_GET['view'] ) && 'shared' === $_GET['view'] ? 'shared' : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$gco_tick = static function ( $on ) {
	return $on ? '<span class="gco-pd-yes" aria-label="Yes">&#10003;</span>' : '<span class="gco-pd-no" aria-label="No">&ndash;</span>';
};
?>
<h1>Force Pages</h1>
<nav class="nav-tab-wrapper">
	<a class="nav-tab<?php echo 'list' === $gco_view ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_PAGES ) ); ?>">All forces</a>
	<a class="nav-tab<?php echo 'shared' === $gco_view ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_PAGES, array( 'view' => 'shared' ) ) ); ?>">Shared defaults</a>
</nav>

<?php if ( 'list' === $gco_view ) : ?>
	<?php
	$gco_live = count(
		array_filter(
			$rows,
			static function ( $row ) {
				return $row['template'] && 'publish' === $row['status'];
			}
		)
	);
	?>
	<p><?php echo esc_html( sprintf( '%1$d of %2$d forces have a published page using the force page template.', $gco_live, count( $rows ) ) ); ?> To add a force: create a page, add the shortcode <code>[gco_force_page]</code> (or the "Force page" WPBakery element), link it under <a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_FORCES ) ); ?>">Forces</a>, then fill in its content here.</p>
	<table class="widefat striped gco-pd-table gco-pd-pages">
		<thead>
			<tr>
				<th>Force</th><th>Page</th><th>Template</th><th>Hero</th><th>Contact</th><th>Area</th><th>GP</th><th>Quotes</th><th>FAQs</th><th>Last edited</th><th></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $rows as $row ) : ?>
			<?php $gco_force = $row['force']; ?>
			<tr>
				<td><strong><a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_PROFILE, array( 'force_id' => (int) $gco_force->id ) ) ); ?>"><?php echo esc_html( $gco_force->name ); ?></a></strong></td>
				<td>
					<?php if ( $row['page_id'] ) : ?>
						<a href="<?php echo esc_url( get_permalink( $row['page_id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $row['page_id'] ) ); ?></a>
						<?php if ( 'publish' !== $row['status'] ) : ?>
							<span class="gco-pd-badge gco-pd-badge--draft"><?php echo esc_html( (string) $row['status'] ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span class="gco-pd-no">No page</span>
					<?php endif; ?>
				</td>
				<td><?php echo $row['page_id'] ? ( $row['template'] ? '<span class="gco-pd-badge gco-pd-badge--published">Template</span>' : '<span class="gco-pd-badge">Old layout</span>' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td><?php echo $gco_tick( $row['filled']['hero'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td><?php echo $gco_tick( $row['filled']['contact'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td><?php echo $gco_tick( $row['filled']['area'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td><?php echo $gco_tick( $row['filled']['gp'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td><?php echo esc_html( (string) $row['filled']['quotes'] ); ?></td>
				<td><?php echo esc_html( (string) $row['filled']['faqs'] ); ?></td>
				<td><?php echo $row['updated'] ? esc_html( get_date_from_gmt( $row['updated'], 'j M Y' ) ) : '&ndash;'; ?></td>
				<td><a class="button button-small" href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_PROFILE, array( 'force_id' => (int) $gco_force->id ) ) ); ?>">Edit content</a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

<?php else : ?>
	<p>These appear on every force page. Use <code>{force}</code> in text to insert the force name.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gco-pd-profile">
		<input type="hidden" name="action" value="gco_pd_save_shared">
		<?php wp_nonce_field( 'gco_pd_save_shared' ); ?>
		<?php foreach ( $fields as $gco_key => $gco_field ) : ?>
			<?php
			$gco_field_name  = 'shared[' . $gco_key . ']';
			$gco_field_id    = 'gco-shared-' . $gco_key;
			$gco_field_value = $shared[ $gco_key ];
			$gco_field_rich  = true;
			include __DIR__ . '/part-field.php';
			?>
		<?php endforeach; ?>
		<div class="gco-pd-savebar"><?php submit_button( 'Save shared defaults', 'primary', 'submit', false ); ?></div>
	</form>
<?php endif; ?>
