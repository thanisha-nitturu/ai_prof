<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/grade/report/grader/lib.php');

$courseid = 2; // Assuming course ID is 2
$course = $DB->get_record('course', ['id' => $courseid]);
$context = context_course::instance($course->id);
$gpr = new grade_plugin_return(['type' => 'report', 'plugin' => 'grader', 'courseid' => $course->id]);
$report = new gradereport_grader($course->id, $gpr, $context);

$leftrows = $report->get_left_rows(false);
$rightrows = $report->get_right_rows(false);

$fulltable = new html_table();
$fulltable->attributes['class'] = 'gradereport-grader-table d-none';
$fulltable->id = 'user-grades';

for ($i=0; $i<4; $i++) {
    $row = clone $leftrows[$i];
    $row->cells = array_merge($row->cells, $rightrows[$i]->cells);
    $fulltable->data[] = $row;
}

echo html_writer::table($fulltable);
