# AI "Smart Answers" — what is built, what is pending

**Audited against** `docs/Auto_Business_Astro_Engine_Spec.md` (the original 9-module
build plan) and the code at commit `fe63d06`. Every line below was checked in the
repo, not remembered.

---

## 1. The short answer

The AI half **exists as code but is not connected to anything**. Nine classes,
about 1,185 lines — ingestion, orchestration, prompt building, Anthropic client —
sit in `app/Astro/{Llm,Ingestion,Orchestration}` and **no route, no controller and
no job ever calls them**. The database tables they need are created and the 19
agents are seeded, but not one book has ever been ingested, because the only
specified way to upload a book was Module 8 (the optional admin panel), which was
never built.

The specific thing you remember — *"connect with AI for smart answers"* — is
**Module 5 (ConclusionEngine + QaHandler)**. That is not built at all, and it is
the **last** link in a chain whose earlier links are also not runnable.

Meanwhile the project quietly took a different road, and took it well: instead of
letting an LLM read the books, **the books' rules were hand-coded as deterministic
engines** — 29 engine classes plus the whole Lal Kitab subsystem, muhurat and
milan. That is why the readings are precise and traceable. It is a real pivot, and
it is worth deciding on purpose rather than by accident.

---

## 2. Module-by-module status

| Module | What the spec asked for | Status |
|---|---|---|
| **1** Master schema | 13 tables, agents seeded, settings | ✅ **Done** |
| **2** Execution engine + canvas | Queue, cron runner, resumable DAG, vault, canvas | ✅ **Done** |
| **3** Book ingestion + fan-out | Ingest books, orchestrate 19 agents | ⚠️ **Code written, never wired or run** |
| **3b** LanguageManager (20 languages) | Reading in any of 20 languages | ❌ **Not built** (a `calc/translate` endpoint exists — on-demand, not the module) |
| **3c** Daily / Monthly / Year agents | Time-based LLM agents | ❌ **Not built as agents** — deterministic equivalents exist (`GocharPhalEngine`, `YearTimeline`, Varshaphal). `docs/TODO_MODULE_3C.md` still open |
| **4** TierGuard | Enforce free/pro/max limits | ❌ **Not built** — limits sit in `app_settings`, nothing reads them |
| **5** **Conclusion + Q&A** | **The "smart answers"** | ❌ **Not built** — tables `workflow_conclusions` and `agent_qa_history` exist and are empty |
| **5b** Public Rashi Phal | Free homepage horoscopes | ❌ **Not built** |
| **5c** Homepage + "Find My Rashi" | Public landing experience | ❌ **Not built** |
| **5d** Client / astrologer screens | Readings as workflows + two screens | ⚠️ **Partly** — the astrologer screen is `/calc` and it is far beyond spec; it is not driven by workflows |
| **6** UX / quality of life | Assorted | ⚠️ **Partly, organically** — saved charts, print, topic search, popups, offline places |
| **7** New book agents (optional) | Self-service book upload | ❌ Not built |
| **8** Admin panel (optional) | Roles, agents, tiers, **book upload** | ❌ Not built — **this is why no book was ever ingested** |
| **9** Legal & protective content | Disclaimer, ToS, privacy, refunds | ❌ **Not built** — a `reading_disclaimer` string exists in settings; readings carry their own limits ("कुंडली बाँधती नहीं"), but there are no legal pages |

### What exists in the AI half, precisely

```
app/Astro/Llm/
  LlmClientInterface.php      42 lines
  AnthropicClient.php        129   reads LLM_API_KEY, LLM_AGENT_MODEL, LLM_CONCLUSION_MODEL
app/Astro/Ingestion/
  PdfTextExtractor.php        63
  MarkdownStructurer.php     151
  MarkdownChunker.php         99
  DigestCompiler.php         118
  BookIngestionService.php   107   PDF → markdown → chunks → agent_knowledge → digest
app/Astro/Orchestration/
  KnowledgeRepository.php    139   per-agent retrieval (the data-isolation rule)
  AgentPromptFactory.php     142   chart + book slices → grounded prompt
  AstrologyOrchestrator.php  195   batched fan-out
```

