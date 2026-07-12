# Analysis of Karma — "Auto Business" Project: The Complete Guide

> **Who this is for:** anyone — even with zero coding experience — who wants to
> understand, run, edit, or extend this project. Everything is explained from
> the ground up: what the project is, how it works, what every file does, where
> the data lives, and how to make common changes safely.

---

## Table of Contents

1. [What is this project?](#1-what-is-this-project)
2. [The big picture — how it works](#2-the-big-picture--how-it-works)
3. [Technology used](#3-technology-used)
4. [How to install and run it](#4-how-to-install-and-run-it)
5. [The pages a user sees](#5-the-pages-a-user-sees)
6. [Folder map (bird's-eye view)](#6-folder-map-birds-eye-view)
7. [Every file explained](#7-every-file-explained)
   - [Project root](#71-project-root)
   - [public_html/ — the web entry](#72-public_html--the-web-entry)
   - [public_html/assets/js/ — browser JavaScript](#73-public_htmlassetsjs--browser-javascript)
   - [app/Core/ — plumbing](#74-appcore--plumbing)
   - [app/Http/ — controllers & pages](#75-apphttp--controllers--pages)
   - [app/Astro/Time & Ephemeris — astronomy](#76-appastrotime--ephemeris--astronomy)
   - [app/Astro/Calc/ — the calculation engine](#77-appastrocalc--the-calculation-engine)
   - [app/Astro/Phala/ — prediction engines](#78-appastrophala--prediction-engines)
   - [app/Astro/Saham/ — the 50 Sahams](#79-appastrosaham--the-50-sahams)
   - [app/Astro/Milan/ — Kundali Milan](#710-appastromilan--kundali-milan)
   - [app/Astro/Llm, Ingestion, Orchestration — AI modules](#711-appastrollm-ingestion-orchestration--ai-modules)
   - [app/Engine & app/Queue — workflow canvas](#712-appengine--appqueue--workflow-canvas)
   - [app/Security/](#713-appsecurity)
   - [migrations/ — the database](#714-migrations--the-database)
   - [docs/](#715-docs)
8. [The database tables in plain words](#8-the-database-tables-in-plain-words)
9. [How to make common changes (recipes)](#9-how-to-make-common-changes-recipes)
10. [Feature-by-feature: how each calculation works](#10-feature-by-feature-how-each-calculation-works)
11. [Glossary of astrology terms used](#11-glossary-of-astrology-terms-used)
12. [Safety rules & gotchas](#12-safety-rules--gotchas)

---

## 1. What is this project?

**Analysis of Karma** is a Vedic-astrology website. A visitor enters their
**birth details** (date, time, and place of birth) and the software:

- casts their **birth chart (Kundali)** and 15 divisional charts (D1–D60),
- computes planetary strengths (**Shadbala, Bhava Bala, Ashtakavarga, Vimshopaka**),
- runs the **Vimshottari Dasha** (planetary period) timeline 5 levels deep,
- shows **Gochar** (today's transits) against the birth chart,
- computes the **Varshaphal** (annual/solar-return chart) with Mudda dasha,
  Muntha, **Varshesh (year lord)** and the **50 Tajik Sahams**,
- generates **predictions in Hindi** (per planet, per house, per karaka, per
  dasha, yoga, Ashtakavarga opinion, Bhava-Bala opinion),
- performs **Kundali Milan** (marriage matching, 36-guna Ashtakoot) between a
  boy's and a girl's charts.

The output is designed to match **Parashara's Light** (the industry-standard
desktop software) so astrologers can trust the numbers.

There is also a hidden **staff-only workflow canvas** (a drag-and-drop
automation builder, like a mini-Zapier) and an **AI ingestion module** that can
read astrology books (PDF) and turn them into structured knowledge using
Claude (Anthropic) — these are for the project owner, not visitors.

---

## 2. The big picture — how it works

```
                Browser (what the visitor sees)
                        │
                        ▼
   public_html/index.php   ← the ONLY entry door ("front controller")
        routes ?r=calc, ?r=milan, ?r=canvas, JSON endpoints …
                        │
                        ▼
   app/Http/…Controller.php   ← one controller per page
        gathers input, calls the engines, picks a view
            │                          │
            ▼                          ▼
   app/Astro/…  (engines)        app/Http/views/…  (HTML pages)
   pure calculation classes      what gets rendered to the browser
            │
            ▼
   MySQL database (rules & phal text, all editable by owner)
   — if the DB is missing, engines fall back to baked-in defaults
```

Key design ideas (worth understanding before editing):

1. **One entry point.** Every web request goes through
   `public_html/index.php`. It looks at `?r=` (the route) and hands off to a
   controller. Nothing else in the project is directly reachable from the
   internet — that's a security feature.

2. **Engines are pure calculators.** Classes under `app/Astro/` take numbers
   in, give arrays out. They never print HTML. This means you can change how a
   page *looks* without touching the math, and vice-versa.

3. **Rules live in the database, with baked fallbacks.** Prediction texts,
   dignity tables, saham formulas etc. are stored in MySQL tables (migrations
   001–015) so the owner can edit them without touching code. Each repository
   class also carries a **baked-in copy** of the seed data, so the site still
   works if a table is empty or the DB is down.

4. **Graceful failure.** Each prediction generator is wrapped in a `safe()`
   call — if one engine crashes, the rest of the page still renders.

5. **Hindi first.** Prediction text is stored in Hindi (`language='hi'`
   columns); an English toggle exists in the top bar.

---

## 3. Technology used

| Layer | Technology | Notes |
|---|---|---|
| Server language | **PHP 8** (strict types) | no framework — plain PHP with a tiny autoloader |
| Database | **MySQL / MariaDB** via PDO | optional in dev; engines fall back to baked data |
| Front-end | Plain **JavaScript** + **Tailwind CSS** (CDN) | no build step, no npm needed |
| Charts | Hand-written canvas-free SVG/HTML renderer (`northchart.js`) | North-Indian style |
| Astronomy | Built-in **analytic ephemeris** (~1–2′ accuracy) or optional **Swiss Ephemeris** binary for arc-second accuracy | switch via `.env` `SWETEST_PATH` |
| AI (optional) | **Claude / Anthropic API** | only for the book-ingestion module |
| Hosting target | Shared hosting (A2-style, cPanel) | `.htaccess` rewrite included |

---

## 4. How to install and run it

### 4.1 On shared hosting (production)

1. Upload the project so that **`public_html/` is the webroot** and everything
   else (`app/`, `migrations/`, `bootstrap.php`, `.env`) sits **one level
   above** it (outside the webroot — this keeps secrets unreachable).
2. Create a MySQL database in your hosting panel.
3. Import the migrations **in numeric order**: `001_master_schema.sql` first,
   then 002, 003 … up to `015_saham_tajik.sql` (phpMyAdmin → Import).
4. Copy `.env.example` to `.env` (project root, NOT inside public_html) and
   fill in:
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` — your database;
   - `APP_ENV=production`;
   - `CREDENTIAL_MASTER_KEY` — run
     `php -r "echo base64_encode(random_bytes(32));"` once and paste the output;
   - optionally `LLM_API_KEY` (only needed for book ingestion);
   - optionally `SWETEST_PATH` (path to a `swetest` Swiss-Ephemeris binary for
     maximum accuracy — without it the built-in ephemeris is used, accurate to
     ~1–2 arc-minutes, fine for most charts).
5. Visit `https://your-site/calc` → the site opens on the **New / Profile**
   form.

### 4.2 On your own computer (development)

```bash
# from the project root:
APP_ENV=local php -S 127.0.0.1:8080 -t public_html
# then open http://127.0.0.1:8080/index.php?r=calc
```

- `APP_ENV=local` auto-logs you in as staff #1 (so the canvas works) — this
  shortcut is **impossible in production**.
- Without MySQL, prediction engines that need DB rules (planet/house/karaka
  phal) show empty sections, but chart math, Guna Milan and Sahams work fully
  (they have complete baked fallbacks).
- Note: PHP's dev server doesn't apply `.htaccess`, so clean URLs like
  `/calc/varshaphal` won't resolve — use `?r=calc/varshaphal` instead, or a
  small router script.

---

## 5. The pages a user sees

| URL (clean / raw) | What it is |
|---|---|
| `/calc` · `?r=calc` | **The main page.** New/Profile form (default landing), Birth Chart + predictions, Planet Positions, Varga charts, Dasha, Bala, Gochar, Varshaphal, Custom Screen |
| `/milan` · `?r=milan` | **Kundali Milan** — boy + girl birth details → 36-guna Ashtakoot match with D1/D9 charts and planet tables |
| `/canvas` · `?r=canvas` | **Staff-only** workflow canvas (drag-drop automation builder) |
| `?r=calc/gochar` | JSON API: transits for a given date/place (used by the Gochar panel) |
| `?r=calc/varshaphal` | JSON API: annual chart + Mudda dasha + Varshesh for a year |
| `?r=calc/dasha-phal` | JSON API: dasha prediction card HTML |

The main `/calc` page is a **single page with sections** switched by the left
menu (Birth Chart, Planet Positions, Varga Charts, Dasha, Bala, Gochar,
Varshaphal, Custom Screen, Kundali Milan link). On phones/tablets the menu
becomes a **☰ Menu drawer**.

---

## 6. Folder map (bird's-eye view)

```
project root
├── .env.example          ← template for your secret settings (copy to .env)
├── .gitignore            ← which files git must never store (.env, logs…)
├── Auto_Business_Astro_Engine_Spec.md   ← the original master specification
├── bootstrap.php         ← starts every request: autoloader + .env + session
├── runner.php            ← cron entry for background jobs (workflow engine)
├── calc_test.php         ← command-line test script for the chart engine
├── app/                  ← ALL server code (not reachable from the web)
│   ├── Core/             ← plumbing: DB, env, session, CSRF, assets
│   ├── Http/             ← controllers + the HTML views
│   ├── Astro/            ← the astrology brain
│   │   ├── Time/         ← Julian-day date maths
│   │   ├── Ephemeris/    ← planet-position providers (built-in / Swiss)
│   │   ├── Calc/         ← charts, strengths, dashas, varshaphal, varshesh
│   │   ├── Phala/        ← Hindi prediction generators + their DB loaders
│   │   ├── Saham/        ← the 50 Tajik sahams
│   │   ├── Milan/        ← marriage matching (Ashtakoot)
│   │   ├── Llm/          ← Claude API client
│   │   ├── Ingestion/    ← PDF book → structured knowledge pipeline
│   │   └── Orchestration/← multi-agent "book panel" reading a chart
│   ├── Engine/           ← workflow canvas execution engine (nodes)
│   ├── Queue/            ← job queue + cron schedule for workflows
│   └── Security/         ← encrypted credential vault
├── docs/                 ← reference documents (saham rules, TODOs)
├── migrations/           ← 15 SQL files that build/seed the database
└── public_html/          ← the ONLY web-visible folder
    ├── index.php         ← front controller (the router)
    ├── .htaccess         ← clean-URL rewrite (/calc → ?r=calc)
    └── assets/js/        ← browser-side JavaScript (charts, search, dasha…)
```

---

## 7. Every file explained

### 7.1 Project root

| File | What it does | When you'd touch it |
|---|---|---|
| **`.env.example`** | A commented template of every setting the app reads: database login, app URL, credential master key, LLM API key, Swiss-Ephemeris path, runner budgets. Copy it to `.env` and fill in real values. | First install; adding a new setting. |
| **`.gitignore`** | Tells git to never store `.env` (secrets), `vendor/`, logs, OS junk, or the generated update zip. | Rarely. |
| **`Auto_Business_Astro_Engine_Spec.md`** | The original full specification of the whole "Auto Business" platform (modules, global rules, hosting constraints). Useful background reading. | Read-only reference. |
| **`bootstrap.php`** | Runs at the start of every request: registers the PSR-4 autoloader (so `AutoBusiness\Astro\...` class names map to `app/Astro/...` files), loads `.env`, starts the session. | Almost never. |
| **`runner.php`** | The cron entry-point. Your hosting panel calls it every minute; it ticks the workflow job queue (Module 2). Not used by the astrology pages. | If you add background jobs. |
| **`calc_test.php`** | A command-line script that computes a known test chart and prints positions — handy to verify the engine after edits: `php calc_test.php`. | After changing engine math. |

### 7.2 public_html/ — the web entry

| File | What it does |
|---|---|
| **`index.php`** | The **front controller**. Reads `?r=` and dispatches: `calc` → CalcController→show(), `milan` → MilanController, `canvas` → canvas view, plus the JSON endpoints (`calc/gochar`, `calc/varshaphal`, `calc/dasha-phal`) and canvas save/load APIs. If you add a new page, you add a route here. |
| **`.htaccess`** | Apache rewrite rules so `/calc` becomes `index.php?r=calc` (clean URLs) and blocks direct access to anything else. |

### 7.3 public_html/assets/js/ — browser JavaScript

These run **in the visitor's browser** (not on the server).

| File | What it does |
|---|---|
| **`northchart.js`** | Draws North-Indian style kundali charts (`ABChart.renderNorth`). Takes `{asc_sign, planets:[{abbr,sign,deg,retro}]}` and renders the diamond chart with colored planet abbreviations, degrees, retro marks, and an optional outer ring (AV/BB numbers + drishti). Used by every chart on the site. |
| **`dasha.js`** | Renders the expandable Vimshottari dasha tree (`ABDasha.render`) — 5 levels (Maha → Antar → Pratyantar → Sookshma → Prana), with running-period highlight and date maths (`jdToDMY`, `runningChain`). |
| **`citysearch.js`** | The city autocomplete (`ABCitySearch.init`). As the user types a place, it searches the bundled world-city list and fills the lat/lon/timezone fields (which are hidden under "Advanced options"). Timezone is resolved for the birth date (handles DST). |
| **`cities.js`** | The data for citysearch: a compact list of world cities with coordinates and timezone rules. |
| **`gochar.js`** | The Gochar (transit) panel (`ABGochar.init`): renders Date/Time/Place inputs (+ hidden Advanced lat/lon/tz), auto-detects the visitor's location by IP, fetches `?r=calc/gochar` and draws the transit chart. |
| **`varshaphal.js`** | The Varshaphal panel (`ABVarsha.init`): "which year?" box → fetches `?r=calc/varshaphal` → shows the summary (Year, Varsha Lagna, Muntha, **Varshesh (Year Lord)**, Age, Varshaphal date), draws the Varsha chart and the Mudda-dasha tree. |
| **`canvas.js`** | The staff workflow canvas UI (drag-drop nodes, connections, save/load via the canvas API). Visitors never load this. |

### 7.4 app/Core/ — plumbing

| File | What it does |
|---|---|
| **`Env.php`** | Reads `.env` values: `Env::get('DB_HOST')`. |
| **`Database.php`** | Opens the single shared MySQL PDO connection (`Database::pdo()`). If the DB is unreachable it throws — repositories catch this and fall back to baked data. |
| **`AdminGuard.php`** | Protects staff-only pages (the canvas). Checks the session for a staff login; in `APP_ENV=local` it auto-logs-in staff #1 for development. |
| **`Csrf.php`** | Generates/validates the anti-forgery token used by POST APIs. |
| **`Asset.php`** | Builds correct URLs for links/assets regardless of how the site is mounted (`Asset::url('/milan')`). |
| **`Uuid.php`** | Makes unique IDs (for workflow runs etc.). |

### 7.5 app/Http/ — controllers & pages

| File | What it does |
|---|---|
| **`CalcController.php`** | The heart of the site. `show()` reads the birth inputs, runs the CalculationEngine, all strength systems, dashas, yogas, every prediction engine (each wrapped in `safe()` so one failure can't kill the page), gochar, varshaphal + sahams — then renders `calc-v2.php`. Also serves the three JSON endpoints (`gocharJson`, `varshaphalJson`, `dashaPhalJson`). |
| **`MilanController.php`** | Kundali Milan: computes both charts (boy + girl), runs `GunaMilan`, renders `milan.php`. |
| **`CanvasController.php`** | Staff canvas save/load JSON APIs (guarded by AdminGuard + CSRF). |
| **`WebhookController.php`** | Receives external webhooks that trigger workflows (Module 2). |
| **views/`calc-v2.php`** | **The main page template** (~2500 lines: HTML + CSS + JS). Contains: the top bar (☰ Menu button, brand, "under testing" banner, language switch, New Kundli), the overview tiles, the left side menu (drawer on mobile), the chart panel (chart selector D1–D60/Gochar/Varsha), the prediction panel (Dasha/Bhavesh/Graha/Bhava/Karaka/Yoga pickers), Planet Positions (incl. House Details with **Short/Long Details** copy buttons), Varga grid, Dasha section, Bala tabs, Gochar section, Varshaphal section (chart, **Saham panel** with Active/All/1-50 selector, Mudda dasha, annual positions incl. Varshesh), and the Custom Screen builder. Default landing = New/Profile form. |
| **views/`_birth_form.php`** | The "Chart Calculation Details" entry form (name, gender, date, time, place search + **Advanced options**: ayanamsa, lat, lon, tz). Included by calc-v2 (in New/Profile) or standalone when no chart yet. |
| **views/`_dasha_cards.php`** | Partial that renders the dasha-phal prediction cards (used by page + JSON endpoint). |
| **views/`milan.php`** | The Kundali Milan page: its own copy of the site chrome (top bar, tiles, left menu), boy/girl forms (with shared Advanced toggle), and the results: 36-guna summary band, 8 koota cards with Hindi phal, Mangal-dosha comparison, parihara notes, D1 + D9 charts for both, planet tables. |
| **views/`calc.php`** | The OLD calculation page (layout v1) — kept for reference; `layout=old` shows it. |
| **views/`canvas.php`** | The staff workflow canvas page (Drawflow-based UI). |

### 7.6 app/Astro/Time & Ephemeris — astronomy

| File | What it does |
|---|---|
| **Time/`JulianDay.php`** | Converts calendar dates ↔ Julian Day numbers (the astronomer's timestamp) with timezone handling; also formats JD → `DD-MM-YYYY`. Every date in the engines is a JD. |
| **Ephemeris/`EphemerisProviderInterface.php`** | The contract: "give me planet longitudes for a JD". |
| **Ephemeris/`AnalyticEphemeris.php`** | Built-in planet-position calculator (VSOP-style series, ~1–2 arc-minute accuracy). No external files needed — this is what runs by default. |
| **Ephemeris/`SwissEphemeris.php`** | Optional wrapper that calls the `swetest` binary (Swiss Ephemeris) for arc-second accuracy. Enabled by `.env` `SWETEST_PATH`. |
| **Ephemeris/`EphemerisFactory.php`** | Picks Swiss if configured, else Analytic. |
| **`Ayanamsa.php`** (in app/Astro/) | The sidereal offset: Lahiri (default), Raman, KP, Fagan-Bradley. Tropical longitude − ayanamsa = sidereal longitude. |

### 7.7 app/Astro/Calc/ — the calculation engine

| File | What it does |
|---|---|
| **`CalculationEngine.php`** | The conductor. `computeChart(jd, lat, lon)` returns the full chart array: ascendant, all 9 planets (longitude, sign, degree, nakshatra, navamsa, house, retro), whole-sign houses, **is_day** flag, Shadbala, and helpers like `keySigns()` and `northPayload()` (chart data formatted for northchart.js). |
| **`Charts.php`** | Zodiac reference: sign names, **sign lords**, nakshatra list, `signIndex()`, `degInSign()`, `navamsaSignIndex()`, degree formatting. |
| **`Varga.php`** | Divisional-chart mapping: D2 Hora, D3 Drekkana, D4, D7, D9 Navamsa, D10, D12, D16, D20, D24, D27, D30, D40, D60 — each planet's sign in each varga. |
| **`Drishti.php`** | Planetary aspects (drishti): every planet aspects the 7th; Mars also 4/8, Jupiter also 5/9, Saturn also 3/10; Rahu/Ketu 5/9. Produces the per-house aspect lists shown in House Details and the chart ring. |
| **`Shadbala.php`** | The six-fold planetary strength (positional, directional, temporal, motional, natural, aspectual) in virupas, with required-minimum ratios (the `ratio ≥ 1.0` used everywhere as "strong"). Also `isDayBirth()`. |
| **`BhavaBala.php`** | House strength in virupas (Bhavadhipati + Bhava-digbala + Bhava-drishti-bala), shown in House Details and used by BhavaBalaPhala. |
| **`Ashtakavarga.php`** | Bhinnashtakavarga (per-planet benefic dots) + Sarvashtakavarga (the per-house totals shown as "AV"). |
| **`VimshopakaBala.php`** | 20-point composite dignity across vargas (Shad/Sapta/Dasha/Shodasha-varga sets). |
| **`VimshottariDasha.php`** | The 120-year Vimshottari dasha tree from the Moon's nakshatra, drilled 5 levels (Maha→Antar→Pratyantar→Sookshma→Prana) with exact JD boundaries. |
| **`PlanetCondition.php`** | The shared "how is this planet doing?" service used by ALL prediction engines: degree-aware **dignity** (exalt / moolatrikona / own / friend / neutral / enemy / debilitated, with neecha-bhanga cancellation), **combustion** (% closeness to Sun with per-planet orbs), retrograde, benefic/malefic status, natural friendships (single and directed), temporary friendships. Owner can override dignity bands and orbs from DB tables. |
| **`Varshaphal.php`** | The annual (Tajik) chart: finds the exact solar-return moment (Sun back to its natal degree) in the chosen year, casts the Varsha chart, computes Muntha, Varsha Lagna, **Varshesh** (via Varshesha.php) and the **Mudda dasha** (the year split in Vimshottari proportions from the annual Moon's nakshatra). |
| **`Varshesha.php`** | Selects the **Varshesh (year lord)**: builds the five Panchadhikari office-bearers (Muntha lord, Varsha-Lagnesh, Janma-Lagnesh, Trirashi lord, Dina-ratri lord) and picks the strongest by **Panchavargeeya Bala** (Kshetra 30 + Uchcha 20 + Hadda 15 + Drekkana 10 + Navamsa 5) — the Parashara's Light convention. |

### 7.8 app/Astro/Phala/ — prediction engines

Pattern: each engine has a **generator** (the logic) and a **repository** (loads
editable rules/text from MySQL, with `lastError()` and baked fallbacks).

| File | What it does |
|---|---|
| **`GrahaCondition.php`** + **`GrahaConditionRepository.php`** | Per-planet Hindi condition report: dignity line, combustion, retro, house placement, companions (with friendship), aspects received — assembled from editable sentence templates (migration 008). Shown in the Planet prediction picker. |
| **`HousePrediction.php`** + **`HousePredictionRepository.php`** | Per-house (Bhava Phaladesh) composite prediction: occupants, lord placement, karaka condition, drishti — plus the **अष्टकवर्ग मत** (Ashtakavarga opinion) and **भाव बल मत** (Bhava-Bala opinion) sections, folding into a per-house verdict (migrations 009, 012, 013). |
| **`AshtakavargaPhala.php`** | The अष्टकवर्ग मत block: SAV band for the house, comparison with 12th-from, occupant/lord/karaka BAV points → opinion + score (12th house reversed). |
| **`BhavaBalaPhala.php`** | The भाव बल मत block: Bhava-Bala virupa band, rank among 12 houses, lord's Shadbala match, running-dasha timing note. |
| **`KarakaPrediction.php`** + **`KarakaPredictionRepository.php`** | Karaka (significator) predictions: for each life-topic karaka planet (Sun=father, Moon=mother, …) its condition + the houses it rules → Hindi lines (migration 010). Includes a copy-to-clipboard text. |
| **`DashaPhalEngine.php`** + **`DashaEngineRepository.php`** + **`DashaPhalaRepository.php`** | Dasha predictions: grades the Maha/Antar lords (dignity, combustion, house, own-house count, relation between the two lords) and assembles the Hindi dasha-phal cards (migrations 002/003/011). |
| **`BhavPhalaRepository.php`** | Loads the "Bhavesh" (house-lord-in-house) sentence matrix (migrations 004/005) for the Bhavesh prediction view. |
| **`YogaFinder.php`** | Detects classical yogas (Gajakesari, Budhaditya, Raj-yoga combinations etc.) shown in the Yoga view and the overview tile. |

### 7.9 app/Astro/Saham/ — the 50 Sahams

| File | What it does |
|---|---|
| **`SahamEngine.php`** | Computes all **50 Tajik Neelkanthi sahams** from the Varsha chart. Data-driven: each saham is A − B + C (different columns for day/night birth), with **sekata** (+30° correction when C is outside the B→A arc), token resolver (planets, lagna, house cusps, PUNYA/GURU/VIDYA dependencies, fixed points), and the three special handlers (samarthya when Mars is lagna-lord, manmatha when Moon is lagna-lord, daridra). Grades each saham's **sahamesh** (sign lord) by Shadbala + dignity/combustion → अनुकूल/प्रतिकूल/मिश्रित verdict, picks the Hindi phal, adds the sahamesh's Mudda-dasha timing window, and flags sahams **related to the running Mudda lord** (as lord or by placement). |
| **`SahamRepository.php`** | Loads saham definitions + phal + config from MySQL (migration 015) — with the complete 50-row **baked fallback** so the panel works before any DB import. Also carries the easy-Hindi **"यह सहम क्या है?"** explanations (editable via the optional `explain_hi` column). |

UI: the Varshaphal Prediction panel — dropdown with **● Active Saham**
(default; all sahams tied to the running Mudda dasha), **All Saham**, then
**1–50** individually; active-saham chips jump to cards.

### 7.10 app/Astro/Milan/ — Kundali Milan

| File | What it does |
|---|---|
| **`GunaMilan.php`** | The Ashtakoot 36-guna match: Varna 1, Vashya 2, Tara 3, Yoni 4, Graha-Maitri 5, Gana 6, Bhakoot 7, Nadi 8 — each with its classical matrix — plus **Mangal dosha** check for both charts, **parihara** (cancellation) rules (e.g. Nadi dosha waived on same rashi different nakshatra), and the total-score band (उत्तम / बहुत अच्छा / मध्यम / निम्न). |
| **`MilanRepository.php`** | Loads the editable koota matrices and Hindi phal/parihara/summary sentences (migration 014), with full baked fallbacks. |

### 7.11 app/Astro/Llm, Ingestion, Orchestration — AI modules

These power the owner's "books read your chart" feature — optional, needs
`LLM_API_KEY`.

| File | What it does |
|---|---|
| Llm/**`LlmClientInterface.php`** | Contract for a text-generation client. |
| Llm/**`AnthropicClient.php`** | Calls the Claude Messages API (model names, retries, token budgets from `.env`). |
| Ingestion/**`PdfTextExtractor.php`** | Pulls raw text out of an uploaded astrology-book PDF. |
| Ingestion/**`MarkdownStructurer.php`** | Uses the LLM to turn raw text into clean structured Markdown chapters. |
| Ingestion/**`MarkdownChunker.php`** | Splits the Markdown into retrievable chunks. |
| Ingestion/**`DigestCompiler.php`** | Compiles a book's chunks into a compact rule digest. |
| Ingestion/**`BookIngestionService.php`** | The pipeline conductor: PDF → text → structure → chunks → digest → DB. |
| Orchestration/**`KnowledgeRepository.php`** | Stores/fetches the ingested book knowledge. |
| Orchestration/**`AgentPromptFactory.php`** | Builds the per-book agent prompts (each "book agent" answers from its own book only). |
| Orchestration/**`AstrologyOrchestrator.php`** | Fans a chart out to several book agents (wave size from `.env`), then a stronger model synthesizes their answers into one conclusion. |

### 7.12 app/Engine & app/Queue — workflow canvas

The staff-only automation builder (like a private Zapier).

| File | What it does |
|---|---|
| Engine/**`NodeInterface.php`** / **`AbstractNode.php`** | What a canvas node must implement. |
| Engine/Nodes/**`TriggerNode.php`** | Workflow start (webhook/cron/manual). |
| Engine/Nodes/**`HttpRequestNode.php`** | Calls an external URL. |
| Engine/Nodes/**`IfElseNode.php`** | Branching. |
| Engine/Nodes/**`TransformNode.php`** | Reshapes data between nodes. |
| Engine/**`NodeFactory.php`** | Builds node objects from the saved canvas JSON. |
| Engine/**`TokenResolver.php`** | Resolves `{{node.field}}` placeholders in node settings. |
| Engine/**`ExecutionEngine.php`** | Walks the graph and runs the nodes with checkpointing. |
| Queue/**`JobQueue.php`** | DB-backed job queue for workflow runs. |
| Queue/**`CronSchedule.php`** | Parses cron expressions for scheduled triggers. |
| Queue/**`WorkflowRunner.php`** | Called by `runner.php` each minute; runs due jobs within the tick budget. |

### 7.13 app/Security/

| File | What it does |
|---|---|
| **`CredentialVault.php`** | Encrypts/decrypts third-party credentials (API keys the owner adds for workflows) with AES-256-GCM using `CREDENTIAL_MASTER_KEY` from `.env`. Nothing is stored in plain text. |

### 7.14 migrations/ — the database

Run **in order** on a fresh database. Each is safe to re-run (idempotent
INSERT … ON DUPLICATE KEY).

| File | Creates / seeds |
|---|---|
| **001_master_schema.sql** | The platform base: staff, workflows, canvas storage, job queue, credentials, book/knowledge tables. |
| **002_dasha_phala.sql** | Dasha prediction rule tables. |
| **003_dasha_phala_seed_full.sql** | Full Hindi seed text for dasha phal (all Maha×Antar combinations). |
| **004_planet_phala.sql** | Bhavesh (lord-in-house) sentence tables. |
| **005_graha_bhava_phal_seed.sql** | Hindi seed for planet-in-house / lord-in-house lines. |
| **006_house_prediction.sql** | House (Bhava Phaladesh) rule tables. |
| **007_karaka_prediction.sql** | Karaka prediction tables. |
| **008_graha_prediction_fix.sql** | Graha-condition upgrade: dignity bands per degree, combustion orbs, sentence templates (`planet_dignity`, `combustion_orbs`, `graha_condition_*`). |
| **009_house_prediction_fix.sql** | House-prediction v2: composite line rules, verdict weights (`house_engine_config` and friends). |
| **010_karaka_prediction_fix.sql** | Karaka engine v2 rules. |
| **011_dasha_engine.sql** | Dasha engine v2: lord-grading rules + editable sentence bank. |
| **012_ashtakavarga_bhava_phal.sql** | अष्टकवर्ग मत tables: SAV bands, comparisons, BAV opinions per house. |
| **013_bhava_bala_phal.sql** | भाव बल मत tables: virupa bands, rank text, lord-match, timing lines. |
| **014_guna_milan.sql** | All 8 koota matrices + phal + parihara + summary bands (editable). |
| **015_saham_tajik.sql** | `saham_definitions` (50 formulas: A/B/C day & night, sekata, nature), `saham_phal` (signifies + अनुकूल/प्रतिकूल text + easy-Hindi `explain_hi`), saham config keys. |

### 7.15 docs/

| File | What it does |
|---|---|
| **`Saham_Calculation_Rules_TajikNeelkanthi.md`** | The full Tajik Neelkanthi saham rulebook the engine was built against (formulas, sekata semantics, special cases, §5 verification invariants). |
| **`TODO_MODULE_3C.md`** | Outstanding ideas/refinements for the year-prediction module. |

---

## 8. The database tables in plain words

You don't need to know SQL to edit content. Open **phpMyAdmin**, find the
table, edit the Hindi text column, save. The site reads it on the next page
load. The important editable tables:

| Table | What you edit there |
|---|---|
| `dasha_*` (002/003/011) | The Hindi sentences shown in Dasha Phal cards. |
| `graha_condition_*`, `planet_dignity`, `combustion_orbs` (008) | Planet condition sentences; dignity degree-bands; combustion orbs. |
| `house_*` (006/009), `av_*` (012), `bb_*` (013) | House prediction lines, अष्टकवर्ग मत and भाव बल मत text/bands. |
| `karaka_*` (007/010) | Karaka lines. |
| `milan_*` / `guna_*` (014) | Koota phal text, parihara rules, summary bands. |
| `saham_definitions`, `saham_phal` (015) | Saham formulas (careful!), phal text, and the easy-Hindi explanations (`explain_hi`). |
| `house_engine_config` | Key–value switches used by several engines (e.g. `saham_house_point`, verdict weights). |

**Golden rule:** text columns are safe to edit freely; formula/matrix columns
(tokens like `MOON`, `H9`, sekata flags, koota matrices) change the math — edit
those only if you know the rule you're implementing.

---

## 9. How to make common changes (recipes)

**Change a prediction sentence** → phpMyAdmin → find the table from §8 →
edit the Hindi text → save. No code, no restart.

**Change page look (colors, sizes, spacing)** → `app/Http/views/calc-v2.php`
(or `milan.php`) → the `<style>` block near the top → edit CSS. The design
tokens are CSS variables (`--sindoor`, `--ink`, `--card`, `--line`).

**Add a menu item / section on /calc** → in `calc-v2.php`: (1) add a button in
the `<nav id="side-menu">`, (2) add a `<div id="sec-yourname" class="l2-section
l2-full hidden">…</div>`, (3) add `'sec-yourname'` to the `FULL_SECTIONS` array
in the JS.

**Add a new page (own URL)** → new controller in `app/Http/`, new view in
`app/Http/views/`, add a `case 'GET yourroute':` in `public_html/index.php`.

**Change the default landing section** → in `calc-v2.php`, search for
"Default landing" — it clicks the New/Profile menu button when the URL has no
birth details.

**Adjust a saham formula** → `saham_definitions` table (A/B/C tokens, sekata)
or, before DB import, the `DEFAULT_DEFS` array in
`app/Astro/Saham/SahamRepository.php`. Keep both in sync.

**Switch to Swiss Ephemeris accuracy** → upload a `swetest` binary via your
hosting panel, set `SWETEST_PATH=/path/to/swetest` in `.env`.

**Verify nothing broke after an edit** → `php -l path/to/file.php` (syntax
check), then `php calc_test.php` (engine sanity), then load the page.

---

## 10. Feature-by-feature: how each calculation works

- **Birth chart:** birth date/time/tz → Julian Day → ephemeris gives tropical
  longitudes → minus ayanamsa = sidereal → sign = longitude ÷ 30 → houses are
  whole-sign from the ascendant (computed from local sidereal time).
- **Shadbala:** six classical strengths summed in virupas; a planet is
  "strong" when total ≥ its required minimum (ratio ≥ 1.0).
- **Ashtakavarga:** for each planet, benefic points contributed to each sign
  by the 7 planets + lagna (classical tables) → per-house totals (SAV).
- **Vimshottari dasha:** Moon's nakshatra fixes the starting lord and elapsed
  fraction; the 120-year cycle is then laid out; each level subdivides in the
  same proportions.
- **Gochar:** the same chart engine run for "now" (or any date) at the
  viewer's location, drawn beside the natal chart.
- **Varshaphal:** Newton-iterate the Sun back to its natal longitude in the
  target year → cast that instant's chart at the birth place; Muntha = natal
  lagna advanced one sign per completed year; Mudda dasha = the year split in
  Vimshottari proportions from the annual Moon; **Varshesh** = strongest of
  the five office-bearers by Panchavargeeya Bala.
- **Sahams:** each saham = A − B + C longitudes (day/night formulas), +30° if
  the sekata condition fails; its lord's strength decides अनुकूल/प्रतिकूल.
- **Guna Milan:** both Moon positions → nakshatra/rashi → the 8 koota
  matrices → points out of 36 → band + parihara checks + Mangal dosha.
- **Predictions:** every engine reads the chart facts (dignity, combustion,
  house, aspects…) through the shared `PlanetCondition`, then picks the
  matching editable Hindi sentences from the DB and assembles a card.

---

## 11. Glossary of astrology terms used

| Term | Meaning |
|---|---|
| Kundali | Birth chart |
| Lagna | Ascendant — the sign rising at birth; house 1 |
| Rashi | Zodiac sign (Mesh/Aries … Meen/Pisces) |
| Nakshatra | One of 27 lunar mansions (13°20′ each) |
| Graha | Planet (incl. Rahu/Ketu, the lunar nodes) |
| Bhava | House (1–12), each ruling life areas |
| Drishti | Planetary aspect (a planet "looks at" houses) |
| Dasha | Planetary time-period system (Vimshottari = 120 yrs) |
| Gochar | Transits — where planets are today |
| Varga | Divisional chart (D9 = Navamsa etc.) |
| Shadbala | Six-fold planet strength |
| Bhava Bala | House strength |
| Ashtakavarga (AV/SAV/BAV) | Benefic-point strength system |
| Varshaphal | Annual chart cast at the solar return (Tajik system) |
| Muntha | Progressed lagna point in the annual chart |
| Mudda dasha | The annual (compressed) dasha for the Varsha year |
| Varshesh | Lord of the year — strongest of 5 office-bearers |
| Saham | Sensitive Arabic-part-style point in the Varsha chart (50 of them) |
| Sahamesh | The lord of the sign a saham falls in |
| Guna Milan / Ashtakoot | 36-point marriage compatibility match |
| Mangal dosha | Mars affliction relevant to marriage |
| Ayanamsa | Offset between tropical and sidereal zodiacs |
| Anukul / Pratikul / Mishrit | Favourable / unfavourable / mixed |
| Uchcha / Neecha | Exalted / debilitated |
| Asta (अस्त) | Combust — too close to the Sun |

---

## 12. Safety rules & gotchas

1. **Never put real secrets in git or a shared zip.** The real `.env` stays on
   the server only. `.env.example` is the safe template.
2. **`public_html` is the only web-visible folder.** Don't move `app/` or
   `.env` inside it.
3. **Migrations run in order** (001 → 015) on a fresh DB. They're idempotent —
   re-importing 015 just refreshes its seed rows.
4. **Baked fallbacks mask DB problems.** If your DB edit "doesn't show",
   check the table actually has rows for `language='hi'` — an empty table
   silently falls back to the baked defaults in the repository class.
5. **PHP dev server ≠ Apache.** Clean URLs (`/calc/...`) need `.htaccess`;
   locally use `?r=...` routes.
6. **After editing a PHP file**, run `php -l thefile.php` — a syntax error in
   `calc-v2.php` would blank the whole main page.
7. **The "System is Under Testing" banner** is in the topbar of both
   `calc-v2.php` and `milan.php` (`.test-banner`) — remove it from both when
   you go live.
8. **calc.php (v1) is legacy.** New work goes in `calc-v2.php`; v1 is kept
   only as reference.

---

*This guide reflects the project as of July 2026 (branch
`claude/eloquent-mendel-xmv82c`). If you add files or tables, please add a row
to the matching table here — future-you will thank you.*
