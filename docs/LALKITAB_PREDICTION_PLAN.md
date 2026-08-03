# लाल किताब भविष्यवाणी — पुनर्निर्माण योजना **v2**

Rewritten against your **updated** rules guide (v2.1): 28 sections, **19-step** process, **18-point** checklist.
*Plan only — no code has been written.*

---

## PART 0 — WHAT v2.1 ADDED (and why it changes the plan)

Your new document adds **12 sections** that were not in v1. Crucially, most of them come with **concrete tables**, which means they are *directly implementable* — unlike the earlier abstract rules.

| New section | Implementable now? | Why it matters |
|---|---|---|
| **ऋण** — पितृ / मातृ / स्त्री / भ्रातृ / कन्या | ✅ **Yes — rules are explicit** (सूर्य/गुरु+राहु, चन्द्र+राहु/शनि, शुक्र+राहु/केतु/शनि, मंगल पीड़ित, 5वाँ+गुरु दोनों पीड़ित) | Explains *why* remedies underperform: "जब तक ऋण की पहचान न हो, सामान्य उपाय भी अधूरा फल देते हैं" |
| **शाप** — सर्प / पितृ / नारी / गुरु-चांडाल | ✅ **Yes** (राहु-केतु अक्ष 5/8, सूर्य-9वाँ, शुक्र/चन्द्र, गुरु-राहु युति) | Deep-cause layer above ordinary affliction |
| **ग्रह अपंगता** — गूंगा/बहरा/लंगड़ा/अंधा/उग्र | ⚠️ Mostly — needs thresholds | Explains *degree* of affliction, not just yes/no |
| **मकान अपंगता** — गूंगा/बहरा/लंगड़ा मकान | ⚠️ Mostly | House-level severity |
| **मृत ग्रह** | ⚠️ Needs AND/OR rule | The extreme case — karakatva absent entirely |
| **जाति · रंग · लिंग तालिका** (9 ग्रह) | ✅ **Yes — full table given** | Drives correct remedy *item, colour, and the स्त्री/पुरुष-ग्रह respect rule* |
| **वास्तु तालिका** (12 भाव → दिशा → ग्रह) + कोण, ब्रह्मस्थान, कुआँ, तुलसी, द्वार | ✅ **Yes — full table given** | Links an afflicted house to a real-world fix |
| **अनाज/खाद्य दान तालिका** (9 ग्रह → वस्तु → दिन) | ✅ **Yes — full table given** | Turns vague remedies into precise prescriptions |
| **वृक्ष उपाय** (पीपल/बरगद/नीम/तुलसी) | ✅ **Yes** | Adds the classic LK remedy class |
| **सवा नौ / संख्या-टोटके** (1.25×, 43 दिन, 11 बुधवार, 7 मंगलवार, शनि=8, राहु=18) | ✅ Yes (partial — need full number table) | Quantity + duration grammar |
| **खानदानी असर · पड़ोसी भाव** | ⚠️ Padosi = yes · Khandani = needs input | Adjacent-house support/weakening; family-wide remedies |
| **गोचर के LK विशेष नियम** · **राशि परिवर्तन** | ✅ Yes | LK transit ≠ Parashari transit — affects the existing Gochar tab |

**Net effect on the plan:** the module count grows from 15 → **25**, but the *risk* drops sharply — because roughly half the new modules ship from tables you've now supplied, with no interpretation needed from me.

**✅ Questions from my last plan that v2.1 has now ANSWERED (no longer need your input):**
grain/food + day per planet · planet colour · planet जाति/लिंग · vastu direction per house · corner rules · tree remedies · सवा concept + 43-day/11-Wed/7-Tue cycles · ऋण detection rules · शाप detection rules · adjacent-house rule · LK-gochar principle · rashi-parivartan principle.

---

## PART 1 — THE DIAGNOSIS (unchanged, and now even clearer)

The current engine's failure is structural, not cosmetic:

