# Reminder — Module 3c (Time-Based Transit & Year Agents)

The Daily (Moon-based) and Monthly (Sun-based) Gochar agents are **not** seeded
in `migrations/001_master_schema.sql` because their books are uploaded later via
Module 7.

**When Module 3c is built, it must still create these two `astro_agents` rows**
so the time-based logic has agents to attach to, even with empty book content:

| agent | prediction_type | grounding_mode | basis |
|-------|-----------------|----------------|-------|
| Daily Gochar Agent   | `daily`   | `grounded` | current Moon vs natal Moon |
| Monthly Gochar Agent | `monthly` | `grounded` | current Sun transit |

The yearly agent (Tajik Neelkanthi) is already seeded in Module 1.
