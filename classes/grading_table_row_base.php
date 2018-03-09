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

namespace mod_coursework;

use html_writer;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use moodle_url;

defined('MOODLE_INTERNAL') || die();


/**
 * Refactoring the grading table to clarify the logic. There will be two subclasses of this -
 * one for single row tables and one for multi-row tables. These classes contain all the business
 * logic relating the what ought to be rendered. The renderer methods then decide how the decision
 * will be translated into a page.
 */
abstract class grading_table_row_base implements user_row {

    /**
     * Using this as a delegate
     * @var submission
     */
    protected $submission;

    /**
     * @var models\coursework
     */
    protected $coursework;

    /**
     * @var allocatable user record
     */
    protected $allocatable;

    /**
     * Constructor
     *
     * @param \mod_coursework\models\coursework $coursework $coursework
     * @param allocatable $user
     */
    public function __construct(models\coursework $coursework, $user) {
        $this->coursework = $coursework;
        $this->allocatable = $user;
    }

    /**
     * Gets the grade agreed by the markers based ont he component marks. Not capped!
     * Chained getter for loose coupling.
     *
     * @return int
     */
    public function get_final_grade() {

        $submission = $this->get_submission();

        if (!$submission) {
            return '';
        }
        return $submission->get_final_grade();
    }

    /**
     * @return bool
     */
    public function is_published() {
        if (!$this->get_submission()) {
            return false;
        }
        return $this->get_submission()->is_published();
    }

    /**
     * Will return the username if permissions allow, otherwise, an anonymous placeholder. Can't delegate to the similar
     * submission::get_user_name() function as there may not be a submission.
     *
     * @param bool $link
     * @throws \coding_exception
     * @return string
     */
    public function get_user_name($link = false) {

        global $DB;

        $viewanonymous = has_capability('mod/coursework:viewanonymous', $this->get_coursework()->get_context());
        if (!$this->get_coursework()->blindmarking || $viewanonymous || $this->is_published()) {
            $user = $DB->get_record('user', array('id' => $this->get_allocatable_id()));
            $fullname = fullname($user);
            $allowed = has_capability('moodle/user:viewdetails', $this->get_coursework()->get_context());
            if ($link && $allowed) {
                $url = new moodle_url('/user/view.php', array('id' => $this->get_allocatable_id(),
                                                              'course' => $this->get_coursework()->get_course_id()));
                return html_writer::link($url, $fullname);
            } else {
                return $fullname;
            }
        } else {
            return get_string('hidden', 'mod_coursework');
        }
    }

    /**
     * Returns the id of the student who's submission this is
     *
     * @return mixed
     */
    public function get_allocatable_id() {
        return $this->get_allocatable()->id;
    }

    /**
     * Getter for submission timesubmitted.
     *
     * @return int
     */
    public function get_time_submitted() {

        $submission = $this->get_submission();

        if (!$submission) {
            return '';
        }
        return $submission->time_submitted();

    }

    /**
     * Getter for personal deadline time
     * 
     * @return int|mixed|string
     */
    public function get_personal_deadlines() {
        global $DB;

        $allocatable = $this->get_allocatable();

        if (!$allocatable) {
            return '';
        }

        $personal_deadline = $DB->get_record('coursework_person_deadlines',
                                            array('courseworkid' => $this->get_coursework()->id,
                                                  'allocatableid' => $allocatable->id(),
                                                  'allocatabletype'=>  $allocatable->type()));
        if ($personal_deadline){
            $personal_deadline = $personal_deadline->personal_deadline;
        } else {
            $personal_deadline = $this->get_coursework()->deadline;
        }
        
        return  $personal_deadline;
    }



    /**
     * Returns the hash used to name files anonymously for this user/coursework combination
     */
    public function get_filename_hash() {
        return $this->get_coursework()->get_username_hash($this->get_allocatable_id());
    }

    /**
     * Returns the id of the coursework instance.
     *
     * @return mixed
     */
    public function get_coursework_id() {
        return $this->get_coursework()->id;
    }

    /**
     * Returns the id of the coursework instance.
     *
     * @return coursework
     */
    public function get_coursework() {
        return $this->coursework;
    }

    /**
     * @return models\submission
     */
    public function get_submission() {

        if (!isset($this->submission)) {

            $params = array(
                'courseworkid' => $this->get_coursework_id(),
                'allocatableid' => $this->get_allocatable()->id(),
                'allocatabletype' => $this->get_allocatable()->type(),
            );
            $this->submission = submission::find($params);
        }

        return $this->submission;
    }

    /**
     * Chained getter to prevent tight coupling.
     *
     * @return string|submission_files empty string if no submission
     */
    public function get_submission_files() {
        if (!$this->get_submission()) {
            return '';
        }
        return $this->get_submission()->get_submission_files();
    }

    /**
     * Chained getter to prevent tight coupling.
     *
     * @return int
     */
    public function get_course_module_id() {
        return $this->get_coursework()->get_coursemodule_id();
    }

    /**
     * @return mixed
     */
    public function get_submission_id() {
        if (!$this->get_submission()) {
            return 0;
        }
        return $this->get_submission()->id;
    }

    /**
     * Tells us if anything has been submitted yet.
     *
     * @return bool
     */
    public function has_submission() {
        $submission = $this->get_submission();
        return !empty($submission);
    }

    /**
     * Empty - the subclass will override if needed. Really only here for Liskov principle.
     * @return assessor_feedback_table|bool
     */
    public function get_assessor_feedback_table() {
        return false;
    }

    /**
     * Checks to see whether we should show the current user who this student is.
     */
    public function can_view_username() {

        if (has_capability('mod/coursework:viewanonymous', $this->get_coursework()->get_context())) {
            return true;
        }

        if ($this->get_coursework()->blindmarking) {
            return false;
        }

        return true;
    }

    /**
     * Tells us whether this user has a final grade yet.
     *
     * @return bool|int|null
     */
    public function has_final_agreed_grade() {
        if (!$this->get_submission()) {
            return false;
        }
        return $this->get_submission()->has_final_agreed_grade();
    }

    /**
     * @return string
     */
    public function get_student_firstname() {

        global $DB;

        $allocatable = $this->get_allocatable();
        if (empty($allocatable->firstname)) {
            $this->allocatable =  user::find($allocatable);
        }

        return $this->get_allocatable()->firstname;
    }

    /**
     * @return string
     */
    public function get_student_lastname() {

        global $DB;

        $allocatable = $this->get_allocatable();
        if (empty($allocatable->lastname)) {
            $this->allocatable =  user::find($allocatable);
        }

        return $this->get_allocatable()->lastname;
    }

    /**
     * @return allocatable
     */
    public function get_allocatable() {
        return $this->allocatable;
    }

    /**
     * Tells us whether this submission has any feedback
     *
     * @return bool|int|null
     */
    public function has_feedback() {
        if (!$this->get_submission()) {
            return false;
        }
        return $this->get_submission()->get_assessor_feedbacks();
    }

    /**
     * @return models\feedback
     */
    public function get_single_feedback(){
        return $this->get_submission()->get_assessor_feedback_by_stage('assessor_1');
    }

}
