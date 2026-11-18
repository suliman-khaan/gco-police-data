<?php
/**
 * Help: the everyday jobs, in plain English.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_url = static function ( $page, array $args = array() ) {
	return esc_url( GCO_PD_Admin::url( $page, $args ) );
};
?>
<h1>Police Data Help</h1>
<p class="gco-pd-help-lead">Everything on the police licensing portal and the force pages is filled in from the figures you load here. Load or change a figure once and every page updates itself: the portal, the leaderboard, the rankings, the charts and each force page.</p>

<div class="gco-pd-guide">

	<section>
		<h2>Every six months: add the new figures</h2>
		<p>There are two kinds of figures, and they are loaded separately:</p>
		<ul>
			<li><strong>FOI rounds</strong>: the waiting times each force sent back in answer to our Freedom of Information requests (one round per batch of letters, named by month, for example <code>2026-12</code>).</li>
			<li><strong>NPCC quarters</strong>: the national statistics (certificates held, applications received and completed, share completed within 4 months), named like <code>2025/26 Q4</code>.</li>
		</ul>

		<h3>The quick way: upload the spreadsheet</h3>
		<ol>
			<li>Go to <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_IMPORT ); ?>">Import</a> and choose your completed template (<code>.xlsx</code> or <code>.csv</code>). One file can hold the FOI sheet, the NPCC sheet, or both.</li>
			<li>Press <strong>Upload and preview</strong>. Nothing is saved yet. You will see every row marked as new, changed, unchanged or error.</li>
			<li>Fix any rows marked <em>error</em> in your spreadsheet and upload again. The message beside each row says what is wrong (for example a force name that is not recognised, or a figure marked "Actual" with no number).</li>
			<li>When the preview is clean, press the <strong>Save … row(s) as draft</strong> button. The figures are now stored but <strong>not visible on the website</strong>.</li>
		</ol>

		<h3>Or type them in</h3>
		<ol>
			<li>Open <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_ROUNDS ); ?>">FOI Rounds</a>, type the month (for example <code>2026-12</code>) and press <strong>Create draft round</strong>.</li>
			<li>Open the round, fill in the days for each force, choose <em>Actual</em>, <em>Estimate</em> or <em>Not provided</em> for each figure, and press <strong>Save figures</strong>.</li>
			<li>For an NPCC quarter use <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_QUARTERS ); ?>">NPCC Quarters</a> in the same way.</li>
		</ol>

		<h3>Check, then publish</h3>
		<ol>
			<li>Look over the draft (the rounds list shows how many forces have data and how many figures are "not provided").</li>
			<li>Press <strong>Publish</strong> beside the round or quarter. The portal, leaderboard, rankings and force pages update straight away, and the page cache is cleared.</li>
			<li>Made a mistake? Press <strong>Unpublish</strong>, correct it, and publish again. Published figures are read-only until you unpublish.</li>
		</ol>
		<p class="description">Nothing a visitor sees changes until you press Publish. A draft is never shown on the site.</p>
	</section>

	<section>
		<h2>What the labels mean</h2>
		<table class="widefat striped">
			<tbody>
				<tr><th>Actual average</th><td>The force gave an exact average from its own records.</td></tr>
				<tr><th>Estimate</th><td>The force could only give an approximate figure (for example "about 6 weeks"). Shown with a blue label.</td></tr>
				<tr><th>Not provided</th><td>The force did not answer that question. It is shown as "Not provided", is left out of rankings and averages, and is never shown as 0.</td></tr>
				<tr><th>Ranking</th><td>Shortest wait is rank 1. Forces on the same figure share a rank. "8th of 43" counts only the forces that gave a figure.</td></tr>
				<tr><th>A force missed the latest round</th><td>Its page keeps showing its last published figures, labelled with the round they came from.</td></tr>
			</tbody>
		</table>
	</section>

	<section>
		<h2>Extra FOI figures: applications, backlog, service standards, how to apply</h2>
		<p>The FOI letters also ask about application volumes, outstanding applications, backlog ages, service standards and how applications can be made. They are optional: leave a column blank when a force did not answer, and older spreadsheets without them still upload. There are two places to enter them:</p>
		<ul>
			<li><strong>"FOI Data" sheet / the second tab of the round editor</strong> (one row per force): the <strong>overall</strong> applications received and completed (with the period they cover), the <strong>overall</strong> outstanding total and the oldest outstanding application, the <strong>backlog age bands</strong> (overall, not per type), the six <strong>service-standard</strong> columns (target days and % completed within target, for new grants, renewals and variations; a number means Actual), the three <strong>online</strong> Yes/No columns and the one <strong>postal</strong> Yes/No column.</li>
			<li><strong>"FOI Application Types" sheet / the third tab ("By application type")</strong> (one row per force per type): received, completed and outstanding for each of the five types: new shotgun grants, new firearm grants, shotgun renewals, firearm renewals and firearm variations (firearm certificates only).</li>
		</ul>
		<p>If you enter both the overall totals and the five types, the upload warns when the types do not add up to the overall figure. Both are kept as the force gave them. In the round editor, leave a type row completely blank if the force did not answer for it; it is never stored as 0.</p>
		<p><strong>Nothing is shown to visitors until you switch it on.</strong> Under <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_SETTINGS ); ?>">Settings</a> there is one tick-box each for: overall volumes, outstanding applications, backlog age, figures by application type, service standards, and how to apply (online and postal). The figures are stored from the first round that includes them, so you can decide later. The force page then gets an "Applications, Backlog &amp; Service Standards" section, and the portal stat cards <code>outstanding</code> and <code>online</code> become available.</p>
	</section>

	<section>
		<h2>When a force gives percentages</h2>
		<ul>
			<li>For the backlog, set <strong>Backlog unit</strong> to <em>Percentage</em> and type the percentages (for example 55, 25, 15, 5). They should add up to about 100; you get a warning if they do not.</li>
			<li>Percentages are shown as percentages, with a note that the force gave them that way. They are <strong>never added to numbers</strong>, and never used in totals.</li>
			<li>"Met the service standard (%)" is always a percentage. Type 82 or 82%. A value of 1 or less is read as a fraction (0.82 = 82%), and the import preview tells you when it did that.</li>
			<li>If a force gives a percentage for any other figure, leave that figure blank, put its wording in Notes and let your developer know so the form can be extended.</li>
		</ul>
	</section>

	<section>
		<h2>Forces that share a licensing department</h2>
		<p>Some forces run one firearms licensing department together and give one combined set of figures.</p>
		<ol>
			<li>Under <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_FORCES ); ?>">Forces</a>, type the same name in <strong>Shared department</strong> for each of them (for example "Warwickshire &amp; West Mercia"), and save.</li>
			<li>Enter the <strong>same combined figures against each force</strong>, as normal: one row per force in the spreadsheet or the round editor.</li>
			<li>The national totals and averages then count the shared figure <strong>once</strong>, so nothing is double counted. Each force still has its own page, its own row in the leaderboards, and a note saying it shares a department.</li>
		</ol>
		<p>Which forces share a department is just a setting here, not part of the plugin: when an arrangement starts, changes or ends, tick or clear it on the Forces screen. No plugin update is ever needed.</p>
		<p>If you forget, the import preview helps: when forces carry <strong>identical large figures</strong> (applications, outstanding, holders) but are not marked as sharing, each row gets a warning naming them, so a department is not counted twice by accident.</p>
		<p class="description">If the forces in a group give <em>different</em> figures, they are treated as separate figures and all of them count. The import preview warns when forces in a shared department disagree, in case it is a typing slip.</p>
	</section>

	<section>
		<h2>Force pages</h2>
		<p>Each force page has a design that is the same for every force. The figures fill in on their own; the <strong>written content</strong> (contact details, GP medical providers, the description of the area, applicant quotes and FAQs) is edited under <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_PAGES ); ?>">Force Pages</a>.</p>
		<ol>
			<li>Open <strong>Force Pages</strong> and press <strong>Edit content</strong> beside a force. The tabs along the top split the content into Hero, Contact, Area &amp; applicants, GP medical, Applicant reports, FAQs and Review &amp; guides.</li>
			<li>Press <strong>Save force page</strong>. The page updates straight away.</li>
			<li>A section with nothing in it is hidden on the page, so a force with only some of the content still looks complete.</li>
		</ol>
		<p>Text that is the same on every force page (the security inspection block, the GP checklist, the disclaimer, the default photo) lives under <strong>Force Pages &rarr; Shared defaults</strong>. Write <code>{force}</code> where the force name should appear.</p>

		<h3>Adding a new force page</h3>
		<ol>
			<li>In WordPress create a new <strong>Page</strong> named like the others (for example "Surrey Police Firearms Licensing") and put <code>[gco_force_page]</code> in its content.</li>
			<li>Link it to its force under <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_FORCES ); ?>">Forces</a> (the Page column).</li>
			<li>Fill in its content under Force Pages and publish the page. The waiting times, charts and ranking appear as soon as that force has published figures.</li>
		</ol>
	</section>

	<section>
		<h2>The portal page</h2>
		<p>The portal is an ordinary page built in WPBakery from the "GCO Police Data" elements (search, stats, leaderboard, waiting times, guides, A&ndash;Z, how we research, FAQs). You can change the wording, move sections and add or remove them there. The figures inside them always come from the published data.</p>
		<ul>
			<li><strong>FAQs:</strong> add or edit the Toggle elements inside the "Police FAQs" element. Questions and answers are also sent to search engines automatically.</li>
			<li><strong>How we research:</strong> "Last updated" fills in from the latest publish date. "Next review" is typed into that element.</li>
			<li><strong>Guide cards:</strong> type the IDs of the pages or posts to show. An ID is the number in the page's edit address (<code>post=1234</code>).</li>
		</ul>
	</section>

	<section>
		<h2>If something looks wrong</h2>
		<table class="widefat striped">
			<tbody>
				<tr><th>A force is not recognised on import</th><td>Add the name used in the spreadsheet as an alias under <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_FORCES ); ?>">Forces</a> (aliases are separated by commas, for example <code>GMP, Greater Manchester</code>). Aliases also make the site search find the force.</td></tr>
				<tr><th>A figure on the site is out of date</th><td>Check the round or quarter is <em>published</em> (not draft). If it is, publish again or ask your host to clear the page cache.</td></tr>
				<tr><th>A force page shows no figures</th><td>Check that the page is linked to its force under <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_FORCES ); ?>">Forces</a> and that the page contains <code>[gco_force_page]</code>.</td></tr>
				<tr><th>A force page section is missing</th><td>It has no content yet. Fill it in under Force Pages.</td></tr>
				<tr><th>The upload is refused</th><td>Only <code>.xlsx</code> and <code>.csv</code> files up to 2 MB and 1,000 rows are accepted. Save the template again from Excel and retry.</td></tr>
			</tbody>
		</table>
	</section>

	<section>
		<h2>Backups and safety</h2>
		<ul>
			<li><a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_ROUNDS ); ?>">FOI Rounds</a> and <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_QUARTERS ); ?>">NPCC Quarters</a> have <strong>Download CSV</strong> buttons: a backup you can open in Excel and upload again.</li>
			<li>Only administrators can see or change police data. Every change is checked for permission before it is saved.</li>
			<li>Deleting the plugin keeps your data unless you tick the option under <a href="<?php echo $gco_url( GCO_PD_Admin::SLUG_SETTINGS ); ?>">Settings</a>.</li>
		</ul>
	</section>
</div>
