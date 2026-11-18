# GCO Police Data — Development Plan

> Execution plan for the build described in [GCO-Police-Data-Plugin-Plan.md](GCO-Police-Data-Plugin-Plan.md) (the agreed spec, "the spec" below).
> The spec says **what** we build; this file says **in what order, on which branch, and when it's done**.
> Status: **draft for review** · created 2026-11-16

---

## 1. Repository & branching

| Branch | Purpose | Contains dev/AI files? |
|---|---|---|
| `main` | Release-only. Every commit is a tagged release (`vX.Y.Z`). This is what gets zipped and deployed to IONOS. | **Never** |
| `development` | Integration branch. All work lands here first. | Yes |
| `feature/<milestone>-<topic>` | One per task group below, cut from `development`, merged back with `--no-ff`. | Yes |

**Rules**
- Nothing is ever committed directly on `main`. Releases go through `bash dev/scripts/release.sh X.Y.Z`, which merges `development` and strips every path in [`.release-exclude`](../../.release-exclude) (`dev/`, `.claude/`, `CLAUDE.md`, `AGENTS.md`, `tests/`, composer/phpcs config, …).
- A local `pre-commit` / `pre-merge-commit` hook blocks dev-only files on `main` even if someone merges by hand. Install once per clone: `bash dev/scripts/install-hooks.sh`. (Hooks are local only — if we add a GitHub remote, add the same check as a CI job / branch protection.)
- **Never merge `main` into `development`** — `main`'s history deletes the dev files, so that merge would delete them on `development`. Hotfixes are made on `development` (or `hotfix/*` cut from it) and released as a patch version.
- Repo root **is** the plugin root, so a release zip is just `git archive --prefix=gco-police-data/ -o gco-police-data-X.Y.Z.zip vX.Y.Z`.
- Versions: `0.x` during the build; `1.0.0` = go-live.

## 2. Environment & tooling (Milestone 0)

| Item | Choice | Notes |
|---|---|---|
| Local WordPress | LocalWP (or `wp-env` if Docker is available) | PHP 8.1, WordPress 7.1, WooCommerce, Porto + WPBakery (licensed copies from the client) |
| Staging | IONOS staging or a clone of the live site | Needed for the page migration and client sign-off — **confirm with client** |
| Coding standard | PHPCS + WordPress Coding Standards (`composer` dev dependency, dev-only) | `gco_pd_` prefix, text domain `gco-police-data` |
| Tests | PHPUnit for the pure classes (Validator, Calculator, Importer mapping, SVG chart); manual QA checklist (spec §12) for UI | Fixtures built from the real template: Derbyshire FOI row + 43-force NPCC 2025/26 Q4 |
| XLSX | SimpleXLSX (MIT), vendored in `includes/lib/` with its licence file | Not via composer at runtime — main must work with no `vendor/` |

**Pre-flight checks on the live host (before Milestone 1 ends):** PHP ≥ 8.1, `zip` + `simplexml` extensions, `max_input_vars`, which page cache is active (WP Rocket? IONOS cache?), which SEO plugin is active (FAQ schema duplication — see §5).

## 3. Milestones

Each milestone = one or more feature branches → merged to `development` → tagged release to `main` where it's worth deploying to staging.

### M0 — Project setup · `feature/m0-setup` · ½ day
- [ ] Plugin bootstrap `gco-police-data.php` (header, constants, PHP/WP version guard with admin notice, autoloader, main instance)
- [ ] `uninstall.php` skeleton (respects "delete all data" setting)
- [ ] composer dev deps (PHPCS/WPCS, PHPUnit), `phpcs.xml.dist`, `tests/bootstrap.php`
- [ ] Local site running with Porto/WPBakery/WooCommerce; copy of the live portal + 2–3 force pages for reference
- **Done when:** plugin activates/deactivates cleanly on PHP 8.1 with no notices; `phpcs` and `phpunit` run.

