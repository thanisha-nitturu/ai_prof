<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');

global $DB, $CFG;

$email = 'karavabhanu@konamfoundation.org';
$user = $DB->get_record('user', ['email' => $email]);

if (!$user) {
    die("User not found\n");
}

echo "Testing for user: {$user->email} (ID: {$user->id})\n";

$course_id = 279;

require_once(__DIR__ . '/mod/coursedashboard/api/handlers/get_assignment_grades.php');

// We can't include the handler directly if it assumes web request and uses $USER from session.
// Wait, in CLI_SCRIPT, $USER is usually the guest user unless we log in.
\core\session\manager::set_user($user);

echo "\n--- MOODLE ASSIGNMENTS ---\n";
$moodleAssignments = fetchAssignmentGradesForUser($DB, $course_id, $user->id);
foreach($moodleAssignments as $ma) {
    echo "ID: {$ma['id']}, Name: {$ma['name']}, Section: {$ma['sectionName']}, Status: {$ma['status']}, Grade: {$ma['grade']}\n";
}

echo "\n--- VEDA ASSIGNMENTS (via FastAPI) ---\n";
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
if (isset($vedaAssigns['assignments'])) {
    foreach($vedaAssigns['assignments'] as $va) {
        echo "ID: {$va['id']}, Name: {$va['name']}, Section: {$va['sectionName']}, Status: {$va['status']}, Score: {$va['finalScore']}\n";
    }
} else {
    echo "No assignments from Veda or error: $response\n";
}

echo "\n--- VEDA QUIZZES (via FastAPI) ---\n";
$fastapiUrlQuiz = "{$CFG->veda_fastapi_url}/api/quiz-results/";
$ch = curl_init($fastapiUrlQuiz);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$responseQuiz = curl_exec($ch);
curl_close($ch);
$vedaQuizzes = json_decode($responseQuiz, true);
if (isset($vedaQuizzes['quizzes'])) {
    foreach($vedaQuizzes['quizzes'] as $vq) {
        echo "Title: {$vq['title']}, Module: {$vq['moduleName']}, Score: {$vq['totalScore']}/{$vq['maxScore']}, Pct: {$vq['percentage']}%\n";
    }
} else {
    echo "No quizzes from Veda or error: $responseQuiz\n";
}

