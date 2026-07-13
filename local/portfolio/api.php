<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');

// Determine if this is a public route
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$is_public_route = (strpos($path, '/public/portfolio/') === 0);

if (!$is_public_route) {
    // Require login to verify user session for non-public routes
    require_login();

    // Determine user role
    $systemcontext = context_system::instance();
    $isadmin = is_siteadmin() || 
               has_capability('moodle/site:config', $systemcontext) || 
               has_capability('moodle/course:create', $systemcontext);
    $userrole = $isadmin ? 'university' : 'student';

    // Read secret key from the backend .env if available
    $backend_env_path = '/home/hledu/placement_and_portfolio_plugins/portfolio_22062026/backend/.env';
    $secret = 'change-me-in-production'; // Fallback
    if (file_exists($backend_env_path)) {
        $env = parse_ini_file($backend_env_path);
        if (isset($env['JWT_SECRET_KEY'])) {
            $secret = $env['JWT_SECRET_KEY'];
        }
    }

    // Generate HS256 JWT token for backend authentication
    if (!function_exists('base64url_encode')) {
        function base64url_encode($data) {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }
    }

    if (!function_exists('generate_jwt')) {
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
    }

    $jwt_token = generate_jwt($USER, $userrole, $secret);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $jwt_token,
        'X-Moodle-Sesskey: ' . sesskey(),
        'X-User-Role: ' . $userrole,
        'X-User-Id: ' . $USER->id,
        'X-User-Name: ' . fullname($USER),
        'X-User-Email: ' . $USER->email,
    ];
} else {
    $headers = [
        'Content-Type: application/json',
    ];
}

// Proxy settings — build the backend URL
$backend_url = 'http://localhost:8090/api' . $path;

// Forward any remaining query params but exclude auth params
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

// Handle file uploads (resume)
if (!empty($_FILES)) {
    $post_fields = [];
    foreach ($_POST as $key => $val) {
        $post_fields[$key] = $val;
    }
    foreach ($_FILES as $key => $file_info) {
        $cfile = new CURLFile($file_info['tmp_name'], $file_info['type'], $file_info['name']);
        $post_fields[$key] = $cfile;
    }
    $body = $post_fields;
    
    // Modify Content-Type for file upload
    $headers = array_filter($headers, function($h) {
        return stripos($h, 'Content-Type:') !== 0;
    });
}

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