1. **Flat +/− scoring** produces शुभ/मध्यम/अशुभ. Lal Kitab **classifies** (कायम / पीड़ित / सोया / अपंग / मृत) — and the *class + reason* IS the prediction.
2. **No causal chain.** Your doc is one long chain (कच्चा घर → पीड़ित ग्रह → ऋण → तालाबंद भाव → चाबी ग्रह → उपाय). The code prints disconnected facts.
3. **Remedy shotgun** — 10–15 bullets per planet, sometimes strengthening *and* pacifying the same planet, never targeting the चाबी ग्रह, never using the correct अनाज/रंग/दिन/मात्रा.
4. **Organised by planet, not by life question.**
5. **~24 of your ~30 rules are absent**, and the 6 present are wired into the wrong model.

v2.1 makes point 3 especially glaring: you have given exact grain, colour, day, quantity, duration, tree and vastu tables — the remedy engine should be *prescription-grade*, and today it is a text dump.

---

## PART 2 — MODULE ARCHITECTURE (25 modules in 6 layers, mapped to your 19 steps)

### 🏗️ Layer 1 — Foundation (चरण 1–5)
| Module | Decides |
|---|---|
| **L1.1 ChartFoundation** | Fixed 12-house chart · occupants · **empty houses** · पक्का (1,2,3,4,5,7,9,11) / कच्चा (6,8,10,12) · per-planet pakka-ghar flag |
| **L1.2 GrahaState** | Each planet → **कायम / पीड़ित / सोया** + evidence (पाप-युति, पाप-दृष्टि, कच्चा घर, शत्रु राशि, नीच, आसपास खाली) |
| **L1.3 BhavState** | Each house → जागृत/सोया · खुला/तालाबंद |
| **L1.4 PhalWeighting** | **ग्रह-फल** vs **भाव-फल** dominance (own sign / पक्का घर ⇒ ग्रह-फल; कच्चा घर / पराई राशि ⇒ भाव-फल) — decides the *wording of every sentence* |

### 🔗 Layer 2 — Relations & Aspects (चरण 6–7, 16)
| Module | Decides |
|---|---|
| **L2.1 YutiTypes** | साथी (**propagates** affliction between house-mates) · मसनूई (कृत्रिम ग्रह) · मुकाबले के ग्रह |
| **L2.2 DrishtiEngine** | **बुनियाद / टक्कर / धोखा** classification + **100 / 50 / 25 %** strength (uses your existing `neev`, `takrav`, `vishwasghat` columns properly) |
| **L2.3 PadosiBhav** 🆕 | Adjacent-house support/weakening — strong neighbour = बुनियाद, weak neighbour = drag |

### 🧭 Layer 3 — Chart-level diagnosis (चरण 8–10)
| Module | Decides |
|---|---|
| **L3.1 KundaliType** | अंधी / आधी अंधी / धर्मी / नाबालिग → sets a **reading mode** (अंधी ⇒ "D1 राशि-कुंडली से क्रॉस-चेक"; नाबालिग ⇒ फल आंशिक/विलंबित) |
| **L3.2 BakraAndKismat** | बलि का बकरा (**protect — never remove**) · किस्मत जगाने वाला ग्रह |
| **L3.3 TalaChabi** | Per blocked house: **ताला** (which planet blocks) → **चाबी** (which unlocks) — *this is what remedies target* |

### 🩸 Layer 4 — Special afflictions 🆕 (चरण 14–15)
| Module | Decides |
|---|---|
| **L4.1 RinEngine** 🆕 | **पितृ** (सूर्य/गुरु + राहु) · **मातृ** (चन्द्र + राहु/शनि) · **स्त्री** (शुक्र + राहु/केतु/शनि) · **भ्रातृ** (मंगल पीड़ित) · **कन्या** (5वाँ भाव **और** गुरु दोनों पीड़ित) — each with its life-symptom text |
| **L4.2 ShrapEngine** 🆕 | **सर्प** (राहु-केतु अक्ष पीड़ित, विशेषकर 5/8) · **पितृ** (सूर्य / 9वाँ पीड़ित) · **नारी** (शुक्र/चन्द्र पीड़ित) · **गुरु-चांडाल** (गुरु-राहु युति) |
| **L4.3 GrahaApangata** 🆕 | **गूंगा** (चारों ओर पाप, कोई शुभ दृष्टि नहीं) · **बहरा** (उपाय/शुभ दृष्टि ग्रहण नहीं) · **लंगड़ा** (कच्चा घर + कमज़ोर ⇒ विलंब) · **अंधा** (दिशाहीन ⇒ उल्टा फल) · **उग्र** (सघन क्रूर युति ⇒ दुर्घटना-प्रवृत्ति) |
| **L4.4 BhavApangata** 🆕 | गूंगा / बहरा / लंगड़ा **मकान** — house-level severity, distinct from सोया घर |
| **L4.5 MritGraha** 🆕 | Extreme case (गूंगा + बहरा + अंधा) ⇒ karakatva treated as **absent** unless a पुनर्जीवन-उपाय is done |

