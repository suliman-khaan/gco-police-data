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
- [ ] New **draft** page built in WPBakery, matching the client mockup section by section (spec §7 order); live `/police-firearms-licensing` untouched
- [ ] Reuse the blog template's carousel for Applications & Install posts
- [ ] FAQs + FAQPage schema (only if the SEO plugin doesn't already output it)
- [ ] Placeholder copy where the client hasn't supplied intro / "How we research" / FAQs
- **Done when:** client signs off the draft on staging.

### M6 — Force page template & migration · `feature/m6-force-pages` · 2 days
- [ ] Force page layout: waiting times + badges + change, rank, national context, trend table + chart, last updated / source
- [ ] Migrate the 20 existing force pages: replace the hard-coded figures with shortcodes, keep the written content; check each page's slug ↔ force link
- [ ] Back up each page (revision / export) before editing; do it on staging first
- **Done when:** all 20 pages render plugin figures, written content unchanged, links from the portal resolve.

### M7 — Real data, QA & launch · `release/1.0.0` · 2 days
- [ ] Load the client's real FOI data (arrives end of November) + any earlier rounds/quarters for trends
- [ ] Full QA checklist (spec §12), PageSpeed before/after on the portal and a non-police page
- [ ] Release `v1.0.0` → zip → install on live → publish rounds → swap the portal live → purge caches
- [ ] Short admin guide for Emma (how to enter a round, import, publish)
- **Done when:** live portal and force pages show published data; client confirms.

## 4. Timeline

Assumes work starts **Mon 16 Nov 2026**; the FOI data arrives ~**30 Nov**.

| Week | Dates | Milestones | Release |
|---|---|---|---|
| 1 | 16–22 Nov | M0, M1, M2 | `v0.1.0` (admin + import on staging) |
| 2 | 23–29 Nov | M3, M4, M5 started | `v0.2.0` (shortcodes on staging) |
| 3 | 30 Nov–6 Dec | M5 sign-off, M6, M7 | `v1.0.0` go-live |

Buffer: M5 sign-off and the data arriving late are the two things most likely to slip. M6 doesn't depend on the portal sign-off, so it can start in parallel.

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
| WPBakery page edits break existing layout | Client-visible regressions | Staging first, page backups, migrate one page and sign off before the other 19 |
| Host lacks PHP 8.1 / `zip` | Plugin won't run | Pre-flight check in week 1; CSV fallback |
| Scope creep (cabinet finder, comparison table, PDF AI) | Timeline | Already out of scope in the spec — quote separately |

## 7. Open client questions (from spec §13, still open)

1. Do all 43 forces respond each FOI round?
2. FOI PDFs public on force pages or private?
3. Earlier FOI rounds / NPCC quarters for trend history at launch?
4. Portal copy (intro, "How we research", FAQs) — or we draft placeholders?
5. *(new)* Is there a staging site on IONOS, and can we get WP admin + licensed Porto/WPBakery for local dev?
6. *(new)* List / URLs of the 20 existing force pages to migrate.
