<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Hook callbacks registration for local_portfolio.
 *
 * @package    local_portfolio
 * @copyright  2026 DevLearn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        // Hooks into the user profile dropdown menu (avatar dropdown), NOT the navbar.
        'hook'     => \core_user\hook\extend_user_menu::class,
        'callback' => [\local_portfolio\hooks\hook_callbacks::class, 'extend_user_menu'],
        'priority' => 500,
    ],
];
