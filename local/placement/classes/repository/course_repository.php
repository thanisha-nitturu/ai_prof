<?php

namespace local_placement\repository;

defined('MOODLE_INTERNAL') || die();

class course_repository {

    /**
     * Get all enrolled courses for a user.
     */
    public static function get_user_courses(int $userid): array {
        return enrol_get_users_courses($userid, true, '*');
    }

    /**
     * Get course category.
     */
    public static function get_category(int $categoryid) {
        global $DB;

        return $DB->get_record(
            'course_categories',
            ['id' => $categoryid]
        );
    }
}