<?php
declare(strict_types=1);

/**
 * Admin-only visual canvas (Module 2). Staff build automations here; clients and
 * astrologers never see this screen (they get the Module 5d screens).
 *
 * Canvas library: Drawflow (MIT) via CDN. Tailwind via CDN in dev, per Global
 * Rules. The CSRF token is emitted into a meta tag for the save endpoint.
 */

use AutoBusiness\Core\AdminGuard;
use AutoBusiness\Core\Asset;
use AutoBusiness\Core\Csrf;

AdminGuard::require();
Asset::noCacheHtml(); // HTML always revalidated (cache-busting)
$csrf = Csrf::token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <title>Auto Business — Workflow Canvas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.css">
    <style>
        #canvas { height: calc(100vh - 4rem); }
        .drawflow .drawflow-node { background:#1f2937; color:#f9fafb; border:1px solid #374151; border-radius:.5rem; min-width:180px; }
        .drawflow .drawflow-node .title { font-weight:600; padding:.25rem .5rem; border-bottom:1px solid #374151; }
        .drawflow .connection .main-path { stroke:#60a5fa; stroke-width:2px; }
    </style>
</head>
<body class="bg-gray-100 text-gray-900">
    <header class="h-16 flex items-center justify-between px-4 bg-gray-900 text-white">
        <div class="flex items-center gap-3">
            <h1 class="font-semibold">Workflow Canvas</h1>
            <input id="wf-name" placeholder="Workflow name"
                   class="px-2 py-1 rounded bg-gray-800 border border-gray-700 text-sm">
            <input id="wf-id" type="hidden">
        </div>
        <div class="flex items-center gap-2">
            <button data-add="webhook"   class="palette px-2 py-1 text-sm rounded bg-emerald-600">+ Webhook</button>
            <button data-add="cron"      class="palette px-2 py-1 text-sm rounded bg-emerald-700">+ Cron</button>
            <button data-add="if"        class="palette px-2 py-1 text-sm rounded bg-amber-600">+ If/Else</button>
            <button data-add="transform" class="palette px-2 py-1 text-sm rounded bg-sky-600">+ Transform</button>
            <button data-add="http"      class="palette px-2 py-1 text-sm rounded bg-indigo-600">+ HTTP</button>
            <button id="save" class="px-3 py-1 text-sm rounded bg-blue-500 font-semibold">Save</button>
            <span id="status" class="text-sm text-gray-300"></span>
        </div>
    </header>

    <div id="canvas" class="w-full bg-gray-200"></div>

    <script src="https://cdn.jsdelivr.net/npm/drawflow@0.0.59/dist/drawflow.min.js"></script>
    <script src="<?= htmlspecialchars(Asset::url('/assets/js/canvas.js'), ENT_QUOTES) ?>"></script>
</body>
</html>
