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
 * Class to represent a single item of moderation agreement
 *
 * @property mixed stage_identifier
 * @property int feedback_manager
 */
class moderation extends table_base{


    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
   public $feedbackid;


    /**
 * @var int
 */
    public $moderatorid;

    /**
     * @var int
     */
    public $stage_identifier = 'moderator';
    /**
     * @var string
     */

    public $agreement;

    /**
     * @var string
     */
    protected static $table_name = 'coursework_mod_agreements';

    /**
     * @var string
     */
    public $modcomment;

    /**
     * @var int
     */
    public $modcommentformat;


    /**
     * Chained getter for loose coupling.
     *
     * @return coursework
     */
    public function get_coursework() {
        return $this->get_submission()->get_coursework();
    }


    /**
     *
     */
    public function get_feedback(){
        global $DB;

        //Moderation done only for single courseworks so submission id to retrieve feedback is enough
        $params = array('id'=>$this->feedbackid);
        $feedback = $DB->get_record('coursework_feedbacks', $params);
        return $feedback;

    }

    public function get_agreement(){
        return $this->agreement;


    }


    /**
     * Memoized getter
     *
     * @return bool|submission
     */
    public function get_submission() {
       $feedback =  $this->get_feedback();
       $this->submission = submission::find($feedback->submissionid);

        return $this->submission;
    }



    /**
     * @return user
     */
    public function moderator() {
        return user::find($this->moderatorid);
    }

    /**
     * Real name for display. Allows us to defer the DB call to retrieve first and last name
     * in case we don't need it.
     */
    public function get_moderator_username() {

        if (!empty($this->lasteditedby)) {
            $this->moderator = core_user::get_user($this->lasteditedby);
        }

        return fullname($this->moderator);
    }

    public function get_moderator_id(){
        return $this->moderator->id;
    }


    /**
     * Check if assessor is allocated to the user in this stage
     * @return bool
     */
    public function is_moderator_allocated(){

        return $this->get_stage()->assessor_has_allocation($this->get_allocatable());
    }

    /**
     * @return \mod_coursework\allocation\allocatable
     */
    public function get_allocatable() {
        return $this->get_submission()->get_allocatable();
    }

    /**
     * @return stage_base
     */
    public function get_stage() {
        return $this->get_coursework()->get_stage('moderator');
    }

    /**
     * @param  $feedback
     * @return moderation|null
     */
    public static function get_moderator_agreement($feedback) {
        global $DB;

        $params = array('feedbackid' => $feedback->id);

        // Should only ever be one that has the particular combination of these three options.
        $moderation = $DB->get_record('coursework_mod_agreements', $params);

        if (is_object($moderation)) {
            return new moderation($moderation);
        }
        return null;
    }

}