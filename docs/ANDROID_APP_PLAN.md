# Android App — Plan

**For:** Analysis of Karma (`p.analysisofkarma.com`) · repo `jagmalla/new1234`
**Written against commit `7045b14`.** Everything below is measured from this
codebase, not assumed.

---

## 1. The one decision everything else follows from

Not "which framework" — **where does the calculation run?**

Today every number this product shows is computed in PHP on the server:
`CalculationEngine::computeChart()`, ~30 Vedic engines, the whole Lal Kitab
subsystem, muhurat, milan. The browser only draws. So an Android app has
exactly three honest positions:

| | Calculation | Text banks | Works with no net |
|---|---|---|---|
| **Thin** | server | server | no |
| **Cached** | server | server, cached on phone | previously opened charts only |
| **On-device** | phone | bundled in the app | yes, fully |

Everything else — WebView vs Flutter vs Kotlin — is a consequence of that
choice. Pick the row first.

---

## 2. What the current code is ready for, and what it is not

Facts, measured:

- **`GET /calc` returns 2.20 MB of HTML.** The whole product is rendered
  up-front — every section, every prediction, every Lal Kitab view. Lal Kitab
  alone starts at 1.22 MB into the document. On a phone on mobile data this is
  the single biggest problem: one chart ≈ 2.2 MB before images or fonts.
- **There is no API for the main page.** Eight JSON endpoints exist
  (`calc/gochar`, `calc/varshaphal`, `calc/lalkitabVarsh`, `calc/dashaPhala`,
  `calc/dashaEngine`, `calc/muhuratScan`, `calc/citySearch`, `calc/ping`) — all
  *secondary* views. The primary reading is HTML only.
- **Auth is `AdminGuard`** — a staff session, or `APP_ENV=local`. There is no
  token auth an app could use. The `users` table exists in migration 001 but the
  account system is not wired: saved charts sit in `localStorage` under a
  `guest` bucket.
- **Three CDN dependencies** — `cdn.tailwindcss.com`, `fonts.googleapis.com`,
  `fonts.gstatic.com`. Without a net the page loads but is unstyled.
- **The place gazetteer is already local** (148,038 towns, 4.2 MB, `app/Astro/Geo/`).
  That work is done and pays off in every option below.
- **Charts are already SVG** drawn by `northchart.js` (152 KB of JS in total) —
  portable to any target that can run JS, and re-implementable in Dart in a day
  or two if needed.

Read plainly: **the system is ready to be wrapped, not yet ready to be
consumed.** Making it consumable is Phase 2, and it is the phase that unlocks
everything after it.

---

## 3. The four routes

### Route A — WebView wrapper / TWA
Package the existing site as an installable app.
*Cost:* days. *Offline:* none (beyond a cached shell). *Play Store:* accepted
**only** if the app adds native value — Google rejects thin webview wrappers
under the Minimum Functionality policy. Push notifications, share, saved charts
and an offline shell are enough to clear that bar.

### Route B — PWA, then TWA
Same as A, but do it properly: a web app manifest, a service worker that caches
the shell and the last-opened charts, then wrap with Bubblewrap into a Trusted
Web Activity. The app is the site; updates ship by uploading PHP, no Play
release needed.
*Cost:* 1–2 weeks. *Offline:* shell + previously opened charts. **This is the
right first step**, and it is not throwaway — every later route keeps it.

### Route C — Flutter app over a JSON API
Build `/api/v1/*` over the existing engines, then a Flutter app that renders
natively. Real app feel, small payloads, proper offline cache of computed
charts, one codebase for iOS later.
*Cost:* API 2–3 weeks; app 6–10 weeks for full parity. *Offline:* charts you
have opened before.

### Route D — fully offline app
Port the calculation to the phone. Realistically that means porting
`AnalyticEphemeris` + `Charts` + `Varga` + `VimshottariDasha` + the Lal Kitab
teva rules to Dart, and bundling `lalkitab_data.json` and the MySQL text banks
as assets. It does **not** mean porting all 62,000 lines: the deterministic
core is a few thousand.
*Cost:* 8–14 weeks on top of C, plus a correctness programme (see §7).
*Offline:* complete.

