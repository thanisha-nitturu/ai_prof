<?php
namespace local_profile_enforcer;

use core\hook\output\before_standard_head_html_generation;

/**
 * Hook callbacks for the profile enforcer
 *
 * @package    local_profile_enforcer
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Inject the trigger JS on the profile edit page.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE;

        // Check if we are on the user edit page
        if ($PAGE->url->compare(new \moodle_url('/user/edit.php'), URL_MATCH_BASE)) {
            // Inject our AMD trigger
            $PAGE->requires->js_call_amd('local_profile_enforcer/trigger', 'init');
        }
    }
}