> **Why this layer matters most:** it is the honest answer to "why do the remedies not work?" — an ऋण or a मृत ग्रह must be addressed *before* ordinary टोटके can bite.

### ⏳ Layer 5 — Timing & context (चरण 11–13, 16)
| Module | Decides |
|---|---|
| **L5.1 Miyaad** | Age → currently "in-charge" planet (needs Q-A2 resolved) |
| **L5.2 VarshphalOverlay** | For a year: **which सोया planet wakes**, **which कायम planet becomes पीड़ित** ⇒ that year's headline |
| **L5.3 GocharLK** 🆕 | LK-style transit: read the transiting planet against the **fixed house's** पक्का/कच्चा + कायम/सोया state — *not* rashi-based gochar |
| **L5.4 RashiParivartan** 🆕 | Use the planet's **current** sign influence in transit/varshphal, not only natal |
| **L5.5 PaapiContext** | राहु mirrors its companion · केतु by house (9/12 ⇒ मोक्ष-कारक) · शनि कच्चा=विलंब / पक्का=न्यायाधीश |
| **L5.6 Rassakashi** | Tug-of-war ⇒ **state the mixed result openly**; resolution follows whichever planet's मियाद arrives first |
| **L5.7 KhandaniPattern** 🆕 | Repeated family patterns ⇒ recommend **family-wide** remedy (needs optional input — see Q-E2) |

### 📜 Layer 6 — Output & remedy (चरण 17–19)
| Module | Decides |
|---|---|
| **L6.1 GrahaIdentity** 🆕 | जाति · रंग · लिंग per planet ⇒ correct donation colour/item; **स्त्री-ग्रह पीड़ित ⇒ घर की स्त्रियों का सम्मान**, पुरुष-ग्रह ⇒ पुरुष-संबंधी |
| **L6.2 VastuLink** 🆕 | Afflicted house → direction → real-world check (रसोई/शौचालय/ब्रह्मस्थान/मुख्य द्वार/जल-स्रोत/तुलसी); symbolic remedy where structural change isn't possible |
| **L6.3 PredictionComposer** | Per **life-area**: **क्या होगा → क्यों (full cause chain) → कब (मियाद/वर्षफल) → क्या करें** |
| **L6.4 RemedyResolver** | The prescription engine — see below |
| **L6.5 EvidenceTrace** | Every statement carries a "क्यों" chip naming the rule + data sheet (turns "confusing" into "verifiable", and is how we debug) |

---

## PART 3 — THE REMEDY ENGINE (biggest single quality jump)

### 3a. कारण → दिशा (direction is decided first, exactly once per planet)
| कारण detected | दिशा | Focus |
|---|---|---|
| पाप-युति / पाप-दृष्टि | **शांत करें** | दान / शमन |
| कच्चा घर | **भाव-स्थापना** | `bhav_sthapana` + `sthapana_vastu` |
| सोया ग्रह / सोया घर | **जगाएँ** | जागृति-उपाय only (reduce delay, not create new result) |
| भाव तालाबंद | **चाबी पर** | remedy the **key planet**, never the house |
| रस्साकशी | **पहले सक्रिय ग्रह पर** | whichever मियाद comes first |
| **ऋण / शाप** 🆕 | **रिश्ता-सेवा + शमन** | ⚠️ दान-टोटका alone is explicitly insufficient |
| **अपंग / मृत ग्रह** 🆕 | **पुनर्जीवन** | heavy, long-cycle remedy; set expectation of slow effect (बहरा ⇒ warn effect will be weak) |
| बलि का बकरा | **⚠️ छेड़ें नहीं** | protect the shield; warn explicitly |
| कायम पर कमज़ोर शुभ | **बल दें** | strengthening |

