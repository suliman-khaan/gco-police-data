<?php
/**
 * Settings.
 *
 * @var array    $settings Current settings.
 * @var object[] $pages    Published pages for the portal dropdown.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<h1>Police Data Settings</h1>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="gco_pd_save_settings">
	<?php wp_nonce_field( 'gco_pd_save_settings' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="gco-pd-portal">Portal page</label></th>
			<td>
				<select id="gco-pd-portal" name="portal_page_id">
					<option value="0">&mdash; not set &mdash;</option>
					<?php foreach ( $pages as $page ) : ?>
						<option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( (int) $settings['portal_page_id'], (int) $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">The police firearms licensing page. When you publish new data, its cached copy (and every linked force page) is cleared so visitors see the update straight away.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Extra FOI figures</th>
			<td>
				<fieldset>
					<legend class="screen-reader-text">Show on the website</legend>
					<?php
					$gco_switches = array(
						'show_volumes'     => 'Applications received and completed',
						'show_outstanding' => 'Outstanding applications and the oldest outstanding',
						'show_backlog'     => 'Backlog age (how long outstanding applications have waited)',
						'show_standards'   => 'Service standards and how often they were met',
					);
					foreach ( $gco_switches as $gco_key => $gco_text ) :
						?>
						<label style="display:block;margin:4px 0">
							<input type="checkbox" name="<?php echo esc_attr( $gco_key ); ?>" value="1" <?php checked( $settings[ $gco_key ] ); ?>>
							<?php echo esc_html( $gco_text ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">Show these on the force pages (and the national totals on the portal). All off by default: the figures are collected and stored from the first round that includes them, but nothing is shown to visitors until you tick it. A backlog is sensitive, so decide before switching it on.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">FOI letters</th>
			<td>
				<label>
					<input type="checkbox" name="show_pdf_public" value="1" <?php checked( $settings['show_pdf_public'] ); ?>>
					Link the FOI response PDF on force pages
				</label>
				<p class="description">Off by default: the PDFs stay private. Turn on if you want visitors to be able to read the original letter.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Uninstall</th>
			<td>
				<label>
					<input type="checkbox" name="delete_data" value="1" <?php checked( $settings['delete_data'] ); ?>>
					Delete all police data when this plugin is deleted
				</label>
				<p class="description">Off by default: deleting the plugin keeps your data. Tick this only if you want everything removed permanently. Download a CSV backup first.</p>
			</td>
		</tr>
	</table>
	<?php submit_button( 'Save settings' ); ?>
</form>
