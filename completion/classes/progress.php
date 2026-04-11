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
 * Contains class used to return completion progress information.
 *
 * @package    core_completion
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_completion;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Class used to return completion progress information.
 *
 * @package    core_completion
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {

    /**
     * Returns the course percentage completed by a certain user, returns null if no completion data is available.
     *
     * @param \stdClass $course Moodle course object
     * @param int $userid The id of the user, 0 for the current user
     * @return null|float The percentage, or null if completion is not supported in the course,
     *         or there are no activities that support completion.
     */
    public static function get_course_progress_percentage($course, $userid = 0) {
        global $USER;

        // Make sure we continue with a valid userid.
        if (empty($userid)) {
            $userid = $USER->id;
        }

        $completion = new \completion_info($course);

        // First, let's make sure completion is enabled.
        if (!$completion->is_enabled()) {
            return null;
        }

        if (!$completion->is_tracked_user($userid)) {
            return null;
        }

        // Before we check how many modules have been completed see if the course has.
        if ($completion->is_course_complete($userid)) {
            return 100;
        }

        // Get the number of modules that support completion.
        $modules = $completion->get_activities();
        $count = count($modules);
        if (!$count) {
            return null;
        }

        // Get the number of modules that have been completed
        $totalcompleted = $completion->count_modules_completed($userid);

        return ($totalcompleted / $count) * 100;
    }

        /**
     * Returns an array of completion details (total count, completed count, percentage) for each course a user is enrolled in.
     *
     * @param int $userid The id of the user, 0 for the current user
     * @return array Array of course completion details
     */
    public static function get_course_completion_details($userid = 0) {
        global $USER, $DB;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $sql = "SELECT c.id, c.fullname
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {course} c ON c.id = e.courseid
                WHERE ue.status = 0
                AND u.deleted = 0
                AND c.visible = 1
                AND u.id = :userid
                ORDER BY c.fullname";

        $courses = $DB->get_records_sql($sql, ['userid' => $userid]);
        $completion_details = [];

        if (empty($courses)) {
            return $completion_details;
        }

        foreach ($courses as $course) {
            $completion = new \completion_info($course);
            $course_data = [
                'coursename' => $course->fullname,
                'courseId' => $course->id,
                'total_activities' => 0,
                'completed_activities' => 0,
                'progress_percentage' => null,
                'completed_module_names' => [], // Add field here
                'section_modules' => [], // new key
                'module_count' => 0
            ];

            if (!$completion->is_enabled()) {
                $course_data['progress_percentage'] = null;
            } elseif (!$completion->is_tracked_user($userid)) {
                $course_data['progress_percentage'] = null;
            } elseif ($completion->is_course_complete($userid)) {
                $course_data['progress_percentage'] = 100;
            } else {
                $modules = $completion->get_activities();
                $total_count = count($modules);

                if ($total_count == 0) {
                    $course_data['progress_percentage'] = null;
                } else {
                    $completed_count = 0;
                    $modinfo = get_fast_modinfo($course->id);

                    foreach ($modules as $module) {
                        $data = $completion->get_data($module, true, $userid);
                        if ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS) {
                            $completed_count++;
                            // Append completed module name
                            $cm = $modinfo->cms[$module->id];

                            // $sectionname = get_section_name($course, $cm->sectionnum);

                            // if (!in_array($sectionname, $course_data['completed_module_names'])) {
                            //     $course_data['completed_module_names'][] = $sectionname;
                            // }
                            if ($cm->modname === 'label' && $cm->name === 'Module Content') {
                                $sectionname = get_section_name($course, $cm->sectionnum);

                                $course_data['completed_module_names'][] = $sectionname;
                            }

                        }
                    }

                    $course_data['total_activities'] = $total_count;
                    $course_data['completed_activities'] = $completed_count;
                    $course_data['progress_percentage'] = round(($completed_count / $total_count) * 100, 2);
                }
            }

                    // === Section Modules and Content ===
            $modinfo = get_fast_modinfo($course, $userid);
            $sections = $modinfo->get_section_info_all();

            foreach ($sections as $section) {
                if (!$section->uservisible) {
                    continue;
                }

                $sectionname = get_section_name($course, $section);
                if ($sectionname == "General"){
                    continue;
                }
                $course_data['section_modules'][$sectionname] = [];

                if (empty($modinfo->sections[$section->section])) {
                    continue;
                }

                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $cm = $modinfo->cms[$cmid];

                    if (!$cm->uservisible || $cm->deletioninprogress) {
                        continue;
                    }

                    $modulename = $cm->name;
                    $modtype = $cm->modname;
                    $content = '';
                    if(!($modulename == "Module Content")){
                        continue;
                    }

                    switch ($modtype) {
                        case 'page':
                            $page = $DB->get_record('page', ['id' => $cm->instance], 'content', IGNORE_MISSING);
                            $content = $page ? format_text($page->content, FORMAT_HTML) : '';
                            break;
                        case 'label':
                            $label = $DB->get_record('label', ['id' => $cm->instance], 'intro', IGNORE_MISSING);
                            $content = $label ? format_text($label->intro, FORMAT_HTML) : '';
                            break;
                        case 'assign':
                            $assign = $DB->get_record('assign', ['id' => $cm->instance], 'intro', IGNORE_MISSING);
                            $content = $assign ? format_text($assign->intro, FORMAT_HTML) : '';
                            break;
                        case 'quiz':
                            $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'intro', IGNORE_MISSING);
                            $content = $quiz ? format_text($quiz->intro, FORMAT_HTML) : '';
                            break;
                        default:
                            $content = '[Unsupported module type: ' . $modtype . ']';
                    }

                    $course_data['section_modules'][$sectionname][$modulename] = $content;
                }
            }
            $course_data['module_count'] = count($course_data['section_modules']);
            $completion_details[] = $course_data;
        }

        return $completion_details;
    }
}

