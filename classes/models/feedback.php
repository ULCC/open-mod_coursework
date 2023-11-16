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

namespace mod_coursework\models;

use context;
use core_user;
use mod_coursework\framework\table_base;
use mod_coursework\ability;
use mod_coursework\stages\base as stage_base;
use stdClass;
use \mod_coursework\feedback_files;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to represent a single item of feedback that a tutor will provide for a submission.
 *
 * @property mixed stage_identifier
 * @property int feedback_manager
 */
class feedback extends table_base {

    /**
     * @var string
     */
    protected static $table_name = 'coursework_feedbacks';

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $submissionid;

    /**
     * @var int
     */
    public $assessorid;

    /**
     * @var int
     */
    public $timecreated;

    /**
     * @var int
     */
    public $timemodified;

    /**
     * @var string
     */
    public $grade;

    /**
     * @var string
     */
    public $feedbackcomment;

    /**
     * @var int
     */
    public $feedbackcommentformat;

    /**
     * @var int
     */
    public $timepublished;

    /**
     * @var int
     */
    public $lasteditedbyuser;

    /**
     * @var int
     */
    public $feedbackfiles;

    /**
     * @var string
     */
    public $firstname;

    /**
     * @var string
     */
    public $lastname;

    /**
     * @var int
     */
    public $courseworkid;

    /**
     * @var stdClass hold all of the custom form data associated with this feedback.
     * Needs further processing. {@see feedback->set_feedback_data()}
     */
    public $form_data;

    /**
     * @var stdClass
     */
    public $student;

    /**
     * @var submission
     */
    public $submission;

    /**
     * @var int 1 = it is a final grade, 0 is default in the DB. Used only for multiple marked things.
     */
    public $isfinalgrade;

    /**
     * @var int 1 = it is a feedback left by a moderator, 0 (default) means it's not.
     */
    public $ismoderation;

    /**
     * @var int the id of the entry (in the ULCC form library) attached to this feedback
     */
    public $entry_id;

    /**
     * @var int tells us what number this feedback was so we can easily link the feedback table to submissions for
     * generating reports.
     */
    public $markernumber;

    /**
     * This allows up to loop through the properties of the object which correspond to fields
     * in the DB table, ignoring the others.
     * @var array
     */
    protected $fields = array(
        'id',
        'submissionid',
        'timecreated',
        'timemodified',
        'assessorid',
        'grade',
        'feedbackcomment',
        'feedbackcommentformat',
        'timepublished',
        'lasteditedbyuser',
        'isfinalgrade',
        'ismoderation',
        'entry_id',
        'markernumber'
    );

    /**
     * @var stdClass
     */
    public $assessor;

    /**
     * @var bool Tells renderer whether to show the comment
     */
    private $showcomment = true;

    /**
     * @var bool Tells renderer whether to show the comment
     */
    private $showgrade = true;

    /**
     * This function is used for student view, it determines if assessors' names should be displayed or should be hidden
     * @return string assessor's name
     * @throws \coding_exception
     */
    public function display_assessor_name(){

        // check if assessor's name in this CW is set to hidden
        if ($this->is_assessor_anonymity_enabled()){
            $assessor_name = get_string('hidden', 'mod_coursework');
        } else {
            $assessor_name = $this->get_assesor_username();
        }

        return $assessor_name;
    }


    /**
     * Real name for display. Allows us to defer the DB call to retrieve first and last name
     * in case we don't need it.
     */
    public function get_assesor_username() {

        if (!$this->firstname && !empty($this->lasteditedbyuser)) {
            //$this->assessor = core_user::get_user($this->lasteditedbyuser);
            $this->assessor = user::get_object($this->lasteditedbyuser);
        }

        return fullname($this->assessor);
    }

    public function get_assessor_id(){
        return $this->assessor->id;
    }

