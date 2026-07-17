<?php
declare(strict_types=1);

namespace AutoBusiness\Core;

/**
 * Static-asset cache-busting helper.
 *
 * Asset::url('/assets/js/app.js') returns '/assets/js/app.js?v=<mtime>' so the
 * browser fetches a fresh copy whenever the file changes — no manual version
 * bumping. Pairs with long-cache headers on the versioned URLs and no-cache on
 * the HTML page (see public_html/.htaccess and Asset::noCacheHtml()).
 */
final class Asset
{
    /** Web path with an automatic ?v=<file-mtime> cache-busting query. */
    public static function url(string $path): string
    {
        $file = AB_ROOT . '/public_html' . $path;
        $v = is_file($file) ? (string) filemtime($file) : null;
        return $v !== null ? $path . '?v=' . $v : $path;
    }

    /** Tell the browser to always revalidate the HTML page (never serve stale). */
    public static function noCacheHtml(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
}
