<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');

$email = 'karavabhanu@konamfoundation.org';
$fastapiUrl = "{$CFG->veda_fastapi_url}/api/assignment-results/";
$payload = [
    'user_email' => $email,
    'courseName' => 'Python Programming',
    'moodle_session_id' => 'cli_test'
];
$ch = curl_init($fastapiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$response = curl_exec($ch);
curl_close($ch);
$vedaAssigns = json_decode($response, true);
echo "--- VEDA ASSIGNMENTS ---\n";
if (isset($vedaAssigns['assignments'])) {
    foreach($vedaAssigns['assignments'] as $va) {
        echo "ID: {$va['id']}, Name: {$va['name']}, Section: {$va['sectionName']}, Status: {$va['status']}, Score: {$va['finalScore']}\n";
    }
} else {
    echo "No assignments from Veda or error: $response\n";
}

$fastapiUrlQuiz = "{$CFG->veda_fastapi_url}/api/quiz-results/";
$ch = curl_init($fastapiUrlQuiz);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$responseQuiz = curl_exec($ch);
curl_close($ch);
$vedaQuizzes = json_decode($responseQuiz, true);
echo "\n--- VEDA QUIZZES ---\n";
if (isset($vedaQuizzes['quizzes'])) {
    foreach($vedaQuizzes['quizzes'] as $vq) {
        echo "Title: {$vq['title']}, Module: {$vq['moduleName']}, Score: {$vq['totalScore']}/{$vq['maxScore']}, Pct: {$vq['percentage']}%\n";
    }
} else {
    echo "No quizzes from Veda or error: $responseQuiz\n";
}
