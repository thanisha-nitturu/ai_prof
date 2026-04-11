<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course Module ID

$cm = get_coursemodule_from_id('codingplayground', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$codingplayground = $DB->get_record('codingplayground', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/codingplayground:view', $context);

// Set up the page
$PAGE->set_url('/mod/codingplayground/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($codingplayground->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_primary_active_tab('mycourses');

// Output starts here
echo $OUTPUT->header();

// Get the section (module) name from Moodle
$section = $DB->get_record('course_sections', array('id' => $cm->section));
$sectionName = get_section_name($course, $section);

// Pass data to React app via window object
echo html_writer::start_tag('script');
echo "window.CODINGPLAYGROUND = ";
echo json_encode(array(
    'courseId'    => $course->id,
    'courseName'  => $course->fullname,
    'sectionName' => $sectionName,
    'userId'      => $USER->id,
    'userName'    => fullname($USER),
    'apiUrl'      => $CFG->wwwroot . '/mod/codingplayground/proxy.php/api/v1',
    'moduleId'    => $cm->id,
    'instanceId'  => $codingplayground->id,
    'name'        => $codingplayground->name,
    'isTeacher'   => has_capability('moodle/course:manageactivities', $context)
));
echo ";";
echo html_writer::end_tag('script');


// Root element for React app
echo html_writer::div('', '', array('id' => 'root'));

// Load React bundle
$pluginurl = new moodle_url('/mod/codingplayground/dist');
$jsfile = __DIR__ . '/dist/assets/index.js';
$cssfile = __DIR__ . '/dist/assets/index.css';

$cachebust = file_exists($jsfile) ? filemtime($jsfile) : time();

if (file_exists($cssfile)) {
    echo html_writer::tag('link', '', array(
        'rel' => 'stylesheet',
        'href' => $pluginurl . '/assets/index.css?v=' . $cachebust
    ));
}

if (file_exists($jsfile)) {
    echo html_writer::tag('script', '', array(
        'type' => 'module',
        'src' => $pluginurl . '/assets/index.js?v=' . $cachebust
    ));
} else {
    echo html_writer::tag('div', 'Plugin not built. Please run build process.', array('class' => 'alert alert-error'));
}

// Finish the page
echo $OUTPUT->footer();
