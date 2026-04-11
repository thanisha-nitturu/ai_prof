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

/**
 * Library functions
 *
 * @package    aiplacement_textinsights
 * @copyright  2025 DeveloperCK <developerck@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Inject JavaScript into course pages
 *
 * @param moodle_page $page The current page
 */
function aiplacement_textinsights_before_standard_html_head_generation() {
    global $COURSE, $PAGE;
    $page = $PAGE;echo "a";die;
    $available = \aiplacement_textinsights\utils::is_textinsights_available($page->context);
    if (!$available) {
     
            return; // No need to inject if the feature is not available.
    }
    if ($page->context->contextlevel === CONTEXT_COURSE ||
        $page->context->contextlevel === CONTEXT_MODULE) {
        // Check capabilities.
        $capabilities = [
            'explain' => has_capability('aiplacement/textinsights:useexplain', $page->context),
            'summarize' => has_capability('aiplacement/textinsights:usesummarize', $page->context),
            'validate' => has_capability('aiplacement/textinsights:usevalidate', $page->context),
        ];
        // Only proceed if user has at least one capability.
        if (array_filter($capabilities)) {
            // Add required JavaScript.
            $page->requires->jquery();
            $page->requires->js_call_amd('aiplacement_textinsights/module', 'init', [
                $COURSE->id,
                $capabilities,
            ]);
            // Add required strings.
            $page->requires->strings_for_js([
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