**Recommendation: B → C → D, in that order, each shippable on its own.**
Do not start at C or D. Route B puts a real app in your hand in two weeks and
tells you what people actually open before you spend three months rebuilding it.

---

## 4. Phase 0 — make the web app app-ready (1–2 weeks)

These are worth doing even if no app is ever built.

1. **Cut the 2.2 MB page.** Render the birth chart + निचोड़ up-front; load the
   other sections on demand through the same lazy pattern the popup already
   uses. Target under 300 KB for the first paint. *This is the highest-value
   item in the whole plan and it helps the website today.*
2. **Vendor Tailwind and the fonts.** Build a static CSS file at deploy time and
   self-host Mukta/Martel. Removes the last three external calls, so the app
   works styled with no net.
3. **Token auth beside `AdminGuard`.** A signed token (JWT or a random token in
   a `device_tokens` table) accepted by the same guard, so an app can call the
   API without a browser session. Sessions keep working exactly as now.
4. **Wire the accounts that migration 001 already defines,** so saved charts
   move off `localStorage` and follow the user to the phone.
5. **Version the assets** — already done via `Asset::url()`; keep it.

## 5. Phase 1 — PWA → Play Store (1–2 weeks)

- `public_html/manifest.webmanifest` — name, icons (192/512), `display:
  standalone`, theme colour, `start_url: /calc`.
- `public_html/sw.js` — cache-first for `/assets/*` and the vendored CSS/fonts;
  network-first with cache fallback for `/calc`; keep the last N charts.
- An offline page that says plainly what still works (saved charts) and what
  does not (new chart needs the server) — never a blank screen.
- `assetlinks.json` for Digital Asset Links, then **Bubblewrap** → signed AAB →
  Play Console.
- Play listing: privacy policy URL, data-safety form (you collect birth data —
  declare it), content rating, screenshots.

**Deliverable:** an installable app, on the Play Store, running your real site.

## 6. Phase 2 — the JSON API (2–3 weeks)

This is the piece of real engineering. The engines already return arrays; the
work is exposing them cleanly and stably.

```
POST /api/v1/chart            birth details            → chart, vargas, houses, balas
GET  /api/v1/chart/{id}/predictions?view=general|dasha|yoga|…
GET  /api/v1/chart/{id}/lalkitab?view=nichod|planet|…
GET  /api/v1/chart/{id}/varshaphal?year=
GET  /api/v1/chart/{id}/lalkitab/varsh?age=
GET  /api/v1/chart/{id}/gochar?date=&lat=&lon=&tz=
POST /api/v1/milan            two births              → guna + lal kitab
GET  /api/v1/muhurat?from=&to=&type=
GET  /api/v1/cities?q=        (exists today as calc/citySearch)
```

Rules that keep this honest:
- **The engines are not touched.** The API is a thin serialiser over
  `CalculationEngine`, `LalKitabEngine`, `LalKitabProcess`, `GunaMilan`,
  `LalKitabMilan`, the muhurat engines. Any calculation change must still show
  up on the website identically.
- **Version the path** (`/api/v1/`) from day one.
- **The self-check harness extends to it.** Today's 62 checks read rendered
  HTML; add checks that call the API and assert the same verdicts, so the two
  faces can never drift.
- Cache computed charts server-side by a hash of the birth details — the same
  chart is requested many times.

## 7. Phase 3 — Flutter app (6–10 weeks)

- Screens: birth entry (with the offline gazetteer), chart viewer (D1–D60 +
  rotate), predictions, Lal Kitab (8 groups), dasha timeline, varshaphal,
  gochar, milan, muhurat, saved charts.
- **Charts:** re-implement `northchart.js` on `CustomPainter`. It is one
  well-understood file — geometry, twelve houses, planet placement, the AV/BB
  ring. Two to three days, and it removes the last reason to keep a WebView.
