# Analysis of Karma — Complete Technical Manual

**Vedic-astrology calculation & prediction platform**
PHP 8.1+ (8.4 recommended) · no framework · PSR-4 autoload · MySQL + baked fallbacks · vanilla-JS front end.

This document explains **how the software is built, every calculation engine, the
formulas used, and how to read and safely edit it in the future.** Keep it with
the source.

---

## Table of contents

1. [Big picture & design philosophy](#1-big-picture--design-philosophy)
2. [How a request flows (architecture)](#2-how-a-request-flows-architecture)
3. [Directory map](#3-directory-map)
4. [Core infrastructure (`app/Core`, `app/Security`)](#4-core-infrastructure)
5. [The astronomy engine (`app/Astro/…`)](#5-the-astronomy-engine)
   - Time · Ephemeris · Ayanamsa · Charts · CalculationEngine · Varga · Drishti · PlanetCondition
   - Strengths: Shadbala · Ashtakavarga · Bhava Bala · Vimshopaka
   - Vimshottari Dasha
6. [Birth-chart prediction engines (`app/Astro/Phala`)](#6-birth-chart-prediction-engines)
7. [Gochar / transit engines (`app/Astro/Gochar`)](#7-gochar--transit-engines)
8. [Varshaphal / annual system (`app/Astro/Varshaphal`, `Varshesh`, `Saham`, `Tajik`, `Muntha`)](#8-varshaphal--annual-system)
9. [Kundali Milan (`app/Astro/Milan`)](#9-kundali-milan-compatibility)
10. [The data/repository pattern (DB + baked fallback)](#10-the-datarepository-pattern)
11. [HTTP layer — controllers, routes, JSON endpoints](#11-http-layer)
12. [Front end — views & JavaScript components](#12-front-end)
13. [Database schema (tables by module)](#13-database-schema)
14. [The automation engine (workflows, queue, canvas)](#14-the-automation-engine)
15. [Book ingestion + AI orchestration (Module 3)](#15-book-ingestion--ai-orchestration)
16. [How to READ the code](#16-how-to-read-the-code)
17. [How to EDIT / extend (common tasks)](#17-how-to-edit--extend)
18. [Formula quick-reference appendix](#18-formula-quick-reference-appendix)

---

## 1. Big picture & design philosophy

The platform does two things:

- **A. Astrology** — from a birth date/time/place it builds the chart and produces
  detailed predictions (birth chart, dashas, transits/gochar, annual/varshaphal,
  compatibility/milan). This is what the website shows.
- **B. Automation** (Module 2/3) — an admin "visual canvas" that runs workflows
  (webhooks, cron, HTTP calls) and an AI book-ingestion + multi-agent pipeline.
  This is optional and admin-only; the astrology site works without it.

**Four design rules run through the whole codebase — learn these first:**

1. **Pure calculation, then text.** All astronomy is pure math in `app/Astro/Calc`
   and the engine classes. The *prediction wording* is separate — it lives in DB
   tables (editable) with a **baked PHP fallback** so the site still works when the
   DB is down. You change *wording* in the DB/`docs`, never in the math.
2. **One immutable chart, many readers.** `CalculationEngine::computeChart()` runs
   **once** and returns a big chart array. Every prediction engine *reads* that
   array — nothing recomputes the ephemeris.
3. **Resilience.** Every `*Repository` returns baked defaults (or `null`) on a DB
   error and records `lastError()`. Nothing fatals because MySQL is down.
4. **No framework, no Composer runtime deps.** A tiny PSR-4 autoloader, PDO,
   native cURL. Everything is explicit and readable.

---

## 2. How a request flows (architecture)

```
Browser  ──►  public_html/index.php          (the ONLY web-reachable PHP)
                │  require ../bootstrap.php   (defines AB_ROOT, registers autoloader, loads .env)
                │  switch on  "{METHOD} {?r=route}"
                ▼
        Controller  (app/Http/*Controller.php)
                │  build CalculationEngine + read birth params
                ▼
        CalculationEngine::computeChart()      →  $chart (immutable array)
                │
                ├─► prediction engines (Phala / Gochar / Varshaphal / Milan …)
                │      each reads $chart + its Repository (DB or baked text)
                ▼
        View  (app/Http/views/*.php)  renders HTML  →  Browser
                │  JS components (northchart.js, gochar.js, dasha.js …)
                ▼
        AJAX endpoints (calc/gochar, calc/varshaphal, calc/dashaPhala …) return JSON
```

**Key files:**

- `bootstrap.php` — defines `AB_ROOT` (project root), registers the PSR-4 autoloader
  (`AutoBusiness\Foo\Bar` → `app/Foo/Bar.php`), and loads `.env`. **Must stay in the
  project root.**
- `public_html/index.php` — the front controller. Routing is a small explicit
  `switch` on `"{$method} {$route}"` where `$route = $_GET['r']`. `.htaccess`
  rewrites clean URLs onto `?r=`.
- Web root is **`public_html/` only**. Everything else (`app/`, `bootstrap.php`,
  `.env`) sits one level **above** the web root so it is never served.

**Route table** (`public_html/index.php`):

| Route | Handler | Purpose |
|---|---|---|
| `GET canvas` | canvas.php view | Admin visual workflow builder |
| `GET calc` | `CalcController::show()` | The main calculator page (birth chart + all predictions) |
| `GET calc/gochar` | `gocharJson()` | Transit chart + gochar phal (JSON) |
| `GET/POST calc/ping` | `ping()` | Access-log heartbeat |
| `POST calc/translate` | `translateJson()` | On-demand Hindi→English of prediction text |
| `GET calc/varshaphal` | `varshaphalJson()` | Annual chart for a chosen year (JSON) |
| `GET calc/dashaPhala` | `dashaPhalaJson()` | Editable maha/antar summary text (JSON) |
| `GET calc/dashaEngine` | `dashaEngineJson()` | Calculated dasha cards for a maha/antar pair (JSON) |
| `GET milan` | `MilanController::show()` | Kundali Milan (compatibility) page |
| `POST api/workflow/save` / `GET api/workflow/load` | `CanvasController` | Save/load canvas graphs (admin) |
| `POST webhook` | `WebhookController::handle()` | Inbound workflow webhook (HMAC-auth) |

---

## 3. Directory map

```
project-root/
├── bootstrap.php            Autoloader + .env + AB_ROOT   (stays in root)
├── runner.php               Cron tick entry (workflow queue)  (stays in root)
├── .env / .env.example      Config (DB, keys) — real .env stays out of git
├── migrations/              26 SQL files (schema + seed prediction text)
├── docs/                    Rule books / specs (source of the seed text)
├── app/
│   ├── Core/                Env, Database (PDO), Csrf, AdminGuard, Asset, AccessLog, Uuid
│   ├── Security/            CredentialVault (AES-256-GCM)
│   ├── Astro/
│   │   ├── Time/            JulianDay
│   │   ├── Ephemeris/       AnalyticEphemeris, SwissEphemeris, Factory, Interface
│   │   ├── Ayanamsa.php
│   │   ├── Calc/            CalculationEngine, Charts, Varga, Drishti, PlanetCondition,
│   │   │                    Shadbala, Ashtakavarga, BhavaBala, VimshopakaBala,
│   │   │                    VimshottariDasha, Varshaphal, Varshesha
│   │   ├── Phala/           Birth-chart prediction engines + repositories
│   │   ├── Gochar/          Transit engines (gochar phal, muhurat, sade-sati, upcoming)
│   │   ├── Varshaphal/      Patyayini dasha, Tajik bhava phal, dasha phal
│   │   ├── Varshesh/        Year-lord selection + phal
│   │   ├── Saham/           50 sahams
│   │   ├── Tajik/           Sphuta drishti + 16 yogas
│   │   ├── Muntha/          Muntha phal
│   │   ├── Milan/           Guna Milan (Ashtakoot)
│   │   ├── Ingestion/       Book → markdown → chunks → digest (AI)
│   │   ├── Llm/             Anthropic (Claude) client
│   │   └── Orchestration/   Multi-agent fan-out
│   ├── Http/                Controllers + views/ (calc-v2.php is the main UI)
│   ├── Engine/              Workflow DAG engine + nodes (automation)
│   ├── Queue/               JobQueue, CronSchedule, WorkflowRunner
└── public_html/             Web root: index.php, .htaccess, assets/{js,css}
```

---

## 4. Core infrastructure

`app/Core/*` and `app/Security/*` — the plumbing every module leans on.

| Class | Purpose | Key methods |
|---|---|---|
| `Core\Env` | Minimal `.env` parser (no dependency). Loads `KEY=VALUE` into getenv/$_ENV. | `load($path)`, `get($k,$default)`, `int($k,$default)`, `require($k)` |
| `Core\Database` | PDO factory, single shared connection, prepared statements only, utf8mb4, exceptions on error. | `pdo(): PDO` |
| `Core\Csrf` | CSRF token for admin/canvas forms. | `token()`, `validate($t)`, `requireValid()` |
| `Core\AdminGuard` | Staff-only gate for the canvas + its API. | `require()` |
| `Core\Asset` | Cache-busting asset URLs (`?v=<mtime>`) + no-cache HTML headers. | `url($path)`, `noCacheHtml()` |
| `Core\AccessLog` | Human-readable visit log to `storage/access.log`. | `begin()`, `ping($vid)` |
| `Core\Uuid` | RFC-4122 v4 UUID (workflow ids). | `v4()` |
| `Security\CredentialVault` | **AES-256-GCM** authenticated encryption for stored secrets; random IV per record; master key from `.env` (`CREDENTIAL_MASTER_KEY`, 32 bytes base64). Never CBC. | `encrypt()`, `decrypt()`, `store()`, `reveal()` |

---

## 5. The astronomy engine

This is the heart. Everything downstream reads its output.

### 5.1 Time — `Astro\Time\JulianDay`

Converts calendar ↔ **Julian Day (UT)**. All astronomy runs on UT; local birth
time is converted with an explicit timezone offset (hours east of Greenwich) so
no timezone database is needed.

- `fromGregorian(Y,M,D,h,m,s,tzHours)` — Meeus algorithm. Local→UT via
  `dayFraction = (h + m/60 + s/3600 − tzHours)/24`; then the standard
  `floor(365.25·(y+4716)) + floor(30.6001·(m+1)) + (day+dayFraction) + b − 1524.5`
  with Gregorian correction `b = 2 − a + a/4`, `a = y/100` (and Jan/Feb counted
  as months 13/14 of the previous year).
- `toGregorian(jd, tz)` / `toDmy(jd, tz)` — inverse (used to print dates like
  `DD-MM-YYYY`).
- `centuriesSinceJ2000(jd) = (jd − 2451545.0)/36525` — feeds the ayanamsa.
- `dayNumber(jd) = jd − 2451543.5` — Schlyter epoch for the analytic ephemeris.

### 5.2 Ephemeris — `Astro\Ephemeris\*`

Supplies **tropical** geocentric ecliptic longitudes for the 7 planets + Rahu/Ketu.
The engine then subtracts the ayanamsa to get sidereal (Vedic) positions, so a
provider never needs to know the ayanamsa.

- `EphemerisProviderInterface` — `positions(jd): array` and `name()`.
- `SwissEphemeris` — **preferred**; shells out to the `swetest` binary (path in
  `.env` `SWETEST_PATH`). Arc-second accuracy. Uses the *true* node by default.
- `AnalyticEphemeris` — **pure-PHP fallback** (Paul Schlyter's method with the
  main Moon and Jupiter/Saturn perturbation terms). Always works, ~1–2 arc-min.
- `EphemerisFactory::create()` — picks Swiss when the binary exists & shell-exec is
  enabled, else the analytic one. **This is why the site runs on shared hosting
  with no extension to install.**

### 5.3 Ayanamsa — `Astro\Ayanamsa`

`sidereal = tropical − ayanamsa`. Linear precession model anchored at J2000.0:

```
ayanamsa(name, jd) = base(name) + rate · centuriesSinceJ2000(jd)
```

Supported names (`app_settings.ayanamsa`, default `lahiri`): **lahiri /
chitrapaksha** (23.85294°, 1.396042°/century), **raman** (22.50538°), **kp**
(23.71667°), **fagan_bradley** (24.74222°). Swiss Ephemeris, when present, can
supply a higher-precision value directly.

### 5.4 Charts — `Astro\Calc\Charts`

Static zodiac helpers used everywhere:

- `SIGNS` (12 English names), `SIGN_LORDS` (rashi lords), `NAKSHATRAS` (27).
- `signIndex(lon) = floor(norm(lon)/30) mod 12`; `signLord(i)`; `degInSign(lon) = norm(lon) mod 30`.
- `nakshatra(lon)` → `{index, name, pada}`; span `= 360/27 = 13°20'`,
  `pada = floor(within / (span/4)) + 1`.
- `navamsaSignIndex(lon)` (D9): each sign split into nine 3°20' parts; **start sign
  by element** — movable (0,3,6,9)→same sign, fixed (1,4,7,10)→9th, dual
  (2,5,8,11)→5th; then add the part index.
- `houseFromAsc(lon, ascSign) = (signIndex − ascSign + 12) mod 12 + 1` (whole-sign houses).
- `format(lon)` → `"12°34' Scorpio"`; `norm(deg)` → 0–360.

### 5.5 CalculationEngine — `Astro\Calc\CalculationEngine`

**The single entry point that produces the chart.** Constructed with an
ephemeris provider + an ayanamsa name.

- `computeChart(jdUt, lat, lonEast): array` — the immutable chart JSON: D1 with
  each planet's sidereal longitude, sign, degree, nakshatra, retrograde, house;
  the ascendant/MC; and the derived layers (varga charts, strengths, ashtakavarga,
  dasha, etc. depending on wiring).
- `gochar(natal, atJd, lat, lon): array` — transit snapshot for any instant. Each
  transit carries `formatted, sign, sign_index, deg, deg_in_sign, sidereal_lon,
  retro, nakshatra{index,name,pada}, combust, house_from_lagna, house_from_moon`.
- `planetSiderealLon(planet, jd)` — one planet's sidereal longitude at any JD (used
  by the transit-timeline finders).
- `vargaCharts(chart)` — all divisional charts (see Varga).
- `northPayload(chart)` — a render-ready payload for the North-Indian chart JS.
- `keySigns(jd, lat, lon)` — quick Sun/asc signs (used by the solar-return search).
- `ayanamsaName()`, `ephemerisName()`.

### 5.6 Varga — `Astro\Calc\Varga`

Divisional-chart sign math (Parashari / Jagannatha Hora conventions).
`sign(varga, lon): int` and `degree(varga, lon): float` for the 15 charts in
`Varga::CHARTS`: **D1** Rasi, **D2** Hora, **D3** Drekkana, **D4** Chaturthamsa,
**D7** Saptamsa, **D9** Navamsa, **D10** Dasamsa, **D12** Dwadasamsa, **D16**
Shodashamsha, **D20** Vimsamsa, **D24** Siddhamsha, **D27** Bhamsha, **D30**
Trimsamsa, **D40** Khavedamsa, **D60** Shashtiamsha.

### 5.7 Drishti — `Astro\Calc\Drishti`

Whole-house (Rashi) graha drishti per BPHS: every planet aspects the 7th from
itself; **Mars** also 4th & 8th; **Jupiter/Rahu/Ketu** also 5th & 9th; **Saturn**
also 3rd & 10th. `byHouse(planetHouses)`, `forChart(chart)`.

### 5.8 PlanetCondition — `Astro\Calc\PlanetCondition`

The **single source of truth** for a planet's dignity, combustion and
benefic/malefic nature (so every panel reports identical facts).

- `dignity(planet, sign, deg, planets, ascSign)` — exalt/debil/own/friend/enemy
  (deep-exaltation degrees; band edits via `configure()`/DB `planet_dignity`).
- `combustion(planet, planets)` — from Sun separation vs the per-planet orb
  (`COMBUST_ORB`, retro-aware; editable via `combustion_orbs`). Returns
  `{sep, pct, tier, orb, amavasya}` or null. `pct = round((orb−sep)/orb·100)`.
- `isBenefic()`, `naisargikaMaitri()`, `naturalRelationDirected()`, `companions()`.

### 5.9 Strengths

**Shadbala** — `Astro\Calc\Shadbala::compute(...)` — full six-fold strength,
validated to ±0.01 virupa against Parashara's Light:

1. **Sthana** = Uchcha + Saptavargaja + Ojha-Yugma + Kendradi + Drekkana
2. **Dig** — directional, from Lagna/MC cusps
3. **Kaala** — Nathonnata + Paksha + Tribhaga + Vara + Hora + Masa + Abda + Ayana + Yuddha
4. **Chesta** — Sun=Ayana, Moon=Paksha; star planets by the Seeghra (Chesta)
   Kendra = (Seeghrochcha − Madhyama)/3
5. **Naisargika** — fixed natural strength
6. **Drig** — net benefic-minus-malefic aspect (sphuta drishti); Moon & Mercury
   always benefic; ÷4

Then Total (virupas) → Rupas (÷60) → ratio vs the required minimum, plus
Ishta/Kashta phala. Helpers: `netDrishtiOnPoint`, `drishtiOnPoint`, `isDayBirth`.

**Ashtakavarga** — `Astro\Calc\Ashtakavarga::compute(signs)` — the bindu system.
Eight contributors (7 planets + Lagna) each drop a **bindu** into the signs falling
in their benefic houses (the classical `BENEFIC[planet][contributor]` house tables).
Per planet that gives a **BAV** (Bhinnashtakavarga); summing all seven gives the
**SAV** (Sarvashtakavarga). `contributes(planet, contributor, sign, contribSign)`
is the single-cell test.

**Bhava Bala** — `Astro\Calc\BhavaBala::compute(...)` — 12-house strength in
virupas: Bhavadhipati (lord's Shadbala) + Bhava Digbala + Bhava Drishti +
Bhavadhipati sign/occupant factors (PL component model).

**Vimshopaka Bala** — `Astro\Calc\VimshopakaBala::compute(lon)` — a planet's
strength across the varga groups (Shadvarga/Saptavarga/Dashavarga/Shodashavarga),
scored out of 20 with the classical per-varga weights.

### 5.10 Vimshottari Dasha — `Astro\Calc\VimshottariDasha`

**120-year cycle keyed to the Moon's birth nakshatra — pure arithmetic, exact.**

- Lords in nakshatra order: **Ketu 7, Venus 20, Sun 6, Moon 10, Mars 7, Rahu 18,
  Jupiter 16, Saturn 19, Mercury 17** (Σ = 120 years). Sidereal year = 365.25636 days.
- `sequence(moonLon, birthJd)` — starting lord = `nakshatra index mod 9`; the birth
  mahadasha's **balance** = its years × (1 − fraction of nakshatra elapsed); then
  the nine mahadashas with start/end JDs.
- `antardashas(md)` — each sub-lord's share = total × (its years / 120), starting
  from the mahadasha lord.
- `subPeriods()` — same rule for pratyantardashas.
- `running()` / `runningChain()` — the maha/antar/pratyantar active at a given JD
  (plus the next antardasha).

---

## 6. Birth-chart prediction engines

`app/Astro/Phala/*` — each reads the chart + a repository (DB text or baked
fallback) and returns render-ready cards. **The math is fixed; the wording is data.**

| Engine | What it produces | Source rules |
|---|---|---|
| `HousePrediction::generate()` | Per-house फलादेश: sign/quality/lord intro, one composite line per occupant (dignity+combustion+yuti+suitability with a single verdict), two-malefics line, aspects. | `house_pred_templates`, `house_engine_config` (migr. 006/009) |
| `KarakaPrediction::generate()` | कारक फल: each natural karaka's dignity/combustion/companions + कारको भावो नाशाय per house. | `karaka_*` (007/010) |
| `BhavPhalaRepository` | Bhavesh phal (lord of house L in house P, 12×12) + Graha-in-Bhava text. | `bhavesh_phal`, `graha_bhava_phal` (004/005) |
| `GrahaCondition::generate()` | The ग्रह स्थिति block per planet (status, combustion, companion matrix, pair-yogas). | `graha_*` (008) |
| `Yogakaraka::classify()` | BPHS Adhyaya-32 classification (yogakaraka/shubh/paap/marak/sam) from house-lordship; `dashaPeriod()`. | code (with `lagna_benefics`) |
| `RajaYoga::compute()` | BPHS Adhyaya-36 Raja Yogas + Bhanga (cancellation) + dedup vs Yogakaraka. | code |
| `YogaFinder::find()` | Small set of unambiguous classical yogas (sign/house only). | code |
| `PhaladeepikaYogaEngine::compute()` | The reliably-computable subset of 106 Phaladeepika yogas, grouped, each with a `detected` flag. | `phaladeepika_yoga` (025) |
| `ShaapEngine::compute()` | Poorva-shaap (BPHS ch.86): 105 santaan rules across 13 categories + remedies. | `shaap_rule`, `shaap_remedy` (026) |
| `DashaPhalEngine::compute()` | Calculated दशा फल v2 — where each dasha lord sits in *this* chart (house/dignity/retro/yuti/aspects/owned houses) scored + text. | `dasha_*` (011) |
| `DashaPhalaRepository::find()` | The editable maha/antar summary (12×12 grid). | `dasha_phala` (002/003) |
| `AshtakavargaPhala::forHouse()` | The "अष्टकवर्ग मत" opinion inside each house card (reuses SAV/BAV). | `av_*` (012) |
| `BhavaBalaPhala::forHouse()` | The "भाव बल मत" opinion inside each house card (reuses BB virupas). | `bb_*` (013) |

---

## 7. Gochar / transit engines

`app/Astro/Gochar/*`. All read the natal chart + a transit snapshot from
`CalculationEngine::gochar()`.

- **`GocharPhalEngine::compute(natal, transits, rules, jd, weekday)`** — the गोचर फल
  panel:
  - **Layer 1** (from Chandra Lagna): each transiting planet's house from the natal
    Moon → shubh/ashubh + house text; the Ksheen-Chandra rule (Moon 2/5/9 ashubh
    only when weak) and Ch.1 modifiers.
  - **Layer 3** (जन्म-ग्रह पर गोचर): transit-over-natal combinations.
  - Plus the Ashtakavarga, Sade-Sati and Muhurat categories (below).
- **`MuhuratEngine::compute(natal, transits, rules, weekday)`** (Ch.8) — राहु काल,
  दिशा शूल, तिथि (नन्दा/भद्रा/जया/रिक्ता/पूर्णा + पक्ष grade), janma-nakshatra वारफल,
  combustion warnings, kashta-rashi.
- **`SadeSatiEngine::compute()`** — is Saturn in a Sade-Sati/Dhaiyya house *now* +
  severity + Paya (a Layer-4 overlay; it never changes Saturn's Layer-1 text).
- **`SadeSatiTimeline::fullTimeline()`** — brackets Saturn's sign-**ingress** dates
  (via bisection over `planetSiderealLon`) to build the whole birth→future timeline
  of Sade-Sati (Moon-based) and Dhaiyya (Lagna-based) periods, 5-layer detail.
- **`UpcomingGochar::compute(eng, nowJd, tz, birthMoonSign)`** — plain-Hindi summary
  of the next ~18 months: each graha's next sign-ingress, retro/direct windows,
  current combustion, Moon-Sun paksha, Moon nakshatra, and Sade-Sati status. Uses
  finite-difference speeds + bisection to find boundaries (no heavy day loops).
- **Ashtakavarga gochar** (`GocharAvRepository`/`GocharAvData`) — bindu-count phal,
  Kaksha phal, SAV hints for the transiting planet's sign.

---

## 8. Varshaphal / annual system

The most involved subsystem — the Tajik (annual) chart and its readings.

### 8.1 The annual chart — `Astro\Calc\Varshaphal::compute(...)`

Finds the **Varsha Pravesh** (solar return): the instant the Sun returns to its
**natal sidereal** longitude in the requested year. Newton iteration:

```
jd ← Gregorian(forYear, birthMonth, birthDay, birthHour, birthMinute)   // seed
repeat: diff = signed(sunSidereal(jd) − natalSunSidereal)
        if |diff| < 1e-5 break
        jd −= diff / 0.985647            // ° Sun moves per day
```

Then it casts the annual chart at that instant/place, and derives:
- **Muntha** = `(natalAscSign + age) mod 12` — the progressed point.
- **Varsha Lagna** (annual ascendant) + lord.
- **Varshesha** (year lord) — see next.
- **Mudda dasha** — the year divided among the 9 lords in Vimshottari proportions.

> **Verified against Parashara's Light** (birth 12-12-1983, Kot Isa Khan): the
> solar-return moment, Gemini varsha-lagna and all planet positions match.

### 8.2 Year lord — `Astro\Calc\Varshesha::compute(...)`

The **Panchadhikari (five office-bearers)**: Muntha lord, Varsha-lagna lord,
Janma-lagna lord, Trirashi lord (triplicity by day/night), **Dina-ratri lord**
(the dispositor of the Sun's sign by a *day* Varsha Pravesh, of the **Moon's** sign
by a *night* one). Each is graded by **Panchavargeeya Bala** (Kshetra 30 + Uchcha
20 + Hadda 15 + Drekkana 10 + Navamsa 5 = 80 vishwas; the panel prints Total ÷ 4).

**Selection rule** (Tajika Neelakanthi, verified vs Parashara's Light):
the **strongest office-bearer that also casts a *friendly* Tajika aspect (sneha
drishti — 3rd/5th/9th/11th) on the Varsha Lagna**; **if none aspects, the Muntha
lord** becomes the year lord. (A stronger lord that does not aspect the lagna is
skipped — e.g. 2027-28 picks the weaker Muntha-lord Venus over the stronger
non-aspecting Mercury.)

### 8.3 Annual dasha — `Astro\Varshaphal\PatyayiniDasha::compute(vp)`

Take the **bhuktamsha** (deg-in-sign, 0–30) of the varsha Lagna and the 7 planets,
order by **descending amsha** = the dasha order. Each *shuddhamsha* = its amsha
minus the next lower one (telescoping to the greatest amsha). Each dasha span =
`shuddhamsha / greatestAmsha × year-length`. Antardashas divide each dasha the
same way, starting with the pakapati (dasha lord).

### 8.4 Annual readings

| Engine | Produces |
|---|---|
| `Varshesh\VarsheshEngine::compute()` | Year-lord selection trail + band-graded phal (7 planets × पूर्ण/मध्यम/हीन) + shloka 13/18/37–44 modifiers. |
| `Varshaphal\DashaPhalEngine::compute()` | Patyayini dasha phal by the lord's Panchavargeeya tier + the उपचय upgrade rule. |
| `Saham\SahamEngine::compute()` | The 50 classical Sahams (formula-defined longitudes), each with its sahamesh graded; the "active" set = sahams whose sahamesh's mudda dasha is running. |
| `Tajik\TajikYogaEngine::compute()` | The 16 Tajik yogas (Ithasala, Isarafa, Kamboola…) from the sphuta-drishti matrix. |
| `Tajik\TajikDrishtiService::matrix()` | **Sphuta drishti in KALA (0–60)** for every ordered planet pair: `diff = norm360(drishya − drashta); r = floor(diff/30); d = diff mod 30`, interpolated between the **dhruvanka** anchors. |
| `Muntha\MunthaPhalEngine::compute()` | Muntha bhava/graha/Rahu-zone reading (Tajik Neelkanthi shloka 1–36). |
| `Varshaphal\TajikBhavaEngine::compute()` | All 262 Bhava-vichara rules grouped by house; auto-marks the subset whose condition is reliably computable against the annual chart. |

### 8.5 Saham formulas (how a saham longitude is built)

Each saham is a formula over three points A, B, C (usually two planets and the
Lagna): **day formula** `A − B + C`, **night formula** often swaps A/B; the result
is normalised to 0–360 and read for sign/house/lord. The 50 definitions live in
`saham_definitions` (ordered by `seq` so dependent sahams resolve after their
inputs). The sahamesh (lord of the saham's sign) is graded via the shared
`PlanetCondition`.

---

## 9. Kundali Milan (compatibility)

`Astro\Milan\GunaMilan::compute(boy, girl, rules, cfg)` — the classical **36-guna
Ashtakoot** match from two Moon rashi/nakshatra/padas:

1. **Varna** (1), **Vashya** (2), **Tara/Dina** (3), **Yoni** (4), **Graha
   Maitri** (5), **Gana** (6), **Bhakoot** (7), **Nadi** (8) — total 36.
2. **Parihara** engine cancels specific doshas (e.g. Bhakoot/Nadi cancellations).
3. **Mangal (Manglik) dosha** test for each person (`mangalPerson()`) — Mars in
   1/2/4/7/8/12 from Lagna/Moon/Venus, with the standard exceptions.
4. Final band + summary sentence.

Look-up matrices (`milan_*` tables, migration 014) are editable; baked classical
data is the fallback. `MilanController` renders both D1+D9 charts, both planet
tables, the eight koota cards, parihara, Mangal and the summary.

---

## 10. The data/repository pattern

**This is the pattern to understand before editing anything text-related.**

Every prediction category has three parts:

```
   *Engine.php        pure logic — decides WHICH rule applies (never edited for wording)
   *Repository.php    load($lang): array  — reads the DB table(s); on error returns baked data + lastError()
   *Data.php          the baked PHP fallback (a copy of the seed text, so the site works with no DB)
        ▲
        └── migrations/0NN_*.sql  — the editable DB tables (the real source of truth) + seed rows
```

So to change *wording*: edit the **DB table** (or the migration + re-import). The
`*Data.php` fallback is generated from the same `docs/` rule files — the docblock
of each `*Data.php` names its source (e.g. "Generated from docs/Gochar_Rules.md —
change the doc / DB, not this").

---

## 11. HTTP layer

`app/Http/*Controller.php`:

- **`CalcController`** — the calculator. `show()` reads birth params from `$_GET`
  (defaults to a demo chart), builds the engine, computes the chart + every
  prediction layer, and renders `calc-v2.php`. Its JSON endpoints power the live
  panels: `gocharJson()`, `varshaphalJson()`, `dashaPhalaJson()`,
  `dashaEngineJson()`, `translateJson()`, `ping()`. Parsers: `parseDate`,
  `parseTime`, `parseAngle` (accepts DMS like `30N48'00`), `parseTz`.
- **`MilanController`** — `show()` builds two charts + `GunaMilan`; `milanPerson()`
  packages one person's chart.
- **`CanvasController`** — admin canvas `save()`/`load()` (CSRF-guarded).
- **`WebhookController`** — `handle(workflowId)`: verifies the per-workflow HMAC,
  enqueues a job, returns **202** (triggers never run inline).

---

## 12. Front end

**Views** — `app/Http/views/`. `calc-v2.php` (~3,900 lines) is the whole
single-page calculator UI + inline CSS + the JS glue. The `_*.php` partials are the
prediction panes (`_gochar_phal.php`, `_dasha_phal.php`, `_saham_pane.php`,
`_tajik_yoga.php`, `_tajik_bhava.php`, `_varshesh_phal.php`, `_muntha_phal.php`,
`_vp_general.php`, `_general_summary.php`, `_upcoming_gochar.php`, etc.), each
re-rendered by the matching JSON endpoint on a year/date change.

**JavaScript** — `public_html/assets/js/` (all pure vanilla, no libraries):

| File | Role |
|---|---|
| `northchart.js` | `ABChart.renderNorth(el, data, opts)` — inline-SVG North-Indian chart (rotate/fit/big/outer/showDeg). |
| `gochar.js` | `ABGochar.init({inputs, output, birth, fallback, injectPhal, steppers, onResult})` — the transit form (date/time/place + the +/- steppers) + fetch + render. |
| `dasha.js` | `ABDasha.render()` — expandable Vimshottari/Mudda dasha tree (children built from the 9-lord order). |
| `varshaphal.js` | `ABVarsha.init()` — the "which year?" box → fetches the annual chart JSON. |
| `citysearch.js` / `cities.js` | Type-ahead place search (Open-Meteo geocoding) → fills lat/lon/tz. |
| `saved_charts.js` | Save/open a birth chart for a signed-in user. |
| `translate.js` | On-demand Hindi→English of prediction text only. |
| `canvas.js` | The admin Drawflow workflow builder. |

**The +/- date/time steppers** (in `gochar.js`, enabled with `steppers:true`) build
one date from both fields and shift it by day/week/month/year or
minute/10min/hour/12hour, then re-fetch — so any panel using that form updates
live. They render as labelled colour-coded columns (Year/Month/Week/Day &
12Hr/Hour/10Min/Min; red minus over green plus).

---

## 13. Database schema

MySQL, ~90 tables, seeded by `migrations/001…026` (import **in numeric order**).
The app runs without them (baked fallbacks), but the DB is where an owner edits
prediction text.

**Importing / syncing the migrations (new hosting):** open
`https://your-site/index.php?r=admin/migrate` — the **DB Sync** page lists all
26 files with applied/pending status and a *Run pending* button. Applied files
are tracked in `schema_migrations`, so the page is safe to reopen any time;
after uploading a new migration file, press *Run pending* again and only the
new one executes. (With SSH: `php bin/migrate.php`, or `--force` to re-run
everything — all seeds are idempotent.) Manual phpMyAdmin import of each file
in numeric order also works.

Grouped:

- **Platform/auth/automation:** `app_settings`, `users`, `staff`, `credentials`,
  `workflows`, `workflow_conclusions`, `job_queue`, `execution_logs`,
  `command_usage_logs`, `access` logs.
- **Birth-chart prediction:** `dasha_phala`, `graha_bhava_phal`, `bhavesh_phal`,
  `planet_*`, `house_pred_templates`, `house_engine_config`, `karaka_*`,
  `graha_*`, `yuti_rules`, `graha_pair_yogas`, `lagna_benefics`, `element_*`,
  `rashi_elements`.
- **Strength opinions:** `av_*` (ashtakavarga), `bb_*` (bhava bala),
  `panchavargiya_weights`.
- **Dasha engine:** `dasha_engine_config`/`dasha_lord_from_lagna`,
  `dasha_antar_from_maha`, `dasha_bhavesh_phala`.
- **Gochar:** `gochar_*` (house/bindu/kaksha/sav/natal phal), `sadesati_phal`,
  `shani_paya`, `muhurat_*` (tithi/disha_shul/nak_vaar/combust).
- **Varshaphal:** `varshesh_phal`, `varshesh_special_rules`, `saham_definitions`,
  `saham_phal`, `tajik_*` (dhruvanka/deeptamsha/hadda/harsha/trirashi/yoga/kamboola),
  `muntha_*`, `tajik_bhava_phal`, `dasha_phal`.
- **Yogas/shaap:** `phaladeepika_yoga`, `shaap_rule`, `shaap_remedy`.
- **Milan:** `milan_*` (koota_phal, parihara, summary, gana/vashya/yoni matrices,
  nakshatra/rashi attrs).
- **AI (Module 3):** `astro_agents`, `agent_knowledge`, `agent_digest`,
  `agent_qa_history`.

---

## 14. The automation engine

Optional admin side (Module 2). A workflow is a DAG stored as JSON.

- **`Engine\ExecutionEngine::run(graph, state, deadline, checkpoint)`** — topologically
  sorts nodes, starts at the Trigger, walks the graph, passing state as
  JSON-compatible arrays. Time-boxed with checkpointing.
- **`Engine\NodeFactory::make(type)`** — maps a node type to a node object.
- **Nodes** (`Engine\Nodes\*`): `TriggerNode` (webhook/cron), `IfElseNode`
  (whitelisted operators), `HttpRequestNode` (native cURL, strict timeouts),
  `TransformNode` (a **whitelisted transform DSL — never `eval`**).
- **`Engine\TokenResolver`** — resolves `{{ Nodes.X.output.y }}` against state.
- **`Queue\JobQueue`** — enqueue + **safe single-claim** (status-flip guarded by an
  affected-rows check so two cron ticks never double-run a job); `saveState`,
  `markDone/Failed`, `resumable`.
- **`Queue\CronSchedule`** — 5-field cron evaluator (`matches`, `nextRun`).
- **`Queue\WorkflowRunner::tick()`** — one cron tick (run via `runner.php`):
  promote due schedules → claim jobs → run → checkpoint.

---

## 15. Book ingestion + AI orchestration

Module 3 (optional, needs `LLM_API_KEY`). Turns classical books into per-agent
knowledge and fans a chart out to many "book agents".

- **`Ingestion\BookIngestionService::ingest()`** — upload → `PdfTextExtractor`
  (pdftotext/pdfparser) → `MarkdownStructurer` (clean headings) →
  `MarkdownChunker` (split at headings, tag by planet/house/sign) →
  `agent_knowledge` → `DigestCompiler` → `agent_digest`. Idempotent per agent.
- **`Orchestration\KnowledgeRepository`** — retrieval bound to **one** `agent_id`
  (strict single-book isolation; no cross-book method exists).
- **`Orchestration\AgentPromptFactory`** — builds each agent's prompt from the chart
  JSON + that book's instruction + only that book's knowledge slice.
- **`Orchestration\AstrologyOrchestrator::run()`** — fans out to the agents via
  `curl_multi_exec` in **waves of 4–5** (shared-hosting safe), with checkpointing.
- **`Llm\AnthropicClient`** — Claude Messages API over native cURL (`complete()`,
  plus `body()`/`headers()`/`endpoint()` hooks for the multi-fan-out).

---

## 16. How to READ the code

1. **Start at the flow:** `public_html/index.php` → `CalcController::show()` →
   `CalculationEngine::computeChart()` → a view. Read those four first.
2. **Every class has a docblock** at the top stating exactly what it does and its
   source book/chapter. Read it before the methods.
3. **Follow the triplet.** For any prediction: find the `*Engine` (logic), the
   `*Repository` (DB access), the `*Data` (fallback), and the migration `.sql`
   (real text). The engine's docblock names the shloka/chapter.
4. **Naming is literal.** `AutoBusiness\Astro\Varshesh\VarsheshEngine` lives at
   `app/Astro/Varshesh/VarsheshEngine.php`. Views are `_snake_case.php` partials.
5. **The chart array is the contract.** When unsure what a field is, dump
   `CalculationEngine::computeChart()`'s return — every engine reads from it.
6. **Tests** in `tests/*_test.php` are runnable examples: `php tests/xxx_test.php`.

---

## 17. How to EDIT / extend

**Golden rules:** never edit `.env` values blindly; never hand-edit a `*Data.php`
(edit the DB/`docs` instead); keep math and wording separate; the web root is
`public_html/` only.

Common tasks:

- **Change a prediction's wording** → edit the DB table (the `*Repository`
  docblock/`migrations/0NN_*.sql` names it), or edit the row and re-import. The
  baked `*Data.php` is only the offline fallback.
- **Change a calculation constant** (ayanamsa value, combustion orb, dasha years)
  → the constant lives in the relevant `Calc/*` or `PlanetCondition`/`Ayanamsa`
  class; some (dignity bands, combustion orbs, bala tiers) are also DB-overridable
  via `configure()`/config tables.
- **Add an ayanamsa** → add a row to `Ayanamsa::MODELS` (`[baseAtJ2000, ratePerCentury]`).
- **Add a divisional chart** → add to `Varga::CHARTS` + a case in `Varga::sign()`.
- **Add a prediction category to the UI** → add a `_your_pane.php` partial, wire it
  into `calc-v2.php` (the `#vp-pred-type` / gochar `#gochar-cat` dropdowns), and if
  it needs live updates add a JSON endpoint in `CalcController` + a route in
  `index.php`.
- **Tune the year-lord rule** → `Astro\Calc\Varshesha::compute()` (aspect set is the
  `[3,5,9,11]` array; the Dina-ratri office is `dinaratriLord()`).
- **Adjust the chart drawing** → `public_html/assets/js/northchart.js`
  (`renderNorth`); the transit form + steppers → `gochar.js`.
- **After editing:** run `php -l file.php` (lint), the relevant `tests/*_test.php`,
  and check the page renders. Bump nothing for assets — `Core\Asset` cache-busts by
  file mtime automatically.

**Deploying a copy to new hosting:** see `DEPLOY.md` — put `public_html/` as the
document root, keep `app/`+`bootstrap.php`+`.env` one level above, copy
`.env.example`→`.env` (fill DB + `CREDENTIAL_MASTER_KEY`), import `migrations/*` in
order.

---

## 18. Formula quick-reference appendix

| Quantity | Formula |
|---|---|
| Julian Day (UT) | `floor(365.25·(y+4716)) + floor(30.6001·(m+1)) + (day + (h+m/60+s/3600−tz)/24) + (2 − y/100 + (y/100)/4) − 1524.5` |
| Sidereal longitude | `tropical − ayanamsa` |
| Ayanamsa (linear) | `base + rate · (jd − 2451545)/36525` (Lahiri base 23.85294°, rate 1.396042°/c) |
| Sign index | `floor(norm(lon)/30) mod 12` |
| Degrees in sign | `norm(lon) mod 30` |
| Nakshatra | span `13°20'`; `pada = floor((lon − idx·span)/(span/4)) + 1` |
| Navamsa start sign | movable→same, fixed→+8, dual→+4 (mod 12), then +partIndex |
| House from asc | `(signIndex − ascSign + 12) mod 12 + 1` |
| Vimshottari balance | `lordYears · (1 − nakshatraFractionElapsed)`; sidereal year 365.25636 d |
| Antardasha span | `mahaDays · (subLordYears / 120)` |
| Combustion % | `round((orb − sunSeparation)/orb · 100)` |
| Solar return (Newton) | `jd −= signed(sunSid(jd) − natalSunSid) / 0.985647` until `<1e-5` |
| Muntha | `(natalAscSign + ageCompleted) mod 12` |
| Panchavargeeya Bala | Kshetra 30 + Uchcha 20 + Hadda 15 + Drekkana 10 + Navamsa 5 = 80 (÷4 for the /20 display) |
| Year lord | strongest Panchadhikari with a friendly Tajik aspect (3/5/9/11) on Varsha Lagna; else Muntha lord |
| Dina-ratri lord | dispositor of the Sun's sign (day pravesh) / the Moon's sign (night pravesh) |
| Patyayini span | `shuddhamsha / greatestAmsha · yearLength` (amsha = deg-in-sign, descending) |
| Tajik sphuta drishti (kala) | `diff = norm360(drishya−drashta); r=floor(diff/30); d=diff mod 30`; interpolate dhruvanka anchors |
| Ashtakavarga SAV | Σ over 7 planets of the BAV bindus per sign (bindu when contributor's benefic-house table hits) |

---

*End of manual. This describes the codebase as of the `claude/astrology-software-mods-ccwpjf`
branch. Each class file's own docblock is the most precise, always-current
reference; this document is the map that ties them together.*
