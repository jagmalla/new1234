# लाल किताब भविष्यवाणी — पुनर्निर्माण योजना (Plan only — nothing built yet)

Prepared against your rules document *"लाल किताब भविष्यवाणी — संपूर्ण नियम, ग्रामर व शब्दावली"* (16 sections, 15-step process, decision flowchart).

---

## PART 1 — HONEST AUDIT: why the current output disappoints

I checked the actual code. The problem is **not** missing data — your 39-sheet dataset is rich and mostly unused. The problem is that the engine's *reasoning model is wrong for Lal Kitab*.

### Root cause 1 — Flat scoring instead of diagnostic classification
Current engine computes `verdict = sum of + / − points` → शुभ / मध्यम / अशुभ.
Lal Kitab does not grade a planet on a scale. It **classifies**: कायम / पीड़ित / सोया — and the *reason* for the class is the prediction. A "+2 शुभ" tells the user nothing actionable.

### Root cause 2 — No causal chain
The doc's entire method is a chain: *घर कच्चा → ग्रह पीड़ित → भाव तालाबंद → चाबी ग्रह → उपाय*.
Current output prints independent facts (house, verdict, remedy list) with no "इसलिए". That is exactly what reads as confusing.

### Root cause 3 — Remedy shotgun
Every afflicted planet currently dumps its whole `bhavgat_upay` list — often 10–15 bullets, sometimes strengthening and pacifying the same planet. Your doc explicitly forbids this (⚠️ "परस्पर-विरोधी उपाय न करें") and says remedies must target the **चाबी वाला ग्रह**, not the symptom.

### Root cause 4 — Organised by planet/house, not by life question
Users ask about धन, विवाह, संतान, नौकरी, रोग. The current 20+ dropdown views are organised by *astrological object*, forcing the user to assemble the answer themselves.

### Root cause 5 — Most of the doc's grammar is simply absent
| Doc concept | Status in code |
|---|---|
| स्थिर द्वादश-भाव चार्ट | ✅ correct |
| पक्के घर (1,2,3,4,5,7,9,11) / कच्चे (6,8,10,12) | ❌ **absent** — code only has *per-planet* pakka ghar (Sun→1, Moon→4, Mars→3,8…), a different concept |
| कायम ग्रह | ❌ absent as a state |
| सोया ग्रह / सोया घर | ⚠️ partial — uses `supt_bhav` waker table, not the doc's "आसपास खाली भाव" rule |
| ग्रह-फल बनाम राशि-फल प्रधानता | ❌ absent |
| मसनूई ग्रह (कृत्रिम) | ❌ absent |
| साथी ग्रह — पीड़ा साझा करना | ⚠️ house-mates listed, but affliction is **not** propagated |
| बलि का बकरा | ❌ absent |
| किस्मत जगाने वाला ग्रह | ❌ absent |
| मुकाबले के ग्रह | ❌ absent |
| दृष्टि 100% / 50% / 25% | ❌ absent (aspects are boolean) |
| बुनियाद / टक्कर / धोखा | ⚠️ data exists (`neev`, `takrav`, `vishwasghat`) but is only used as ± points, never classified |
| अंधी / आधी अंधी / धर्मी / नाबालिग कुंडली | ❌ absent |
| ताला और चाबी | ❌ **absent — biggest single gap**, it is what makes remedies correct |
| ग्रहों की मियाद (उम्र तालिका) | ⚠️ conflicting system in use (see Q-A2) |
| वर्षफल overlay | ⚠️ different method in use (see Q-A4) |
| रस्साकशी — मिश्रित फल स्पष्ट कहना | ❌ absent |
| उपाय: कारण → दिशा | ❌ absent |

**Bottom line:** roughly 12 of the doc's ~18 core rules are not implemented, and the 6 that are, are wired into a scoring model the tradition doesn't use. That is why it feels generic.

---

## PART 2 — THE FIX IN ONE SENTENCE

Replace the additive scorer with a **diagnostic pipeline that mirrors your 15-step flowchart**, where every sentence carries *क्या → क्यों → कब → क्या करें*, and remedies are resolved to a **single non-contradictory direction per planet**, focused on the चाबी ग्रह.

---

## PART 3 — MODULE DESIGN (mapped to your doc's steps)