### M1 — Data layer · `feature/m1-schema` · 1–1.5 days
- [ ] `class-install.php`: 5 tables via `dbDelta()`, `gco_pd_db_version` + upgrade routine, `gco_manage_police_data` capability → administrators
- [ ] Seed 43 forces from the template's `Forces` sheet (slug, name, country, NPCC name → `aliases`, plus GMP / TVP / Met etc.); auto-link `page_id` by slug match
- [ ] `class-repository.php`: all reads/writes via `$wpdb->prepare()`; referential checks in code (dbDelta does not create foreign keys)
- [ ] `class-validator.php`: value/status pairing, round label `^\d{4}-(0[1-9]|1[0-2])$`, NPCC label `^\d{4}/\d{2} Q[1-4]$`, ranges, `completed_in_sla ≤ apps_completed_12m`
- [ ] Unit tests for the validator
- **Done when:** fresh activation creates tables + 43 forces; re-activation is idempotent; validator tests green.

### M2 — Admin, import & export · `feature/m2-admin`, `feature/m2-import` · 2.5–3 days
- [ ] Admin menu **Police Data** (cap-checked): Rounds, NPCC Quarters, Forces, Import, Settings
- [ ] Rounds / Quarters list: create, draft ↔ published, delete (draft only)
- [ ] Round editor grid (43 rows): value + status per metric, FOI ref, response date, PDF picker; live validation in `admin.js`. **Save per row / as one JSON field** so ~730 inputs don't hit `max_input_vars` (default 1000)
- [ ] Forces screen: name, slug, aliases, linked page dropdown, active
- [ ] Importer (`.xlsx` + `.csv`): detect `FOI Data` / `NPCC Data` sheets; map by exact header; skip blank pre-formatted rows; convert Excel serial dates; case-insensitive statuses (`Actual` / `Estimate` / `Not provided`); match force by name or alias; match `PDF File Name` against the media library; ignore the `% Completed Within SLA` column (recalculated — optionally warn if it disagrees)
- [ ] Preview table: new / changed / unchanged / error per row; duplicate force in a round rejected; commit writes to **draft** only
- [ ] Upload hardening per spec §8 (type check, 2 MB, 1,000 data rows, temp file only)
- [ ] Exporter: round(s) / quarter(s) → same template format; CSV-injection-safe
- [ ] Settings: show PDF link publicly (off), delete data on uninstall (off)
- **Done when:** the template round-trips (import → export → re-import = "unchanged"); NPCC Q4 import matches all 43 forces; non-admin gets no access to any screen or handler.

### M3 — Calculations & cache · `feature/m3-calculator` · 1.5 days
- [ ] `class-calculator.php`: ranks (ties share, `not_provided` unranked), leaderboard (% within SLA desc, tie → more completed), top/bottom 5 per metric, national stats (NPCC + FOI), force rank, NPCC change vs previous quarter, change vs previous round, trend series, coverage
- [ ] `class-cache.php`: snapshot storage, versioned cache key, page-cache purge (`rocket_clean_post` / `rocket_clean_domain` when present) for the portal + linked force pages
- [ ] Rebuild on publish/unpublish of **either** an FOI round or an NPCC quarter, and on force edits
- [ ] Unit tests against fixtures: national % within SLA = **70.67%**, holders = **476,079**; ties; fewer than 43 forces; missing-latest-round fallback
- **Done when:** all calculator tests green; publishing shows the summary ("38 of 43 forces, 6 figures not provided").

### M4 — Shortcodes & front-end components · `feature/m4-shortcodes` · 2 days
- [ ] `class-shortcodes.php`: the 11 shortcodes from spec §7, reading the snapshot only (max one `get_option` per view)
- [ ] Templates in `templates/`, overridable from `{theme}/gco-police-data/`
- [ ] `front.css` (~6 KB) / `front.js` (~3 KB, no jQuery): live search with inline JSON + aliases, tabs, A–Z filter; enqueued only when a shortcode renders
- [ ] `class-svg-chart.php`: inline SVG trend chart + accessible table fallback
- [ ] Badges & empty states: Actual / Estimate / Not provided — never "0"
- [ ] *(Recommended, small)* register the shortcodes as WPBakery elements (`vc_map`) so the client can place them without typing shortcodes
- **Done when:** every shortcode renders on a test page with fixture data, on desktop and mobile, with no PHP notices and nothing enqueued on non-police pages.

