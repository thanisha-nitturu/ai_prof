<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/externallib.php');
require_once($CFG->libdir . '/completionlib.php');

require_login();
require_sesskey();

header('Content-Type: application/json');

echo json_encode(
    \local_placement\service\placement_course_service::get_user_placement_courses(
        $USER->id
    ),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);