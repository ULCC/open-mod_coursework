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
 * Prints a particular instance of coursework
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache"); // HTTP/1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

use mod_coursework\models\submission;
use mod_coursework\warnings;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

global $CFG, $DB, $PAGE, $COURSE, $OUTPUT, $USER, $SESSION;

require_once($CFG->dirroot . '/mod/coursework/lib.php');
require_once($CFG->dirroot . '/mod/coursework/renderer.php');
require_once($CFG->dirroot . '/lib/completionlib.php');
require_once($CFG->dirroot . '/lib/plagiarismlib.php');
require_once($CFG->dirroot . '/lib/editor/tinymce/lib.php');
require_once($CFG->dirroot . '/mod/coursework/classes/export/csv.php');

// TODO move all js to renderer.
$jsmodule = array(
    'name' => 'mod_coursework',
    'fullpath' => '/mod/coursework/module.js',
    'requires' => array('base',
                        'node-base'),
    'strings' => array()
);

$tiny_mce = new tinymce_texteditor();
$PAGE->requires->js('/lib/editor/tinymce/tiny_mce/' . $tiny_mce->version . '/tiny_mce.js');
// Course_module ID, or coursework instance ID - it should be named as the first character of the module.
$course_module_id = optional_param('id', 0, PARAM_INT);
$coursework_id = optional_param('e', 0, PARAM_INT);
// Hacky fix for the need for the form to self-submit to this page.
if (!$coursework_id) {
    $coursework_id = optional_param('courseworkid', 0, PARAM_INT);
}
$publish = optional_param('publishbutton', 0, PARAM_ALPHA);
$download = optional_param('download', false, PARAM_BOOL);
$resubmit = optional_param('resubmit', 0, PARAM_TEXT); // Are we resubmitting a turnitin thing?
$resubmitted = optional_param('resubmitted', 0, PARAM_INT); // Is this a post-resubmit redirect?
$submissionid = optional_param('submissionid', 0, PARAM_INT); // Which thing to resubmit.
$confirm = optional_param('confirm', 0, PARAM_INT);
$export_grades = optional_param('export', false, PARAM_BOOL);
$download_grading_sheet = optional_param('export_grading_sheet', false, PARAM_BOOL);


if (!isset($SESSION->displayallstudents[$course_module_id])) {
    $SESSION->displayallstudents[$course_module_id] = optional_param('displayallstudents', false, PARAM_BOOL);

    $displayallstudents = $SESSION->displayallstudents[$course_module_id];
} else {
    $displayallstudents = optional_param('displayallstudents', $SESSION->displayallstudents[$course_module_id], PARAM_INT);
    $SESSION->displayallstudents[$course_module_id] = $displayallstudents;
}


// If a session variable holding page preference for the specific coursework is not set, set default value (0).
if (isset($SESSION->perpage[$course_module_id]) && optional_param('per_page', 0, PARAM_INT) != $SESSION->perpage[$course_module_id]
    && optional_param('per_page', 0, PARAM_INT) != 0){ // prevent blank pages if not in correct page
    $page = 0;
    $SESSION->page[$course_module_id] = $page;
} else if (!(isset($SESSION->page[$course_module_id]))) {
    $SESSION->page[$course_module_id] = optional_param('page', 0, PARAM_INT);
    $page = $SESSION->page[$course_module_id];
} else {
    $page = optional_param('page', $SESSION->page[$course_module_id], PARAM_INT);
    $SESSION->page[$course_module_id] = $page;
}

// If a session variable holding perpage preference for the specific coursework is not set, set default value (grab default value from global setting).
if (!(isset($SESSION->perpage[$course_module_id]))) {
    $SESSION->perpage[$course_module_id] = optional_param('per_page', $CFG->coursework_per_page, PARAM_INT);
    $perpage = $SESSION->perpage[$course_module_id];
} else {
    $perpage = optional_param('per_page', $SESSION->perpage[$course_module_id], PARAM_INT);
    $SESSION->perpage[$course_module_id] = $perpage;
}


// If a session variable holding sortby preference for the specific coursework is not set, set default value ('lastname').
if (!(isset($SESSION->sortby[$course_module_id]))) {
    $SESSION->sortby[$course_module_id] = optional_param('sortby', 'lastname', PARAM_ALPHA);
    $sortby = $SESSION->sortby[$course_module_id];
} else {
    $sortby = optional_param('sortby', $SESSION->sortby[$course_module_id], PARAM_ALPHA);
    $SESSION->sortby[$course_module_id] = $sortby;
}

// If a session variable holding sorthow preference for the specific coursework is not set, set default value ('ASC').
if (!(isset($SESSION->sorthow[$course_module_id]))) {
    $SESSION->sorthow[$course_module_id] = optional_param('sorthow', 'ASC', PARAM_ALPHA);
    $sorthow = $SESSION->sorthow[$course_module_id];
} else {
    $sorthow = optional_param('sorthow', $SESSION->sorthow[$course_module_id], PARAM_ALPHA);
    $SESSION->sorthow[$course_module_id] = $sorthow;
}


