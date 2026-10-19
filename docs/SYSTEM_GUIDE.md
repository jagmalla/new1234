# Analysis of Karma — Complete System Guide

**Live site:** `p.analysisofkarma.com`  ·  **Repository:** `jagmalla/new1234`
**Stack:** PHP 8.4 (no framework), vanilla JavaScript, inline SVG, MySQL/MariaDB
(optional — every page renders without it).

This is the full functional map of the software: every menu, every page, every
option inside a page, every prediction, and — most important — **how the pieces
are wired together and how a reading is actually produced**. It is written to be
read start to finish, or dipped into by section.

> Two systems live side by side and never mix their maths:
> **Vedic** (the classical calculations — chart, dashas, varshaphal, gochar,
> muhurat, milan) and **Lal Kitab** (a separate rule-book with its own fixed
> chart, its own dignity rules and its own 35-year dasha). Wherever this guide
> says "Vedic", it means the mainstream calculation; "Lal Kitab" is always
> flagged explicitly.

---

## 0. The idea in one paragraph

You type a person's birth details once. The server computes the whole horoscope
— planets, houses, 15 divisional charts, strengths, dashas, yogas, debts — as
**structured facts**. Those facts are then turned into readings by **deterministic
engines**: each engine looks at the facts, decides which rule applies, and pulls
the matching Hindi sentence. Nothing is invented and nothing is random — the same
birth details always give the same reading, and every sentence can be traced back
to the rule and the number that produced it. The Lal Kitab half works the same
way but on its own separate chart and rule-set.

---

## 1. How the whole thing fits together (the pipeline)

```
  BIRTH DETAILS                                              WHAT YOU SEE
  (name, date, time,          ┌───────────────────────┐
   place → lat/lon/tz)  ───▶  │  1. EPHEMERIS          │  where every planet
                              │  AnalyticEphemeris      │  sat in the sky at
                              │  (Swiss optional)       │  that moment
                              └──────────┬──────────────┘
                                         ▼
                              ┌───────────────────────┐
                              │  2. CALCULATION ENGINE │  ascendant, 12 houses,
                              │  Charts · Varga ·       │  D1–D60 charts, Moon,
                              │  Ashtakavarga · Shadbala│  nakshatra, ayanamsa
                              │  BhavaBala · Vimshottari│
                              └──────────┬──────────────┘
                                         │  (one big structured array — the
                                         │   "chart" — passed to everything else)
                 ┌───────────────────────┼───────────────────────────┐
                 ▼                        ▼                           ▼
     ┌────────────────────┐   ┌────────────────────┐    ┌────────────────────┐
     │ 3a. VEDIC PHALA     │   │ 3b. LAL KITAB       │    │ 3c. TIME SYSTEMS    │
     │ engines (House,     │   │ Teva → Engine →     │    │ Gochar (transit),  │
     │ Graha, Karaka,      │   │ Process → Dasha     │    │ Varshaphal (year), │
     │ Dasha, Yoga, Dosha, │   │ (its own chart &    │    │ Muhurat (timing),  │
     │ Shaap, Career…)     │   │ rules)              │    │ Milan (matching)   │
     └─────────┬──────────┘   └─────────┬──────────┘    └─────────┬──────────┘
               │  each engine asks a    │                         │
               ▼  REPOSITORY for text   ▼                         ▼
     ┌─────────────────────────────────────────────────────────────────────┐
     │ 4. REPOSITORIES  — supply the Hindi sentences for each matched rule.  │
     │    Text lives in MySQL tables; if MySQL is absent, baked-in defaults  │
     │    render instead, so the page NEVER breaks for want of a database.   │
     └─────────────────────────────────┬───────────────────────────────────┘
                                        ▼
                              ┌───────────────────────┐
                              │ 5. VIEW (calc-v2.php)  │  one page, many panels,
                              │ draws charts as SVG,    │  switched by the menu
                              │ lays out the reading    │  and the dropdowns
                              └───────────────────────┘
```

**The key design choice:** the *maths* and the *words* are separate. An engine
produces a verdict ("Saturn is strong, lord of the 7th, sitting in the 10th");
a repository turns that verdict into a sentence. That is why the readings are
precise and consistent, and why the app still works offline — the maths is pure
PHP, and the words fall back to built-in copies when there is no database.

---

## 2. How you move around — the page shell

Every reading is one HTML page (`/calc`). It has four fixed parts, and the menu
swaps what fills the middle.

