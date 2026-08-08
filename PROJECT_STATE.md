# PROJECT_STATE — p.analysisofkarma.com (Vedic astrology site)

**Repo/branch:** `jagmalla/new1234` · work branch `claude/astrology-software-mods-ccwpjf` (push here only).
**Delivery:** commit + push **AND** hand a credential-scanned ZIP of *only changed files* (preserve `app/` & `public_html/` paths); user extracts onto live hosting (not auto-deployed). Keep the "System is Under Testing" popup LIVE.

## Architecture
- PHP 8.4, no framework. PSR-4: `AutoBusiness\Foo\Bar` → `app/Foo/Bar.php`. Web root `public_html/`.
- Front controller `public_html/index.php` routes by `?r=` (e.g. `GET calc`, `GET calc/varshaphal`, `GET calc/lalkitabVarsh`). Live A2 host rewrites clean paths → `?r=` via `.htaccess`; JS fetches clean paths (`/calc/lalkitabVarsh?…`).
- Main page: `CalcController::show()` (AdminGuard-gated; `APP_ENV=local` bypasses) → renders `app/Http/views/calc-v2.php` (the big shell: left menu, chart panel, full-width sections `#sec-*`).
- Namespaces: `Astro\Calc\CalculationEngine`, `Astro\Ephemeris\EphemerisFactory`, `Astro\Time\JulianDay`.

## Lal Kitab subsystem (current focus)
- Engine `app/Astro/LalKitab/LalKitabEngine.php`; data `lalkitab_data.json` via `LalKitabData::section('key')`. Chart is **fixed-Aries** (house 1 = Aries always); planets placed by bhava; uch/neech from real D1 sign.
- `compute($chart,$age,$active)` → all reading categories incl. `age_cycle`, `varsh_gyan`.
- `varshReading($chart,$age)` (annual chart): rotates natal placements through the `varsh_gyan` age-permutation (varsh-house *i* carries natal house `row[i]`), re-runs the SAME pipeline (planet/house/yoga/remedy/do-dont) on rotated placements; returns `north` payload, `saar`, `score`, basic-info. `ageCycle()` = Vimshottari-style: 4 अवस्था=महादशा, active planets split into age sub-periods=अन्तर्दशा, each with prediction+remedy.
- Endpoint `CalcController::lalkitabVarshJson()` (route `calc/lalkitabVarsh`) → returns `north`, `summary_html`, `bar_html`, `html` (renders `_lalkitab_varsh.php`). Auspiciousness bar reused from `Astro\Muhurat\Auspiciousness::score/band/barHtml`.
- View `_lalkitab.php` (inside `#sec-lalkitab`): 2-col grid (`#lk-teva-col` teva chart | prediction panel with `#lk-select` dropdown of `.lk-view[data-lk="…"]`).
- **Left main menu** (`calc-v2.php`): "Laal Kitab" `l2-mi` now has an `l2-sub` submenu (data-lk = overview/varsh/agecycle/remedy). Click handler dispatches `#lk-select` change → `showLkView()`.
- JS `public_html/assets/js/lalvarsh.js` (`ABLalVarsh`): on age change fetches endpoint, draws annual + janam charts (`ABChart.renderNorth`), injects summary/bar/body. Lazy-loads on first varsh open.

## Recent decisions
- Varsh view = **full-width Vedic-Varshaphal layout**: `showLkView` adds `.lk-wide` to `#sec-lalkitab` (hides teva col, section spans full width); inner `.lkv-main` grid = annual chart | prediction; janam teva chart re-rendered below (`#lkv-janam`).
- Politics engine reframed as "potential ceiling" (yoga-counting can't separate real winners from losers — validated & documented in-code).

## Current task: DONE
Varsh Kundali + Age Timeline promoted to left-menu submenu; Varsh view rebuilt to Varshaphal layout. All committed/pushed (`fb88766`).

## Suggested next steps
- Height-sync the annual-chart card to the prediction column (Varshaphal syncs; here it's natural flow).
- Optional: apply full-width to Age Timeline too; add a year→calendar-date label.

## Gotchas
- **Tailwind CDN is blocked in the sandbox** → `.hidden` (display:none) doesn't apply in Playwright; inject `.hidden{display:none!important}` to simulate production. Screenshots need this or the "hidden" home panel shows.
- Playwright: `require('/opt/node22/lib/node_modules/playwright')`, chromium `/opt/pw-browsers/chromium`, run with `/opt/node22/bin/node`. Dev server needs a router that serves `public_html/*` static files AND maps clean paths → `?r=` (php -S `return false` serves from cwd, not public_html — must `readfile` assets manually). Assets use `Asset::url()` → `?v=timestamp`.
- Deliver **interdependent files together** — a partial deploy (view calling a not-yet-deployed engine method) → PHP fatal → HTML error page → "Unexpected token '<'… not valid JSON" on AJAX views.
- PHP test harness: `define('AB_ROOT',realpath('.'))` + a `spl_autoload_register` mapping `AutoBusiness\…`→`app/…php`.
- Commit trailers required: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>` + `Claude-Session: https://claude.ai/code/session_01PEe7MTRHVeppoGi3kv1pA2`. Never put the model identifier in commits/PRs/code.
