---
name: ship
description: Deliver a finished change to the astrology app — run the 62-check self-test, scan for credentials, record the decision in Hindi, commit with the required trailers, push with backoff, and hand the owner correctly named update files. Use whenever a change is complete and ready to go out, or when the owner says ship / deliver / give me the file / give me the zip.
---

# Ship a change

The delivery ritual for this project. Every step exists because skipping it
caused a real problem before. Do them in order; do not skip one because the
change "looks small".

## 1. Prove it works

```bash
php -l <each changed .php file>          # syntax
node -c <each changed .js file>          # syntax
php tests/lalkitab_process_check.php     # must print: पास: 62 · फेल: 0
```

`LK_BASE` is already exported by the session-start hook. If the suite cannot
reach the site, the dev server is down — re-run
`.claude/hooks/session-start.sh`.

**A red check is a stop.** Find out whether the product or the check is wrong
before going further — both have been the culprit. If you fixed a check, prove
it now catches the fault: reintroduce the fault, watch it go red, restore, watch
it go green.

**A UI change is not verified by numbers alone.** Render it and *look* at the
screenshot. Measurements have said "fits exactly" while every value on screen
read `मि… Ge…`. Test at 1920 / 1440 / 820 / 390 where layout matters.

Remember the sandbox has no Tailwind CDN: `.hidden`, `mx-auto`, `grid-cols-*`
must be injected to simulate production, or the layout will look wrong here and
right in production.

## 2. Never ship a secret

```bash
git diff -- <changed files> | grep -iE "password|secret|api[_-]?key|token|bearer|AKIA|ghp_|sk-" | grep '^\+'
```

Must come back empty.

## 3. Record the decision

Append a section to `docs/LALKITAB_DECISIONS.md`, in Hindi, covering:
what was asked, what was actually wrong (the cause, not the symptom), what
changed, and what was measured. Frequencies and measurements, not adjectives.

## 4. Commit

Never mention the model name anywhere in a commit, PR, or code comment.
The message must end with exactly these two lines:

```
Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01PEe7MTRHVeppoGi3kv1pA2
```

Before committing, make sure `_devrouter.php` is not staged (it is gitignored,
so this should take care of itself).

## 5. Push

```bash
git push -u origin claude/astrology-software-mods-ccwpjf
```

Only on a network error, retry up to 4 times with 2s, 4s, 8s, 16s backoff.
Never push to another branch. Never open a pull request unless asked.

## 6. Hand over the files

The owner uploads files to a server by hand, so give exactly what changed:

- **Serial number** = `git rev-list --count HEAD` *after* the commit. It only
  ever goes up and is never reused.
- **One or two files changed** → send the files themselves, named
  `<serial>_<originalname>.php`, and say which path each one overwrites.
- **Three or more, or anything needing folder structure** → send one zip named
  `<serial>_<short-change-description>.zip`, built with
  `zip "$OUT" path/to/file …` from the repo root so it keeps its folder paths,
  and tell them to extract into the project root.
- Every delivery gets a **new, different, descriptive name**. Never reuse one.

Finish by telling them to hard-refresh, and mention that assets are
cache-busted by `?v=<file-mtime>` so a changed JS/CSS file needs the file to
actually land on the server.

## 7. Report honestly

Say what was measured, in plain terms. If something was left out or is still
broken, say so plainly and say why. Never claim a check passed without running
it.
