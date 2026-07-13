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
 * My Courses.
 *
 * - each user can currently have their own page (cloned from system and then customised)
 * - only the user can see their own dashboard
 * - users can add any blocks they want
 *
 * @package    core
 * @subpackage my
 * @copyright  2021 Mathew May <mathew.solutions>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include the necessary Moodle files
require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/my/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

redirect_if_major_upgrade_required();

require_login();

//added fro continue button redirection from pathways.php
$redirect = optional_param('redirect', null, PARAM_URL);
if ($redirect) {
    echo '<!DOCTYPE html><html><head><script>';
    echo 'window.history.replaceState(null, "", window.location.pathname);';
    echo 'window.location.href = ' . json_encode($redirect) . ';';
    echo '</script></head><body>Redirecting...</body></html>';
    exit;
}

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/my/chat.php');
$PAGE->set_pagelayout('mycourses');
$PAGE->set_title('Pathways');
$PAGE->set_heading('Pathways');

echo $OUTPUT->header();

// Embed local HTML file using iframe
// echo '<iframe src="hl_pathways.html" style="width: 100%; height: 100vh; border: none;"></iframe>';

// Version1 pathways as of march 13 2025
// echo '<div style="position: relative; width: 100%; padding-top: 56.25%;"><iframe src="demo.html" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;"></iframe></div>'; 

//version2 pathways from march 13 2025
echo '<div style="position: relative; width: 100%; padding-top: 56.25%;"><iframe src="v2.html" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;"></iframe></div>';
echo $OUTPUT->footer();