| # | Module | Doc step | What it decides |
|---|---|---|---|
| **M1** | `ChartFoundation` | 1–3 | Fixed chart, occupants, **empty houses**, पक्का/कच्चा house class, per-planet pakka-ghar flag |
| **M2** | `GrahaState` | 4 | Each planet → exactly **one** state: **कायम / पीड़ित / सोया**, with an evidence list (paap-yuti, paap-drishti, कच्चा घर, शत्रु राशि, नीच, आसपास खाली) |
| **M3** | `BhavState` | 4 | Each house → जागृत / सोया, and खुला / तालाबंद |
| **M4** | `PhalWeighting` | 5 | Whether to speak in **ग्रह-फल** (own sign / own pakka ghar / pakka house) or **भाव-फल** (कच्चा घर / पराई राशि) language — decides the *wording* of every sentence |
| **M5** | `YutiTypes` | 6 | साथी (+ **propagate** affliction between house-mates), मसनूई (कृत्रिम ग्रह), मुकाबले के ग्रह |
| **M6** | `DrishtiEngine` | 7 | Uses `neev`/`takrav`/`vishwasghat` properly; assigns **100/50/25%** strength; classifies each aspect as **बुनियाद / टक्कर / धोखा** |
| **M7** | `KundaliType` | 8 | अंधी / आधी अंधी / धर्मी / नाबालिग → sets a **reading mode** that changes confidence + wording (e.g. अंधी ⇒ "D1 राशि-कुंडली से क्रॉस-चेक आवश्यक", नाबालिग ⇒ फल आंशिक/विलंबित) |
| **M8** | `BakraAndKismat` | 9 | बलि का बकरा (protect it — **never** remove its remedy) and किस्मत जगाने वाला ग्रह |
| **M9** | `TalaChabi` | 10 | For each blocked benefic/pakka house: **which planet is the ताला**, **which is the चाबी** → this is what remedies target |
| **M10** | `Miyaad` | 11 | Current-age active planet; reconciles the three age systems (see Q-A2) |
| **M11** | `VarshphalOverlay` | 11 | For the chosen year: **which सोया planet wakes**, **which कायम planet becomes पीड़ित** → that year's headline events |
| **M12** | `PaapiContext` + `Rassakashi` | 12–13 | Rahu mirrors its companion; Ketu by house; Saturn कच्चा=विलंब / पक्का=न्यायाधीश. Tug-of-war ⇒ **state mixed result openly**, no fake certainty |
| **M13** | `PredictionComposer` | 14 | Per **life-area**, a 4-part statement: **क्या होगा → क्यों (cause chain) → कब (मियाद/वर्षफल) → क्या करें** |
| **M14** | `RemedyResolver` | 15 | कारण → दिशा map; dedupe; **conflict-check**; वर्जित-check; **max 3–5** prioritised actions with वार + 43-day window |
| **M15** | `EvidenceTrace` | — | Every statement carries a "क्यों" chip naming the rule + data sheet it came from (this is what converts "confusing" into "trustworthy", and is how we debug) |

### M14 — the remedy decision table (the heart of the fix)
| कारण (detected by) | दिशा | उपाय focus |
|---|---|---|
| पाप-युति / पाप-दृष्टि | **शांत करें** | दान / शमन of the malefic |
| कच्चा घर | **भाव-स्थापना** | house-establishment (`bhav_sthapana` + `sthapana_vastu`) |
| सोया ग्रह / सोया घर | **जगाएँ** | जागृति-उपाय only (goal: remove delay, not create new result) |
| भाव तालाबंद | **चाबी पर केंद्रित** | remedy the **key planet**, not the house |
| रस्साकशी | **पहले सक्रिय ग्रह पर** | whichever planet's मियाद comes first |
| बलि का बकरा | **⚠️ छेड़ें नहीं** | protect the shield; warn explicitly |
| कायम पर कमज़ोर शुभ | **बल दें** | strengthening remedy |

---

## PART 4 — OUTPUT / UX REDESIGN (fixing "confusing")

**One main report — `लाल किताब निष्कर्ष`** — in the doc's own order:

