<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/report/lib.php');
require_once($CFG->dirroot . '/grade/report/grader/lib.php');

$courseid = 2;
$course = $DB->get_record('course', ['id' => $courseid]);
$context = context_course::instance($course->id);
$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'grader', 'courseid' => $course->id]);
$report = new grade_report_grader($course->id, $gpr, $context);

echo $report->get_grade_table();
