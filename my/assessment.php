<?php
require_once('../config.php');
require_login();

$PAGE->set_url('/my/assessment.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Assessment');
$PAGE->set_heading('Assessment');

echo $OUTPUT->header();

// Mount point
echo "<div id='local-veda-root'></div>";

// Load CSS
foreach (glob(__DIR__.'/../local/veda/static/build/assets/*.css') as $file) {
    $fname = basename($file);
    echo "<link rel='stylesheet' href='{$CFG->wwwroot}/local/veda/static/build/assets/{$fname}' />";
}

// Load JS
echo "<script>";
foreach (glob(__DIR__.'/../local/veda/static/build/assets/*.js') as $file) {
    $fname = basename($file);
    $url   = $CFG->wwwroot . "/local/veda/static/build/assets/{$fname}";
    echo "
        var s = document.createElement('script');
        s.src = '{$url}';
        s.type = 'module';
        document.body.appendChild(s);
    ";
}
echo "</script>";

echo $OUTPUT->footer();