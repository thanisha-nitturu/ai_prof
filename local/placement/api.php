<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');

// Require login to verify user session
require_login();

// Determine user role
$systemcontext = context_system::instance();
$isadmin = is_siteadmin() || 
           has_capability('moodle/site:config', $systemcontext) || 
           has_capability('moodle/course:create', $systemcontext);
$userrole = $isadmin ? 'university' : 'student';

// Read secret key from the backend .env if available
$backend_env_path = '/home/hledu/placement_and_portfolio_plugins/placement_08062026/backend/.env';
$secret = 'change-this-to-a-long-random-secret'; // Fallback
if (file_exists($backend_env_path)) {
    $env = parse_ini_file($backend_env_path);
    if (isset($env['SECRET_KEY'])) {
        $secret = $env['SECRET_KEY'];
    }
}

// Generate HS256 JWT token for backend authentication
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generate_jwt($user, $role, $secret) {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'sub' => $user->email,
        'role' => $role,
        'exp' => time() + 3600, // Expires in 1 hour
    ]);

    $base64UrlHeader = base64url_encode($header);
    $base64UrlPayload = base64url_encode($payload);

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64url_encode($signature);

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

$jwt_token = generate_jwt($USER, $userrole, $secret);

// Proxy settings — build the backend URL, stripping Moodle-specific query params
// that are only needed as headers (sesskey, userRole, userId, userName, userEmail).
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$backend_url = 'http://localhost:8000/api/v1' . $path;

// Forward any remaining query params (e.g. filters) but exclude the auth params
// that are already sent as headers to avoid confusion.
$moodle_auth_params = ['sesskey', 'userRole', 'userId', 'userName', 'userEmail'];
$raw_qs = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
$forwarded_params = [];
if (!empty($raw_qs)) {
    parse_str($raw_qs, $qs_pairs);
    foreach ($qs_pairs as $k => $v) {
        if (!in_array($k, $moodle_auth_params)) {
            $forwarded_params[$k] = $v;
        }
    }
}
if (!empty($forwarded_params)) {
    $backend_url .= '?' . http_build_query($forwarded_params);
}

$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');

$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $jwt_token,
    // All five identity headers required by the FastAPI auth dependency (dependencies/auth.py).
    // X-User-Id is critical — without it the backend falls back to the JWT sub (email)
    // and records an inconsistent student_id.
    'X-Moodle-Sesskey: ' . sesskey(),
    'X-User-Role: ' . $userrole,
    'X-User-Id: ' . $USER->id,
    'X-User-Name: ' . fullname($USER),
    'X-User-Email: ' . $USER->email,
];

// Initialize cURL session
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $backend_url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

if ($method !== 'GET' && $method !== 'HEAD') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    $http_code = 500;
    $response = json_encode(['error' => 'Proxy error: ' . $error_msg]);
    $content_type = 'application/json';
}
curl_close($ch);

// Send the response back to Moodle page
http_response_code($http_code);
if ($content_type) {
    header('Content-Type: ' . $content_type);
} else {
    header('Content-Type: application/json');
}
echo $response;
