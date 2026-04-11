<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Enforce profile picture upload.
 * This hook runs after every require_login() call in Moodle.
 */
function local_profile_enforcer_after_require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect) {
    global $USER, $CFG, $PAGE;

    // Skip for guests, not logged in users, admins, and special scripts (AJAX/CLI)
    if (!isloggedin() || isguestuser() || CLI_SCRIPT || AJAX_SCRIPT || is_siteadmin()) {
        return;
    }

    // Redirect to profile if picture is missing.
    if (empty($USER->picture)) {
        $editurl = new moodle_url('/user/edit.php', ['id' => $USER->id]);
        $isprofilepage = $PAGE->url->compare($editurl, URL_MATCH_BASE);
        
        if (!$isprofilepage && !$preventredirect) {
            // Redirect them with a warning message
            redirect($editurl, get_string('mustuploadpicture', 'local_profile_enforcer'), null, \core\output\notification::NOTIFY_WARNING);
        }
    }
}
