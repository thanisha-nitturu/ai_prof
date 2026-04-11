<?php
defined('MOODLE_INTERNAL') || die();
use core_question\category_manager;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
 

class local_create_sections_external extends external_api {

    /**
     * Describes the parameters for the create_sections function.
     *
     * @return external_function_parameters
     */
    public static function create_sections_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The ID of the course'),
            'names' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'The name for the new section')
            )
        ]);
    }

    /**
     * The function to create course sections.
     *
     * @param int $courseid
     * @param array $names
     * @return array
     */
    public static function create_sections($courseid, $names) {
        global $DB;

        // Get course context
        $context = context_course::instance($courseid);
        require_capability('moodle/course:update', $context);

        // Get the course format object
        $format = course_get_format($courseid);

        // Find the current max section number
        $maxsection = (int)$DB->get_field_sql('SELECT MAX(section) FROM {course_sections} WHERE course = ?', [$courseid]);

        $created = [];
        foreach ($names as $name) {
            $maxsection++;

            // Create new section object
            $section = (object)[
                'course' => $courseid,
                'section' => $maxsection,
                'name' => $name,
                'summary' => '',
                'summaryformat' => FORMAT_HTML,
                'visible' => 1
            ];

            // Insert section into DB
            $sectionid = $DB->insert_record('course_sections', $section);

            // Let the course format update its internal structures
            $format->update_course_format_options([$sectionid]);

            $created[] = ['id' => $sectionid, 'name' => $name];
        }

        // Rebuild course cache
        rebuild_course_cache($courseid, true);

        return $created;

    }

    /**
     * Describes the structure of the data returned by the create_sections function.
     *
     * @return external_multiple_structure
     */
    public static function create_sections_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The ID of the newly created section'),
                'name' => new external_value(PARAM_TEXT, 'The name of the newly created section'),
            ])
        );
    }

    /**
     * Describes the parameters for the create_label function.
     */
    public static function create_label_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The ID of the course'),
            'sectionnum' => new external_value(PARAM_INT, 'The section number to add the label to'),
            'intro' => new external_value(PARAM_RAW, 'The HTML content for the label'),
            'labelname' => new external_value(PARAM_TEXT, 'The visible name for the label')
        ]);
    }


    public static function create_label($courseid, $sectionnum, $intro ,$labelname) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // Validate parameters
        $params = self::validate_parameters(self::create_label_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'intro' => $intro,
            'labelname' => $labelname
        ]);

        // Validate context and capability
        $context = context_course::instance($params['courseid'], MUST_EXIST);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // Get the course and section details
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $params['sectionnum']], '*', MUST_EXIST);

        // Prepare the data for the new label
        $labeldata = new stdClass();
        $labeldata->course = $course->id;
        $labeldata->name = $params['labelname']; // Labels don't have a visible name, but the field is required.
        $labeldata->intro = $params['intro'];
        $labeldata->introformat = FORMAT_HTML;
        $labeldata->timemodified = time();
        $instanceid = $DB->insert_record('label', $labeldata);
        //$labeldata->name = $params['labelname'];

        // Prepare the course module object
        $cm = new stdClass();
        $cm->course = $course->id;
        $cm->module = $DB->get_field('modules', 'id', ['name' => 'label']);
        $cm->instance = $instanceid;
        $cm->section = $section->id; // ✅ FIX: Use the section's unique database ID (e.g., 1152)
        $cm->visible = 1;
        $cm->completion = 1;
        $cm->added = time();
        $cm->idnumber = ''; // Optional ID number

        // Use the official Moodle API to add the course module
        $cmid = add_course_module($cm);

        // This is critical to actually place it in the section
        course_add_cm_to_section($course, $cmid, $section->section);

        // Refresh the course cache so the UI updates
        rebuild_course_cache($course->id, true);
        // Rebuild the course cache to make the change visible
        rebuild_course_cache($course->id, true);

        return [
            'cmid' => $cmid,
            'instanceid' => $instanceid
        ];
    }


    /**
     * Describes the structure of the data returned by the create_label function.
     */
    public static function create_label_returns() {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'The course module ID for the new label'),
            'instanceid' => new external_value(PARAM_INT, 'The instance ID from the label table'),
        ]);
    }

    public static function update_label_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the label'),
            'newintro' => new external_value(PARAM_RAW, 'Updated label HTML content'),
        ]);
    }

    public static function update_label($cmid, $newintro) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/label/lib.php');

        // Validate parameters
        $params = self::validate_parameters(self::update_label_parameters(), [
            'cmid' => $cmid,
            'newintro' => $newintro
        ]);

        // Get the course module and context
        $cm = get_coursemodule_from_id('label', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // Fetch the existing label record
        $label = $DB->get_record('label', ['id' => $cm->instance], '*', MUST_EXIST);

        // Update intro and metadata
        $label->intro = $params['newintro'];
        $label->introformat = FORMAT_HTML;
        $label->timemodified = time();

        // Update the label record in DB
        $DB->update_record('label', $label);

        // Rebuild course cache so Moodle UI reflects changes
        rebuild_course_cache($cm->course, true);

        return [
            'status' => 'success',
            'cmid' => $cm->id,
            'updated' => true,
            'updated_at' => time()
        ];
    }

    public static function update_label_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status of the update'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'updated' => new external_value(PARAM_BOOL, 'Whether the label was updated'),
            'updated_at' => new external_value(PARAM_INT, 'Timestamp of update')
        ]);
    }



    public static function create_empty_media_label_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number'),
            'labelname' => new external_value(PARAM_TEXT, 'Label name'),
            'filename' => new external_value(PARAM_TEXT, 'File name for the label'),
            // Note: filecontent comes from $_FILES['filecontent']
        ]);
    }

    public static function create_empty_media_label($courseid, $sectionnum, $labelname, $filename) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->libdir.'/filelib.php');

        $params = self::validate_parameters(self::create_empty_media_label_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'labelname' => $labelname,
            'filename' => $filename
        ]);

        // 1️⃣ Validate course, section, capability
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $section = $DB->get_record('course_sections', ['course' => $params['courseid'], 'section' => $params['sectionnum']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $context);

        // 2️⃣ Create empty label
        $labeldata = new stdClass();
        $labeldata->course = $course->id;
        $labeldata->name = $params['labelname'];
        $labeldata->intro = '';
        $labeldata->introformat = FORMAT_HTML;
        $labeldata->timecreated = time();
        $labeldata->timemodified = time();
        $instanceid = $DB->insert_record('label', $labeldata);

        $cm = new stdClass();
        $cm->course = $course->id;
        $cm->module = $DB->get_field('modules', 'id', ['name' => 'label']);
        $cm->instance = $instanceid;
        $cm->section = $section->id;
        $cm->visible = 1;
        $cm->completion = 1;
        $cm->added = time();
        $cmid = add_course_module($cm);
        course_add_cm_to_section($course, $cmid, $section->section);

        $modcontextid = context_module::instance($cmid)->id;

        // 3️⃣ Handle uploaded file
        if (empty($_FILES['filecontent']) || !is_uploaded_file($_FILES['filecontent']['tmp_name'])) {
            throw new moodle_exception('nofileuploaded', 'local_yourplugin');
        }
        $tmpfile = $_FILES['filecontent']['tmp_name'];

        $fs = get_file_storage();
        $file_record = [
            'contextid' => $modcontextid,
            'component' => 'mod_label',
            'filearea'  => 'intro',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $params['filename']
        ];

        $fs->create_file_from_pathname($file_record, $tmpfile);

        $fileurl = moodle_url::make_pluginfile_url(
            $modcontextid, 'mod_label', 'intro', 0, '/', $params['filename']
        )->out(false);

        // 4️⃣ Update label intro
        $label = $DB->get_record('label', ['id' => $cm->instance], '*', MUST_EXIST);
        $ext = strtolower(pathinfo($params['filename'], PATHINFO_EXTENSION));

        if (in_array($ext, ['mp3','wav','ogg'])) {
            $label->intro = "<audio controls><source src=\"@@PLUGINFILE@@/{$params['filename']}\" type=\"audio/$ext\"></audio>";
        } elseif (in_array($ext, ['mp4','webm','ogg'])) {
            $label->intro = "<video controls width='640'><source src=\"@@PLUGINFILE@@/{$params['filename']}\" type=\"video/$ext\"></video>";
        } else {
            $label->intro = "<a href=\"@@PLUGINFILE@@/{$params['filename']}\" target='_blank'>Download file</a>";
        }
        $label->introformat = FORMAT_HTML;
        $DB->update_record('label', $label);

        rebuild_course_cache($course->id, true);

        return [
            'cmid' => $cmid,
            'instanceid' => $instanceid,
            'contextid' => $modcontextid,
            'fileurl' => $fileurl
        ];
    }

    public static function create_empty_media_label_returns() {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'instanceid' => new external_value(PARAM_INT, 'Label instance ID'),
            'contextid' => new external_value(PARAM_INT, 'Module context ID'),
            'fileurl' => new external_value(PARAM_TEXT, 'URL of the uploaded file in the label')
        ]);
    }

    
    /**
     * Defines parameters for updating an existing media label.
     */
    public static function update_media_label_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the label to update'),
            'filename' => new external_value(PARAM_TEXT, 'The new file name for the label'),
            // Note: The actual file content is expected in $_FILES['filecontent']
        ]);
    }

    /**
     * Updates an existing label by replacing its media file and embedding code.
     */
    public static function update_media_label($cmid, $filename) {
        global $DB, $CFG;

        // 1. Include necessary Moodle libraries
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/filelib.php');

        // 2. Validate parameters
        $params = self::validate_parameters(self::update_media_label_parameters(), [
            'cmid' => $cmid,
            'filename' => $filename
        ]);

        // 3. Get Moodle objects and check capabilities
        $cm = get_coursemodule_from_id('label', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        $label = $DB->get_record('label', ['id' => $cm->instance], '*', MUST_EXIST);

        // 4. Handle the file update process
        $fs = get_file_storage();
        
        // IMPORTANT: First, delete any existing files in this label's intro area.
        // This prevents orphaned files from accumulating.
        // This is the new, correct way
        $files = $fs->get_area_files($context->id, 'mod_label', 'intro', 0);
        foreach ($files as $file) {
            $file->delete();
        }

        // Check for the newly uploaded file
        if (empty($_FILES['filecontent']) || !is_uploaded_file($_FILES['filecontent']['tmp_name'])) {
            throw new moodle_exception('nofileuploaded', 'webservice');
        }
        $tmpfile = $_FILES['filecontent']['tmp_name'];

        // Define the record for the new file
        $file_record = [
            'contextid' => $context->id,
            'component' => 'mod_label',
            'filearea'  => 'intro',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $params['filename']
        ];

        // Store the new file in the designated file area
        $stored_file = $fs->create_file_from_pathname($file_record, $tmpfile);

        // 5. Update the label's intro content to embed the new file
        $ext = strtolower(pathinfo($params['filename'], PATHINFO_EXTENSION));

        if (in_array($ext, ['mp3', 'wav', 'ogg'])) {
            $label->intro = "<audio controls><source src=\"@@PLUGINFILE@@/{$params['filename']}\" type=\"audio/$ext\"></audio>";
        } elseif (in_array($ext, ['mp4', 'webm'])) {
            $label->intro = "<video controls width='640'><source src=\"@@PLUGINFILE@@/{$params['filename']}\" type=\"video/$ext\"></video>";
        } else {
            // Fallback for other file types like PDF, DOCX, etc.
            $label->intro = "<a href=\"@@PLUGINFILE@@/{$params['filename']}\" target='_blank'>Download {$params['filename']}</a>";
        }

        $label->introformat = FORMAT_HTML;
        $label->timemodified = time();

        $DB->update_record('label', $label);

        // 6. Rebuild the course cache to reflect changes immediately
        rebuild_course_cache($cm->course, true);

        // Generate the URL for the newly uploaded file
        $fileurl = moodle_url::make_pluginfile_url(
            $context->id, 'mod_label', 'intro', 0, '/', $params['filename']
        )->out(false);

        return [
            'status' => 'success',
            'cmid' => $cm->id,
            'fileurl' => $fileurl,
            'updated_at' => $label->timemodified
        ];
    }

    /**
     * Describes the return structure for the update_media_label function.
     */
    public static function update_media_label_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status of the update'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the updated label'),
            'fileurl' => new external_value(PARAM_TEXT, 'The URL of the new file'),
            'updated_at' => new external_value(PARAM_INT, 'Timestamp of the update')
        ]);
    }


    public static function create_quiz_from_xml_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The ID of the course'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number (1..n)'),
            'quizname' => new external_value(PARAM_TEXT, 'The name of the quiz'),
            'categoryname' => new external_value(PARAM_TEXT, 'Name for the new question category'),
            'xmlfile' => new external_value(PARAM_RAW, 'Contents of the XML formatted question file'),
            'userid' => new external_value(PARAM_INT, 'User id to act as')
        ]);
    }

    /**
     * The main combined function to create a quiz and populate it with questions.
     */
    public static function create_quiz_from_xml($courseid, $sectionnum, $quizname, $categoryname, $xmlfile, $userid) {
        global $DB, $USER, $CFG;

        // --- SETUP: Consolidate all required libraries ---
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/question/engine/lib.php');
        require_once($CFG->dirroot . '/question/editlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        // --- Validate all combined parameters ---
        $params = self::validate_parameters(self::create_quiz_from_xml_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'quizname' => $quizname,
            'categoryname' => $categoryname,
            'xmlfile' => $xmlfile,
            'userid' => $userid
        ]);

        // --- Switch user and start a single transaction for the entire operation ---
        $originaluser = $USER;
        $USER = $DB->get_record('user', ['id' => $params['userid']], '*', MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();

        try {
            // --- Initial validation and capability check ---
            $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
            $context = context_course::instance($course->id);
            require_capability('moodle/course:manageactivities', $context);

            // === Default Quiz Description (same for all quizzes) ===
            $default_description = "
                <p><b>To attempt the quiz:</b></p>
                <ul>
                    <li>You have 50 minutes to complete the quiz, which consists of 15 multiple-choice questions drawn from above module content.</li>
                    <li>Ensure you review each question carefully and submit your answers before the time expires.</li>
                    <li>To pass, you must reach at least 70% within 3 attempts. We keep your highest score.</li>
                </ul>
            ";

            // === Step 1: Create minimal quiz module ===
            $moduledata = new stdClass();
            $moduledata->course = $course->id;
            $moduledata->section = $params['sectionnum'];
            $moduledata->modulename = 'quiz';
            $moduledata->name = $params['quizname'];
            $moduledata->visible = 1;
            $moduledata->introeditor = ['text' => $default_description, 'format' => FORMAT_HTML, 'itemid' => 0];
            $moduledata->completion = COMPLETION_TRACKING_AUTOMATIC;
            $moduledata->completionview = 0;
            $moduledata->completiongradeitemnumber = null;
            $moduledata->completionpassgrade = 1;
            $moduledata->completionusegrade = 1;
            
            $moduledata->timeopen = 0;
            $moduledata->timeclose = 0;
            $moduledata->timelimit = 0;
            $moduledata->overduehandling = 'autoabandon';
            $moduledata->graceperiod = 0;
            $moduledata->preferredbehaviour = 'deferredfeedback';
            $moduledata->canredoquestions = 0;
            $moduledata->attempts = 0;
            $moduledata->attemptonlast = 0;
            $moduledata->grademethod = 1;
            $moduledata->decimalpoints = 2;
            $moduledata->questiondecimalpoints = -1;
            $moduledata->reviewattempt = 0;
            $moduledata->reviewcorrectness = 0;
            $moduledata->reviewmaxmarks = 0;
            $moduledata->reviewmarks = 0;
            $moduledata->reviewspecificfeedback = 0;
            $moduledata->reviewgeneralfeedback = 0;
            $moduledata->reviewrightanswer = 0;
            $moduledata->reviewoverallfeedback = 0;
            $moduledata->questionsperpage = 0;
            $moduledata->navmethod = 'free';
            $moduledata->shuffleanswers = 0;
            $moduledata->sumgrades = 0;
            $moduledata->grade = 0;
            $moduledata->quizpassword = '';
            $moduledata->subnet = '';
            $moduledata->browsersecurity = '';
            $moduledata->delay1 = 0;
            $moduledata->delay2 = 0;
            $moduledata->showuserpicture = 0;
            $moduledata->showblocks = 0;


            $moduleinfo = create_module($moduledata);
            $cm = get_coursemodule_from_id('quiz', $moduleinfo->coursemodule, 0, false, MUST_EXIST);

            $cm = get_coursemodule_from_id('quiz', $moduleinfo->coursemodule, 0, false, MUST_EXIST);

            // Automatic completion based on grade
            
            // === Step 2: Update quiz settings directly ===
            $quiz = $DB->get_record('quiz', ['id' => $moduleinfo->instance], '*', MUST_EXIST);

            // --- General ---
            $quiz->intro = $default_description;
            $quiz->introformat = FORMAT_HTML;

            // --- Timing ---
            $quiz->timeopen = 0;
            $quiz->timeclose = 0;
            $quiz->timelimit = 50 * 60; // 50 minutes
            $quiz->overduehandling = 'autosubmit';
            $quiz->graceperiod = 0;

            // --- Grade ---
            $quiz->grade = 100;
            $quiz->passgrade = 70;
            $quiz->sumgrades = 0;
            $quiz->attempts = 3;
            $quiz->grademethod = 1; // Highest grade
            $quiz->decimalpoints = 2;

            // --- Layout & Behaviour ---
            $quiz->questionsperpage = 0; // All on one page
            $quiz->shuffleanswers = 1;
            $quiz->preferredbehaviour = 'deferredfeedback';


            // Timing bitmask constants (from mod/quiz/lib.php)
            $during      = 4096;             // 4096
            $immediately = 256; // 256
            $open        = 512;  // 512

            $three_periods = $during + $immediately + $open; // 4096 + 256 + 512 = 4864

            $quiz->reviewattempt = 69888; // The attempt: During, Immediately, Later
            $quiz->reviewcorrectness = 4352; // Whether correct: Immediately, Later
            $quiz->reviewmarks = 4864; // Maximum marks: During, Immediately, Later
            $quiz->reviewspecificfeedback = 4352; // Specific feedback: Immediately, Later
            $quiz->reviewgeneralfeedback = 4352; // General feedback: Immediately, Later
            $quiz->reviewrightanswer = 4352; // Right answer: Immediately, Later
            $quiz->reviewoverallfeedback = 4352; // Overall feedback: Immediately, Later
            $quiz->reviewmaxmarks = $three_periods;
            
            // --- Appearance ---
            $quiz->showuserpicture = 0;
            $quiz->questiondecimalpoints = 2;

            // --- Safe Exam Browser ---
            $quiz->seb_requiresafeexam = 0;

            // --- Extra Restrictions ---
            $quiz->password = '';

            // --- Overall Feedback ---
            $quiz->feedbacktext = [
                ['text' => 'Congratulations! You passed the quiz.', 'format' => FORMAT_HTML],
                ['text' => 'Please review the material and try again.', 'format' => FORMAT_HTML]
            ];
            $quiz->feedbackboundaries = ['100', '0'];

            $cm->completion = 2;
            $cm->completionview = 0;
            $cm->completionusegrade = 1;
            $cm->completionpassgrade = 1;

            $DB->update_record('course_modules', $cm);


            // Save all changes
            $DB->update_record('quiz', $quiz);
            rebuild_course_cache($course->id, true);

            // === STEP 2: Create Category and Import Questions (from original second function) ===
            $modulecontext = context_module::instance($cm->id);
            require_capability('mod/quiz:manage', $modulecontext); // Capability for managing quiz content

            // --- Create question category in the quiz's module context ---
            $categoryobject = new stdClass();
            $categoryobject->name = $params['categoryname'];
            $categoryobject->info = '';
            $categoryobject->infoformat = FORMAT_HTML;
            $categoryobject->contextid = $modulecontext->id;
            $categoryobject->stamp = make_unique_id_code();
            $categoryobject->parent = 0;
            $categoryid = $DB->insert_record('question_categories', $categoryobject);
            $categoryobject->id = $categoryid;

            // --- Import questions from XML file content ---
            ob_start();
            $tempfile = tempnam($CFG->tempdir, 'xml_');
            file_put_contents($tempfile, $params['xmlfile']);
            $format = new qformat_xml();
            $format->setCategory($categoryobject);
            $format->setCourse($course);
            $format->setFilename($tempfile);
            $format->setStoponerror(false);
            $importok = $format->importpreprocess() && $format->importprocess() && $format->importpostprocess();
            ob_end_clean();
            unlink($tempfile);

            if (!$importok) {
                throw new moodle_exception('cannotimport', 'question');
            }

            // === STEP 3: Add Imported Questions to the Quiz (from original third function) ===
            $questions = get_questions_category($categoryobject, true);
            if (empty($questions)) {
                throw new moodle_exception('noquestions', 'quiz', '', null, 'No questions were found in the imported file.');
            }
            
            $questionids = array_map(function($q) { return $q->id; }, $questions);

            // --- Add each question to the quiz ---
            foreach ($questionids as $questionid) {
                // The page parameter is 0 to add all questions to the same page.
                quiz_add_quiz_question($questionid, $quiz, 0, 1.0);
            }

            // --- Finalize quiz structure and grades ---
            quiz_update_sumgrades($quiz);
            quiz_grade_item_update($quiz);
            rebuild_course_cache($course->id, true);
            
            // --- If all steps succeeded, commit the transaction ---
            $transaction->allow_commit();
            
            // --- Restore the original user ---
            $USER = $originaluser;

            // --- Return the final combined result ---
            return [
                'quizid' => $quiz->id,
                'cmid' => $cm->id,
                'categoryid' => $categoryid,
                'questioncount' => count($questionids)
            ];

        } catch (Exception $e) {
            // --- If any step failed, roll back all changes and restore the user ---
            $transaction->rollback($e);
            $USER = $originaluser;
            throw $e; // Re-throw the exception to notify the caller of the failure
        }
    }

    /**
     * Define the return structure for the new combined function.
     */
    public static function create_quiz_from_xml_returns() {
        return new external_single_structure([
            'quizid' => new external_value(PARAM_INT, 'The ID of the created quiz'),
            'cmid' => new external_value(PARAM_INT, 'The course module ID of the quiz'),
            'categoryid' => new external_value(PARAM_INT, 'ID of the newly created question category'),
            'questioncount' => new external_value(PARAM_INT, 'Number of questions imported and added to the quiz')
        ]);
    }

    public static function update_quiz_from_xml_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the quiz'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number where the quiz should be created'),
            'xmlfile' => new external_value(PARAM_RAW, 'Contents of the XML file with new questions'),
            'categoryname' => new external_value(PARAM_TEXT, 'Name for the new question category'),
            'userid' => new external_value(PARAM_INT, 'User ID performing the update'),
            'quizname' => new external_value(PARAM_TEXT, 'Optional: New quiz name', VALUE_OPTIONAL)
        ]);
    }

    public static function update_quiz_from_xml($cmid, $sectionnum, $xmlfile, $categoryname, $userid, $quizname = null) {
        global $DB, $USER, $CFG;

        // --- SETUP: Consolidate all required libraries ---
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/question/engine/lib.php');
        require_once($CFG->dirroot . '/question/editlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        // Get current quiz & course info
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $courseid = $quiz->course;

        // Step 1: Delete the existing quiz completely
        quiz_delete_instance($quiz->id);

        // Delete leftover question categories
        $modulecontext = context_module::instance($cmid);
        $categories = $DB->get_records('question_categories', ['contextid' => $modulecontext->id]);
        foreach ($categories as $cat) {
            question_category_delete_safe($cat);
        }

        // Step 2: Create a new quiz using the existing function
        $result = self::create_quiz_from_xml(
            $courseid,
            $sectionnum,
            $quizname ?? $quiz->name,
            $categoryname,
            $xmlfile,
            $userid
        );

        return [
            'status' => 'success',
            'quizid' => $result['quizid'],
            'cmid' => $result['cmid'],
            'categoryid' => $result['categoryid'],
            'questioncount' => $result['questioncount']
        ];
    }


    public static function update_quiz_from_xml_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status of the operation (success or failure)'),
            'quizid' => new external_value(PARAM_INT, 'ID of the newly created quiz'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the new quiz'),
            'categoryid' => new external_value(PARAM_INT, 'ID of the new question category'),
            'questioncount' => new external_value(PARAM_INT, 'Number of questions imported into the quiz')
        ]);
    }

    /**
     * Parameter definition: We expect a Quiz ID
     */
    public static function fetch_quiz_questions_parameters() {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'The ID of the quiz instance (not CMID)')
        ]);
    }

    /**
     * The Logic: Fetch questions based on Moodle 4.0+ structure
     */
    public static function fetch_quiz_questions($quizid) {
        global $DB;

        // Validation
        self::validate_parameters(self::fetch_quiz_questions_parameters(), ['quizid' => $quizid]);

        // 1. Get Quiz Slots to find Category IDs
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot');

        if (!$slots) {
            throw new moodle_exception('noslots', 'mod_quiz');
        }

        $category_ids = [];

        // 2. Loop through slots to find the "Random Question" references
        foreach ($slots as $slot) {
            // Check for Random Question Reference
            $set_reference = $DB->get_record('question_set_references', [
                'itemid' => $slot->id,
                'component' => 'mod_quiz',
                'questionarea' => 'slot'
            ]);

            if ($set_reference) {
                $filter = json_decode($set_reference->filtercondition);
                if (isset($filter->filter->category->values[0])) {
                    $category_ids[] = $filter->filter->category->values[0];
                }
            }
        }

        $category_ids = array_unique($category_ids);
        
        if (empty($category_ids)) {
            return ['status' => 'error', 'message' => 'No random question categories found.', 'questions' => []];
        }

        // 3. Fetch Questions and Answers
        list($insql, $inparams) = $DB->get_in_or_equal($category_ids);

        // SQL: We put qa.id FIRST to ensure we get every single answer row
        $sql = "SELECT 
                    qa.id AS distinct_key, 
                    q.id AS question_id,
                    q.name AS question_name,
                    q.questiontext,
                    qa.id AS answer_id,
                    qa.answer,
                    qa.fraction,
                    COALESCE(qmo.shuffleanswers, 0) AS shuffleanswers
                FROM {question_bank_entries} qbe
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                JOIN {question} q ON q.id = qv.questionid
                JOIN {question_answers} qa ON qa.question = q.id
                LEFT JOIN {qtype_multichoice_options} qmo ON qmo.questionid = q.id
                WHERE qbe.questioncategoryid $insql
                  AND qv.version = (
                      SELECT MAX(v.version) 
                      FROM {question_versions} v 
                      WHERE v.questionbankentryid = qbe.id
                  )
                ORDER BY q.id, qa.id";

        $records = $DB->get_records_sql($sql, $inparams);

        // 4. Grouping Logic
        $questions_map = [];
        
        foreach ($records as $r) {
            $qid = $r->question_id;

            // Create question object if not exists
            if (!isset($questions_map[$qid])) {
                $questions_map[$qid] = [
                    'id' => $qid,
                    'name' => $r->question_name,
                    'questiontext' => $r->questiontext, 
                    'options' => [],
                    'correct_answer_text' => '', // Placeholder
                    'shuffleanswers' => (int)$r->shuffleanswers
                ];
            }

            // Determine if this specific option is correct (1.0 = 100%)
            $is_correct = ($r->fraction > 0.99); 

            // Add to options list
            $questions_map[$qid]['options'][] = [
                'id' => $r->answer_id,
                'text' => $r->answer,
                'is_correct' => $is_correct
            ];

            // If this is the correct answer, save it to the main question object for easy reading
            if ($is_correct) {
                $questions_map[$qid]['correct_answer_text'] = $r->answer;
            }
        }

        return [
            'status' => 'success',
            'message' => 'Questions retrieved successfully',
            'questions' => array_values($questions_map)
        ];
    }

    /**
     * Return structure definition
     */
    public static function fetch_quiz_questions_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Question ID'),
                    'name' => new external_value(PARAM_TEXT, 'Question Name'),
                    'questiontext' => new external_value(PARAM_RAW, 'Question Text'),
                    'correct_answer_text' => new external_value(PARAM_RAW, 'The correct answer text for quick reference'),
                    'shuffleanswers' => new external_value(PARAM_INT, 'Whether to shuffle answers (1=yes, 0=no)'),
                    'options' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Answer ID'),
                            'text' => new external_value(PARAM_RAW, 'Answer Text'),
                            'is_correct' => new external_value(PARAM_BOOL, 'Is this the correct answer?')
                        ])
                    )
                ])
            )
        ]);
    }
    
    

}                                                                                                                                  