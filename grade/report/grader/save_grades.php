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
 * AJAX endpoint for saving grades in the Grader Report without a full page refresh.
 *
 * @package   gradereport_grader
 * @copyright 2024 Custom
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/report/grader/lib.php');

// Raise limits for large courses.
raise_memory_limit(MEMORY_HUGE);
set_time_limit(60);

header('Content-Type: application/json');

// --- Input validation ---
$courseid     = required_param('courseid', PARAM_INT);
$sesskey      = required_param('sesskey', PARAM_RAW);
$timepageload = required_param('timepageload', PARAM_INT);

// Validate sesskey.
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'error' => 'Invalid session key.']);
    die();
}

// Access checks.
if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    echo json_encode(['success' => false, 'error' => 'Invalid course.']);
    die();
}

require_login($course);
$context = context_course::instance($course->id);

if (!has_capability('moodle/grade:edit', $context)) {
    echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    die();
}

// --- Parse posted grades ---
// Grades arrive as a nested array: grade[userid][itemid] = value.
// Moodle's optional_param_array() cannot handle multi-dimensional arrays,
// so we read directly from $_POST and clean each value individually.
$grades_raw = isset($_POST['grade']) ? $_POST['grade'] : [];

if (!is_array($grades_raw) || empty($grades_raw)) {
    echo json_encode(['success' => false, 'error' => 'No grades submitted.']);
    die();
}

// Build a clean nested array: [userid][itemid] => cleaned_value.
$grades_input = [];
foreach ($grades_raw as $userid_raw => $items) {
    if (!is_array($items)) {
        continue;
    }
    $userid_clean = clean_param($userid_raw, PARAM_INT);
    if (!$userid_clean) {
        continue;
    }
    foreach ($items as $itemid_raw => $value_raw) {
        $itemid_clean = clean_param($itemid_raw, PARAM_INT);
        if (!$itemid_clean) {
            continue;
        }
        // Clean the grade value - allow numeric strings, empty string, and -1 (no grade).
        $value_clean = clean_param($value_raw, PARAM_RAW_TRIMMED);
        $grades_input[$userid_clean][$itemid_clean] = $value_clean;
    }
}

if (empty($grades_input)) {
    echo json_encode(['success' => false, 'error' => 'No valid grades submitted.']);
    die();
}


// --- Process grades ---
$warnings      = [];
$updated       = [];
$changedgrades = false;

$viewfullnames = has_capability('moodle/site:viewfullnames', $context);

$separategroups = false;
$mygroups       = [];
$groupmode      = groups_get_course_groupmode($course);
if ($groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
    $separategroups = true;
    $mygroups       = groups_get_user_groups($courseid);
    $mygroups       = $mygroups[0];
}

// Load all grade items for the course once.
$grade_items_cache = [];