//we will use the same defaults as page (above) defaulting to page setting if no specific viewallstudents_page has been set
if (isset($SESSION->viewallstudents_perpage[$course_module_id]) && optional_param('viewallstudents_per_page', 0, PARAM_INT) != $SESSION->viewallstudents_perpage[$course_module_id]
    && optional_param('viewallstudents_per_page', 0, PARAM_INT) != 0){ // prevent blank pages if not in correct page
    $viewallstudents_page = 0;
    $SESSION->viewallstudents_page[$course_module_id] = $viewallstudents_page;
} else if (!(isset($SESSION->viewallstudents_page[$course_module_id]))) {
    $SESSION->viewallstudents_page[$course_module_id] =  optional_param('viewallstudents_page', 0, PARAM_INT);
    $viewallstudents_page = $SESSION->viewallstudents_page[$course_module_id];
} else {
    $viewallstudents_page = optional_param('viewallstudents_page', $SESSION->page[$course_module_id], PARAM_INT);
    $SESSION->viewallstudents_page[$course_module_id] = $viewallstudents_page;
}

//we will use the same defaults as perpage (above) defaulting to perpage setting if no specific viewallstudents_perpage has been set
if (!(isset($SESSION->viewallstudents_perpage[$course_module_id]))) {
    $SESSION->viewallstudents_perpage[$course_module_id] = optional_param('viewallstudents_per_page', $perpage, PARAM_INT);
    $viewallstudents_perpage = $SESSION->viewallstudents_perpage[$course_module_id];
} else {
    $viewallstudents_perpage = optional_param('viewallstudents_per_page', $SESSION->perpage[$course_module_id], PARAM_INT);
    $SESSION->viewallstudents_perpage[$course_module_id] = $viewallstudents_perpage;
}

//we will use the same defaults as sortby (above) defaulting to sortby setting if no specific viewallstudents_sortby has been set
if (!(isset($SESSION->viewallstudents_sortby[$course_module_id]))) {
    $SESSION->viewallstudents_sortby[$course_module_id] = optional_param('viewallstudents_sortby', $sortby, PARAM_ALPHA);
    $viewallstudents_sortby = $SESSION->viewallstudents_sortby[$course_module_id];
} else {
    $viewallstudents_sortby = optional_param('viewallstudents_sortby', $SESSION->sortby[$course_module_id], PARAM_ALPHA);
    $SESSION->viewallstudents_sortby[$course_module_id] = $viewallstudents_sortby;
}

//we will use the same defaults as sorthow (above) defaulting to sorthow setting if no specific viewallstudents_sorthow has been set
if (!(isset($SESSION->viewallstudents_sorthow[$course_module_id]))) {
    $SESSION->viewallstudents_sorthow[$course_module_id] = optional_param('viewallstudents_sorthow', $sorthow, PARAM_ALPHA);
    $viewallstudents_sorthow = $SESSION->viewallstudents_sorthow[$course_module_id];
} else {
    $viewallstudents_sorthow = optional_param('viewallstudents_sorthow', $SESSION->sorthow[$course_module_id], PARAM_ALPHA);
    $SESSION->viewallstudents_sorthow[$course_module_id] = $viewallstudents_sorthow;
}



if (!($sorthow === 'ASC' || $sorthow === 'DESC')) {
    $sorthow = 'ASC';
}

$coursework_record = new stdClass();

if ($course_module_id) {
    $course_module = get_coursemodule_from_id('coursework',
                                              $course_module_id,
                                              0,
                                              false,
                                              MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $course_module->course), '*', MUST_EXIST);
    $coursework_record = $DB->get_record('coursework',
                                         array('id' => $course_module->instance),
                                         '*',
                                         MUST_EXIST);
} else {
    if ($coursework_id) {
        $coursework_record = $DB->get_record('coursework',
                                             array('id' => $coursework_id),
                                             '*',
                                             MUST_EXIST);
        $course = $DB->get_record('course',
                                  array('id' => $coursework_record->course),
                                  '*',
                                  MUST_EXIST);
        $course_module = get_coursemodule_from_instance('coursework',
                                                        $coursework_record->id,
                                                        $course->id,
                                                        false,
                                                        MUST_EXIST);
    } else {
        die('You must specify a course_module ID or an instance ID');
    }
}

$coursework = mod_coursework\models\coursework::find($coursework_record);

