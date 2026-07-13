<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Hook callbacks for local_portfolio.
 *
 * @package    local_portfolio
 * @copyright  2026 DevLearn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_portfolio\hooks;

use core_user\hook\extend_user_menu;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callback class for local_portfolio.
 */
class hook_callbacks {

    /**
     * Extend the user profile dropdown menu with a Portfolio link.
     *
     * This adds the item to the avatar/profile dropdown (not the top navbar).
     * Only shown to authenticated, non-guest users.
     *
     * @param extend_user_menu $hook The user menu hook instance.
     */
    public static function extend_user_menu(extend_user_menu $hook): void {
        global $CFG;

        // Fail safe: skip during install or upgrade to avoid errors.
        if (during_initial_install() || isset($CFG->upgraderunning)) {
            return;
        }

        // Security: only show to authenticated, non-guest users (fail closed).
        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Check user role
        $context = \context_system::instance();
        $isadmin = is_siteadmin() ||
                   has_capability('moodle/site:config', $context) ||
                   has_capability('moodle/course:create', $context);
        $userrole = $isadmin ? 'university' : 'student';

        if ($userrole !== 'student') {
            return;
        }

        // Build the menu item as a stdClass with required fields.
        $item                  = new \stdClass();
        $item->itemtype        = 'link';
        $item->url             = new moodle_url('/local/portfolio/index.php');
        $item->title           = get_string('portfolio', 'local_portfolio');
        // titleidentifier is used by Moodle for accessibility / testing.
        $item->titleidentifier = 'portfolio,local_portfolio';

        $hook->add_navitem($item);
    }
}