foreach ($grades_input as $userid_raw => $items) {
    $userid = clean_param($userid_raw, PARAM_INT);
    if (!$userid) {
        continue;
    }

    foreach ($items as $itemid_raw => $postedvalue) {
        $itemid = clean_param($itemid_raw, PARAM_INT);
        if (!$itemid) {
            continue;
        }

        // Fetch and cache grade items.
        if (!isset($grade_items_cache[$itemid])) {
            $grade_items_cache[$itemid] = grade_item::fetch(['id' => $itemid, 'courseid' => $courseid]);
        }
        $gradeitem = $grade_items_cache[$itemid];

        if (!$gradeitem) {
            $warnings[] = "Invalid grade item ID: {$itemid}";
            continue;
        }

        // Determine the new final grade.
        if ($gradeitem->gradetype == GRADE_TYPE_SCALE) {
            $finalgrade = ($postedvalue == -1) ? null : (float) $postedvalue;
        } else {
            $finalgrade = unformat_float($postedvalue);
        }

        // Fetch the existing grade to compare.
        $existinggrade = grade_grade::fetch(['itemid' => $itemid, 'userid' => $userid]);

        // Skip if no change.
        if ($existinggrade) {
            if ($gradeitem->gradetype == GRADE_TYPE_SCALE) {
                if ((int) $existinggrade->finalgrade === (int) $postedvalue && $postedvalue != -1) {
                    continue;
                }
                if (is_null($existinggrade->finalgrade) && $postedvalue == -1) {
                    continue;
                }
            } else {
                $formatted_existing = format_float($existinggrade->finalgrade, $gradeitem->get_decimals());
                if ($postedvalue === $formatted_existing) {
                    continue;
                }
            }

            // Check for concurrent edit conflict.
            $dategraded = $existinggrade->get_dategraded();
            if (!empty($dategraded) && $timepageload < $dategraded) {
                $userfieldsapi = \core_user\fields::for_name();
                $userfields    = 'id, ' . $userfieldsapi->get_sql('', false, '', '', false)->selects;
                $user          = $DB->get_record('user', ['id' => $userid], $userfields);
                $gradestr      = new stdClass();
                $gradestr->username = fullname($user, $viewfullnames);
                $gradestr->itemname = $gradeitem->get_name();
                $warnings[] = get_string('gradewasmodifiedduringediting', 'grades', $gradestr);
                continue;
            }
        }

        // Validate bounds.
        if (!is_null($finalgrade)) {
            $bounded = $gradeitem->bounded_grade($finalgrade);
            if ($bounded > $finalgrade) {
                $userfieldsapi = \core_user\fields::for_name();
                $userfields    = 'id, ' . $userfieldsapi->get_sql('', false, '', '', false)->selects;
                $user          = $DB->get_record('user', ['id' => $userid], $userfields);
                $gradestr      = new stdClass();
                $gradestr->username = fullname($user, $viewfullnames);
                $gradestr->itemname = $gradeitem->get_name();
                $warnings[] = get_string('lessthanmin', 'grades', $gradestr);
            } else if ($bounded < $finalgrade) {
                $userfieldsapi = \core_user\fields::for_name();
                $userfields    = 'id, ' . $userfieldsapi->get_sql('', false, '', '', false)->selects;
                $user          = $DB->get_record('user', ['id' => $userid], $userfields);
                $gradestr      = new stdClass();
                $gradestr->username = fullname($user, $viewfullnames);
                $gradestr->itemname = $gradeitem->get_name();
                $warnings[] = get_string('morethanmax', 'grades', $gradestr);
            }
        }

        // Group access control.
        if ($separategroups) {
            $sharinggroup = false;
            foreach ($mygroups as $groupid) {
                if (groups_is_member($groupid, $userid)) {
                    $sharinggroup = true;
                    break;
                }
            }
            if (!$sharinggroup) {
                $warnings[] = get_string('errorsavegrade', 'grades');
                continue;
            }
        }

        // Save the grade.
        $gradeitem->update_final_grade($userid, $finalgrade, 'gradebook', false, FORMAT_MOODLE, null, null, true);
        $changedgrades = true;

        // Retrieve the freshly saved/recalculated grade for the response.
        $saved_grade = grade_grade::fetch(['itemid' => $itemid, 'userid' => $userid]);
        if ($saved_grade) {
            $saved_grade->grade_item = $gradeitem;
            $display_grade = grade_format_gradevalue(
                $saved_grade->finalgrade,
                $gradeitem,
                true,
                null,
                null
            );
            $updated[] = [
                'userid'   => $userid,
                'itemid'   => $itemid,
                'rawgrade' => $saved_grade->finalgrade,
                'display'  => $display_grade,
            ];
        }
    }
}

// If grades changed, fetch updated course totals for all affected users.
$course_totals = [];
if ($changedgrades) {
    // Trigger recalculation.
    grade_regrade_final_grades($courseid);

    // Gather unique userids that had changes.
    $changed_userids = array_unique(array_column($updated, 'userid'));

    // Find the course total grade item.
    $course_item = grade_item::fetch_course_item($courseid);
    if ($course_item) {
        foreach ($changed_userids as $uid) {
            $total_grade = grade_grade::fetch(['itemid' => $course_item->id, 'userid' => $uid]);
            if ($total_grade) {
                $total_grade->grade_item = $course_item;
                $display = grade_format_gradevalue(
                    $total_grade->finalgrade,
                    $course_item,
                    true,
                    null,
                    null
                );
                $course_totals[] = [
                    'userid'  => $uid,
                    'itemid'  => $course_item->id,
                    'rawgrade' => $total_grade->finalgrade,
                    'display' => $display,
                ];
            }
        }

        // Also update category totals if any exist.
        $category_items = $DB->get_records('grade_items', [
            'courseid'  => $courseid,
            'itemtype'  => 'category',
        ]);
        foreach ($category_items as $cat_item_record) {
            $cat_item = new grade_item($cat_item_record, false);
            foreach ($changed_userids as $uid) {
                $cat_grade = grade_grade::fetch(['itemid' => $cat_item->id, 'userid' => $uid]);
                if ($cat_grade) {
                    $cat_grade->grade_item = $cat_item;
                    $display = grade_format_gradevalue(
                        $cat_grade->finalgrade,
                        $cat_item,
                        true,
                        null,
                        null
                    );
                    $course_totals[] = [
                        'userid'  => $uid,
                        'itemid'  => $cat_item->id,
                        'rawgrade' => $cat_grade->finalgrade,
                        'display' => $display,
                    ];
                }
            }
        }
    }
}

echo json_encode([
    'success'      => true,
    'warnings'     => $warnings,
    'updated'      => $updated,
    'totals'       => $course_totals,
]);