### M5 — Portal page rebuild · `feature/m5-portal` · 1.5–2 days
- [x] New **draft** page built in WPBakery, matching the client mockup section by section (spec §7 order); live `/police-firearms-licensing` untouched — draft page ID 9795, slug `police-firearms-licensing-draft`
- [x] Reuse the blog template's carousel for Applications & Install posts — `porto_tb_posts`, pinned to the category's current 2 posts by ID; switch to the `tax`/`terms` filter once there are more
- [x] FAQs + FAQPage schema (only if the SEO plugin doesn't already output it) — confirmed Yoast only emits FAQ schema for its own block, not `vc_toggle`; added `[gco_police_faq_schema]`
- [x] Placeholder copy where the client hasn't supplied intro / "How we research" / FAQs — marked inline with `<!-- GCO Police Data: placeholder copy, pending client review -->`
- **Done when:** client signs off the draft on staging — **open**, needs staging access and client review (§7.5).

### M6 — Force page template & migration · `feature/m6-force-pages` · 4–5 days (was 2)

**Decision (16 Nov 2026):** every force page is rendered by **one plugin template**, following the client's force-page mockup. Per-force text lives in fields on the force record, not in WPBakery layouts. Not a custom post type: the existing WordPress pages stay as containers, so their URLs, titles, Yoast meta and internal links are unchanged. The page content becomes `[gco_force_page]`. A layout change is made once, in the template, and applies to all 43 forces.

**Mockup sections → source of each**

| # | Section | Source |
|---|---|---|
| 1 | Breadcrumb + hero: "{Force} Firearms Licensing", intro, background photo, crest | Breadcrumb from Yoast when present, else our own; intro/photo/crest = **force fields** (photo falls back to a default in Settings) |
| 2 | Jump nav: Contact · GP Medical · Recent Comments · Current Waiting Times · FAQs | Generated; a tab is dropped when its section is empty |
| 3 | Sidebar: Contact Details & General Information | **Force fields**: unit name, address, phone, email, licensing webpage URL |
| 4 | Sidebar: GP Medical Assessments | **Force field** (intro + checklist), falling back to a shared default text in Settings |
| 5 | "Firearms Licensing in {area}": area description | **Force field** (text) + auto sentence from FOI holders ("5,670 shotgun and 1,684 firearm certificate holders…") |
| 6 | Previous and Current Applicant Reports (3 quote cards) | **Force field**, repeatable: quote, application type, month, source |
| 7 | Current Waiting Times: FOI ref + date, 5-row table | Plugin data (latest FOI response) |
| 8 | Reported Shotgun Grant Times: previous vs latest round bars + change box | Plugin data. New **horizontal bar chart** (inline SVG, accessible table); hidden with fewer than 2 rounds |
| 9 | National Firearms Licensing Overview: rank X/43, holders, received, completed | Plugin data (latest NPCC quarter) |
| 10 | Preparing for Your Firearms Security Inspection (image + "View Gun Cabinets") | **Shared** text/image/button in Settings, with `{force}` placeholder |
| 11 | Frequently Asked Questions – {Force} (two columns) | **Force field**, repeatable Q/A; one FAQPage JSON-LD (reuse the portal FAQ markup) |
| 12 | Related Guides and Articles | Reuse `[gco_police_guides]`; IDs from Settings, overridable per force |

**Tasks**
- [x] **Data:** table `gco_force_profiles` (one JSON document per force, so new fields need no schema change); `DB_VERSION` 3. Field list, sanitising and completeness in `GCO_PD_Profile`.
- [x] **Admin:** Police Data → **Force Pages** lists every force (page, template/old layout, what is filled) with an **Edit content** screen in 7 tabs. It has media pickers, add/remove/reorder rows (providers, quotes, FAQs), an unsaved-changes warning, a cap + nonce check, per-type sanitising (`wp_kses` for rich text), and View/Edit page links. Saving purges that page's cache.
- [x] **Shared defaults** (Force Pages → Shared defaults): default hero photo, tagline, GP text + checklist + link, the security-inspection block (text, image, products, button), related-guide IDs and the disclaimer. `{force}` is replaced per page.
- [x] **Render:** `[gco_force_page force="auto" part="all|hero|body"]` (also a WPBakery element) builds the page from `templates/force-page/*.php` (theme-overridable). Every section hides when empty; the H1 and breadcrumb use the page title, so SEO titles are unchanged.
- [x] **Data sections:** waiting-times table with change, FOI ref + date; bar chart of the last 4 rounds + change box; full history table; NPCC overview cards; holders sentence that updates with each round.
- [x] **Assets:** force-page CSS in `front.css` (jump nav with scroll highlight, sidebar layout, quote cards, bar chart). Below 1024px the sidebar moves under the main content; no horizontal scroll at 375px.
- [x] **Linking:** Hampshire slug variant, and the linker now also picks up forces saved with `page_id = 0`. All 21 existing pages link.
- [x] **Migration tool:** `wp gco-pd force-pages status | migrate [--force=slug|--all] [--apply] [--overwrite] [--keep-content] | restore --force=slug`.
  - Dry run unless `--apply`. Before the first change the old content and its WPBakery CSS are kept in `_gco_pd_pre_m6_content` / `_gco_pd_pre_m6_css` (plus a WordPress revision); `restore` puts both back.
  - Dry run on all 21 pages (local copy): every section recognised (contact, area, applicant checklist, GP providers, quotes, FAQs, review dates); waiting times, national context and guides are skipped because the data now generates them.
  - The shared inspection text, products and disclaimer are taken from the first migrated page, with every name variant replaced by `{force}`.
- [x] **Pilot on the local copy:** Derbyshire migrated and checked at desktop and phone width.
- [ ] **Rollout on live:** install the release, run `migrate --force=derbyshire` (dry run, then `--apply`) → client sign-off → `migrate --all --apply` → review each page against its backup.
- [ ] **Shared defaults on live** (needs the media library): default hero photo, inspection image, related-guide IDs, GP "more about" link.
- **Out of scope (per the quote):** the ~22 forces that have no page today. The template and data cover them, so the client only needs to create a page, add `[gco_force_page]` and fill the profile fields. Creating and writing those pages is quoted separately if asked.
- [x] **Tests:** extractor (8 tests on a page in the real layout), bar chart (2). All 105 unit tests pass. Sanitising checked against the live code (scripts, `javascript:` links, bad emails/dates stripped; save is idempotent).
- **Done when:** all 21 existing pages render the template with their original text carried into fields (nothing lost, the report reviewed); each page's sections hide cleanly when empty; the portal, search and A–Z link to every force with a page; and the client has signed off Derbyshire before the batch.

### M7 — Real data, QA & launch · `release/1.0.0` · 2 days
- [ ] Load the client's real FOI data (arrives end of November) + any earlier rounds/quarters for trends
- [~] Full QA checklist (spec §12): **done on the local copy** (results below); repeat on live with real data
- [~] PageSpeed before/after: assets verified (nothing on non-police pages, JS deferred, 5.4 KB + 2.6 KB gzipped); run PageSpeed itself on live
- [x] Short admin guide for Emma: **Police Data → Help** screen inside the plugin; README rewritten (install, shortcodes, WP-CLI, hooks)
- [x] `wp gco-pd portal create|apply|restore` puts the new portal on the live page with a backup (`apply --page=4180`)
- [x] `v0.3.0` built for live testing (install the zip, run the dry-run migrations)
- [ ] Release `v1.0.0` → zip → install on live → publish rounds → `portal apply` → force-page migration → purge caches
- **Done when:** live portal and force pages show published data; client confirms.

**QA run, local copy, 16 Nov 2026**

| Spec §12 item | Result |
|---|---|
| Derbyshire FOI row: values, statuses, date, ref | pass (32 days, Actual, 11 Jun 2026, 01/FOI/26/008471/V) |
| NPCC Q4: 43 forces, 70.67%, 476,079; GMP / Met / TVP aliases | pass |
| Blank / Not provided: shown as such, unranked, not 0 in averages | pass |
| Mismatch rows rejected with a message; duplicate force in a round rejected | pass |
| Estimate badge; draft round never visible | pass |
| Publish updates snapshot and bumps the cache version | pass; **WP Rocket purge not testable locally, check on live** |
| Missing from latest round: last figures kept, labelled; ties share rank; fewer than 43 forces | pass |
| Search: partial names, aliases, keyboard, Escape, no-match | pass |
| Non-admin (shop manager, logged-out): 8 screens and 16 handlers denied even with a valid nonce; admin refused without nonce or via GET | pass |
| Malicious uploads: PHP renamed .xlsx/.csv, .php, double extension, .exe, over 2 MB; CSV formula injection on export | pass |
| Mobile 375 px, real Porto theme: portal and force page, no page-level scroll, tables scroll inside their cards | pass |
| No plugin assets on non-police pages (home, about, shop category, blog, FAQ, guides, old portal) | pass |
| Cost per view | force page: 1 plugin query (the other 61 are the WooCommerce product grid); portal: 0 |

Found and fixed during QA: invalid `font-family` declaration (browsers dropped it), editor screens denied by WordPress (menu hiding), `page_id = 0` forces never auto-linked.

### M8 — Extra FOI figures, percentages and shared departments · `feature/m8-extra-figures` (from client feedback, 16 Nov 2026)

Emma (client) raised three points; all three are built, optional and off by default for visitors.

| Feedback | What was built |
|---|---|
| "We asked for application volumes, outstanding applications, backlog ages and service standards. Publish them? The spreadsheet must account for them." | 20 new optional FOI columns (blue headers) in the template and importer: applications received/completed + period, outstanding + oldest outstanding (days), backlog age bands (up to 3 / 3-6 / 6-12 / over 12 months) with one unit and one status, service standard (wording, days, % met). Same Actual / Estimate / Not provided rule per figure. Stored from the first round that has them. **Public only when switched on** (4 tick-boxes in Settings: volumes, outstanding, backlog, standards), so the client can decide later. Force pages get an "Applications, Backlog & Service Standards" section; portal stat cards `outstanding` and `standard`. Round editor gets a second tab. |
| "Some forces give percentages rather than numbers." | Backlog bands have a Count or Percentage unit per row. Percentages are validated (0-100, should add to about 100), shown as given with a note, and **never summed into counts**. "Met standard %" is a percentage by nature; a bare value of 1 or less is read as a fraction (Excel stores 82% as 0.82) with a notice in the preview. Percentages for other figures: not built (ask the client which, if any). |
| "Shared departments: same data against both forces, but no double counting in national statistics." | `shared_group` on each force (Police Data > Forces). Per figure, forces in a group that carry the **same** value are counted once in national totals and averages (NPCC holders/applications/% within SLA, FOI waiting-time averages, new extras); **different** values all count. Each force keeps its own page and rank, and shows a "shares a department with ..." note. Snapshot reports forces vs licensing departments ("Across 41 forces (39 licensing departments)"). The import preview warns when a group disagrees with itself. |

- [x] DB version 4 (new response columns, `forces.shared_group`); old rows and old spreadsheets keep working (new headers optional)
- [x] Spreadsheet: new template with the blue column group, dropdowns, validation, red mismatch cells, amber warnings (percentages not adding to 100, counts not adding to outstanding), instruction rows for all three topics, Forces-sheet reference column
- [x] Tests: 123 unit tests pass; end-to-end QA on the local copy: 35 checks (import preview, commit, re-import = unchanged, export then re-import = unchanged, old template still imports, national figures with a shared department, switches off = nothing public)
- [ ] **Client to confirm:** which forces share a department; which of the four groups to publish; whether any figure other than the backlog arrives as a percentage; the exact wording and bands of the new FOI questions (the age bands and the service-standard fields are our reading of "backlog ages" and "service standards")

### M9 — FOI data by application type, per-type service standards, online/post · `feature/m9-by-type` · **PLANNED, nothing built yet** (client feedback round 2, 18 Nov 2026)

**Status:** requirements confirmed by the client (Emma, via James); three small points still to confirm (§ Open questions). **Do not start coding until those are answered and the scope/time is agreed.** The agreed rule: finalise the data sheet first, then restructure the plugin once, so nothing is built twice.

#### 1. What the client confirmed

| # | Client answer | Consequence |
|---|---|---|
| 1 | Five application types: new shotgun grants, new firearm grants, shotgun renewals, firearm renewals, firearm variations | Same five as the existing waiting-time metrics (`sg_grant`, `fac_grant`, `sg_renew`, `fac_renew`, `variation`) |
| 2 | Volumes (received, completed, outstanding) requested **per type**; outstanding also has an **overall total** for shotgun and firearm applications; **backlog age bands are overall**, not per type | The M8 backlog bands stay as built. Volumes and outstanding move to a per-type structure; overall totals stay |
| 3 | Service standards: target days and % completed within target, **separately for new grants, renewals and variations**; % "where recorded". Client proposed six exact headers (below) | Replaces the single service-standard fields from M8 |
| 4 | Also requested: whether each force accepts **online applications** for new grants, renewals and variations, and whether any must still be **submitted by post** | New yes/no fields |
| 5 | "Capture everything even if it isn't published straight away" | Collect all of it; publishing stays behind per-group switches in Settings (all off by default) |
| 6 | Shared departments: Bedfordshire + Cambridgeshire + Hertfordshire; Norfolk + Suffolk; Staffordshire + West Midlands; Surrey + Sussex | Already supported (shared groups are data). Enter these four groups on the Forces screen; nothing to build. Note West Midlands and Staffordshire share across what is otherwise a one-force-one-page layout: both keep their own page |

Client's proposed service-standard headers (to be used exactly):
`New Grants - Target Processing Time (Days)` · `New Grants - Completed Within Target (%)` · `Renewals - Target Processing Time (Days)` · `Renewals - Completed Within Target (%)` · `Variations - Target Processing Time (Days)` · `Variations - Completed Within Target (%)`

#### 2. Open questions (send to the client once, together)

1. **Overall outstanding total:** "an overall total for shotgun and firearm applications": one combined total, or a shotgun total **and** a firearm total? (Plan assumes both can be held; the combined total is the existing `outstanding` field.)
2. **Post:** is "must still be submitted by post" asked **per category** (grants / renewals / variations) like the online question, or once **overall**? (Plan holds it per category; an overall answer fits the same fields.)
3. **Variations:** confirm variations apply to **firearm certificates only** (shotgun certificates have none), so the fifth type is "firearm variation".

Safe defaults if unanswered: hold both shotgun and firearm totals plus the combined total; hold post per category; variations = firearm. Changing any of these later means a column change, so confirm before the sheet is issued.

#### 3. Proposed spreadsheet structure (template v3)

The by-type volumes would add about 30 columns to a one-row-per-force sheet (5 types x 3 figures, each with a status), which makes the sheet unusable. So:

- **Sheet "FOI Data"** (unchanged shape: one row per force per round): keeps everything from M8 except the single service-standard fields. Adds:
  - the six service-standard columns above, each with a status column for the days figure (the % is optional "where recorded");
  - `Outstanding Applications - Shotgun` and `- Firearm` (if confirmed), next to the existing overall outstanding;
  - six dropdown columns (Yes / No / blank): `Online Applications Accepted - New Grants / Renewals / Variations`, `Postal Applications Still Required - New Grants / Renewals / Variations`.
- **New sheet "FOI Application Types"** (one row per force **per application type**, so 5 rows per force per round, mirroring the table in the force's FOI reply): `Round | Police Force | Application Type (dropdown of the five) | Received | Received Status | Completed | Completed Status | Outstanding | Outstanding Status`. Dropdowns, red mismatch cells and a duplicate check on Round + Force + Type, as on the other sheets.
- "How to fill" gets matching instructions; the "Forces" sheet reference column for shared departments is filled with the four groups above.
- Old files (v1, v2) keep importing: every new column and the whole new sheet are optional.

#### 4. Data model (DB version 5)

- `gco_responses`: add `std_grant_days`, `std_grant_pct`, `std_renew_days`, `std_renew_pct`, `std_variation_days`, `std_variation_pct` (+ status for each days figure and each %); `online_grant|renew|variation` and `post_grant|renew|variation` as `ENUM('yes','no') NULL`; `outstanding_sg`, `outstanding_fac` (+ status) if confirmed. **The M8 single-standard columns (`standard_text`, `standard_days`, `standard_met_pct` and statuses) are left in place but unused**, not dropped: a live install of 0.4.x must not lose anything, and dropping can happen in a later clean-up.
- New table `gco_response_types`: `round_id`, `force_id`, `app_type ENUM('sg_grant','fac_grant','sg_renew','fac_renew','variation')`, `received|completed|outstanding` (INT NULL) each with `_status`; unique key on (`round_id`,`force_id`,`app_type`). Deleting a draft round or a force's response cascades in the repository (no foreign keys, as elsewhere).
- Overall `apps_received` / `apps_completed` / `outstanding` stay as the force's own overall totals (a force may give only totals).

#### 5. Plugin work

1. **Schema + upgrade** (DB v5), new table, uninstall list.
2. **Validator:** per-type rows (count + status, same pairing rule); service standards (days + status, % with the M8 fraction rule); yes/no normaliser (Yes/No/blank, also "Y", "N", "true"); soft warnings when the per-type figures do not add up to the overall total (received, completed, outstanding), never blocking.
3. **Import:** header definitions (all optional); a second sheet type `foi_types` detected by the "Application Type" header; preview section "By application type" with new/changed/unchanged/error per row; duplicate (round, force, type) rejected; commit writes types to the draft round; the M8 shared-department mismatch and identical-figures warnings extended to type rows.
4. **Repository / cache:** save, get and delete type rows; published types gathered into the snapshot input in one query.
5. **Export:** FOI Data CSV in the new header order; **a second CSV for application types**; both on the rounds screen (the backup must round-trip: export then import = "unchanged").
6. **Calculator** (snapshot schema 3): per force, a `types` block (latest response) with change since the previous round; national totals per type with the shared-department rule (one count per group when the group's figures are identical); national service-standard averages per group; counts of forces accepting online / still requiring post per category ("31 of 43 accept online new-grant applications"; "not provided" never counted as "no").
7. **Admin:** round editor gets a third tab "By application type" (one row per force and type, 215 rows, same one-JSON-field save, live validation), the six standards and the online/post dropdowns on the second tab; Forces screen unchanged. Settings: new switches `show_type_volumes` and `show_application_methods` (the existing `show_standards` covers the six standards); all off by default.
8. **Front end (behind the switches):** force page: "Applications by type" table (received / completed / outstanding per type, change since last round); "Service standards" table (three rows: target days, % within target); "How to apply" strip (online / post badges per category). Portal stat cards: forces accepting online applications; national average % within target per group.
9. **Help screen, README, tests, QA, version 0.5.0.**

#### 6. Tests and acceptance

- Unit: validator (types, standards, yes/no, sum warnings); parser (new sheet detection, old files still parse); calculator (per-type national sums with a shared group, standards averages, online counts, blanks never counted as zero or "no"); exporter round-trip.
- End to end on the local copy, as for M8: fill template v3 with realistic rows (shared group, a force that answered some types only, post-only force, percentages, bad rows) and check preview, commit, re-import = unchanged, export then re-import = unchanged, snapshot totals with a shared department, front end with all switches off (nothing public) and on.
- Spreadsheet: validations, red/amber rules and instruction rows checked by reading the workbook back; the plugin's own importer must read every new header.
- The previous suites (data rules 21, security 14, extras 35, unit tests) must still pass.

#### 7. Effort and order

About **3 to 4 working days**: schema, validator and import/export (1.5), calculator and snapshot (0.5), round editor tab and settings (1), front end (0.5 to 1), spreadsheet v3, tests, docs and release (0.5 to 1). Order: confirm the three questions, issue template v3 for the client to check, then build; a build can start the day the sheet is signed off.

#### 8. Risks

| Risk | Mitigation |
|---|---|
| The client changes a column after the build starts | Issue template v3 first, build only from the signed-off version |
| Overall total and sum of types disagree in a force's reply | Warning only; both kept as given; the page shows what the force said |
| 215-row grid is slow or hits `max_input_vars` | Same single-JSON-field save as the main grid (about 1 MB cap, tested with 215 rows) |
| A force answers by type for some types only | Missing types stay "Not provided", never 0; national per-type totals count only forces that answered, and say how many |
| Publishing sensitive figures (backlog, outstanding) by accident | Every new group is off by default behind its own switch; the client decides after reviewing the data |

**Done when:** template v3 imports cleanly through the preview, a round round-trips through export and re-import unchanged, national figures count each shared department once, nothing new is visible to visitors until its switch is on, and the previous test suites still pass.

## 4. Timeline

Assumes work starts **Mon 16 Nov 2026**; the FOI data arrives ~**30 Nov**.

| Week | Dates | Milestones | Release |
|---|---|---|---|
| 1 | 16–22 Nov | M0, M1, M2 | `v0.1.0` (admin + import on staging) |
| 2 | 23–29 Nov | M3, M4, M5 started | `v0.2.0` (shortcodes on staging) |
| 3 | 30 Nov–6 Dec | M5 sign-off, M6 (template, admin, migration tool, Derbyshire pilot) | `v0.3.0` (force template on live as draft) |
| 4 | 7–13 Dec | M6 batch migration, M7 | `v1.0.0` go-live |

Buffer: M5 sign-off and the data arriving late are the two things most likely to slip. M6 doesn't depend on the portal sign-off, so it can start in parallel. The move to a plugin force-page template (16 Nov) adds 2–3 days to M6 and pushes go-live by about a week; flag it to the client as a scope change.

Testing environment: the staging copy has no `uploads/`, so images (hero photos, guide thumbnails, crests) only show on live. Real-content testing happens on live with pages kept as drafts or backed up.

## 5. Spec review — decisions to confirm before/while coding

1. **One "current" snapshot, not one per round.** The spec stores `gco_pd_snapshot_{round}`, but the leaderboard comes from NPCC quarters, trends span all rounds, and forces missing from the latest round must fall back to their last published figures. Proposal: a single `gco_pd_snapshot` (autoload = no) built from *all* published FOI rounds + NPCC quarters, rebuilt whenever either is (un)published.
2. **"Xth of 43" wording.** With forces unranked (not provided), show "Xth of N" where N = forces ranked on that metric.
3. **Estimates in national averages.** Spec implies they count (only `not_provided` is excluded). Confirm, and show "based on N forces".
4. **% within SLA edge cases.** `apps_completed_12m` = 0 or NULL → no %, unranked on the leaderboard.
5. **Foreign keys.** `dbDelta()` doesn't create them; integrity is enforced in the repository layer (block deleting a force with data, cascade deleting a draft round's responses).
6. **NPCC notes.** The template has a `Notes` column on NPCC Data; the `gco_npcc` table has none. Add `notes TEXT NULL` (private) or ignore it.
7. **`max_input_vars`.** The round editor posts ~730 fields; save per row via `admin-post` / one JSON payload instead of one big form.
8. **FAQPage schema.** Check whether Yoast / Rank Math / Porto already emits FAQ schema to avoid duplicates.
9. **Page caching.** Confirm which cache layer is live (WP Rocket, IONOS, Cloudflare?) so publish purges the right one.

## 6. Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Data arrives late / incomplete | Launch slips | Build & QA on fixtures; launch works with partial coverage (fallback + "Not provided") |
| NPCC changes layout (grant/renewal split from Q1 2026/27) | Import breaks | Header-based mapping; unknown columns reported in preview, not fatal |
| Force-page migration loses written content | Client-visible regressions, SEO loss | Old content kept in a revision + `_gco_pd_pre_m6_content` meta; dry-run report per page; `restore` command; pilot on Derbyshire before the other 20 |
| Extra M6 scope (profile fields, admin form) | Go-live slips ~1 week | Agreed 16 Nov; template parts ship incrementally, and sections hide when empty, so pages can go live before every field is filled |
| Host lacks PHP 8.1 / `zip` | Plugin won't run | Pre-flight check in week 1; CSV fallback |
| Scope creep (cabinet finder, comparison table, PDF AI) | Timeline | Already out of scope in the spec — quote separately |

## 7. Open client questions (from spec §13, still open)

1. Do all 43 forces respond each FOI round?
2. FOI PDFs public on force pages or private?
3. Earlier FOI rounds / NPCC quarters for trend history at launch?
4. Portal copy (intro, "How we research", FAQs) — or we draft placeholders?
5. *(new)* Is there a staging site on IONOS, and can we get WP admin + licensed Porto/WPBakery for local dev?
6. ~~List / URLs of the 20 existing force pages to migrate.~~ Found: 21 force pages on the site; 20 auto-link, and Hampshire needs a slug fix.
7. *(new, M6)* Accept the force-page template scope change (+2–3 days, go-live ~13 Dec)?
8. ~~The 22 forces without a page: create them now?~~ Decided: out of scope, the client adds them (quote: "adding the remaining forces is just the written content"). Note the quote says 20 existing pages, but 21 exist; confirm Hampshire is included.
9. *(new, M6)* Applicant quotes: where do they come from, and do we have permission to publish them with a source name?
10. *(new, M6)* Police crests in the hero: confirm we may use them. Crests are protected marks, so the fallback is no crest.
11. *(new, M6)* GP medical text: one shared text for all forces, or per-force wording? (Built to support both: each page keeps its own text, with the shared text as fallback.)
12. *(new, M6)* **Content to check:** the Derbyshire page lists a Stafford ST18 address (looks like Staffordshire's). The "about this area" texts quote holder numbers "as of June 2026" that will go stale; the template already adds an up-to-date holders sentence, so the client may want to remove the numbers from the text.
13. *(new, M9)* Overall outstanding total: one combined total, or a shotgun total and a firearm total?
14. *(new, M9)* "Must still be submitted by post": per category (grants, renewals, variations) or once overall?
15. *(new, M9)* Variations apply to firearm certificates only?
