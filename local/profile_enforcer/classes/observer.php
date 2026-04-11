<?php
namespace local_profile_enforcer;

defined('MOODLE_INTERNAL') || die();

use core\event\user_created;
use context_user;

/**
 * Observer for user creation to handle profile picture upload.
 */
class observer {

    /**
     * Triggered when a new user is created.
     * @param user_created $event
     */
    public static function user_created(user_created $event) {
        global $SESSION, $DB, $CFG;

        $userid = $event->objectid;

        // Check if we have a pending profile picture file path from the signup session.
        if (!empty($SESSION->pending_profile_pic_path)) {
            $path = $SESSION->pending_profile_pic_path;
            unset($SESSION->pending_profile_pic_path);

            if (file_exists($path)) {
                require_once($CFG->libdir . '/gdlib.php');
                
                $usercontext = context_user::instance($userid);
                
                // Process the file from the temp path.
                process_new_icon($usercontext, 'user', 'icon', 0, $path);
                
                // Mark the user as having a picture.
                $DB->set_field('user', 'picture', 1, ['id' => $userid]);
                
                // Cleanup
                @unlink($path);
            }
        }
    }
}
