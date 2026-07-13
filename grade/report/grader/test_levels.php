<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/moodle/config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/report/lib.php');
require_once($CFG->dirroot . '/grade/report/grader/lib.php');
$courseid = 2;
$course = $DB->get_record('course', ['id' => $courseid]);
$context = context_course::instance($course->id);
$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'grader', 'courseid' => $course->id]);
$report = new grade_report_grader($course->id, $gpr, $context);
echo "Total levels: " . count($report->gtree->get_levels()) . "\n";
foreach ($report->gtree->get_levels() as $idx => $level) {
    echo "Level $idx:\n";
    foreach ($level as $element) {
        echo "  - Type: " . $element['type'] . "\n";
        if (isset($element['object'])) {
             echo "    Name: " . $element['object']->get_name() . "\n";
        }
    }
}
