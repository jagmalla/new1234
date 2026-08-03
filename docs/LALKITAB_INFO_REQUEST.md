# लाल किताब — मुझे आपसे क्या चाहिए
## What I Need From You (companion to `LALKITAB_INFO_REQUEST.xlsx`)

This is the readable version of the Excel file. **Fill your answers in the Excel** — this document just explains *why* each item is needed and what happens if it's missing.

> **Excel file:** `docs/LALKITAB_INFO_REQUEST.xlsx` — 10 sheets. Fill only the **yellow** cells. Green rows are examples you can delete.

---

## सबसे पहले क्या भरें / Fill in this order

| Priority | Sheet | What | If missing… |
|---|---|---|---|
| 🔴 1 | **TEST_CHARTS** + **LIFE_EVENTS** | 8–12 real charts + what actually happened | I cannot prove accuracy — only guess. **This is the single most valuable item.** |
| 🔴 2 | **A_Blocking** | 4 rule conflicts only you can settle | I must guess a tradition; the engine may be built on the wrong rule |
| 🟠 3 | **B_C_Rules** | 15 rules needing a precise definition (my proposal given — approve or correct) | I use my proposal and mark it "assumed" in the UI |
| 🟠 4 | **Masnui** · **Drishti_Pct** · **Planet_Numbers** | 3 small tables absent from your document | Those three features stay switched off |
| 🔵 5 | **Scope** | 6 presentation/scope decisions | I follow my recommendation |
| 🟢 6 | **Verify_Tables** | Confirm I read your tables correctly | Risk of a silent mis-reading propagating everywhere |

---

## 🔴 A — नियम-टकराव / Blocking conflicts (sheet `A_Blocking`)

These are places where **your rules document and your 39-sheet dataset say different things.** Both are legitimate Lal Kitab positions — I cannot pick for you without guessing at your tradition.

### A1. पक्का घर — house-based or planet-based?
- **Your document:** houses **1, 2, 3, 4, 5, 7, 9, 11** are पक्के; 6, 8, 10, 12 कच्चे. Based on house number.
- **Your dataset:** each *planet* has its own pakka ghar — सूर्य→1, चन्द्र→4, मंगल→3 & 8, बुध→7, गुरु→9, शुक्र→7, शनि→10, राहु→12, केतु→6.
- **The clash:** शनि in house 10 is in *its own pakka ghar*, but 10 is a *कच्चा house*. Which reading wins?
- **My proposal:** keep both as separate flags; on conflict the planet's own pakka ghar outweighs.

### A2. मियाद / उम्र — three age systems now exist
| | System | Source |
|---|---|---|
| (a) | सूर्य 0–1 · चन्द्र 1–4 · मंगल 4–7 · राहु 7–9 · गुरु 9–16 · शनि 16–36 · बुध 36–48 · केतु 48–56 · शुक्र 56+ | your document |
| (b) | 4 अवस्थाएँ × 25 वर्ष, mapped to house-groups | your xlsx `avastha` — **the Age Timeline currently uses this** |
| (c) | per-planet प्रभाव / अशुभ / विशेष वर्ष | your xlsx `grah_chakra` |

- **My proposal:** use all three at different levels — **(b) = महादशा** frame, **(a) = अन्तर्दशा** ("who is in charge right now"), **(c) = event spikes**.
- **Why it matters:** this decides how the Age Timeline (already built) is re-wired.

### A3. सोया ग्रह — which definition?
- **Your document:** the planet's *surrounding houses are empty*.
- **Your dataset (`supt_bhav`):** each house has a specific **जगाने वाला ग्रह** that must be present in the chart.
- **My proposal:** either condition marks the planet सोया, showing the reason separately.

### A4. वर्षफल — permutation or overlay?
- **What I built:** rotate the janam chart through the `varsh_gyan` per-age permutation.
- **What your document describes:** an **overlay** — take the janam कायम/सोए planets, then see *which सोया planet wakes* and *which कायम planet becomes afflicted* that year.
- **My proposal:** keep the permutation as the **chart**, add the overlay as the **reading**.
- **Also please answer:** should the annual chart include the year's **actual transit positions**, or the permutation alone?

---

## 🟠 B & C — अधूरी परिभाषाएँ / Rules needing a precise definition (sheet `B_C_Rules`)

Your document names these but doesn't define them computably. **I have proposed a rule for each** — in the Excel just write **Approve**, or **Correct** plus the right rule.

