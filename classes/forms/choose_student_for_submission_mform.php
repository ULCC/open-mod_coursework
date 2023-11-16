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
 *
 * @package
 * @subpackage
 * @copyright  2012 Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

use mod_coursework\models\coursework;
use moodleform;

defined('MOODLE_INTERNAL' || die());

global $CFG;

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Makes a simple dropdown that allows us to choose the student who the manager is going to submit work on behalf of.
 */
class choose_student_for_submission_mform extends moodleform {

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {

        $mform =& $this->_form;

        /* @var coursework $coursework */
        $coursework = $this->_customdata->coursework;
        $students = $coursework->get_unfinalised_students();

        if (empty($students)) {
            echo get_string('nounfinalisedstudents', 'mod_coursework');
            return;
        }

        $options = array();
        $allnames = \core_user\fields::get_name_fields();

        foreach ($students as $student) {

            // We use fullname($this), which needs these. It doesn't use them though.
            foreach ($allnames as $namefield) {
                if (!isset($student->$namefield)) {
                    $student->$namefield = '';
                }
            }

            $options[$student->id] = fullname($student);
        }

        $mform->addElement('select', 'userid', get_string('studenttosubmitfor', 'mod_coursework'), $options);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $this->add_action_buttons(true, get_string('choosestudent', 'mod_coursework'));

    }
}