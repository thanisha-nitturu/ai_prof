<?php
require_once(__DIR__ . '/../../config.php');

// Ensure user is logged in
require_login();

// The FastAPI backend URL
$backend_base = 'http://localhost:8000';

// Get the path info (everything after proxy.php)
$path_info = $_SERVER['PATH_INFO'] ?? '';
if (!$path_info && isset($_SERVER['REQUEST_URI'])) {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $request_uri = explode('?', $_SERVER['REQUEST_URI'])[0];
    if (strpos($request_uri, $script_name) === 0) {
        $path_info = substr($request_uri, strlen($script_name));
    }
}

// Target URL
$target_url = $backend_base . $path_info;
if ($_SERVER['QUERY_STRING']) {
    $target_url .= '?' . $_SERVER['QUERY_STRING'];
}

// Initialize cURL
$ch = curl_init($target_url);

// Forward the request method
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

// Forward the request body for POST/PUT/PATCH
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
    $input = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
}

// Forward relevant headers
$headers = [];
$incoming_headers = getallheaders();
foreach (['Content-Type', 'Authorization', 'session-id', 'moodle-session-id'] as $h) {
    if (isset($incoming_headers[$h])) {
        $headers[] = $h . ': ' . $incoming_headers[$h];
    }
}
// Add Moodle User info for backend usage if needed
$headers[] = 'X-Moodle-User-Id: ' . $USER->id;
$headers[] = 'X-Moodle-Session-Id: ' . session_id();

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

// Execute request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

if (curl_errno($ch)) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['error' => 'Proxy error: ' . curl_error($ch)]);
} else {
    header("HTTP/1.1 $http_code");
    if ($content_type) {
        header("Content-Type: $content_type");
    }
    echo $response;
}

curl_close($ch);