| Q# | Rule | My proposed definition |
|---|---|---|
| **B3** | धोखा दृष्टि | An aspect that is सहायक/नींव by the table, **but** the aspecting planet is itself पीड़ित, or an enemy of the house-lord |
| **B4** | बलि का बकरा | A पीड़ित malefic in a कच्चा घर absorbing affliction that would otherwise strike a कायम शुभ planet or the lagna — **and its remedy is then withheld** |
| **B5** | अंधी कुंडली | ≤3 occupied houses ⇒ अंधी · 4–5 ⇒ आधी अंधी · 6+ ⇒ normal |
| **B6** | मुकाबले के ग्रह | Shared `karak_bhav` in `graha_parichay` **+** mutual enmity in `maitri` |
| **B7** | किस्मत जगाने वाला | Strongest कायम शुभ planet linked to house 9/10/11 by placement or दृष्टि |
| **C1** | गूंगा ग्रह | Malefics in both adjacent houses (2nd & 12th from it) or aspecting it, **and** no benefic aspect — *please confirm whether "घिरा" means adjacent houses, aspects, or both* |
| **C2** | मृत ग्रह | All three (गूंगा + बहरा + अंधा) required — strictest reading, keeps it rare |
| **C3** | बहरा ग्रह | No benefic aspect + कच्चा घर + its house-lord also afflicted ⇒ warn that remedies will act slowly |
| **C4** | गूंगा मकान vs सोया घर | सोया = temporary, wakes with time/planet · गूंगा = permanently mute even with a strong lord |
| **C5** | अंधा ग्रह | No benefic aspect, no malefic aspect, no companion — i.e. **no contact at all** ⇒ unpredictable/reverse results |
| **C7** | सवा-मात्रा | Solid goods ⇒ सवा किलो · money ⇒ सवा नौ/इक्यावन रुपये · cloth ⇒ सवा मीटर |
| **C8** | राशि परिवर्तन | In transit/varshphal use the planet's **current sign** influence, while its Lal Kitab house stays fixed from birth |

### Three small tables I still need (own sheets)
- **`Masnui`** — the full **artificial-planet** combination table. Your document gives only सूर्य + शनि → कृत्रिम राहु. *Without this, मसनूई stays off.*
- **`Drishti_Pct`** — which house-distance carries **100% / 50% / 25%** aspect strength. *Without this, aspects stay boolean.*
- **`Planet_Numbers`** — the number tied to each planet for donation quantities. You gave **शनि = 8** and **राहु = 18**; I need the other seven.

---

## 🔴 D — परीक्षण कुंडलियाँ / Test charts (sheets `TEST_CHARTS`, `LIFE_EVENTS`)

**This is the most important thing you can give me.**

When we built the Politics tab, yoga-counting *looked* correct — but until we tested it against 13 real people we couldn't tell winners from losers, and one "improvement" turned out to make it measurably **worse**. I found that only because there was real data to check against. I don't want to repeat that mistake here.

**What I need:**
- **8–12 real charts** — date, time, place, timezone. Initials are fine instead of names; privacy is preserved.
- **4–5 real events per chart** — marriage, children, job change/loss, money gain/loss, illness, accident, foreign travel, litigation, property — with the **year** (month if known) and whether it was good/bad/mixed.

**What I'll do with it:** run every chart through the new pipeline and publish a **hit / miss / vague scorecard**, then only release if it measurably beats the current version. You'll see the evidence, not just my claim.

> **Accuracy note:** birth *time* matters most. If a time is approximate, mark it in the "समय की सटीकता" column — I'll weight those charts lower rather than treat a guess as fact.

---

## 🔵 E — दायरा व प्रस्तुति / Scope decisions (sheet `Scope`)

| Q# | Question | My recommendation |
|---|---|---|
| **E1** | Vastu remedies need the native's **house details** (kitchen/toilet direction, main door, ब्रह्मस्थान, water source, trees). Add an optional form? | **Yes** — without it I can only say "check this direction" |
| **E2** | Family-repeated problems (खानदानी असर) — add an input, or infer from the chart? | Optional input; infer when blank |
| **E3** | Your doc says LK gochar differs from Parashari. Rebuild the Gochar tab, or add a separate **"लाल किताब गोचर"** view? | **Separate LK view** — keeps the Vedic tab correct |
| **E4** | Should the new **निष्कर्ष report** become the default view, with today's 20 views moved to "विस्तृत/संदर्भ"? | **Yes** — one clear answer up front |
| **E5** | Hindi-only or bilingual? | Hindi-primary with English subtitles (as now) |
| **E6** | Lal Kitab logic is *classification*, not percentage. **Drop the शुभता % bar** from Lal Kitab tabs (keep it in मुहूर्त/गोचर)? | **Yes** — an arbitrary-looking number adds to the confusion |

---

## 🟢 Verify — मैंने आपका दस्तावेज़ सही पढ़ा? (sheet `Verify_Tables`)

I've listed back the tables I extracted from your document — **अनाज/दान**, **जाति·रंग·लिंग**, **वास्तु दिशा**, **वृक्ष उपाय**, **ऋण पहचान**, **शाप पहचान** (43 rows). Leave a row blank if it's right; mark it and give the correction if I mis-read.

This takes five minutes and prevents a wrong value propagating into every prediction.

---

## अगर आप कुछ न भरें तो? / If you fill nothing

I can still start today on the parts that need no answers:
- **Foundation** — पक्का/कच्चा houses, कायम/पीड़ित/सोया classification, ग्रह-फल vs भाव-फल wording, evidence trace
- **ऋण + शाप** — your v2.1 rules are fully explicit ✅
- **जाति/रंग/लिंग + अनाज/दिन + वृक्ष** remedy composition ✅
- **ताला–चाबी + RemedyResolver** — fixes the remedy shotgun, needs no disputed data
- **Life-area report** layout

Anything built on an unanswered question will be **visibly marked as an assumption in the UI**, so you can correct it later without hunting through code.

---

*Nothing has been built for this plan yet. See `docs/LALKITAB_PREDICTION_PLAN.md` for the full 25-module design.*
