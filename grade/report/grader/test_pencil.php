<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/grade/report/grader/lib.php');

\core\session\manager::set_user($DB->get_record('user', ['username' => 'admin']));

$courseid = 2; // assuming course ID 2
$course = $DB->get_record('course', array('id' => $courseid));
$context = context_course::instance($courseid);
$gpr = new grade_plugin_return(array('type' => 'report', 'plugin' => 'grader', 'courseid' => $courseid));
$report = new grade_report_grader($courseid, $gpr, $context);

$rows = $report->get_right_rows(false);
foreach ($rows as $row) {
    foreach ($row->cells as $cell) {
        if (!empty($cell->text)) {
            if (strpos(strtolower($cell->text), 'edit') !== false || strpos(strtolower($cell->text), 'pencil') !== false || strpos(strtolower($cell->text), 'fa-') !== false) {
                echo "Cell HTML:\n";
                echo $cell->text . "\n\n";
                break 2;
            }
        }
    }
}
echo "\nDone.\n";
