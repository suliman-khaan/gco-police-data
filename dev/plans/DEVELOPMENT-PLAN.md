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

### M9 — FOI data by application type, per-type service standards, online/post · `feature/m9-by-type` · **BUILT** (requirements confirmed by the client 18 Nov 2026)

**Status:** built and tested on the local copy; version 0.5.0. The data sheet (template v3) is the single source of truth and is built together with the plugin so nothing is done twice.

#### 1. What the client confirmed

| # | Client answer | Decision |
|---|---|---|
| 1 | Five application types: new shotgun grants, new firearm grants, shotgun renewals, firearm renewals, firearm variations | Same five as the waiting-time metrics (`sg_grant`, `fac_grant`, `sg_renew`, `fac_renew`, `variation`). Variations are **firearm certificates only** |
| 2 | Volumes (received, completed, outstanding) **per type**; outstanding also has **one overall total** (shotgun and firearm together, no separate shotgun/firearm totals); **backlog age bands are overall** | Per-type rows in a new sheet; the existing overall `Applications Received / Completed` and `Outstanding Applications` stay as the overall totals; the M8 backlog bands stay as built |
| 3 | Service standards: target days and % completed within target for **new grants, renewals, variations**; % "where recorded". Client's six exact headers (below) | Six columns exactly as proposed. No status columns: a number means Actual, blank means Not provided (a range goes in Notes). Replaces the single M8 service-standard fields |
| 4 | Whether each force accepts **online** applications for new grants, renewals and variations; whether any must still be **submitted by post** | Three Yes/No columns for online (per category) and **one overall Yes/No** for post, with specifics in Notes |
| 5 | Capture everything even if not published straight away | All collected; every group stays behind a Settings switch, all off by default |
| 6 | Shared departments: Bedfordshire + Cambridgeshire + Hertfordshire; Norfolk + Suffolk; Staffordshire + West Midlands; Surrey + Sussex | Already supported (data, not code). Entered on the Forces screen; listed as a reference on the template's Forces sheet |

Service-standard headers (exact): `New Grants - Target Processing Time (Days)` · `New Grants - Completed Within Target (%)` · `Renewals - Target Processing Time (Days)` · `Renewals - Completed Within Target (%)` · `Variations - Target Processing Time (Days)` · `Variations - Completed Within Target (%)`

#### 2. Spreadsheet (template v3)

- **"FOI Data"** (one row per force per round): everything from M8 except the five single service-standard columns, plus the six service-standard columns, `Online Applications - New Grants / Renewals / Variations` (Yes/No) and `Postal Applications Still Required` (Yes/No), before Notes and PDF.
- **New sheet "FOI Application Types"** (one row per force per application type, 5 rows per force per round): `Round | Police Force | Application Type | Received | Received Status | Completed | Completed Status | Outstanding | Outstanding Status`. Dropdowns, red mismatch cells, duplicate check on Round + Force + Type.
- v1 and v2 files keep importing (every new column and the new sheet are optional).

#### 3. Data model (DB version 5)

- `gco_responses`: `std_grant_days|pct`, `std_renew_days|pct`, `std_variation_days|pct` (+ derived status), `online_grant|renew|variation` and `post_required` as `ENUM('yes','no') NULL`. The M8 single-standard columns stay in the table, unused (nothing is dropped from a live install).
- New table `gco_response_types` (`round_id`, `force_id`, `app_type`, `received|completed|outstanding` each with `_status`; unique on round + force + type; cascade delete done in the repository).

#### 4. Plugin work

Schema and upgrade · validator (type rows, standards, yes/no) · parser and importer (second sheet, preview section, duplicates, warnings that overall totals differ from the sum of the types) · repository and export (second CSV, header-driven main CSV) · calculator (per-force types block with change, national per-type totals with the shared-department rule, standards averages, online/post counts) · round editor third tab and updated second tab · Settings switches `show_type_volumes` and `show_application_methods` (with the existing `show_standards`) · force page sections and portal stat cards (behind the switches) · Help, README, tests, QA, version 0.5.0.