- **A. कुंडली का प्रकार** + reading-mode caution (अंधी/धर्मी/नाबालिग)
- **B. ग्रह-दशा एक नज़र में** — three columns: **कायम** ✅ | **पीड़ित** ⚠️ | **सोया** 😴 (with the one-line reason each)
- **C. निदान (Diagnosis)** — ताला–चाबी map · किस्मत जगाने वाला ग्रह · बलि का बकरा · रस्साकशी
- **D. जीवन-क्षेत्र फल** — 10 areas (धन · विवाह · संतान · नौकरी/व्यवसाय · रोग · शिक्षा · मुकदमा/शत्रु · विदेश · भाग्य/पिता · माता/सुख/भूमि), each as **क्या → क्यों → कब → क्या करें**
- **E. उपाय योजना** — max 5, prioritised, conflict-free, with वार + 43-day calendar
- **F. नियम-पालन चेकलिस्ट** — your 15 steps with ✓ each, proving the process actually ran

The existing 20 views stay, but move under **"विस्तृत / संदर्भ"** so the default screen is one clear answer instead of a menu.

---

## PART 5 — BUILD PHASES

| Phase | Contents | Visible gain |
|---|---|---|
| **P0** | M1 · M2 · M3 · M4 · M15 | Correct foundation: कायम/पीड़ित/सोया + पक्का/कच्चा + "क्यों" on every line |
| **P1** | M6 · M5 · M12 | Real दृष्टि (बुनियाद/टक्कर/धोखा, %), साथी-पीड़ा, मसनूई, रस्साकशी |
| **P2** | M7 · M8 · M9 | कुंडली प्रकार, बलि का बकरा, किस्मत-ग्रह, **ताला–चाबी** |
| **P3** | M13 · M14 + new report UI | The actual readable answer + correct, few, non-contradictory remedies |
| **P4** | M10 · M11 | Age system reconciled; Varshphal overlay (touches the Age Timeline & Varsh Kundali already built) |
| **P5** | Validation & calibration | Accuracy proven against real charts |

> P0–P3 alone deliver ~80% of the improvement. P4 revisits work already shipped, so I'd like your answers on Q-A2/A4 before touching it.

---

## PART 6 — VALIDATION (the lesson from the Politics tab)

When we built the Politics engine, yoga-counting *looked* right but could not separate real leaders from failures until we tested it against 13 real people. I do not want to repeat that mistake here.

**Proposal:** before P3 ships, run 8–12 real charts with known life facts through the pipeline and compare output to reality, then publish a scorecard (hit / miss / vague). If it doesn't beat the current version measurably, we fix it before release, not after.

---

## PART 7 — QUESTIONS FOR YOU

### 🔴 Group A — Rule conflicts I cannot resolve alone (these block correct work)

**A1. पक्का घर — which definition?**
Your doc: houses **1,2,3,4,5,7,9,11** are पक्के, 6,8,10,12 कच्चे (house-number based).
Your dataset: **per-planet** pakka ghar (Sun→1, Moon→4, Mars→3&8, Mercury→7, Jupiter→9, Venus→7, Saturn→10, Rahu→12, Ketu→6).
→ Use **both** as separate flags? And when they conflict (e.g. Saturn in its own pakka ghar 10, which is a कच्चा house), **which wins**?

**A2. उम्र/मियाद — three systems now exist. Which is authoritative?**
- (a) **Doc's table:** सूर्य 0–1, चन्द्र 1–4, मंगल 4–7, राहु 7–9, गुरु 9–16, शनि 16–36, बुध 36–48, केतु 48–56, शुक्र 56+
- (b) **Your xlsx `avastha`:** 4 अवस्थाएँ × 25 वर्ष, mapped to house-groups 1-3 / 4-6 / 7-9 / 10-12 *(this is what the Age Timeline currently uses)*
- (c) **Your xlsx `grah_chakra`:** per-planet प्रभाव / अशुभ / विशेष वर्ष
→ **My proposal:** (b) = महादशा frame, (a) = अन्तर्दशा / "अभी कौन ग्रह चार्ज में है", (c) = event spikes. **Confirm or correct.**

**A3. सोया ग्रह — which definition?**
Doc: "आसपास के भाव खाली". Your dataset `supt_bhav`: each house has a specific **जगाने वाला ग्रह** who must be present.
→ Use the doc's rule, your dataset's rule, or **either one triggers सोया**?