- **Offline cache:** SQLite (Drift). Every chart the app has ever computed is
  readable with no net. This is the "offline" most astrologers actually want —
  their own client list, in their pocket, on a plane.
- Hindi typography: bundle Mukta; do not rely on the device font.
- Keep the WebView for two or three rarely-used pages if parity is taking too
  long — that is a legitimate shortcut, not a defeat.

## 8. Phase 4 — on-device calculation (optional, 8–14 weeks)

Only if you want a chart cast with the phone in flight mode.

- Port to Dart: `JulianDay`, `AnalyticEphemeris`, `Charts`, `Varga`,
  `VimshottariDasha`, `Drishti`, `LalKitabTeva` + the Lal Kitab dasha. Bundle
  `lalkitab_data.json` and export the MySQL text banks to JSON assets.
- **Correctness programme, non-negotiable:** generate a fixture set of a few
  thousand random births, compute every value in PHP, ship it as a test asset,
  and fail the Dart build if any planet differs by more than an arc-second or
  any verdict word differs at all. Two implementations of one astrology is the
  fastest way to lose trust; the fixtures are what stop it.
- Swiss Ephemeris on device is possible (NDK) but not worth it at first — the
  analytic ephemeris is 1–2 arc-minutes, which does not move a rashi or a house.

---

## 9. Play Store checklist (do not leave to the end)

- Privacy policy URL, reachable, naming birth data.
- **Data safety form** — birth date, time and place are personal data. Declare
  collection, purpose, and whether it leaves the device.
- Content rating questionnaire.
- Target API level (Play enforces a recent one; check at submission).
- Signed AAB, Play App Signing.
- Screenshots: phone + 7" + 10", plus a feature graphic.
- If money is ever taken in-app, Play Billing is mandatory — plan pricing
  outside the app if you want to avoid the 15–30% cut.
- The Minimum Functionality policy — Route A alone risks rejection; Route B with
  offline + saved charts + push does not.

## 10. Risks, ranked by how likely they are to actually bite

1. **The 2.2 MB page.** Ship an app around it and every user feels it on day
   one. Fix in Phase 0.
2. **Two implementations drifting** (Phase 4). Fixtures or don't do it.
3. **Play rejection for a thin wrapper.** Avoided by Phase 1 being a real PWA.
4. **Shared hosting under app load.** A chart is CPU-heavy; A2 shared hosting
   has limits. Server-side caching in Phase 2 is the mitigation; a small VPS is
   the fallback.
5. **Hindi rendering on old Androids** — bundle the font, test on Android 8.
6. **Birth-data privacy.** Once charts sync to a server they are personal
   records. Decide early: device-only, or server with an account and deletion.

## 11. Effort at a glance

| Phase | What you get | Effort |
|---|---|---|
| 0 | Faster site, no CDN, token auth | 1–2 weeks |
| 1 | Installable app on Play Store | 1–2 weeks |
| 2 | `/api/v1` + API self-checks | 2–3 weeks |
| 3 | Flutter app, native charts, offline cache | 6–10 weeks |
| 4 | Calculation on the phone | 8–14 weeks |

Phases 0+1 = **a real app in about a month**. Phases 0–3 = a proper product in
about four months. Phase 4 only if true offline casting is the goal.

## 12. What I can start on immediately, in this repo

In rough order of value:

1. Split the 2.2 MB page into on-demand fragments (Phase 0.1).
2. Vendor Tailwind + fonts (Phase 0.2) — this also finishes the offline work we
   started with the gazetteer.
3. `manifest.webmanifest` + service worker + offline page (Phase 1).
4. `/api/v1/chart` and `/api/v1/chart/{id}/lalkitab` as the first two endpoints,
   with self-checks that compare them against the rendered page.

Say which one to take first. My own order would be 1, 2, 3 — because those three
improve the website you already have, whether or not the app is ever built.
