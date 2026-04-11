<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\user_created',
        'callback'    => 'local_profile_enforcer\observer::user_created',
    ],
];