### 3b. Each prescription is then *composed* from your tables (not free text)
> **ग्रह** → **दिशा** → **अनाज/वस्तु** (grain table) → **रंग** (जाति-रंग table) → **दिन** (grain table) → **मात्रा** (सवा 1.25×) → **अवधि** (43 दिन / 11 बुधवार / 7 मंगलवार) → **वृक्ष-उपाय** (if applicable) → **वास्तु-सुधार** (if that direction is affected) → **रिश्ता-सेवा** (if ऋण/शाप) → **वर्जित-check** (existing `varjit` / `daan_nishedh` data)

**Example of the intended output shape** (illustrative — real values come from the chart):
> **शनि — दिशा: शांत करें** *(कारण: 10वें कच्चे घर में, राहु से टकराव)*
> उड़द दाल व काले तिल **सवा किलो**, **शनिवार**, 43 दिन · काला/नीला वस्त्र · पीपल को शनिवार जल (जड़ में तेल नहीं) · **वास्तु:** 10वाँ भाव = दक्षिण — रसोई/भारी सामान की जाँच करें · ⛔ वर्जित: (auto-checked)

**Hard rules enforced by the resolver:** max 3–5 actions · never strengthen + pacify the same planet · never touch a बलि-का-बकरा · always prefer the चाबी ग्रह · ऋण/शाप always adds the relationship-service line.

---

## PART 4 — OUTPUT / UX (fixing "confusing")

**One report — `लाल किताब निष्कर्ष`** — in your doc's own order:

- **A. कुंडली का प्रकार** + reading-mode caution (अंधी / धर्मी / नाबालिग)
- **B. ग्रह-दशा एक नज़र में** — **कायम** ✅ | **पीड़ित** ⚠️ | **सोया** 😴 | **अपंग/मृत** ⛔ with a one-line reason each
- **C. 🆕 ऋण व शाप** — which are present, their life-symptoms, and the relationship-service requirement
- **D. निदान** — ताला–चाबी map · किस्मत जगाने वाला ग्रह · बलि का बकरा · रस्साकशी
- **E. जीवन-क्षेत्र फल** — 10 areas (धन · विवाह · संतान · नौकरी/व्यवसाय · रोग · शिक्षा · मुकदमा/शत्रु · विदेश · भाग्य/पिता · माता/सुख/भूमि), each **क्या → क्यों → कब → क्या करें**
- **F. उपाय योजना** — max 5, prioritised, conflict-free, with अनाज/रंग/दिन/मात्रा/अवधि + वार calendar
- **G. 🆕 वास्तु जाँच** — afflicted houses → directions to inspect at home
- **H. नियम-पालन चेकलिस्ट** — your **18** checklist points with ✓, proving the process ran

Today's 20 views stay, demoted to **"विस्तृत / संदर्भ"**, so the default screen is one clear answer.

---

## PART 5 — BUILD PHASES

| Phase | Contents | Gain |
|---|---|---|
| **P0** | L1.1–L1.4 + L6.5 EvidenceTrace | Correct foundation: कायम/पीड़ित/सोया + पक्का/कच्चा + "क्यों" on every line |
| **P1** 🆕 | **L4.1 ऋण · L4.2 शाप** + L6.1 GrahaIdentity | Deep-cause layer + correct remedy items/colours — **highest value per effort** (rules are explicit, no guesswork) |
| **P2** | L3.3 ताला-चाबी · L6.4 RemedyResolver · L6.3 Composer + new report | The readable answer + prescription-grade remedies |
| **P3** | L2.1–L2.3 · L3.1 · L3.2 | दृष्टि grammar, मसनूई/साथी/मुकाबले, कुंडली प्रकार, बकरा/किस्मत |
| **P4** 🆕 | L4.3–L4.5 अपंगता/मृत ग्रह · L6.2 VastuLink | Severity grading + real-world vastu fixes |
| **P5** | L5.1–L5.7 timing (Miyaad, Varshphal, **GocharLK**, RashiParivartan, Khandani) | Revisits the Age Timeline / Varsh Kundali / Gochar tabs already shipped |
| **P6** | Validation & calibration | Accuracy proven, not assumed |

