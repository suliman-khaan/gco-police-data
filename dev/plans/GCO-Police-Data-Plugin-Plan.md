# GCO Police Data — Build Plan

**Client:** Gun Cabinets Online (guncabinetsonline.co.uk)
**Agreed:** $450 fixed · 2–3 weeks · data arrives end of November 2026
**Stack:** WordPress 7.1 · Porto theme · WPBakery · WooCommerce · IONOS hosting
**Deliverable:** custom plugin `gco-police-data` + police portal page redesign + police force page template + migration of the 20 existing force pages

---

## 1. Goals

1. Emma enters each force's FOI response **once per round** (manual form or spreadsheet upload).
2. Everything else updates itself:
   - the individual force pages
   - the police licensing portal (leaderboard, waiting times, national stats)
   - the trend history on each force
3. Missing figures are **never treated as zero**, and every figure shows whether it's an **Actual** or an **Estimate**.
4. Fast pages, no new heavy dependencies, safe admin.

## 2. Scope

### In scope ($450)
| # | Item |
|---|------|
| 1 | Plugin: data storage, admin screens, spreadsheet import, calculations, caching |
| 2 | Portal page `/police-firearms-licensing` rebuilt as in the client mockup: hero + live search, national stats strip, leaderboard (top 5 + full table), "at a glance" shortest/longest, waiting-times tabs (Shotgun / Firearm / Renewals / Variations), guides cards, Applications & Install blog carousel, A–Z browse, "How we research" box, FAQs |
| 3 | Force page template: waiting-times table (with Actual/Estimate tags), rank vs other forces, national context, **trend table + trend chart**, last updated / source |
| 4 | Migrate the 20 existing force pages to pull figures from the plugin (their written content stays as it is) |

### Out of scope (quote separately)
- Reading the figures out of an uploaded PDF automatically (AI)
- Writing content for the remaining 23 force pages (with the template in place, adding a page = written content only)
- Cabinet finder / AI search in the footer
- Dynamic product comparison table
- Collecting data or chasing FOI requests

## 3. Data sources (how the data arrives)

There are **two separate sources**, each on its own schedule:

| Source | Frequency | What it gives | Used for |
|---|---|---|---|
| **A. FOI letters** (one PDF per force) | ~every 6 months (rounds) | waiting times per application type + holders | waiting-times tables, "at a glance", force page waits + trends |
| **B. NPCC Firearms Licensing report** (one PDF, all 43 forces) | quarterly (e.g. 2025/26 Q4, data drawn 23/03/2026) | total certificate holders, total SG/FAC/RFD certificates, applications received and completed (12 months), number completed within SLA, temporary permits | **leaderboard (% within SLA)**, national stats strip, force rank |

**SLA** = NPCC / Home Office aspiration: **80% of applications completed within 4 months of receipt**. `% within SLA` = completed within SLA ÷ completed (12 months). It's calculated, never typed.

NPCC uses short names (GMP, TVP, Met Police, Avon & Somerset, Devon & Cornwall, Hampshire). The plugin keeps an alias per force so imports match either name.
NPCC has said that from **Q1 2026/27 (June 2026 release)** it will split the grant and renewal data. The importer maps columns by header name, so extra columns can be added later without a rebuild (small follow-up if the layout changes a lot).

### A. FOI letters

Each force answers an FOI letter (e.g. Derbyshire, 11/06/2026, ref 01/FOI/26/008471/V) with 7 answers:

| FOI Q | Field | Unit |
|---|---|---|
| Q1 | New shotgun certificate | days |
| Q2 | New firearm certificate (FAC) | days |
| Q3 | Shotgun renewal | days |
| Q4 | Firearm renewal | days |
| Q5 | Variation | days |
| Q6 | Shotgun certificate holders | count |
| Q7 | Firearm certificate holders | count |

**Every FOI figure carries a status:** `actual` · `estimate` · `not_provided`.
- Weeks → days (×7) when the data is entered. A range is entered as its upper figure with status `estimate`, and the original wording goes in the notes.

