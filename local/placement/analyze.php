<?php
/**
 * analyze.php — Placement plugin data handler
 *
 * Receives a JSON payload from the React frontend containing:
 *   - resume_name, resume_data  (Base64 PDF Data URL stored in MongoDB)
 *   - target_role               (student's target job title)
 *   - applications              (student's application records + company metadata)
 *   - courses                   (Moodle placement-track courses with progress)
 *
 * Validates the Moodle session and CSRF sesskey, then forwards the payload
 * to the Veda FastAPI service at $CFG->veda_fastapi_url/api/placement/analyze
 * using cURL — mirroring the pattern used by local_veda handler classes.
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

// ── Authentication & CSRF ─────────────────────────────────────────────────────
// TODO(security): Moodle's require_sesskey() validates the CSRF token embedded
// in the sesskey query parameter.  This is the standard Moodle CSRF guard.
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

// Enforce required top-level keys.
$required = ['resume_name', 'resume_data', 'target_role', 'applications', 'courses'];
foreach ($required as $key) {
    if (!array_key_exists($key, $payload)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => "Missing required field: {$key}"]);
        exit;
    }
}

// Validate resume_data is a PDF Data URL to prevent arbitrary binary injection.
// TODO(security): Server-side magic-byte verification with a PDF library would
// be a stronger guarantee; this prefix check is the first line of defence.
$resume_data = $payload['resume_data'];
if (!empty($resume_data) && strpos($resume_data, 'data:application/pdf;base64,') !== 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'resume_data must be a PDF Data URL']);
    exit;
}

// Enforce a server-side size cap on the base64 resume string (~7 MB encoded ≈ 5 MB PDF).
$max_resume_length = 7 * 1024 * 1024;
if (strlen($resume_data) > $max_resume_length) {
    http_response_code(413);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Resume exceeds the maximum allowed size of 5 MB']);
    exit;
}

// ── Enrich payload with Moodle user context ───────────────────────────────────
global $USER, $CFG;

$payload['moodle_user_id']    = (int) $USER->id;
$payload['moodle_username']   = fullname($USER);
$payload['moodle_user_email'] = $USER->email;

// ── Debugging: Return the payload instead of forwarding to Veda FastAPI ───────
//-----------------------------------------------------------------------------------
// header('Content-Type: application/json');

// echo json_encode([
//     'message' => 'Payload that would be sent to Veda',
//     'payload' => $payload
// ], JSON_PRETTY_PRINT);

// exit;
//-----------------------------------------------------------------------------------
//------- Remove the above debugging block to enable actual forwarding to Veda FastAPI -------

// ── Forward to Veda FastAPI ───────────────────────────────────────────────────
// $CFG->veda_fastapi_url is set in config.php, e.g. 'http://localhost:8066'

$fastapi_base = rtrim($CFG->veda_fastapi_url, '/');
$fastapi_url  = "{$fastapi_base}/api/placement/analyze";

// echo json_encode([
//     'fastapi_url' => $fastapi_url
// ]);
// exit;


$ch = curl_init($fastapi_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    // Pass Moodle user identity so Veda FastAPI can log/trace the request.
    'X-Moodle-User-Id: '    . (int) $USER->id,
    'X-Moodle-User-Email: ' . $USER->email,
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

// ── Handle cURL errors ────────────────────────────────────────────────────────
if ($response === false) {
    // Log error server-side but return a generic message to the client.
    error_log("[local_placement/analyze.php] cURL error contacting Veda FastAPI: {$curl_err}");
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Analysis service is temporarily unavailable. Please try again later.']);
    exit;
}

// ── Relay the FastAPI response verbatim ──────────────────────────────────────
http_response_code($http_code);
header('Content-Type: application/json');
echo $response;




