> **My recommendation: P0 → P1 → P2 first.** That trio alone converts the output from "generic" to "diagnostic + prescriptive", and P1 is now cheap because your ऋण/शाप/रंग/अनाज rules are explicit.

---

## PART 6 — VALIDATION (non-negotiable, from the Politics lesson)

When we built the Politics engine, yoga-counting *looked* correct but could not distinguish real leaders from failures until tested against 13 real people — and one "improvement" made it measurably worse. I won't repeat that.

**Before P2 ships:** run 8–12 real charts with known life events, publish a **hit / miss / vague scorecard**, and only release if it beats the current version measurably.

---

## PART 7 — QUESTIONS

### 🔴 Group A — Conflicts I cannot resolve alone *(still open — these block correct work)*
- **A1. पक्का घर** — your doc: houses **1,2,3,4,5,7,9,11**. Your dataset: **per-planet** pakka ghar (सूर्य→1, चन्द्र→4, मंगल→3&8, बुध→7, गुरु→9, शुक्र→7, शनि→10, राहु→12, केतु→6). Use both as separate flags? **Which wins on conflict** (e.g. शनि in its own pakka ghar 10, which is a कच्चा house)?
- **A2. मियाद** — three systems: **(a)** doc's table (सूर्य 0–1 … शुक्र 56+), **(b)** your xlsx `avastha` 4×25 yr *(what the Age Timeline uses today)*, **(c)** `grah_chakra` प्रभाव/अशुभ years. *My proposal:* (b)=महादशा frame, (a)=अन्तर्दशा/"अभी चार्ज में", (c)=event spikes. **Confirm?**
- **A3. सोया ग्रह** — doc: "आसपास के भाव खाली"; your dataset `supt_bhav`: a specific **जगाने वाला ग्रह** must be present. Which — or **either triggers सोया**?
- **A4. वर्षफल** — I currently rotate via the `varsh_gyan` permutation; your doc describes an **overlay** (which सोया wakes / which कायम is afflicted). Is the permutation from your book? Keep rotation as the *chart* + add overlay as the *reading*? And should the annual chart use the year's **actual transit positions**?

### 🟠 Group B — Rules named but not fully defined *(shrunk — v2.1 answered many)*
- **B1. मसनूई ग्रह** — still only सूर्य+शनि → "कृत्रिम राहु". Need the **full combination table**.
- **B2. दृष्टि 100/50/25 %** — need the **house-distance → percentage** mapping.
- **B3. धोखा दृष्टि** — *my proposed rule:* an aspect that is सहायक/नीव by table **but** the aspecting planet is itself पीड़ित, or a शत्रु of the house-lord ⇒ धोखा. **Acceptable?**
- **B4. बलि का बकरा** — *my proposed rule:* a पीड़ित malefic in a कच्चा घर absorbing affliction that would otherwise reach a कायम शुभ ग्रह or the lagna. **Acceptable?**
- **B5. अंधी कुंडली threshold** — *my proposal:* ≤3 occupied houses ⇒ अंधी, 4–5 ⇒ आधी अंधी. **Your number?**
- **B6. मुकाबले के ग्रह** — do you have a co-karak table, or shall I derive it from `graha_parichay.karak_bhav` overlap + `maitri` शत्रु?
- **B7. किस्मत जगाने वाला** — *my proposal:* strongest **कायम शुभ** planet linked to house 9/10/11 by placement or दृष्टि. **Confirm?**