**Spreadsheet template (agreed import format):** `GCO-Police-FOI-Data-Template.xlsx`
- `FOI Data`: one row per force per round. The column headers are fixed and the importer maps on them exactly.
- `NPCC Data`: one row per force per quarter. **Pre-filled with 2025/26 Q4 for all 43 forces** (checked against the report's own %).
- `Forces`: the 43 territorial forces, with a plugin slug and the NPCC short name.
- `Coverage`: shows which forces are still missing for an FOI round.

## 4. Data model (custom tables)

Custom tables, not post meta: 43 forces × several metrics × many rounds means lots of small values, and we need indexed queries plus fast aggregates.

```sql
{prefix}gco_forces
  id            BIGINT UNSIGNED PK AUTO_INCREMENT
  slug          VARCHAR(80)  UNIQUE        -- 'derbyshire'
  name          VARCHAR(120)               -- 'Derbyshire Constabulary'
  aliases       VARCHAR(255) NULL          -- 'Derbyshire' | 'GMP' | 'TVP' ... (import matching + search)
  country       ENUM('England','Wales')
  page_id       BIGINT UNSIGNED NULL       -- linked force page (WP page ID)
  is_active     TINYINT(1) DEFAULT 1

{prefix}gco_rounds
  id            BIGINT UNSIGNED PK AUTO_INCREMENT
  label         CHAR(7) UNIQUE             -- '2026-11'
  status        ENUM('draft','published') DEFAULT 'draft'
  published_at  DATETIME NULL
  created_at, updated_at

{prefix}gco_responses
  id            BIGINT UNSIGNED PK AUTO_INCREMENT
  round_id      BIGINT UNSIGNED  (FK rounds)
  force_id      BIGINT UNSIGNED  (FK forces)
  foi_ref       VARCHAR(80) NULL
  response_date DATE NULL
  sg_grant_days    SMALLINT UNSIGNED NULL,  sg_grant_status    ENUM('actual','estimate','not_provided')
  fac_grant_days   SMALLINT UNSIGNED NULL,  fac_grant_status   ENUM(...)
  sg_renew_days    SMALLINT UNSIGNED NULL,  sg_renew_status    ENUM(...)
  fac_renew_days   SMALLINT UNSIGNED NULL,  fac_renew_status   ENUM(...)
  variation_days   SMALLINT UNSIGNED NULL,  variation_status   ENUM(...)
  sg_holders       INT UNSIGNED NULL,       sg_holders_status  ENUM(...)
  fac_holders      INT UNSIGNED NULL,       fac_holders_status ENUM(...)
  notes            TEXT NULL               -- private, never output on the front end
  pdf_id           BIGINT UNSIGNED NULL    -- media attachment of the FOI letter
  updated_by       BIGINT UNSIGNED, updated_at DATETIME
  UNIQUE KEY round_force (round_id, force_id)
  KEY force (force_id)

{prefix}gco_npcc_quarters
  id            BIGINT UNSIGNED PK AUTO_INCREMENT
  label         CHAR(10) UNIQUE            -- '2025/26 Q4'
  data_date     DATE                       -- 2026-03-23
  status        ENUM('draft','published') DEFAULT 'draft'
  pdf_id        BIGINT UNSIGNED NULL       -- the NPCC report PDF

{prefix}gco_npcc
  quarter_id         BIGINT UNSIGNED (FK npcc_quarters)
  force_id           BIGINT UNSIGNED (FK forces)
  total_holders      INT UNSIGNED NULL
  total_certificates INT UNSIGNED NULL    -- shotgun + firearm + RFD
  apps_received_12m  INT UNSIGNED NULL
  apps_completed_12m INT UNSIGNED NULL
  completed_in_sla   INT UNSIGNED NULL
  temporary_permits  INT UNSIGNED NULL
  PRIMARY KEY (quarter_id, force_id)
  -- % within SLA is calculated (completed_in_sla / apps_completed_12m), never stored or typed
```

- `NULL` = not provided. Code never coerces `NULL` to `0`.
- A row is valid if every value/status pair agrees: a value requires `actual|estimate`, and `NULL` requires `not_provided`.
- Tables are created with `dbDelta()` on activation. Schema version is stored in the `gco_pd_db_version` option, with an upgrade routine.
- Seed: the 43 forces from the template's `Forces` sheet, and the 20 existing force pages auto-linked by slug match.

## 5. Calculations (on publish, cached)

Computed when a round is **published** (or re-published) and stored as one JSON snapshot per round in the `gco_pd_snapshot_{round}` option (autoload = no):

| Output | Rule |
|---|---|
| Rank per metric | Ascending days. Only `actual`/`estimate` count. `not_provided` gets **no rank** (shown as "—", never ranked last). Ties share a rank. |
| Leaderboard | From the latest published **NPCC quarter**, ranked by **% completed within SLA** (desc). Columns: rank, force, applications received, applications completed, % within SLA (green at 80% or above = meeting the aspiration, amber/red below). Ties are broken by more applications completed. |
| Shortest / longest top 5 | Per FOI metric, from ranked values (latest published FOI round). |
| National stats strip | From NPCC: total certificate holders (476,079 in 2025/26 Q4), total certificates, applications received / completed (12 months), national % within SLA = Σ completed in SLA ÷ Σ completed (70.67% in Q4). From FOI: forces reporting X of 43, national average/median wait per metric. |
| Force rank | "Xth of 43" on % within SLA (NPCC) plus a rank per FOI waiting-time metric. |
| NPCC change | % within SLA change vs the previous quarter (▲/▼). |
| Change vs previous round | Per force per metric: difference in days and ▲/▼ arrow. Blank if either round is missing. |
| Trend series | Per force: every published round in date order (feeds the trend table and chart). |
| Data coverage | Count of actual / estimate / not provided. |

If a force has no row in the latest round, it keeps showing its **last published figures** with "Last updated: {round}".

## 6. Admin (WP Admin → Police Data)

1. **Rounds** (FOI) and **NPCC Quarters**: list, create (YYYY-MM / 2025/26 Q4), draft/published toggle, delete (draft only). One importer handles both sheets of the template and detects which sheet(s) the file contains.
2. **Round editor**: a grid of 43 rows for the selected round with inline number and status inputs per metric, FOI ref, date and a PDF picker (media library). Live validation turns the value/status mismatches red, the same rule as the spreadsheet.
3. **Import**: upload `.xlsx` or `.csv`, then a preview table showing new / changed / unchanged / errors per row. Confirm writes to the **draft** round only. Nothing goes live until **Publish**.
4. **Forces**: name, slug, linked page (dropdown), active.
5. **Publish**: recalculates the snapshot, clears caches and shows a summary ("38 of 43 forces, 6 figures not provided").
6. **Export**: download any round (or all) back to the same template format, which doubles as a backup.

XLSX parsing uses a small bundled MIT library (SimpleXLSX, read-only, no extra PHP extensions needed beyond `zip` + `simplexml`). CSV is supported as a fallback.

## 7. Front end

### Shortcodes (so the existing WPBakery/Porto pages can place them)
| Shortcode | Used on | Output |
|---|---|---|
| `[gco_police_search]` | Portal hero | Live search box. Instant results while typing, keyboard accessible, links to force pages |
| `[gco_police_stats]` | Portal | 4 stat cards (holders, forces reporting, fastest average, national average) |
| `[gco_police_leaderboard limit="5"]` | Portal | Top N + link to the full table (`limit="all"`) |
| `[gco_police_glance metric="sg_grant"]` | Portal | Shortest / longest top 5, side by side |
| `[gco_police_waiting_table]` | Portal | Tabbed table: Shotgun / Firearm / Renewals / Variations, with Actual/Estimate badges |
| `[gco_police_az]` | Portal | A–Z filter of all forces |
| `[gco_force_waiting_times force="auto"]` | Force page | Current waits + status badges + change vs last round |
| `[gco_force_rank force="auto"]` | Force page | "5th of 43" per metric |
| `[gco_force_trend force="auto"]` | Force page | Trend table + small line chart |
| `[gco_force_context force="auto"]` | Force page | National averages vs this force, holders |
| `[gco_force_updated force="auto"]` | Force page | "Last updated June 2026 · Source: FOI response" |

`force="auto"` = the force linked to the current page.

### Display rules
- `not_provided` → "Not provided" in grey, never "0".
- `estimate` → blue "Estimate" badge. `actual` → green "Actual average" badge (as in the mockup).
- Brand: #064d49, Poppins, card style matching the new blog template.

### Live search
- The full force list (43 × name, slug, URL, short wait) is printed **inline as JSON** in the page and filtered in the browser. No request per keystroke and no admin-ajax load, so it's instant and works with page caching.
- It also matches common names ("Met", "GMP", "Thames Valley") through an alias list.

### Trend chart
- **Inline SVG** rendered in PHP: no Chart.js, no external requests, nothing loaded on other pages. Points carry an accessible table fallback.

### Portal page sections (top to bottom, per client mockup)
Hero (title, intro, search, "browse all 43") → stats strip → leaderboard + "at a glance" → waiting-times tabs → guides cards → **Applications & Install blog carousel** (reuses the blog template's carousel) → A–Z browse → "How we research" (+ last updated / next review from the latest round) → FAQs (with FAQPage schema).

## 8. Security

- **Capability:** custom `gco_manage_police_data`, granted to administrators on activation. Every admin page, form handler and import checks it.
- **Nonces** on every form and action (`check_admin_referer`); admin actions are POST only.
- **Input:** `absint` / whitelisted enums for status / `sanitize_text_field` / `wp_kses_post` (notes); round label validated with `^\d{4}-(0[1-9]|1[0-2])$`.
- **SQL:** `$wpdb->prepare()` everywhere; table names from `$wpdb->prefix` only.
- **Uploads:**
  - accepts `.xlsx` and `.csv` only, checked with `wp_check_filetype_and_ext`;
  - 2 MB maximum, 1,000-row cap;
  - processed from the temp file and never stored in a public folder;
  - formula cells are read as values only;
  - CSV-injection-safe export (prefixes `= + - @`).
- **Output:** everything escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_json_encode` for the inline search data). Notes and the FOI ref are never shown publicly. The PDF link is optional per site setting.
- **Public data:** only published rounds are ever read on the front end. Drafts are invisible.
- **No** `eval`, no remote calls, no file writes outside uploads, no exposed REST write routes.
- Uninstall keeps the data by default. A "delete all data on uninstall" checkbox is available in settings.

## 9. Performance

- **One query per page view at most:** shortcodes read the precomputed snapshot (one `get_option`, object-cached) and do no per-force queries.
- **Snapshot rebuilt only on publish/save.** Cache keys are versioned (`gco_pd_v{n}`), and the version bump busts WP Rocket/page caches for the portal and the force pages (`rocket_clean_post` if available).
- **CSS/JS** (~6 KB CSS, ~3 KB JS, no jQuery dependency for the front end) are enqueued **only** on pages that use the shortcodes.
- Custom tables are indexed on `(round_id, force_id)` and `force_id`.
- No autoloaded options except a tiny settings array.

## 10. Plugin structure

```
gco-police-data/
├── gco-police-data.php           bootstrap, constants, activation/uninstall hooks
├── uninstall.php
├── includes/
│   ├── class-install.php         dbDelta, seed forces, capability
│   ├── class-repository.php      all DB reads/writes (prepared)
│   ├── class-validator.php       value/status rules, round label, row checks
│   ├── class-importer.php        xlsx/csv → rows → preview → commit
│   ├── class-exporter.php
│   ├── class-calculator.php      ranks, national stats, trends → snapshot
│   ├── class-cache.php           snapshot storage + page cache busting
│   ├── class-shortcodes.php
│   ├── class-svg-chart.php
│   └── lib/SimpleXLSX.php        (MIT)
├── admin/
│   ├── class-admin.php           menus, screens, handlers
│   └── views/ rounds.php, round-edit.php, import.php, forces.php
├── assets/ admin.css, admin.js, front.css, front.js
└── templates/ portal-*.php, force-*.php   (overridable from the theme)
```

Coding standard: WordPress PHP Coding Standards, prefix `gco_pd_`, PHP 8.1+, no globals beyond the main instance.

## 11. Build order & timeline (2–3 weeks)

| Week | Work |
|---|---|
| 1 | Plugin core: tables, seed 43 forces, admin rounds / round editor, validator, importer + preview, exporter. Test with the Derbyshire row + dummy data. |
| 1–2 | Calculator + snapshot + cache; all shortcodes; SVG trend chart. |
| 2 | Portal page rebuild (new draft page, live page untouched until sign-off). |
| 2–3 | Force page template; migrate the 20 existing pages (written content preserved, figures replaced by shortcodes). |
| 3 | Load the client's real data (end of November), QA, publish, swap the portal live. |

## 12. Testing checklist

- [ ] Import the template with the Derbyshire example: values, statuses, date and FOI ref land correctly
- [ ] Import the NPCC 2025/26 Q4 sheet: 43 forces matched (incl. GMP / TVP / Met aliases), national % within SLA = 70.67%, holders = 476,079
- [ ] Blank + "Not provided" → shows "Not provided", excluded from rank, not counted as 0 in averages
- [ ] Mismatch rows rejected in the preview with a clear message
- [ ] Duplicate force in the same round rejected
- [ ] Weeks/range note flows through; estimate badge shows
- [ ] Draft round never visible on the front end
- [ ] Publish → portal + force pages update; page cache cleared
- [ ] Force with no data in the latest round shows last published figures + "Last updated"
- [ ] Ties share a rank; fewer than 43 forces handled
- [ ] Search: partial names, aliases (Met, GMP), keyboard, mobile
- [ ] Non-admin user cannot reach any admin screen or handler (cap + nonce)
- [ ] Malicious upload (wrong extension, renamed PHP, oversize, formula injection) rejected
- [ ] Mobile layouts: portal, tabs, tables (horizontal scroll inside the card only), chart
- [ ] PageSpeed: no new render-blocking assets on non-police pages

## 13. Questions for the client

**Resolved:**
- ~~Where do applications / % within SLA come from?~~ The NPCC quarterly report, per force.
- ~~Leaderboard basis?~~ % within SLA (NPCC), as in their mockup.

**Still open:**
1. Do all 43 forces respond to the FOI each round, or only some? (Handled either way. Affects the "forces reporting" wording.)
2. Should the FOI PDF be linkable publicly on force pages (transparency) or kept private?
3. Previous FOI rounds and earlier NPCC quarters: send them if they have them, so the trends aren't empty at launch.
4. Portal copy: intro, "How we research", FAQs. We'll draft placeholder text if they don't have it.
