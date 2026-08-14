# Run the whole thing on your own computer (offline)

For Windows. No coding. Nothing to compile. About 15 minutes, once.

When it is done you can open the software from your own machine with the
internet switched off — charts, predictions, Lal Kitab, muhurat, milan, and
city search all work.

---

## What you need to know first

The software is plain PHP files. There is **no installer** and **nothing to
build**. You only need PHP itself — the same way a video file needs a video
player.

MySQL is **optional**. Without it the app falls back to its built-in data and
every page still works. Skip the database entirely for offline use.

---

## Step 1 — Install PHP

1. Go to **https://windows.php.net/download/**
2. Under **PHP 8.4**, download the **Thread Safe** ZIP for **x64**.
3. Create a folder `C:\php` and unzip everything into it.
   You should end up with `C:\php\php.exe`.

### Turn on the two extensions the app uses

1. In `C:\php`, find the file `php.ini-development`.
2. Make a copy of it in the same folder and rename the copy to **`php.ini`**.
3. Open `php.ini` in Notepad. Use Ctrl+F to find each line below and **delete
   the semicolon** at the start:

   ```
   ;extension=mbstring     ->  extension=mbstring
   ;extension=openssl      ->  extension=openssl
   ;extension=pdo_mysql    ->  extension=pdo_mysql
   ```

   (`mbstring` is what makes Hindi text work. `pdo_mysql` is only needed if you
   later add a database, but turning it on now costs nothing.)
4. Save and close.

---

## Step 2 — Put the software on your computer

Put the project folder anywhere, for example `C:\astrology`.

Inside it you must see these folders side by side:

```
C:\astrology\
    app\
    public_html\
    docs\
    migrations\
    storage\
    tests\
```

> **Important:** `public_html` must stay *inside* the project folder, next to
> `app`. Do not move it or flatten the folders — the app looks one level up
> from `public_html` for its own code.

---

## Step 3 — Make the styling work offline

The design (Tailwind) normally loads from the internet. Without this step the
page still works but looks plain and unstyled offline.

**You only do this once, while you still have internet:**

1. Open this address in your browser: **https://cdn.tailwindcss.com**
2. Press **Ctrl+S** and save the file as:

   ```
   C:\astrology\public_html\assets\vendor\tailwind.js
   ```

   Make sure the name is exactly `tailwind.js` (not `tailwind.js.txt` — in the
   save dialog set "Save as type" to **All files**).

That's it. The app checks for that file by itself: if it is there it uses your
local copy, if it is not it goes back to the internet. Nothing else to change.

The Hindi fonts (Mukta, Martel) are **already included** in
`public_html/assets/vendor/fonts/` — you do not need to download those.

---

## Step 4 — Create the starter file

1. Open Notepad.
2. Paste exactly this:

   ```
   @echo off
   cd /d C:\astrology
   set APP_ENV=local
   set PHP_CLI_SERVER_WORKERS=4
   start "" http://localhost:8080/calc
   C:\php\php.exe -S localhost:8080 _devrouter.php
   ```

3. Save it in `C:\astrology` with the name **`START.bat`**
   (in the save dialog set "Save as type" to **All files**, or Windows will
   name it `START.bat.txt` and it will not run).

---

## Step 5 — Create the router file

The website's real host uses a rule file that the built-in PHP server does not
understand, so one small file replaces it.

1. Open Notepad again.
2. Paste exactly this:

   ```php
   <?php
   declare(strict_types=1);
   $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
   $file = __DIR__ . '/public_html' . $path;
   if ($path !== '/' && is_file($file)) {
       if (substr($file, -4) === '.php') { chdir(__DIR__ . '/public_html'); require $file; return true; }
       $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
       header('Content-Type: ' . ([
           'js' => 'application/javascript', 'css' => 'text/css', 'json' => 'application/json',
           'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
           'svg' => 'image/svg+xml', 'gz' => 'application/gzip', 'woff2' => 'font/woff2',
       ][$ext] ?? 'application/octet-stream'));
       readfile($file); return true;
   }
   $r = ltrim($path, '/');
   if ($r !== '' && !isset($_GET['r'])) {
       $_GET['r'] = $r; $_REQUEST['r'] = $r;
       $q = $_SERVER['QUERY_STRING'] ?? '';
       $_SERVER['QUERY_STRING'] = 'r=' . rawurlencode($r) . ($q !== '' ? '&' . $q : '');
   }
   chdir(__DIR__ . '/public_html');
   require __DIR__ . '/public_html/index.php';
   return true;
   ```

3. Save it in `C:\astrology` as **`_devrouter.php`** (again: "Save as type" =
   **All files**).

---

## Step 6 — Start it

Double-click **`START.bat`**.

- A black window opens. **Leave it open** — that black window *is* the server.
- Your browser opens at `http://localhost:8080/calc`.
- Fill in the birth details and press **Create Chart**.

To stop, close the black window. To use it again, double-click `START.bat`.

**Now switch your internet off and reload the page.** Everything should still
work and still look right.

---

## If something goes wrong

| What you see | What it means | Fix |
|---|---|---|
| `'C:\php\php.exe' is not recognized` | PHP is not where the .bat expects | Check `C:\php\php.exe` exists; fix the path in `START.bat` |
| Page says **403** or *"Staff authentication required"* | `APP_ENV=local` did not apply | Start it only by double-clicking `START.bat`, not by running php by hand |
| Page opens but looks plain / no colours | `tailwind.js` is missing or misnamed | Redo Step 3; confirm the file is exactly `public_html\assets\vendor\tailwind.js` |
| Hindi shows as boxes □□□ | `mbstring` is off | Redo the php.ini part of Step 1 |
| Page hangs forever | only one worker | Confirm `set PHP_CLI_SERVER_WORKERS=4` is in `START.bat` |
| Port already in use | 8080 is taken | Change **both** `8080`s in `START.bat` to `8081` |

---

## Keeping it up to date

When you get an update file, copy it over the same file inside `C:\astrology`,
then press **Ctrl+F5** in the browser. Nothing needs reinstalling.

---

## What still needs internet

Almost nothing. Two things quietly upgrade themselves when you are online:

- **City search** — offline it uses the built-in list of 148,038 towns; online
  it can also reach the live geocoder for anything unusual.
- **Your location guess** on the transit page — offline it simply does not
  pre-fill, and you type or pick the place yourself.

Everything that matters — every calculation, every prediction, every chart —
is computed on your own machine and never needed the internet.
