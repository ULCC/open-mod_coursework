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
 * Page that prints a table of all students and all markers so that first marker, second marker, moderators
 * etc can be allocated manually or automatically.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\models;


use coding_exception;
use context_course;

use gradingform_controller;
use html_writer;
use mod_coursework\framework\table_base;
use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\allocation\auto_allocator;
use mod_coursework\allocation\manager;
use mod_coursework\allocation\strategy\base as allocation_strategy_base;
use mod_coursework\decorators\coursework_groups_decorator;
use mod_coursework\grade_judge;
use mod_coursework\grading_report;
use mod_coursework\render_helpers\grading_report\cells\first_name_cell;
use mod_coursework\render_helpers\grading_report\cells\grade_for_gradebook_cell;
use mod_coursework\render_helpers\grading_report\cells\last_name_cell;
use mod_coursework\render_helpers\grading_report\cells\email_cell;
use mod_coursework\render_helpers\grading_report\cells\idnumber_cell;
use mod_coursework\render_helpers\grading_report\cells\moderation_agreement_cell;
use mod_coursework\render_helpers\grading_report\cells\single_assessor_feedback_cell;
use mod_coursework\render_helpers\grading_report\cells\filename_hash_cell;
use mod_coursework\render_helpers\grading_report\cells\group_cell;
use mod_coursework\render_helpers\grading_report\cells\moderation_cell;
use mod_coursework\render_helpers\grading_report\cells\multiple_agreed_grade_cell;
use mod_coursework\render_helpers\grading_report\cells\plagiarism_cell;
use mod_coursework\render_helpers\grading_report\cells\plagiarism_flag_cell;
use mod_coursework\render_helpers\grading_report\cells\status_cell;
use mod_coursework\render_helpers\grading_report\cells\submission_cell;
use mod_coursework\render_helpers\grading_report\cells\time_submitted_cell;
use mod_coursework\render_helpers\grading_report\cells\user_cell;
use mod_coursework\render_helpers\grading_report\sub_rows\multi_marker_feedback_sub_rows;
use mod_coursework\render_helpers\grading_report\cells\personal_deadline_cell;
use mod_coursework\stages\assessor;
use mod_coursework\stages\final_agreed;
use mod_coursework\stages\base as stage_base;
use mod_coursework\stages\moderator;
use moodle_exception;
use mod_coursework\render_helpers\grading_report\sub_rows\no_sub_rows;
use stored_file;
use mod_coursework\plagiarism_helpers\base as plagiarism_base;
use mod_coursework\export;
use gradingform_rubric_instance;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/grade/grading/lib.php');

/**
 * Class representing a coursework instance.
 *
 * @property int grouping_id
 * @property int use_groups
 * @property int allowearlyfinalisation
 * @property mixed startdate
 * @author administrator
 */
class coursework extends table_base {

    /**
     * @var string
     */
    protected static $table_name = 'coursework';

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $formid;

    /**
     * @var int This is the courseid, but is called course to match the DB column. By convention, all Moodle
     * modules have a column called 'course' that holds the course id and other things will break if we change that.
     * We are using the coursework object as a saveable DB row, dso this has to stay the same. Do not access this
     * directly - use get_course_id() and set_course_id() to make the code easier to read.
     */
    public $course;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $intro;

    /**
     * @var int
     */
    public $introformat;

    /**
     * @var int unix timestamp
     */
    public $timecreated;

    /**
     * @var int unix timestamp
     */
    public $timemodified;

    /**
     * @var int|string
     */
    public $grade;

    /**
     * @var int unix timestamp
     */
    public $deadline;

    /**
     * @var int
     */
    public $numberofmarkers;

    /**
     * @var int
     */
    public $finalstagegrading;

    /**
     * @var int 0 or 1
     */
    public $blindmarking;

    /**
     * @var int
     */
    public $maxbytes;

    /**
     * @var string
     */
    public $generalfeedback;

    /**
     * @var string
     */
    public $individualfeedback;

    /**
     * @var string
     */
    public $feedbackcomment;

    /**
     * @var int
     */
    public $feedbackcommentformat;

    /**
     * @var int unix timestamp
     */
    public $generalfeedbacktimepublished;

    /**
     * @var int 0 or 1
     */
    public $allowlatesubmissions;

    /**
     * @var int 0 or 1
     */
    public $enablegeneralfeedback;

    /**
     * @var string uses the constants in this class for undergraduate and postgraduate.
     */
    public $courseworktype;

    /**
     * @var int 0 or 1
     */
    public $moderationenabled;

    /**
     * @var int 0 or 1
     */
    public $allocationenabled;

    /**
     * @var string
     */
    public $moderatorallocationstrategy;

    /**
     * @var array
     */
    public $submissions;

    /**
     * @var array hold all the feedbacks for this coursework so that we can have them cached
     */
    public $feedbacks;

    /**
     * @var
     */
    public $filescache;

    /**
     * @var array
     */
    protected $stages = array();

    /**
     * @var \context Instance of a moodle context, i.e. that of the coursemodule for this coursework.
     */
    private $context;

    /**
     * @var \stdClass
     */
    public $student;

    /**
     * @var $coursework_submission submission
     */
    public $coursework_submission;

    /**
     * @var \stdClass
     */
    public $coursemodule;

    /**
     * @var int
     */
    public $number_of_participants;

    /**
     * @var string name of the class used to manage allocations
     */
    public $assessorallocationstrategy;

    /**
     * @var \mod_coursework\allocation\manager
     */
    private $allocationmanager;

    /**
     * If set to 1, the feedback will be available to students as soon as the deadline passes, even if it's not published.
     *
     * @var int
     */
    public $autoreleasefeedback;

    /**
     * @var int 1 or 0
     */
    public $studentviewcomponentfeedbacks;

    /**
     * @var int 1 or 0
     */
    public $studentviewmoderatorfeedbacks;

    /**
     * @var int 1 or 0
     */
    public $showallfeedbacks;

    /**
     * @var 1 or 0
     */
    public $preventlatesubmissions;

    /**
     * @var int 1 or 0
     */
    public $studentviewfinalfeedback;

    /**
     * @var int 1 or 0
     */
    public $studentviewcomponentgrades;

    /**
     * @var int 1 or 0
     */
    public $studentviewfinalgrade;

    /**
     * @var int 1 or 0
     */
    public $studentviewmoderatorgrade;

    /**
     * Defines the prefix used for form names and DB tables and fields so we can dynamically use allocation
     * strategies for more purposes.
     */
    const ASSESSOR = 'assessor';

    /**
     * Defines the prefix used for form names and DB tables and fields so we can dynamically use allocation
     * strategies for more purposes.
     */
    const MODERATOR = 'moderator';

    /**
     * @var int
     */
    public $maxfiles;

    /**
     * @var int
     */
    public $renamefiles;

    /**
     * @var string
     */
    public $filetypes;

    /**
     * @var \stdClass We use this because course in the module tables is always a course id integer and the active record pattern
     * need it to stay this way.
     */
    protected $courseobject;

    /**
     * @var
     */
    protected $gradebookgrades;

    /**
     * @var int
     */
    public $courseworkid;

    /**
     * @var int
     */
    public $submissionid;

    /**
     * @var int
     */
    public $cmid;

    /**
     * @var int 0 or 1
     */
    public $extensionsenabled;


    public $gradeeditingtime;

    /**
     * @var int
     */
    public $markingdeadlineenabled;


    /**
     * @var int
     */
    public $initialmarkingdeadline;


    /**
     * @var int
     */
    public $agreedgrademarkingdeadline;

    /**
     * @var int 0 or 1
     */
    public $personaldeadlineenabled;

    /**
     * Factory makes it renderable.
     *
     * @param int|\stdClass $db_record
     * @return mixed|\mod_coursework_coursework
     */
    public static function find($db_record, $reload = true) {
        $coursework_object = parent::find($db_record);

        if ($coursework_object && $coursework_object->is_configured_to_have_group_submissions()) {
            $coursework_object = new coursework_groups_decorator($coursework_object);
        }

        return $coursework_object;
    }

    /**
     * Constructor: takes a DB row from the coursework table or an id. We don't always retrieve it
     * first as we may want to overwrite with submitted data or make a new one.
     *
     * @param int|string|\stdClass|null $db_record a row from the DB coursework table or an id
     */
    public function __construct($db_record = null) {

        parent::__construct($db_record);
    }

    /**
     * Gets the relevant course module and caches it.
     *
     * @throws moodle_exception
     * @return mixed|\stdClass
     */
    public function get_course_module() {

        global $DB;

        if (!isset($this->coursemodule)) {
            if (empty($this->id)) {
                throw new moodle_exception('Trying to get course module for a coursework that has not yet been saved');
            }
            $module_record = module::$pool['name']['coursework'] ?? $DB->get_record('modules', ['name' => 'coursework']);
            $module_id = $module_record->id;
            $course_id = $this->get_course_id();
            if (!isset(course_module::$pool['course-module-instance'][$course_id][$module_id][$this->id]->id)) {
                $this->coursemodule = course_module::$pool['course-module-instance'][$course_id][$module_id][$this->id] =
                    $DB->get_record('course_modules', ['course' => $course_id, 'module' => $module_id, 'instance' => $this->id], '*', MUST_EXIST);
            }
            $this->coursemodule = course_module::$pool['course-module-instance'][$course_id][$module_id][$this->id];
        }

        return $this->coursemodule;
    }

    public function cm_object($coursemodule){


        $modinfo = get_fast_modinfo($this->get_course_id());
        return $this->coursemodule = $modinfo->get_cm($coursemodule->id);

    }

    /**
     * @return bool
     */
    public function allow_late_submissions() {
        return (bool)$this->allowlatesubmissions;
    }

