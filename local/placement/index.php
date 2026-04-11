<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/my/lib.php');

// Ensure only site admins can access this page.
require_login();
if (!is_siteadmin()) {
    throw new \moodle_exception('nopermissions', 'error', '', 'Admin access only');
}

$strmymoodle = get_string('placement', 'local_placement');

// Setup the page.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/placement/index.php'));
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_pagetype('local-placement-index');
$PAGE->set_title($strmymoodle);
$PAGE->set_heading($strmymoodle);

// Add block region.
$PAGE->blocks->add_region('content');

echo $OUTPUT->header();

// Display 'Edit mode' button if allowed.
echo $OUTPUT->addblockbutton('content');

// Display the blocks.
echo $OUTPUT->custom_block_region('content');

echo $OUTPUT->footer();
