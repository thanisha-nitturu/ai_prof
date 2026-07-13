<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/grade/report/grader/lib.php');

$courseid = 2; // Assuming 2 is the course ID, or we can fetch the first course
$course = $DB->get_record('course', ['id' => $courseid]);
$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'grader', 'courseid' => $courseid]);
$context = context_course::instance($courseid);
$report = new grade_report_grader($courseid, $gpr, $context);

$levels = $report->gtree->get_levels();
echo "Total levels returned by get_levels(): " . count($levels) . "\n\n";

foreach ($levels as $index => $level_row) {
    echo "LEVEL $index:\n";
    foreach ($level_row as $element) {
        $type = $element['type'] ?? 'unknown';
        $name = isset($element['object']) ? $element['object']->get_name() : 'N/A';
        echo "  - Type: $type | Name: $name\n";
    }
}
