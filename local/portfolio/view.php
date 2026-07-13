<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Public portfolio view page — no login required.
 *
 * Accessed via a clean URL:
 *   https://site/local/portfolio/p/{slug}
 *
 * The .htaccess in this directory rewrites that clean URL to this file.
 * This file extracts the slug from REQUEST_URI and embeds it in
 * window.PORTFOLIO_CONFIG.shareSlug so the React app renders the correct
 * public portfolio without hash-based routing.
 *
 * Security guarantees:
 *  - NO_MOODLE_COOKIES is defined BEFORE config.php is loaded, which
 *    prevents Moodle from creating or sending the MoodleSession cookie.
 *  - No require_login() — public access is intentional.
 *  - No session info (sesskey, userId, userEmail) is ever embedded in HTML.
 *  - window.PORTFOLIO_CONFIG contains ONLY isPublic, shareSlug, and apiUrl.
 *  - The slug is validated with a strict regex before being used.
 *  - All PHP-generated output is escaped with htmlspecialchars / json_encode.
 *
 * @package    local_portfolio
 * @copyright  2026 DevLearn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// SECURITY: Prevent Moodle from creating or sending the MoodleSession cookie.
// This MUST be defined BEFORE config.php is loaded.
define('NO_MOODLE_COOKIES', true);

// Bootstrap Moodle for $CFG access only — no session, no login check.
require_once(__DIR__ . '/../../config.php');

// ---------------------------------------------------------------------------
// Extract the public slug from the incoming request URI.
// The .htaccess rewrites /local/portfolio/p/{slug} to this file, so the
// original path is still visible in REQUEST_URI.
//
// Accepted slug pattern: letters, digits, hyphens, underscores (3–80 chars)
// ---------------------------------------------------------------------------
$share_slug = null;
$request_uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
// Match both the new clean URL  /p/{slug}
// and the legacy deep path       /local/portfolio/p/{slug}
if (preg_match('#(?:^|/local/portfolio)/p/([A-Za-z0-9][A-Za-z0-9_-]{1,78}[A-Za-z0-9_-])$#', $request_uri, $m)) {
    $share_slug = $m[1]; // Safe: validated by regex above
}

// If slug is missing or invalid, render a 404 page immediately.
if ($share_slug === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Not Found</title></head>'
        . '<body><p style="font-family:sans-serif;padding:2rem">Portfolio not found.</p></body></html>';
    exit;
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/portfolio/view.php', ['slug' => $share_slug]));
$PAGE->set_pagelayout('base');

// Ensure the Moodle theme is fully initialized so we can extract CSS and favicon
$PAGE->initialise_theme_and_output();
global $OUTPUT;

// 1. Favicon — use the app's own SVG shipped with the build.
// Moodle's theme/image.php requires an active session which is suppressed by
// NO_MOODLE_COOKIES, so we reference the bundled SVG directly.
$favicon_url = $CFG->wwwroot . '/local/portfolio/static/build/favicon.svg';

// 2. Get all Moodle Theme CSS URLs (including Boost/Classic styles)
$moodle_css_urls = [];
foreach ($PAGE->theme->css_urls($PAGE) as $css) {
    $moodle_css_urls[] = $css->out(false);
}

// Build the API proxy URL — points to api.php on the same Moodle host.
// api.php itself enforces that /public/portfolio/* requests carry no auth headers.
$apiUrl = $CFG->wwwroot . '/local/portfolio/api.php';

// (Moodle CSS was already collected above — no duplicate initialisation needed.)

// Locate build assets.
$pluginurl = $CFG->wwwroot . '/local/portfolio/static/build';
$jsfile    = __DIR__ . '/static/build/assets/index.js';
$cssfile   = __DIR__ . '/static/build/assets/index.css';
$cachebust = file_exists($jsfile) ? filemtime($jsfile) : time();

// ---------------------------------------------------------------------------
// Security headers
// ---------------------------------------------------------------------------
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
// Prevent the page from accessing powerful browser features
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

// ---------------------------------------------------------------------------
// Emit a fully self-contained HTML document — no Moodle chrome whatsoever.
// ---------------------------------------------------------------------------
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Portfolio</title> <!-- React will update this to "Portfolio-{name}" once data loads -->

  <!-- Favicon — bundled SVG from the React build -->
  <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8'); ?>" />

  <!-- Moodle Global CSS (to ensure both views look identical) -->
  <?php foreach ($moodle_css_urls as $url): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" />
  <?php endforeach; ?>

<?php if (is_readable($cssfile)): ?>
  <!-- Plugin Tailwind CSS -->
  <link rel="stylesheet" href="<?php echo htmlspecialchars($pluginurl . '/assets/index.css?v=' . $cachebust, ENT_QUOTES, 'UTF-8'); ?>" />
<?php endif; ?>
  <script>
    /*
     * SECURITY: window.PORTFOLIO_CONFIG intentionally contains NO session info.
     *
     *  isPublic   → tells React to suppress all edit controls and use the
     *               public (unauthenticated) API endpoints.
     *  shareSlug  → the portfolio's public slug, extracted server-side from
     *               the clean URL and validated before injection.
     *               React renders this directly without hash-based routing.
     *  apiUrl     → points to api.php which enforces no-auth for public routes.
     *
     * No sesskey, no userId, no userEmail, no JWT is ever emitted here.
     */
    window.PORTFOLIO_CONFIG = {
      isPublic:   true,
      shareSlug:  <?php echo json_encode($share_slug); ?>,
      apiUrl:     <?php echo json_encode($apiUrl); ?>
    };
  </script>
</head>
<body>
  <div id="root"></div>
<?php if (is_readable($jsfile)): ?>
  <script type="module" src="<?php echo htmlspecialchars($pluginurl . '/assets/index.js?v=' . $cachebust, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php else: ?>
  <p style="font-family:sans-serif;padding:2rem;color:#c00;">
    Portfolio bundle not found. Please build the frontend and deploy the static assets.
  </p>
<?php endif; ?>
</body>
</html>
