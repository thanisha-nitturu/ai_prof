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
 * A drawer based layout for the Eskada theme.
 *
 * @package    theme_moove
 * @copyright  2025 Willian Mano - willianmanoaraujo@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $secondary = $PAGE->secondarynav;

    if ($secondary->get_children_key_list()) {
        $tablistnav = $PAGE->has_tablist_secondary_navigation();
        $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
        $secondarynavigation = $moremenu->export_for_template($OUTPUT);
        $extraclasses[] = 'has-secondarynavigation';
    }

    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);

// Force reorder nodes: Home -> My courses -> Calender (Dashboard).
$nav = $PAGE->primarynav;
if ($nav instanceof \core\navigation\views\primary) {
    $home = $nav->find('home', null);
    $mycourses = $nav->find('mycourses', null);
    $dashboard = $nav->find('myhome', null);
    if ($home) $home->remove();
    if ($mycourses) $mycourses->remove();
    if ($dashboard) $dashboard->remove();
    if ($home) $nav->add_node($home);
    if ($mycourses) $nav->add_node($mycourses);
    if ($dashboard) $nav->add_node($dashboard);
}

$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$bodyattributes = $OUTPUT->body_attributes($extraclasses);

// List of user emails that should NOT see Pathways, Playground, or Placement.
$hide3pemails = [
    'nalawseh2@huskers.unl.edu',
    'woodarda@lopers.unk.edu',
    'owilson-bahun2@huskers.unl.edu',
    'npolicky2@huskers.unl.edu',
    'allewellyn2@huskers.unl.edu',
    'aklapp2@huskers.unl.edu',
    'lgieselman2@huskers.unl.edu',
    'gdalton4@huskers.unl.edu',
    'kpetry3@huskers.unl.edu',
    'mkiesel2@huskers.unl.edu',
    'acohen6@huskers.unl.edu',
    'pshields2@huskers.unl.edu',
    'mliss2@huskers.unl.edu',
    'kpriest2@huskers.unl.edu',
    'canderjaska2@huskers.unl.edu',
    'kmoody9@huskers.unl.edu',
    'jfriesen7@huskers.unl.edu',
    'jlowe22@huskers.unl.edu',
    'pthutika2@huskers.unl.edu',
    'kpatel17@huskers.unl.edu',
    'bfentaw2@huskers.unl.edu',
    'jschmitt9@huskers.unl.edu',
    'nitturuthanisha@gmail.com',
];
$hide3p = false;
if (isloggedin() && !empty($hide3pemails)) {
    $hide3p = in_array(strtolower($USER->email), array_map('strtolower', $hide3pemails));
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => \core\context\course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'is_siteadmin' => is_siteadmin(),
    'hide_3p' => $hide3p,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
];

$themesettings = new \theme_moove\util\settings();

$templatecontext = array_merge($templatecontext, $themesettings->footer());

echo $OUTPUT->render_from_template('theme_moove/drawers', $templatecontext);
