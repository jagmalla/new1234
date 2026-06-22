-- =============================================================================
-- Auto Business — Astro Intelligence Engine
-- Module 1: Master Database Schema
-- -----------------------------------------------------------------------------
-- One migration that creates the full relational structure.
--   Engine : InnoDB (transactions + foreign keys)
--   Charset: utf8mb4 / utf8mb4_unicode_ci (full Unicode incl. emoji & all
--            20 supported reading languages)
-- Run once on a fresh database, e.g.:
--   mysql -u USER -p auto_business < migrations/001_master_schema.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- SECTION A — CORE PLATFORM TABLES
-- =============================================================================

-- -----------------------------------------------------------------------------
-- users — end-user accounts (clients & astrologers). Tier drives usage gating.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(255)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    tier            ENUM('free','pro','max') NOT NULL DEFAULT 'free',
    timezone        VARCHAR(64)     NOT NULL DEFAULT 'UTC',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- staff — admin-panel logins (Module 8). Separate from end users on purpose.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(255)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('super_admin','editor','support') NOT NULL DEFAULT 'support',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_staff_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- workflows — saved visual-canvas automations. id is a UUID (CHAR(36)).
-- workflow_graph_json holds the canvas layout + logic; schedule drives cron.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflows (
    id                  CHAR(36)        NOT NULL,
    user_id             BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(255)    NOT NULL,
    workflow_graph_json LONGTEXT        NULL,
    last_known_schema   LONGTEXT        NULL,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    schedule_cron       VARCHAR(120)    NULL,
    next_run_at         DATETIME        NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workflows_user (user_id),
    KEY idx_workflows_next_run (next_run_at),
    CONSTRAINT fk_workflows_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- job_queue — powers the master cron runner. Triggers enqueue here and return
-- immediately; the runner claims pending jobs safely and resumes long work via
-- state_json across ticks.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_queue (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_id     CHAR(36)        NULL,
    status          ENUM('pending','claimed','running','done','failed') NOT NULL DEFAULT 'pending',
    payload_json    LONGTEXT        NULL,
    state_json      LONGTEXT        NULL,
    attempts        INT UNSIGNED    NOT NULL DEFAULT 0,
    claimed_at      DATETIME        NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_queue_status (status),
    KEY idx_job_queue_workflow (workflow_id),
    CONSTRAINT fk_job_queue_workflow FOREIGN KEY (workflow_id)
        REFERENCES workflows (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- execution_logs — per-run audit trail. Large blobs should be truncated by the
-- engine before insert. Composite index supports the monitoring dashboard.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS execution_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_id     CHAR(36)        NULL,
    status          ENUM('success','failed','partial') NOT NULL DEFAULT 'success',
    input_data      LONGTEXT        NULL,
    output_data     LONGTEXT        NULL,
    error_message   TEXT            NULL,
    executed_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_execution_logs_workflow_time (workflow_id, executed_at),
    CONSTRAINT fk_execution_logs_workflow FOREIGN KEY (workflow_id)
        REFERENCES workflows (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- credentials — encrypted secrets per user (AES-256-GCM at the app layer).
-- iv is the per-record random IV; encrypted_data holds ciphertext + auth tag.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS credentials (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(255)    NOT NULL,
    type            VARCHAR(64)     NOT NULL,
    iv              VARBINARY(255)  NOT NULL,
    encrypted_data  BLOB            NOT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_credentials_user (user_id),
    CONSTRAINT fk_credentials_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION B — ASTROLOGY ENGINE TABLES
-- =============================================================================

-- -----------------------------------------------------------------------------
-- astro_agents — one row per agent. Agent 1 is the Calculation Engine; agents
-- 2-20 are the 19 book agents. grounding_mode defaults to 'grounded' so book
-- agents answer ONLY from their own stored text (strict single-book isolation).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS astro_agents (
    id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agent_name                  VARCHAR(255)    NOT NULL,
    book_label                  VARCHAR(255)    NULL,
    source_text                 LONGTEXT        NULL,
    system_instruction_template LONGTEXT        NULL,
    grounding_mode              ENUM('grounded','style','hybrid')        NOT NULL DEFAULT 'grounded',
    prediction_type             ENUM('standard','daily','monthly','yearly') NOT NULL DEFAULT 'standard',
    is_active                   TINYINT(1)      NOT NULL DEFAULT 1,
    created_by_staff_id         BIGINT UNSIGNED NULL,
    created_at                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_astro_agents_active (is_active),
    KEY idx_astro_agents_prediction_type (prediction_type),
    CONSTRAINT fk_astro_agents_staff FOREIGN KEY (created_by_staff_id)
        REFERENCES staff (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- agent_knowledge — the retrievable Markdown chunks per book (ground truth).
-- Kept even after the digest is built so a reading can fetch an exact passage.
-- heading_path is the natural retrieval anchor, e.g. 'Chapter 4 > Mars > 7th'.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS agent_knowledge (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agent_id        BIGINT UNSIGNED NOT NULL,
    chunk_index     INT UNSIGNED    NOT NULL,
    heading_path    VARCHAR(512)    NULL,
    markdown_text   LONGTEXT        NOT NULL,
    embedding_json  LONGTEXT        NULL,
    topic_tags      VARCHAR(512)    NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_agent_knowledge_agent_chunk (agent_id, chunk_index),
    CONSTRAINT fk_agent_knowledge_agent FOREIGN KEY (agent_id)
        REFERENCES astro_agents (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- agent_digest — the compiled structured summary per agent (significations,
-- yogas, dasha effects, remedies). One current digest per agent; version lets
-- Module 7 keep prior versions for rollback.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS agent_digest (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agent_id        BIGINT UNSIGNED NOT NULL,
    digest_json     LONGTEXT        NOT NULL,
    version         INT UNSIGNED    NOT NULL DEFAULT 1,
    compiled_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_agent_digest_agent_version (agent_id, version),
    CONSTRAINT fk_agent_digest_agent FOREIGN KEY (agent_id)
        REFERENCES astro_agents (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- command_usage_logs — one row per reading command. Drives daily tier limits
-- (TierGuard, Module 4). Index supports "how many commands today" lookups.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS command_usage_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    executed_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    agents_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    output_language VARCHAR(16)     NOT NULL DEFAULT 'en',
    prediction_type ENUM('standard','daily','monthly','yearly') NOT NULL DEFAULT 'standard',
    location_used   ENUM('birth','current') NOT NULL DEFAULT 'birth',
    prediction_date DATE            NULL,
    PRIMARY KEY (id),
    KEY idx_command_usage_user_time (user_id, executed_at),
    CONSTRAINT fk_command_usage_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- workflow_conclusions — the synthesized master conclusion (Pro/Max). Linked to
-- the command that produced it. questions_asked tracks the Max Q&A counter.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS workflow_conclusions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    command_log_id  BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    output_language VARCHAR(16)     NOT NULL DEFAULT 'en',
    final_summary   LONGTEXT        NULL,
    questions_asked SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workflow_conclusions_user (user_id),
    KEY idx_workflow_conclusions_command (command_log_id),
    CONSTRAINT fk_workflow_conclusions_command FOREIGN KEY (command_log_id)
        REFERENCES command_usage_logs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_workflow_conclusions_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- agent_qa_history — the Max-tier follow-up Q&A exchanges for a conclusion.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS agent_qa_history (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conclusion_id   BIGINT UNSIGNED NOT NULL,
    question_text   TEXT            NOT NULL,
    answer_text     LONGTEXT        NULL,
    asked_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_agent_qa_history_conclusion (conclusion_id),
    CONSTRAINT fk_agent_qa_history_conclusion FOREIGN KEY (conclusion_id)
        REFERENCES workflow_conclusions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- app_settings — admin-editable config read by the engine (ayanamsa, tier
-- limits, enabled languages, default location mode, and the shared disclaimer
-- text so Modules 5b/5c/5d can pull it from one place before Module 9 finalizes
-- the wording). Key is unique so settings can be upserted by key.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_settings (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key         VARCHAR(128)    NOT NULL,
    setting_value       LONGTEXT        NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_app_settings_key (setting_key),
    CONSTRAINT fk_app_settings_staff FOREIGN KEY (updated_by_staff_id)
        REFERENCES staff (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- SECTION C — SEED DATA
-- =============================================================================

-- -----------------------------------------------------------------------------
-- app_settings seed — engine defaults the spec names explicitly. ayanamsa
-- defaults to 'lahiri'. The disclaimer row is the single shared source Modules
-- 5b/5c/5d read; Module 9 overwrites setting_value with final wording later.
-- -----------------------------------------------------------------------------
INSERT INTO app_settings (setting_key, setting_value) VALUES
    ('ayanamsa', 'lahiri'),
    ('default_location_mode', 'birth'),
    ('enabled_languages', 'en,hi,pa,es,fr,ar,zh,bn,pt,ru,ur,id,de,ja,ta,te,mr,gu,it'),
    ('tier_limit_free_commands',  '3'),
    ('tier_limit_free_agents',    '3'),
    ('tier_limit_pro_commands',   '10'),
    ('tier_limit_pro_agents',     '6'),
    ('tier_limit_max_commands',   '25'),
    ('tier_limit_max_agents',     '9'),
    ('max_qa_question_cap',       '5'),
    ('reading_disclaimer', 'For entertainment and educational purposes only. Astrological readings are not a substitute for professional medical, legal, financial, or psychological advice. No specific outcome is guaranteed. Decisions you make are your own responsibility.')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- -----------------------------------------------------------------------------
-- REMINDER FOR MODULE 3c: the two time-based Gochar agents are intentionally
-- NOT seeded here — their books are uploaded later via Module 7. When Module 3c
-- is built it MUST still create the two agent rows so the time-based logic has
-- agents to attach to, even with empty book content:
--   * Daily Gochar Agent  (Moon-based)  -> prediction_type = 'daily'   grounding_mode = 'grounded'
--   * Monthly Gochar Agent (Sun-based)  -> prediction_type = 'monthly' grounding_mode = 'grounded'
-- (Tajik Neelkanthi already covers 'yearly' and IS seeded below.)
--
-- astro_agents seed — Agent 1 (Calculation Engine) + the 19 book agents.
-- The calculator has no book and is not grounded-text driven; the book agents
-- default to grounding_mode = 'grounded' (strict single-book isolation). The
-- three time-based agents (Module 3c) carry their daily/monthly/yearly
-- prediction_type; all others are 'standard'. source_text / digest / chunks are
-- populated later by the Module 3 ingestion pipeline.
-- -----------------------------------------------------------------------------
INSERT INTO astro_agents
    (agent_name, book_label, grounding_mode, prediction_type, is_active)
VALUES
    ('Calculation Engine', NULL, 'style', 'standard', 1),

    ('Brihat Parashara Hora Shastra Agent', 'Brihat Parashara Hora Shastra', 'grounded', 'standard', 1),
    ('Phaldeepika Agent',                   'Phaldeepika',                   'grounded', 'standard', 1),
    ('Lal Kitab Agent',                     'Lal Kitab',                     'grounded', 'standard', 1),
    ('Brihat Jataka Agent',                 'Brihat Jataka',                 'grounded', 'standard', 1),
    ('Yantra Chintamani Agent',             'Yantra Chintamani',             'grounded', 'standard', 1),
    ('Bhrigu Nandi Nadi Agent',             'Bhrigu Nandi Nadi',             'grounded', 'standard', 1),
    ('Ravan Samhita Agent',                 'Ravan Samhita',                 'grounded', 'standard', 1),
    ('Muhurta Chintamani Agent',            'Muhurta Chintamani',            'grounded', 'standard', 1),
    ('Tajik Neelkanthi Agent',              'Tajik Neelkanthi',              'grounded', 'yearly',   1),
    ('Uttara Kalamrita Agent',              'Uttara Kalamrita',              'grounded', 'standard', 1),
    ('Saravali Agent',                      'Saravali',                      'grounded', 'standard', 1),
    ('Prashna Marga Agent',                 'Prashna Marga',                 'grounded', 'standard', 1),
    ('Mudra Vigyan Agent',                  'Mudra Vigyan',                  'grounded', 'standard', 1),
    ('Ratna Pradipika Agent',               'Ratna Pradipika',               'grounded', 'standard', 1),
    ('The Picatrix Agent',                  'The Picatrix',                  'grounded', 'standard', 1),
    ('Three Books of Occult Philosophy Agent', 'Three Books of Occult Philosophy', 'grounded', 'standard', 1),
    ('De Vita Libri Tres Agent',            'De Vita Libri Tres',            'grounded', 'standard', 1),
    ('Culpeper''s Herbal Agent',            'Culpeper''s Herbal',            'grounded', 'standard', 1),
    ('Kalachakra Tantra Agent',             'Kalachakra Tantra',             'grounded', 'standard', 1);

-- =============================================================================
-- End of Module 1 migration.
-- =============================================================================
