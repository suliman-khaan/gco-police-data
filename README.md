# GCO Police Data

WordPress plugin for Gun Cabinets Online that stores police force FOI responses and NPCC
firearms-licensing figures, and renders the police licensing portal and the force pages from them.

- Requires: WordPress 6.5+ (tested on 7.1), PHP 8.1+
- Optional: WPBakery (adds "GCO Police Data" elements), WooCommerce (product grid in the inspection block)
- License: proprietary, built for guncabinetsonline.co.uk
- Author: [Suliman K.](https://sulimankhan.pro)

## Install

Upload the zip under Plugins → Add New → Upload, then activate. Activation creates the tables, seeds the 43
forces, links existing force pages by slug and gives administrators the `gco_manage_police_data` capability.
Updating over an older version upgrades the tables on the next page load.

Everyday use is explained on **Police Data → Help** inside WordPress.

## What it does

| Area | Where |
|---|---|
| FOI rounds and NPCC quarters: enter by hand or import `.xlsx` / `.csv`, preview, save as draft, publish | Police Data → FOI Rounds / NPCC Quarters / Import |
| Force list, aliases (also used by the site search), page link | Police Data → Forces |
| Written content of each force page, shared defaults | Police Data → Force Pages |
| CSV backups | Download buttons on the rounds and quarters screens |

## Extra FOI figures, percentages and shared departments

- **Extra figures** (from round 2026-11), all optional and stored with the Actual / Estimate / Not provided rule:
  overall applications received and completed (with the period they cover), the overall outstanding total and the
  oldest outstanding, backlog age bands (overall), service standards per category (target days and % completed within
  target for new grants, renewals and variations; a number means Actual), online applications per category and one
  overall "postal still required" answer (Yes/No).
- **By application type**: a second sheet / table (`gco_response_types`) holds received, completed and outstanding for
  each of the five types (new shotgun grant, new firearm grant, shotgun renewal, firearm renewal, firearm variation;
  variations are firearm certificates only). Imported from the "FOI Application Types" sheet or entered on the third
  tab of the round editor; exported as a second CSV. The import warns when the types do not add up to the overall total.
  Nothing is public until switched on under Settings (`show_volumes`, `show_outstanding`, `show_backlog`,
  `show_type_volumes`, `show_standards`, `show_application_methods`).
- **Percentages**: backlog bands carry a Count or Percentage unit per row. Percentages are stored and shown as given and
  never summed into counts. The "completed within target (%)" columns are percentages by nature; a bare value of 1 or less is read as a fraction.
- **Shared departments**: a force can belong to a shared group (Police Data → Forces). Forces in a group that carry the
  same figure are counted once in national totals and averages (NPCC and FOI, per figure); different figures all count.
  The import preview warns when a group disagrees with itself, and when forces carry identical large figures but are
  not marked as sharing (a forgotten tick). Groups are data, so changing them never needs a plugin update.

Older spreadsheets without the new columns still import (the headers are optional).

Publishing rebuilds one cached snapshot, so every front-end block reads a single option and the plugin adds
one query to a force page and none to the portal. Page caches (WP Rocket) are cleared for the portal and the
force pages; other cache layers can hook `gco_pd_purge_pages`.

## Shortcodes

Portal blocks: `[gco_police_hero]` `[gco_police_search]` `[gco_police_stats]` `[gco_police_leaderboard]`
`[gco_police_glance]` `[gco_police_waiting_table]` `[gco_police_guides]` `[gco_police_az]`
`[gco_police_research]` and `[gco_police_faq_schema]` (wraps WPBakery Toggle elements, adds FAQPage schema).

Force pages: `[gco_force_page]` renders the whole page (`part="hero"` and `part="body"` split it for a
full-width hero row). Smaller blocks for one-off use: `[gco_force_waiting_times]` `[gco_force_rank]`
`[gco_force_trend]` `[gco_force_context]` `[gco_force_updated]`. `force="auto"` uses the force linked to the
current page; `force="slug"` picks one.

All of them are WPBakery elements under "GCO Police Data". Templates can be overridden by copying them from
`templates/` to `{theme}/gco-police-data/`. CSS and JS load only on pages that use a shortcode.

## WP-CLI

```bash
# Force pages
wp gco-pd force-pages status
wp gco-pd force-pages migrate --force=derbyshire            # dry run, writes nothing
wp gco-pd force-pages migrate --force=derbyshire --apply
wp gco-pd force-pages migrate --all --apply
wp gco-pd force-pages restore --force=derbyshire            # puts the old page back

# Portal page
wp gco-pd portal create                                     # new draft page, live page untouched
wp gco-pd portal apply --page=<id> --dry-run
wp gco-pd portal apply --page=<id>                          # old content kept for restore
wp gco-pd portal restore --page=<id>
```

`migrate` reads the written content of an existing WPBakery force page into the force profile, keeps the
original content and its WPBakery CSS in post meta, and switches the page to `[gco_force_page]`.

## Hooks

- `gco_pd_data_changed` fires when data changes; `gco_pd_snapshot_rebuilt` after the snapshot is rebuilt.
- `gco_pd_purge_pages` receives page IDs whose cached HTML must be cleared.
- `gco_pd_force_page_slug_candidates` filters the slugs tried when linking a force to its page.

## Uninstall

Deleting the plugin keeps all data unless "Delete all police data when this plugin is deleted" is ticked under
Police Data → Settings.