    /**
     * @return string
     */
    public function get_assessor_stage_no(){
        $no = '';
        if (substr($this->stage_identifier,0,9 ) =='assessor_'){
            $no = substr($this->stage_identifier, -1);
        }
        return $no;
    }

    public function get_feedbacks_assessorid(){
        return $this->assessorid;
    }
    /**
     * Gets the HTML user picture for the assessor.
     *
     * @return string
     */
    public function get_assesor_user_picture() {

        global $DB, $OUTPUT;

        $user = $DB->get_record('user', array('id' => $this->assessorid));
        if ($user) {
            return $OUTPUT->user_picture($user);
        }
        return '';
    }

    /**
     * Chained getter for loose coupling.
     *
     * @return coursework
     */
    public function get_coursework() {
        return $this->get_submission()->get_coursework();
    }

    /**
     * Chained getter for loose coupling.
     *
     * @return int
     */
    public function get_coursemodule_id() {
        return $this->get_submission()->get_course_module_id();
    }


    /**
     * Returns a feedback instance
     * @todo get rid of this.
     *
     * @static
     * @param $submission
     * @param int $assessorid
     * @param int $isfinalgrade do we want the final grade (in case this assessor did a component
     * one and a final one
     * @internal param $submissionid
     * @return feedback|null
     */
    public static function get_teacher_feedback(submission $submission,
                                                $isfinalgrade = 0,
                                                $assessorid = 0 ) {
        global $DB;

        $params = array('submissionid' => $submission->id);
        // If it's single marker, we just get the only one.
        if ($assessorid && $submission->has_multiple_markers()) {
            $params['assessorid'] = $assessorid;
            $params['ismoderation'] = 0;
        }

        $params['isfinalgrade'] = $isfinalgrade ? 1 : 0;

        // Should only ever be one that has the particular combination of these three options.
        $feedback = $DB->get_record('coursework_feedbacks', $params);

        if (is_object($feedback)) {
            return new feedback($feedback);
        }
        return null;
    }

    /**
     * Check if assessor is allocated to the user in this stage
     * @return bool
     */
    public function is_assessor_allocated(){
       return $this->get_stage()->assessor_has_allocation($this->get_allocatable());
    }

    /**
     * @param $context_id
     * @return void
     */
    public function set_feedback_files($context_id) {

        if (is_array($this->feedbackfiles)) {
            return;
        }

        if (!$context_id) {
            return;
        }

        $fs = get_file_storage();
        $this->feedbackfiles = $fs->get_area_files($context_id, 'mod_coursework',
                                                   'feedback', $this->id, "id", false);
    }

    /**
     * Fetches all the files for this feedback and returns them as an array
     *
     * @return array
     */
    public function get_feedback_files() {

        $this->set_feedback_files($this->get_context_id());
        if ($this->feedbackfiles !=null){
            $this->feedback_files = new feedback_files($this->feedbackfiles, $this);
            return $this->feedback_files;
        }

        return false;
    }



    /**
     * @return mixed
     */
    public function set_student() {
        global $DB;

        if (!$this->submissionid) {
            return;
        }
        $sql = "SELECT u.id,
                       u.id AS userid,
                       u.firstname,
                       u.lastname
                  FROM {user} u
            INNER JOIN {coursework_submissions} s
                    ON u.id = s.userid
                 WHERE s.id = :sid
                    ";
        $params = array('sid' => $this->submissionid);
        $this->student = $DB->get_record_sql($sql, $params);
    }

    /**
     * Makes sure we have the correct user recorded as having edited it. Timemodified is dealt
     * with by the parent. We also need to make sure than when a new feedback is saved, we end up getting the marker number
     * which is next up from the last one.
     */
    public function pre_save_hook() {

        global $USER, $DB;
        if (!isset($this->lasteditedbyuser)) {
            $this->lasteditedbyuser = $USER->id;
        }

        if ($this->ismoderation == 0 && $this->isfinalgrade == 0 && empty($this->id)) {
            $sql = 'SELECT MAX(feedbacks.markernumber)
                      FROM {coursework_feedbacks} feedbacks
                     WHERE feedbacks.submissionid = :subid';
            $params = array('subid' => $this->submissionid);
            $maxmarkernumber = $DB->get_field_sql($sql, $params);

            if (empty($maxmarkernumber)) {
                $maxmarkernumber = 0;
            }

            $this->markernumber = $maxmarkernumber + 1;
        }
    }

