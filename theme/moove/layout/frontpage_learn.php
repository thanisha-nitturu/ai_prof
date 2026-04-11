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
 * Frontpage layout for the moove theme.
 *
 * @package    theme_moove
 * @copyright  2022 Willian Mano {@link https://conecti.me}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => \core\context\course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
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

if (isloggedin()) {
    echo $OUTPUT->render_from_template('theme_moove/drawers', $templatecontext);
} else {
    $templatecontext = array_merge($templatecontext, $themesettings->frontpage());
    
    // Override the main content with embedded iframe 
    ob_start();
    ?>
    <div style="margin: 0; padding: 0; width: 100vw; height: 100vh; position: fixed; top: 0; left: 0; z-index: 9999;">
        <div id="loading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 36px 36px 30px 36px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); display: flex; align-items: center; justify-content: center;">
  <style>
    .scatterbox-loader {
      width: 64px;
      height: 64px;
      position: relative;
      display: flex;
      flex-wrap: wrap; 
      gap: 6px;
    }
    .scatterbox-loader .box {
      width: 18px;
      height: 18px;
      background: #6366F1;
      border-radius: 4px;
      opacity: 0.85;
      position: absolute;
      animation: scatter 1.2s cubic-bezier(.68,-0.55,.27,1.55) infinite;
    }
    .scatterbox-loader .box1 { left: 0; top: 0; animation-delay: 0s; }
    .scatterbox-loader .box2 { left: 23px; top: 0; animation-delay: 0.15s; }
    .scatterbox-loader .box3 { left: 46px; top: 0; animation-delay: 0.3s; }
    .scatterbox-loader .box4 { left: 0; top: 23px; animation-delay: 0.45s; }
    .scatterbox-loader .box5 { left: 23px; top: 23px; animation-delay: 0.6s; }
    .scatterbox-loader .box6 { left: 46px; top: 23px; animation-delay: 0.75s; }
    .scatterbox-loader .box7 { left: 0; top: 46px; animation-delay: 0.9s; }
    .scatterbox-loader .box8 { left: 23px; top: 46px; animation-delay: 1.05s; }
    .scatterbox-loader .box9 { left: 46px; top: 46px; animation-delay: 1.2s; }
    @keyframes scatter {
      0%, 100% { transform: scale(1) translateY(0); opacity: 0.85; }
      20% { transform: scale(1.15) translateY(-8px); opacity: 1; }
      50% { transform: scale(0.9) translateY(8px); opacity: 0.7; }
      80% { transform: scale(1.1) translateY(-4px); opacity: 0.9; }
    }
  </style>
  <div class="scatterbox-loader" aria-label="Loading">
    <div class="box box1"></div>
    <div class="box box2"></div>
    <div class="box box3"></div>
    <div class="box box4"></div>
    <div class="box box5"></div>
    <div class="box box6"></div>
    <div class="box box7"></div>
    <div class="box box8"></div>
    <div class="box box9"></div>
  </div>
</div>
        <iframe 
            src="https://learn-landing.hitloop.com/" 
            style="width: 100%; height: 100%; border: none; display: block;"
            onload="document.getElementById('loading').style.display='none';"
            title=""
            frameborder="0"
            allowfullscreen>
        </iframe>
    </div>
    <div style="display: none;">
        <?php echo $OUTPUT->main_content(); ?>
    </div>
    <?php
    $custom_content = ob_get_clean();
    
    // Get the URL for the local favicon
    $favicon_url = $OUTPUT->image_url('Favicon_HighRes', 'theme_moove');
    
    // Use the existing frontpage template but inject our custom content
    echo $OUTPUT->doctype();
    ?>
    <html <?php echo $OUTPUT->htmlattributes(); ?>>
    <head>
        <!-- <title><?php echo $templatecontext['sitename']; ?></title> -->
        <title>Your AI Learning Platform</title>
        
        <!-- Local favicon from theme -->
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <!-- Direct favicon reference -->
        <?php
        // Get favicon URL from theme (this looks inside your theme’s /pix directory).
        $favicon_url = $OUTPUT->image_url('Favicon_HighRes', 'theme_moove');
        ?>
        <link rel="icon" href="<?php echo $favicon_url; ?>?v=2" type="image/png">
        <link rel="shortcut icon" href="<?php echo $favicon_url; ?>?v=2" type="image/png">
        <link rel="apple-touch-icon" href="<?php echo $favicon_url; ?>?v=2">

        <?php echo $OUTPUT->standard_head_html(); ?>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { margin: 0 !important; padding: 0 !important; overflow: hidden !important; }
            #page, .navbar, header, footer { display: none !important; }
        </style>
    </head>
    <body <?php echo $bodyattributes; ?>>
        <?php echo $custom_content; ?>
    </body>
    </html>
    <?php
}
?>
