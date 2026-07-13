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

namespace filter_videolesson;

/**
 * Allows plugins to add any elements to the footer.
 *
 * @package    filter_videolesson
 * @author     BitKea Technologies LLP
 * @copyright  2024 BitKea Technologies LLP (https://www.bitkea.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Callback to add head elements.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook,
    ): void {
        global $CFG, $PAGE;
        // Support both old and new filter names for backward compatibility.
        if (!filter_is_enabled('videolesson') && !filter_is_enabled('videoaws')) {
            return;
        }
        $lib = $CFG->dirroot . '/mod/videolesson/lib.php';
        if (!file_exists($lib)) {
            return;
        }
        require_once($lib);
        // Queue Plyr/vendor CSS and JS via $PAGE->requires (dedupes; avoids raw <script> with RequireJS).
        // YouTube/Vimeo APIs: pass true when you know the page needs them; filter path uses footer AMD for the player.
        videolesson_register_player_page_requires($PAGE, false, false);
    }

    /**
     * Callback to add head elements.
     *
     * @param \core\hook\output\before_standard_footer_html_generation $hook
     */
    public static function before_standard_footer_html_generation(
        \core\hook\output\before_standard_footer_html_generation $hook,
    ): void {

        global $PAGE;

        // Support both old and new filter names for backward compatibility.
        if (!filter_is_enabled('videolesson') && !filter_is_enabled('videoaws')) {
            return;
        }

        // Require the AMD module.
        $PAGE->requires->js_call_amd('filter_videolesson/player', 'init');

        // Return null since we're not outputting custom HTML.
        return;
    }
}