    /**
     * Returns the id of the associated coursemodule, if there is one. Otherwise false.
     *
     * @return int
     */
    public function get_coursemodule_id() {
        $coursemodule = $this->get_course_module();
        return (int)$coursemodule->id;
    }

    /**
     * Returns the idnumber of the associated coursemodule, if there is one. Otherwise false.
     *
     * @return int
     */
    public function get_coursemodule_idnumber() {
        $coursemodule = $this->get_course_module();
        return (int)$coursemodule->idnumber;
    }


    /**
     * Getter function for the coursework's course object.
     *
     * @global \moodle_database $DB
     * @return object
     */
    public function get_course() {
        return get_course($this->get_course_id());
    }

    /**
     * Getter function for the coursework's course object.
     *
     * @global \moodle_database $DB
     * @return \context
     */
    public function get_course_context() {
        return context_course::instance($this->get_course_id());
    }

    /**
     * Getter function for the course id. Avoids confusion because it needs to be called course (see docs for
     * that property).
     *
     * @return int
     */
    public function get_course_id() {
        return (int)$this->course;
    }

    /**
     * Setter function for the course id. Avoids confusion because it needs to be called course (see docs for
     * that property).
     *
     * @param $courseid
     * @return int
     */
    public function set_course_id($courseid) {
        $this->course = $courseid;
    }

    /**
     * Gets all the feedbacks for this coursework as DB rows.
     *
     * @global \moodle_database $DB
     * @internal param array $userids visible users (paged results) only
     * @return array
     */
    public function get_all_raw_feedbacks() {
        feedback::fill_pool_coursework($this->id);
        $feedbacks = feedback::$pool[$this->id]['id'];

        return $feedbacks;
    }

