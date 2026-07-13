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
 * This file contains a renderer for the assignment class
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_assign\output;

use assign_files;
use html_writer;
use mod_assign\output\grading_app;
use portfolio_add_button;
use stored_file;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * A custom renderer class that extends the plugin_renderer_base and is used by the assign module.
 *
 * @package mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /** @var string a unique ID. */
    public $htmlid;

    /**
     * Rendering assignment files
     *
     * @param \context $context
     * @param int $userid
     * @param string $filearea
     * @param string $component
     * @param \stdClass $course
     * @param \stdClass $coursemodule
     * @return string
     */
    public function assign_files(\context $context, $userid, $filearea, $component, $course = null, $coursemodule = null) {
        return $this->render(new \assign_files($context, $userid, $filearea, $component, $course, $coursemodule));
    }

    /**
     * Rendering assignment files
     *
     * @param \assign_files $tree
     * @return string
     */
    public function render_assign_files(\assign_files $tree) {
        $this->htmlid = \html_writer::random_id('assign_files_tree');
        $this->page->requires->js_init_call('M.mod_assign.init_tree', array(true, $this->htmlid));
        $html = '<div id="'.$this->htmlid.'">';
        $html .= $this->htmllize_tree($tree, $tree->dir);
        $html .= '</div>';

        if ($tree->portfolioform) {
            $html .= $tree->portfolioform;
        }
        return $html;
    }

    /**
     * Utility function to add a row of data to a table with 2 columns where the first column is the table's header.
     * Modified the table param and does not return a value.
     *
     * @param \html_table $table The table to append the row of data to
     * @param string $first The first column text
     * @param string $second The second column text
     * @param array $firstattributes The first column attributes (optional)
     * @param array $secondattributes The second column attributes (optional)
     * @return void
     */
    private function add_table_row_tuple(\html_table $table, $first, $second, $firstattributes = [],
            $secondattributes = []) {
        $row = new \html_table_row();
        $cell1 = new \html_table_cell($first);
        $cell1->header = true;
        if (!empty($firstattributes)) {
            $cell1->attributes = $firstattributes;
        }
        $cell2 = new \html_table_cell($second);
        if (!empty($secondattributes)) {
            $cell2->attributes = $secondattributes;
        }
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;
    }

    /**
     * Render a grading message notification
     * @param \assign_gradingmessage $result The result to render
     * @return string
     */
    public function render_assign_gradingmessage(\assign_gradingmessage $result) {
        $urlparams = array('id' => $result->coursemoduleid, 'action'=>'grading');
        if (!empty($result->page)) {
            $urlparams['page'] = $result->page;
        }
        $url = new \moodle_url('/mod/assign/view.php', $urlparams);
        $classes = $result->gradingerror ? 'notifyproblem' : 'notifysuccess';

        $o = '';
        $o .= $this->output->heading($result->heading, 4);
        $o .= $this->output->notification($result->message, $classes);
        $o .= $this->output->continue_button($url);
        return $o;
    }

    /**
     * Render the generic form
     * @param \assign_form $form The form to render
     * @return string
     */
    public function render_assign_form(\assign_form $form) {
        $o = '';
        if ($form->jsinitfunction) {
            $this->page->requires->js_init_call($form->jsinitfunction, array());
        }
        $o .= $this->output->box_start('boxaligncenter ' . $form->classname);
        $o .= $this->moodleform($form->form);
        $o .= $this->output->box_end();
        return $o;
    }

    /**
     * Render the user summary
     *
     * @param \assign_user_summary $summary The user summary to render
     * @return string
     */
    public function render_assign_user_summary(\assign_user_summary $summary) {
        $o = '';
        $supendedclass = '';
        $suspendedicon = '';

        if (!$summary->user) {
            return;
        }

        if ($summary->suspendeduser) {
            $supendedclass = ' usersuspended';
            $suspendedstring = get_string('userenrolmentsuspended', 'grades');
            $suspendedicon = ' ' . $this->pix_icon('i/enrolmentsuspended', $suspendedstring);
        }
        $o .= $this->output->container_start('usersummary');
        $o .= $this->output->box_start('boxaligncenter usersummarysection'.$supendedclass);
        if ($summary->blindmarking) {
            $o .= get_string('hiddenuser', 'assign') . $summary->uniqueidforuser.$suspendedicon;
        } else {
            $o .= $this->output->user_picture($summary->user);
            $o .= $this->output->spacer(array('width'=>30));
            $urlparams = array('id' => $summary->user->id, 'course'=>$summary->courseid);
            $url = new \moodle_url('/user/view.php', $urlparams);
            $fullname = fullname($summary->user, $summary->viewfullnames);
            $extrainfo = array();
            foreach ($summary->extrauserfields as $extrafield) {
                $extrainfo[] = s($summary->user->$extrafield);
            }
            if (count($extrainfo)) {
                $fullname .= ' (' . implode(', ', $extrainfo) . ')';
            }
            $fullname .= $suspendedicon;
            $o .= $this->output->action_link($url, $fullname);
        }
        $o .= $this->output->box_end();
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * Render the submit for grading page
     *
     * @param \assign_submit_for_grading_page $page
     * @return string
     */
    public function render_assign_submit_for_grading_page($page) {
        $o = '';

        $o .= $this->output->container_start('submitforgrading');
        $o .= $this->output->heading(get_string('confirmsubmissionheading', 'assign'), 3);

        $cancelurl = new \moodle_url('/mod/assign/view.php', array('id' => $page->coursemoduleid));
        if (count($page->notifications)) {
            // At least one of the submission plugins is not ready for submission.

            $o .= $this->output->heading(get_string('submissionnotready', 'assign'), 4);

            foreach ($page->notifications as $notification) {
                $o .= $this->output->notification($notification);
            }

            $o .= $this->output->continue_button($cancelurl);
        } else {
            // All submission plugins ready - show the confirmation form.
            $o .= $this->moodleform($page->confirmform);
        }
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * Page is done - render the footer.
     *
     * @return void
     */
    public function render_footer() {
        return $this->output->footer();
    }

    /**
     * Render the header.
     *
     * @param assign_header $header
     * @return string
     */
    public function render_assign_header(assign_header $header) {
        if ($header->subpage) {
            $this->page->navbar->add($header->subpage, $header->subpageurl);
            $args = ['contextname' => $header->context->get_context_name(false, true), 'subpage' => $header->subpage];
            $title = get_string('subpagetitle', 'assign', $args);
        } else {
            $title = $header->context->get_context_name(false, true);
        }
        $courseshortname = $header->context->get_course_context()->get_context_name(false, true);
        $title = $courseshortname . ': ' . $title;
        $heading = format_string($header->assign->name, false, array('context' => $header->context));

        $this->page->set_title($title);
        $this->page->set_heading($this->page->course->fullname);

        $description = $header->preface;
        if ($header->showintro || $header->activity) {
            $description = $this->output->box_start('generalbox boxaligncenter');
            if ($header->showintro) {
                $description .= format_module_intro('assign', $header->assign, $header->coursemoduleid);
            }
            if ($header->activity) {
                $description .= $this->format_activity_text($header->assign, $header->coursemoduleid);
            }
            $description .= $header->postfix;
            $description .= $this->output->box_end();
        }

        $activityheader = $this->page->activityheader;
        $activityheader->set_attrs([
            'title' => $activityheader->is_title_allowed() ? $heading : '',
            'description' => $description
        ]);

        return $this->output->header();
    }

    /**
     * Render the header for an individual plugin.
     *
     * @param \assign_plugin_header $header
     * @return string
     */
    public function render_assign_plugin_header(\assign_plugin_header $header) {
        $o = $header->plugin->view_header();
        return $o;
    }

    /**
     * Render a table containing the current status of the grading process.
     *
     * @param \assign_grading_summary $summary
     * @return string
     */
    public function render_assign_grading_summary(\assign_grading_summary $summary) {
        // Create a table for the data.
        $o = '';
        $o .= $this->output->container_start('gradingsummary');
        $o .= $this->output->heading(get_string('gradingsummary', 'assign'), 3);

        if (isset($summary->cm)) {
            $currenturl = new \moodle_url('/mod/assign/view.php', array('id' => $summary->cm->id));
            $o .= groups_print_activity_menu($summary->cm, $currenturl->out(), true, participationonly: false);
        }

        $o .= $this->output->box_start('boxaligncenter gradingsummarytable');
        $t = new \html_table();
        $t->attributes['class'] = 'generaltable table table-striped table-bordered table-hover';
        $t->caption = get_string('gradingsummary', 'assign');
        $t->captionhide = true; // Hidden because it matches the title above.

        // Visibility Status.
        $cell1content = get_string('hiddenfromstudents');
        $cell2content = (!$summary->isvisible) ? get_string('yes') : get_string('no');
        $this->add_table_row_tuple($t, $cell1content, $cell2content);

        // Status.
        if ($summary->teamsubmission) {
            if ($summary->warnofungroupedusers === \assign_grading_summary::WARN_GROUPS_REQUIRED) {
                $o .= $this->output->notification(get_string('ungroupedusers', 'assign'));
            } else if ($summary->warnofungroupedusers === \assign_grading_summary::WARN_GROUPS_OPTIONAL) {
                $o .= $this->output->notification(get_string('ungroupedusersoptional', 'assign'));
            }
            $cell1content = get_string('numberofteams', 'assign');
        } else {
            $cell1content = get_string('numberofparticipants', 'assign');
        }

        $cell2content = $summary->participantcount;
        $this->add_table_row_tuple($t, $cell1content, $cell2content);

        // Drafts count and dont show drafts count when using offline assignment.
        if ($summary->submissiondraftsenabled && $summary->submissionsenabled) {
            $cell1content = get_string('numberofdraftsubmissions', 'assign');
            $cell2content = $summary->submissiondraftscount;
            $this->add_table_row_tuple($t, $cell1content, $cell2content);
        }

        // Submitted for grading.
        if ($summary->submissionsenabled) {
            $cell1content = get_string('numberofsubmittedassignments', 'assign');
            $cell2content = $summary->submissionssubmittedcount;
            $this->add_table_row_tuple($t, $cell1content, $cell2content);

            if (!$summary->teamsubmission) {
                $cell1content = get_string('numberofsubmissionsneedgrading', 'assign');
                $cell2content = $summary->submissionsneedgradingcount;
                $this->add_table_row_tuple($t, $cell1content, $cell2content);
            }
        }

        $time = time();
        if ($summary->duedate) {
            // Time remaining.
            $duedate = $summary->duedate;
            $cell1content = get_string('timeremaining', 'assign');
            if ($summary->courserelativedatesmode) {
                $cell2content = get_string('relativedatessubmissiontimeleft', 'mod_assign');
            } else {
                if ($duedate - $time <= 0) {
                    $cell2content = get_string('assignmentisdue', 'assign');
                } else {
                    $cell2content = format_time($duedate - $time);
                }
            }

            $this->add_table_row_tuple($t, $cell1content, $cell2content);

            if ($duedate < $time) {
                $cell1content = get_string('latesubmissions', 'assign');
                $cutoffdate = $summary->cutoffdate;
                if ($cutoffdate) {
                    if ($cutoffdate > $time) {
                        $cell2content = get_string('latesubmissionsaccepted', 'assign', userdate($summary->cutoffdate));
                    } else {
                        $cell2content = get_string('nomoresubmissionsaccepted', 'assign');
                    }

                    $this->add_table_row_tuple($t, $cell1content, $cell2content);
                }
            }

        }

        // Add time limit info if there is one.
        $timelimitenabled = get_config('assign', 'enabletimelimit');
        if ($timelimitenabled && $summary->timelimit > 0) {
            $cell1content = get_string('timelimit', 'assign');
            $cell2content = format_time($summary->timelimit);
            $this->add_table_row_tuple($t, $cell1content, $cell2content, [], []);
        }

        // All done - write the table.
        $o .= \html_writer::table($t);
        $o .= $this->output->box_end();

        // Close the container and insert a spacer.
        $o .= $this->output->container_end();
        $o .= \html_writer::end_tag('center');

        return $o;
    }

    /**
     * Render a table containing all the current grades and feedback.
     *
     * @param \assign_feedback_status $status
     * @return string
     */
    public function render_assign_feedback_status(\assign_feedback_status $status) {
        $o = '';

        $o .= $this->output->container_start('feedback');
        $o .= $this->output->heading(get_string('feedback', 'assign'), 3);
        $o .= $this->output->box_start('boxaligncenter feedbacktable');
        $t = new \html_table();
        $t->caption = get_string('feedback', 'assign');
        $t->captionhide = true; // Hidden because it matches the title above.

        // Grade.
        if (isset($status->gradefordisplay)) {
            $cell1content = get_string('gradenoun');
            $cell2content = $status->gradefordisplay;
            $this->add_table_row_tuple($t, $cell1content, $cell2content);

            // Grade date.
            $cell1content = get_string('gradedon', 'assign');
            $cell2content = userdate($status->gradeddate);
            $this->add_table_row_tuple($t, $cell1content, $cell2content);
        }

        if ($status->grader) {
            // Grader.
            $cell1content = get_string('gradedby', 'assign');
            $cell2content = $this->output->user_picture($status->grader) .
                            $this->output->spacer(array('width' => 30)) .
                            fullname($status->grader, $status->canviewfullnames);
            $this->add_table_row_tuple($t, $cell1content, $cell2content);
        }

        foreach ($status->feedbackplugins as $plugin) {
            if ($plugin->is_enabled() &&
                    $plugin->is_visible() &&
                    $plugin->has_user_summary() &&
                    !empty($status->grade) &&
                    !$plugin->is_empty($status->grade)) {

                $displaymode = \assign_feedback_plugin_feedback::SUMMARY;
                $pluginfeedback = new \assign_feedback_plugin_feedback($plugin,
                                                                      $status->grade,
                                                                      $displaymode,
                                                                      $status->coursemoduleid,
                                                                      $status->returnaction,
                                                                      $status->returnparams);
                $cell1content = $plugin->get_name();
                $cell2content = $this->render($pluginfeedback);
                $this->add_table_row_tuple($t, $cell1content, $cell2content);
            }
        }

        $o .= \html_writer::table($t);
        $o .= $this->output->box_end();

        if (!empty($status->gradingcontrollergrade)) {
            $o .= $this->output->heading(get_string('gradebreakdown', 'assign'), 4);
            $o .= $status->gradingcontrollergrade;
        }

        $o .= $this->output->container_end();
        return $o;
    }

    /**
     * Render a compact view of the current status of the submission.
     *
     * @param \assign_submission_status_compact $status
     * @return string
     */
    public function render_assign_submission_status_compact(\assign_submission_status_compact $status) {
        $o = '';
        $o .= $this->output->container_start('submissionstatustable');
        $o .= $this->output->heading(get_string('submission', 'assign'), 3);

        if ($status->teamsubmissionenabled) {
            $group = $status->submissiongroup;
            if ($group) {
                $team = format_string($group->name, false, ['context' => $status->context]);
            } else if ($status->preventsubmissionnotingroup) {
                if (count($status->usergroups) == 0) {
                    $team = '<span class="alert alert-error">' . get_string('noteam', 'assign') . '</span>';
                } else if (count($status->usergroups) > 1) {
                    $team = '<span class="alert alert-error">' . get_string('multipleteams', 'assign') . '</span>';
                }
            } else {
                $team = get_string('defaultteam', 'assign');
            }
            $o .= $this->output->container(get_string('teamname', 'assign', $team), 'teamname');
        }

        if (!$status->teamsubmissionenabled) {
            if ($status->submission && $status->submission->status != ASSIGN_SUBMISSION_STATUS_NEW) {
                $statusstr = get_string('submissionstatus_' . $status->submission->status, 'assign');
                $o .= $this->output->container($statusstr, 'submissionstatus' . $status->submission->status);
            } else {
                if (!$status->submissionsenabled) {
                    $o .= $this->output->container(get_string('noonlinesubmissions', 'assign'), 'submissionstatus');
                } else {
                    $o .= $this->output->container(get_string('noattempt', 'assign'), 'submissionstatus');
                }
            }
        } else {
            $group = $status->submissiongroup;
            if (!$group && $status->preventsubmissionnotingroup) {
                $o .= $this->output->container(get_string('nosubmission', 'assign'), 'submissionstatus');
            } else if ($status->teamsubmission && $status->teamsubmission->status != ASSIGN_SUBMISSION_STATUS_NEW) {
                $teamstatus = $status->teamsubmission->status;
                $submissionsummary = get_string('submissionstatus_' . $teamstatus, 'assign');
                $groupid = 0;
                if ($status->submissiongroup) {
                    $groupid = $status->submissiongroup->id;
                }

                $members = $status->submissiongroupmemberswhoneedtosubmit;
                $userslist = array();
                foreach ($members as $member) {
                    $urlparams = array('id' => $member->id, 'course' => $status->courseid);
                    $url = new \moodle_url('/user/view.php', $urlparams);
                    if ($status->view == assign_submission_status::GRADER_VIEW && $status->blindmarking) {
                        $userslist[] = $member->alias;
                    } else {
                        $fullname = fullname($member, $status->canviewfullnames);
                        $userslist[] = $this->output->action_link($url, $fullname);
                    }
                }
                if (count($userslist) > 0) {
                    $userstr = join(', ', $userslist);
                    $formatteduserstr = get_string('userswhoneedtosubmit', 'assign', $userstr);
                    $submissionsummary .= $this->output->container($formatteduserstr);
                }
                $o .= $this->output->container($submissionsummary, 'submissionstatus' . $status->teamsubmission->status);
            } else {
                if (!$status->submissionsenabled) {
                    $o .= $this->output->container(get_string('noonlinesubmissions', 'assign'), 'submissionstatus');
                } else {
                    $o .= $this->output->container(get_string('nosubmission', 'assign'), 'submissionstatus');
                }
            }
        }

        // Is locked?
        if ($status->locked) {
            $o .= $this->output->container(get_string('submissionslocked', 'assign'), 'submissionlocked');
        }

        // Grading status.
        $statusstr = '';
        $classname = 'gradingstatus';
        if ($status->gradingstatus == ASSIGN_GRADING_STATUS_GRADED ||
            $status->gradingstatus == ASSIGN_GRADING_STATUS_NOT_GRADED) {
            $statusstr = get_string($status->gradingstatus, 'assign');
        } else {
            $gradingstatus = 'markingworkflowstate' . $status->gradingstatus;
            $statusstr = get_string($gradingstatus, 'assign');
        }
        if ($status->gradingstatus == ASSIGN_GRADING_STATUS_GRADED ||
            $status->gradingstatus == ASSIGN_MARKING_WORKFLOW_STATE_RELEASED) {
            $classname = 'submissiongraded';
        } else {
            $classname = 'submissionnotgraded';
        }

        $o .= $this->output->container($statusstr, $classname);

        $submission = $status->teamsubmission ? $status->teamsubmission : $status->submission;
        $duedate = $status->duedate;
        if ($duedate > 0) {

            if ($status->extensionduedate) {
                // Extension date.
                $duedate = $status->extensionduedate;
            }
        }

        // Time remaining.
        // Only add the row if there is a due date, or a countdown.
        if ($status->duedate > 0 || !empty($submission->timestarted)) {
            [$remaining, $classname] = $this->get_time_remaining($status);

            // If the assignment is not submitted, and there is a submission in progress,
            // Add a heading for the time limit.
            if (!empty($submission) &&
                $submission->status != ASSIGN_SUBMISSION_STATUS_SUBMITTED &&
                !empty($submission->timestarted)
            ) {
                $o .= $this->output->container(get_string('timeremaining', 'assign'));
            }
            $o .= $this->output->container($remaining, $classname);
        }

        // Show graders whether this submission is editable by students.
        if ($status->view == assign_submission_status::GRADER_VIEW) {
            if ($status->canedit) {
                $o .= $this->output->container(get_string('submissioneditable', 'assign'), 'submissioneditable');
            } else {
                $o .= $this->output->container(get_string('submissionnoteditable', 'assign'), 'submissionnoteditable');
            }
        }

        // Grading criteria preview.
        if (!empty($status->gradingcontrollerpreview)) {
            $o .= $this->output->container($status->gradingcontrollerpreview, 'gradingmethodpreview');
        }

        if ($submission) {

            if (!$status->teamsubmission || $status->submissiongroup != false || !$status->preventsubmissionnotingroup) {
                foreach ($status->submissionplugins as $plugin) {
                    $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
                    if ($plugin->is_enabled() &&
                        $plugin->is_visible() &&
                        $plugin->has_user_summary() &&
                        $pluginshowsummary
                    ) {

                        $displaymode = \assign_submission_plugin_submission::SUMMARY;
                        $pluginsubmission = new \assign_submission_plugin_submission($plugin,
                            $submission,
                            $displaymode,
                            $status->coursemoduleid,
                            $status->returnaction,
                            $status->returnparams);
                        $plugincomponent = $plugin->get_subtype() . '_' . $plugin->get_type();
                        $o .= $this->output->container($this->render($pluginsubmission), 'assignsubmission ' . $plugincomponent);
                    }
                }
            }
        }

        $o .= $this->output->container_end();
        return $o;
    }

    /**
     * Render a table containing the current status of the submission.
     *
     * @param assign_submission_status $status
     * @return string
     */
    public function render_assign_submission_status(assign_submission_status $status) {
        $o = '';
        $o .= $this->output->container_start('submissionstatustable');
        $o .= $this->output->heading(get_string('submissionstatusheading', 'assign'), 3);
        $time = time();

        $o .= $this->output->box_start('boxaligncenter submissionsummarytable');

        $t = new \html_table();
        $t->attributes['class'] = 'generaltable table table-striped table-bordered table-hover';
        $t->caption = get_string('submissionstatusheading', 'assign');
        $t->captionhide = true; // Hidden because it matches the title above.

        $warningmsg = '';
        if ($status->teamsubmissionenabled) {
            $cell1content = get_string('submissionteam', 'assign');
            $group = $status->submissiongroup;
            if ($group) {
                $cell2content = format_string($group->name, false, ['context' => $status->context]);
            } else if ($status->preventsubmissionnotingroup) {
                if (count($status->usergroups) == 0) {
                    $notification = new \core\output\notification(get_string('noteam', 'assign'), 'error');
                    $notification->set_show_closebutton(false);
                    $warningmsg = $this->output->notification(get_string('noteam_desc', 'assign'), 'error');
                } else if (count($status->usergroups) > 1) {
                    $notification = new \core\output\notification(get_string('multipleteams', 'assign'), 'error');
                    $notification->set_show_closebutton(false);
                    $warningmsg = $this->output->notification(get_string('multipleteams_desc', 'assign'), 'error');
                }
                $cell2content = $this->output->render($notification);
            } else {
                $cell2content = get_string('defaultteam', 'assign');
            }

            $this->add_table_row_tuple($t, $cell1content, $cell2content);
        }

        // If multiple attempts are allowed.
        if ($status->maxattempts > 1 || $status->maxattempts == ASSIGN_UNLIMITED_ATTEMPTS) {
            $currentattempt = 1;
            if (!$status->teamsubmissionenabled) {
                if ($status->submission) {
                    $currentattempt = $status->submission->attemptnumber + 1;
                }
            } else {
                if ($status->teamsubmission) {
                    $currentattempt = $status->teamsubmission->attemptnumber + 1;
                }
            }

            $cell1content = get_string('attemptnumber', 'assign');
            $maxattempts = $status->maxattempts;
            if ($maxattempts == ASSIGN_UNLIMITED_ATTEMPTS) {
                $cell2content = get_string('currentattempt', 'assign', $currentattempt);
            } else {
                $cell2content = get_string('currentattemptof', 'assign',
                    array('attemptnumber' => $currentattempt, 'maxattempts' => $maxattempts));
            }

            $this->add_table_row_tuple($t, $cell1content, $cell2content);
        }

        $cell1content = get_string('submissionstatus', 'assign');
        $cell2attributes = [];
        if (!$status->teamsubmissionenabled) {
            if ($status->submission && $status->submission->status != ASSIGN_SUBMISSION_STATUS_NEW) {
                $cell2content = get_string('submissionstatus_' . $status->submission->status, 'assign');
                $cell2attributes = array('class' => 'submissionstatus' . $status->submission->status);
            } else {
                if (!$status->submissionsenabled) {
                    $cell2content = get_string('noonlinesubmissions', 'assign');
                } else {
                    $cell2content = get_string('nosubmissionyet', 'assign');
                }
            }
        } else {
            $group = $status->submissiongroup;
            if (!$group && $status->preventsubmissionnotingroup) {
                $cell2content = get_string('nosubmission', 'assign');
            } else if ($status->teamsubmission && $status->teamsubmission->status != ASSIGN_SUBMISSION_STATUS_NEW) {
                $teamstatus = $status->teamsubmission->status;
                $cell2content = get_string('submissionstatus_' . $teamstatus, 'assign');

                $members = $status->submissiongroupmemberswhoneedtosubmit;
                $userslist = array();
                foreach ($members as $member) {
                    $urlparams = array('id' => $member->id, 'course'=>$status->courseid);
                    $url = new \moodle_url('/user/view.php', $urlparams);
                    if ($status->view == assign_submission_status::GRADER_VIEW && $status->blindmarking) {
                        $userslist[] = $member->alias;
                    } else {
                        $fullname = fullname($member, $status->canviewfullnames);
                        $userslist[] = $this->output->action_link($url, $fullname);
                    }
                }
                if (count($userslist) > 0) {
                    $userstr = join(', ', $userslist);
                    $formatteduserstr = get_string('userswhoneedtosubmit', 'assign', $userstr);
                    $cell2content .= $this->output->container($formatteduserstr);
                }

                $cell2attributes = array('class' => 'submissionstatus' . $status->teamsubmission->status);
            } else {
                if (!$status->submissionsenabled) {
                    $cell2content = get_string('noonlinesubmissions', 'assign');
                } else {
                    $cell2content = get_string('nosubmission', 'assign');
                }
            }
        }

        $this->add_table_row_tuple($t, $cell1content, $cell2content, [], $cell2attributes);

        // Is locked?
        if ($status->locked) {
            $cell1content = '';
            $cell2content = get_string('submissionslocked', 'assign');
            $cell2attributes = array('class' => 'submissionlocked');
            $this->add_table_row_tuple($t, $cell1content, $cell2content, [], $cell2attributes);
        }

        // Grading status.
        $cell1content = get_string('gradingstatus', 'assign');
        if ($status->gradingstatus == ASSIGN_GRADING_STATUS_GRADED ||
            $status->gradingstatus == ASSIGN_GRADING_STATUS_NOT_GRADED) {
            $cell2content = get_string($status->gradingstatus, 'assign');
        } else {
            $gradingstatus = 'markingworkflowstate' . $status->gradingstatus;
            $cell2content = get_string($gradingstatus, 'assign');
        }
        if ($status->gradingstatus == ASSIGN_GRADING_STATUS_GRADED ||
            $status->gradingstatus == ASSIGN_MARKING_WORKFLOW_STATE_RELEASED) {
            $cell2attributes = array('class' => 'submissiongraded');
        } else {
            $cell2attributes = array('class' => 'submissionnotgraded');
        }
        $this->add_table_row_tuple($t, $cell1content, $cell2content, [], $cell2attributes);

        $submission = $status->teamsubmission ? $status->teamsubmission : $status->submission;
        $duedate = $status->duedate;
        if ($duedate > 0) {
            if ($status->view == assign_submission_status::GRADER_VIEW) {
                if ($status->cutoffdate) {
                    // Cut off date.
                    $cell1content = get_string('cutoffdate', 'assign');
                    $cell2content = userdate($status->cutoffdate);
                    $this->add_table_row_tuple($t, $cell1content, $cell2content);
                }
            }

            if ($status->extensionduedate) {
                // Extension date.
                $cell1content = get_string('extensionduedate', 'assign');
                $cell2content = userdate($status->extensionduedate);
                $this->add_table_row_tuple($t, $cell1content, $cell2content);
                $duedate = $status->extensionduedate;
            }
        }

        // Time remaining.
        // Only add the row if there is a due date, or a countdown.
        if ($status->duedate > 0 || !empty($submission->timestarted)) {
            $cell1content = get_string('timeremaining', 'assign');
            [$cell2content, $cell2attributes] = $this->get_time_remaining($status);
            $this->add_table_row_tuple($t, $cell1content, $cell2content, [], ['class' => $cell2attributes]);
        }

        // Add time limit info if there is one.
        $timelimitenabled = get_config('assign', 'enabletimelimit') && $status->timelimit > 0;
        if ($timelimitenabled && $status->timelimit > 0) {
            $cell1content = get_string('timelimit', 'assign');
            $cell2content = format_time($status->timelimit);
            $this->add_table_row_tuple($t, $cell1content, $cell2content, [], []);
        }

        // Show graders whether this submission is editable by students.
        if ($status->view == assign_submission_status::GRADER_VIEW) {
            $cell1content = get_string('editingstatus', 'assign');
            if ($status->canedit) {
                $cell2content = get_string('submissioneditable', 'assign');
                $cell2attributes = array('class' => 'submissioneditable');
            } else {
                $cell2content = get_string('submissionnoteditable', 'assign');
                $cell2attributes = array('class' => 'submissionnoteditable');
            }
            $this->add_table_row_tuple($t, $cell1content, $cell2content, [], $cell2attributes);
        }

        // Last modified.
        if ($submission) {
            $cell1content = get_string('timemodified', 'assign');

            if ($submission->status != ASSIGN_SUBMISSION_STATUS_NEW) {
                $cell2content = userdate($submission->timemodified);
            } else {
                $cell2content = "-";
            }

            $this->add_table_row_tuple($t, $cell1content, $cell2content);

            if (!$status->teamsubmission || $status->submissiongroup != false || !$status->preventsubmissionnotingroup) {
                foreach ($status->submissionplugins as $plugin) {
                    $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
                    if ($plugin->is_enabled() &&
                        $plugin->is_visible() &&
                        $plugin->has_user_summary() &&
                        $pluginshowsummary
                    ) {

                        $cell1content = $plugin->get_name();
                        $displaymode = \assign_submission_plugin_submission::SUMMARY;
                        $pluginsubmission = new \assign_submission_plugin_submission($plugin,
                            $submission,
                            $displaymode,
                            $status->coursemoduleid,
                            $status->returnaction,
                            $status->returnparams);
                        $cell2content = $this->render($pluginsubmission);
                        $this->add_table_row_tuple($t, $cell1content, $cell2content);
                    }
                }
            }
        }

        $o .= $warningmsg;
        $o .= \html_writer::table($t);
        $o .= $this->output->box_end();

        // Grading criteria preview.
        if (!empty($status->gradingcontrollerpreview)) {
            $o .= $this->output->heading(get_string('gradingmethodpreview', 'assign'), 4);
            $o .= $status->gradingcontrollerpreview;
        }

        $o .= $this->output->container_end();
        return $o;
    }

    /**
     * Output the attempt history chooser for this assignment
     *
     * @param \assign_attempt_history_chooser $history
     * @return string
     */
    public function render_assign_attempt_history_chooser(\assign_attempt_history_chooser $history) {
        $o = '';

        $context = $history->export_for_template($this);
        $o .= $this->render_from_template('mod_assign/attempt_history_chooser', $context);

        return $o;
    }

    /**
     * Output the attempt history for this assignment
     *
     * @param \assign_attempt_history $history
     * @return string
     */
    public function render_assign_attempt_history(\assign_attempt_history $history) {
        $o = '';

        // Don't show the last one because it is the current submission.
        array_pop($history->submissions);

        // Show newest to oldest.
        $history->submissions = array_reverse($history->submissions);

        if (empty($history->submissions)) {
            return '';
        }

        $containerid = 'attempthistory' . uniqid();
        $o .= $this->output->heading(get_string('attempthistory', 'assign'), 3);
        $o .= $this->box_start('attempthistory', $containerid);

        foreach ($history->submissions as $i => $submission) {
            $grade = null;
            foreach ($history->grades as $onegrade) {
                if ($onegrade->attemptnumber == $submission->attemptnumber) {
                    if ($onegrade->grade != ASSIGN_GRADE_NOT_SET) {
                        $grade = $onegrade;
                    }
                    break;
                }
            }

            if ($submission) {
                $submissionsummary = userdate($submission->timemodified);
            } else {
                $submissionsummary = get_string('nosubmission', 'assign');
            }

            $attemptsummaryparams = array('attemptnumber'=>$submission->attemptnumber+1,
                                          'submissionsummary'=>$submissionsummary);
            $o .= $this->heading(get_string('attemptheading', 'assign', $attemptsummaryparams), 4);

            $t = new \html_table();
            $t->caption = get_string('attemptheading', 'assign', $attemptsummaryparams);
            $t->captionhide = true; // Hidden because it matches the title above.

            if ($submission) {
                $cell1content = get_string('submissionstatus', 'assign');
                $cell2content = get_string('submissionstatus_' . $submission->status, 'assign');
                $this->add_table_row_tuple($t, $cell1content, $cell2content);

                foreach ($history->submissionplugins as $plugin) {
                    $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
                    if ($plugin->is_enabled() &&
                            $plugin->is_visible() &&
                            $plugin->has_user_summary() &&
                            $pluginshowsummary) {

                        $cell1content = $plugin->get_name();
                        $pluginsubmission = new \assign_submission_plugin_submission($plugin,
                                                                                    $submission,
                                                                                    \assign_submission_plugin_submission::SUMMARY,
                                                                                    $history->coursemoduleid,
                                                                                    $history->returnaction,
                                                                                    $history->returnparams);
                        $cell2content = $this->render($pluginsubmission);
                        $this->add_table_row_tuple($t, $cell1content, $cell2content);
                    }
                }
            }

            if ($grade) {
                // Heading 'feedback'.
                $title = get_string('feedback', 'assign', $i);
                $title .= $this->output->spacer(array('width'=>10));
                if ($history->cangrade) {
                    // Edit previous feedback.
                    $returnparams = http_build_query($history->returnparams);
                    $urlparams = array('id' => $history->coursemoduleid,
                                   'rownum'=>$history->rownum,
                                   'useridlistid'=>$history->useridlistid,
                                   'attemptnumber'=>$grade->attemptnumber,
                                   'action'=>'grade',
                                   'returnaction'=>$history->returnaction,
                                   'returnparams'=>$returnparams);
                    $url = new \moodle_url('/mod/assign/view.php', $urlparams);
                    $icon = new \pix_icon('gradefeedback',
                                            get_string('editattemptfeedback', 'assign', $grade->attemptnumber+1),
                                            'mod_assign');
                    $title .= $this->output->action_icon($url, $icon);
                }
                $cell = new \html_table_cell($title);
                $cell->attributes['class'] = 'feedbacktitle';
                $cell->colspan = 2;
                $t->data[] = new \html_table_row(array($cell));

                // Grade.
                $cell1content = get_string('gradenoun');
                $cell2content = $grade->gradefordisplay;
                $this->add_table_row_tuple($t, $cell1content, $cell2content);

                // Graded on.
                $cell1content = get_string('gradedon', 'assign');
                $cell2content = userdate($grade->timemodified);
                $this->add_table_row_tuple($t, $cell1content, $cell2content);

                // Graded by set to a real user. Not set can be empty or -1.
                if (!empty($grade->grader) && is_object($grade->grader)) {
                    $cell1content = get_string('gradedby', 'assign');
                    $cell2content = $this->output->user_picture($grade->grader) .
                                    $this->output->spacer(array('width' => 30)) . fullname($grade->grader);
                    $this->add_table_row_tuple($t, $cell1content, $cell2content);
                }

                // Feedback from plugins.
                foreach ($history->feedbackplugins as $plugin) {
                    if ($plugin->is_enabled() &&
                        $plugin->is_visible() &&
                        $plugin->has_user_summary() &&
                        !$plugin->is_empty($grade)) {

                        $pluginfeedback = new \assign_feedback_plugin_feedback(
                            $plugin, $grade, \assign_feedback_plugin_feedback::SUMMARY, $history->coursemoduleid,
                            $history->returnaction, $history->returnparams
                        );

                        $cell1content = $plugin->get_name();
                        $cell2content = $this->render($pluginfeedback);
                        $this->add_table_row_tuple($t, $cell1content, $cell2content);
                    }

                }

            }

            $o .= \html_writer::table($t);
        }
        $o .= $this->box_end();

        $this->page->requires->yui_module('moodle-mod_assign-history', 'Y.one("#' . $containerid . '").history');

        return $o;
    }

    /**
     * Render a submission plugin submission
     *
     * @param \assign_submission_plugin_submission $submissionplugin
     * @return string
     */
    public function render_assign_submission_plugin_submission(\assign_submission_plugin_submission $submissionplugin) {
        $o = '';

        if ($submissionplugin->view == \assign_submission_plugin_submission::SUMMARY) {
            $showviewlink = false;
            $summary = $submissionplugin->plugin->view_summary($submissionplugin->submission,
                                                               $showviewlink);

            $classsuffix = $submissionplugin->plugin->get_subtype() .
                           '_' .
                           $submissionplugin->plugin->get_type() .
                           '_' .
                           $submissionplugin->submission->id;

            $o .= $this->output->box_start('boxaligncenter plugincontentsummary summary_' . $classsuffix);

            $link = '';
            if ($showviewlink) {
                $previewstr = get_string('viewsubmission', 'assign');
                $icon = $this->output->pix_icon('t/viewdetails', $previewstr);

                $expandstr = get_string('viewfull', 'assign');
                $expandicon = $this->output->pix_icon('t/switch_plus', $expandstr);
                $options = array(
                    'class' => 'expandsummaryicon expand_' . $classsuffix,
                    'aria-label' => $expandstr,
                    'role' => 'button',
                    'aria-expanded' => 'false'
                );
                $o .= \html_writer::link('', $expandicon, $options);

                $jsparams = array($submissionplugin->plugin->get_subtype(),
                                  $submissionplugin->plugin->get_type(),
                                  $submissionplugin->submission->id);

                $this->page->requires->js_init_call('M.mod_assign.init_plugin_summary', $jsparams);

                $action = 'viewplugin' . $submissionplugin->plugin->get_subtype();
                $returnparams = http_build_query($submissionplugin->returnparams);
                $link .= '<noscript>';
                $urlparams = array('id' => $submissionplugin->coursemoduleid,
                                   'sid'=>$submissionplugin->submission->id,
                                   'plugin'=>$submissionplugin->plugin->get_type(),
                                   'action'=>$action,
                                   'returnaction'=>$submissionplugin->returnaction,
                                   'returnparams'=>$returnparams);
                $url = new \moodle_url('/mod/assign/view.php', $urlparams);
                $link .= $this->output->action_link($url, $icon);
                $link .= '</noscript>';

                $link .= $this->output->spacer(array('width'=>15));
            }

            $o .= $link . $summary;
            $o .= $this->output->box_end();
            if ($showviewlink) {
                $o .= $this->output->box_start('boxaligncenter hidefull full_' . $classsuffix);
                $collapsestr = get_string('viewsummary', 'assign');
                $options = array(
                    'class' => 'expandsummaryicon contract_' . $classsuffix,
                    'aria-label' => $collapsestr,
                    'role' => 'button',
                    'aria-expanded' => 'true'
                );
                $collapseicon = $this->output->pix_icon('t/switch_minus', $collapsestr);
                $o .= \html_writer::link('', $collapseicon, $options);

                $o .= $submissionplugin->plugin->view($submissionplugin->submission);
                $o .= $this->output->box_end();
            }
        } else if ($submissionplugin->view == \assign_submission_plugin_submission::FULL) {
            $o .= $this->output->box_start('boxaligncenter submissionfull');
            $o .= $submissionplugin->plugin->view($submissionplugin->submission);
            $o .= $this->output->box_end();
        }

        return $o;
    }

    /**
     * Render the grading table.
     *
     * @param \assign_grading_table $table
     * @return string
     */
    public function render_assign_grading_table(\assign_grading_table $table) {
        $o = '';

        // Construct the 'Remove all Filter' button and the table within a single wrapper so AJAX and Form layouts don't break
        $cmid = $this->page->cm->id;
        $reseturl = new \moodle_url('/mod/assign/view.php', [
            'id' => $cmid,
            'action' => 'grading',
            'group' => 0,
            'status' => '',
            'workflowfilter' => '',
            'markingallocationfilter' => '',
            'suspendedparticipantsfilter' => 0,
            'tifirst' => '',
            'tilast' => '',
            'search' => '',
            'treset' => 1,
            'reset' => 1
        ]);
        
        $o .= '<div class="gradingtable-wrapper">';

        $o .= '<div id="custom-reset-filters-container" style="display: flex; align-items: center; margin-left: auto;">
            <a href="' . $reseturl->out(false) . '" class="btn btn-sm btn-outline-danger" style="font-size: 13px; font-weight: 500; padding: 6px 16px; border-radius: 6px; border: 0.5px solid #dc3545; height: 38px; display: inline-flex; justify-content: center; align-items: center;">
                Remove all Filters
            </a>
        </div>';

        $o .= $this->output->box_start('boxaligncenter gradingtable position-relative');

        $this->page->requires->js_init_call('M.mod_assign.init_grading_table', array());
        
        // Inject CSS to format and align the "View all Submissions" page to match the requested template
        $o .= '<style>
            /* Full 100% width - aggressively override Moodle constraints */
            form,
            form.mform,
            form.mform fieldset,
            form.mform .fcontainer {
                max-width: 100% !important;
                width: 100% !important;
                flex: 1 1 auto !important;
                border: none !important;
            }
            body.path-mod-assign #page-content,
            body.path-mod-assign .secondary-navigation,
            body.path-mod-assign .primary-align,
            body.path-mod-assign [role="main"],
            body.path-mod-assign .container {
                max-width: 100% !important;
                width: 100% !important;
                padding-left: 0px !important;
                padding-right: 0px !important;
            }
            body.path-mod-assign #page-header {
                margin-bottom: 4px !important;
                padding-bottom: 4px !important;
            }
            body.path-mod-assign #page-content {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }
            #region-main {
                background: var(--color-background-primary, #ffffff) !important;
                padding: 0px 0px !important;
                box-sizing: border-box !important;
                overflow: visible !important;
            }
            #region-main > .card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            body.path-mod-assign #region-main-box {
                flex: 0 0 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
            }
            body.path-mod-assign .card-body {
                padding: 0 !important;
            }

            /* Toolbar alignments */
            .tertiary-navigation,
            .tertiary-navigation > div.d-flex {
                display: flex !important;
                align-items: center !important;
                gap: 8px 8px !important;
                flex-wrap: wrap !important;
                margin-bottom: 4px !important;
                padding-bottom: 4px !important;
                width: 100% !important;
            }
            .tertiary-navigation .navitem {
                margin: 0 !important;
                border: none !important;
            }
            
            /* Remove horizontal lines */
            hr {
                display: none !important;
            }
            .tertiary-navigation .navitem::before,
            .tertiary-navigation .navitem::after,
            .tertiary-navigation .navitem-divider,
            .tertiary-navigation .divider {
                display: none !important;
                border: none !important;
            }
            
            .tertiary-navigation .navitem:last-child,
            .tertiary-navigation .d-flex.ml-auto,
            .tertiary-navigation .ms-auto {
                margin-left: auto !important;
                margin-right: 0 !important;
            }
            
            .tertiary-navigation input[type="text"],
            .tertiary-navigation select,
            .tertiary-navigation .btn,
            .tertiary-navigation .dropdown-toggle {
                padding: 6px 16px !important; /* Increased horizontal padding */
                min-height: 38px !important; /* Standardize vertical height */
                font-size: 13px !important;
                border-radius: var(--border-radius-md, 6px) !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            
            /* Add border to clickable dropdown buttons and secondary buttons so they do not look like plain text */
            .tertiary-navigation select,
            .tertiary-navigation .btn:not(.btn-primary):not(.btn-success):not(.btn-danger),
            .tertiary-navigation .dropdown-toggle {
                border: 0.5px solid var(--color-border-tertiary, #dee2e6) !important;
                background-color: #ffffff !important;
                color: #374151 !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
            }
            
            /* Force dropdowns to open downwards and not flip up */
            .tertiary-navigation .dropdown,
            .tertiary-navigation .btn-group {
                position: relative !important;
            }
            .tertiary-navigation .dropdown-menu {
                top: 100% !important;
                bottom: auto !important;
                transform: none !important;
                margin-top: 4px !important;
            }
            .tertiary-navigation .dropdown-menu.dropdown-menu-right,
            .tertiary-navigation .dropdown-menu.dropdown-menu-end {
                left: auto !important;
                right: 0 !important;
            }
            
            /* Seamless Input Groups (Floating Clear Cross inside Search Bar) */
            .tertiary-navigation .input-group {
                display: flex !important;
                flex-wrap: nowrap !important;
                align-items: center !important;
                position: relative !important;
            }
            .tertiary-navigation .input-group input[type="text"] {
                border-radius: var(--border-radius-md, 6px) !important; /* Full border */
                border-right: 0.5px solid var(--color-border-tertiary, #dee2e6) !important;
                padding-right: 32px !important; /* Make room for the absolute X */
                width: 100% !important;
            }
            .tertiary-navigation .input-group .btn,
            .tertiary-navigation .input-group-append .btn {
                position: absolute !important;
                right: 4px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                border: none !important;
                background: transparent !important;
                box-shadow: none !important;
                padding: 4px !important;
                min-height: 0 !important;
                height: 24px !important;
                width: 24px !important;
                color: #6b7280 !important;
                z-index: 5 !important;
            }
            /* Respect Moodle native hidden state for empty search bars */
            .tertiary-navigation .btn.d-none,
            .tertiary-navigation .input-group .btn.d-none,
            .tertiary-navigation .input-group-append .btn.d-none {
                display: none !important;
            }

            /* Grading options row alignment */
            .gradingoptionsform {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                font-size: 10px !important;
                margin-bottom: 3px !important;
                flex-wrap: wrap !important;
                gap: 3px !important;
            }

            /* Outer wrapper — clips the table to the page content width */
            .gradingtable-wrapper {
                width: 100% !important;
                max-width: 100% !important;
                overflow: hidden !important;   /* clips overflow horizontally */
                box-sizing: border-box !important;
            }

            /* Table scroll container */
            .gradingtable {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: auto !important;
                overflow-y: visible !important;
                -webkit-overflow-scrolling: touch !important;
                flex-shrink: 0 !important;
                border: 0.5px solid var(--color-border-tertiary, #dee2e6) !important;
                border-radius: var(--border-radius-md, 6px) !important;
                padding: 0 !important;
                margin: 0 !important;
                box-sizing: border-box !important;
            }

            /* Hide table caption and remove unwanted gaps safely */
            .gradingtable table.generaltable caption {
                display: none !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                visibility: hidden !important;
            }
            .gradingtable > .no-overflow {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            /* Globally hide Moodle YUI cloned floating headers to prevent detached headers */
            .floater,
            .yui3-datatable-header {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                opacity: 0 !important;
            }
            
            /* The table itself — use full width, allow natural column sizing */
            .gradingtable table.generaltable {
                display: table !important;
                table-layout: auto !important;
                width: max-content !important;
                min-width: 100% !important;
                border-collapse: collapse !important;
                border-spacing: 0 !important;
                font-size: 11px !important;
                color: var(--color-text-primary, #111827) !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Header alignments and fixes */
            .gradingtable table.generaltable thead {
                visibility: visible !important;
                opacity: 1 !important;
                display: table-header-group !important;
            }
            .gradingtable table.generaltable thead,
            .gradingtable table.generaltable thead tr {
                position: static !important;
                height: auto !important;
                line-height: normal !important;
                transform: none !important;
                margin: 0 !important;
            }
            .gradingtable table.generaltable thead tr {
                background: var(--color-background-secondary, #f8f9fa) !important;
            }
            
            /* Hide only the YUI dummy row (which has no text) injected for column sizing */
            .gradingtable table.generaltable thead tr:not(:last-child),
            .gradingtable table.generaltable thead tr.yui3-datatable-first-row,
            .gradingtable table.generaltable thead tr.emptyrow {
                display: none !important;
                height: 0 !important;
            }
            
            .gradingtable table.generaltable thead th {
                background-color: var(--color-background-secondary, #f8f9fa) !important;
                border-bottom: 0.5px solid var(--color-border-tertiary, #dee2e6) !important;
                border-top: none !important;
                border-right: 0.5px solid var(--color-border-tertiary, #dee2e6) !important;
                padding: 5px 4px !important;
                font-weight: 500 !important;
                font-size: 13px !important;
                white-space: nowrap !important;
                text-align: left !important;
                vertical-align: bottom !important;
            }
            /* Compact column widths to reduce horizontal scroll */
            .gradingtable table.generaltable thead th.c0,
            .gradingtable table.generaltable tbody td.c0 {
                width: 30px !important;
                max-width: 30px !important;
                text-align: center !important;
            }
            .gradingtable table.generaltable thead th.status,
            .gradingtable table.generaltable tbody td.status {
                width: 90px !important;
                max-width: 100px !important;
            }
            /* Due column — as narrow as the content allows */
            .gradingtable table.generaltable thead th.duedate,
            .gradingtable table.generaltable tbody td.duedate {
                width: 1% !important;
                white-space: nowrap !important;
                padding-left: 6px !important;
                padding-right: 6px !important;
            }

            /* Feedback column — compact */
            .gradingtable table.generaltable thead th.assignfeedback_comments,
            .gradingtable table.generaltable tbody td.assignfeedback_comments {
                width: 120px !important;
                max-width: 140px !important;
                text-align: center !important;
            }
            .gradingtable table.generaltable thead th.finalgrade,
            .gradingtable table.generaltable tbody td.finalgrade {
                width: 70px !important;
                max-width: 80px !important;
            }
            .gradingtable table.generaltable thead th:last-child {
                border-right: none !important;
            }

            /* Body rows - Force uniform #f8f9fa color by neutralizing Bootstrap 5 td striping */
            .gradingtable table.generaltable tbody tr,
            .gradingtable table.generaltable tbody tr:nth-of-type(odd),
            .gradingtable table.generaltable tbody tr:nth-child(even),
            .gradingtable table.generaltable tbody tr.unselectedrow {
                border-bottom: 0.5px solid var(--color-border-tertiary, #dee2e6) !important;
                background-color: #f8f9fa !important;
            }
            
            .gradingtable table.generaltable tbody tr > td,
            .gradingtable table.generaltable tbody tr > th {
                background-color: #f8f9fa !important;
                box-shadow: none !important; /* Neutralize Bootstrap 5 zebra shadow */
                --bs-table-accent-bg: transparent !important;
            }
            
            .gradingtable table.generaltable tbody tr:last-child {
                border-bottom: none !important;
            }

            /* Custom CSS to tighten the layout and make table headers blue */
            .tertiary-navigation {
                margin-bottom: 0.5rem !important;
                padding-bottom: 0 !important;
                border-bottom: none !important;
            }
            .name-filter-container label,
            .name-filter-container small {
                display: none !important;
            }
            .name-filter-container .dropdown {
                margin-top: 0 !important;
            }
            .generaltable th a {
                color: #0f6cbf !important; /* Moodle primary blue */
            }

            /* Remove bullets and align file submissions neatly */
            .gradingtable table.generaltable tbody td .plugincontents ul:has(.icon) {
                list-style-type: none !important;
                padding-left: 0 !important;
                margin-left: 0 !important;
            }
            .gradingtable table.generaltable tbody td .plugincontents ul:has(.icon) li {
                list-style-type: none !important;
                margin-bottom: 0.75rem;
                padding-left: 0 !important;
                margin-left: 0 !important;
            }
            .gradingtable table.generaltable tbody td .plugincontents .fileuploadsubmission {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 0.25rem; /* Neatly reduces the gap */
            }
            .gradingtable table.generaltable tbody td .plugincontents .fileuploadsubmission .icon {
                margin: 0 !important;
                padding: 0 !important;
                width: 16px;
                height: 16px;
            }
            .gradingtable table.generaltable tbody td .plugincontents .fileuploadsubmissiontime {
                margin-left: 1.25rem; /* Aligns the date perfectly under the file name */
                font-size: 0.85em;
                color: #6c757d;
            }
            
            /* Ensure plugin content backgrounds are transparent to inherit row hover color */
            .gradingtable table.generaltable tbody td .plugincontents,
            .gradingtable table.generaltable tbody td .plugincontentsummary {
                background-color: transparent !important;
            }
            
            .gradingtable table.generaltable tbody tr:hover > td,
            .gradingtable table.generaltable tbody tr:hover > th {
                background-color: #e9ecef !important; /* Slightly darker shade for hover effect */
            }
            .gradingtable td {
                padding: 5px 4px !important;
                vertical-align: middle !important;
                line-height: 1.3 !important;
                border-top: none !important;
                border-right: 0.5px solid var(--color-border-tertiary, #dee2e6) !important;
                font-size: 11px !important;
            }
            .gradingtable td:last-child {
                border-right: none !important;
            }
            
            /* Centering for right-side columns */
            .gradingtable th.grade,
            .gradingtable td.grade,
            .gradingtable td.c5,
            .gradingtable td.cgrade,
            .gradingtable th.timemarked,
            .gradingtable td.timemarked,
            .gradingtable th.assignfeedback_comments,
            .gradingtable td.assignfeedback_comments,
            .gradingtable th.cutoffdate,
            .gradingtable td.cutoffdate,
            .gradingtable th.timesubmitted {
                text-align: center !important;
                vertical-align: middle !important;
                min-width: 80px !important;
            }

            /* Grade column 3-dots action menu absolute positioning */
            .gradingtable td.grade,
            .gradingtable td.c5,
            .gradingtable td.cgrade {
                position: relative !important;
                white-space: nowrap !important; /* Prevents 68.00 % from splitting into two lines */
                padding-right: 28px !important; /* Leave room for the action menu */
            }
            
            /* Quick Grading Inputs and Selects Styling */
            .gradingtable td.grade .quickgrade,
            .gradingtable td.c5 .quickgrade,
            .gradingtable td.cgrade .quickgrade {
                max-width: 60px !important;
                text-align: center !important;
                border: 0.5px solid var(--color-border-tertiary, #dee2e6) !important;
                border-radius: var(--border-radius-md, 6px) !important;
                padding: 4px 6px !important;
                margin: 0 4px 0 0 !important;
                display: inline-block !important;
                font-size: 11px !important;
                background-color: #ffffff !important;
                vertical-align: middle !important;
            }
            .gradingtable td.grade {
                white-space: nowrap !important;
                vertical-align: middle !important;
            }
            
            .gradingtable td.grade .action-menu,
            .gradingtable td.c5 .action-menu,
            .gradingtable td.cgrade .action-menu {
                position: absolute !important;
                top: 4px !important;
                right: 4px !important;
                margin: 0 !important;
                display: inline-block !important;
            }

            /* File submissions column alignment and strict containment */
            .gradingtable [id^="assign_files_tree"] {
                width: 100% !important;
                max-width: 200px !important;
                min-width: 140px !important;
                white-space: normal !important;
                word-wrap: break-word !important;
                overflow: hidden !important; /* Brutally prevent any bleeding into the next column */
            }
            
            /* Remove YUI tree indentation and spacer cells to eliminate left space */
            .gradingtable .ygtvitem td:not(.ygtvcontent),
            .gradingtable .ygtvblankdepthcell,
            .gradingtable .ygtvdepthcell,
            .gradingtable .ygtvspacer {
                display: none !important;
                width: 0 !important;
            }
            
            /* Overcome YUI TreeView rigid layouts and white backgrounds that cause overlap */
            .gradingtable .ygtvitem table {
                table-layout: fixed !important;
                width: 100% !important;
                background-color: transparent !important;
            }
            
            .gradingtable .ygtvitem,
            .gradingtable .ygtvitem td,
            .gradingtable .ygtvcontent,
            .gradingtable .ygtvlabel,
            .gradingtable .ygtvrow,
            .gradingtable [class*="ygtv-highlight"],
            .gradingtable .assignsubmission_file,
            .gradingtable .assignsubmission_file .box,
            .gradingtable .assignsubmission_file div,
            .gradingtable .fileuploadsubmission,
            .gradingtable .fileuploadsubmission div {
                background-color: transparent !important;
                white-space: normal !important;
                word-wrap: break-word !important;
                max-width: 100% !important;
            }

            .gradingtable .fileuploadsubmission {
                display: flex !important;
                align-items: flex-start !important;
                gap: 6px !important;
                overflow-wrap: break-word !important; /* Wrap whole words, break only if necessary */
                word-break: normal !important;
                text-align: left !important;
                line-height: 1.3 !important;
            }
            
            .gradingtable .fileuploadsubmissiontime {
                display: block !important;
                float: none !important;
                text-align: left !important;
                font-size: 10.5px !important;
                line-height: 1.2 !important;
                color: var(--color-text-secondary, #6b7280) !important;
                margin-top: 4px !important;
                padding-left: 22px !important; /* Aligns with text, skipping the icon */
            }

            /* Un-fix the sticky footer and place it neatly at the bottom of the table */
            .stickyfooter,
            #sticky-footer {
                position: static !important;
                box-shadow: none !important;
                background: transparent !important;
                padding: 0 !important;
                margin-top: 20px !important;
                border-top: none !important;
                z-index: 1 !important;
                width: 100% !important;
            }

            /* Fix Pagination and Show dropdown alignment */
            .paging,
            .paging-bar,
            div:has(> .pagination) {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 16px !important;
                margin: 20px 0 !important;
                flex-wrap: wrap !important;
            }
            
            .paging form,
            .paging-bar form,
            form:has(select[name="perpage"]) {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                margin: 0 !important;
            }
            
            .paging form label,
            .paging-bar form label,
            form:has(select[name="perpage"]) label {
                margin: 0 !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                line-height: 1 !important;
            }
            
            .paging form select,
            .paging-bar form select,
            form:has(select[name="perpage"]) select {
                margin: 0 !important;
                padding: 4px 30px 4px 10px !important;
                height: 32px !important;
                border-radius: 4px !important;
                border: 0.5px solid var(--color-border-tertiary, #dee2e6) !important;
            }
            
            .pagination {
                display: flex !important;
                align-items: center !important;
                margin: 0 !important;
                gap: 4px !important;
                flex-wrap: wrap !important;
            }
            
            .pagination .page-item .page-link {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-width: 32px !important;
                height: 32px !important;
                padding: 0 8px !important;
                font-size: 13px !important;
                border-radius: 4px !important;
                border: 0.5px solid var(--color-border-tertiary, #dee2e6) !important;
                color: var(--color-text-primary, #374151) !important;
            }
            
            .pagination .page-item.active .page-link {
                background-color: #4f46e5 !important;
                border-color: #4f46e5 !important;
                color: #ffffff !important;
                font-weight: 600 !important;
            }
            
            /* Name Filter Dropdown Alphabet Adjustments */
            .initialsdropdownform .initialbar {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                margin-bottom: 16px !important;
                position: relative !important;
                width: 100% !important;
            }
            .initialsdropdownform .initialbarlabel {
                width: 100% !important;
                margin-bottom: 6px !important;
                font-weight: 600 !important;
                font-size: 12px !important;
                line-height: 26px !important; /* perfectly aligns with the height of the All button */
                color: #4b5563 !important;
            }
            .initialsdropdownform .initialbargroups {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 4px !important;
                width: 100% !important;
            }
            
            /* Move "All" button to the extreme right side of the label */
            .initialsdropdownform .initialbargroups > ul:first-child {
                position: absolute !important;
                top: 0 !important;
                right: 0 !important;
                margin: 0 !important;
            }
            
            .initialsdropdownform .pagination {
                gap: 4px !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .initialsdropdownform .pagination .page-item {
                margin: 0 !important;
            }
            .initialsdropdownform .pagination .page-item .page-link,
            .initialsdropdownform .pagination .page-item input[type="button"] {
                min-width: 26px !important;
                height: 26px !important;
                padding: 0 !important;
                font-size: 11.5px !important;
                font-weight: 500 !important;
                margin: 0 !important; /* Overrides Moodle native .me-1 */
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 4px !important;
            }
            
            /* Premium Feedback Modal Styling */
            .modal-dialog {
                max-width: 850px !important; /* Wider modal for readability */
            }
            .modal-content {
                border: none !important;
                border-radius: 12px !important;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
            }
            .modal-header {
                border-bottom: 1px solid #f3f4f6 !important;
                padding: 1.5rem !important;
                background-color: #ffffff !important;
                border-radius: 12px 12px 0 0 !important;
            }
            .modal-title {
                font-weight: 700 !important;
                color: #111827 !important;
                font-size: 1.25rem !important;
                letter-spacing: -0.025em !important;
            }
            .modal-body {
                padding: 2rem !important;
                color: #374151 !important;
                line-height: 1.7 !important;
                font-size: 0.95rem !important;
                font-family: var(--font-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif) !important;
            }
            
            /* Elegant Headings inside Modal Body */
            .modal-body h1, 
            .modal-body h2, 
            .modal-body h3, 
            .modal-body h4,
            .modal-body h5,
            .modal-body h6,
            .modal-body p > strong:only-child {
                font-size: 1.15rem !important;
                font-weight: 700 !important;
                color: #1f2937 !important;
                margin-top: 2rem !important;
                margin-bottom: 1rem !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                border-left: 4px solid #4f46e5 !important;
                padding-left: 0.875rem !important;
                line-height: 1.3 !important;
                display: block !important;
            }
            
            /* Remove top margin for the very first heading */
            .modal-body > *:first-child,
            .modal-body > *:first-child > strong:only-child {
                margin-top: 0 !important;
            }
            
            .modal-body p {
                margin-bottom: 1.25rem !important;
            }
            
            /* Lists inside Modal */
            .modal-body ul, 
            .modal-body ol {
                margin-bottom: 1.25rem !important;
                padding-left: 1.5rem !important;
            }
            .modal-body li {
                margin-bottom: 0.5rem !important;
            }
            
            /* Code blocks / Inline code */
            .modal-body code {
                background-color: #f3f4f6 !important;
                color: #db2777 !important;
                padding: 0.2rem 0.4rem !important;
                border-radius: 4px !important;
                font-size: 0.875em !important;
            }
            
            /* Submission Details Merged Column */
            .submission-details-container {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                text-align: center !important;
                gap: 4px !important;
                min-width: 200px !important;
            }
            .submission-details-container .plugin-item {
                margin-bottom: 0 !important;
            }
            .submission-details-container .plugincontentsummary {
                padding: 4px !important;
                margin: 0 !important;
            }
            .comments-modal-btn {
                background-color: transparent !important;
                border: 1px solid #4f46e5 !important;
                color: #4f46e5 !important;
                border-radius: 6px !important;
                font-weight: 500 !important;
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
                justify-content: center !important;
                width: fit-content !important;
                padding: 4px 10px !important;
                transition: all 0.2s ease !important;
            }
            .comments-modal-btn:hover {
                background-color: #4f46e5 !important;
                color: white !important;
            }
            .submission-comments-modal .modal-body {
                background-color: #ffffff !important;
                padding: 24px !important;
                font-size: 14px !important;
            }
            /* Hide the native toggle inside the modal since we auto-expand it */
            .custom-comments-body .comment-link {
                display: none !important;
            }
        </style>';
        
        $o .= $this->flexible_table($table, $table->get_rows_per_page(), false);
        
        $o .= '<script>
            (function() {
                // Move "Remove all Filters" button just above the Grading table
                document.addEventListener("DOMContentLoaded", function() {
                    var resetBtn = document.getElementById("custom-reset-filters-container");
                    var tertiaryNavs = document.querySelectorAll(".tertiary-navigation");
                    if (resetBtn && tertiaryNavs.length > 0) {
                        // The Quick Grading row is always the last tertiary-navigation before the table
                        var targetRow = tertiaryNavs[tertiaryNavs.length - 1];
                        var innerFlex = targetRow.querySelector(".d-flex.flex-wrap") || targetRow.querySelector("div.d-flex") || targetRow;
                        innerFlex.appendChild(resetBtn);
                    }

                    // Move "Show", Pagination, and "Notify students + Save" out of the sticky footer and place below the table
                    function relocateFooterControls() {
                        var tableWrapper = document.querySelector(".gradingtable-wrapper");
                        if (!tableWrapper || document.getElementById("grading-below-table-bar")) return;

                        var stickyFooter = document.querySelector(".stickyfooter") || document.querySelector(".sticky-footer") || document.querySelector("[data-sticky-footer]");
                        var perpageEl = stickyFooter ? stickyFooter.querySelector("form:has(select[name=\"perpage\"]), label:has(select[name=\"perpage\"])") : null;
                        var pagingBarEl = stickyFooter ? stickyFooter.querySelector("nav.pagination, .pagination") : null;
                        if (!perpageEl) perpageEl = document.querySelector("form:has(select[name=\"perpage\"]), label:has(select[name=\"perpage\"])");
                        if (!pagingBarEl) pagingBarEl = document.querySelector(".paging-bar nav.pagination, nav:has(.pagination)");
                        var savePanel = document.querySelector("[data-region=\"quick-grading-save\"]");

                        // Need at least perpage or paging bar to proceed
                        if (!perpageEl && !pagingBarEl && !savePanel) return;

                        // Build a clean bar below the table
                        var belowBar = document.createElement("div");
                        belowBar.id = "grading-below-table-bar";
                        belowBar.style.cssText = [
                            "display: flex",
                            "align-items: center",
                            "justify-content: space-between",
                            "gap: 16px",
                            "margin-top: 12px",
                            "padding: 10px 14px",
                            "background: #f8f9fa",
                            "border: 0.5px solid #dee2e6",
                            "border-radius: 6px",
                            "box-sizing: border-box",
                            "width: 100%",
                            "flex-wrap: wrap",
                        ].join(";");

                        // Left side: Show dropdown + pagination
                        var leftGroup = document.createElement("div");
                        leftGroup.style.cssText = "display:flex;align-items:center;gap:16px;flex-wrap:wrap;";
                        if (perpageEl) {
                            // Get the closest col-auto parent if it exists, otherwise take the element itself
                            var perpageContainer = perpageEl.closest(".col-auto") || perpageEl;
                            perpageContainer.style.cssText = "margin:0;padding:0;";
                            leftGroup.appendChild(perpageContainer);
                        }
                        if (pagingBarEl) {
                            var pagingContainer = pagingBarEl.closest(".col") || pagingBarEl.closest("nav") || pagingBarEl;
                            pagingContainer.style.cssText = "margin:0;padding:0;";
                            leftGroup.appendChild(pagingContainer);
                        }
                        belowBar.appendChild(leftGroup);

                        // Right side: Save panel (if quick grading)
                        if (savePanel) {
                            savePanel.style.cssText = "display:flex;align-items:center;gap:12px;margin:0;";
                            belowBar.appendChild(savePanel);
                        }

                        tableWrapper.appendChild(belowBar);

                        // Hide the now-empty sticky footer
                        if (stickyFooter) {
                            stickyFooter.style.display = "none";
                        }
                        // Remove the bottom padding Moodle adds for the sticky footer
                        document.body.style.paddingBottom = "0";
                        var mainContent = document.getElementById("region-main");
                        if (mainContent) mainContent.style.paddingBottom = "0";
                    }

                    // Try immediately, then retry after short delay (Moodle renders sticky footer async)
                    relocateFooterControls();
                    setTimeout(relocateFooterControls, 300);
                    setTimeout(relocateFooterControls, 800);
                });

                if (window.gradingTableAjaxInitialized) return;
                window.gradingTableAjaxInitialized = true;
                
                // Auto-expand native comments when the new Submission Comments Modal opens
                require(["jquery"], function($) {
                    $(document).on("show.bs.modal", ".submission-comments-modal", function() {
                        var $commentLink = $(this).find(".comment-link");
                        if ($commentLink.length && $commentLink.attr("aria-expanded") !== "true") {
                            setTimeout(function() {
                                $commentLink[0].click();
                            }, 50);
                        }
                    });
                });
                
                // Hard refresh filter clearing logic
                try {
                    const navEntries = performance.getEntriesByType("navigation");
                    const isReload = navEntries.length > 0 ? navEntries[0].type === "reload" : performance.navigation.type === 1;
                    if (isReload) {
                        const currentUrl = new URL(window.location.href);
                        if (!currentUrl.searchParams.has("_cleared")) {
                            const stickyFilters = ["tifirst", "tilast", "status", "workflowfilter", "markingallocationfilter"];
                            let needsRefresh = false;
                            
                            stickyFilters.forEach(f => {
                                if (!currentUrl.searchParams.has(f) || currentUrl.searchParams.get(f) !== "") {
                                    currentUrl.searchParams.set(f, "");
                                    needsRefresh = true;
                                }
                            });
                            
                            // Delete non-sticky filters entirely so they do not cause SQL errors (e.g. userid=0)
                            const nonStickyFilters = ["search", "userid"];
                            nonStickyFilters.forEach(f => {
                                if (currentUrl.searchParams.has(f)) {
                                    currentUrl.searchParams.delete(f);
                                    needsRefresh = true;
                                }
                            });
                            
                            if (needsRefresh) {
                                currentUrl.searchParams.set("_cleared", "1");
                                window.location.replace(currentUrl.toString());
                                return; // Stop execution to allow redirect
                            }
                        }
                    } else if (window.location.search.includes("_cleared=1")) {
                        // Clean up the URL cosmetically after the clearing redirect
                        const cleanUrl = new URL(window.location.href);
                        cleanUrl.searchParams.delete("_cleared");
                        window.history.replaceState({}, "", cleanUrl.toString());
                    }
                } catch(e) {}
                
                window.updateGradingTable = function(url) {
                    const gradingTable = document.querySelector(".gradingtable");
                    if (gradingTable) {
                        gradingTable.style.opacity = "0.5";
                        gradingTable.style.pointerEvents = "none";
                        gradingTable.style.transition = "opacity 0.2s ease";
                    }
                    
                    fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, "text/html");
                        
                        const newGradingTable = doc.querySelector(".gradingtable");
                        if (newGradingTable && gradingTable) {
                            // 1. Replace Table (keeps AMD modules intact outside)
                            gradingTable.innerHTML = newGradingTable.innerHTML;
                            gradingTable.style.opacity = "1";
                            gradingTable.style.pointerEvents = "auto";
                            
                            // 2. Replace Pagination safely
                            const oldPagination = document.querySelector(".pagination")?.closest("nav") || document.querySelector(".pagination");
                            const newPagination = doc.querySelector(".pagination")?.closest("nav") || doc.querySelector(".pagination");
                            if (oldPagination && newPagination) {
                                oldPagination.innerHTML = newPagination.innerHTML;
                            } else if (oldPagination) {
                                oldPagination.innerHTML = "";
                            }
                            
                            // 3. Manually visually sync tertiary navigation (Toolbars) to preserve ES6 listeners
                            const currentNav = document.querySelector(".tertiary-navigation");
                            const newNav = doc.querySelector(".tertiary-navigation");
                            if (currentNav && newNav) {
                                // Sync text inputs (Search)
                                const oldSearch = currentNav.querySelectorAll("input[type=\'text\'], input[type=\'search\']");
                                const newSearch = newNav.querySelectorAll("input[type=\'text\'], input[type=\'search\']");
                                oldSearch.forEach((el, i) => { if (newSearch[i]) el.value = newSearch[i].value; });
                                
                                // Sync selects
                                const oldSelect = currentNav.querySelectorAll("select");
                                const newSelect = newNav.querySelectorAll("select");
                                oldSelect.forEach((el, i) => { if (newSelect[i]) el.value = newSelect[i].value; });
                                
                                // Sync dropdown button texts (Action menus)
                                const oldButtons = currentNav.querySelectorAll(".dropdown-toggle");
                                const newButtons = newNav.querySelectorAll(".dropdown-toggle");
                                oldButtons.forEach((el, i) => { 
                                    if (newButtons[i]) el.innerHTML = newButtons[i].innerHTML;
                                });
                                
                                // Sync combobox/select_menu current values (Name initials, Status filter)
                                const oldValues = currentNav.querySelectorAll("[data-region=\'currentvalue\']");
                                const newValues = newNav.querySelectorAll("[data-region=\'currentvalue\']");
                                oldValues.forEach((el, i) => {
                                    if (newValues[i]) el.innerHTML = newValues[i].innerHTML;
                                });
                                
                                // Sync core/select_menu (Status filter) state
                                const oldSelects = currentNav.querySelectorAll(".select-menu");
                                const newSelects = newNav.querySelectorAll(".select-menu");
                                oldSelects.forEach((el, i) => {
                                    if (newSelects[i]) {
                                        const oldInput = el.querySelector("input[type=\'hidden\']");
                                        const newInput = newSelects[i].querySelector("input[type=\'hidden\']");
                                        if (oldInput && newInput) oldInput.value = newInput.value;
                                        
                                        const oldText = el.querySelector("[data-selected-option]");
                                        const newText = newSelects[i].querySelector("[data-selected-option]");
                                        if (oldText && newText) oldText.innerHTML = newText.innerHTML;
                                        
                                        const oldOptions = el.querySelectorAll("[role=\'option\']");
                                        const newOptions = newSelects[i].querySelectorAll("[role=\'option\']");
                                        oldOptions.forEach((opt, j) => {
                                            if (newOptions[j]) {
                                                if (newOptions[j].hasAttribute("aria-selected")) {
                                                    opt.setAttribute("aria-selected", "true");
                                                } else {
                                                    opt.removeAttribute("aria-selected");
                                                }
                                            }
                                        });
                                    }
                                });
                                
                                // Sync checkboxes (Quick grading, Folders)
                                const oldChecks = currentNav.querySelectorAll("input[type=\'checkbox\']");
                                const newChecks = newNav.querySelectorAll("input[type=\'checkbox\']");
                                oldChecks.forEach((el, i) => { if (newChecks[i]) el.checked = newChecks[i].checked; });
                            }
                            
                            try {
                                if (typeof M !== "undefined" && M.mod_assign && M.mod_assign.init_grading_table) {
                                    if (typeof YUI !== "undefined") {
                                        YUI().use("node", function(Y) {
                                            M.mod_assign.init_grading_table(Y);
                                        });
                                    }
                                }
                            } catch (e) {
                                console.error("Grading table reinit error:", e);
                            }
                            
                            window.history.pushState({}, "", url);
                        } else {
                            window.location.href = url;
                        }
                    })
                    .catch(err => {
                        console.error("AJAX error:", err);
                        window.location.href = url;
                    });
                };
                
                // Intercept changes (checkboxes, selects, and core/select_menu)
                document.addEventListener("change", function(e) {
                    if (e.target.closest(".tertiary-navigation") || e.target.closest(".gradingoptionsform")) {
                        
                        if (e.target.tagName === "SELECT") {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            window.updateGradingTable(e.target.value);
                        } else if (e.target.classList.contains("select-menu") || e.target.dataset.region === "select-menu") {
                            // core/select_menu (Status filter) triggers change on the div, and value holds the URL
                            if (e.target.value) {
                                e.preventDefault();
                                e.stopPropagation();
                                e.stopImmediatePropagation();
                                window.updateGradingTable(e.target.value);
                            }
                        } else if (e.target.type === "checkbox") {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            const url = new URL(window.location.href);
                            const name = e.target.id.split("-")[0];
                            url.searchParams.set(name, e.target.checked ? 1 : 0);
                            window.updateGradingTable(url.toString());
                        }
                    }
                }, true);
                
                // Intercept Enter key in search box
                document.addEventListener("keydown", function(e) {
                    if (e.key === "Enter" && e.target.closest(".tertiary-navigation input")) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        const url = new URL(window.location.href);
                        url.searchParams.set("search", e.target.value);
                        url.searchParams.delete("userid");
                        window.updateGradingTable(url.toString());
                    }
                }, true);
                
                // Intercept all links and combobox/select_menu dropdown clicks
                document.addEventListener("click", function(e) {
                    // Intercept initials bar Apply button
                    const initialsApply = e.target.closest(".initialsdropdownform input[data-action=\'save\']");
                    if (initialsApply) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        const form = initialsApply.closest(".initialsdropdownform");
                        
                        const firstItems = Array.from(form.querySelectorAll(".firstinitial li"));
                        const lastItems = Array.from(form.querySelectorAll(".lastinitial li"));
                        
                        const firstActive = firstItems.find(item => item.classList.contains("active"));
                        const lastActive = lastItems.find(item => item.classList.contains("active"));
                        
                        const sifirst = firstActive ? firstActive.querySelector(".page-link, input[type=\'button\']") : null;
                        const silast = lastActive ? lastActive.querySelector(".page-link, input[type=\'button\']") : null;
                        
                        const url = new URL(window.location.href);
                        
                        const firstVal = (firstActive && firstActive.classList.contains("initialbarall")) ? "" : (sifirst ? (sifirst.value || sifirst.dataset.initial || sifirst.textContent) : "");
                        const lastVal = (lastActive && lastActive.classList.contains("initialbarall")) ? "" : (silast ? (silast.value || silast.dataset.initial || silast.textContent) : "");
                        
                        if (firstVal && firstVal !== "All") {
                            url.searchParams.set("tifirst", firstVal);
                        } else {
                            url.searchParams.set("tifirst", "");
                        }
                        
                        if (lastVal && lastVal !== "All") {
                            url.searchParams.set("tilast", lastVal);
                        } else {
                            url.searchParams.set("tilast", "");
                        }
                        
                        window.updateGradingTable(url.toString());
                        
                        const dropdownFormContainer = form.closest(".dropdown-menu");
                        if (dropdownFormContainer) {
                            dropdownFormContainer.classList.remove("show");
                        }
                        return;
                    }
                    
                    const option = e.target.closest("[role=\'option\']");
                    if (option && e.target.closest(".tertiary-navigation")) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        const value = option.dataset.value;
                        
                        // Handle Name Filter (comboboxsearch) where value is userid
                        const combobox = e.target.closest(".comboboxsearch");
                        if (combobox) {
                            const url = new URL(window.location.href);
                            const searchInput = document.querySelector(".tertiary-navigation input[type=\'text\']");
                            if (searchInput) url.searchParams.set("search", searchInput.value);
                            if (value) url.searchParams.set("userid", value);
                            
                            window.updateGradingTable(url.toString());
                            
                            // Visually update combobox text immediately
                            const btnText = combobox.querySelector("[data-region=\'currentvalue\']");
                            if (btnText) btnText.textContent = option.textContent.trim();
                            const dropdown = combobox.querySelector(".dropdown-menu");
                            if (dropdown) dropdown.classList.remove("show");
                            return;
                        }
                        
                        // Handle Status Filter (select_menu) where value is the full URL
                        const selectMenu = e.target.closest(".select-menu");
                        if (selectMenu) {
                            if (value && (value.startsWith("http") || value.startsWith("/"))) {
                                window.updateGradingTable(value);
                                
                                // Visually update select_menu text immediately
                                const btnText = selectMenu.querySelector("[data-selected-option]");
                                if (btnText) btnText.textContent = option.textContent.trim();
                                
                                const allOptions = selectMenu.querySelectorAll("[role=\'option\']");
                                allOptions.forEach(opt => opt.removeAttribute("aria-selected"));
                                option.setAttribute("aria-selected", "true");
                                
                                const dropdown = selectMenu.querySelector(".dropdown-menu");
                                if (dropdown) dropdown.classList.remove("show");
                            }
                            return;
                        }
                    }
                    
                    // Intercept standard links
                    const link = e.target.closest(".pagination a, .generaltable th a, a.btn-outline-danger, a[href*=\'hide\'], a[href*=\'show\'], a[href*=\'action=grading\']");
                    if (link) {
                        const href = link.getAttribute("href");
                        if (href && href !== "#" && !href.startsWith("javascript:")) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            window.updateGradingTable(href);
                        }
                    }
                }, true);
                
            })();
        </script>';
        
        $o .= $this->output->box_end();
        $o .= '</div>';

        return $o;
    }

    /**
     * Render a feedback plugin feedback
     *
     * @param \assign_feedback_plugin_feedback $feedbackplugin
     * @return string
     */
    public function render_assign_feedback_plugin_feedback(\assign_feedback_plugin_feedback $feedbackplugin) {
        $o = '';

        if ($feedbackplugin->view == \assign_feedback_plugin_feedback::SUMMARY) {
            $showviewlink = false;
            $summary = $feedbackplugin->plugin->view_summary($feedbackplugin->grade, $showviewlink);

            $classsuffix = $feedbackplugin->plugin->get_subtype() .
                           '_' .
                           $feedbackplugin->plugin->get_type() .
                           '_' .
                           $feedbackplugin->grade->id;
            $o .= $this->output->box_start('boxaligncenter plugincontentsummary summary_' . $classsuffix);

            $link = '';
            if ($showviewlink) {
                $previewstr = get_string('viewfeedback', 'assign');
                $icon = $this->output->pix_icon('t/viewdetails', $previewstr);

                $expandstr = get_string('viewfull', 'assign');
                $expandicon = $this->output->pix_icon('t/switch_plus', $expandstr);
                $options = array(
                    'class' => 'expandsummaryicon expand_' . $classsuffix,
                    'aria-label' => $expandstr,
                    'role' => 'button',
                    'aria-expanded' => 'false'
                );
                $o .= \html_writer::link('', $expandicon, $options);

                $jsparams = array($feedbackplugin->plugin->get_subtype(),
                                  $feedbackplugin->plugin->get_type(),
                                  $feedbackplugin->grade->id);
                $this->page->requires->js_init_call('M.mod_assign.init_plugin_summary', $jsparams);

                $urlparams = array('id' => $feedbackplugin->coursemoduleid,
                                   'gid'=>$feedbackplugin->grade->id,
                                   'plugin'=>$feedbackplugin->plugin->get_type(),
                                   'action'=>'viewplugin' . $feedbackplugin->plugin->get_subtype(),
                                   'returnaction'=>$feedbackplugin->returnaction,
                                   'returnparams'=>http_build_query($feedbackplugin->returnparams));
                $url = new \moodle_url('/mod/assign/view.php', $urlparams);
                $link .= '<noscript>';
                $link .= $this->output->action_link($url, $icon);
                $link .= '</noscript>';

                $link .= $this->output->spacer(array('width'=>15));
            }

            $o .= $link . $summary;
            $o .= $this->output->box_end();
            if ($showviewlink) {
                $o .= $this->output->box_start('boxaligncenter hidefull full_' . $classsuffix);
                $collapsestr = get_string('viewsummary', 'assign');
                $options = array(
                    'class' => 'expandsummaryicon contract_' . $classsuffix,
                    'aria-label' => $collapsestr,
                    'role' => 'button',
                    'aria-expanded' => 'true'
                );
                $collapseicon = $this->output->pix_icon('t/switch_minus', $collapsestr);
                $o .= \html_writer::link('', $collapseicon, $options);

                $o .= $feedbackplugin->plugin->view($feedbackplugin->grade);
                $o .= $this->output->box_end();
            }
        } else if ($feedbackplugin->view == \assign_feedback_plugin_feedback::FULL) {
            $o .= $this->output->box_start('boxaligncenter feedbackfull');
            $o .= $feedbackplugin->plugin->view($feedbackplugin->grade);
            $o .= $this->output->box_end();
        }

        return $o;
    }

    /**
     * Render a course index summary
     *
     * @deprecated since Moodle 5.0 (MDL-83888).
     * @todo MDL-84429 Final deprecation in Moodle 6.0.
     * @param \assign_course_index_summary $indexsummary
     * @return string
     */
    #[\core\attribute\deprecated(
        since: '5.0',
        mdl: 'MDL-83888',
        reason: 'The assign_course_index_summary class is not used anymore.',
    )]
    public function render_assign_course_index_summary(\assign_course_index_summary $indexsummary) {
        \core\deprecation::emit_deprecation([$this, __FUNCTION__]);

        $o = '';

        $strplural = get_string('modulenameplural', 'assign');
        $strsectionname  = $indexsummary->courseformatname;
        $strduedate = get_string('duedate', 'assign');
        $strsubmission = get_string('submission', 'assign');
        $strgrade = get_string('gradenoun');

        $table = new \html_table();
        if ($indexsummary->usesections) {
            $table->head  = array ($strsectionname, $strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right', 'right');
        } else {
            $table->head  = array ($strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right');
        }
        $table->data = array();

        $currentsection = '';
        foreach ($indexsummary->assignments as $info) {
            $params = array('id' => $info['cmid']);
            $link = \html_writer::link(new \moodle_url('/mod/assign/view.php', $params),
                                      $info['cmname']);
            $due = $info['timedue'] ? userdate($info['timedue']) : '-';

            if ($info['cangrade']) {
                $params['action'] = 'grading';
                $gradeinfo = \html_writer::link(new \moodle_url('/mod/assign/view.php', $params),
                    get_string('numberofsubmissionsneedgradinglabel', 'assign', $info['gradeinfo']));
            } else {
                $gradeinfo = $info['gradeinfo'];
            }

            $printsection = '';
            if ($indexsummary->usesections) {
                if ($info['sectionname'] !== $currentsection) {
                    if ($info['sectionname']) {
                        $printsection = $info['sectionname'];
                    }
                    if ($currentsection !== '') {
                        $table->data[] = 'hr';
                    }
                    $currentsection = $info['sectionname'];
                }
            }

            if ($indexsummary->usesections) {
                $row = [$printsection, $link, $due, $info['submissioninfo'], $gradeinfo];
            } else {
                $row = [$link, $due, $info['submissioninfo'], $gradeinfo];
            }
            $table->data[] = $row;
        }

        $o .= \html_writer::table($table);

        return $o;
    }

    /**
     * Get the time remaining for a submission.
     *
     * @param \mod_assign\output\assign_submission_status $status
     * @return array The first element is the time remaining as a human readable
     *               string and the second is a CSS class.
     */
    protected function get_time_remaining(\mod_assign\output\assign_submission_status $status): array {
        $time = time();
        $submission = $status->teamsubmission ? $status->teamsubmission : $status->submission;
        $submissionstarted = $submission && property_exists($submission, 'timestarted') && $submission->timestarted;
        $timelimitenabled = get_config('assign', 'enabletimelimit') && $status->timelimit > 0 && $submissionstarted;
        // Define $duedate as latest between due date and extension - which is a possibility...
        $extensionduedate = intval($status->extensionduedate);
        $duedate = !empty($extensionduedate) ? max($status->duedate, $extensionduedate) : $status->duedate;
        $duedatereached = $duedate > 0 && $duedate - $time <= 0;
        $timelimitenabledbeforeduedate = $timelimitenabled && !$duedatereached;

        // There is a submission, display the relevant early/late message.
        if ($submission && $submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            $latecalculation = $submission->timemodified - ($timelimitenabledbeforeduedate ? $submission->timestarted : 0);
            $latethreshold = $timelimitenabledbeforeduedate ? $status->timelimit : $duedate;
            $earlystring = $timelimitenabledbeforeduedate ? 'submittedundertime' : 'submittedearly';
            $latestring = $timelimitenabledbeforeduedate ? 'submittedovertime' : 'submittedlate';
            $ontime = $latecalculation <= $latethreshold;
            return [
                get_string(
                    $ontime ? $earlystring : $latestring,
                    'assign',
                    format_time($latecalculation - $latethreshold)
                ),
                $ontime ? 'earlysubmission' : 'latesubmission'
            ];
        }

        // There is no submission, due date has passed, show assignment is overdue.
        if ($duedatereached) {
            return [
                get_string(
                    $status->submissionsenabled ? 'overdue' : 'duedatereached',
                    'assign',
                    format_time($time - $duedate)
                ),
                'overdue'
            ];
        }

        // An attempt has started and there is a time limit, display the time limit.
        if ($timelimitenabled && !empty($submission->timestarted)) {
            return [
                (new \assign($status->context, null, null))->get_timelimit_panel($submission),
                'timeremaining'
            ];
        }

        // Assignment is not overdue, and no submission has been made. Just display the due date.
        return [get_string('paramtimeremaining', 'assign', format_time($duedate - $time)), 'timeremaining'];
    }

    /**
     * Internal function - creates htmls structure suitable for YUI tree.
     *
     * @param \assign_files $tree
     * @param array $dir
     * @return string
     */
    protected function htmllize_tree(\assign_files $tree, $dir) {
        global $CFG;
        $yuiconfig = array();
        $yuiconfig['type'] = 'html';

        if (empty($dir['subdirs']) and empty($dir['files'])) {
            return '';
        }

        $result = '<ul>';
        foreach ($dir['subdirs'] as $subdir) {
            $image = $this->output->pix_icon(file_folder_icon(),
                                             $subdir['dirname'],
                                             'moodle',
                                             array('class'=>'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'>' .
                       '<div>' . $image . ' ' . s($subdir['dirname']) . '</div> ' .
                       $this->htmllize_tree($tree, $subdir) .
                       '</li>';
        }

        foreach ($dir['files'] as $file) {
            $filename = $file->get_filename();
            if ($CFG->enableplagiarism) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $plagiarismlinks = plagiarism_get_links(array('userid'=>$file->get_userid(),
                                                             'file'=>$file,
                                                             'cmid'=>$tree->cm->id,
                                                             'course'=>$tree->course));
            } else {
                $plagiarismlinks = '';
            }
            $image = $this->output->pix_icon(file_file_icon($file),
                                             $filename,
                                             'moodle',
                                             array('class'=>'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'>' .
                '<div>' .
                    '<div class="fileuploadsubmission">' . $image . ' ' .
                    html_writer::link($tree->get_file_url($file), $file->get_filename(), [
                        'target' => '_blank',
                    ]) . ' ' .
                    $plagiarismlinks . ' ' .
                    $this->get_portfolio_button($tree, $file) . ' ' .
                    '</div>' .
                    '<div class="fileuploadsubmissiontime">' . $tree->get_modified_time($file) . '</div>' .
                '</div>' .
            '</li>';
        }

        $result .= '</ul>';

        return $result;
    }

    /**
     * Get the portfolio button content for the specified file.
     *
     * @param assign_files $tree
     * @param stored_file $file
     * @return string
     */
    protected function get_portfolio_button(assign_files $tree, stored_file $file): string {
        global $CFG;
        if (empty($CFG->enableportfolios)) {
            return '';
        }

        if (!has_capability('mod/assign:exportownsubmission', $tree->context)) {
            return '';
        }

        require_once($CFG->libdir . '/portfoliolib.php');

        $button = new portfolio_add_button();
        $portfolioparams = [
            'cmid' => $tree->cm->id,
            'fileid' => $file->get_id(),
        ];
        $button->set_callback_options('assign_portfolio_caller', $portfolioparams, 'mod_assign');
        $button->set_format_by_file($file);

        return (string) $button->to_html(PORTFOLIO_ADD_ICON_LINK);
    }

    /**
     * Helper method dealing with the fact we can not just fetch the output of flexible_table
     *
     * @param \flexible_table $table The table to render
     * @param int $rowsperpage How many assignments to render in a page
     * @param bool $displaylinks - Whether to render links in the table
     *                             (e.g. downloads would not enable this)
     * @return string HTML
     */
    protected function flexible_table(\flexible_table $table, $rowsperpage, $displaylinks) {

        $o = '';
        ob_start();
        $table->out($rowsperpage, $displaylinks);
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * Helper method dealing with the fact we can not just fetch the output of moodleforms
     *
     * @param \moodleform $mform
     * @return string HTML
     */
    protected function moodleform(\moodleform $mform) {

        $o = '';
        ob_start();
        $mform->display();
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * Defer to template.
     *
     * @param grading_app $app - All the data to render the grading app.
     */
    public function render_grading_app(grading_app $app) {
        $context = $app->export_for_template($this);
        return $this->render_from_template('mod_assign/grading_app', $context);
    }

    /**
     * Renders the submission action menu.
     *
     * @param \mod_assign\output\actionmenu $actionmenu The actionmenu
     * @return string Rendered action menu.
     */
    public function submission_actionmenu(\mod_assign\output\actionmenu $actionmenu): string {
        $context = $actionmenu->export_for_template($this);
        return $this->render_from_template('mod_assign/submission_actionmenu', $context);
    }

    /**
     * Renders the user submission action menu.
     *
     * @param \mod_assign\output\user_submission_actionmenu $actionmenu The actionmenu
     * @return string The rendered action menu.
     */
    public function render_user_submission_actionmenu(\mod_assign\output\user_submission_actionmenu $actionmenu): string {
        $context = $actionmenu->export_for_template($this);
        return $this->render_from_template('mod_assign/user_submission_actionmenu', $context);
    }

    /**
     * Renders the override action menu.
     *
     * @param \mod_assign\output\override_actionmenu $actionmenu The actionmenu
     * @return string The rendered override action menu.
     */
    public function render_override_actionmenu(\mod_assign\output\override_actionmenu $actionmenu): string {
        $context = $actionmenu->export_for_template($this);
        return $this->render_from_template('mod_assign/override_actionmenu', $context);
    }

    /**
     * Renders the grading action menu.
     *
     * @param \mod_assign\output\grading_actionmenu $actionmenu The actionmenu
     * @return string The rendered grading action menu.
     */
    public function render_grading_actionmenu(\mod_assign\output\grading_actionmenu $actionmenu): string {
        $context = $actionmenu->export_for_template($this);
        return $this->render_from_template('mod_assign/grading_actionmenu', $context);
    }

    /**
     * Formats activity intro text.
     *
     * @param object $assign Instance of assign.
     * @param int $cmid Course module ID.
     * @return string
     */
    public function format_activity_text($assign, $cmid) {
        global $CFG;
        require_once("$CFG->libdir/filelib.php");
        $context = \context_module::instance($cmid);
        $options = array('noclean' => true, 'para' => false, 'filter' => true, 'context' => $context, 'overflowdiv' => true);
        $activity = file_rewrite_pluginfile_urls(
            $assign->activity, 'pluginfile.php', $context->id, 'mod_assign', ASSIGN_ACTIVITYATTACHMENT_FILEAREA, 0);
        return trim(format_text($activity, $assign->activityformat, $options, null));
    }
}
