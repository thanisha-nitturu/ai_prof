<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/grade/report/grader/lib.php');

$courseid = 2; // Assuming course ID is 2 based on devlearn
$course = $DB->get_record('course', ['id' => $courseid]);
$context = context_course::instance($course->id);
$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'grader', 'courseid' => $course->id]);
$report = new gradereport_grader($course->id, $gpr, $context);

$actual_levels = [];
foreach ($report->gtree->get_levels() as $level_row) {
    $has_content = false;
    foreach ($level_row as $element) {
        $type = $element['type'];
        if ($type === 'category') {
            if (grade_tree::can_output_item($element)) {
                $has_content = true;
                break;
            }
        } else if ($type !== 'filler' && $type !== 'fillerfirst' && $type !== 'fillerlast') {
            $has_content = true;
            break;
        }
    }
    if ($has_content) {
        $actual_levels[] = $level_row;
    }
}

echo "Total actual levels before slice: " . count($actual_levels) . "\n";
foreach ($actual_levels as $index => $level_row) {
    echo "Level $index:\n";
    foreach ($level_row as $element) {
        echo "  - Type: " . $element['type'] . ", Colspan: " . ($element['colspan'] ?? 1);
        if (isset($element['object']->itemname)) echo ", Name: " . $element['object']->itemname;
        if (isset($element['object']->fullname)) echo ", Name: " . $element['object']->fullname;
        echo "\n";
    }
}
