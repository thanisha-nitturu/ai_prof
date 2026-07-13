<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/grade/report/grader/lib.php');

$courseid = $DB->get_field('course', 'id', ['idnumber' => ''], IGNORE_MISSING) ?: 2; // guess courseid
$context = context_course::instance($courseid);
$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'grader', 'courseid' => $courseid]);
$report = new grade_report_grader($courseid, $gpr, $context);
$report->get_grade_table(true);
$rows = $report->get_left_rows(true);
$avgrows = $report->get_left_avg_row($rows, 2, false);

$html = '';
foreach ($avgrows as $row) {
    if ($row->attributes['class'] ?? '' !== '') {
        $html .= "<tr class='" . $row->attributes['class'] . "'>\n";
    }
    foreach ($row->cells as $cell) {
        $html .= "  <th class='" . ($cell->attributes['class'] ?? '') . "'>" . $cell->text . "</th>\n";
    }
    $html .= "</tr>\n";
}
echo $html;