// commented out the redirection for Release1 #108535552, this will be revisited for Release2
/*if (has_capability('mod/coursework:allocate', $coursework->get_context()))   {
    $warnings = new \mod_coursework\warnings($coursework);

    $percentage_allocation_not_complete = $warnings->percentage_allocations_not_complete();
    $manual_allocation_not_complete = '';
    if ($coursework->allocation_enabled()){
        $manual_allocation_not_complete = $warnings->manual_allocation_not_completed();
    }


    if (!empty($percentage_allocation_not_complete) || !empty($manual_allocation_not_complete))     {

        $redirectdetail =   new \stdClass();
        $redirectdetail->percentage     =   $percentage_allocation_not_complete;
        $redirectdetail->manual         =   $manual_allocation_not_complete;

        redirect($CFG->wwwroot.'/mod/coursework/actions/allocate.php?id='.$course_module_id,get_string('configuration_needed','coursework',$redirectdetail));
    }
}*/

// change default sortby to Date (timesubmitted) if CW is set to blind marking and a user doesn't have capability to view anonymous
$viewanonymous = has_capability('mod/coursework:viewanonymous', $coursework->get_context());
if (($coursework->blindmarking && !$viewanonymous )) {
    $sortby = optional_param('sortby', 'timesubmitted', PARAM_ALPHA);
}

// Make sure we sort out any stuff that cron should have done, just in case it's not run yet.
if (($coursework->has_deadline() && $coursework->deadline_has_passed()) || $coursework->personal_deadlines_enabled()) {
    $coursework->finalise_all();
}

// This will set $PAGE->context to the coursemodule's context.
require_login($course, true, $course_module);

// Name of new zip file.
$filename = str_replace(' ', '_', clean_filename($COURSE->shortname . '-' . $coursework->name . '.zip'));
if ($download && $zip_file = $coursework->pack_files()) {
    send_temp_file($zip_file, $filename); // Send file and delete after sending.
}


if ($export_grades){

    // headers and data for csv
    $csv_cells = array('name','username');
    
    if ($coursework->personal_deadlines_enabled()){
        $csv_cells[] = 'personaldeadline';
    }

    $csv_cells[] = 'submissiondate';
    $csv_cells[] = 'submissiontime';
    $csv_cells[] = 'submissionfileid';

    if ($coursework->extensions_enabled()){
        $csv_cells[] = 'extensiondeadline';
        $csv_cells[] = 'extensionreason';
        $csv_cells[] = 'extensionextrainfo';
    }
    $csv_cells[] = 'stages';

    if ($coursework->moderation_agreement_enabled()){
        $csv_cells[] = 'moderationagreement';
    }
    $csv_cells[] = 'finalgrade';


    $timestamp = date('d_m_y @ H-i');
    $filename = get_string('finalgradesfor', 'coursework'). $coursework->name .' '.$timestamp;
    $csv = new \mod_coursework\export\csv($coursework, $csv_cells, $filename);
    $csv->export();


}

if ($download_grading_sheet){

   $csv_cells =  \mod_coursework\export\grading_sheet::cells_array($coursework);

    $timestamp = date('d_m_y @ H-i');
    $filename = get_string('gradingsheetfor', 'coursework'). $coursework->name .' '.$timestamp;
    $grading_sheet = new \mod_coursework\export\grading_sheet($coursework, $csv_cells, $filename);
    $grading_sheet->export();
}

$can_grade = has_capability('mod/coursework:addinitialgrade', $PAGE->context);
$can_submit = has_capability('mod/coursework:submit', $PAGE->context);
$can_view_students = false;

// TODO this is awful.
$capabilities = array('addinstance',
                      'submitonbehalfof',
                      'addinitialgrade',
                      'editinitialgrade',
                      'addagreedgrade',
                      'editagreedgrade',
                      'publish',
                      'viewanonymous',
                      'revertfinalised',
                      'allocate',
                      'viewallgradesatalltimes',
                      'administergrades',
                      'grantextensions',
                      'canexportfinalgrades',
                      'viewextensions',
                      'grade');

foreach ($capabilities as $capability) {

    if (has_capability('mod/coursework:' . $capability, $PAGE->context)) {
        $can_view_students = true;
        break;
    }
}

if ((float)substr($CFG->release, 0, 5) > 2.6) { // 2.8 > 2.6
    $event = \mod_coursework\event\course_module_viewed::create(array(
                                                                    'objectid' => $coursework->id,
                                                                    'context' => $coursework->get_context(),
                                                                ));
    $event->trigger();
} else {
    add_to_log($course->id,
               'coursework',
               'view',
               "view.php?id=$course_module->id",
               $coursework->name,
               $course_module->id);
}

// Print the page header.

// sort group by groupname (default)
if ($coursework->is_configured_to_have_group_submissions()){
    $sortby = optional_param('sortby', 'groupname', PARAM_ALPHA);
    $viewallstudents_sortby = optional_param('viewallstudents_sortby', 'groupname', PARAM_ALPHA);

}
$params = array('id' => $course_module->id,
    'sortby' => $sortby,
                'sorthow' => $sorthow,
                'per_page' => $perpage);

