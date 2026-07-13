<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot.'/grade/report/grader/lib.php');

$courseid = 2; // Usually 2 is the first actual course
$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course) {
    $courseid = 1;
    $course = $DB->get_record('course', ['id' => $courseid]);
}

$context = context_course::instance($courseid);
$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'grader', 'courseid' => $courseid]);
$report = new grade_report_grader($courseid, $gpr, $context);

$levels = $report->gtree->get_levels();
echo "Total levels: " . count($levels) . "\n";
foreach ($levels as $idx => $row) {
    echo "--- LEVEL $idx ---\n";
    foreach ($row as $element) {
        $type = $element['type'] ?? 'unknown';
        $name = isset($element['object']) ? $element['object']->get_name() : '';
        $colspan = $element['colspan'] ?? 1;
        $rowspan = $element['rowspan'] ?? 1;
        $can_output = ($type === 'category') ? grade_tree::can_output_item($element) : 'N/A';
        
        echo "Type: str_pad($type, 15) | Name: str_pad($name, 20) | Colspan: $colspan | Rowspan: $rowspan | can_output: $can_output\n";
    }
}