    /**
     * @param $can_grade bool
     * @return int number of ungraded assessments, 0
     */
    public function get_ungraded_assessments_number($can_grade) {
        global $USER, $DB;
        // Is this a teacher? If so, show the number of bits of work they need to mark.
        if (!$can_grade) {
            return 0;
        }
        // Count submitted work that this person has not graded.
        submission::fill_pool_coursework($this->id);
        feedback::fill_pool_coursework($this->id);
        $submissions = submission::$pool[$this->id]['id'];
        $count = 0;
        foreach ($submissions as $s) {
            $feedback = feedback::get_object($this->id, 'submissionid-assessorid', [$s->id, $USER->id]);
            if (empty($feedback)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Getter for DB deadline field.
     *
     * @return mixed
     */
    public function get_deadline() {
        return $this->deadline;
    }

    /**
     * Getter for DB deadline field.
     *
     * @return mixed
     */
    public function deadline_has_passed() {
        return ($this->has_deadline() && $this->deadline < time());
    }

    public function has_deadline()  {
        return !empty($this->deadline);
    }

    /**
     * Gets all the submissions for this coursework, as raw DB rows. This is a more efficient way, compared to
     * multiple calls to the DB, once for every row. Later functions will filter many of these out due to permissions
     * issues.
     *
     * @param array $groups array of group ids
     * @global \moodle_database $DB
     * @internal param int $page
     * @internal param int $perpage
     * @internal param string $sortby
     * @internal param string $sorthow
     * @return \stdClass[] array of objects
     */
    public function get_participants($groups = array(0)) {

        if (is_array($this->submissions)) {
            return $this->submissions;
        }

        // Fetch the list of ids of all participants - this may get really long so fetch just id.
        $groups = (array)$groups;
        $allusers = array();
        foreach ($groups as $groupdid) {
            $allusers = array_merge($allusers, get_enrolled_users($this->get_context(), 'mod/coursework:submit', $groupdid, 'u.id'));
        }

        return $allusers;

    }

    /**
     * Find out how many markers there should be.
     *
     * @return int Cast to int so we can do strict comparisons elsewhere
     */
    public function get_max_markers() {
        return (int)$this->numberofmarkers;
    }

    /**
     * Returns the context object for this coursework.
     *
     * @return \context
     */
    public function get_context() {

        if (empty($this->context)) {
            $this->context = \context_module::instance($this->get_coursemodule_id());
        }
        return $this->context;
    }

    /**
     * Returns the context object for this coursework.
     *
     * @return \int
     */
    public function get_context_id() {

        return $this->get_context()->id;
    }


    function get_grade_editing_time()   {
        return $this->gradeeditingtime;
    }

    /**
     * Returns the initial marking deadline timestamp
     *
     * @return int
     */
    function get_initial_marking_deadline() {
        return  (!empty($this->initialmarkingdeadline))     ?   $this->initialmarkingdeadline : 0;
    }


    /**
     * Returns the initial marking deadline timestamp
     *
     * @return int
     */
    function get_agreed_grade_marking_deadline() {
          return  (!empty($this->agreedgrademarkingdeadline))     ?   $this->agreedgrademarkingdeadline : 0;
    }

    /**
     * Returns array of file storage options to be used across the whole coursework
     *
     * @return array
     */
    public function get_file_options() {

        global $CFG, $DB;

        require_once($CFG->dirroot. '/repository/lib.php');

        $turnitinenabled = $this->tii_enabled();

        // Turn it in only allows one file.
        $max_files =  $this->maxfiles;

        // Turn it in only likes some file types.
        /* DOC, DOCX, Corel
         * WordPerfect, HTML, Adobe
         * PostScript, TXT, RTF, and PDF
         */
        if ($turnitinenabled) {
            $file_types = array('.doc',
                                '.docx',
                                '.txt',
                                '.html',
                                '.eps',
                                '.pdf',
                                '.htm',
                                '.rtf');
        } else if (!empty($this->filetypes)) {
            $file_types = preg_split('/,+\s?|\s+/', $this->filetypes);
            foreach ($file_types as $key => $filetype) {
                $filetype = trim($filetype); // Remove whitespace
                if (empty($filetype)) {
                    unset($file_types[$key]);
                    continue;
                }
                if (strpos($filetype, '.') === false) {
                    $filetype = '.' .$filetype; // Add dot if not there
                } else {
                    $filetype = strrchr($filetype, '.'); // Remove leading characters e.g. *.doc => .doc
                }
                $file_types[$key] = $filetype;
            }
        } else {
            $file_types = '*';
        }

        return array(
            'subdirs' => false,
            'maxbytes' => $this->get_max_bytes(),
            'maxfiles' => $max_files,
            'accepted_types' => $file_types,
            'return_types' => FILE_INTERNAL
        );
    }

    /**
     * Finds out how large an uploaded file is allowed to be, returning coursemodule, course or site
     * defaults in that order of preference.
     *
     * @return int
     */
    public function get_max_bytes() {
        global $CFG;

        if (!empty($this->maxbytes)) {
            return $this->maxbytes;
        } else {
            $course = $this->get_course();
            if (!empty($course->maxbytes)) {
                return $this->maxbytes = $course->maxbytes;
            } else {
                return $this->maxbytes = $CFG->maxbytes;
            }
        }
    }

    /**
     * This will fetch and cache all the grades form the gradebook for this coursework.
     *
     * @return bool
     */
    public function get_grading_info() {

        if (!is_null($this->gradebookgrades)) {
            return $this->gradebookgrades;
        }
        return false;
        // Get them for this whole coursework.
    }

    /**
     * If not set for this coursework, use the one from the site settings.
     *
     * @return int timestamp
     */
    public function get_general_feedback_deadline() {

        global $CFG;

        if ($this->generalfeedback > 0) {
            return $this->generalfeedback;
        } else {
            if ($CFG->coursework_generalfeedback) {
                return strtotime('+ '.$CFG->coursework_generalfeedback.' weeks', $this->deadline);
            } else { // If site setting is 0.
                return strtotime('+ 2 weeks', $this->deadline);
            }
        }
    }

    /**
     * If not set for this coursework, use the one from the site settings.
     *
     * @return int timestamp
     */
    public function get_individual_feedback_deadline() {

        if ($this->individualfeedback > 0) {
            return $this->individualfeedback;
        } else {
            // This was previously set to default to two weeks if not set, but this conflicts with the requirement to
            // sometimes allow the feedback to be released whenever it is provided.
            return $this->deadline;
        }
    }

    /**
     * @param allocatable $allocatable
     * @return string
     */
    public function get_allocatable_identifier_hash($allocatable) {
        if ($allocatable->type() == 'user' && $this->blindmarking) {
            return $this->get_username_hash($allocatable->id());
        }
        return $allocatable->id();
    }

    /**
     * @return plagiarism_base[]
     */
    public function get_plagiarism_helpers() {
        $enabled_plagiarism_plugins = array_keys(\core_component::get_plugin_list('plagiarism'));
        $objects = array();
        foreach ($enabled_plagiarism_plugins as $plugin_name) {
            $class_name = "\\mod_coursework\\plagiarism_helpers\\{$plugin_name}";
            if (class_exists($class_name)) {
                /**
                 * @var plagiarism_base $helper
                 */
                $helper = new $class_name($this);
                if ($helper->enabled()) {
                    $objects[] = $helper;
                }
            }
        }
        return $objects;
    }

    /**
     * @param user $user
     * @return bool
     */
    public function is_assessor($user) {
        foreach ($this->marking_stages() as $stage) {
            if ($stage->user_is_assessor($user)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function plagiarism_enbled() {
        $plagiarism_helpers = $this->get_plagiarism_helpers();
        foreach ($plagiarism_helpers as $helper) {
            if ($helper->enabled()) {
                return true;
            }
        }
        return false;
    }


    /**
     * @return bool
     */
    public function plagiarism_flagging_enbled() {
        return (bool)$this->plagiarismflagenabled;
    }

    /**
     * @return bool
     */
    public function early_finalisation_allowed() {
        return !!$this->allowearlyfinalisation;
    }


    /**
     * Pushes all grades form the coursework into the gradebook. Will overwrite any older grades.
     *
     * @global \moodle_database $DB
     * @return void
     */
    public function publish_grades() {

        $submisisons = $this->get_submissions_to_publish();

        foreach ($submisisons as $submisison) {
            $submisison->publish();
        }

    }

    /**
     * Gets a student record based on a supplied submission id and adds user details to this object.
     *
     * @param int $submissionid
     * @return bool|mixed
     */
    public function set_coursework_submission_student($submissionid) {
        global $DB;

        if (!$submissionid) {
            return false;
        }

        $sql = "SELECT u.id, u.id AS userid, u.firstname, u.lastname
                  FROM {user} u
            INNER JOIN {coursework_submissions} s
                    ON u.id = s.userid
                 WHERE s.id = :eid
                    ";
        return ($this->student = $DB->get_record_sql($sql, array('eid' => $submissionid)));
    }

    /**
     * @static
     * @param $params
     * @return array
     */
    public static function get_view_params($params) {
        if (optional_param('page', 0, PARAM_INT) > 0) {
            $params['page'] = optional_param('page', 0, PARAM_INT);
        }
        if (optional_param('sortby', 0, PARAM_TEXT) !== '') {
            $params['sortby'] = optional_param('sortby', '', PARAM_TEXT);
        }
        if (optional_param('sorthow', 0, PARAM_TEXT) !== '') {
            $params['sorthow'] = optional_param('sorthow', '', PARAM_TEXT);
        }
        return $params;
    }

    /**
     * Generate zip file from array of given files
     *
     * @internal param int $context_id
     * @return bool | string path of temp file - note this returned file does not have a .zip
     * extension - it is a temp file.
     */
    public function pack_files() {

        global $CFG, $DB, $USER;

        $context = \context_module::instance($this->get_coursemodule_id());
        $ability = new ability(user::find($USER), $this);

        $submissions = $DB->get_records('coursework_submissions',
                                        array('courseworkid' => $this->id, 'finalised'=>1));
        if (!$submissions) {
            return false;
        }
        $files_for_zipping = array();
        $fs = get_file_storage();

        $grading_sheet = new \mod_coursework\export\grading_sheet($this, null, null);
       // get only submissions that user can grade
        $submissions = $grading_sheet->get_submissions();

        foreach ($submissions as $submission) {

            // If allocations are in use, then we don't supply files that are not allocated.
            $submission = submission::find($submission);


            $files = $fs->get_area_files($context->id, 'mod_coursework', 'submission',
                                         $submission->id, "id", false);
            foreach ($files as $f) {

                $filename = basename($f->get_filename());

                $foldername = '';

                if($this->blindmarking == 0 || has_capability('mod/coursework:viewanonymous',$this->get_context())) {
                    $submissionuser = $submission->get_allocatable();
                    if ($this->is_configured_to_have_group_submissions() && $submissionuser->name) {
                        $foldername = $submissionuser->name . '_';
                    } elseif(!$this->is_configured_to_have_group_submissions()) {
                        $foldername = $submissionuser->firstname . ' ' . $submissionuser->lastname . '_';
                    }
                }

                $foldername     .=   $this->get_username_hash($submission->get_allocatable()->id());

                $filename           =   $foldername.'/'.$filename;

                /* @var $f stored_file */
                $files_for_zipping[$filename] = $f;
            }
        }



        // Create path for new zip file.
        $temp_zip = tempnam($CFG->dataroot.'/temp/', 'ocm_');
        // Zip files.
        $zipper = new \zip_packer();
        if ($zipper->archive_to_pathname($files_for_zipping, $temp_zip)) {
            return $temp_zip;
        }
        return false;
    }





    /**
     * Makes code cleaner than writing MATHS all over the place!!
     * @return bool
     */
    public function has_multiple_markers() {
        return $this->numberofmarkers > 1;
    }

    /**
     * Returns the maximum grade a student can achieve.
     *
     * @return int
     */
    public function get_max_grade() {
        return $this->grade;
    }

    /**
     * We must make sure that random class names are not passed in and then instantiated. This checks
     * that the file is there and that the class it contains is a subclass of
     * coursework_allocation_strategy like it needs to be.
     *
     * @param string $allocationstrategy the class name without the namespace
     * @return bool
     */
    public function validate_and_set_allocation_strategy($allocationstrategy) {

        if ($this->allocation_strategy_is_valid($allocationstrategy)) {
            $this->assessorallocationstrategy = $allocationstrategy;
            return $this->save();
        }
        return false;
    }

    /**
     * @param string $strategy_name
     * @return bool|void
     */
    public function set_assessor_allocation_strategy($strategy_name) {

        if ($this->allocation_strategy_is_valid($strategy_name)) {
            return $this->update_attribute('assessorallocationstrategy', $strategy_name);
        }
        return false;
    }

    /**
     * @param string $strategy_name
     * @return bool|void
     */
    public function set_moderator_allocation_strategy($strategy_name) {

        if ($this->allocation_strategy_is_valid($strategy_name)) {
            return $this->update_attribute('moderatorallocationstrategy', $strategy_name);
        }
        return false;
    }

    /**
     * Tests that the name of the supplied strategy is actually a real allocation strategy class.
     *
     * @param string $strategy_name
     * @return bool
     */
    protected function allocation_strategy_is_valid($strategy_name) {
        $classname = '\mod_coursework\allocation\strategy\\' . $strategy_name;
        if (class_exists($classname)) {
            $class = new $classname($this);
            if ($class instanceof allocation_strategy_base) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ensures we only have one. Sort-of singleton pattern.
     *
     * @throws coding_exception
     * @return \mod_coursework\allocation\manager
     */
    public function get_allocation_manager() {

        if (!isset($this->allocationmanager)) {
            $this->allocationmanager = new manager($this);
        }

        if (!($this->allocationmanager instanceof manager)) {
            throw new coding_exception('Cannot instantiate allocation manager');
        }

        return $this->allocationmanager;
    }

    /**
     * Returns the user that was supplied if groups are not enabled, or the correct group
     * if groups are enabled.
     *
     * @param user $user
     * @return allocatable
     */
    public function submiting_allocatable_for_student($user) {
        if ($this->is_configured_to_have_group_submissions()) {
            return $this->get_student_group($user);
        } else {
            return $user;
        }
    }

    /**
     * Tells us if moderators are being asked to remark this assessment.
     *
     * @return bool
     */
    public function moderation_enabled() {
        return (bool)$this->moderationenabled;
    }

    /**
     * Lets us know if the strategy in use uses allocations at all.
     * @return bool
     */
    public function allocation_enabled() {
        return (bool)$this->allocationenabled;
    }

    /**
     * Lets us know if the moderations agreement has been enabled.
     * @return bool
     */
    public function moderation_agreement_enabled() {
        return (bool)$this->moderationagreementenabled;
    }

    /**
     * Will delegate the save operation to the strategy class.
     *
     * @param string $short_name
     * @return void
     */
    public function save_allocation_strategy_options($short_name) {

        $classname = '\mod_coursework\allocation\strategy\\'.$short_name;

        /* @var \mod_coursework\allocation\strategy\base $strategy */
        $strategy = new $classname($this);

        $strategy->save_allocation_strategy_options();
    }

    /**
     * Tells us the deadline for a specific user.
     *
     * @param int $userid
     * @return int
     */
    public function get_user_deadline($userid) {

        return $this->deadline;

    }

    /**
     * Is there any submission that has not been moderated, but which should have been? This is used to prevent publishing if more
     * work needs moderating.
     *
     */
    public function unmoderated_work_exists() {

        global $DB;

        $sql = "
            SELECT 1
              FROM {coursework_submissions} s
             WHERE NOT EXISTS (SELECT 1
                                 FROM {coursework_feedbacks} f
                                WHERE s.id = f.submissionid
                                  AND f.ismoderation = 1)
               AND s.courseworkid = :courseworkid
        ";

        // If moderator allocations are in use, we want to allow submissions without moderations if they
        // have no allocation.
        $rules = $this->get_allocation_manager()->get_moderation_set_rules();
        if (!empty($rules)) {
            $sql .= " AND EXISTS (SELECT 1 FROM {coursework_allocation_pairs} p
                                         WHERE s.userid = p.studentid
                                           AND p.moderator = 1
                                           AND p.courseworkid = s.courseworkid)";
        }
        $params = array(
            'courseworkid' => $this->id
        );

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Checks whether the current user is an assessor allocated to mark this submission.
     *
     * @param allocatable $allocatable
     * @return bool
     */
    public function assessor_has_any_allocation_for_student($allocatable, $userid=false) {

        global $DB, $USER;

        if (!$userid){
            $userid = $USER->id;
        }
        $params = array(
            'courseworkid' => $this->id,
            'assessorid' => $userid,
            'allocatableid' => $allocatable->id(),
            'allocatabletype' => $allocatable->type(),
        );

        return $DB->record_exists('coursework_allocation_pairs', $params);
    }


    /**
     * Check if current assessor is not already allocated for this submission in different stage
     *
     * @param allocatable $allocatable
     * @return bool
     */
    public function assessor_has_allocation_for_student_not_in_current_stage($allocatable, $userid, $stage) {

        global $USER;
        if (!$userid) {
            $userid = $USER->id;
        }
        allocation::fill_pool_coursework($this->id);
        $records = isset(allocation::$pool[$this->id]['allocatableid-allocatabletype-assessorid'][$allocatable->id() . '-' . $allocatable->type() . "-$userid"]) ?
            allocation::$pool[$this->id]['allocatableid-allocatabletype-assessorid'][$allocatable->id() . '-' . $allocatable->type() . "-$userid"] : [];

        foreach ($records as $record) {
            if ($record->stage_identifier != $stage) {
                return true;
            }
        }

        return false;
    }


    /**
     * Checks whether the current user is an assessor allocated to mark this submission.
     *
     * @param allocatable $allocatable
     * @return bool
     */
    public function current_user_is_moderator_for_student($allocatable) {

        global $DB, $USER;

        $params = array(
            'courseworkid' => $this->id,
            'assessorid' => $USER->id,
            'stage_identifier' => 'moderator_1',
            'allocatableid' => $allocatable->id(),
            'allocatabletype' => $allocatable->type(),
        );

        return $DB->record_exists('coursework_allocation_pairs', $params);
    }

    /**
     * Gets all the submissions at once for the grading table.
     *
     * @return submission[]
     */
    public function get_all_submissions() {
        submission::fill_pool_coursework($this->id);
        $submissions = submission::$pool[$this->id]['id'];

        return $submissions;
    }

    /**
     * Get submissions that need grading, either in initial or final stage
     * For multiple marker coursework if final grade is not given it is assumed that submission may need grading in
     * either initial, final or both stages
     *
     * @throws \dml_exception
     * @throws \dml_missing_record_exception
     * @throws \dml_multiple_records_exception
     */
    public function get_submissions_needing_grading(){

        $needsgrading = array();
        $submissions = $this->get_finalised_submissions();

        foreach ($submissions as $submission) {

            $stage_identifier = ($this->has_multiple_markers())? 'final_agreed_1': 'assessor_1';
            $submission = submission::find($submission);
            if (!$feedback = $submission->get_assessor_feedback_by_stage($stage_identifier)){
                $needsgrading[] = $submission;
            }

         }


        return $needsgrading;

    }

    /**
     * Get all graded submissions for the specified marking stage
     *
     * @param $stage_identifier
     * @return array
     * @throws \dml_missing_record_exception
     * @throws \dml_multiple_records_exception
     */
    public function get_graded_submissions_by_stage($stage_identifier){

        $graded = array();
        $submissions = $this->get_finalised_submissions();

        foreach ($submissions as $submission) {
            $submission = submission::find($submission);
            if ($feedback = $submission->get_assessor_feedback_by_stage($stage_identifier)){
                $graded[$submission->id] = $submission;
            }
        }
        return $graded;
    }

    /**
     * Function to get all assessor's graded submissions within the specified coursework
     *
     * @param $assessorid
     * @return array
     * @throws \dml_exception
     */
    public function get_assessor_graded_submissions($assessorid){
        global $DB;

        $graded = array();
        $params = array('courseworkid' => $this->id, 'assessorid' => $assessorid);
        $sql = "SELECT cs.id
                FROM {coursework_feedbacks} cf 
                JOIN {coursework_submissions} cs 
                ON cs.id = cf.submissionid
                WHERE cs.courseworkid = :courseworkid 
                AND assessorid = :assessorid";
        $submissions =  $DB->get_records_sql($sql, $params);

        foreach ($submissions as $submission) {
            $submission = submission::find($submission);
            $graded[$submission->id] = $submission;
        }

        return $graded;
    }


    /**
     * Get all published submissions in the coursework
     *
     * @return array
     * @throws \dml_exception
     */
    public function get_published_submissions(){
        global $DB;

        $sql = "SELECT *
                FROM {coursework_submissions}
                WHERE courseworkid = :courseworkid
                AND firstpublished IS NOT NULL";

      $submissions =  $DB->get_records_sql($sql, array('courseworkid' => $this->id));
        foreach ($submissions as &$submission) {
            $submission = submission::find($submission);
        }
        return $submissions;
    }


    /**
     * Returns a hash of this user's id. Not username as this would be non-unique across
     * different courseworks, compromising anonymity.
     *
     * TODO: Fix broken hash in a backward compatible way.
     *       Should be "$id|userid" or similar
     * TODO: Fix all calls to prefix "aa" or something to keep Excel happy
     * @param int $userid
     * @return string
     */
    static function get_name_hash($id,$userid,$time=1440000609)
    {
        if ($id < 1) {
            return '';
        }

        $uhash  =   $id . $userid;

        //hash with zero have the potential to become changed in outside programs
        //so we generate a hash without a leading zero
        $uhash = substr(md5($uhash), 0, 8);
        $uhash = 'X' . $uhash;


        return $uhash;
    }

    public function get_username_hash($userid)
    {
        return static::get_name_hash($this->id,$userid,$this->timecreated);
    }

    /**
     * Returns the allocation record if there is one.
     *
     * @param int $studentid
     * @return bool|allocation
     */
    public function get_moderator_allocation($studentid) {

        global $DB;

        $params = array(
            'courseworkid' => $this->id,
            'studentid' => $studentid,
            'moderator' => 1
        );
        $moderatorallocation = $DB->get_record('coursework_allocation_pairs', $params);
        if ($moderatorallocation) {
            $moderatorallocation = new allocation($moderatorallocation, $this);
        }

        return $moderatorallocation;
    }

    /**
     * Tells us whether this submission is set to be moderated by looking for an allocation.
     *
     * @param int $studentid
     * @return bool
     */
    public function moderator_is_allocated($studentid) {
        return (bool)$this->get_moderator_allocation($studentid);
    }

    /**
     * Fetches the allocations for this submission so we know who is to mark it.
     *
     * @param int $student_id
     * @return allocation $allocation
     */
    public function get_assessor_allocation($allocatable, $stage_identifier) {
        global $DB;

        $params = array(
            'courseworkid' => $this->id,
            'allocatableid' => $allocatable->allocatableid,
            'stage_identifier'=>$stage_identifier,
            'allocatabletype'=> $allocatable->allocatabletype
        );
        $allocation = $DB->get_record('coursework_allocation_pairs', $params);

        return $allocation;
    }


    public function get_assessors_stage_identifier($allocatable_id, $assessor_id){
        global $DB;

        $params = array(
            'courseworkid' => $this->id,
            'assessorid' => $assessor_id,
            'allocatableid' => $allocatable_id
        );

        $stage_identifier = $DB->get_record('coursework_allocation_pairs', $params);

        return $stage_identifier->stage_identifier;
    }


    /**
     * Checks settings to see whether the current user (who we assume is a student) can
     * view their feedback.
     *
     * @return bool
     */
    public function student_can_view_individual_feedback() {

        if ($this->has_individual_autorelease_feedback_enabled()) {
            return true;
        }

        $releasedate = $this->get_student_feedback_release_date();
        if ($releasedate < time()) {
            return true;
        }

        return false;

    }

    /**
     * Tells us when a student should be able to see the feedback for their submission.
     *
     * @return int
     */
    public function get_student_feedback_release_date() {

        $normalsubmissiondeadline = $this->deadline;
        $normalfeedbackdeadline = $this->get_individual_feedback_deadline();
        $normaloffset = $normalfeedbackdeadline - $normalsubmissiondeadline;

        return $this->deadline + $normaloffset;
    }

    /**
     * Gives us an array of students who have not yet submitted their work and finalised it so that they can maybe have
     * work submitted on their behalf by others.
     *
     * @param string $fields
     * @return array
     */
    public function get_unfinalised_students($fields = 'u.id, u.firstname, u.lastname') {

        $students = get_enrolled_users(context_course::instance($this->get_course_id()), 'mod/coursework:submit', 0, $fields);
        submission::fill_pool_coursework($this->id);
        $alreadyfinalised = isset(submission::$pool[$this->id]['finalised'][1]) ? submission::$pool[$this->id]['finalised'][1] : [];
        foreach ($alreadyfinalised as $submission) {
            unset ($students[$submission->userid]);
        }

        return $students;
    }

    /**
     * Returns true or false for whether to display the Student ID column.
     *
     */
    public function display_studentid() {

        if ($this->blindmarking) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Tells us if any allocations are being used for moderators, so we can check for an allocation if we need to.
     *
     * @return bool
     */
    public function moderator_allocations_in_use() {
        return !empty($this->moderatorallocationstrategy) && $this->moderatorallocationstrategy !== 'none';
    }

    /**
     * If the grades are saved, then we may have to re-do the moderator allocation set based on the final grade
     * that the student got e.g. if we want to moderate only those who have scored below 30%.
     */
    public function grade_changed_event() {

        global $DB;

        // Are we using graded boundaries for the moderations set?
        $params = array(
            'courseworkid' => $this->id,
        );
        $sql = "
            SELECT id
              FROM {coursework_mod_set_rules} rules
             WHERE courseworkid = :courseworkid
               AND rulename LIKE '%grade%'
        ";
        if ($DB->record_exists_sql($sql, $params)) {

            // Re-do it.
            $manager = $this->get_allocation_manager();
            $manager->auto_generate_moderation_set();

            $allocator = new auto_allocator($this);
            $allocator->process_allocations();
            // Possibly, the moderation set now does not include someone who used to be in it (grade changed up or down).
            // This means we need to wipe them and start again.
        }
    }

    /**
     * Tells us if the coursework is set to allow students to see component feedbacks.
     *
     * @return bool
     */
    public function students_can_view_component_feedbacks() {
        return ($this->studentviewcomponentfeedbacks);
    }

    /**
     * Tells us whether students are allowed to see moderator feedbacks for this coursework.
     *
     * @return bool
     */
    public function students_can_view_moderator_feedbacks() {
        return ($this->studentviewmoderatorfeedbacks);
    }

    /**
     * @return int
     */
    public function students_can_view_all_feedbacks() {
        return $this->showallfeedbacks;
    }

    /**
     * Returns the setting that tells us whether students are allowed to view this. Ignores deadlines, release dates etc.
     *
     * @return int 1 or 0
     */
    public function students_can_view_component_feedback_comments() {
        return $this->studentviewcomponentfeedbacks;
    }

    /**
     * Returns the setting that tells us whether students are allowed to view this. Ignores deadlines, release dates etc.
     *
     * @return int 1 or 0
     */
    public function students_can_view_component_feedback_grades() {
        return $this->studentviewcomponentgrades;
    }

    /**
     * Returns the setting that tells us whether students are allowed to view this. Ignores deadlines, release dates etc.
     *
     * @return int 1 or 0
     */
    public function students_can_view_final_feedback_comments() {
        return $this->studentviewfinalfeedback;
    }

    /**
     * Returns the setting that tells us whether students are allowed to view this. Ignores deadlines, release dates etc.
     *
     * @return int 1 or 0
     */
    public function students_can_view_final_feedback_grades() {
        return $this->studentviewfinalgrade;
    }

    /**
     * Returns the setting that tells us whether students are allowed to view this. Ignores deadlines, release dates etc.
     *
     * @return int 1 or 0
     */
    public function students_can_view_moderator_feedback_comments() {
        return $this->studentviewmoderatorfeedbacks;
    }

    /**
     * Returns the setting that tells us whether students are allowed to view this. Ignores deadlines, release dates etc.
     *
     * @return int 1 or 0
     */
    public function students_can_view_moderator_feedback_grades() {
        return $this->studentviewmoderatorgrade;
    }

    /**
     * Tells us whether the deadline has passed and late submissions are prevented. Specific to the current user,
     * who needs to be a student.
     */
    public function allowed_to_submit() {
        global $USER;
        return (time() < $this->get_user_deadline($USER->id)) || $this->allow_late_submissions();
    }

    /**
     * @param $userid
     * @return bool
     */
    function user_grade_is_published($userid) {
        // Get the gradebook grade.

        /**
         * @var \stdClass $grades
         */
        $grades = grade_get_grades($this->get_course_id(), 'mod', 'coursework', $this->id, $userid);

        if (isset($grades->items[0]->grades[$userid]) &&
            isset($grades->items[0]->grades[$userid]->grade) &&
            $grades->items[0]->grades[$userid]->grade != -1
        ) {

            return true;
        }
        return false;
    }

    /**
     * @param user $student
     * @return group
     * @throws \coding_exception
     */
    public function get_student_group($student) {
        global $DB;

        if (!$this->is_configured_to_have_group_submissions() && $this->assessorallocationstrategy != 'group_assessor') {
            throw new coding_exception('Asking for a student group when groups are disabled.');
        }

        if ($this->grouping_id) {
                $sql = "
                SELECT groups.*
                  FROM {groups} groups
            INNER JOIN {groupings_groups} groupings
                    ON groups.id = groupings.groupid
            INNER JOIN {groups_members} gm
                    ON gm.groupid = groups.id
                 WHERE gm.userid = :userid
                   AND groups.courseid = :courseid
                   AND groupings.groupingid = :grouping_id

                 LIMIT 1
            ";
            $params = array(
                'grouping_id' => $this->grouping_id,
                'courseid' => $this->get_course()->id,
                'userid' => $student->id());
        } else {
            $sql = "
                SELECT groups.*
                  FROM {groups} groups
            INNER JOIN {groups_members} gm
                    ON gm.groupid = groups.id
                 WHERE gm.userid = :userid
                   AND groups.courseid = :courseid
                 LIMIT 1
            ";
            $params = array(
                'userid' => $student->id(),
                'courseid' => $this->get_course()->id,
            );
        }

        $group = $DB->get_record_sql($sql, $params);
        return group::find($group);

    }

    /**
     * @param user|null $user
     * @return \mod_coursework\decorators\submission_groups_decorator|submission
     */
    public function get_user_submission($user) {

        if ($this->is_configured_to_have_group_submissions()) {
            $allocatable = $this->get_student_group($user);
        } else {
            $allocatable = $user;
        }

        return $this->get_allocatable_submission($allocatable);

    }

    /**
     * @param user $user
     * @return bool
     */
    public function has_user_submission($user) {
        return !!$this->get_user_submission($user);
    }

    /**
     * @param allocatable $allocatable
     * @return submission
     */
    public function get_allocatable_submission($allocatable) {

        if (!($allocatable instanceof allocatable)) {
            return false;
        }

        $own_submission_params = array(
            'courseworkid' => $this->id,
            'allocatableid' => $allocatable->id(),
            'allocatabletype' => $allocatable->type(),
        );
        return submission::find($own_submission_params);
    }

    /**
     * @param user $user
     * @return \mod_coursework\framework\table_base
     */
    public function build_own_submission($user) {
        if ($this->is_configured_to_have_group_submissions()) {
            $allocatable = $this->get_student_group($user);
        } else {
            $allocatable = $user;
        }
        $own_submission_params = array(
            'courseworkid' => $this->id,
            'allocatableid' => $allocatable->id(),
            'allocatabletype' => $allocatable->type(),
        );
        return submission::build($own_submission_params);
    }


    /**
     * Uses the knowledge of the Coursework settings to compose the object which the renderer can deal with.
     * This is the messy wiring for the nice, reusable components in the grading report :)
     *
     * @param array $report_options
     * @return grading_report
     */
    public function renderable_grading_report_factory($report_options) {

        // Single or multiple? Compose it, so don't use inheritance.

        $report = new grading_report($report_options, $this);

        $cell_items = array(
            'coursework' => $this,
        );


        // Add the cell objects. These are used to generate the table headers and to render each row.
        if ($this->is_configured_to_have_group_submissions()) {
            $report->add_cell(new group_cell($cell_items));
        } else {
            $report->add_cell(new first_name_cell($cell_items));
            $report->add_cell(new last_name_cell($cell_items));
            $report->add_cell(new email_cell($cell_items));
            $report->add_cell(new user_cell($cell_items));
            $report->add_cell(new idnumber_cell($cell_items));
        }

        if ($this->personal_deadlines_enabled()) {
            $report->add_cell(new personal_deadline_cell($cell_items));
        }
        $report->add_cell(new status_cell($cell_items));
        $report->add_cell(new submission_cell($cell_items));
        $report->add_cell(new time_submitted_cell($cell_items));
        if ($this->plagiarism_flagging_enbled()) {
            $report->add_cell(new plagiarism_flag_cell($cell_items));
        }

        if ($this->plagiarism_enbled()) {
            $report->add_cell(new plagiarism_cell($cell_items));
        }

        if ($this->has_multiple_markers()) {
            $items_with_stage = array(
                'stage' => $this->get_final_grade_stage(),
                'coursework' => $this,
            );
            $report->add_cell(new multiple_agreed_grade_cell($items_with_stage));
        } else {
            $assessor_stages = $this->get_assessor_marking_stages();

            $items_with_stage = array(
                'stage' => reset($assessor_stages),
                'coursework' => $this,
            );
            $report->add_cell(new single_assessor_feedback_cell($items_with_stage));
        }
        if($this->moderation_agreement_enabled()){
            $items_with_stage = array(
                'stage' => $this->get_moderator_grade_stage(),
                'coursework' => $this,
            );
            $report->add_cell(new moderation_agreement_cell($items_with_stage));
        }

        $report->add_cell(new grade_for_gradebook_cell($cell_items));

        // Sub rows helper for assessor feedbacks (or not).
        if ($this->has_multiple_markers()) {
            $report->add_sub_rows(new multi_marker_feedback_sub_rows());
        } else {
            $report->add_sub_rows(new no_sub_rows());
        }

        return $report;
    }

    /**
     * @return bool
     */
    public function is_configured_to_have_group_submissions() {
        return !!$this->use_groups;
    }

    /**
     * @return string user or group
     */
    public function get_allocatable_type() {
        if ($this->is_configured_to_have_group_submissions()) {
            return 'group';
        } else {
            return 'user';
        }
    }

    /**
     * @return bool
     */
    public function students_can_still_submit() {
        return $this->deadline >= time();
    }

    /**
     * @param $deadline - coursework deadline, individually extended deadline
     * @return bool
     */
    public function due_to_send_first_reminders($deadline) {
        global $CFG;

        return ($deadline - $CFG->coursework_day_reminder *86400) < time();
    }

    /**
     * @param $deadline - courseworkindividually extended deadline
     * @return bool
     */
    public function due_to_send_second_reminders($deadline) {
        global $CFG;

        return ($deadline - $CFG->coursework_day_second_reminder*86400) < time();
    }

    /**
     * @return user[]
     */
    public function get_students() {
        $users = array();
        $raw_users = get_enrolled_users($this->get_context(), 'mod/coursework:submit');

       // filter students who are restricted from the coursework
        $cm = $this->get_course_module();
        $cmobject = $this->cm_object($cm);

        $info = new \core_availability\info_module($cmobject);
        $raw_users = $info->filter_user_list($raw_users);

        foreach ($raw_users as $raw_user) {
            $users[$raw_user->id] = new user($raw_user);
        }
        return $users;
    }

    /**
     * @return user[]
     */
    public function get_student_ids() {
        $raw_users = get_enrolled_users($this->get_context(), 'mod/coursework:submit', 0, 'u.id');
        return array_keys($raw_users);
    }

    /**
     * @return user[]
     */
    public function get_students_who_have_not_yet_submitted() {
        $students = $this->get_students();
        foreach ($students as $key => $student) {
            if ($this->has_user_submission($student)) {
                unset($students[$key]);
            }
        }
        return $students;
    }

    /**
     * @param allocatable $student
     * @return user[]
     */
    public function initial_assessors($student) {
        $assessors = array();
        $stages = $this->get_assessor_marking_stages();
        // If allocations, send the allocated teachers.
        // Otherwise send everyone.
        foreach ($stages as $stage) {
            if ($this->allocation_enabled()) {
                $teacher = $stage->allocated_teacher_for($student);
                if ($teacher) {
                    $assessors[] = $teacher;
                }
            } else {
                $assessors = array_merge($assessors, $stage->get_teachers());
            }
        }
        return array_unique($assessors);

    }

    public function disable_general_feedback() {
        $this->update_attribute('generalfeedback', 0);
    }

    public function enable_general_feedback() {
        $this->update_attribute('generalfeedback', strtotime('1 week ago'));
    }

    /**
     * @return bool
     */
    public function blindmarking_enabled() {
        return (bool)$this->blindmarking;
    }

    /**
     * @return bool
     */
    public function viewinitialgradeenabled() {
        return (bool)$this->viewinitialgradeenabled;
    }
    /**
     * @return bool
     */
    public function individual_feedback_deadline_has_passed() {
        return $this->individualfeedback <= time();
    }

    /**
     * @return bool
     */
    public function percentage_allocations_enabled() {
        return $this->allocation_enabled() && $this->assessorallocationstrategy == 'percentages';
    }

    /**
     * @param user $student
     *
     * @return bool
     */
    public function student_is_in_any_group($student) {
        global $DB;

        if ($this->grouping_id != 0) {
            $sql = "SELECT 1
                      FROM {groups_members} m
                INNER JOIN {groups} g
                        ON g.id = m.groupid
                INNER JOIN {groupings_groups} gr
                        ON gr.groupid = g.id
                     WHERE m.userid = :userid
                       AND g.courseid = :courseid
                       AND gr.groupingid = :groupingid
                ";

            $params = array(
                'userid' => $student->id,
                'courseid' => $this->get_course()->id,
                'groupingid' => $this->grouping_id,
            );
            return $DB->record_exists_sql($sql, $params);
        } else {

            $sql = "SELECT 1
                      FROM {groups_members} m
                INNER JOIN {groups} g
                        ON g.id = m.groupid
                     WHERE m.userid = :userid
                       AND g.courseid = :courseid
                ";

            return $DB->record_exists_sql($sql,
                                          array('userid' => $student->id,
                                                'courseid' => $this->get_course()->id));
        }
    }

    /**
     * @return bool
     */
    public function sampling_enabled()
    {
      return  (bool)$this->samplingenabled;
    }

    /**
     * @return bool
     */
    public function automaticagreement_enabled(){
        return $this->automaticagreementstrategy != null;
    }

    /**
     * @return bool
     */
    public function autopopulatefeedbackcomment_enabled(){
        return (bool)$this->autopopulatefeedbackcomment;
    }

    /**
     * @param \stdClass $db_grade
     */
    protected function update_feedback_timepublished($db_grade) {
        global $DB;

        // Record the publish time, but only if not already recorded, so we only ever see time
        // of first publishing.
        if (!$db_grade->timepublished) {
            $feedback = new \stdClass();
            $feedback->id = $db_grade->feedback_id;
            $feedback->timepublished = time();
            $DB->update_record('coursework_feedbacks', $feedback);
        }
    }

    /**
     * @return bool
     */
    public function is_using_advanced_grading() {
        $grading_manager = $this->get_advanced_grading_manager();
        if ($grading_manager) {
            return !!$grading_manager->get_active_controller();
        }
        return false;
    }

    /**
     * @return bool|gradingform_controller|null
     */
    public function get_advanced_grading_active_controller() {
        $grading_manager = $this->get_advanced_grading_manager();
        if ($grading_manager) {
            /**
             * @var gradingform_controller $controller
             */
            $controller = $grading_manager->get_active_controller();
            $menu = make_grades_menu($this->grade);
            $controller->set_grade_range($menu, $this->grade > 0);
            return $controller;
        }
        return false;
    }

    /**
     * @return \grading_manager
     */
    protected function get_advanced_grading_manager() {
        return get_grading_manager($this->get_context(), 'mod_coursework', 'submissions');
    }

    /**
     * Function to check if rubric is used in the current coursework
     *
     * @return bool
     */
    public function is_using_rubric(){
        $grading_manager = $this->get_advanced_grading_manager();
        $method = $grading_manager->get_active_method();

        if ($method == 'rubric'){
            return true;
        }
        return false;
    }

    /**
     * Function to get all coursework's rubric criteria
     *
     * @return mixed
     */
    public function get_rubric_criteria(){
        $controller = $this->get_advanced_grading_active_controller();
        return $controller->get_definition()->rubric_criteria;
    }

    /**
     * Returns an array of marking stages that define the different marking events for each submission.
     *
     * @return stage_base[]
     */
    public function marking_stages() {

        if (empty($this->stages)) {

            for ($i = 1; $i <= $this->get_max_markers(); $i++) {
                $identifier = 'assessor_' . $i;
                $this->stages[$identifier] = new assessor($this, $identifier);
            }

            if ($this->get_max_markers() > 1) {
                $identifier = 'final_agreed_1';
                $this->stages[$identifier] = new final_agreed($this, $identifier);
            }

            if ($this->moderation_agreement_enabled()){
                $identifier = 'moderator';
                $this->stages[$identifier] = new moderator($this, $identifier);

            }
        }

        return $this->stages;
    }

    /**
     * Temporary messy solution only used in the bit that makes the grading report cells.
     *
     * @return stage_base[]
     */
    public function get_assessor_marking_stages() {
        $stages = array();

        for ($i = 1; $i <= $this->get_max_markers(); $i++) {
            $stages[] = new assessor($this, 'assessor_' . $i);
        }
        if ($this->moderation_agreement_enabled()){
            $stages[] = new moderator($this, 'moderator');
        }

        return $stages;
    }

    /**
     * Temporary messy solution only used in the bit that makes the grading report cells.
     *
     * @throws \coding_exception
     * @return final_agreed
     */
    private function get_final_grade_stage() {
        if ($this->get_max_markers() > 1) {
            return new final_agreed($this, 'final_agreed_1');
        }
        throw new coding_exception('Trying to get the final grade stage of a coursework that does not have one');
    }

    /**
     * Temporary messy solution only used in the bit that makes the grading report cells.
     *
     * @return moderator
     */
    private function get_moderator_grade_stage() {
        if ($this->moderation_agreement_enabled()) {
            return new moderator($this, 'moderator');
        }
    }

    public  function get_submission_notification_users()    {
        return $this->submissionnotification;
    }


    /**
     * Utility method that returns an image icon.
     *
     * @todo This should be in the renderer.
     *
     * @param $text
     * @param $image_name
     * @return string
     */
    public final static function get_image($text, $image_name) {
        global $CFG;
        $url = $CFG->wwwroot . '/theme/image.php?image=t/' . $image_name;
        return html_writer::empty_tag('img',
                                      array('alt' => $text,
                                            'title' => $text,
                                            'src' => $url));
    }

    /**
     * @return allocatable[]
     */
    public function get_allocatables() {
        global $DB;

        $allocatables = array();

        if ($this->is_configured_to_have_group_submissions()) {
            if ($this->grouping_id) {
                $sql = "
                SELECT groups.*
                  FROM {groups} groups
            INNER JOIN {groupings_groups} groupings
                    ON groups.id = groupings.groupid
                 WHERE groups.courseid = :courseid
                   AND groupings.groupingid = :grouping_id
            ";
                $params = array(
                    'grouping_id' => $this->grouping_id,
                    'courseid' => $this->get_course()->id,
                );
            } else {
                $sql = "
                SELECT groups.*
                  FROM {groups} groups
                 WHERE groups.courseid = :courseid
            ";
                $params = array(
                    'courseid' => $this->get_course()->id,
                );
            }

            $groups = $DB->get_records_sql($sql, $params);
            foreach ($groups as $group) {
                $group = group::find($group);
                //find out if members of this group can access this coursework, if group is left without members then remove it
                $cm = $this->get_course_module();
                $cmobject = $this->cm_object($cm);
                /**
                 * @var group $group
                 */
                $members =  $group->get_members($this->get_context(), $cmobject);
                if (empty($members)){
                    continue;
                }
                $allocatables[$group->id] = $group;
            }

        } else {

            $allocatables = $this->get_students();
        }
        return $allocatables;
    }

    /**
     * @return array
     */
    public function get_allocatable_ids() {
        return array_keys($this->get_allocatables());
    }

    /**
     * @return int
     */
    public function number_of_allocatables() {
        return  count($this->get_allocatables());
    }

    /**
     * We cache these so that we can cache result sets inside the stages for calls like has_alloction()
     *
     * @param $identifier
     * @return stage_base
     */
    public function get_stage($identifier) {

        if (!array_key_exists($identifier, $this->stages)) {
            if ($identifier != 'moderator') {
                $stage_name = substr($identifier, 0, strripos($identifier, '_'));
            } else {
                $stage_name = $identifier;
            }
            $classname = "\\mod_coursework\\stages\\" . $stage_name;
            return new $classname($this, $identifier);
        }

        return $this->stages[$identifier];
    }

    /**
     * @return submission[]
     * @throws \coding_exception
     */
    private function get_submissions_to_publish() {
        $params = array(
            'courseworkid' => $this->id
        );

        $submissions = submission::find_all($params);
        /**
         * @var submission $submission
         */
        foreach ($submissions as $key => $submission) {
            if (!$submission->ready_to_publish()) {
                unset($submissions[$key]);
            }
        }

        return $submissions;
    }

    /**
     * @return final_agreed
     */
    public function get_final_agreed_marking_stage() {
        return new final_agreed($this, 'final_agreed_1');
    }

    /**
     * @return moderator
     */
    public function get_moderator_marking_stage() {
        return new moderator($this, 'moderator_1');
    }

    /**
     * @return bool
     */
    public function has_stuff_to_publish() {
        $submissions = $this->get_submissions_to_publish();
        return !empty($submissions);
    }

    /**
     * @return bool
     */
    public function is_general_feedback_enabled(){
        return ($this->generalfeedback != 0);
    }

    /**
     * @return bool
     */
    public function has_individual_autorelease_feedback_enabled(){
        return ($this->individualfeedback != 0);
    }

    /**
     * @return array
     */
    public function get_finalised_submissions(){
        global $DB;

        $submissions = $DB->get_records('coursework_submissions',
            array('courseworkid' => $this->id, 'finalised'=>1), '', 'id');

        foreach ($submissions as &$submission) {
            $submission = submission::find($submission);
        }

        return $submissions;

    }

    /**
     * @return bool
     */
    public function start_date_has_passed() {
        return empty($this->startdate) || $this->startdate < time();
    }

    public function finalise_all() {
        global $DB;
        $excludesql = '';
         // check if any extensions granted for this coursework
        if ($this->extensions_enabled() && $this->extension_exists()){
            $submissions_with_extensions = $this->get_submissions_with_extensions();
            foreach ($submissions_with_extensions as $submission){
                // exclude submissions that are still within extended deadline
                if ($submission->extended_deadline > time()){
                    $excludesubmissions[$submission->submissionid] = $submission->submissionid;
                }
            }
            // build exclude sql
            if (!empty($excludesubmissions)){
                $excludesql = ' AND id NOT IN(';
                $excludesql .= implode(',',$excludesubmissions);
                $excludesql .= ')';
            }
        }

        // if it's personal dealdines coursework, check individual deadline if passed, if not exclude
        if ($this->personal_deadlines_enabled()) {
            $submissions = $this->get_coursework_submission_personal_deadlines();
            foreach ($submissions as $submission) {
                // exclude submissions still within personal deadline
                if ($submission->personal_deadline > time()) {
                    $excludesubmissions[$submission->submissionid] = $submission->submissionid;
                }
            }
        }

        // build exclude sql
        if (!empty($excludesubmissions)) {
            $excludesql = ' AND id NOT IN(';
            $excludesql .= implode(',', $excludesubmissions);
            $excludesql .= ')';
        }



        $DB->execute("UPDATE {coursework_submissions}
                         SET finalised = 1
                       WHERE courseworkid = ? $excludesql", array($this->id));
    }

    /**
     * @return array
     */
    public static function extension_reasons() {
        global $CFG;

        $extension_reasons = array();
        if (!empty($CFG->coursework_extension_reasons_list)) {
            $extension_reasons = $CFG->coursework_extension_reasons_list;
            $extension_reasons = explode("\n", $extension_reasons);
            $extension_reasons = array_map(function ($i) {
                    return trim($i);
                },
                $extension_reasons);
            $extension_reasons = array_merge(array('' => 'Not specified'), $extension_reasons);
        }
        return $extension_reasons;
    }

    /**
     * Lets us know if extensions are enabled in the coursework.
     * @return bool
     */
    public function extensions_enabled() {
        return (bool)$this->extensionsenabled;
    }

    /*
* @return bool
*/
    public function extension_exists(){

        global $DB;
        return $DB->record_exists('coursework_extensions',array('courseworkid'=>$this->id));
    }

    public function get_submissions_with_extensions(){
        global $DB;

        $sql = "SELECT *, cs.id as submissionid
                FROM {coursework_extensions} ce
                JOIN {coursework_submissions} cs
                ON ce.courseworkid = cs.courseworkid
                AND ce.allocatableid = cs.allocatableid
                AND ce.allocatabletype = cs.allocatabletype
                AND cs.courseworkid = :courseworkid";

        return $DB->get_records_sql($sql,array('courseworkid'=>$this->id));
    }

    /**
     * Get all personal deadline in coursework, they can be coursework deadline-(default) or personal
     */
    public function get_coursework_submission_personal_deadlines(){
        global $DB;

        $sql = "SELECT cs.id as submissionid, personal_deadline
                FROM {coursework_submissions} cs
                LEFT JOIN {coursework_person_deadlines} pd ON cs.courseworkid = pd.courseworkid
                AND pd.allocatableid = cs.allocatableid
                AND pd.allocatabletype = cs.allocatabletype
                WHERE cs.courseworkid = :courseworkid";

        $submissions = $DB->get_records_sql($sql,array('courseworkid'=>$this->id));

        // for submissions that don't have a set personal deadline give coursework's default deadline
        if($submissions) {
            foreach ($submissions as $submission) {
                if (is_null($submission->personal_deadline)) {
                    $submission->personal_deadline = $this->deadline;
                }
            }
        }
        return $submissions;
    }







    /**
     * Has the given stage got a an automatic sampling rule
     *
     * @param $stage
     * @return bool
     */
    public function has_automatic_sampling_at_stage($stage)   {
        global  $DB;

        return $DB->record_exists('coursework_sample_set_rules',array('courseworkid'=>$this->id,'stage_identifier'=>$stage));
    }



    /**
     * Returns all allocatables in the current coursework that have feedback
     *
     * @return allocatable[]
     */
    public function get_allocatables_with_feedback($stage, $random = false)    {
        global $DB, $CFG;

        $sql = "SELECT   cwrsub.allocatableid, cwrfb.*
                         FROM       {coursework_submissions}  cwrsub,
                                    {coursework_feedbacks}    cwrfb
                         WHERE      cwrsub.id   = cwrfb.submissionid
                         AND        cwrsub.courseworkid = :coursework_id
                         AND        stage_identifier = :stage";



        if ($random) {
            $sql .= ($CFG->dbtype == 'pgsql') ? " ORDER BY RANDOM() " :  " ORDER BY RAND() ";
        }

        return $DB->get_records_sql($sql,
            array('coursework_id' => $this->id, "stage"=>$stage));

    }


    /*
     * Creates automatic feedback
     *
     */
    public function create_automatic_feedback() {
        global $SESSION;

        if ($this->numberofmarkers <= 1 || $this->automaticagreementstrategy == 'null') {
            return;
        }
        submission::fill_pool_coursework($this->id);
        feedback::fill_pool_coursework($this->id);
        $submissions = isset(submission::$pool[$this->id]['finalised'][1]) ? submission::$pool[$this->id]['finalised'][1] : [];
        if (empty($submissions)) {
            return;
        }
        $current = time();
        $gradeeditingtime = $this->gradeeditingtime;
        $SESSION->keep_cache_data = 1;
        foreach ($submissions as $submission) {
            if ($gradeeditingtime != 0) {
                // initial feedbacks - other feedbacks than final
                $initial_feedbacks = isset(feedback::$pool[$this->id]['submissionid-stage_identifier_index'][$submission->id . '-others']) ?
                    feedback::$pool[$this->id]['submissionid-stage_identifier_index'][$submission->id . '-others'] : [];
                $valid_fb = false;
                foreach ($initial_feedbacks as $feedback) {
                    if ($feedback->timecreated + $gradeeditingtime <= $current) {
                        $valid_fb = true;
                        break;
                    }
                }
                if (!$valid_fb) {
                    continue;
                }
            }
            if (!$submission->editable_feedbacks_exist()) {
                // this submission needs automatic agreement
                $auto_feedback_classname = '\mod_coursework\auto_grader\\' . $submission->get_coursework()->automaticagreementstrategy;
                /**
                 * @var auto_grader $auto_grader
                 */
                $auto_grader = new $auto_feedback_classname($submission->get_coursework(),
                    $submission->get_allocatable(),
                    $submission->get_coursework()->automaticagreementrange);
                $auto_grader->create_auto_grade_if_rules_match();
            }

        }
        unset($SESSION->keep_cache_data);
        feedback::remove_cache($this->id);


    }


    /** Function to check it Turnitin is enabled for the particular coursework
     * @return bool
     * @throws \dml_exception
     */
    public function tii_enabled(){

        if (!isset(self::$pool[$this->id]['tii_enabled'][$this->id])) {
            global $CFG, $DB;
            $turnitinenabled = false;
            if ($CFG->enableplagiarism) {
                $plagiarismsettings = (array)get_config('plagiarism');
                if (!empty($plagiarismsettings['turnitin_use'])) {
                    $params = array(
                        'cm' => $this->get_coursemodule_id(),
                        'name' => 'use_turnitin',
                        'value' => 1
                    );
                    if ($DB->record_exists('plagiarism_turnitin_config', $params)) {
                        $turnitinenabled = true;
                    }
                }
            }
            self::$pool[$this->id]['tii_enabled'][$this->id] = $turnitinenabled;
        }
        return self::$pool[$this->id]['tii_enabled'][$this->id];
    }






    /**
     * Lets us know if personal deadlines are enabled in the coursework.
     * @return bool
     */
    public function personal_deadlines_enabled(){
        return (bool)$this->personaldeadlineenabled;
    }


    /**
     * Lets us know if draft feedback is enabled in the coursework.
     * @return bool
     */
    public function draft_feedback_enabled(){
        return (bool)$this->draftfeedbackenabled;
    }


    /**
     * Return all allocatables and the allocatables deadline
     * Note! allocatables are returned irrespective of whether they have submitted.
     *
     * @return array
     */
    public function get_allocatables_and_deadline()    {

        $allocatables   =   $this->get_allocatables();

        if (!empty($allocatables)) {
            $allocatables   =   array_map(array($this,"get_allocatable_personal_deadline"), $allocatables);
        }

        return $allocatables;
    }

    /**
     * This function adds the allocatables personal deadline in the current coursework to the allocatable record
     *
     * @param int $allocatableid
     *
     * @return array
     */
    private function  get_allocatable_personal_deadline($allocatable)   {

        global  $DB;

        $allocatable->deadline      =   $this->deadline;
        $allocatable->coursework_id =   $this->id;

        if ($this->personal_deadlines_enabled()) {
            personal_deadline::fill_pool_coursework($this->id);
            $deadlinerecord = personal_deadline::get_object($this->id, 'allocatableid-allocatabletype', [$allocatable->id, $allocatable->type()]);

            if (!empty($deadlinerecord)) {
                $allocatable->deadline = $deadlinerecord->personal_deadline;
            }
        }

        return $allocatable;

    }

    /** Check is courseowrk kas any users added to sample
     *
     * @return bool
     */
    public function has_samples(){
        global $DB;

        return $DB->record_exists('coursework_sample_set_mbrs', array('courseworkid' => $this->id));

    }

    /**
     * Check if the user in this coursework is a student (has capability to submit)
     *
     * @return bool
     */
    public function can_submit(){
        if (has_capability('mod/coursework:submit', $this->get_context())) {
            return true;
        }
        return false;
    }

    /**
     * Check if the user in this coursework is a marker (has any capability to grade)
     *
     * @return bool
     */
    public function can_grade(){
        if (has_capability('mod/coursework:addinitialgrade', $this->get_context()) || has_capability('mod/coursework:addagreedgrade', $this->get_context())
        || has_capability('mod/coursework:addallocatedagreedgrade', $this->get_context())) {
            return true;
        }
        return false;
    }


    /**
     * @return bool
     */
    public function marking_deadline_enabled(){
        return (bool)$this->markingdeadlineenabled ;
    }

    /**
     * Function to check if a given individual is in the given group
     *
     * @param $studentid
     * @param $groupid
     * @return bool
     * @throws \dml_exception
     */
    public function student_in_group($studentid, $groupid){
        global $DB;
        $sql = "SELECT groups.*
                  FROM {groups} groups
            INNER JOIN {groups_members} gm
                    ON gm.groupid = groups.id
                 WHERE gm.userid = :userid
                   AND groups.courseid = :courseid
                   AND groups.id = :groupid";
        $params = array('userid' => $studentid,
            'courseid' => $this->get_course()->id,
            'groupid' => $groupid);
        return $DB->record_exists_sql($sql, $params);
    }
    /**
     * Function to retrieve all submissions by coursework
     *
     * @return submissions
     */
    function retrieve_submissions_by_coursework(){
        global $DB;
        return $DB->get_records('coursework_submissions', ['courseworkid' => $this->id, 'allocatabletype' => 'user']);
    }
    /**
     * Function to retrieve all submissions submitted by a user
     *
     * @param $user_id
     * @return submissions
     */
    public function retrieve_submissions_by_user($user_id){
        global $DB;
        return $DB->get_records('coursework_submissions', ['courseworkid' => $this->id, 'authorid' => $user_id, 'allocatabletype' => 'user']);
    }

    /**
     * Function to retrieve all feedbacks by a submission
     *
     * @param $submission_id
     * @return feedbacks
     */
    public function retrieve_feedbacks_by_submission($submission_id) {
        feedback::fill_pool_coursework($this->id);
        $result = isset(feedback::$pool[$this->id][submissionid]) ? feedback::$pool[$this->id][submissionid] : [];
        return $result;
    }
    /**
     * Function to remove all submissions submitted by a user
     *
     * @param $user_id
     */
    public function remove_submissions_by_user($user_id){
        global $DB;
        $DB->delete_records('coursework_submissions', ['courseworkid' => $this->id, 'authorid' => $user_id, 'allocatabletype' => 'user']);
    }
    /**
     * Function to remove all submissions by this coursework
     *
     * @return submissions
     */
    public function remove_submissions_by_coursework(){
        global $DB;
        $DB->delete_records('coursework_submissions', ['courseworkid' => $this->id, 'allocatabletype' => 'user']);
    }
    /**
     * Function to Remove the corresponding file by context, item-id and fielarea
     *
     * @param $context_id
     * @param $item_id
     * @param $filearea
     */
    public function remove_corresponding_file($context_id, $item_id, $filearea){
        global $DB;
        $component = 'mod_coursework';
        $fs = get_file_storage();
        $fs->delete_area_files($context_id, $component, $filearea, $item_id);
    }
    /**
     * Function to Remove all feedbacks by a submission
     *
     * @param $submission_id
     */
    public function remove_feedbacks_by_submission($submission_id){
        global $DB;
        $DB->delete_records('coursework_feedbacks', ['submissionid' => $submission_id]);
    }
    /**
     * Function to Remove all agreements by a feedback
     *
     * @param $feedback_id
     */
    public function remove_agreements_by_feedback($feedback_id){
        global $DB;
        $DB->delete_records('coursework_mod_agreements', ['feedbackid' => $feedback_id]);
    }
    /**
     * Function to Remove all deadline extensions by user
     *
     * @param $user_id
     */
    public function remove_deadline_extensions_by_user($user_id){
        global $DB;
        $DB->execute('DELETE FROM {coursework_extensions} WHERE allocatabletype = ? AND (allocatableid = ? OR allocatableuser = ? ) ', array('user', $user_id, $user_id));
    }
    /**
     * Function to Remove all personal deadlines by coursework
     *
     */
    public function remove_personal_deadlines_by_coursework() {
        global $DB;

        $DB->execute('DELETE FROM {coursework_person_deadlines} WHERE allocatabletype = ? AND courseworkid = ? ', array('user', $this->id));
        personal_deadline::remove_cache($this->id);
    }
    /**
     * Function to Remove all deadline extensions by coursework
     *
     */
    public function remove_deadline_extensions_by_coursework(){
        global $DB;
        $DB->execute('DELETE FROM {coursework_extensions} WHERE allocatabletype = ? AND courseworkid = ? ', array('user', $this->id));
    }


    /**
     * Function to check if Coursework has any final feedback
     *
     * @return bool
     * @throws \dml_exception
     */
    public function has_any_final_feedback(){
        global $DB;

        $sql = "SELECT * 
                FROM {coursework_feedbacks} cf 
                JOIN {coursework_submissions} cs ON cs.id = cf.submissionid
                WHERE cs.courseworkid = :courseworkid
                AND cf.stage_identifier = 'final_agreed_1'";

        return $DB->record_exists_sql($sql, array('courseworkid' => $this->id));
    }

    /**
     * Tells us the deadline for a specific allocatable.
     *
     * @param int $allocatableid
     * @return int
     */
    public function get_allocatable_deadline($allocatableid) {
        $deadline = $this->deadline;

        if($this->use_groups){
            $allocatable = group::find($allocatableid);
        } else {
            $allocatable = user::find($allocatableid);
        }


        if ($this->personal_deadlines_enabled()){
            // find personal deadline for a user if this option enabled
            $personal = $this->get_allocatable_personal_deadline($allocatable);
            if (!empty($personal)) {
                $deadline = $personal->deadline;
            }
        }

        if($this->extensions_enabled()){ // check if coursework allows extensions
            // check if extension for this user exists
            $extension = $this->get_allocatable_extension($allocatable);
            if (!empty($extension)) {
                $deadline = $extension;
            }
        }

        return $deadline;
    }

    /**
     * * This function returns allocatable extension if given
     * @param $allocatable
     * @return bool/int
     */
    private function  get_allocatable_extension($allocatable)   {

        global  $DB;
        $extension = false;


        if ($this->extensions_enabled() ) {
            $extensionrecord = $DB->get_record('coursework_extensions', array('courseworkid' => $this->id,'allocatableid' => $allocatable->id));

            if (!empty($extensionrecord)) {
                $extension = $extensionrecord->extended_deadline;
            }
        }

        return $extension;
    }


    /**
     * Function to Remove all plagiarisms by a submission
     *
     * @param $submission_id
     */
    public function remove_plagiarisms_by_submission($submission_id) {
        global $DB;

        $DB->delete_records('coursework_plagiarism_flags', ['submissionid' => $submission_id]);
    }


    /**
     * Function to check if Coursework has any submission
     *
     * @return bool
     * @throws \dml_exception
     */
    public function has_any_submission(){
        global $DB;

        $sql = "SELECT * 
                FROM {coursework_submissions} cs 
                WHERE cs.courseworkid = :courseworkid";

        return $DB->record_exists_sql($sql, array('courseworkid' => $this->id));
    }


    /**
     * Function to check if coursework or course that a coursework belongs to is hidden
     *
     * @return bool
     * @throws moodle_exception
     */
    public function is_coursework_visible(){

        $visible = true;
        if ($this->get_course_module()->visible == 0 || $this->get_course()->visible == 0){
            $visible = false;
        }
        return $visible;

    }

    /**
     * @param null $stage_index
     */
    public function clear_stage($stage_index = null) {
        if ($stage_index) {
            if (isset($this->stages[$stage_index])) {
                unset($this->stages[$stage_index]);
            }
        } else {
            $this->stages = [];
        }
    }


    /**
     * cache array
     *
     * @var
     */
    public static $pool;

    /**
     * Fill pool to cache for later use
     *
     * @param $array
     */
    public static function fill_pool($array) {
        self::$pool = [
            'id' => []
        ];

        foreach ($array as $record) {
            $object = new self($record);
            self::$pool['id'][$record->id] = $object;
        }
    }

    /**
     *
     * @param $coursework_id
     * @throws \dml_exception
     */
    public static function fill_pool_coursework($coursework_id) {
        global $DB;
        if (empty(self::$pool['id'][$coursework_id])) {
            $courseworks = $DB->get_records('coursework', ['id' => $coursework_id]);
            self::fill_pool($courseworks);
        }
    }

    /**
     *
     * @param $coursework_id
     * @return bool
     */
    public static function get_object($coursework_id) {
        if (!isset(self::$pool['id'][$coursework_id])) {
            self::fill_pool_coursework($coursework_id);
        }
        return self::$pool['id'][$coursework_id] ?? false;
    }

    /**
     *
     */
    public function fill_cache() {
        global $DB;
        $coursework_id = $this->id;
        submission::fill_pool_coursework($coursework_id);
        coursework::fill_pool([$this]);
        course_module::fill_pool([$this->get_course_module()]);
        module::fill_pool($DB->get_records('modules', ['name' => 'coursework']));
        feedback::fill_pool_submissions($coursework_id, array_keys(submission::$pool[$coursework_id]['id']));
        allocation::fill_pool_coursework($coursework_id);
        plagiarism_flag::fill_pool_coursework($coursework_id);
        assessment_set_membership::fill_pool_coursework($coursework_id);
        if (\core_plugin_manager::instance()->get_plugin_info('local_uolw_sits_data_import')) {
            user::fill_candidate_number_data($this);
        }
    }
}