| Part | What it is |
|---|---|
| **Top dark bar** | Brand, the "System is Under Testing" notice, chart name · DOB · place, a language switch (Hindi / English), **Save Chart**, **New Kundli**. Stays on one line at every width. |
| **Overview strip** | Eight colour-coded chips just under the bar: Name · DOB/Time · Birth Place · Lagna · Moon Sign · Lal Kitab Dasha · Current Dasha · Yoga count. Tap a chip to jump to that section. Hidden on the New/Profile and Custom pages. |
| **Side menu (left)** | The section switcher. On phones/tablets it becomes a ☰ drawer. Full list in §3. |
| **Work area** | Usually two columns: the **chart** on the left, the **reading** on the right. Some sections take the full width. |

**Language switch:** translates only the prediction *text* (via the
`calc/translate` endpoint). The chart, menus and labels stay as they are; the
page does not reload.

**Save Chart / Open Saved Charts:** charts are saved in the browser
(`localStorage`). "Open Saved Charts" lists them; opening one re-runs it on the
D1 page. (Server-side accounts exist in the schema but are not wired yet.)

**Create Chart** (on the New/Profile form) is the button that submits the birth
details and produces the reading.

---

## 3. The menu and every sub-menu

The left menu has top-level sections; several expand into sub-items that jump to
a specific panel. Here is the complete tree, in menu order.

| # | Menu (section) | Sub-items | What it opens |
|---|---|---|---|
| 1 | 👤 **New / Profile** | Birth form + Advanced options | Enter/edit birth details |
| 2 | 📆 **Today · आज** | — | Today's panchang & muhurat snapshot for the chart |
| 3 | 🧩 **Custom Screen** | — | Build your own grid of panels (see §16) |
| 4 | 🌟 **Birth Chart** | Kundali Chart · Predictions | The D1 chart + the prediction column (the main reading) |
| 5 | 🪐 **Planet Positions** | Birth Details · House Details · Positions (D1) · Gochar Details | Tables of every number behind the chart |
| 6 | 🗂️ **Varga Charts** | — | All 15 divisional charts in a grid |
| 7 | ⏳ **Dasha** | Current Dasha · Vimshottari (5 levels) | The running period and the full dasha tree |
| 8 | 💪 **Bala (Strength)** | Shadbala · Bhava Bala · Ashtakavarga · Vimshopaka | The four strength systems |
| 9 | 🔭 **Gochar (Transit)** | Gochar Calculation · 12-Month Timeline | Current transits + a year-ahead timeline |
| 10 | 🗓️ **Varshaphal** | Year Selection · Varsha + Mudda Dasha · Annual Positions · Bala + Year Lord | The annual (Tajik) chart and its reading |
| 11 | 🕉️ **Mahurat · मुहूर्त** | — | Auspicious-time finder (see §12) |
| 12 | 📕 **Laal Kitab** | निचोड़ · ग्रह फल · भाव फल · संबंध-जाल · ऋण·श्राप·दोष · समय · उपाय · संदर्भ | The whole Lal Kitab reading (see §11) |

A separate page, **Kundali Milan** (`/milan`), is reached from the menu/links and
covered in §13.

---

## 4. Page: New / Profile (entering the birth details)

This is where every reading begins. Fields:

| Field | Notes |
|---|---|
| **Name** | Label only; does not affect the maths. |
| **Date (DD-MM-YYYY)** | Birth date. |
| **Time (HH:MM)** | Birth time, 24-hour. The ascendant is very sensitive to this. |
| **Place** | Type a city/state/country; it fills lat/lon/timezone automatically from the built-in gazetteer of **148,038 towns** (works offline). |
| **Gender** | Used by Lal Kitab Milan side-selection and a few gendered remedies. |

**Advanced options** (collapsed by default) let you override by hand:

- **Ayanamsa** — the zodiac-alignment system (default **Lahiri**). Determines where
  the sidereal zodiac starts; changing it shifts every planet's sign slightly.
- **Latitude / Longitude** — can be typed directly, including DMS form (e.g.
  `30N48'00`).
- **Timezone (east +)** — the place's offset *on the birth date* (so historical
  DST is the user's responsibility to set).

Pressing **Create Chart** sends these to the server, which runs the pipeline in
§1 and returns the full reading page.

---

## 5. Page: Birth Chart — the main reading

This is the heart of the software: the D1 chart on the left, the prediction
column on the right.

### 5.1 The chart side (Kundali Chart)

- **Chart ▾ dropdown** — pick which chart to draw. All 15 divisional charts plus
  Gochar and Varsha:

  | Code | Name | What it is traditionally read for |
  |---|---|---|
  | **D1** | Rasi | the whole life, the body, everything (the main chart) |
  | D2 | Hora | wealth |
  | D3 | Drekkana | siblings, courage |
  | D4 | Chaturthamsa | property, home, happiness |
  | D7 | Saptamsa | children, progeny |
  | **D9** | Navamsa | marriage, spouse, dharma (the most important after D1) |
  | D10 | Dasamsa | career, status |
  | D12 | Dwadasamsa | parents |
  | D16 | Shodashamsha | vehicles, comforts |
  | D20 | Vimsamsa | spiritual practice |
  | D24 | Siddhamsha | education, learning |
  | D27 | Bhamsha | strengths & weaknesses |
  | D30 | Trimsamsa | misfortunes, troubles |
  | D40 | Khavedamsa | maternal legacy |
  | D60 | Shashtiamsha | past-life karma (finest division) |

