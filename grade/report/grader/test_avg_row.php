<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/moodle/config.php');
require_once($CFG->dirroot.'/grade/report/grader/lib.php');

$course = reset($DB->get_records('course', [], '', '*', 0, 1));
$context = context_course::instance($course->id);
$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'grader', 'courseid' => $course->id]);
$report = new grade_report_grader($course->id, $gpr, $context);
$rows = $report->get_left_avg_row([], 1, false);
print_r(array_map(function($r) { return $r->attributes; }, $rows));
print_r(array_map(function($c) { return $c->attributes; }, $rows[0]->cells));
echo "\n";
