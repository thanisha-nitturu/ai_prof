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
 * Activity event reminder handler.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/local/reminders/reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/activity_handlers.class.php');

/**
 * Class to specify the reminder message object for due events.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class due_reminder extends course_reminder {

    /**
     * @var object
     */
    private $coursemodule;
    /**
     * @var object
     */
    private $cm;
    /**
     * Activity reference.
     *
     * @var object
     */
    private $activityobj;
    /**
     * Activity name.
     * @var string
     */
    private $modname;

    /**
     * Creates new activity reminder instance.
     *
     * @param object $event calendar event.
     * @param object $course course instance.
     * @param object $cm coursemodulecontext instance.
     * @param object $coursemodule course module.
     * @param integer $aheaddays ahead days in number.
     * @param object $customtime contains the custom time value and unit (if configured).
     */
    public function __construct($event, $course, $cm, $coursemodule, $aheaddays = 1, $customtime = null) {
        parent::__construct($event, $course, $aheaddays, $customtime);
        $this->cm = $cm;
        $this->coursemodule = $coursemodule;
    }

    /**
     * Set activity instance if there is any.
     *
     * @param string $modulename module name.
     * @param object $activity activity instance
     */
    public function set_activity($modulename, $activity) {
        $this->activityobj = $activity;
        $this->modname = $modulename;
    }

    /**
     * Cleanup this reminder instance.
     */
    public function cleanup() {
        parent::cleanup();

        if (isset($this->activityobj)) {
            unset($this->activityobj);
        }
    }

    /**
     * Filter out users who still does not have completed this activity.
     *
     * @param array $users user array to check.
     * @param string $type call type.
     * @return array array of filtered users.
     */
    public function filter_authorized_users($users, $type=null) {
        global $CFG;

        if (isset($CFG->local_reminders_noremindersforcompleted)
            && !$CFG->local_reminders_noremindersforcompleted) {
                return $users;
        }

        if (!empty($this->modname) && !empty($this->activityobj)) {
            $clsname = 'local_reminder_'.$this->modname.'_handler';
            if (class_exists($clsname)) {
                $handlercls = new $clsname;
                return $handlercls->filter_authorized_users($users, $type, $this->activityobj,
                    $this->course, $this->coursemodule, $this->cm);
            } else {
                try {
                    $handlercls = new local_reminder_generic_handler;
                    return $handlercls->filter_authorized_users($users, $type, $this->activityobj,
                        $this->course, $this->coursemodule, $this->cm);
                } catch (Exception $ex) {
                    mtrace('Error occurred while processing with generic activity handler!'.$ex->getMessage());
                }
            }
        }
        return $users;
    }

    /**
     * Generates a message content as a HTML for activities.
     *
     * @param object $user The user object
     * @param object $changetype change type (add/update/removed/overdue)
     * @param stdClass $ctxinfo additional context info needed to process.
     * @return string Message content as HTML text.
     */
    public function get_message_html($user=null, $changetype=null, $ctxinfo=null) {
        // For quiz activities: return a clean custom body with no standard labels.
        if (!empty($this->modname) && $this->modname === 'quiz') {
            return $this->get_quiz_message_html($user, $changetype, $ctxinfo);
        }

        $htmlmail = $this->get_html_header();
        $htmlmail .= html_writer::start_tag('body', ['id' => 'email']);
        $htmlmail .= $this->get_reminder_header();
        $htmlmail .= html_writer::start_tag('div');
        $htmlmail .= html_writer::start_tag('table',
                ['cellspacing' => 0, 'cellpadding' => 8, 'style' => $this->tbodycssstyle]);

        $contenttitle = $this->get_message_title();
        if (!isemptystring($changetype)) {
            if (!is_null($ctxinfo) && property_exists($ctxinfo, 'overduetitle') && !isemptystring($ctxinfo->overduetitle)) {
                $titleprefixlangstr = get_string('calendarevent'.strtolower($changetype).'prefix', 'local_reminders');
                $contenttitle = "[$ctxinfo->overduetitle]: $contenttitle";
            }
        }
        $htmlmail .= html_writer::start_tag('tr');
        $htmlmail .= html_writer::start_tag('td', ['colspan' => 2]);
        $htmlmail .= html_writer::link($this->generate_event_link(),
                html_writer::tag('h3', $contenttitle, ['style' => $this->titlestyle]),
                ['style' => 'text-decoration: none']);
        $htmlmail .= html_writer::end_tag('td').html_writer::end_tag('tr');

        if (!isemptystring($changetype) && $changetype == REMINDERS_CALL_TYPE_OVERDUE
            && !is_null($ctxinfo) && !isemptystring($ctxinfo->overduemessage)) {
            $htmlmail .= html_writer::start_tag('tr');
            $htmlmail .= html_writer::start_tag('td', ['colspan' => 2]);
            $htmlmail .= html_writer::tag('h4', $ctxinfo->overduemessage, ['style' => $this->overduestyle]);
            $htmlmail .= html_writer::end_tag('td').html_writer::end_tag('tr');
        }

        $htmlmail .= $this->write_table_row(get_string('contentwhen', 'local_reminders'),
            format_event_time_duration($user, $this->event));
        $htmlmail .= $this->write_location_info($this->event);

        $htmlmail .= $this->write_table_row(get_string('contenttypecourse', 'local_reminders'), $this->course->fullname);

        $activitylink = html_writer::link($this->cm->get_url(), $this->cm->get_context_name(), ['target' => '_blank']);
        $htmlmail .= $this->write_table_row(get_string('contenttypeactivity', 'local_reminders'), $activitylink);

        $formattercls = null;
        if (!empty($this->modname) && !empty($this->activityobj)) {
            $clsname = 'local_reminder_'.$this->modname.'_handler';
            if (class_exists($clsname)) {
                $formattercls = new $clsname;
                $formattercls->append_info($htmlmail, $this->modname, $this->activityobj, $user, $this->event, $this);
            }
        }

        $description = isset($formattercls) ? $formattercls->get_description($this->activityobj, $this->event) :
            $this->event->description;
        $htmlmail .= $this->write_description($description, $this->event);

        $htmlmail .= $this->get_html_footer();
        return $htmlmail.html_writer::end_tag('table').
            html_writer::end_tag('div').
            html_writer::end_tag('body').
            html_writer::end_tag('html');
    }

    /**
     * Returns the section name for this activity.
     * Uses the custom section name if set, otherwise falls back to "Section {number}".
     *
     * @return string section name.
     */
    private function get_section_name() {
        global $DB;

        // $this->coursemodule->section holds the section ID (not the section number).
        $section = $DB->get_record('course_sections', ['id' => $this->coursemodule->section]);
        if ($section) {
            // If the teacher gave the section a custom name, use it.
            if (!empty($section->name)) {
                return $section->name;
            }
            // Otherwise fall back to "Section {number}", e.g. "Section 2".
            return 'Section ' . $section->section;
        }
        return '';
    }

    /**
     * Generates a clean custom HTML body for quiz reminders.
     * Only shows our custom branded message — no standard labels.
     *
     * @param object $user The user object
     * @param object $changetype change type
     * @param stdClass $ctxinfo additional context info
     * @return string custom HTML body for quiz reminders
     */
    private function get_quiz_message_html($user=null, $changetype=null, $ctxinfo=null) {
        $activityname = preg_replace('/\s+(opens|closes)$/i', '', $this->event->name);
        $coursename   = $this->course->fullname;
        $firstname    = $user ? $user->firstname : 'Student';
        $duedate      = format_event_time_duration($user, $this->event, null, false, 'html');
        $sectionname  = $this->get_section_name();

        $tzone = 99;
        if (isset($user) && !empty($user)) {
            $tzone = reminders_get_timezone($user);
        }

        // Attempts and pass grade.
        $attempts = $this->activityobj->attempts == 0 ? "Unlimited" : $this->activityobj->attempts;
        $passgrade = !empty($this->activityobj->gradepass) ? $this->activityobj->gradepass : 0;
        $maxgrade = !empty($this->activityobj->grade) ? $this->activityobj->grade : 0;
        $passpercent = ($maxgrade > 0) ? round(($passgrade / $maxgrade) * 100) : 0;

        // Determine if this is an overdue reminder.
        $isoverdue = (!empty($changetype) && $changetype == REMINDERS_CALL_TYPE_OVERDUE);

        // Determine if this is an "opening soon" reminder.
        $isopening = (isset($this->event->eventtype) && $this->event->eventtype === 'open')
                     || (preg_match('/opens$/i', $this->event->name));

        $ofsection = !empty($sectionname) ? " of <strong>" . htmlspecialchars($sectionname) . "</strong>" : "";
        $phrase    = "<strong>" . htmlspecialchars($activityname) . "</strong>{$ofsection} in the course <strong>" . htmlspecialchars($coursename) . "</strong>";

        $quizinfo = "<p><strong>Attempts Allowed:</strong> {$attempts}<br>"
                  . "<strong>Completion Requirement:</strong> You must achieve a minimum grade of 70% in at least one attempt to successfully complete the quiz.</p>";

        if ($isoverdue) {
            $custombody = "<p>Hi {$firstname},</p> "
                . "<p>Just a quick heads-up that {$phrase} was due on <strong>{$duedate}</strong>.</p> "
                . $quizinfo
                . "<p>Don't worry—you can still submit! Your work will be marked as a late submission, but the most important thing is getting it turned in so you don't fall behind.</p> "
                . "<p>If you're stuck or having technical trouble, just reply to this email and we'll help you out.</p>";
        } else if ($isopening) {
            $quizopens  = !empty($this->activityobj->timeopen) ? userdate($this->activityobj->timeopen, '', $tzone) : 'Always open';
            $quizcloses = !empty($this->activityobj->timeclose) ? userdate($this->activityobj->timeclose, '', $tzone) : 'No closing date';

            $custombody = "<p>Hi {$firstname},</p>"
                . "<p>The quiz for <strong>" . htmlspecialchars($sectionname) . "</strong> in your <strong>" . htmlspecialchars($coursename) . "</strong> course is opening soon. Here are the key details you need to know:</p>"
                . "<ul>"
                . "    <li><strong>Opens:</strong> {$quizopens}</li>"
                . "    <li><strong>Closes:</strong> {$quizcloses}</li>"
                . "</ul>"
                . $quizinfo
                . "<p>Best of luck! If you have any technical issues or questions, simply reply to this email and the <strong>ai.prof Support</strong> team will assist you.</p>";
        } else {
            $custombody = "<p>Hi {$firstname},</p> "
                . "<p>This is a friendly reminder that {$phrase} is due soon.</p> "
                . $quizinfo
                . "<p><strong>Deadline: {$duedate}</strong></p> "
                . "<p>We recommend getting your submission in early to avoid any last-minute technical hitches.</p> "
                . "<p>If you're having any trouble or need clarification on the requirements, just reply to this email and we'll be happy to help!</p>";
        }


        $bodystyle = 'font-family:Arial,Sans-serif;font-size:14px;color:#333;line-height:1.6;padding:20px;';

        $htmlmail  = html_writer::tag('head', '');
        $htmlmail .= html_writer::start_tag('body', ['id' => 'email']);
        $htmlmail .= html_writer::tag('div', $custombody, ['style' => $bodystyle]);
        $htmlmail .= html_writer::end_tag('body');
        $htmlmail .= html_writer::end_tag('html');
        return $htmlmail;
    }

    /**
     * Generates a message content as a plain-text for activity.
     *
     * @param object $user The user object
     * @param object $changetype change type (add/update/removed)
     * @return string Message content as plain-text.
     */
    public function get_message_plaintext($user=null, $changetype=null) {
        // For quiz activities: return a clean custom plain-text body with no standard labels.
        if (!empty($this->modname) && $this->modname === 'quiz') {
            $activityname = preg_replace('/\s+(opens|closes)$/i', '', $this->event->name);
            $coursename   = $this->course->fullname;
            $firstname    = $user ? $user->firstname : 'Student';
            $duedate      = format_event_time_duration($user, $this->event, null, false, 'plain');
            $sectionname  = $this->get_section_name();
            $isoverdue    = (!empty($changetype) && $changetype == REMINDERS_CALL_TYPE_OVERDUE);

            // Determine if this is an "opening soon" reminder.
            $isopening = (isset($this->event->eventtype) && $this->event->eventtype === 'open')
                         || (preg_match('/opens$/i', $this->event->name));

            $tzone = 99;
            if (isset($user) && !empty($user)) {
                $tzone = reminders_get_timezone($user);
            }

            // Attempts and pass grade.
            $attempts = $this->activityobj->attempts == 0 ? "Unlimited" : $this->activityobj->attempts;
            $passgrade = !empty($this->activityobj->gradepass) ? $this->activityobj->gradepass : 0;
            $maxgrade = !empty($this->activityobj->grade) ? $this->activityobj->grade : 0;
            $passpercent = ($maxgrade > 0) ? round(($passgrade / $maxgrade) * 100) : 0;

            $ofsection = !empty($sectionname) ? " of '{$sectionname}'" : "";
            $phrase    = "'{$activityname}'{$ofsection} in the course '{$coursename}'";

            $quizinfo = "Attempts Allowed: {$attempts}\n"
                      . "Completion Requirement: You must achieve a minimum grade of 70% in at least one attempt to successfully complete the quiz.\n\n";

            if ($isoverdue) {
                return "Hi {$firstname},\n\n"
                    . "Just a quick heads-up that {$phrase} was due on {$duedate}.\n\n"
                    . $quizinfo
                    . "Don't worry—you can still submit! Your work will be marked as a late submission, but the most important thing is getting it turned in so you don't fall behind.\n\n"
                    . "If you're stuck or having technical trouble, just reply to this email and we'll help you out.";
            } else if ($isopening) {
                $quizopens  = !empty($this->activityobj->timeopen) ? userdate($this->activityobj->timeopen, '', $tzone) : 'Always open';
                $quizcloses = !empty($this->activityobj->timeclose) ? userdate($this->activityobj->timeclose, '', $tzone) : 'No closing date';

                return "Hi {$firstname},\n\n"
                    . "The quiz for '{$sectionname}' in your '{$coursename}' course is opening soon. Here are the key details you need to know:\n\n"
                    . "Opens: {$quizopens}\n"
                    . "Closes: {$quizcloses}\n\n"
                    . $quizinfo
                    . "Best of luck! If you have any technical issues or questions, simply reply to this email and the ai.prof Support team will assist you.";
            } else {
                return "Hi {$firstname},\n\n"
                    . "This is a friendly reminder that {$phrase} is due soon.\n\n"
                    . $quizinfo
                    . "Deadline: {$duedate}\n\n"
                    . "We recommend getting your submission in early to avoid any last-minute technical hitches.\n\n"
                    . "If you're having any trouble or need clarification on the requirements, just reply to this email and we'll be happy to help!";
            }
        }

        $text = $this->get_message_title().' '.$this->get_aheaddays_plain()."\n";
        $text .= get_string('contentwhen', 'local_reminders').': '.$this->get_tzinfo_plain($user, $this->event)."\n";
        $text .= get_string('contenttypecourse', 'local_reminders').': '.$this->course->fullname."\n";
        $text .= get_string('contenttypeactivity', 'local_reminders').': '.$this->cm->get_context_name()."\n";
        $text .= get_string('contentdescription', 'local_reminders').': '.$this->event->description."\n";

        return $text;
    }

    /**
     * The name 'reminder_due'.
     *
     * @return string Message provider name
     */
    protected function get_message_provider() {
        return 'reminders_due';
    }

    /**
     * Generates a message title for the activity reminder.
     *
     * @param string $type type of message to be send (null=reminder cron)
     * @return string Message title as a plain-text.
     */
    public function get_message_title($type=null) {
        global $CFG;

        // For quiz activities: use the custom subject format required by ai.prof.
        if (!empty($this->modname) && $this->modname === 'quiz') {
            $activityname    = preg_replace('/\s+(opens|closes)$/i', '', $this->event->name);
            $coursename      = $this->course->fullname;
            $isoverdue       = (!empty($type) && $type == REMINDERS_CALL_TYPE_OVERDUE);

            // Determine if this is an "opening soon" reminder.
            $isopening = (isset($this->event->eventtype) && $this->event->eventtype === 'open')
                         || (preg_match('/opens$/i', $this->event->name));

            if ($isoverdue) {
                return "Action Required: Your submission for {$activityname} in {$coursename}";
            } else if ($isopening) {
                return "Upcoming Quiz: {$activityname} [{$coursename}]";
            } else {
                return "Upcoming Deadline: {$activityname} [{$coursename}]";
            }
        }

        $title = '('.$this->course->shortname;
        if (!empty($this->cm) &&
            (!isset($CFG->local_reminders_showmodnameintitle) || $CFG->local_reminders_showmodnameintitle > 0)) {
            $title .= '-'.get_string('modulename', $this->event->modulename);
        }
        return $title.') '.$this->event->name;
    }

    /**
     * Overrides create_reminder_message_object to set the subject directly
     * (without [prefix] brackets) when the activity is a quiz.
     *
     * @param object $admin impersonated admin user.
     * @return object message object.
     */
    public function create_reminder_message_object($admin=null) {
        if (!empty($this->modname) && $this->modname === 'quiz') {
            // Build the message object without adding [Prefix] brackets around the subject.
            if ($admin == null) {
                $admin = get_admin();
            }

            $contenthtml = $this->get_message_html();
            $titlehtml   = $this->get_message_title(); // Already fully custom for quiz.

            $cheaders = $this->get_custom_headers();
            if (!empty($cheaders)) {
                $admin->customheaders = $cheaders;
            }

            $eventdata = new \core\message\message();
            $eventdata->component         = 'local_reminders';
            $eventdata->name              = $this->get_message_provider();
            $eventdata->userfrom          = $admin;
            $eventdata->subject           = $titlehtml;
            $eventdata->fullmessage       = $this->get_message_plaintext();
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = $contenthtml;
            $eventdata->smallmessage        = $this->get_message_plaintext();
            $eventdata->notification      = $this->notification;

            $this->eventobject = $eventdata;
            return $eventdata;
        }

        // For all other activity types, use the standard parent implementation.
        return parent::create_reminder_message_object($admin);
    }

    /**
     * Overrides get_updating_event_message to set the subject directly
     * for quiz overdue notifications.
     *
     * @param string $changetype change type.
     * @param object $admin admin user.
     * @param object $touser to user.
     * @param stdClass $ctxinfo additional context info.
     * @return object message object.
     */
    public function get_updating_event_message($changetype, $admin=null, $touser=null, $ctxinfo=null) {
        if (!empty($this->modname) && $this->modname === 'quiz') {
            $fromuser = $admin ?: get_admin();

            $contenthtml = $this->get_message_html($touser, $changetype, $ctxinfo);
            $msgtitle = $this->get_message_title($changetype);
            $smallmsg = $this->get_message_plaintext($touser, $changetype);

            $eventdata = new \core\message\message();
            $eventdata->component           = 'local_reminders';
            $eventdata->name                = $this->get_message_provider();
            $eventdata->userfrom            = $fromuser;
            $eventdata->userto              = $touser;
            $eventdata->subject             = $msgtitle;
            $eventdata->fullmessage         = $smallmsg;
            $eventdata->fullmessageformat   = FORMAT_PLAIN;
            $eventdata->fullmessagehtml     = $contenthtml;
            $eventdata->smallmessage        = $smallmsg;
            $eventdata->notification        = $this->notification;

            return $eventdata;
        }
        return parent::get_updating_event_message($changetype, $admin, $touser, $ctxinfo);
    }

    /**
     * Adds activity id and name to header.
     *
     * @return array of new header.
     */
    public function get_custom_headers() {
        $headers = parent::get_custom_headers();

        $headers[] = 'X-Activity-Id: '.$this->cm->id;
        $headers[] = 'X-Activity-Name: '.$this->cm->get_context_name();

        return $headers;
    }

}
