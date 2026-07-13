<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Portfolio landing page.
 *
 * @package    local_portfolio
 * @copyright  2026 DevLearn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Security: require authenticated login
require_login();

// Security: explicitly deny guest access
if (isguestuser()) {
    redirect(new moodle_url('/'), get_string('noguest'), null, \core\output\notification::NOTIFY_ERROR);
}

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/portfolio/index.php'));
$PAGE->set_title(get_string('pagetitle', 'local_portfolio'));
$PAGE->set_primary_active_tab('portfolio');

$PAGE->add_body_class('portfolio-fullwidth');
$PAGE->requires->css(new moodle_url('/local/portfolio/fullwidth.css'));

// Determine role before any output.
$isadmin = is_siteadmin() ||
           has_capability('moodle/site:config', $context) ||
           has_capability('moodle/course:create', $context);
$userrole = $isadmin ? 'university' : 'student';

// Output starts here.
echo $OUTPUT->header();

// Pass session key and user info to React app via window object
echo html_writer::start_tag('script');
echo "window.PORTFOLIO_CONFIG = " . json_encode([
    'userId' => $USER->id,
    'userName' => fullname($USER),
    'userEmail' => $USER->email,
    'sesskey' => sesskey(),
    'apiUrl' => $CFG->wwwroot . '/local/portfolio/api.php',
    'userRole' => $userrole,
]) . ";";
echo html_writer::end_tag('script');

// Root element for React app
echo html_writer::div('', '', ['id' => 'root']);

// Load CSS and JS bundles
$pluginurl = new moodle_url('/local/portfolio/static/build');
$jsfile = __DIR__ . '/static/build/assets/index.js';
$cssfile = __DIR__ . '/static/build/assets/index.css';

$cachebust = file_exists($jsfile) ? filemtime($jsfile) : time();

if (is_readable($cssfile)) {
    echo html_writer::empty_tag('link', [
        'rel' => 'stylesheet',
        'href' => $pluginurl . '/assets/index.css?v=' . $cachebust
    ]);
}

if (is_readable($jsfile)) {
    echo html_writer::empty_tag('script', [
        'type' => 'module',
        'src' => $pluginurl . '/assets/index.js?v=' . $cachebust
    ]);
} else {
    echo html_writer::div(
        'Portfolio bundle not found or not built. Please build or deploy the static client files.',
        'alert alert-danger'
    );
}

echo $OUTPUT->footer();