- **Rotate ▾** — put any house (bhava) at the top position, so you can read the
  chart "from" the Moon, or from any planet. Default is from the Lagna.
- **Zoom (two-finger pinch)** — on touch screens, pinch the chart to shrink it
  (down to 40%) so a large chart fits on a phone; pinch out to restore.
- **The outer ring on the D1 chart** carries three numbers per house:
  - **AV** = Ashtakavarga bindus (benefic points) — coloured **red** (0–24),
    **yellow** (25–28) or **green** (29–56) by strength.
  - **BB** = Bhava Bala (house strength, in virupas) — **red** (<360),
    **yellow** (360–510) or **green** (>510).
  - **Dr** = Drishti — which planets aspect that house.
- **Dasha strip** under the chart always shows the running Mahadasha →
  Antardasha → Pratyantar, plus the Lal Kitab dasha for comparison.

### 5.2 The prediction side — the dropdown, option by option

The **Select ▾** dropdown chooses which layer of the reading to show. There is
also a **🔍 topic search** box (chips + free text) that scans every layer, and an
**⤢ expand** button for a wider reading view, and a **🖨 print** button.

| Option | What it shows and how it is produced |
|---|---|
| 🔎 **General Overview** | The headline summary — lagna nature, Moon sign, key strengths and the running dasha, stitched from the strongest findings across the other engines. |
| ⏳ **Dasha (दशा)** | What the *current and upcoming* Vimshottari periods mean, from `DashaPhalEngine` — reads the Maha/Antar lords, their house-lordship and placement, and supplies the matching period reading. |
| 🪐 **Planet (ग्रह)** | Every planet twice: **(A) as a house-lord** (Bhavesh — "lord of the 3rd placed in the 6th means…") and **(B) as a placement** (the planet sitting in a house). From `GrahaCondition` + `HousePrediction`. Each line carries the planet's condition (exalted/debilitated/own/combust/retrograde) as the reason. |
| 🏠 **House (भाव)** | Each of the 12 houses read in turn — who owns it, who sits in it, who aspects it, how strong it is — from `HousePrediction` using Bhava Bala and the occupants. |
| 🏛 **Bhavesh (भावेश)** | The house-lords specifically: where each lord goes and what that does to the house it rules. |
| 🎯 **Karak (कारक)** | The natural significators (Sun=soul, Moon=mind, Mars=energy, Mercury=speech, Jupiter=wisdom/children, Venus=marriage, Saturn=longevity) and how well each performs — from `KarakaPrediction`. |
| ✨ **Kundali Yog (योग)** | Named planetary combinations (Raja yogas, Dhana yogas, and the Phaladeepika yoga set) that are *actually active* in this chart, with a count — from `PhaladeepikaYogaEngine`, `RajaYoga`, `YogaFinder`, `Yogakaraka`. The overview strip's "Yoga" chip shows this count (active · shubh · ashubh). |
| ⚠️ **Dosha (दोष व परिहार)** | Afflictions present (and, importantly, their **parihar** — the classical cancellation/relief when the chart provides one) — from `DoshaFinder`. |
| 🧬 **Shrap (पूर्वशाप व सन्तान)** | Past-life curses (pitru, matru, sarpa, etc.) and children-related reading — from `ShaapEngine` + `SantanEngine`. |
| 🔍 **खोज परिणाम** | Where the topic-search results land (hidden until you search). |

Beyond the dropdown, specialised life-area engines also feed the reading:
**CareerEngine** (10th house / D10 / job vs business), **WealthEngine** (2nd/11th/
Dhana yogas), **VideshEngine** (foreign travel & residence), **PoliticsEngine**
(public life), **StrengthMeter** (an overall strength gauge).

---

## 6. Page: Planet Positions (the numbers behind everything)

Four tables, reached from the sub-menu:

- **Birth Details** — the computed facts: ascendant degree, ayanamsa used, day/
  night birth, sunrise basis, Julian Day, location.
- **House Details** — each house's cusp, its sign, its lord, who occupies it, its
  Bhava Bala.
- **Positions (D1)** — every planet's exact sidereal longitude, sign, degree-in-
  sign, nakshatra + pada, house from Lagna, house from Moon, Navamsa sign,
  retrograde/combust flags, and its dignity.
