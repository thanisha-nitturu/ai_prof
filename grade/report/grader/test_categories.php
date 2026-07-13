<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/report/lib.php');
require_once($CFG->dirroot . '/grade/report/grader/lib.php');

$course = $DB->get_record('course', array('id' => 2));
if (!$course) $course = $DB->get_record('course', array('id' => 1));

$context = context_course::instance($course->id);
$report = new grade_report_grader($course->id, new stdClass(), $context);

$levels = $report->gtree->get_levels();
echo "Number of levels: " . count($levels) . "\n";
foreach ($levels as $idx => $level) {
    echo "Level $idx:\n";
    foreach ($level as $element) {
        $name = isset($element['object']) ? $element['object']->get_name() : $element['type'];
        echo "  - Type: " . $element['type'] . ", Name: " . $name . ", colspan: " . (isset($element['colspan']) ? $element['colspan'] : 1) . "\n";
    }
}