### 🟣 Group C — NEW questions from v2.1
- **C1. गूंगा ग्रह — "चारों ओर से पाप ग्रहों से घिरा" means what exactly?** Malefics in the **adjacent houses (2nd & 12th from it)**, or malefics **aspecting** it, or both?
- **C2. मृत ग्रह** — doc says गूंगा **+** बहरा **+** अंधा. Is that **all three required**, or any two?
- **C3. बहरा ग्रह** — need a computable rule. *My proposal:* no benefic दृष्टि **and** in a कच्चा घर **and** its lord पीड़ित ⇒ बहरा (⇒ warn that remedies will act slowly).
- **C4. गूंगा मकान vs सोया घर** — both are "empty + inactive". **What separates them?** (My guess: सोया = temporarily dormant, wakes with time/planet; गूंगा = permanently mute regardless of lord's strength. Confirm?)
- **C5. अंधा ग्रह — "दिशाहीन स्थिति"** — need a concrete definition (no aspect either way? no benefic *or* malefic contact at all?).
- **C6. ग्रह-संख्या तालिका** — you gave शनि=8, राहु=18. Need the **full 9-planet number table** for donation quantities.
- **C7. सवा-मात्रा** — is there a rule for when to use **सवा किलो** vs **सवा नौ रुपये** vs **सवा मीटर**, or is it per-remedy?
- **C8. राशि परिवर्तन** — confirm this means: in गोचर/वर्षफल reading, use the planet's **current transit sign**, while the natal LK house placement stays fixed?

### 🟢 Group D — What I need FROM you *(most valuable)*
- **D1. 8–12 real charts + known life facts** — birth date/time/place **plus** what actually happened (marriage year, job changes/losses, major illness, financial peaks/crashes, foreign travel, litigation). **This is the single highest-value item.** Without it I am guessing; with it I can calibrate and *prove* accuracy.
- **D2. 2–3 expert/book readings** for those same charts — the gold standard for tone, depth and conclusions.
- **D3. Which book/edition is your 39-sheet xlsx from?** Your own doc warns traditions differ on मसनूई, मियाद and दृष्टि-%. Knowing the source lets me resolve every future conflict by that tradition instead of asking again.

### 🔵 Group E — Scope & new data inputs
- **E1. वास्तु input** — VastuLink needs the native's **house details** (kitchen direction, toilet direction, main door, ब्रह्मस्थान condition, water source, trees). Add an **optional "घर विवरण" form**? Which fields? *(Without it I can only say "जाँच करें" for the affected direction — still useful, but generic.)*
- **E2. खानदानी असर input** — add an optional **family-history** input (repeated divorce / संतान-बाधा / same illness), or infer purely from the chart (e.g. पितृ ऋण + 9वाँ पीड़ित ⇒ ancestral)?
- **E3. गोचर scope** — should I rebuild the **existing Gochar tab** to LK rules, or keep Parashari gochar there and add a separate **"लाल किताब गोचर"** view inside the Lal Kitab section? *(Your doc says LK gochar is fundamentally different — I'd recommend a separate LK view so the Vedic tab stays correct.)*
- **E4.** Should the new **निष्कर्ष report replace the default view**, with today's 20 views moved to "विस्तृत/संदर्भ"?
- **E5. Language** — Hindi-primary (as now) or bilingual?
- **E6. शुभता % bar** — LK logic is *classification*, not percentage. **Drop the numeric % from the Lal Kitab tabs** (keep it in मुहूर्त/गोचर where it fits)? I suspect the arbitrary number is part of what feels confusing.

---

## PART 8 — WHAT I CAN START TODAY WITHOUT ANY ANSWERS

Unblocked and safe right now:
- **L1.1–L1.4** foundation (पक्का/कच्चा, कायम/पीड़ित/सोया, ग्रह-फल vs भाव-फल, evidence trace) — using the doc's definitions, with A1/A3 defaults **visibly marked in the UI** so you can correct them later
- **L4.1 ऋण + L4.2 शाप** — rules are fully explicit in v2.1 ✅
- **L6.1 GrahaIdentity** (जाति/रंग/लिंग) + the **अनाज/दिन** and **वृक्ष** tables ✅
- **L3.3 ताला–चाबी** + **L6.4 RemedyResolver** — no disputed data, and this fixes the worst problem (remedy shotgun)
- **L6.3 life-area composer** + the new report layout

Everything else waits on Groups A / B / C.

---

*Nothing has been built. No engine or view code changed.*