- **Gochar Details** — the same table for the *current sky* (today's transit),
  so you can compare transit to birth.

This section is the audit trail: everything a prediction claims can be checked
against these raw numbers.

---

## 7. Page: Varga Charts

All 15 divisional charts (D1–D60, listed in §5.1) drawn together in a grid, each
as its own North-Indian diamond. Used to cross-check a theme: e.g. a promising
10th house in D1 is only trusted if D10 agrees.

**How a varga is built:** each divisional chart slices every sign into N parts
(D9 = 9 parts of 3°20′ each, etc.) and maps the planet's position into a new
sign by the classical rule for that division. Computed by `Varga.php` from the
same D1 longitudes — no new astronomy, just re-mapping.

---

## 8. Page: Dasha (the timeline of life)

The **Vimshottari Dasha** — the main Vedic timing system. It is driven by the
Moon's nakshatra at birth and runs a fixed 120-year cycle of nine planets:

| Lord | Years | | Lord | Years |
|---|---|---|---|---|
| Ketu | 7 | | Saturn | 19 |
| Venus | 20 | | Mercury | 17 |
| Sun | 6 | | (Ketu) | … |
| Moon | 10 | | | |
| Mars | 7 | | **Total** | **120** |
| Rahu | 18 | | | |
| Jupiter | 16 | | | |

Sub-menu:

- **Current Dasha** — the period running *now*: Mahadasha → Antardasha →
  Pratyantar, with exact start/end dates, plus the next Antardasha.
- **Vimshottari (5 levels)** — the full tree, drillable five levels deep
  (Maha → Antar → Pratyantar → Sookshma → Prana), each with dates.

Dates are computed by `VimshottariDasha.php`; the readings for a period come from
`DashaPhalEngine` (which reads the period-lord's lordship and placement). A
second JSON endpoint, `calc/dashaEngine`, serves deeper on-demand drill-downs.

> **Lal Kitab has its own, different dasha** — a fixed 35-year planetary cycle
> that does **not** depend on the Moon's nakshatra. It is shown separately under
> Lal Kitab (§11) and on the overview strip, clearly labelled so the two are
> never confused.

---

## 9. Page: Bala (Strength) — four systems

How strong each planet and house is, by four independent classical methods:

- **Shadbala** — "six-fold strength" of each planet: Sthana (positional), Dig
  (directional), Kaala (temporal), Chesta (motional), Naisargika (natural) and
  Drik (aspectual), summed in virupas, with the Ishta/Kashta (benefic/malefic
  potency) split. From `Shadbala.php`.
- **Bhava Bala** — strength of each *house* (from its lord's Shadbala, its Dig
  bala, aspects on it, and its occupants). This is the **BB** number on the
  chart ring. From `BhavaBala.php`.
- **Ashtakavarga** — the bindu (benefic-point) system: each planet contributes
  points to each sign, summed into the Sarvashtakavarga. This is the **AV**
  number on the chart ring. From `Ashtakavarga.php`.
- **Vimshopaka Bala** — a planet's strength scored out of 20 across groups of
  divisional charts (Shadvarga 6, Saptavarga 7, Dashavarga 10, Shodashavarga 16).
  From `VimshopakaBala.php`.

The readings that interpret these numbers come from `AshtakavargaPhala` and
`BhavaBalaPhala`.

---

## 10. Page: Gochar (Transit) — the current sky

Where the planets are **right now** (or on any date you set) relative to the
birth chart.

Sub-menu:

- **Gochar Calculation** — a date/time/place card (change it and everything
  below recomputes) and the transit chart drawn beside the natal D1. A
  **Rotate** control turns the charts. Served live by the `calc/gochar` endpoint.
- **12-Month Timeline** — the year ahead: when each slow planet changes sign,
  and the running **Sade Sati** (Saturn's 7½-year passage over the Moon) status.
  From `YearTimeline`, `UpcomingGochar`, `SadeSatiEngine` / `SadeSatiTimeline`.

The transit *reading* (what the current transits mean) comes from
`GocharPhalEngine`, shown as the "Gochar Phal" panel. Sade Sati gets its own
detailed timeline (`SadeSatiTimeline`) with the three phases.

---

## 11. Page: Lal Kitab (a separate system)

Lal Kitab is **not** a view of the Vedic chart — it is its own book with its own
rules. The software implements it faithfully and keeps it walled off from the
Vedic maths.

**What makes it different:**
- The chart is a **fixed-Aries teva**: house 1 is always Aries, house 2 Taurus,
  and so on — planets fall into houses by their sign, not by a rising sign.
- **Dignity comes from the HOUSE, not the rashi** — a planet is strong or weak by
  which Lal Kitab house it sits in.
- It has **one-way drishti** (aspects go in one direction only) and **टक्कर**
  (collision) rules not found in Vedic astrology.
- Verdicts use **four states**, with **निष्क्रिय (dormant)** as its own state —
  a dormant planet is *not* the same as a bad one.
- Its **dasha is a fixed 35-year cycle** (Shani, Rahu, Ketu, Guru, Surya, Shukra,
  Mangal, Budh, Chandra blocks), shown with age-bands and calendar years.

The eight views (sub-menu), in order:

| View | Shows |
|---|---|
| 📋 **निचोड़ व प्राथमिकता** (nichod) | The summary: the chart's overall temperament, your biggest strength, what to watch, the current period, and the **priority** remedies — the one screen to read first. |
| 🪐 **ग्रह फल** (planet) | Each planet's Lal Kitab reading and its remedy, planet by planet, with a "show only troubled planets" filter. |
| 🏠 **भाव फल** (house) | Each house read the Lal Kitab way. |
| 🔗 **संबंध-जाल** (yoga) | The relationship web: yogas, **मसनूई** (artificial/made) planets, **टक्कर** (collisions) and **दृष्टि** (one-way aspects). |
| 🧬 **ऋण · श्राप · दोष** (shrap) | The **debts** (rin) a chart carries — ancestral, self-made — plus curses and blemishes, each with how to clear it. |
| 📅 **समय** (agecycle) | Timing: the 35-year age-dasha, the yearly (varsh) cycle, and Sade Sati in the Lal Kitab frame. |
| 🛠 **उपाय** (remedy) | The consolidated remedies: direction first (pacify / strengthen / awaken), the right target, protector and forbidden-combination guards, with duration and a stop-when condition. |
| 📚 **संदर्भ व तुलना** (reference) | Reference tables and a Vedic ↔ Lal Kitab comparison. |

**How the Lal Kitab reading is produced (its own mini-pipeline):**

```
chart longitudes ─▶ LalKitabTeva  (fixed-Aries chart, house-based dignity)
                 ─▶ LalKitabEngine (drishti, takkar, masnui, debts, verdicts)
                 ─▶ LalKitabProcess (the निचोड़ + the remedy sequence)
                 ─▶ LalKitabDasha  (the 35-year timing)
                    using LalKitabData / LalKitabSettings for the rule text
```

The **remedy sequence** (`LalKitabProcess::remedySequence`) is deliberate: it
decides the *direction* (शांति / बल-वृद्धि / जगाना), redirects a remedy to the
planet that "wakes" the target, respects protector and forbidden (वर्जित) guards,
checks the native (स्वभाव) gate, and caps the result at one or two remedies with
a duration and a stop-when — so a client is never handed a confusing pile of
upaay.

The whole Lal Kitab subsystem is guarded by a **62-check self-test** that fetches
real pages and asserts the rendered result, so a rule change that breaks a
reading is caught immediately.

---

## 12. Page: Varshaphal (the annual chart) & the Lal Kitab Varsh Kundali

**Varshaphal** (Tajik / annual astrology) answers "what does *this year of your
life* hold?" It casts a fresh chart for the moment the Sun returns to its exact
birth position each year (the solar return), and reads it with Tajik rules.

Sub-menu / options:

- **Year Selection** — a bar at the top; pick the age/year and everything on the
  page recomputes for that year (served by `calc/varshaphal`).
- **Varsha + Mudda Dasha** — the annual chart itself, plus the **Mudda Dasha**
  (the dasha *within* the year), drillable.
- **Annual Positions** — the planet table for the solar-return chart.
- **Bala + Year Lord** — the Panchavargeeya strengths and the **Varshesh** (the
  lord of the year — the single planet that governs the whole year).

The annual reading is assembled from several Tajik engines:

| Piece | Engine | What it adds |
|---|---|---|
| **Muntha** | `MunthaPhalEngine` | the "progressed ascendant" — moves one house per year; its house and lord colour the year |
| **Varshesh** (year lord) | `VarsheshEngine` | the planet ruling the year and its effect |
| **Saham** | `SahamEngine` | sensitive points (Punya, Vidya, Yasas, Vivaha, Putra…) — Arabic-part-style |
| **Tajik Yoga** | `TajikYogaEngine` + `TajikBhavaEngine` | the year's Tajik combinations (Ithasala, Ishraaf, etc.) |
| **Dasha-Phal** | Varshaphal `DashaPhalEngine` + `PatyayiniDasha` | what each sub-period of the year brings |
| **Bhava-Phal** | `TajikBhavaEngine` | each house of the annual chart |

These surface as the Varshaphal prediction panels: **General · Saham · Tajik ·
Varshesh · Muntha · Bhava · Dasha**.

**Lal Kitab Varsh Kundali** (a dedicated page): the left side shows the Lal Kitab
annual chart, below it the Vedic Varshaphal chart **with the Muntha marked**; the
right side carries the year's predictions like every other Lal Kitab page; a year
bar at the top switches both charts together. Served by `calc/lalkitabVarsh`.

---

## 13. Page: Kundali Milan (`/milan`) — matchmaking

A separate page for matching two horoscopes (marriage compatibility). It runs
**two independent systems** side by side:

1. **Guna Milan (Ashtakoota)** — the classic 36-guna score across eight kootas:

   | Koota | Guna | Tests |
   |---|---|---|
   | वर्ण (Varna) | 1 | ego / caste temperament |
   | वश्य (Vashya) | 2 | mutual control / attraction |
   | तारा (Tara) | 3 | birth-star compatibility, health |
   | योनि (Yoni) | 4 | physical/sexual compatibility |
   | ग्रह मैत्री (Graha Maitri) | 5 | mental friendship (Moon-lords) |
   | गण (Gana) | 6 | temperament (deva/manushya/rakshasa) |
   | भकूट (Bhakoot) | 7 | love, family welfare |
   | नाड़ी (Nadi) | 8 | health, progeny (the heaviest) |
   | **Total** | **36** | |

   From `GunaMilan.php`. Mangal (Manglik) dosha is checked too.

2. **Lal Kitab Milan** — the Lal Kitab compatibility, rebuilt as five clear axes
   (saat / karak / mangal / ghar / rin) scored as a count of clear points out of
   five (not a percentage), with a tier (shubh / shubh-upay / savdhan / kathin),
   the relevant **parihar** (relief), remedies drawn from each chart's own upaay,
   and cautions. From `LalKitabMilan.php`.

**Marriage-muhurat date finder** (bottom of the page): defaults to *today →
+6 months*, with **1-month / 3-month / 6-month / 1-year** tabs that fill the
dates from today. It scans the range for auspicious wedding dates using the
muhurat engines (`MarriageDateFinder` + the muhurat scanner). When you open Milan
with a chart already loaded, the current chart is pre-filled on the side matching
the selected gender.

---

## 14. Page: Mahurat (मुहूर्त) — auspicious timing

Answers "when is a good time to do X?" It combines **panchang purity** (the five
limbs of the day) with **task-specific rules**.

The page shows a date/time/place card, the transit chart, the natal D1 and the
year chart, and a **category picker**. The muhurat verdict recomputes whenever you
change the date — it scores the moment and explains Rahu Kaal, Disha Shool and the
tithi-result.

**The panchang basis** (checked for every muhurat) — Vara (weekday), Tithi
(lunar day), Nakshatra, Karana, plus Tara-bala, Chandra-bala and Guru-Shukra
bala, and the nakshatra's **sanjna** (temperament: चर/क्षिप्र/मृदु/उग्र/मध्यम/
सौम्य/दारुण…), each with the kind of work it favours.

**Task categories** (each its own engine), grouped:

| Group | Engine | Covers |
|---|---|---|
| General शुभ | `GeneralMuhuratEngine`, `KaryaMuhuratEngine` | any general auspicious act |
| Personal | `PersonalMuhuratEngine` | timing tuned to the person's own chart |
| Sanskara (rites) | `SanskaraMuhuratEngine` | सीमन्तोन्नयन, नामकरण/जातकर्म, अन्नप्राशन, कर्णवेध, मुण्डन/चूडाकर्म, विद्यारम्भ, उपनयन |
| Vivaha (marriage) | `VivahaMuhuratEngine`, `AgniVivahaMuhuratEngine` | wedding muhurat and its agni/variations |
| Griha / Vastu | `VastuMuhuratEngine` | गृह-प्रवेश (house entry), construction |
| Yatra (travel) | `YatraMuhuratEngine`, `YatraPratishthaEngine` | journeys, installation |
| Shatkarma (study) | `ShatkarmaStudyEngine` | the six tantric acts (study/reference) |

The auspiciousness score and its red/amber/green bar come from
`Auspiciousness.php`; a **date-range scan** (find every good day between two
dates) is done by `MuhuratDateScanner` and served by `calc/muhuratScan`. All the
rule data sits in `MuhuratChintamaniData` and `ShatkarmaData`.

---

## 15. Page: Today · आज

A quick daily snapshot for the loaded chart — today's panchang (vara, tithi,
nakshatra, yoga, karana) and a muhurat-purity reading for the day, so the person
sees "how is today for me" without opening the full muhurat page.

---

## 16. Page: Custom Screen

A build-your-own workspace. It opens with a default layout of **2 big boxes (top
row) + 3 small boxes (bottom row)**, all sized to fit on one screen. Each box is
a **"+ Add panel"** slot; press it and pick any panel from the catalogue. Panels
are freely resizable (drag the corner), and a logged-in user's layout is
remembered.

The catalogue (56 options) covers everything in the app:

- **Charts** — D1–D60, Gochar, Varsha.
- **D1 predictions** — General, Dasha, Bhavesh, Graha, Bhava, Karaka, Yoga, Shaap.
- **Lal Kitab** — निचोड़, ग्रह फल, भाव फल, कारक, संबंध-जाल, ऋण·श्राप·दोष,
  साढ़ेसाती, मांगलिक, आयु-दशा, आयु योग, स्वास्थ्य, भवन, वर्ष कुंडली, तुलना.
- **Varshaphal** — General, Saham, Tajik, Varshesh, Muntha, Bhava-Phal, Dasha-Phal.
- **Gochar** — Gochar Phal.
- **Muhurat** — आज का मुहूर्त (static card); the full श्रेणी-वार finder opens the
  Muhurat page (it is live/date-driven, so it can't be a frozen snapshot).
- **Dasha** — Vimshottari, Mudda.
- **Bala** — Shadbala, Bhava Bala, Ashtakavarga, Vimshopaka.
- **Other** — Kundali Milan (opens the Milan page).

So a reader can, for example, put the D1 chart, the निचोड़, the dasha and the
current Varshaphal side by side on one screen.

---

## 17. The complete list of functions (engines & what each does)

Grouped by job. Each is a plain PHP class under `app/Astro/…`.

### 17.1 Astronomy & chart core (`Calc/`, `Ephemeris/`, `Time/`)
- **AnalyticEphemeris** — computes planet positions from analytic series (default;
  accurate to ~1–2 arc-minutes, enough for signs and houses). No internet.
- **SwissEphemeris** — optional higher-precision provider (needs the Swiss
  Ephemeris binary via `SWETEST_PATH`); `EphemerisFactory` picks whichever is set.
- **CalculationEngine** — the conductor: takes birth details, calls the ephemeris,
  builds the ascendant, the 12 houses, every planet's sign/degree/nakshatra/
  condition, the vargas, the strengths and the dasha, and returns one big
  structured "chart" array that every other engine consumes.
- **Charts** — ascendant + house cusps + planet-to-house mapping.
- **Varga** — the 15 divisional charts.
- **Ashtakavarga** — bindu points (the AV ring numbers).
- **Shadbala** — six-fold planetary strength.
- **BhavaBala** — house strength (the BB ring numbers).
- **VimshopakaBala** — divisional-chart strength out of 20.
- **VimshottariDasha** — the 120-year dasha tree.
- **Drishti** — Vedic aspects.
- **PlanetCondition** — exaltation/debilitation/own/friend/combust/retrograde.
- **Varshaphal / Varshesha** — the solar-return chart and its year-lord.
- **Ayanamsa** — the sidereal offset (Lahiri and others).
- **JulianDay** — date/time ↔ Julian Day and back (all time maths).

### 17.2 Vedic predictions (`Phala/`)
HousePrediction · GrahaCondition · KarakaPrediction · DashaPhalEngine ·
PhaladeepikaYogaEngine · DoshaFinder · ShaapEngine · SantanEngine · RajaYoga ·
YogaFinder · Yogakaraka · CareerEngine · WealthEngine · VideshEngine ·
PoliticsEngine · StrengthMeter · AshtakavargaPhala · BhavaBalaPhala — each paired
with a `…Repository` that supplies its Hindi text.

### 17.3 Lal Kitab (`LalKitab/`)
LalKitabTeva · LalKitabEngine · LalKitabProcess · LalKitabDasha · LalKitabMasnui ·
LalKitabMilan · LalKitabData · LalKitabSettings · LalKitabSelfCheck.

### 17.4 Time systems
- **Gochar/** — GocharPhalEngine, GocharData/Repository, GocharAv, SadeSatiEngine/
  Timeline, UpcomingGochar, YearTimeline, DashaSupport, MuhuratData/Engine.
- **Varshaphal/**, **Varshesh/**, **Muntha/**, **Saham/**, **Tajik/** — the annual
  (Tajik) engines listed in §12.
- **Muhurat/** — the 12 muhurat engines + Auspiciousness + MuhuratDateScanner +
  MarriageDateFinder + data classes (§12, §13).
- **Milan/** — GunaMilan + MilanRepository (§13).

### 17.5 Support (`Core/`, `Geo/`)
Database (one lazy PDO) · Env (.env reader) · Asset (cache-busting `?v=`) · Csrf ·
AccessLog · AdminGuard · MigrationRunner · Uuid · **CityGazetteer** (the offline
148k-town place search).

### 17.6 The AI half (built but not wired)
`Llm/`, `Ingestion/`, `Orchestration/` — an LLM client, book-ingestion and a
fan-out orchestrator exist in code but are **not connected to any route**. See
`docs/AI_PLAN_STATUS.md`. The readings you see today are the deterministic
engines above, not an LLM.

---

## 18. How the pages talk to the server (JSON endpoints)

The first reading is one big HTML page; the interactive bits fetch JSON so they
update without a reload. Routing is a small map keyed by `?r=` (clean paths map
onto it via `.htaccess`).

| Route | Purpose |
|---|---|
| `calc` | The main reading page (GET). |
| `calc/citySearch` | Place search → lat/lon/tz (the gazetteer). |
| `calc/gochar` | Transit chart + gochar phal for a date/place. |
| `calc/varshaphal` | The annual chart + reading for a chosen year. |
| `calc/lalkitabVarsh` | The Lal Kitab annual (varsh) chart for an age. |
| `calc/dashaPhala` | Dasha-period readings. |
| `calc/dashaEngine` | Deeper on-demand dasha drill-downs. |
| `calc/muhuratScan` | Scan a date range for auspicious days. |
| `calc/translate` | Translate the prediction text (language switch). |
| `calc/ping` | Health check. |
| `milan` | The Kundali Milan page. |
| `canvas` | The staff-only visual builder (default route). |
| `admin/migrate` | Run the DB migrations (staff only). |

---

## 19. How the whole system connects — in words

1. **One computation, many readers.** The birth details are turned into the
   `chart` array *once* by `CalculationEngine`. Every prediction engine, the Lal
   Kitab subsystem, gochar, varshaphal, muhurat and milan all read from that same
   array (or recompute a sibling chart from the same ephemeris). Change the
   ascendant and *everything* downstream changes consistently, because they all
   draw from the one source.

2. **Maths and words are separated.** Engines decide *what is true* (verdicts,
   numbers). Repositories decide *how to say it* (Hindi/English sentences, stored
   in MySQL). The view decides *how to show it* (SVG charts, panels). You can
   change the wording without touching the astrology, and vice-versa.

3. **MySQL is optional.** Every repository wraps its database read in a
   try/catch and falls back to baked-in data. No database → the section still
   renders with its built-in text. This is why the app runs offline.

4. **Vedic and Lal Kitab never cross.** They are two pipelines from the same
   birth moment. The Lal Kitab teva, dignity, drishti and dasha are computed
   independently and only ever *compared* to the Vedic side (in the reference
   view), never merged into it.

5. **The front end is thin.** The server does all the astrology; the browser
   draws SVG charts (`northchart.js`) and fetches the JSON endpoints for the
   interactive panels. That keeps the readings identical whether viewed on a
   phone, a tablet or the Custom Screen.

---

## 20. How predictions work — the short version

> A prediction is **never free text**. It is: *a rule the engine matched* +
> *the number or placement that triggered it* + *the sentence the repository
> stores for that rule*.

Worked example — the "Planet (ग्रह)" line for Mars:

1. `CalculationEngine` places Mars: sign, degree, **house 7**, and its condition
   (say, **debilitated, retrograde**).
2. `GrahaCondition` + `HousePrediction` see "Mars, lord of houses 1 & 8, placed
   in house 7, debilitated, retrograde."
3. The matching rule is looked up; the repository returns the stored Hindi
   reading for *that exact situation*, and the condition (debilitated, retrograde)
   is shown as the **reason**.
4. The view prints the line, colour-coded, in the "Planet" layer.

Because every step is deterministic, the same birth details always yield the
same reading, and every sentence can be traced back to a number you can verify in
**Planet Positions** (§6). The Lal Kitab readings follow the same discipline on
their own chart; the muhurat and varshaphal readings follow it on their own
(daily / yearly) charts.

---

## 21. Quality control

A self-check harness (`LalKitabSelfCheck`, **62 checks**) fetches real pages over
HTTP and asserts against the rendered HTML — run from the command line
(`php tests/lalkitab_process_check.php`) or the browser (`/lk-selfcheck.php`).
Every new rule gets a check, and every check is "negative-tested" (the fault is
reintroduced to confirm the check actually goes red). There are also focused unit
tests under `tests/` for the calculation engine, gochar, muhurat, muntha, dasha,
yoga, sadesati and more.

---

## 22. Where to read more

- `docs/PROJECT_DOCUMENTATION.md` — the architecture & file-by-file reference.
- `docs/LALKITAB_DECISIONS.md` — the running log of every design decision (why
  each rule is the way it is), newest at the bottom.
- `docs/OFFLINE_SETUP.md` — run the whole app on your own computer, step by step.
- `docs/ANDROID_APP_PLAN.md` — the plan for an Android app.
- `docs/AI_PLAN_STATUS.md` — what the AI/LLM half is and what remains.

---

*This guide describes the software as it stands in the repository. The two
systems — Vedic and Lal Kitab — are kept deliberately separate; the calculations
are deterministic and traceable; and every page here is produced by the pipeline
in §1.*