#### 5. Tests and acceptance

Unit tests (validator, parser, calculator, exporter) plus an end-to-end run on the local copy with template v3 filled with realistic rows (shared group, a force that answered only some types, bad rows): preview, commit, re-import = unchanged, export then re-import = unchanged, national totals count a shared department once, nothing public with all switches off. The earlier suites (data rules, security, M8 extras, unit tests) must still pass.

#### 6. Effort and risks

About 3 to 4 working days. Main risks: the client changing a column after the build starts (mitigated: template v3 is the contract, shown to the client first); an overall total that disagrees with the sum of types (warning only, both kept as given); a type answered for some forces only (missing stays "Not provided", never 0, and national totals say how many forces answered); publishing sensitive figures by accident (everything off by default).

**Done when:** template v3 imports cleanly through the preview, a round round-trips through export and re-import unchanged, national figures count each shared department once, nothing new is visible until its switch is on, and the earlier suites still pass.


#### 7. Result

- [x] Schema v5 (`gco_response_types`, six standards, online/post); the M8 single-standard columns are left in place, unused
- [x] Template v3: `FOI Data` (45 columns, client's six exact headers), new `FOI Application Types` sheet, four shared groups listed on the Forces sheet; headers generated from the plugin's own constants
- [x] Importer reads both sheets (types preview, duplicates, unknown types, overall-vs-types warning, blank main row created when a force only has type rows), exporter writes both (round-trip unchanged), v1 files still import
- [x] Calculator: per-force types block with change, national totals per type counting a shared department once, standards averages, online/post counts (a blank is never "no")
- [x] Round editor: third tab (215 rows, one JSON field, about 79 KB), second tab updated; Settings: six publish switches, all off; force page: by-type table, standards table, how-to-apply strip; portal cards `outstanding` and `online`
- [x] Tests: 138 unit tests; end-to-end QA on the local copy: 43 checks (including the real grid save handler with a by-type payload), plus the earlier suites (data rules 21, security 14, shared-department warning 5) all passing

### M10 — Force page in the blog layout · `feature/blog-layout` · **BUILT** (client request 19–20 Nov 2026)

Client asked for the force pages to follow the new blog template (reference: /where-to-install-a-gun-cabinet) with the FOI data in a sticky sidebar. Content-from-spreadsheet was discussed and **left for later** (separate piece of work, to be scoped and priced if the client wants it).

- Main column opens with a hero card (breadcrumb, tag, "Updated" date, title, intro, photo/crest), then area, waiting times, applications, reports, GP medical, contact, national overview, inspection, FAQs. Guides and sources run full width underneath.
- Sidebar: sticky **FOI at a glance** (five waiting times, shotgun-grant change, NPCC rank / % within 4 months / holders, FOI reference and date, two buttons) and **On this page** links with the active section highlighted. Below 992px: one column, FOI box straight after the hero.
- `[gco_force_page]` is now one row; `part="hero"` renders nothing and `part="body"` renders the whole page, so pages migrated before 0.6.0 keep working. `migrate --apply` moves old two-row pages to the single row (profile fields are only filled when empty).
- Smooth scrolling on force pages (not for reduced-motion users).
- Fixed in the round editor: grid header no longer covers row 1 (grid scrolls in its own box, pinned below the save bar); stored decimals show as whole numbers (`616.00` -> `616`) so saved backlog counts no longer block the next save.
- Portal layout is applied with `wp gco-pd portal apply`; no WP-CLI means no way to apply it from the admin yet (possible follow-up: an admin button).
- Not checked: hero photo / crest (staging has no media), live PageSpeed.

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
13. ~~Overall outstanding total~~ Answered: one overall total plus the five per-type figures.
14. ~~Post~~ Answered: one overall Yes/No, specifics in Notes.
15. ~~Variations~~ Answered: firearm certificates only.
