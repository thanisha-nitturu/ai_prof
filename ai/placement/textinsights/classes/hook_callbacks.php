<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace aiplacement_textinsights;

use core\hook\output\before_standard_head_html_generation;

/**
 * Hook callbacks for the text insights placement
 *
 * @package    aiplacement_textinsights
 * @copyright  2025 DeveloperCK <developerck@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Bootstrap the course assist UI.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $COURSE, $PAGE;
        $available = \aiplacement_textinsights\utils::is_textinsights_available($PAGE->context);
        if (!$available) {
            return; // No need to inject if the feature is not available.
        }
        if (
            $PAGE->context->contextlevel === CONTEXT_COURSE ||
            $PAGE->context->contextlevel === CONTEXT_MODULE
        ) {
            // Check capabilities.
            $capabilities = [
                'explain' => has_capability('aiplacement/textinsights:useexplain', $PAGE->context),
                'summarize' => has_capability('aiplacement/textinsights:usesummarize', $PAGE->context),
                'validate' => has_capability('aiplacement/textinsights:usevalidate', $PAGE->context),
            ];
            // Only proceed if user has at least one capability.
            if (array_filter($capabilities)) {
                // Add required JavaScript.
                $PAGE->requires->jquery();
                $PAGE->requires->js_call_amd('aiplacement_textinsights/module', 'init', [
                    $COURSE->id,
                    $capabilities,
                ]);
                // Add required strings.
                $PAGE->requires->strings_for_js([
                    'explain',
                    'summarize',
                    'validate',
                    'loading',
                    'error',
                    'poweredby',
                ], 'aiplacement_textinsights');
            }
        }
    }
}
