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
 * Creates an mform for moderator agreement
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

global $CFG;

use coding_exception;

use mod_coursework\models\moderation;
use moodleform;

require_once($CFG->libdir.'/formslib.php');

/**
 * Simple form providing a moderation agreement and comment area that will feed straight into the moderation_agreement table
 */
class moderator_agreement_mform extends moodleform {

    /**
     * @var int the id of the submission that the grade pertains to
     */
    public $submission_id;

    /**
     * @var int
     */
    public $moderatorid;

    /**
     * Makes the form elements.
     */
    public function definition() {

        $mform =& $this->_form;

        /**
         * @var $moderation moderation
         */
        $moderation = $this->_customdata['moderation'];
        $feedback = $moderation->get_feedback();
        $coursework = $moderation-> get_coursework();

        $mform->addElement('hidden', 'submissionid', $moderation->get_submission()->id);
        $mform->setType('submissionid', PARAM_INT);


        $mform->addElement('hidden', 'moderatorid', $moderation->moderatorid);
        $mform->setType('moderatorid', PARAM_INT);

        $mform->addElement('hidden', 'stage_identifier', $moderation->stage_identifier);
        $mform->setType('stage_identifier', PARAM_ALPHANUMEXT);

        $mform->addElement('hidden', 'feedbackid', $feedback->id);
        $mform->setType('feedbackid', PARAM_INT);

        $mform->addElement('hidden', 'moderationid', $moderation->id);
        $mform->setType('moderationid', PARAM_INT);

        $mform->addElement('hidden', 'courseworkid', $coursework->id);
        $mform->setType('courseworkid', PARAM_INT);


        // moderator agreement
        $options = array('agreed'=>get_string('agreed', 'coursework'), 'disagreed'=>get_string('disagreed', 'coursework'));
        $mform->addElement('select', 'agreement',
                            get_string('moderationagreement', 'coursework'),
                            $options,
                            array('id' => 'moderation_agreement'));


        $mform->addElement('editor', 'modcomment', get_string('comment', 'mod_coursework'), array('id' => 'moderation_comment'));
        $mform->setType('editor', PARAM_RAW);


        $this->add_action_buttons();
    }

    /**
     * This is just to grab the data and add it to the feedback object.
     *
     * @param moderation $moderation
     * @return moderation
     */
    public function process_data(moderation $moderation) {

        $formdata = $this->get_data();

        $moderation->agreement = $formdata->agreement;
        $moderation->feedbackid = $formdata->feedbackid;
        $moderation->courseworkid = $formdata->courseworkid;
        $moderation->modcomment = $formdata->modcomment['text'];
        $moderation->modcommentformat = $formdata->modcomment['format'];


        return $moderation;
    }
}

