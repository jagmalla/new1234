<?php
/**
 * DB Sync (migrations) admin page — rendered by MigrateController.
 * Scope: $view = ['db_error','db_name','status','result'].
 */
$h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$st = $view['status'];
$res = $view['result'];
$pending = array_values(array_filter($st, static fn ($x) => !$x['applied']));
?><!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DB Sync — Migrations</title>
<style>
  html{font-size:clamp(17px,15.8px + 0.5vw,20.5px);-webkit-text-size-adjust:100%;text-size-adjust:100%}
  img,svg{max-width:100%}
  pre{white-space:pre-wrap;overflow-wrap:anywhere;max-width:100%}
  table{display:block;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
  body{font-family:"Noto Sans Devanagari",system-ui,Arial,sans-serif;background:#f1f5f9;color:#0f172a;margin:0;padding:24px}
  .wrap{max-width:860px;margin:0 auto}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px 22px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
  h1{font-size:1.25rem;margin:0 0 4px}
  .sub{color:#64748b;font-size:.85rem;margin-bottom:12px}
  table{width:100%;border-collapse:collapse;font-size:.85rem}
  th{background:#f8fafc;text-align:left;padding:7px 10px;border-bottom:1px solid #e2e8f0}
  td{padding:6px 10px;border-bottom:1px solid #f1f5f9}
  .chip{display:inline-block;padding:1px 9px;border-radius:999px;font-size:.72rem;font-weight:700}
  .ok{background:#dcfce7;color:#166534}
  .pend{background:#fef9c3;color:#854d0e}
  .err{background:#fee2e2;color:#991b1b}
  .btn{background:#1d4ed8;color:#fff;border:0;border-radius:9px;padding:9px 18px;font-size:.9rem;font-weight:700;cursor:pointer}
  .btn:hover{background:#1e40af}
  .btn.gray{background:#64748b}
  .alert{border-radius:10px;padding:10px 14px;font-size:.86rem;margin-bottom:12px}
  .alert.bad{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
  .alert.good{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
  code{background:#f1f5f9;border-radius:4px;padding:1px 5px;font-size:.8em}
  a{color:#1d4ed8}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>🗄 DB Sync — Migrations Import</h1>
    <div class="sub">Database: <b><?= $h($view['db_name']) ?></b> ·
      <code>migrations/</code> की सभी SQL files क्रम से import होती हैं; लगी हुई files
      <code>schema_migrations</code> में दर्ज रहती हैं, इसलिए यह पेज बार-बार चलाना safe है।
      नई migration file upload करके फिर से "Run pending" दबाएँ — केवल नई ही चलेगी।</div>

    <?php if ($view['db_error'] !== null): ?>
      <div class="alert bad"><b>Database से connection नहीं बना:</b> <?= $h($view['db_error']) ?><br>
        <code>.env</code> में DB_HOST / DB_NAME / DB_USER / DB_PASS जाँचें।</div>
    <?php else: ?>

      <?php if (is_array($res)): ?>
        <?php if (($res['error'] ?? null) !== null): ?>
          <div class="alert bad">
            <b>❌ <?= $h($res['error']['file']) ?> पर रुका:</b> <?= $h($res['error']['message']) ?>
            <?php if ($res['error']['statement'] !== ''): ?><br><small>Statement: <code><?= $h($res['error']['statement']) ?>…</code></small><?php endif; ?>
            <?php if ($res['ran'] !== []): ?><br>इससे पहले <?= count($res['ran']) ?> file(s) सफल रहीं।<?php endif; ?>
          </div>
        <?php elseif ($res['ran'] === []): ?>
          <div class="alert good">✅ सब कुछ पहले से applied है — कोई pending migration नहीं (<?= (int) $res['skipped'] ?> skipped)।</div>
        <?php else: ?>
          <div class="alert good">✅ <b><?= count($res['ran']) ?> migration(s) सफलतापूर्वक लगीं</b> (<?= (int) $res['skipped'] ?> पहले से applied):<br>
            <?php foreach ($res['ran'] as $r): ?>
              • <?= $h($r['file']) ?> — <?= (int) $r['statements'] ?> statements, <?= (int) $r['ms'] ?> ms<br>
            <?php endforeach; ?>
            अब <a href="?r=calc">Calculator खोलें</a> — predictions दिखने लगेंगी।</div>
        <?php endif; ?>
      <?php endif; ?>

      <form method="post" action="?r=admin/migrate/run" style="display:flex;gap:10px;align-items:center;margin-bottom:14px">
        <button type="submit" class="btn">▶ Run pending (<?= count($pending) ?>)</button>
        <button type="submit" class="btn gray" name="force" value="1"
                onclick="return confirm('सभी 26 files दुबारा चलेंगी (idempotent seeds — data दोहराया नहीं जाएगा)। जारी रखें?')">↻ Force re-run all</button>
      </form>

      <table>
        <thead><tr><th>#</th><th>Migration file</th><th>Size</th><th>Status</th><th>Applied at</th></tr></thead>
        <tbody>
        <?php foreach ($st as $i => $x): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><code><?= $h($x['file']) ?></code></td>
            <td><?= number_format($x['size'] / 1024, 1) ?> KB</td>
            <td><?= $x['applied'] ? '<span class="chip ok">✅ applied</span>' : '<span class="chip pend">⏳ pending</span>' ?></td>
            <td><?= $h($x['applied_at'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="card sub" style="margin-top:0">
    💡 यही काम phpMyAdmin से हर file import करके भी हो सकता है, पर यह पेज क्रम, दोहराव व त्रुटि — तीनों सँभालता है।
    · <a href="?r=calc">← Calculator पर वापस</a>
  </div>
</div>
</body>
</html>
