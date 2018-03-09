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
 * Creates an mform for final grade
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

global $CFG;

use coding_exception;
use gradingform_rubric_instance;
use mod_coursework\models\feedback;
use moodleform;

require_once($CFG->libdir.'/formslib.php');

/**
 * Simple form providing a grade and comment area that will feed straight into the feedback table so
 * that the final comment for the gradebook can be added.
 */
class assessor_feedback_mform extends moodleform {

    /**
     * @var int the id of the submission that the grade pertains to
     */
    public $submission_id;

    /**
     * @var int
     */
    public $assessorid;

    /**
     * Makes the form elements.
     */
    public function definition() {

        $mform =& $this->_form;

        /**
         * @var $feedback feedback
         */
        $feedback = $this->_customdata['feedback'];
        $coursework = $feedback->get_coursework();

        $mform->addElement('hidden', 'submissionid', $feedback->submissionid);
        $mform->setType('submissionid', PARAM_INT);

        $mform->addElement('hidden', 'isfinalgrade', $feedback->isfinalgrade);
        $mform->setType('isfinalgrade', PARAM_INT);

        $mform->addElement('hidden', 'ismoderation', $feedback->ismoderation);
        $mform->setType('ismoderation', PARAM_INT);

        $mform->addElement('hidden', 'assessorid', $feedback->assessorid);
        $mform->setType('assessorid', PARAM_INT);

        $mform->addElement('hidden', 'feedbackid', $feedback->id);
        $mform->setType('feedbackid', PARAM_INT);

        $mform->addElement('hidden', 'stage_identifier', $feedback->stage_identifier);
        $mform->setType('stage_identifier', PARAM_ALPHANUMEXT);

        $grademenu = make_grades_menu($coursework->grade);

        if ($coursework->is_using_advanced_grading()) {
            $controller = $coursework->get_advanced_grading_active_controller();
            $gradinginstance = $controller->get_or_create_instance(0, $feedback->assessorid, $feedback->id);
            $mform->addElement('grading', 'advancedgrading', get_string('grade'), array('gradinginstance' => $gradinginstance));
        } else {
            $mform->addElement('select',
                               'grade',
                               get_string('grade'),
                               $grademenu,
                               array('id' => 'feedback_grade'));
        }

        // Useful to keep the overall comments even if we have a rubric or something. There may be a place
        // in the rubric for comments, but not necessarily an overall comment.
        $mform->addElement('editor', 'feedbackcomment', get_string('comment', 'mod_coursework'), array('id' => 'feedback_comment'));
        $mform->setType('editor', PARAM_RAW);

        $file_manager_options =  array(
            'subdirs' => false,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL
        );

        $uploadfilestring = get_string('uploadafile');
        $this->_form->addElement('filemanager',
                                 'feedback_manager',
                                 $uploadfilestring,
                                 null,
                                 $file_manager_options);


        $this->add_submit_buttons($coursework->draft_feedback_enabled());

    }

    /**
     * @param $draftenabled
     */
    public function add_submit_buttons($draftenabled){

        $button_array = array();

        if ($draftenabled) {
            $button_array[] = $this->_form->createElement('submit', 'submitfeedbackbutton', get_string('saveasdraft', 'coursework'));
        }


            $button_array[] =
                $this->_form->createElement('submit', 'submitbutton', get_string('saveandfinalise', 'coursework'));

        $button_array[] = $this->_form->createElement('cancel');
        $this->_form->addGroup($button_array, 'buttonar', '', array(' '), false);
        $this->_form->closeHeaderBefore('buttonar');

    }

    /**
     * This is just to grab the data and add it to the feedback object.
     *
     * @param feedback $feedback
     * @return feedback
     */
    public function process_data(feedback $feedback) {

        $formdata = $this->get_data();

        if ($feedback->get_coursework()->is_using_advanced_grading()) {
            $coursework = $feedback->get_coursework();
            $controller = $coursework->get_advanced_grading_active_controller();
            $gradinginstance = $controller->get_or_create_instance(0, $feedback->assessorid, $feedback->id);
            /**
             * @var gradingform_rubric_instance $grade
             */
            $feedback->grade = $gradinginstance->submit_and_get_grade($formdata->advancedgrading, $feedback->id);
        } else {
            $feedback->grade = $formdata->grade;
        }

        $feedback->feedbackcomment = $formdata->feedbackcomment['text'];
        $feedback->feedbackcommentformat = $formdata->feedbackcomment['format'];

        return $feedback;
    }

    /**
     * Saves any of the files that may have been attached. Needs a feedback that has an id.
     *
     * @param feedback $feedback
     * @throws coding_exception
     * @return bool|void
     */
    public function save_feedback_files(feedback $feedback) {

        if (!$feedback->persisted()) {
            throw new coding_exception('Must supply a feedback that has been persisted so we have an itemid to use');
        }

        $formdata = $this->get_data();

        file_save_draft_area_files($formdata->feedback_manager,
                                   $feedback->get_coursework()->get_context()->id,
                                   'mod_coursework',
                                   'feedback',
                                   $feedback->id);
    }


}

