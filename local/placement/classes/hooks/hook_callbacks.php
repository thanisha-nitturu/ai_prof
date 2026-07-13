<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Hook callbacks for local_placement.
 *
 * @package    local_placement
 * @copyright  2026 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_placement\hooks;

use core\hook\navigation\primary_extend;
use navigation_node;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_placement.
 */
class hook_callbacks {

    /**
     * Extend primary navigation with the Placement item.
     *
     * @param primary_extend $hook
     */
    public static function primary_navigation_extend(primary_extend $hook): void {
        global $CFG;

        if (during_initial_install() || isset($CFG->upgraderunning)) {
            return;
        }

        // Only show to logged-in users who are not guest users.
        if (isloggedin() && !isguestuser()) {
            $primaryview = $hook->get_primaryview();
            $url = new moodle_url('/local/placement/index.php');
            $primaryview->add(
                get_string('placement', 'local_placement'),
                $url,
                navigation_node::TYPE_ROOTNODE,
                null,
                'placement',
                new \pix_icon('i/dashboard', '')
            );
        }
    }
}