if (!empty($SESSION->displayallstudents[$course_module_id]))   {
    $params['viewallstudents_sorthow']  =   $viewallstudents_sorthow;
    $params['viewallstudents_sortby']  =   $viewallstudents_sortby;
    $params['viewallstudents_per_page']  =   $viewallstudents_perpage;
}


$PAGE->set_url('/mod/coursework/view.php', $params);
$PAGE->set_title($coursework->name);
$PAGE->set_heading($course->shortname);

//$PAGE->set_button($OUTPUT->update_module_button($course_module->id, 'coursework')); // deprecated from 3.2 (MDL-53765 core)

// Auto publish after the deadline
if ($coursework->has_individual_autorelease_feedback_enabled() &&
    $coursework->individual_feedback_deadline_has_passed() &&
    $coursework->has_stuff_to_publish()
) {

    $coursework->publish_grades();
}



//Create automatic feedback
if ($coursework->automaticagreement_enabled())  {
    $coursework->create_automatic_feedback();

}

// Output starts here.
$html = '';

/**
 * @var mod_coursework_object_renderer $object_renderer
 */
$object_renderer = $PAGE->get_renderer('mod_coursework', 'object');
/**
 * @var mod_coursework_page_renderer $page_renderer
 */
$page_renderer = $PAGE->get_renderer('mod_coursework', 'page');

// Hook to allow plagiarism plugins to update status/print links.
$loginlink = plagiarism_update_status($course, $course_module);

// Only show the 'login to Turnitin as teacher' link to actual teachers.
// TODO probably more capabilities need this
if (has_any_capability(array('mod/coursework:addinitialgrade',
                             'mod/coursework:addagreedgrade'),
                       $coursework->get_context())) {
    $html .= $loginlink;
}

$html .= $object_renderer->render(new mod_coursework_coursework($coursework));

// Allow tutors to upload files as part of the coursework task? Easily done via the main
// course thing, so not necessary.

// If this is a student, show the submission form, or their existing submission, or both
// There is scope for an arbitrary number of files to be added here, before the deadline.
if ($can_submit && !$can_grade) {
    $html .= $page_renderer->student_view_page($coursework, \mod_coursework\models\user::find($USER));
}

// Display the submissions table of all the students instead.
if ($can_view_students) {

    // If the resubmit button was pressed (for plagiarism), we need to fire a new event.
    if ($resubmit && $submissionid) {

        /**
         * @var submission $submission
         */
        $submission = submission::find($submissionid);

        $params = array(
            'cm' => $course_module->id,
            'userid' => $submission->get_author_id(),
        );
        // Get the hash so we can retrieve the file and update timemodified.
        $filehash = $DB->get_field('plagiarism_turnitin_files', 'identifier', $params);

        // Remove the turnitin file, which may have hit the limit for retries ages ago.
        $DB->delete_records('plagiarism_turnitin_files', $params);

        // If Turnitin is enabled after a file has been submitted, it'll fail because it uses the file
        // timemodified as the submission date. We need to prevent this, so we alter the file timemodified
        // to be now and rely on the submission timemodified date to tell us when the student finalised their
        // work.
        if ($filehash) {
            $params = array(
                'pathnamehash' => $filehash
            );
            $file = $DB->get_record('files', $params);
            $file->timemodified = time();
            $DB->update_record('files', $file);
        }
        $submission->submit_plagiarism('final'); // Must happen AFTER files have been updated.
        redirect($PAGE->url, get_string('resubmitted', 'coursework', $submission->get_allocatable_name()));
    }

    // If publish button was pressed, update the gradebook after confirmation.
    if ($publish && has_capability('mod/coursework:publish', $PAGE->context)) {

        if (!$confirm) {

            // Ask the user for confirmation.
            $confirmurl = clone $PAGE->url;
            $confirmurl->param('confirm', 1);
            $confirmurl->param('publishbutton', 'true');
            $html = $OUTPUT->confirm(get_string('confirmpublish', 'mod_coursework'), $confirmurl, $PAGE->url);
        } else {
            // Already confirmed. Publish and redirect.
            $coursework->publish_grades();
            $url = clone($PAGE->url);
            $url->remove_params(array('confirm',
                                      'publishbutton'));
            redirect($url, get_string('gradespublished', 'mod_coursework'));
        }
    } else {

        $html .= $page_renderer->teacher_grading_page($coursework, $page, $perpage, $sortby, $sorthow);
        $html .= $page_renderer->non_teacher_allocated_grading_page($coursework,$viewallstudents_page,$viewallstudents_perpage,$viewallstudents_sortby,$viewallstudents_sorthow,$displayallstudents);

    }
}

echo $OUTPUT->header();
echo $html;
// Finish the page.
echo $OUTPUT->footer();

