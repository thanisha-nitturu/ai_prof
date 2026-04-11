<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Adds an instance of codingplayground
 *
 * @param stdClass $codingplayground
 * @param mod_codingplayground_mod_form $mform
 * @return int intance id
 */
function codingplayground_add_instance($codingplayground, $mform = null) {
    global $DB;

    $codingplayground->timecreated = time();
    $codingplayground->timemodified = time();

    return $DB->insert_record('codingplayground', $codingplayground);
}

/**
 * Updates an instance of codingplayground
 *
 * @param stdClass $codingplayground
 * @param mod_codingplayground_mod_form $mform
 * @return bool true if success
 */
function codingplayground_update_instance($codingplayground, $mform = null) {
    global $DB;

    $codingplayground->timemodified = time();
    $codingplayground->id = $codingplayground->instance;

    return $DB->update_record('codingplayground', $codingplayground);
}

/**
 * Deletes an instance of codingplayground
 *
 * @param int $id instance id
 * @return bool true if success
 */
function codingplayground_delete_instance($id) {
    global $DB;

    if (!$codingplayground = $DB->get_record('codingplayground', array('id' => $id))) {
        return false;
    }

    $DB->delete_records('codingplayground', array('id' => $codingplayground->id));

    return true;
}

/**
 * Callback to check if user can view the activity
 */
function codingplayground_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO: return true;
        case FEATURE_SHOW_DESCRIPTION: return true;
        case FEATURE_GRADE_HAS_GRADE: return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        default: return null;
    }
}
