<?php

namespace local_placement\service;

defined('MOODLE_INTERNAL') || die();

use completion_info;
use context_course;
use local_placement\repository\course_repository;

class placement_course_service {

    private const PLACEMENT_CATEGORY_NAME = 'Placement Track';

    public static function get_user_placement_courses(int $userid): array {

        $courses = course_repository::get_user_courses($userid);

        $placementcourses = [];
        $categoriescache = [];

        foreach ($courses as $course) {

            if (!isset($categoriescache[$course->category])) {
                $categoriescache[$course->category] =
                    course_repository::get_category($course->category);
            }

            $category = $categoriescache[$course->category];

            if (!$category) {
                continue;
            }

            // Match by category name OR legacy ID/path.
            $isplacement =
                trim($category->name) === self::PLACEMENT_CATEGORY_NAME ||
                $category->id == 30 ||
                strpos($category->path, '/30/') !== false ||
                strpos($category->path, '/30') === 0;

            if (!$isplacement) {
                continue;
            }

            $progress = 0;
            $moduleslabel = "Completion not enabled";

            if ($course->enablecompletion) {

                $completion = new completion_info($course);

                $courseprogress =
                    \core_completion\progress::get_course_progress_percentage(
                        $course,
                        $userid
                    );

                if ($courseprogress !== null) {
                    $progress = (int) round($courseprogress);
                }

                $activities = $completion->get_activities();

                $totalactivities = 0;
                $completedactivities = 0;

                foreach ($activities as $activity) {

                    if (
                        $activity->completion ==
                        COMPLETION_TRACKING_NONE
                    ) {
                        continue;
                    }

                    $totalactivities++;

                    $data = $completion->get_data(
                        $activity,
                        true,
                        $userid
                    );

                    if (
                        $data->completionstate ==
                            COMPLETION_COMPLETE ||
                        $data->completionstate ==
                            COMPLETION_COMPLETE_PASS
                    ) {
                        $completedactivities++;
                    }
                }

                if ($totalactivities > 0) {
                    $moduleslabel =
                        "{$completedactivities} / {$totalactivities} activities";
                } else {
                    $moduleslabel = "No tracked activities";
                }
            }

            $color = "bg-blue-500";

            if ($progress >= 75) {
                $color = "bg-emerald-500";
            } else if ($progress >= 40) {
                $color = "bg-amber-500";
            }

            $coursecontext = context_course::instance($course->id);

            $placementcourses[] = [
                'id' => (int) $course->id,
                'name' => format_string(
                    $course->fullname,
                    true,
                    ['context' => $coursecontext]
                ),
                'shortname' => format_string(
                    $course->shortname,
                    true,
                    ['context' => $coursecontext]
                ),
                'progress' => $progress,
                'modules' => $moduleslabel,
                'color' => $color
            ];
        }

        return $placementcourses;
    }
}