Seeded in `astro_agents`: Calculation Engine + 18 book agents (Brihat Parashara
Hora Shastra, Phaldeepika, Lal Kitab, Brihat Jataka, Yantra Chintamani, Bhrigu
Nandi Nadi, Ravan Samhita, Muhurta Chintamani, Tajik Neelkanthi, Uttara Kalamrita,
Saravali, Prashna Marga, Mudra Vigyan, Ratna Pradipika, The Picatrix, Three Books
of Occult Philosophy, De Vita Libri Tres, Kalachakra Tantra). The spec's list
named 19 books; **Culpeper's Herbal is missing from the seed.**

Settings already in place: `ayanamsa`, `default_location_mode`,
`enabled_languages`, `tier_limit_{free,pro,max}_{commands,agents}`,
`max_qa_question_cap`, `reading_disclaimer`.

---

## 3. What has to happen, in order, to reach "smart answers"

Nothing later works until the earlier item does.

1. **A way to get a book in.** Spec says Module 8 admin panel. A CLI command
   (`php bin/ingest.php <agent> <file.pdf>`) does the same job for a fraction of
   the work and is enough for one owner. — *2–3 days*
2. **Run the ingestion and verify it.** The classes are written but have never
   executed; expect real bugs in PDF extraction and chunking. Needs a check that
   the chunks are genuinely retrievable ("## Mars in 7th House" must be findable).
   — *3–5 days*
3. **Wire the orchestrator.** A route + queued job that takes a computed chart,
   fans out to the selected agents in waves of 4–5, stores each answer with its
   source book. — *4–6 days*
4. **ConclusionEngine (Module 5).** The synthesis call across all book answers,
   noting where books disagree, citing each point's source, stored in
   `workflow_conclusions`. **This is the "smart answer".** — *4–6 days*
5. **QaHandler.** Max-tier chat, 5 questions, counted server-side into
   `agent_qa_history`. — *3–4 days*
6. **TierGuard (Module 4).** Nothing above should ship ungated. — *2–3 days*
7. **Module 9 legal pages.** Before any AI-written text reaches a client. — *1–2 days*

**Total ≈ 4–6 weeks**, plus per-reading API cost, plus the books themselves in a
form that can be ingested (and the right to use them).

---

## 4. The cheaper road, and why I would take it first

There is a version of "smart answers" that skips the whole book pipeline.

The system already produces, for every chart, a large amount of **correct,
structured, traceable** material: planet verdicts with reasons, house readings,
yogas, debts, the निचोड़, and remedies that already carry direction, target and
duration. That is a better grounding corpus than a scanned PDF, because it is
already specific to *this* chart and already checked by 62 self-checks.

**Ground the LLM in our own computed output instead of in books.**

```
chart → existing engines → structured JSON  →  LLM  →  answer in the client's words
                                    ↑
                       nothing invented; every sentence traceable
                       to a verdict the engines produced
```

What that gives, quickly:

- **"Ask about your kundali"** — a client types *"will I get a government job?"* and
  the answer is composed from the career engine, the 10th house, the dasha and the
  relevant remedies — not from the model's memory.
- **Plain-language rewrite** of the निचोड़ for the client, at a chosen reading
  level, in any language — which is Module 3b's real goal without Module 3b.
- **The astrologer's own assistant** — *"why did it call Saturn निष्क्रिय?"* — the
  engines already record `verdict_why`; the LLM just reads it aloud.

Cost: **1–2 weeks**, no ingestion, no book rights, no cross-book isolation problem,
and every answer stays anchored to output we have already spent months making
correct. The guard rails are the same ones the spec insisted on — answer only from
the supplied material, say plainly when it is not covered.

**This is a deviation from the original spec and I am flagging it as one.** The
spec's 19-book fan-out is a bigger, richer product; this is the fastest honest
route to a client typing a question and getting a good answer. They are not
exclusive — the book pipeline can be built later on top of exactly the same
question/answer surface.

---

## 5. My recommendation

1. **Decide the road** — books-first (spec, 4–6 weeks) or engines-first (1–2 weeks).
2. If engines-first: build **Q&A grounded in our own output**, with the disclaimer
   and tier gate from day one.
3. Either way, build **TierGuard** and the **Module 9 legal pages** before any
   generated text goes to a paying client.
4. Add **Culpeper's Herbal** to the agent seed, or drop it from the spec list —
   right now the two disagree.
5. Keep `docs/TODO_MODULE_3C.md` alive or close it deliberately; the deterministic
   gochar engines may already have made it unnecessary.

Tell me which road and I will start on it.