    /**
     * Tells us whether the feedback is the one holding the final agreed grade for a multiple marked
     * coursework.
     *
     * @return bool
     */
    public function is_agreed_grade() {
        $identifier = $this->get_stage()->identifier();
        if ($this->get_coursework()->has_multiple_markers()) {
            return $identifier == 'final_agreed_1';
        } else {
            return $identifier == 'assessor_1';
        }
    }

    /**
     * Tells us whether this is a feedback added by a moderator.
     *
     * @return bool
     */
    public function is_moderation() {
        return $this->get_stage()->identifier() == 'moderator_1';
    }

    /**
     * Chained getter.
     *
     * @return mixed
     */
    public function get_coursework_id() {
        return $this->get_coursework()->id;
    }

    /**
     * Chained getter.
     *
     * @return context
     */
    public function get_context() {
        return $this->get_coursework()->get_context();
    }


    /**
     * Is this feedback one of the component grades in a multiple marking scenario?
     *
     */
    public function is_initial_assessor_feedback() {
        return $this->get_stage()->is_initial_assesor_stage();
    }

    /**
     * Has the general deadline for individual feedback passed?
     *
     * @return bool
     */
    public function individual_deadline_passed() {
        return time() > $this->get_coursework()->get_individual_feedback_deadline();
    }

    /**
     * Tells us what state the submission is in e.g. submission::PUBLISHED
     * @return int
     */
    protected function get_submission_state() {
        return $this->get_submission()->get_state();
    }

    /**
     * Memoized getter
     *
     * @return bool|submission
     */
    public function get_submission() {

        if (!isset($this->submission) && !empty($this->submissionid)) {
            global $DB;
            $coursework_id = $this->courseworkid ?? $DB->get_field(submission::$table_name, 'courseworkid', ['id' => $this->submissionid], MUST_EXIST);
            if (!isset(submission::$pool[$coursework_id])) {
                submission::fill_pool_coursework($coursework_id);
            }
            $this->submission = submission::$pool[$coursework_id]['id'][$this->submissionid] ?? false;
        }

        return $this->submission;
    }

    /**
     * Getter for the feedback grade.
     *
     * @return int
     */
    public function get_grade() {
        return $this->grade;
    }

    /**
     * Lets us specify which assessor is dealing with this. Only used after we instantiate without a DB record.
     *
     * @param int $assessorid
     */
    public function set_assessor_id($assessorid) {
        $this->assessorid = $assessorid;
    }

    /**
     * @param $userid
     * @return int
     */
    public function get_user_deadline($userid) {
        return $this->get_coursework()->get_user_deadline($userid);
    }

    /**
     * @return int
     */
    private function get_context_id() {
        return $this->get_submission()->get_context_id();
    }

    /**
     * This takes various settings from the visibility grid, depending on what type of feedback this is.
     * @todo Needs replacing with some sort of polymorphism
     *
     * @param bool $show
     */
    public function set_allowed_to_show_grade($show) {
        $this->showgrade = $show;
    }

    /**
     * @param bool $show
     */
    public function set_allowed_to_show_comment($show) {
        $this->showcomment = $show;
    }

    /**
     * @return bool
     */
    public function is_allowed_to_show_grade() {
        return $this->showgrade;
    }

    /**
     * @return bool
     */
    public function is_allowed_to_show_comment() {
        return $this->showcomment;
    }

    /**
     * @return user
     */
    public function assessor() {
        return user::get_object($this->assessorid);
    }

