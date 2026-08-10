# Analysis of Karma — Complete Project Documentation

**Live site:** `p.analysisofkarma.com` · **Repository:** `jagmalla/new1234`
**Stack:** PHP 8.4, no framework, MySQL/MariaDB (optional for most pages), vanilla JavaScript
**This document describes commit `324bed1` and is the authoritative overview.**

> `docs/PROJECT_GUIDE.md` is an earlier guide written in July 2026. It is still
> useful for the Vedic engines but predates the whole Lal Kitab subsystem, the
> Muhurat modules, the rebuilt Kundali Milan and the self-check harness. Where
> the two disagree, **this file is current**.

---

## Table of contents

1. [What the software does](#1-what-the-software-does)
2. [How a request flows through the system](#2-how-a-request-flows-through-the-system)
3. [Folder map](#3-folder-map)
4. [The plumbing (`app/Core`)](#4-the-plumbing-appcore)
5. [Astronomy: time, ephemeris, chart](#5-astronomy-time-ephemeris-chart)
6. [The Vedic side](#6-the-vedic-side)
7. [The Lal Kitab side](#7-the-lal-kitab-side)
8. [Kundali Milan (matchmaking)](#8-kundali-milan-matchmaking)
9. [Muhurat (electional astrology)](#9-muhurat-electional-astrology)
10. [The user interface](#10-the-user-interface)
11. [Where the text comes from: data banks](#11-where-the-text-comes-from-data-banks)
12. [Database and migrations](#12-database-and-migrations)
13. [The workflow/automation half of the codebase](#13-the-workflowautomation-half-of-the-codebase)
14. [Testing and the self-check harness](#14-testing-and-the-self-check-harness)
15. [Running it locally](#15-running-it-locally)
16. [Deploying](#16-deploying)
17. [Conventions this codebase follows](#17-conventions-this-codebase-follows)
18. [Known gotchas](#18-known-gotchas)
19. [Glossary](#19-glossary)

---

## 1. What the software does

A visitor enters **birth date, time and place**. The software casts the chart and
produces, on one screen:

| Area | What it produces |
|---|---|
| **Charts** | D1 (Rasi) plus 14 divisional charts — D2, D3, D4, D7, D9, D10, D12, D16, D20, D24, D27, D30, D40, D60 |
| **Strength** | Shadbala, Bhava Bala, Ashtakavarga, Vimshopaka Bala |
| **Timing** | Vimshottari Dasha five levels deep, with per-period predictions |
| **Transits** | Gochar against the natal chart, a 12-month timeline, Sade-Sati / Dhaiya |
| **Annual** | Varshaphal (Tajik): Muntha, Varshesh, Mudda dasha, Saham, Tajik yogas, annual bhava-phal |
| **Predictions** | House, Bhavesh, Graha, Karaka, Yoga, Dosha, Poorva-Shaap / Santaan |
| **Topic analyses** | Wealth, Career, Children, Foreign travel/settlement, Politics |
| **Lal Kitab** | A complete parallel system — see §7 |
| **Matchmaking** | 36-guna Ashtakoot **and** a separate Lal Kitab compatibility reading |
| **Muhurat** | Category-wise auspicious timing, plus a marriage-date finder |

Everything is bilingual-leaning Hindi/English; the reading text is Hindi.

**Two systems, deliberately separate.** The Vedic engines and the Lal Kitab
engines never share verdicts. Lal Kitab has its own chart, its own dignity
rules, its own dasha and its own remedies; mixing the two is the classic way
these systems go wrong. Where both appear on one page they are labelled and
kept apart (for example, the Lal Kitab 35-year dasha is printed under the
Vimshottari strip with the explicit note *"35-साला चक्र, विंशोत्तरी नहीं"*).

---

## 2. How a request flows through the system

```
browser
  │  GET /calc?date=01-12-1980&time=10:30&lat=…&lon=…&tz=5.5
  ▼
public_html/.htaccess        clean path  →  index.php?r=calc
  ▼
public_html/index.php        front controller: a switch on "METHOD route"
  ▼
app/Http/CalcController::show()
  │   ├─ AdminGuard::require()          staff gate (APP_ENV=local bypasses)
  │   ├─ parse inputs (date/time/lat/lon/tz)
  │   ├─ EphemerisFactory::create()     Swiss Ephemeris if configured, else pure PHP
  │   ├─ CalculationEngine::computeChart()      ← the one chart everything reads
  │   ├─ ~30 prediction engines, each wrapped in safe()
  │   └─ LalKitabEngine::compute() + LalKitabProcess::run()
  ▼
app/Http/views/calc-v2.php   one big shell: menu · chart panel · prediction panel
                             · full-width sections (#sec-*) · "और देखें" strip
  ▼
browser JS (public_html/assets/js/*.js) draws charts, lazy-loads panels,
          fetches JSON endpoints for things that change after load
```

**Routes** (all in `public_html/index.php`):

| Route | Handler | Purpose |
|---|---|---|
| `GET calc` | `CalcController::show` | the main page |
| `GET calc/gochar` | `gocharJson` | recompute transits for a new date/place |
| `GET calc/varshaphal` | `varshaphalJson` | annual chart for a chosen year |
| `GET calc/lalkitabVarsh` | `lalkitabVarshJson` | Lal Kitab annual chart for a chosen age |
| `GET calc/dashaPhala` | `dashaPhalaJson` | dasha text for a maha/antar pair |
| `GET calc/dashaEngine` | `dashaEngineJson` | computed dasha analysis |
| `GET calc/muhuratScan` | `muhuratScanJson` | scan a date range for auspicious days |
| `GET calc/citySearch` | `citySearchJson` | place search / reverse lookup from the bundled gazetteer |
| `POST calc/translate` | `translateJson` | on-demand translation |
| `GET calc/ping` | `ping` | health/keepalive |
| `GET milan` | `MilanController::show` | Kundali Milan page |
| `GET canvas`, `POST api/workflow/*`, `POST webhook` | Canvas/Webhook controllers | the automation half (§13) |
| `GET/POST admin/migrate` | `MigrateController` | run the SQL seed migrations |

`public_html/lk-selfcheck.php` is the one page outside this map — the browser
runner for the self-check harness (§14), gated by a key in `.env`.

---

## 3. Folder map

```
.env.example          template for the real .env (which lives in the project root,
                      one level ABOVE the webroot, and is never committed)
bootstrap.php         PSR-4 autoloader + Env loader + session start
runner.php            CLI cron entry: claims jobs, resumes paused ones, runs
                      due schedules within a per-tick budget (`* * * * * php runner.php`)
bin/migrate.php       CLI migration runner
public_html/          ← the webroot. The ONLY directory the browser can reach
  index.php           front controller
  lk-selfcheck.php    browser runner for the Lal Kitab self-check
  .htaccess           clean-path rewrites, cache headers, dotfile denial
  assets/js/          13 vanilla-JS files (no build step, no bundler)
app/
  Core/               Env, Database, AdminGuard, Asset, Csrf, AccessLog, Uuid,
                      MigrationRunner
  Http/               5 controllers + views/
  Http/views/         36 PHP templates (see §10)
  Astro/
    Time/             JulianDay
    Ephemeris/        provider interface + Swiss + pure-PHP analytic
    Geo/              CityGazetteer + cities.tsv.gz (148,038 towns, offline place search)
    Calc/             chart, vargas, dashas, the four bala systems, drishti
    Phala/            28 Vedic prediction engines + their DB repositories
    Gochar/           16 transit / Sade-Sati / timeline classes
    Varshaphal/ Varshesh/ Muntha/ Saham/ Tajik/   annual (Tajik) system
    Muhurat/          15 electional-astrology engines
    Milan/            36-guna Ashtakoot
    LalKitab/         9 classes + the Lal Kitab data bank (JSON)
    Llm/ Ingestion/ Orchestration/   AI book-ingestion modules
  Engine/ Queue/ Security/   the workflow-automation half (§13)
migrations/           26 numbered .sql files — schema + the editable text banks
tests/                15 CLI test scripts
docs/                 specs, rule sheets, decision logs, source workbooks
storage/              runtime state (access log, visit counters) — must be writable
```

Roughly 62,000 lines of PHP/JS/JSON. The five largest files are
`app/Http/views/calc-v2.php` (~5,350 lines, the UI shell),
`app/Astro/LalKitab/lalkitab_data.json` (~7,700 lines of source text),
`LalKitabEngine.php` (~3,150), `_lalkitab.php` (~2,900) and
`LalKitabProcess.php` (~1,500).

---

## 4. The plumbing (`app/Core`)

| Class | Job |
|---|---|
| `Env` | Minimal `.env` parser. No `.env` on disk → falls back to the real process environment. |
| `Database` | One lazily-created PDO (MySQL, utf8mb4, exceptions on, no emulated prepares). |
| `AdminGuard` | `require()` passes when `$_SESSION['staff_id']` is set, **or** when `APP_ENV=local` (dev shortcut). Otherwise 403 JSON. Guards `/calc`, `/milan`, `/canvas` and the migrate pages. |
| `Asset` | `Asset::url('/assets/js/x.js')` appends `?v=<file-mtime>` so a changed file busts the cache with no manual version bumping. `noCacheHtml()` sets no-cache headers on HTML. |
| `Csrf` | Token issue/verify for the canvas and admin POSTs. |
| `AccessLog` | Visit logging into `storage/`. |
| `MigrationRunner` | Applies `migrations/*.sql` in order and records what ran. |
| `Uuid` | UUID v4 generation. |

**The autoloader** is eight lines in `bootstrap.php`: `AutoBusiness\Foo\Bar` maps
to `app/Foo/Bar.php`. There is no Composer, no `vendor/`, and nothing to install.

---

## 5. Astronomy: time, ephemeris, chart

**`Astro\Time\JulianDay`** — conversions between civil date/time (with a
timezone offset) and Julian Day, plus formatting helpers (`toDmy`).

**`Astro\Ephemeris`** — planet positions behind an interface:

- `SwissEphemeris` shells out to a `swetest` binary when `SWETEST_PATH` points at
  one and shell exec is available — gold-standard accuracy.
- `AnalyticEphemeris` is pure PHP, always works, accurate to about 1–2 arc-minutes.
- `EphemerisFactory::create()` picks the best available. Everything downstream is
  identical either way; only precision differs.
- Rahu/Ketu default to the **true (osculating) node**, matching Parashara's Light.
  `NODE_TYPE=mean` in `.env` switches to the mean node.

**`Astro\Geo\CityGazetteer`** — the bundled place gazetteer: 148,038 towns with
state, country, coordinates and IANA timezone in `app/Astro/Geo/cities.tsv.gz`
(outside the webroot), searched by `GET calc/citySearch` and also able to name a
place from coordinates. The place box asks Open-Meteo first when the browser is
online — the same worldwide reach it always had — and falls back to this file
when there is no connection, the service does not answer, or it returns nothing,
so a birth place can always be picked. Old names (Bombay, Calcutta, Bangalore…)
and missing accents resolve. Data credits are in the class docblock.

**`Astro\Calc\CalculationEngine::computeChart($jd, $lat, $lon)`** is the single
source of truth. It returns sidereal longitudes, sign, degree-in-sign, house,
nakshatra + pada, retrograde flag for every planet, plus ascendant and houses.
**Every other engine in the project consumes that array; nothing recomputes
positions.** Ayanamsa is selectable (Lahiri default).

---

## 6. The Vedic side

**`Astro\Calc`** — `Charts` (sign/house helpers), `Varga` (the 15 divisionals),
`VimshottariDasha` (5 levels), `Ashtakavarga`, `Shadbala`, `BhavaBala`,
`VimshopakaBala`, `Drishti` (aspects), `PlanetCondition`, `Varshaphal`,
`Varshesha`.

**`Astro\Phala`** — 28 files, the prediction layer. The pattern is consistent:

```
XxxEngine.php        computes WHICH rules fire for this chart
XxxRepository.php    loads the editable Hindi text for those rules from MySQL
XxxData.php          the same text baked into PHP as a fallback
```

Every repository is written so a database failure returns `null` and the engine
falls back to its baked data — **the page still renders without MySQL**. That is
why the site works on a host with no database configured.

Engines include: `HousePrediction`, `KarakaPrediction`, `GrahaCondition`,
`DashaPhalEngine`, `PhaladeepikaYogaEngine`, `ShaapEngine`, `DoshaFinder`,
`YogaFinder`, `RajaYoga`, `Yogakaraka`, `AshtakavargaPhala`, `BhavaBalaPhala`,
`StrengthMeter`, and the five topic engines (`WealthEngine`, `CareerEngine`,
`SantanEngine`, `VideshEngine`, `PoliticsEngine`).

**`Astro\Gochar`** — 16 classes: current transits and their phal, `SadeSatiEngine`
+ `SadeSatiTimeline`, `YearTimeline` (12 months ahead), `UpcomingGochar`,
`GocharAv` (transit Ashtakavarga).

**Annual (Tajik) system** — `Varshaphal` (annual chart), `Varshesh` (year lord),
`Muntha`, `Saham` (50 sahams), `Tajik` (Tajik yogas and aspects), plus
`Varshaphal\TajikBhavaRepository` and `DashaPhalRepository` for the annual texts.

`CalcController::show()` wires all of it. Each engine call is wrapped in a
private `safe(callable, fallback)` helper so one failing engine degrades to a
missing panel rather than a blank page.

---

## 7. The Lal Kitab side

This is the largest and most heavily worked subsystem. It is a **complete
parallel astrology**, not a bolt-on.

### 7.1 The doctrine the code implements

- **Fixed-Aries teva.** House 1 is always Aries. Planets sit by the *bhava* they
  occupy in the birth chart. Signs therefore carry no information inside the
  teva — which is why the real D1 is drawn directly underneath it for reference.
- **Dignity comes from the house, not the rashi.** `UCH_BHAV`, `NEECH_BHAV`,
  `SWA_BHAV`, plus उच्च-भंग conditions.
- **पक्का घर is intensity, not merit** — it amplifies whichever way the result
  already leans. `कच्चा घर` weakens/delays.
- **One-way drishti** and **one-way टक्कर** (a house strikes the 8th from it),
  with three named collisions (विश्वासघात / साझी / अचानक).
- **A 35-year fixed dasha** — शनि 6 · राहु 6 · केतु 3 · गुरु 6 · सूर्य 2 ·
  चन्द्र 1 · शुक्र 3 · मंगल 6 · बुध 2 — repeating from birth. Never Vimshottari.
- **Four verdicts, not two:** शुभ · मध्यम · अशुभ · **निष्क्रिय** (asleep/inert).
  निष्क्रिय is not "bad" — it is *stopped*, and its remedy is to wake the planet,
  never to pacify it. This distinction runs through the entire subsystem.
- **क्षमता (capacity)** is a separate axis from शुभ/अशुभ: a planet can be
  benefic but weak.

### 7.2 The classes

| File | Role |
|---|---|
| `LalKitabData.php` | Loads `lalkitab_data.json`; `section('key')` returns one bank. Holds the constant tables (pakka/kachcha ghar, uch/neech houses, Hindi names). |
| `lalkitab_data.json` | **41 sections** of source text — planet-in-house phal (nek/mandi), house subjects, remedies, forbidden acts, debts, manglik rules, age tables, and more. |
| `LalKitabTeva.php` | Pure functions over the teva: `dignity()`, `aspectsFrom()`, `seenBy()`, `struckBy()`, `collisions()`, `impairedState()`, `foundation()` (बुनियाद), `classifyTeva()`. |
| `LalKitabEngine.php` | `compute($chart, $age, $active)` — builds every reading category: planets, houses, karak, yoga, inter-planet effects, drishti, collisions, masnui, debts/shraap, manglik, age-dasha timeline, sade-sati, remedies + remedy plan, longevity, health, vastu, varsh-gyan, reference tables. Also `varshReading()` for the annual Lal Kitab chart. |
| `LalKitabProcess.php` | The **judgement layer** on top of the engine: temperament, debt findings with confidence/protector, contradiction resolution (भार-क्रम), core points (निचोड़), and the **remedy queue**. |
| `LalKitabDasha.php` | The 35-year cycle. |
| `LalKitabMasnui.php` | मसनूई (artificial) planets. |
| `LalKitabSettings.php` | 8 contested-doctrine switches, URL-driven, defaults = the spec's suggestion, printed in the report footer. |
| `LalKitabMilan.php` | Lal Kitab compatibility between two charts (§8). |
| `LalKitabSelfCheck.php` | The 56-check harness (§14). |

### 7.3 The remedy queue — the most important design decision

`LalKitabProcess::remedySequence()` decides remedies in a fixed order, and the
order is the point:

1. **Direction first, remedy second — never the reverse.**
   `निष्क्रिय → जगाना`, `अशुभ → शांति`, `शुभ but weak → बल-वृद्धि`.
   Pacifying a sleeping planet puts it to sleep more deeply; years of honest
   effort then produce nothing and the person concludes the system does not work.
2. **Aim at the right planet.** If Jupiter is suppressed by Saturn, the remedy is
   Saturn's, not Jupiter's — the target is redirected and the reason printed.
3. **Do not disturb a protector.** A planet currently blocking a debt is never
   pacified; removing the shield produces trouble weeks after the remedy starts,
   and the person will connect the two — correctly.
4. **The forbidden-acts gate.** Each chart has acts the book forbids. A candidate
   remedy whose wording collides with one is dropped, with a note.
5. **The situation gate.** Many remedies invert depending on family status
   (parents living, siblings, children). Where the answer is unknown the remedy
   is *withheld*, never guessed. Six questions are asked in a strip on the page.
6. **One remedy, at most two.** A list of eight is abandoned in week two, and an
   abandoned remedy is worse than one never started.
7. Every issued remedy carries **direction · why · how long · when to stop**.

### 7.4 The presentation

`app/Http/views/_lalkitab.php` (~2,900 lines) renders `#sec-lalkitab`:
the fixed-Aries teva, the Vedic D1 beneath it for cross-reading, a Vimshottari
popup button, and a dropdown (`#lk-select`) of **22 views in 8 groups** —
निचोड़, ग्रह फल, भाव फल, संबंध-जाल, ऋण/श्राप/दोष, समय, उपाय, संदर्भ.

---

## 8. Kundali Milan (matchmaking)

`MilanController::show()` computes both charts and renders `views/milan.php`
with two independent readings.

**Vedic — `Astro\Milan\GunaMilan`**: the classical 36-guna Ashtakoot (Varna,
Vashya, Tara, Yoni, Graha Maitri, Gana, Bhakoot, Nadi) with parihara rules and
Mangal-dosha handling. Texts come from `MilanRepository` (migration 014) with a
baked classical fallback.

**Lal Kitab — `LalKitab\LalKitabMilan`**: a separate reading on **five points**,
each taken from the book itself:

1. **सप्तम भाव** — the spouse's house on both sides, with the book's own
   nek/mandi text for whoever sits there.
2. **विवाह-कारक** — शुक्र (wife), राहु (in-laws), केतु (children), which are the
   book's own karak assignments. *"गुरु = पति-कारक" is deliberately not used*: it
   is Vedic convention, and this book's karak table calls Jupiter father.
3. **मंगल व पाप ग्रह** — all five papa grahas in houses 1/4/7/8/12, ranked by the
   book's order (मंगल > शनि/सूर्य/राहु > केतु) and intensity by house (7 highest,
   12 lowest), plus the eight parihar conditions — **two of which can only be
   tested against the other chart**, which is why they had never been applied
   before.
4. **गृहस्थी के भाव** — 2 (family/in-laws), 4 (home), 12 (bedroom).
5. **पितृ-ऋण** — shared vs one-sided, and marriage-touching vs personal.

**There is no percentage.** Averaging axes produced a number that looked like a
measurement and was not; the page shows a **count of clear points out of five**
and states explicitly that it must not be read against the guna score.
The verdict comes from gates: `शुभ` · `शुभ — उपाय के बाद` · `सावधानी` · `कठिन`.

Remedies come from the same `LalKitabProcess` queue as everywhere else, so they
inherit direction, the forbidden gate and the one-or-two limit.

Below the reading sits the **marriage-date finder**
(`Astro\Muhurat\MarriageDateFinder`): pick a range, and every day in it is graded
by `VivahaMuhuratEngine`. The date boxes open pre-filled (today → +6 months) with
1/3/6-month and 1-year buttons.

---

## 9. Muhurat (electional astrology)

`app/Astro/Muhurat` — 15 engines over `MuhuratChintamaniData` and `ShatkarmaData`:

`GeneralMuhuratEngine`, `PersonalMuhuratEngine`, `KaryaMuhuratEngine`,
`VivahaMuhuratEngine` and `AgniVivahaMuhuratEngine` (marriage),
`SanskaraMuhuratEngine` (rites), `VastuMuhuratEngine` (building),
`YatraMuhuratEngine` and `YatraPratishthaEngine` (travel/installation),
`ShatkarmaStudyEngine`, `MuhuratDateScanner`, `MarriageDateFinder`, and
`Auspiciousness` (the score/band/bar widget reused across pages).

---

## 10. The user interface

**No build step.** No bundler, no framework, no npm. Tailwind utility classes
come from a CDN; everything else is hand-written CSS inside the view files, and
the JavaScript is plain ES5-flavoured code in `public_html/assets/js/`.

### The shell — `views/calc-v2.php`

One page holds everything:

- `#ov-strip` — overview tiles (name, DOB, place, lagna, moon, **Lal Kitab dasha**,
  current dasha, yoga). Tiles are shortcuts that click the matching menu button.
- `#side-menu` — the section menu. On laptop it collapses to an icon rail
  (remembered in `localStorage`); on tablet and phone it is a ☰ drawer.
- `#chart-panel` — chart picker (D1–D60 + Gochar + Varsha), rotation control,
  the drawn chart, and the dasha strip.
- `#pred-panel` — prediction picker, topic chips, and every prediction view.
- `#sec-*` full-width sections: `profile`, `custom`, `grah`, `varga`, `dasha`,
  `bal`, `gochar`, `muhurat`, `varsha`, `lalkitab`, `today`.
- **`#more-panel` — the "और देखें" strip** at the bottom of the birth-chart page:
  80 buttons in 11 groups covering every section, chart, prediction, Lal Kitab
  view and Kundali Milan.

### How the popup works

Clicking a button in the "और देखें" strip **borrows the real section node** into
the modal and leaves a comment marker in its place; closing puts it back with its
original classes. It is deliberately *not* a copy: a copy kills the section's
charts, dropdowns and buttons (their handlers are bound to the real node) and
puts the same `id` on the page twice. Sections are built by clicking their actual
side-menu button so every normal side effect still happens — lazy charts render,
tabs activate, the Lal Kitab view switches. Kundali Milan, being its own page,
opens in an iframe with the current native prefilled as the boy.

The button list is **generated from the dropdowns and the menu**, never written
out by hand, so it cannot drift from what it mirrors.

### JavaScript files

| File | Purpose |
|---|---|
| `northchart.js` | Draws the North-Indian chart as SVG (`ABChart.renderNorth`), including the AV/BB/Drishti outer ring. |
| `dasha.js` | Vimshottari tree + the dasha detail modal (`ABDashaModal`). |
| `gochar.js`, `varshaphal.js`, `lalvarsh.js` | Lazy-fetch their JSON endpoints and inject the returned HTML. |
| `chartzoom.js` | Chart zoom/pan. |
| `cities.js`, `citysearch.js` | Place lookup for the birth form. |
| `saved_charts.js` | Save/load charts for logged-in users. |
| `datefmt.js`, `translate.js`, `milan_charts.js`, `canvas.js` | Formatting, on-demand translation, milan chart drawing, the workflow canvas. |

---

## 11. Where the text comes from: data banks

Three kinds, and it matters which is which:

1. **`app/Astro/LalKitab/lalkitab_data.json`** — 41 sections of Lal Kitab source
   text. Read-only at runtime through `LalKitabData::section()`. Edit the JSON to
   change Lal Kitab wording.
2. **MySQL tables seeded by `migrations/*.sql`** — the Vedic prediction texts
   (dasha phala, house/karaka/graha predictions, yoga, shaap, gochar, varshesh,
   muntha, tajik, guna milan). These are meant to be **editable by the owner**
   without touching code.
3. **`*Data.php` classes** — the same text baked into PHP as a fallback so that a
   missing or unreachable database never blanks a page.

Computed sentences (the ones that read like an astrologer wrote them) are
assembled in the engines from these banks plus the chart facts — they are not
stored as finished paragraphs.

---

## 12. Database and migrations

MySQL/MariaDB, `utf8mb4`. Connection settings come from `.env`
(`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).

`migrations/` holds 26 numbered files: `001_master_schema.sql` creates the schema
(workflows, job queue, credentials, agent knowledge, and the prediction text
tables); `002`–`026` seed and correct the text banks — dasha phala, planet phala,
graha-bhava phal, house and karaka predictions, dasha engine, Ashtakavarga and
Bhava-Bala phal, guna milan, saham, tajik yoga and drishti, gochar phal, varshesh,
muntha, gochar sade-sati, gochar Ashtakavarga, gochar muhurat, tajik bhava phal,
dasha phal, Phaladeepika yoga, poorva shaap.

Apply them from the browser at `admin/migrate` (staff-gated) or through
`Core\MigrationRunner`, which records what has already run.

**The astrology pages do not require the database.** Every repository degrades to
its baked `*Data.php` fallback. The database is required for the workflow/canvas
half, saved charts, and owner-edited text.

---

## 13. The workflow/automation half of the codebase

The project began as "Auto Business", a visual workflow builder, and that half is
still present and functional:

- `app/Engine/` — `ExecutionEngine` topologically sorts a node graph, starts at
  the Trigger and walks the nodes. Execution is **checkpointed after every node**
  and bounded by a wall-clock deadline; on timeout it returns `paused` with full
  state, which the runner persists to `job_queue.state_json` and resumes on the
  next cron tick. This is what makes it survive shared hosting.
  Node types: `TriggerNode`, `HttpRequestNode`, `IfElseNode`, `TransformNode`.
- `app/Queue/` — `JobQueue`, `CronSchedule`, `WorkflowRunner` (driven by
  `runner.php` from cron).
- `app/Security/CredentialVault` — AES-256-GCM encryption for stored credentials,
  keyed by `CREDENTIAL_MASTER_KEY`.
- `app/Astro/Llm`, `Ingestion`, `Orchestration` — a book-ingestion pipeline
  (PDF → text → structured Markdown → heading chunks → `agent_knowledge` →
  compiled digest) and an orchestrator that prompts agents over that knowledge.
  `AnthropicClient` is the LLM client. These are dormant on the live astrology
  site but wired and callable.
- `views/canvas.php` + `assets/js/canvas.js` — the visual builder UI, staff-only.

---

## 14. Testing and the self-check harness

### CLI tests (`tests/`)

15 scripts, run with `php tests/<name>.php`: `calc_test`, `dasha_phal_test`,
`gochar_test`, `gochar_av_test`, `muhurat_test`, `muntha_test`,
`phala_yoga_test`, `sadesati_test`, `shaap_test`, `shadbala_pl_test`,
`tajik_test`, `tajik_bhava_test`, `varshesh_test`, `access_log_test`,
and `lalkitab_process_check`.

### The self-check harness — `LalKitabSelfCheck`

This is the main quality gate and it works differently from a unit test: it
**fetches real pages over HTTP and asserts against the rendered HTML**, so the
view layer is inside the test boundary. It runs from two places, sharing one
implementation:

```bash
php tests/lalkitab_process_check.php                    # local
LK_BASE=https://your-site php tests/lalkitab_process_check.php
```
```
https://your-site/lk-selfcheck.php?key=<LK_SELFCHECK_KEY>   # live, no terminal needed
```

It loads seven birth charts plus three couples plus a date-range search, and runs
**56 checks**. They are not smoke tests; each one guards a specific failure that
actually occurred:

- `X3` — a sleeping planet must never be given a pacifying remedy.
- `X8` — no health claim without its doctor note.
- `U3` / `U3-COV` — an inert dasha ruler reads "ठहराव", not "मंदा"; and the
  coverage guard fails if no chart exercises that branch any more.
- `RN-1` — a debt rule joined by "व" (AND) must not fire on a partial match.
- `TM-3` — the dasha list starts at birth and only the current period is expanded.
- `MM-1…MM-6` — matchmaking: an inert planet is never printed green; no
  percentage gauge; cross-chart parihar is actually evaluated; every remedy shows
  its direction and the list stops at five; the bias caution is present; **and the
  verdict varies from couple to couple** (the check that would have caught the
  original "no couple is ever auspicious" defect).
- `MDF-1` / `MDF-2` — the date finder opens pre-filled, and a one-year request
  scans the whole year instead of silently truncating.
- `MORE-1` — every one of the 80 "और देखें" buttons points at something that
  exists.
- `Y10` — two different charts must not produce near-identical summaries.

**Every check is negative-tested.** A check is only accepted after the fault it
guards is deliberately reintroduced and the check is observed to turn red. A
check that has never failed has never been shown to test anything.

---

## 15. Running it locally

There is nothing to install — no Composer, no npm.

```bash
# 1) minimal environment
cp .env.example .env
#    set APP_ENV=local  (this bypasses the staff gate on /calc and /milan)

# 2) a dev router is needed because the webroot is public_html/ but the
#    project root is one level up. Create _devrouter.php in the project root:
```

```php
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = __DIR__ . '/public_html' . $path;
if ($path !== '/' && is_file($file)) {                 // stream static assets
    if (substr($file, -4) === '.php') { chdir(__DIR__ . '/public_html'); require $file; return true; }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . (['js'=>'application/javascript','css'=>'text/css','json'=>'application/json'][$ext] ?? 'application/octet-stream'));
    readfile($file); return true;
}
$r = ltrim($path, '/');                                 // emulate .htaccess
if ($r !== '' && !isset($_GET['r'])) {
    $_GET['r'] = $r; $_REQUEST['r'] = $r;
    $q = $_SERVER['QUERY_STRING'] ?? '';
    $_SERVER['QUERY_STRING'] = 'r=' . rawurlencode($r) . ($q !== '' ? '&' . $q : '');
}
chdir(__DIR__ . '/public_html');
require __DIR__ . '/public_html/index.php';
return true;
```

```bash
# 3) run it (workers > 1 matters: the page loads many assets at once, and the
#    self-check calls the site from inside itself)
APP_ENV=local PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8899 _devrouter.php

# 4) open
http://127.0.0.1:8899/calc?date=01-12-1980&time=10:30&lat=28.6139&lon=77.2090&tz=5.5
http://127.0.0.1:8899/milan

# 5) verify
php tests/lalkitab_process_check.php        # expects 56/56
```

**Delete `_devrouter.php` before committing** — it is a local scaffold, not part
of the application.

---

## 16. Deploying

The live host is A2 shared hosting; deployment is a file upload, not a pipeline.

1. Point the domain's document root at **`public_html/`**. Everything else —
   `app/`, `storage/`, `migrations/`, `tests/`, `.env` — must stay one level
   above it and must never be web-reachable.
2. Create `.env` in the project root from `.env.example`. Keys that matter:
   `APP_ENV`, `APP_BASE_URL`, `DB_*`, `CREDENTIAL_MASTER_KEY`,
   `LK_SELFCHECK_KEY`, and optionally `SWETEST_PATH` / `SWEPH_PATH` for Swiss
   Ephemeris and `NODE_TYPE`.
3. Make `storage/` writable.
4. Run the migrations once at `admin/migrate` if the database half is wanted.
5. **Clear OPcache after every upload**, or the old PHP keeps serving.
6. Open `/lk-selfcheck.php?key=…` and confirm 56/56.

**Upload interdependent files together.** A partial deploy — a view calling an
engine method that has not landed yet — produces a PHP fatal, which arrives at an
AJAX call as HTML and surfaces as `Unexpected token '<' … not valid JSON`.

---

## 17. Conventions this codebase follows

These are not style preferences; they are the rules that keep an astrology
engine honest.

- **State the frequency truth.** Before adding a rule, measure how often it
  fires. A label that appears on 96% of charts carries no information and only
  frightens. Several rules were demoted to notes for exactly this reason: "the
  7th lord is away" is true of 99 charts in 100; a dormant house of about 60;
  an afflicted Rahu of 60.
- **Gates, not scores.** Averaging produces a middle for everybody. Each axis
  decides for itself and only the counts are combined.
- **No fake precision.** If a number can take six values, it is not a percentage.
- **Say what is stopped, not what is bad.** निष्क्रिय / सुप्त are their own
  verdict with their own remedy direction.
- **Name the doctrine you did not apply, and why.** Where a familiar Vedic rule
  is deliberately omitted from a Lal Kitab page, the page says so.
- **Every claim traces to the book or to a computed fact.** No invented rules.
- **A new rule ships with a check, and the check ships red first.**
- **Comments explain *why*, in the register of the surrounding file.** Much of
  the Lal Kitab code is commented in Hindi, matching its subject matter.
- `docs/LALKITAB_DECISIONS.md` records one section per change: what was measured,
  what was decided, what was deliberately not done.

---

## 18. Known gotchas

- **Devanagari and regex.** Vowel signs are `\p{M}`, not `\p{L}`. A pattern like
  `[^\p{L}]+` shatters Hindi words at every matra. Use `[^\p{L}\p{M}]+`. This bug
  silently disabled the forbidden-acts gate once.
- **`+1 month` in PHP and JS** turns 31 January into 3 March. Add the month
  first, then clamp the day to that month's length.
- **Tailwind is a CDN dependency.** In a sandbox with no outbound network the
  utility classes (including `.hidden`) do not apply, so layouts and "hidden"
  panels behave differently in headless tests than in production.
- **`php -S` with one worker deadlocks on self-calls.** The self-check fetches
  the site from inside the site; set `PHP_CLI_SERVER_WORKERS`.
- **Charts cannot be drawn into a hidden container** — it measures zero. Every
  chart in a hidden section is rendered lazily on first open.
- **`AdminGuard` gates `/calc`.** With `APP_ENV=production` and no staff session
  the page returns `403 {"error":"Staff authentication required"}`. This surprises
  people setting up a fresh copy.
- **Duplicate DOM ids after opening मुहूर्त.** Building the Muhurat page clones a
  strip and repeats a handful of ids. Pre-existing, in the Vedic Muhurat code,
  documented but not yet fixed.
- **`U3-COV` will eventually go red on its own.** It depends on one birth chart
  still having an inert dasha ruler; as that native ages the branch stops being
  exercised, and the check is designed to say so rather than pass vacuously. When
  it fails, pick a new birth date for `LalKitabSelfCheck::CHARTS`.

---

## 19. Glossary

| Term | Meaning |
|---|---|
| **Kundali / teva** | Birth chart. "Teva" is the Lal Kitab word for it. |
| **Lagna** | Ascendant — the sign rising at birth. |
| **Bhava / भाव** | House (1–12), a life area. |
| **Rashi / राशि** | Zodiac sign. |
| **Varga (D1…D60)** | Divisional charts; D9 (Navamsa) for marriage, D10 for career, and so on. |
| **Dasha** | Planetary period. Vimshottari (Vedic, 120-year cycle) vs the Lal Kitab 35-year cycle. |
| **Gochar** | Transit — where the planets are now, read against the birth chart. |
| **Varshaphal** | The annual (Tajik) chart, cast for each solar return. |
| **Muntha** | A point that advances one house per year in the annual chart. |
| **Saham** | Sensitive computed points (Arabic-parts-like) in the Tajik system. |
| **Shadbala / Bhava Bala / Ashtakavarga** | Numeric strength systems for planets and houses. |
| **Drishti** | Aspect. Lal Kitab's is one-way and forward. |
| **Karak / कारक** | Significator — the planet that "stands for" a subject. |
| **Manglik** | Mars in houses 1/4/7/8/12 — the marriage dosha. |
| **Parihar / परिहार** | A condition that cancels or reduces a dosha. |
| **Pitru rin / पितृ-ऋण** | Ancestral debt — a Lal Kitab concept. |
| **Shrap / श्राप** | A curse-pattern; remedied by pacifying, not repaying. |
| **Sade-Sati** | Saturn's ~7½-year passage over the Moon sign and its neighbours. |
| **निष्क्रिय / सुप्त** | Inert / dormant — the fourth verdict; stopped, not bad. |
| **पक्का घर** | A planet's permanent house in Lal Kitab; amplifies the result. |
| **मसनूई ग्रह** | "Artificial" planets in Lal Kitab. |
| **टक्कर** | Collision between houses (one-way, the 8th from). |
| **बुनियाद** | The "foundation" of a house — its base planet and the structure above it. |
| **निचोड़** | The distilled conclusion — the summary the client reads first. |
| **उपाय** | Remedy. |

---

*Written against commit `324bed1`. When the code changes, this file and
`docs/LALKITAB_DECISIONS.md` are the two places to update.*
