<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_codingplayground_mod_form extends moodleform_mod {

    function definition() {
        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('name', 'mod_codingplayground'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" field
        $this->standard_intro_elements();

        // Adding the API URL field
        $mform->addElement('text', 'api_url', get_string('api_url', 'mod_codingplayground'), array('size'=>'64'));
        $mform->setType('api_url', PARAM_URL);
        $mform->addHelpButton('api_url', 'api_url', 'mod_codingplayground');
        $mform->setDefault('api_url', 'http://localhost:8000/api/v1');

        // Add standard elements
        $this->standard_coursemodule_elements();

        // Add standard buttons
        $this->add_action_buttons();
    }
}