    /**
     * @return stage_base
     */
    public function get_stage() {
        return $this->get_coursework()->get_stage($this->stage_identifier);
    }

    /**
     * @return \mod_coursework\allocation\allocatable
     */
    public function get_allocatable() {
        return $this->get_submission()->get_allocatable();
    }

    public function is_assessor_anonymity_enabled(){
        return $this->get_coursework()->assessoranonymity;
    }

    /**
     * cache array
     *
     * @var
     */
    public static $pool;

    /**
     *
     * @param $coursework_id
     * @throws \dml_exception
     */
    public static function fill_pool_coursework($coursework_id) {
        global $DB;
        if (isset(self::$pool[$coursework_id])) {
            return;
        }
        if (submission::$pool[$coursework_id]) {
            $submission_ids = submission::$pool[$coursework_id]['id'];
        } else {
            $submission_ids = $DB->get_records(submission::$table_name, ['courseworkid' => $coursework_id], '', 'id');
        }
        self::fill_pool_submissions($coursework_id, array_keys($submission_ids));
    }

    /**
     * @param $coursework_id
     * @param $submission_ids
     */
    public static function fill_pool_submissions($coursework_id, $submission_ids) {
        global $DB;

        if (isset(self::$pool[$coursework_id])) {
            return;
        }

        $key = self::$table_name;
        $cache = \cache::make('mod_coursework', 'courseworkdata', ['id' => $coursework_id]);

        $data = $cache->get($key);
        if ($data === false) {
            $data = [
                'id' => [],
                'submissionid-stage_identifier' => [],
                'submissionid-stage_identifier_index' => [],
                'submissionid-finalised' => [],
                'submissionid-ismoderation-isfinalgrade-stage_identifier' => [],
                'submissionid-assessorid' => [],
                'submissionid' => []
            ];
            if ($submission_ids) {
                list($submission_id_sql, $submission_id_params) = $DB->get_in_or_equal($submission_ids, SQL_PARAMS_NAMED);
                $feedbacks = $DB->get_records_sql("SELECT * FROM {coursework_feedbacks} WHERE submissionid $submission_id_sql", $submission_id_params);
                foreach ($feedbacks as $record) {
                    $object = new self($record);
                    $stage_identifier = $record->stage_identifier;
                    $stage_identifier_index = ($stage_identifier == 'final_agreed_1') ? $stage_identifier : 'others';
                    $data['id'][$record->id] = $object;
                    $data['submissionid-stage_identifier'][$record->submissionid . '-' . $stage_identifier][] = $object;
                    $data['submissionid-stage_identifier_index'][$record->submissionid . '-' . $stage_identifier_index][] = $object;
                    $data['submissionid-finalised'][$record->submissionid . '-' . $record->finalised][] = $object;
                    $data['submissionid-ismoderation-isfinalgrade-stage_identifier'][$record->submissionid . '-' . $record->ismoderation . '-' . $record->isfinalgrade . '-' . $stage_identifier][] = $object;
                    $data['submissionid-assessorid'][$record->submissionid . '-' . $record->assessorid][] = $object;
                    $data['submissionid'][$record->submissionid][] = $object;
                }
            }
            $cache->set($key, $data);
        }
        self::$pool[$coursework_id] = $data;
    }

    /**
     *
     * @param $coursework_id
     * @param $key
     * @param $params
     * @return bool
     */
    public static function get_object($coursework_id, $key, $params) {
        if (!isset(self::$pool[$coursework_id])) {
            self::fill_pool_coursework($coursework_id);
        }
        $value_key = implode('-', $params);
        return self::$pool[$coursework_id][$key][$value_key][0] ?? false;
    }

    /**
     *
     */
    protected function post_save_hook() {
        $courseworkid = $this->get_submission()->courseworkid;
        self::remove_cache($courseworkid);
    }

    /**
     *
     */
    protected function after_destroy() {
        $courseworkid = $this->get_submission()->courseworkid;
        self::remove_cache($courseworkid);
    }
}