**A4. वर्षफल — which construction method?**
I currently rotate the janam chart through the `varsh_gyan` per-age permutation. Your doc instead describes an **overlay**: take the janam कायम/सोए planets, then see which सोया planet wakes / which कायम planet is afflicted that year.
→ Is the `varsh_gyan` permutation from your book (i.e. keep it as the *chart*) and I add the overlay as the *reading*? Or should the overlay replace the rotation? **Also:** should the annual chart use the year's **actual transit positions**, or only the permutation?

### 🟠 Group B — Rules your doc names but doesn't fully define

**B1. मसनूई ग्रह** — only सूर्य+शनि → "कृत्रिम राहु" is given as an example. I need the **full combination table** (which yuti creates which artificial planet). Do you have it?

**B2. धोखा दृष्टि** — doc says it "अनुभव माँगता है". I need a computable rule.
→ *My proposal:* an aspect that is सहायक/नीव by the table **but** the aspecting planet is itself पीड़ित or a शत्रु of the house-lord ⇒ धोखा. Acceptable?

**B3. दृष्टि 100 / 50 / 25%** — I need the **house-distance → percentage** mapping (which aspect is full, which half, which quarter).

**B4. बलि का बकरा** — need an identification rule.
→ *My proposal:* a पीड़ित malefic sitting in a कच्चा घर that absorbs affliction otherwise reaching a कायम शुभ ग्रह or the lagna. Acceptable?

**B5. किस्मत जगाने वाला ग्रह** — *my proposal:* the strongest **कायम शुभ** planet connected to house 9, 10 or 11 by placement or दृष्टि. Confirm?

**B6. अंधी कुंडली threshold** — how many occupied houses = अंधी?
→ *My proposal:* ≤3 occupied ⇒ अंधी, 4–5 ⇒ आधी अंधी. Your call.

**B7. मुकाबले के ग्रह** — do you have a co-karak table (which planets compete for which topic), or should I derive it from `graha_parichay.karak_bhav` overlap + `maitri` शत्रु status?

### 🟢 Group C — What I need FROM you (most important for quality)

**C1. 8–12 real charts with known life facts** — birth date/time/place **plus** what actually happened (marriage year, job changes/losses, major illness, financial peaks/crashes, foreign travel, litigation). This is the single most valuable thing you can give me. Without it I am guessing; with it I can calibrate and *prove* accuracy.

**C2. 2–3 "gold standard" readings** — for any of those charts, a reading written by a Lal Kitab expert or copied from a book, so I can match the expected **tone, depth and conclusions** rather than inventing a style.

**C3. Which book/edition is your 39-sheet xlsx from?** Your doc itself warns that traditions differ on मसनूई, मियाद and दृष्टि-%. If I know the source tradition, I resolve every future conflict by that book instead of asking you again.

### 🔵 Group D — Scope & presentation decisions

**D1. Priority** — build all 15 modules, or start with the highest-impact set (**M1–M4 + M9 ताला-चाबी + M13–M14**) which I estimate gives ~80% of the improvement?

**D2.** Should the new **निष्कर्ष report replace the default view**, with today's 20 views moved to "विस्तृत/संदर्भ"?

**D3. Language** — keep Hindi-primary (as now), or bilingual Hindi + English?

**D4. The शुभता % bar** — Lal Kitab's logic is *classification* (कायम/पीड़ित/सोया), not a percentage. Should I **drop the numeric %** in the Lal Kitab tabs (keep it only in मुहूर्त/गोचर where it fits), since an arbitrary-looking number may be part of what feels confusing?

---

## PART 8 — WHAT I CAN DO WITHOUT ANY ANSWERS

If you'd rather I just start, these are unblocked and safe today:
- **M1–M4** (पक्का/कच्चा houses, कायम/पीड़ित/सोया classification, ग्रह-फल vs भाव-फल wording, evidence trace) — using the doc's definitions, with A1/A3 defaults clearly marked in the UI so you can correct them later
- **M9 ताला–चाबी** and **M14 RemedyResolver** — these need no disputed data and fix the worst problem (remedy shotgun)
- **M13 life-area composer** + the new report layout

Everything else waits on Group A/B answers.

---

*Nothing has been built for this plan. No code changed.*
