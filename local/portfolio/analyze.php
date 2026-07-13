<?php
/**
 * analyze.php — Portfolio plugin data handler
 *
 * Receives a JSON payload from the frontend.
 * Validates the Moodle session and CSRF sesskey, then forwards the payload
 * to the Veda FastAPI service at $CFG->veda_fastapi_url/api/portfolio/analyze
 * using cURL.
 */

require_once(__DIR__ . '/../../config.php');

// ── Authentication & CSRF ─────────────────────────────────────────────────────
require_login();
require_sesskey();

// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// ── Read and basic-validate the JSON payload ─────────────────────────────────
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

// Optional: you can enforce required top-level keys if needed here
// $required = ['portfolio_data'];
// foreach ($required as $key) {
//     if (!array_key_exists($key, $payload)) {
//         http_response_code(400);
//         header('Content-Type: application/json');
//         echo json_encode(['error' => "Missing required field: {$key}"]);
//         exit;
//     }
// }

// ── Enrich payload with Moodle user context ───────────────────────────────────
global $USER, $CFG;

$systemcontext = context_system::instance();
$isadmin = is_siteadmin() || 
            has_capability('moodle/site:config', $systemcontext) || 
            has_capability('moodle/course:create', $systemcontext);
$userrole = $isadmin ? 'university' : 'student';

header('Content-Type: application/json');

// ── Forward to Veda FastAPI ───────────────────────────────────────────────────
// $CFG->veda_fastapi_url is set in config.php, e.g. 'http://localhost:8066'

$fastapi_base = rtrim($CFG->veda_fastapi_url, '/');
$fastapi_url  = "{$fastapi_base}/api/portfolio/analyze";

$ch = curl_init($fastapi_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-User-Role: ' . $userrole,
    'X-User-Id: ' . $USER->id,
    'X-User-Name: ' . fullname($USER),
    'X-User-Email: ' . $USER->email,
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

// ── Handle cURL errors ────────────────────────────────────────────────────────
if ($response === false) {
    // Log error server-side but return a generic message to the client.
    error_log("[local_portfolio/analyze.php] cURL error contacting Veda FastAPI: {$curl_err}");
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Analysis service is temporarily unavailable. Please try again later.']);
    exit;
}

// ── Relay the FastAPI response verbatim ──────────────────────────────────────
http_response_code($http_code);
header('Content-Type: application/json');
echo $response;
