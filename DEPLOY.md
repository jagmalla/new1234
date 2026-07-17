# Deploying this site to new hosting

This is the **complete source** of the Vedic-astrology website (PHP 8.x, no
framework). No credentials are included — you supply your own on the new host.

## 1. Folder layout (important)

```
project-root/          ← upload ABOVE the web root (NOT web-accessible)
├── app/               ← all PHP classes (PSR-4: AutoBusiness\… → app/…)
├── bootstrap.php      ← autoloader + env loader (MUST stay in the root)
├── runner.php         ← background/cron runner (stays in the root)
├── migrations/        ← MySQL schema + seed data (import in order 001,002,…)
├── docs/              ← rule books / specs (reference only)
├── tests/             ← CLI test harnesses
├── storage/           ← runtime cache (writable; starts empty)
├── .env.example       ← copy to .env and fill in (see step 3)
└── public_html/       ← THE WEB ROOT — point your domain here
    ├── index.php      ← front controller (routes via ?r=)
    ├── .htaccess      ← clean-URL rewrite
    └── assets/        ← css / js / images
```

On cPanel/A2-style hosting: put `public_html/` as your domain's document root,
and keep `app/`, `bootstrap.php`, `.env`, etc. **one level above** it so they
are never served directly.

## 2. Requirements
- PHP 8.1+ (8.4 recommended) with `mysqli`/`pdo_mysql`, `openssl`, `mbstring`.
- MySQL / MariaDB.
- (Optional) Swiss Ephemeris `swetest` binary for arc-second accuracy — set
  `SWETEST_PATH` in `.env`. Without it the built-in ephemeris is used.

## 3. Configuration
1. Copy `.env.example` to `.env` (keep it in the project root, above the webroot).
2. Fill in your database (`DB_HOST/DB_NAME/DB_USER/DB_PASS`).
3. Generate the credential master key and paste it into `CREDENTIAL_MASTER_KEY`:
   ```
   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```
4. (Optional) Set `LLM_API_KEY` only if you use the book-agent/AI features.

## 4. Database
Create a database, then import the SQL files in `migrations/` **in numeric
order** (001, 002, 003, …). Most prediction text lives in these tables; the app
still runs with baked-in fallbacks if the DB is unreachable.

## 5. Go live
Point the domain at `public_html/`. Open the site — the calculator works
immediately. If you see HTTP 500, check that `bootstrap.php` is in the project
root and `.env` has valid values.

---
No `.env`, API keys, or other secrets are bundled here — create your own.
