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
 * @copyright  2016 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\coursework;
use mod_coursework\allocation;


require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $DB, $PAGE, $OUTPUT;

require_once($CFG->dirroot.'/mod/coursework/classes/forms/upload_allocations_form.php');

require_once($CFG->libdir.'/csvlib.class.php');


$coursemoduleid = required_param('cmid', PARAM_INT);

$coursemodule = $DB->get_record('course_modules', array('id' => $coursemoduleid));
$coursework = coursework::find($coursemodule->instance);
$course = $DB->get_record('course', array('id' => $coursemodule->course));
require_login($course, false, $coursemodule);

$csvtype = 'allocationsupload';
$title = get_string($csvtype, 'coursework');
$PAGE->set_url(new moodle_url('/mod/coursework/actions/upload_allocations.php'));
$PAGE->set_title($title);
$PAGE->set_heading($title);

$grading_sheet_capabilities = array('mod/coursework:allocate');


// Bounce anyone who shouldn't be here.
if (!has_any_capability($grading_sheet_capabilities, $PAGE->context)) {
    $message = 'You do not have permission to upload allocations';
    redirect(new moodle_url('mod/coursework/view.php'), $message);
}



$allocationsuploadform    =   new upload_allocations_form($coursemoduleid);

if ($allocationsuploadform->is_cancelled()) {
    redirect("$CFG->wwwroot/mod/coursework/view.php?id=$coursemoduleid");
}



if ($data   =   $allocationsuploadform->get_data())   {

    //perform checks on data

    $content = $allocationsuploadform->get_file_content('allocationsdata');

    $csvimport   =  new \mod_coursework\allocation\upload($coursework);

    $procsessingresults =  $csvimport->validate_csv($content, $data->encoding, $data->delimiter_name);

    //process
    $csvimport->process_csv($content, $data->encoding, $data->delimiter_name, $procsessingresults);
    $page_renderer = $PAGE->get_renderer('mod_coursework', 'page');
    echo $page_renderer->process_csv_upload($procsessingresults, $content, $csvtype);

    $cache = \cache::make('mod_coursework', 'courseworkdata', ['id' => $coursework->id]);
    $cache->purge();

} else {
    $page_renderer = $PAGE->get_renderer('mod_coursework', 'page');
    echo $page_renderer->csv_upload($allocationsuploadform, $csvtype);
}