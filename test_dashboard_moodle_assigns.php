<?php
define('CLI_SCRIPT', true);
$GLOBALS['request_data'] = ['courseId' => 279];
require(__DIR__ . '/config.php');

global $USER, $DB;
$email = 'karavabhanu@konamfoundation.org';
$user = $DB->get_record('user', ['email' => $email]);
\core\session\manager::set_user($user);

require_once(__DIR__ . '/mod/coursedashboard/api/handlers/get_assignment_grades.php');

// The file get_assignment_grades.php outputs JSON and exits. So we will just look at the JSON output!
