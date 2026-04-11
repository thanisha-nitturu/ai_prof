<?php
require_once('../../config.php');

// Ensure user is logged in
require_login();

$context = context_system::instance();

// Set up the page
$PAGE->set_url('/mod/codingplayground/all_playgrounds.php');
$PAGE->set_title('Free Playgrounds');
$PAGE->set_heading('Coding Playgrounds Workspace');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

// Output starts here
echo $OUTPUT->header();

// Pass data to React app via window object
echo html_writer::start_tag('script');
echo "window.CODINGPLAYGROUND = ";
echo json_encode(array(
    'userId'      => (string)$USER->id,
    'userName'    => fullname($USER),
    'apiUrl'      => $CFG->wwwroot . '/mod/codingplayground/proxy.php/api/v1',
    'isTeacher'   => has_capability('moodle/course:manageactivities', $context),
    'isAdmin'     => is_siteadmin(),
    'isGlobal'    => true
));
echo ";";
echo html_writer::end_tag('script');

// Helper to set local storage so the React app knows who the user is
echo html_writer::start_tag('script');
echo "
    localStorage.setItem('user', JSON.stringify({
        id: '" . $USER->id . "',
        name: '" . addslashes(fullname($USER)) . "',
        email: '" . addslashes($USER->email) . "',
        user_type: '" . (is_siteadmin() ? 'teacher' : 'student') . "'
    }));
";
echo html_writer::end_tag('script');


// Root element for React app
echo html_writer::div('', '', array('id' => 'root'));

// Load React bundle
$pluginurl = new moodle_url('/mod/codingplayground/dist');
$jsfile = __DIR__ . '/dist/assets/index.js';
$cssfile = __DIR__ . '/dist/assets/index.css';

$cachebust = file_exists($jsfile) ? filemtime($jsfile) : time();

if (file_exists($cssfile)) {
    echo html_writer::tag('link', '', array(
        'rel' => 'stylesheet',
        'href' => $pluginurl . '/assets/index.css?v=' . $cachebust
    ));

    // Force-hide redundant Moodle page headers and breadcrumbs
    echo html_writer::start_tag('style');
    echo "
        #page-header, 
        .header-main, 
        .breadcrumb, 
        .breadcrumb-item, 
        .page-context-header, 
        [role='navigation'][aria-label='Breadcrumbs'],
        .secondary-navigation {
            display: none !important;
        }
        #region-main,
        #region-main-box,
        #page-content,
        .main-inner,
        .header-maxwidth {
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            max-width: 100% !important;
            width: 100% !important;
        }
        body.limitedwidth #page.drawers .main-inner,
        body.playground-fullscreen-active #page.drawers .main-inner {
            max-width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
    ";
    echo html_writer::end_tag('style');
}

if (file_exists($jsfile)) {
    // If we're on this global page, we want the React app to start at /playgrounds
    // We can use history.pushState if needed, but the React app will read the current URL.
    // To fix the routing, we can temporarily change the URL in the browser's view.
    echo html_writer::start_tag('script');
    echo "
        if (window.location.pathname.endsWith('all_playgrounds.php')) {
            // This is a hack to make the React router think it's at /playgrounds
            // while we are actually on this PHP page.
            // window.history.replaceState({}, '', '/playgrounds');
        }
    ";
    echo html_writer::end_tag('script');

    echo html_writer::tag('script', '', array(
        'type' => 'module',
        'src' => $pluginurl . '/assets/index.js?v=' . $cachebust
    ));
} else {
    echo html_writer::tag('div', 'Plugin not built. Please run build process.', array('class' => 'alert alert-error'));
}

// Finish the page
echo $OUTPUT->footer();
