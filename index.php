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
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/// Replace coursework with the name of your module and remove this line

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

global $CFG, $DB, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);   // course

if (! $course = $DB->get_record('course', array('id' => $id))) {
    error('Course ID is incorrect');
}

require_course_login($course);

if ((float)substr($CFG->release, 0, 5) > 2.6) { // 2.8 > 2.6
    $event = \mod_coursework\event\course_module_instance_list_viewed::create(array('context' => context_course::instance($course->id)));
    $event->trigger();
} else {
    add_to_log($course->id,
               'coursework',
               'view',
               "view.php?id=$course_module->id",
               $coursework->name,
               $course_module->id);
}

// Print the header.

$PAGE->set_url('/mod/coursework/view.php', array('id' => $id));
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->shortname);
$PAGE->set_pagelayout('incourse');
echo $OUTPUT->header();

// Get all the appropriate data.

if (! $courseworks = get_all_instances_in_course('coursework', $course)) {
    echo $OUTPUT->heading(get_string('nocourseworks', 'coursework'), 2);
    echo $OUTPUT->continue_button("view.php?id=$course->id");
    echo $OUTPUT->footer();
    die();
}


echo $OUTPUT->heading(get_string('modulenameplural', 'coursework'), 2);
$page_renderer = $PAGE->get_renderer('mod_coursework', 'page');
echo $page_renderer->view_course_index($course->id);
echo $OUTPUT->footer();