# Auto Business — Astro Intelligence Engine

> **Build instructions for Claude Code:** This file is the complete build plan.
> Build it ONE MODULE AT A TIME, in order (Module 1 first). Do not start a later
> module until the current one is reviewed. Apply the Global Architecture Rules
> to every module. After each module, list the files you created.

---

# How to Use This Document

This document is a complete build plan for an AI coding assistant. It is split into **modules**. Give the assistant the System Context + Global Architecture Rules first, then request one module at a time, in order. Each module builds on the previous one.

Modules 7 and 8 are **optional add-ons** (green headers): a self-service "Create New Book Agent" flow and a full Admin Panel. Build them only if you want those capabilities. Modules 5b and 5c build the free public homepage (Rashi Phal, vision, and a "Find My Rashi" helper) to attract and onboard visitors, and Module 9 covers the legal/protective content (disclaimer, terms, privacy, refunds).

**Key design decision (book agents):  **Agents do NOT re-read full PDFs on every reading. Each book is ingested ONCE: converted to clean structured Markdown, split by heading into retrievable chunks, and distilled into a compact digest (the agent's "own version"). Each agent is locked to ONE book and answers strictly from it. The final Conclusion agent is the only one allowed to reason freely across all the book answers. Readings can be produced in any of 20 languages (Module 3b).

# System Context (paste first)

Act as an expert Principal Systems Architect and Senior Full-Stack PHP Developer. Build a production-ready, self-hosted visual workflow automation platform named **Auto Business**, including a specialized multi-agent AI Astrology Prediction Engine. Do not write the entire codebase at once — build one module at a time, in order, and confirm before moving on.

# Global Architecture Rules (apply to every module)

### Tech stack

- Backend: PHP 8.x, strict OOP, MVC, declare(strict_types=1), PDO prepared statements everywhere.
- Database: MySQL / MariaDB.
- Frontend: HTML5, Tailwind (CDN in dev), vanilla JS. Visual canvas via Rete.js or Drawflow (both MIT) — not GoJS (commercial).
- Native PHP cURL and curl_multi_exec for parallel work. No Node.js or Python daemons. No root/SSH-only features.

### Execution model — queue + cron runner (critical for A2)

- **Triggers never run workflows inline. **A webhook or schedule enqueues a job into a job_queue table and returns immediately (HTTP 202).
- A single master cron runs every minute: * * * * * php /home/USER/auto_business/runner.php. It claims pending jobs (SELECT ... FOR UPDATE or status-flip + affected-rows check so two ticks never double-run), executes them, and is resumable.
- Assume max_execution_time may be ~30s. Long work persists state to job_queue.state_json and resumes on the next tick.

### Parallel agent fan-out — run in safe waves

**Hosting reality:  **Firing 20 LLM calls at once via curl_multi_exec can exhaust memory/time on A2 shared hosting. Keep the fan-out pattern but run it in BATCHES (e.g. 4-5 concurrent at a time) inside the cron runner, persisting results between waves. The architecture is unchanged; it just executes in controlled waves so it never crashes the account.

### Security (non-negotiable)

- **Encryption: **AES-256-GCM (authenticated), random IV per record, master key in an environment variable / .env stored OUTSIDE public_html. (Not AES-256-CBC.)
- **No arbitrary code execution: **the "Custom Code" node is a whitelisted transform DSL (string/array/math ops, safe expression evaluator) — never eval() or user-supplied callables.
- CSRF tokens on all dashboard/admin/canvas forms. Inbound webhooks use a per-workflow secret/HMAC instead. Every cURL call sets CURLOPT_TIMEOUT and CURLOPT_CONNECTTIMEOUT. Strict try/catch with errors logged, never fatal.

### Roles

- End-user tiers: free, pro, max (feature/usage gating).
- Staff roles for the admin panel: super_admin, editor, support (see Module 8).

**MODULE 1 — Master Database Schema**

Generate one SQL migration file creating the relational structure below. Use InnoDB, UTF8MB4, foreign keys, and the noted indexes.

### Core platform tables

- **users**: id, email (unique), password_hash, tier ENUM(free,pro,max), timezone, is_active, created_at.
- **staff**: id, email (unique), password_hash, role ENUM(super_admin,editor,support), is_active, created_at. (Admin-panel logins, separate from end users.)
- **workflows**: id (UUID), user_id, name, workflow_graph_json, last_known_schema, is_active, schedule_cron, next_run_at, created_at, updated_at.
- **job_queue**: id, workflow_id, status ENUM(pending,claimed,running,done,failed), payload_json, state_json, attempts, claimed_at, created_at. Powers the runner.
- **execution_logs**: id, workflow_id, status, input_data, output_data (truncate large blobs), error_message, executed_at. Index (workflow_id, executed_at).
- **credentials**: id, user_id, name, type, iv, encrypted_data, created_at, updated_at.

### Astrology engine tables

- **astro_agents**: id, agent_name, book_label, source_text (raw uploaded text — optional once compiled), system_instruction_template, grounding_mode ENUM(grounded,style,hybrid), prediction_type ENUM(standard,daily,monthly,yearly), is_active, created_by_staff_id, created_at, updated_at. Seed with the books in Module 3.
- **agent_knowledge**: id, agent_id, chunk_index, heading_path (e.g. 'Chapter 4 > Mars > 7th House'), markdown_text, embedding_json (nullable), topic_tags, created_at. Holds the retrievable Markdown chunks per book. Index (agent_id, chunk_index).
- **agent_digest**: id, agent_id, digest_json (the compiled structured summary: significations, yogas, dasha effects, remedies), version, compiled_at. One current digest per agent.
- **command_usage_logs**: id, user_id, executed_at, agents_count, output_language, prediction_type, location_used ENUM(birth,current), prediction_date. Index (user_id, executed_at) for daily tier limits.
- **workflow_conclusions**: id, command_log_id, user_id, output_language, final_summary, questions_asked, created_at.
- **agent_qa_history**: id, conclusion_id, question_text, answer_text, asked_at.
- **app_settings**: id, setting_key (e.g. 'ayanamsa', tier limits, enabled_languages), setting_value, updated_by_staff_id, updated_at. Admin-editable config read by the engine; ayanamsa defaults to 'lahiri'.

**Output: **the complete SQL migration with all tables, foreign keys, indexes, and the seed rows for the book agents.

**MODULE 2 — Core Execution Engine & Visual Canvas**

Build the foundational automation mechanics. Apply all Global Architecture Rules.

**Who uses the canvas:  **The visual canvas is an ADMIN/STAFF-only builder. You assemble the astrology engine (and any automation) as workflows here. Clients and astrologers never see it — they get the friendly screens in Module 5d that run these workflows behind buttons.

### 1. Frontend canvas

- Drag-and-drop UI (Rete.js / Drawflow) to place Trigger nodes (Webhook, Cron), Logic nodes (If/Else, Loop), Action nodes (HTTP Request), and the safe Transform node.
- Connect output ports to input ports; serialize layout + logic to workflow_graph_json; save via CSRF-protected endpoint.

### 2. runner.php + ExecutionEngine.php

- runner.php: claims jobs safely, evaluates due schedules via next_run_at, executes, resumes across ticks.
- **ExecutionEngine.php**: DAG parser — topologically sorts nodes, starts at the Trigger, walks Logic/Action/AI nodes, passing state strictly as JSON. Every node implements execute(array $input): array.

### 3. TokenResolver.php

- Parses \{\{ Nodes.API_Fetch.output.title \}\} dynamic variables, resolving dot-notation against the global state before a node runs.

### 4. CredentialVault.php + HttpRequestNode.php

- **CredentialVault.php**: AES-256-GCM encrypt/decrypt, per-record IV, master key from .env outside webroot.
- **HttpRequestNode.php**: native cURL with strict timeouts; resolves tokens + injects decrypted credentials server-side; returns status + body downstream.

**Output: **the canvas frontend, runner.php, ExecutionEngine.php, TokenResolver.php, CredentialVault.php (GCM), and HttpRequestNode.php.

**MODULE 3 — Book Ingestion + Multi-Agent Orchestration (Fan-Out)**

Build the predictive engine. This module has two phases: a one-time ingestion that compiles each book, and the runtime fan-out that produces predictions. Apply all Global Architecture Rules.

**Why ingestion first:  **You supply the real books. The system reads each book ONCE and builds a compact knowledge base for it (the agent's "own version"). At prediction time the agent reads only the relevant slices — fast, cheap, and grounded in the real text. This is what prevents the LLM from inventing fake rules and shlokas.

### 1. Book ingestion pipeline (one-time per book)

1. Admin uploads the real book (PDF/text) for an agent (UI in Module 8).
2. Convert the book to clean structured Markdown — chapters/houses/planets become ## headings, remedies and rules become lists. Markdown headings act as natural retrieval anchors and use far fewer tokens than raw PDF text.
3. Split the Markdown by heading into chunks; store each row in agent_knowledge (markdown_text + topic_tags like 'mars', '7th-house', 'remedies', plus the heading path).
4. Run a one-time AI distillation pass that compiles a structured digest — planet/house significations, yogas, dasha effects, remedies — and store it in agent_digest as digest_json.
5. Mark grounding_mode per book: grounded (only use stored text), style (model's own knowledge), or hybrid.

**Why Markdown:  **Storing each book as heading-structured Markdown means an agent can jump straight to the relevant section (e.g. "## Mars in 7th House") instead of scanning the whole text. Cleaner structure = easier retrieval + fewer tokens per reading.

**Accuracy safeguard:  **Keep the original Markdown chunks in agent_knowledge even after the digest is built. When a reading needs precision (a specific remedy or rule), the agent retrieves the exact passage instead of trusting the summary. Digest = speed; chunks = ground truth.

### 2. Agent 1 — Calculation Engine (runs first)

- Pure PHP astronomical/astrological computation. Calculates D1, D9, Varshphal, Vimshottari + Mudda dashas, and Gochar (transits).
- Outputs an immutable JSON payload (the chart). All book agents consume this same payload — never recomputed per agent.
- **Ayanamsa: **admin-selectable (Lahiri/Chitrapaksha by default; also Raman, KP). Stored in settings so the admin panel can change it; the engine uses it for every calculation.
- **Transit block (for the time-based agents in Module 3c): **the engine also computes the current Moon position, the current Sun sign/house, and a full Varshaphal annual chart for the requested date. By default these are computed against the BIRTH location; the user can override to their CURRENT location for daily/monthly transits.

### 3. AstrologyOrchestrator.php (fan-out in waves)

- Takes the Calculation JSON and broadcasts it to the selected book agents using curl_multi_exec — but in batches of 4-5 concurrent (per Global Rules), persisting results to job_queue.state_json between waves.
- Each agent receives the chart + only its relevant knowledge slices (digest entries / retrieved chunks matched to the chart's placements).

### 4. AgentPromptFactory.php

- Dynamically builds each agent's prompt: chart JSON + that book's system_instruction_template + the selected knowledge slices.
- Enforce strict JSON output per agent: \{"prediction": "...", "remedies": \[...\]\}.
- For grounded books, the prompt instructs the model to answer ONLY from the supplied text and say so if the text doesn't cover it — no invention.

### 5. Strict single-book isolation (hard rule)

**Critical — enforce at the data layer, not just the prompt:  **Telling an LLM "only use this book" is NOT enough — it still has every book in its training data and will quietly blend them, inventing shlokas and remedies. True isolation requires BOTH of the rules below.

- **Data isolation: **at prediction time, each agent is fed ONLY its own book — retrieve digest/chunks WHERE agent_id = this agent. Cross-book retrieval is forbidden. An agent is physically never given another book's text.
- **Grounded prompting: **book agents default to grounding_mode = grounded. The prompt instructs the model to answer ONLY from the supplied passages and to say plainly "this book does not cover this" when its book is silent — never filling the gap from memory or another tradition.
- **Honest gaps are expected, not a bug: **when a book genuinely doesn't address a topic, that agent correctly returns a "not covered" result. The Conclusion agent (Module 5) fills those gaps. Forcing every agent to always answer fully would guarantee invention.
- Each agent's output is labeled with its source book so every point in the final reading is traceable to the book it came from.
- The admin panel (Module 8) can loosen a specific book to grounding_mode = hybrid later, but grounded is the default for all book agents.

### 6. The book agents (seed list)

Agent 1 is the Calculation Engine. Agents 2-20 are the 19 book agents:

*Brihat Parashara Hora Shastra · Phaldeepika · Lal Kitab · Brihat Jataka · Yantra Chintamani · Bhrigu Nandi Nadi · Ravan Samhita · Muhurta Chintamani · Tajik Neelkanthi · Uttara Kalamrita · Saravali · Prashna Marga · Mudra Vigyan · Ratna Pradipika · The Picatrix · Three Books of Occult Philosophy · De Vita Libri Tres · Culpeper's Herbal · Kalachakra Tantra.*

**Note:  **Agent 1 (calculator) + 19 books = 20 agents total. The original draft labeled these 2-20 but called it "19 agents"; this spec treats Agent 1 as the calculator and 2-20 as the 19 books to keep the numbering consistent.

**Output: **the ingestion pipeline, Agent 1 calculation class, AstrologyOrchestrator.php (batched fan-out), AgentPromptFactory.php (with per-agent data isolation + grounded prompting), and the JSON contracts.

**MODULE 3b — Multi-Language Output (LanguageManager.php)**

Let the user choose the language for their reading. The selected language is applied to every book agent AND the final conclusion, so the entire reading is consistent in one language. Apply all Global Architecture Rules.

### 1. Language selector

- A dropdown on the reading screen lets the user pick one output language before running. Default = English. Store the choice as output_language on the command/conclusion record so logs and Q&A stay in the same language.
- Pass output_language into AgentPromptFactory so each agent prompt instructs the LLM to answer entirely in that language — including the prediction and remedies fields of the JSON. The ConclusionEngine and QaHandler use the same language.

### 2. Supported languages (20)

Offer these in the dropdown (display the native name; store an ISO code):

English

Hindi (हिन्दी)

Punjabi (ਪੰਜਾਬੀ)

Spanish (Español)

French (Français)

Arabic (العربية)

Mandarin Chinese (中文)

Bengali (বাংলা)

Portuguese (Português)

Russian (Русский)

Urdu (اردو)

Indonesian

German (Deutsch)

Japanese (日本語)

Tamil (தமிழ்)

Telugu (తెలుగు)

Marathi (मराठी)

Gujarati (ગુજરાતી)

Italian (Italiano)

### 3. Notes

- Because every agent answers natively in the chosen language, token usage is the same as English (no separate translation pass) — the model simply writes in the target language.
- Astrology terms (graha, dasha, yoga) often read better left in their original form. Instruct agents to keep such technical terms and add the local-language meaning in brackets on first use.
- The admin panel (Module 8) can enable/disable specific languages from this list.

**Output: **LanguageManager.php (the supported-language list + prompt-injection helper), the selector UI, and the output_language wiring through AgentPromptFactory, ConclusionEngine, and QaHandler.

**MODULE 3c — Time-Based Transit & Year Agents**

Three calculation-driven agents that predict over time. Each still follows the strict single-book isolation rule — it answers ONLY from its assigned book — but first receives accurate live positions from the Calculation Engine to look the answer up against. Apply all Global Architecture Rules.

**How these differ from the book agents:  **The 19 book agents mostly read text. These three are calculation-first: Agent 1 computes the exact Moon / Sun / annual-chart positions, then the agent reads ONLY its own book to interpret those positions. Accurate numbers in, single-book interpretation out.

### 1. Daily Gochar Agent — Moon-based (DailyGocharAgent.php)

- The Moon changes sign roughly every 2.25 days, so daily prediction is driven by the Moon's current transit relative to the person's birth Moon (classical Chandra-based gochar).
- Input from Agent 1: today's Moon sign/house vs the natal Moon. Computed against the BIRTH location by default; user may override to CURRENT location.
- Reads ONLY the provided daily-gochar book (its own agent_id knowledge), grounded mode. Answers the day's effect + any remedy strictly from that book.

### 2. Monthly Gochar Agent — Sun-based (MonthlyGocharAgent.php)

- The Sun changes sign about once a month (Sankranti), making it the natural anchor for monthly prediction.
- Input from Agent 1: the current Sun sign/house transit relative to the birth chart. Birth-location default; current-location override allowed.
- Reads ONLY its assigned monthly-gochar book, grounded mode.

### 3. Year Prediction Agent — Varshaphal (YearPredictionAgent.php)

- Annual prediction via the Vedic Varshaphal / Tajik system, using the Tajik Neelkanthi book.
- Input from Agent 1: a full annual chart cast for the birthday of the requested year — Muntha, year-lord (Varshesh), Mudda dasha, and Tajik aspects/Sahams.
- Reads ONLY the Tajik Neelkanthi book content (grounded). This is more than a transit lookup — it interprets the annual chart strictly per that book.

### 4. Wiring

- Add these as agent rows (with their own book uploaded via Module 7) and a prediction_type field: daily / monthly / yearly. The reading screen lets the user pick a prediction type and (for daily/monthly) the location to use.
- They run through the same AstrologyOrchestrator fan-out and feed their answers into the Conclusion agent (Module 5) like any other agent.
- store_location_used and prediction_date are saved on the command record so logs/Q&A stay consistent.

**Output: **the Calculation Engine extensions (current Moon, current Sun, Varshaphal chart, birth/current location toggle, ayanamsa setting), DailyGocharAgent.php, MonthlyGocharAgent.php, YearPredictionAgent.php, and the prediction-type + location UI.

**MODULE 4 — Tier-Gated Limits & Validation (TierGuard.php)**

Enforce usage limits both server-side (authoritative) and in the UI. Apply all Global Architecture Rules.

**Tier**

**Commands / day**

**Agents per command**

**Free**

3

3

**Pro**

10

6

**Max**

25

9

### Implementation

- **TierGuard.php**: before AstrologyOrchestrator runs, check today's command count in command_usage_logs and the requested agents_count against the user's tier. Reject over-limit requests server-side (never trust the browser).
- Frontend JS: disable agent checkboxes automatically once the tier's per-command agent limit is reached; show remaining daily commands.
- Limits are read from a config/DB table so the admin panel (Module 8) can change them without code edits.

**Output: **TierGuard.php, the usage-logging logic, and the frontend limit JS.

**MODULE 5 — Interactive Conclusion & Q&A Engine**

After the book agents return, run a final synthesis call gated by tier. Apply all Global Architecture Rules.

**Tier**

**What they receive**

**Free**

Raw book-agent outputs only. No synthesized summary, no questions.

**Pro**

An AI-synthesized master conclusion / suggestion reading. No custom questions (UI locked).

**Max**

Master conclusion PLUS a live chat to ask up to 5 custom questions about their reading.

### Implementation

**The Conclusion agent is the ONE exception to single-book isolation.  **Every book agent answers strictly from its own book. The Conclusion agent is the only one allowed to reason freely ("AI brain") across ALL the book answers — weighing them, noting where books agree or conflict, and producing one combined suggestion. It cites which book each point came from, so the final reading stays traceable.

- **ConclusionEngine.php**: for Pro/Max, makes a synthesis LLM call that receives all book-agent outputs (each labeled with its source book) plus the original chart, and combines them into one coherent conclusion; stores it in workflow_conclusions.
- Where books disagree, the conclusion notes the difference rather than silently picking one — this is the value of consulting many books at once.
- **QaHandler.php**: route for Max users' custom questions. Checks questions_asked against the limit (5), queries the LLM with the reading context + prior Q&A, stores the exchange in agent_qa_history, and increments the counter.
- All limits server-enforced; the chat input is hidden/locked in the UI for Free and Pro. The conclusion and Q&A use the same output_language as the reading.

**Output: **ConclusionEngine.php, QaHandler.php, and the tier-gated conclusion/chat UI.

**MODULE 5b — Public Rashi Phal (Free Homepage Horoscopes)**

A free, public daily and monthly horoscope for all 12 rashis, shown on the website homepage to attract visitors and convert them to paid readings. Generic per-sign (not personal). Apply all Global Architecture Rules.

**Cost-control rule (critical):  **Rashi Phal is generated ONCE per period and stored, then served to every visitor from the database — NOT regenerated on each page view. A cron job builds all 12 daily readings each day and all 12 monthly readings each month. Visitors always read the stored copy. This is the difference between pennies a day and a runaway AI bill on a busy homepage.

### 1. What visitors see

- Homepage shows all 12 rashis (Vedic Janma Rashi / moon-sign), each with a short one-line teaser + a "Read More" button.
- Read More → the full DAILY Rashi Phal for that sign (Moon transit + the day's interaction with each planet), written in easy, everyday language.
- A "Monthly Rashi Phal" button → the MONTHLY version (Sun transit + the month's planetary interactions), also in easy language.
- Free to view, no login required. Each reading carries the short disclaimer (Module 9) and a call-to-action toward the paid personal readings.

### 2. Generic vs personal (protect the paid product)

- **Rashi Phal is generic: **per-sign only, based on the rashi — every Aries visitor sees the same text. It uses the daily/monthly transit logic from Module 3c but with NO personal birth chart.
- **Paid readings stay personal: **the full multi-agent, full-birth-chart product is the paid offering. Keep the free tier clearly lighter so it drives interest without replacing the paid reading.

### 3. Auto-update engine (RashiPhalGenerator.php)

- A daily cron (via the master runner) regenerates all 12 daily readings when the date changes; a monthly cron regenerates the 12 monthly readings at month start.
- Uses the Calculation Engine for the day's/month's transit per sign, then one AI call per sign to write the easy-language text + one-line teaser. Stores results in a rashi_phal table.
- If a generation run fails, keep serving the previous stored reading (never show a blank homepage).

### 4. Data

- **rashi_phal** table: id, rashi (1-12), period_type ENUM(daily,monthly), teaser_line, full_text, output_language, for_date, generated_at. Index (rashi, period_type, for_date).
- Generate the public readings in the site's main languages (at least Hindi + English + Punjabi); the language toggle reuses Module 3b.

**Output: **RashiPhalGenerator.php, the rashi_phal table, the daily/monthly cron wiring, and the homepage + read-more frontend.

**MODULE 5c — Homepage Experience & "Find My Rashi"**

The public landing experience: a warm overview that speaks to both clients and astrologers, a vision section, the language-toggleable Rashi Phal, and a free "Find My Rashi" helper. Apply all Global Architecture Rules.

### 1. Detailed overview — speaks to both audiences

- A hero section with two clear paths so every visitor feels it is for them:
	- **For clients: **"Understand your day, month, and year through astrology — in simple language you can actually use."
	- **For astrologers: **"Consult 19 classical books at once and sharpen your predictions, with sources you can trace."
- Each path shows benefits framed for that reader and a clear next step (try the free Rashi Phal / see subscription plans).

### 2. Vision & purpose section

- A sincere, purpose-driven section showing the platform exists to genuinely help people and support practitioners — making classical astrological wisdom accessible, in everyone's own language, honestly and respectfully.
- Tone: warm, good-intentioned, never over-promising. Pair it with the disclaimer so honesty and care come across together.

**Copy guidance:  **Write the vision so a reader feels the project is built for their good — to give clients clarity and calm, and to give astrologers a faster, deeper research tool. Avoid fear-based or guaranteed-outcome language; it reads as trustworthy and also keeps you on the right side of the disclaimer.

### 3. Rashi Phal with language toggle (English / Hindi)

- The homepage shows the daily and monthly Rashi Phal with an English / Hindi toggle the visitor controls; the stored reading is shown in the chosen language (reuses Module 3b + the stored readings from Module 5b — still no per-view AI cost).
- Generate and store the public readings in at least English and Hindi (Punjabi optional) so the toggle is instant.

### 4. "Find My Rashi" helper (free)

For visitors who don't know their signs. They enter birth details; the Calculation Engine returns their three key signs.

- **Returns: **Moon sign (Janma Rashi), Ascendant (Lagna), and Sun sign.
- **Inputs: **birth date, birth time, and birth place. Place uses a searchable city dropdown (auto-fills coordinates + timezone) with a manual override for coordinates/timezone if the city isn't found.

**Accuracy honesty (important):  **Sun sign needs only the birth date. Moon sign and especially the Ascendant need an accurate birth TIME (the Ascendant changes about every 2 hours). If the visitor doesn't know their birth time, show the Sun sign and a Moon-sign estimate, and clearly state the Ascendant needs a birth time rather than guessing. Honest gaps protect trust and reduce legal risk.

- This is a free hook: after showing the three signs, invite the visitor to read their Rashi Phal and explore a full personal reading.
- **FindMyRashi.php**: validates inputs, calls the Calculation Engine with the chosen ayanamsa, returns the three signs + the honesty note when birth time is missing.

**Output: **the homepage (overview + vision + plans), the language-toggle Rashi Phal section, FindMyRashi.php, and the city-search + manual-override birth-details form.

**MODULE 5d — How It Runs as Auto Business Workflows + Client/Astrologer Screens**

This module ties the astrology product to the Auto Business automation engine and defines the two simple user-facing screens. Apply all Global Architecture Rules.

**The core idea:  **The astrology engine (Calculation → book agents → Conclusion) is built as Auto Business WORKFLOWS on the visual canvas — but ONLY you/admin ever see the canvas. Clients and astrologers get clean, friendly screens that RUN these pre-built workflows behind buttons. They never pick nodes or see the canvas. Canvas = the builder's tool; the screens = the user's tool.

### 1. Astrology engine = pre-built workflows (admin side)

- You/admin assemble each prediction flow as an Auto Business workflow: Calculation node → selected book-agent nodes (fan-out) → Conclusion node, with the language and tier rules applied.
- Save these as named, reusable workflow templates (e.g. "Full Reading", "Daily Gochar", "Year Prediction", "Career Focus"). Each maps a user action to a workflow + a set of agents.
- Each prediction option a user can pick corresponds to a stored agent set. Picking options on the frontend = selecting which agents run — without the user ever knowing it's a workflow underneath.

### 2. Client screen (simple, view-only results)

- A clean interface where the client enters their birth details (city-search + override from Module 5c) and receives their kundali, charts (D1/D9 etc.), and key details — clearly presented, in their chosen language.
- No agent picking, no canvas. Optionally a "Get Reading" button that runs the appropriate pre-built workflow for their tier and shows the result.
- Every result carries the disclaimer (Module 9).

### 3. Astrologer screen (working tool)

1. Enter client details → the chart is generated and displayed (chart + key placements).
2. A "Predictions" button opens a side panel of prediction/suggestion types.
3. The astrologer ticks which types they want — these checkboxes select the background agents (e.g. choose Lal Kitab + Tajik + remedies). Limits follow the astrologer's tier (Module 4).
4. Run → the pre-built workflow fans out to the chosen agents and returns each book's answer plus the synthesized conclusion, labeled by source book.
5. Results show in the side window in the chosen language; the astrologer can copy/save them for the client.

### 4. Custom questions (Max tier)

- Max-tier users get a question box: they type a custom question about the reading, and the AI agents answer it using the reading context (reuses QaHandler from Module 5, up to the 5-question limit).
- Hidden/locked for Free and Pro tiers.

### 5. Separation of access

- Three distinct interfaces, enforced server-side: admin/staff (canvas + admin panel), astrologer (working screen), client (simple screen). A user only ever sees the screen for their role.

**Output: **the workflow-template mapping (action → workflow + agent set), the client screen, the astrologer screen with the prediction side-panel + agent-selection, and the Max-tier custom-question box.

**MODULE 6 — UX / Quality-of-Life Features**

Apply all Global Architecture Rules.

### 1. Data Schema Inspector

- A frontend slide-out panel reading each upstream node's last_known_schema, letting the user click-to-insert \{\{ tokens \}\} into text inputs — preventing manual typing errors.

### 2. Isolated Step Testing (test_node.php)

- A backend controller to test a single node in isolation by injecting mock JSON input, without firing the whole workflow. Returns that node's output for inspection.

**Output: **the schema-inspector frontend panel and test_node.php.

**MODULE 7 (OPTIONAL) — Create / Manage New Book Agents (Self-Service)**

**Optional module:  **Build this if you want to add new astrology books (or any reference text) as agents yourself, without a developer. It wires the UI to the ingestion pipeline from Module 3. Skip it if your book list is fixed.

Apply all Global Architecture Rules. Reuse the Module 3 ingestion pipeline.

### 1. "New Agent" wizard (staff UI)

1. Enter agent_name, book_label, and choose grounding_mode (grounded / style / hybrid).
2. Upload the real book file (PDF/text) or paste source text.
3. Write or auto-generate the system_instruction_template (how this book should 'speak' and reason).
4. Click Compile: runs the Module 3 ingestion (chunk → store in agent_knowledge → build agent_digest). Show progress; large books compile across cron ticks.
5. Preview: run a test chart through the new agent before activating. Toggle is_active when satisfied.

### 2. Re-compile & versioning

- Editing source text or template creates a new agent_digest version; keep prior versions so you can roll back.
- A "Re-compile" button re-runs ingestion if you upload a better copy of the book.

### 3. Safety

- Only staff with super_admin or editor role (Module 8) can create/compile agents. All uploads validated (type/size); ingestion runs in the queue, never inline.

**Output: **the New Agent wizard UI, the upload + compile controllers, and the digest-versioning logic.

**MODULE 8 (OPTIONAL) — Admin Panel (Roles, Agents, Tiers, Content)**

**Optional module:  **A full back-office so you and your staff can manage the platform without touching code. Build it once the core engine works. Skip if you'll manage everything via the database directly.

Apply all Global Architecture Rules. Admin logins use the staff table, separate from end users.

### 1. Staff roles & permissions

- **super_admin**: full access — manage staff, agents, tiers, billing settings, everything.
- **editor**: create/edit/compile book agents and content; cannot manage staff or tier limits.
- **support**: view users, readings, and logs; assist users; no create/edit of agents or limits.
- Enforce permissions server-side on every admin route (not just by hiding UI). Separate login + session from end-user auth; CSRF on all forms.

### 2. Agent management

- List, create (via Module 7 wizard), edit, enable/disable, re-compile, and preview book agents. Edit each agent's grounding_mode and system_instruction_template.

### 3. Tier, limits & engine settings

- Edit the commands/day and agents/command numbers for free/pro/max (read by TierGuard) without code changes. Edit the Max Q&A question cap.
- Edit engine settings in app_settings: ayanamsa (default Lahiri), enabled languages, and default location mode (birth/current) for transits.

### 4. User management

- View/search users, change a user's tier, activate/deactivate accounts, view their command usage and reading history.

### 5. Monitoring

- Dashboard: commands run today, success/fail counts from execution_logs, agent usage, and per-tier activity. View execution and Q&A logs for support.

**Output: **the admin auth + role middleware, agent/tier/user management screens, and the monitoring dashboard.

**MODULE 9 — Legal & Protective Content (Disclaimers, ToS, Privacy, Refunds)**

**Not legal advice:  **These are standard protective templates for an astrology/entertainment service. They are a sensible starting point, not a substitute for a lawyer. For a real business in British Columbia, have a local lawyer review the final wording and confirm compliance with BC consumer-protection and PIPEDA/PIPA privacy law before launch.

Build these as editable pages (admin-managed via app_settings / a content table) and display them where noted. Apply all Global Architecture Rules.

### 1. Reading disclaimer (show on every reading + homepage footer + Rashi Phal)

Short form (under each reading and teaser):

For entertainment and educational purposes only. Astrological  
readings are not a substitute for professional medical, legal,  
financial, or psychological advice. No specific outcome is  
guaranteed. Decisions you make are your own responsibility.

- Display the short disclaimer on: the homepage footer, every free Rashi Phal, and every paid reading and conclusion.

### 2. Terms of Service (outline)

- Acceptance of terms; eligibility (18+ or with guardian consent).
- Description of service: astrology readings for entertainment/educational use; predictions are interpretive, not factual guarantees.
- Account responsibilities; acceptable use; prohibited misuse.
- Tiers and what each includes (free/pro/max); fair-use limits.
- Intellectual property (your content and the source books); user content.
- Limitation of liability and "as-is" clause; indemnification.
- Governing law: British Columbia, Canada; dispute resolution.
- Changes to terms; contact information.

### 3. Privacy Policy (outline — BC PIPA / PIPEDA aware)

- What is collected: account info, birth date/time/place (sensitive — needed for charts), usage logs, payment info (via processor).
- How it's used: to generate readings, run the service, and improve it; never sold.
- Storage & security: encrypted credentials, where data is hosted, retention period.
- Third parties: LLM API provider, payment processor, email provider — name them and link their policies.
- User rights: access, correction, deletion of their data; how to request it.
- Cookies/analytics; children's data; contact for privacy requests.

### 4. Refund & subscription terms

- Subscription billing cycle (monthly/yearly), auto-renewal, and how to cancel.
- Refund policy: state clearly (e.g. digital/entertainment readings are generally non-refundable once delivered; describe any exceptions).
- What happens to access on cancellation; no refund for unused daily command quota.
- Price-change notice period; free-tier has no payment obligation.
- Display these at the point of purchase and require a checkbox acceptance of ToS + disclaimer before a paid reading or subscription.

### 5. Consent capture

- Require an explicit "I have read and agree to the Terms, Privacy Policy, and Disclaimer" checkbox at signup and at checkout; store the consent timestamp and version against the user.

**Output: **the disclaimer/ToS/privacy/refund content pages (admin-editable), the footer + per-reading disclaimer display, and the consent-checkbox + version logging.

# Suggested Build Order

1. Module 1 — database schema.
2. Module 2 — engine + canvas + credential vault.
3. Module 3 — book ingestion (PDF → Markdown) + Agent 1 + orchestrator.
4. Module 3b — multi-language output.
5. Module 3c — time-based agents (daily/monthly gochar, year prediction).
6. Module 4 — tier limits.
7. Module 5 — conclusion + Q&A.
8. Module 5b — public Rashi Phal (free homepage horoscopes).
9. Module 5c — homepage experience + Find My Rashi.
10. Module 5d — workflow mapping + client/astrologer screens.
11. Module 6 — UX helpers.
12. Module 7 (optional) — self-service new agents.
13. Module 8 (optional) — admin panel.
14. Module 9 — legal & protective content (disclaimer, ToS, privacy, refunds).

# Running Costs (quick reference)

- Hosting, domain, SSL: already covered (A2).
- LLM API: pay-as-you-go by tokens — no subscription. Ingestion is a one-time cost per book; each reading then uses few tokens because agents read the compact digest, not full PDFs. Use a cheaper model for routine agents and a stronger one for the synthesis/conclusion step.
- Everything else (PHP, MySQL, Rete.js/Drawflow, PHPMailer) is free and open-source.

*Prepared for Prabhjot Singh · Surrey, BC*

