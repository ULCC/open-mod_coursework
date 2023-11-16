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
use stdClass;

require_once($CFG->libdir.'/formslib.php');

/**
 * Simple form providing a grade and comment area that will feed straight into the feedback table so
 * that the final comment for the gradebook can be added.
 */
class advance_plugins_form extends moodleform {

    /**
     * Makes the form elements.
     */
    public function definition() {

        $mform =& $this->_form;

        $mform->addElement('editor', 'text_element', get_string('comment', 'mod_coursework'), array());
        $mform->setType('editor', PARAM_RAW);

        $file_manager_options =  array(
            'subdirs' => false,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL
        );
        $this->_form->addElement('filemanager',
            'file_element',
            '',
            null,
            $file_manager_options);

    }
}

