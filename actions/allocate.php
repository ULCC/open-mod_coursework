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
 * Page that prints a table of all students and all markers so that first marker, second marker, moderators
 * etc can be allocated manually or automatically.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \mod_coursework\models\coursework;
use \mod_coursework\allocation\widget;

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $OUTPUT, $DB, $PAGE;

require_once($CFG->dirroot.'/mod/coursework/lib.php');

$coursemoduleid = required_param('id', PARAM_INT);
$coursemodule = get_coursemodule_from_id('coursework', $coursemoduleid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $coursemodule->course), '*', MUST_EXIST);
$coursework = $DB->get_record('coursework', array('id' => $coursemodule->instance), '*', MUST_EXIST);
$coursework = coursework::find($coursework);
$formsavebutton = optional_param('save', 0, PARAM_BOOL);
$samplingformsavebutton = optional_param('save_sampling', 0, PARAM_BOOL);
$allocateallbutton = optional_param('auto-allocate-all', 0, PARAM_BOOL);
$allocatenonmanualbutton = optional_param('auto-allocate-non-manual', 0, PARAM_BOOL);
$allocatenonallocatedbutton = optional_param('auto-allocate-non-allocated', 0, PARAM_BOOL);
$assessorallocationstrategy = optional_param('assessorallocationstrategy', false, PARAM_TEXT);

$moderationruletype = optional_param('addmodsetruletype', 0, PARAM_ALPHAEXT);
$deletemodsetrule = optional_param('delete-mod-set-rule', array(), PARAM_RAW) ;

// options used for pagination
// If a session variable holding page preference for the specific coursework is not set, set default value (0).
if (isset($SESSION->allocate_perpage[$coursemoduleid]) && (isset($SESSION->perpage[$coursemoduleid]) && optional_param('per_page', 0, PARAM_INT) != $SESSION->perpage[$coursemoduleid])
    && optional_param('per_page', 0, PARAM_INT) != 0){ // prevent blank pages if not in correct page
    $page = 0;
    $SESSION->allocate_page[$coursemoduleid] = $page;
} else if (!(isset($SESSION->allocate_page[$coursemoduleid]))) {
    $SESSION->allocate_page[$coursemoduleid] = optional_param('page', 0, PARAM_INT);
    $page = $SESSION->allocate_page[$coursemoduleid];
} else {
    $page = optional_param('page', $SESSION->allocate_page[$coursemoduleid], PARAM_INT);
    $SESSION->allocate_page[$coursemoduleid] = $page;
}

// If a session variable holding perpage preference for the specific coursework is not set, set default value (10).
if (!(isset($SESSION->allocate_perpage[$coursemoduleid]))) {
    $SESSION->allocate_perpage[$coursemoduleid] = optional_param('per_page', $CFG->coursework_per_page, PARAM_INT);
    $perpage = $SESSION->allocate_perpage[$coursemoduleid];
} else {
    $perpage = optional_param('per_page', $SESSION->allocate_perpage[$coursemoduleid], PARAM_INT);
    $SESSION->allocate_perpage[$coursemoduleid] = $perpage;
}

// SQL sort for allocation table.
$sortby = optional_param('sortby', '', PARAM_ALPHA);
$sorthow = optional_param('sorthow', '', PARAM_ALPHA);
$options = compact('sortby', 'sorthow','perpage','page');

// $_POST['allocatables'] comes as array of arrays which is not supported by optional_param_array, however we clean this later in process_data() function
$formdataarray = isset($_POST['allocatables']) ? $_POST['allocatables'] : array();

require_login($course, true, $coursemodule);

require_capability('mod/coursework:allocate', $PAGE->context, null, true, "Can't allocate here - permission denied.");

$url = '/mod/coursework/actions/allocate.php';
$link = new \moodle_url($url, array('id' => $coursemoduleid));
$PAGE->set_url($link);
$title = get_string('allocatefor', 'mod_coursework', $coursework->name);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$PAGE->requires->jquery();

$PAGE->requires->js('/mod/coursework/loadingoverlay.min.js');

// Will set off the function that adds listeners for onclick/onchange etc.
$jsmodule = array(
    'name' => 'mod_coursework',
    'fullpath' => '/mod/coursework/module.js',
    'requires' => array('base',
                        'node-base')
);
$PAGE->requires->js_init_call('M.mod_coursework.init_allocate_page',
                              array('wwwroot'=>$CFG->wwwroot,'coursemoduleid'=>$coursemoduleid),
                              false,
                              $jsmodule);

$PAGE->requires->string_for_js('sameassessorerror', 'coursework');



$allocationsmanager = $coursework->get_allocation_manager();
$allocationtable = new mod_coursework\allocation\table\builder($coursework, $options);
$allocationtable = new mod_coursework_allocation_table($allocationtable);
$pageurl    =   $PAGE->url;

// 1. Save the rules and settings from the config bits.

if ($formsavebutton) {
    // We need to save the allocation strategy. Make sure it's a real class first.
    if ($assessorallocationstrategy) {
        if ($assessorallocationstrategy != $coursework->assessorallocationstrategy) {
            $coursework->set_assessor_allocation_strategy($assessorallocationstrategy);
        }
        $coursework->save_allocation_strategy_options($assessorallocationstrategy);
    }

    $coursework->save();
}


if ($samplingformsavebutton)    {
    if ($coursework->sampling_enabled()) {
        $allocationsmanager->save_sample();
    }
}

// Adjust moderation set if needs be. This must happen before moderation pairs are saved/auto-allocated.
if ($deletemodsetrule) {
    if (is_array($deletemodsetrule)) {
        reset($deletemodsetrule);
        $deleteruleid = key($deletemodsetrule); // Only one button can be clicked.
        if (is_numeric($deleteruleid)) {
            $DB->delete_records('coursework_mod_set_rules', array('id' => $deleteruleid));
        }
    }
}



// 2. Process the manual allocations

// Did we just get the form submitted to us?
if ($formsavebutton) {
    $processor = new \mod_coursework\allocation\table\processor($coursework);
    $processor->process_data($formdataarray);

    $allocationsmanager->auto_generate_sample_set();
}


// 3. Process the auto allocations to fill in the gaps.





// Get the data to render as a moderation set widget.
$allocationwidget = new widget($coursework);
$allocationwidget = new \mod_coursework_allocation_widget($allocationwidget);

/**
 * @var mod_coursework_object_renderer $object_renderer
 */
$object_renderer = $PAGE->get_renderer('mod_coursework', 'object');
/**
 * @var mod_coursework_page_renderer $page_renderer
 */
$page_renderer = $PAGE->get_renderer('mod_coursework', 'page');


$warnings = new \mod_coursework\warnings($coursework);

$percentage_allocation_not_complete = $warnings->percentage_allocations_not_complete();
$manual_allocation_not_complete = '';
if ($coursework->allocation_enabled()){
    $manual_allocation_not_complete = $warnings->manual_allocation_not_completed();
}

if ($formsavebutton && $percentage_allocation_not_complete == '' && $manual_allocation_not_complete == ''){
    redirect($CFG->wwwroot.'/mod/coursework/view.php?id='.$coursemoduleid, get_string('changessaved','mod_coursework'));
} elseif ($formsavebutton) {
    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo $percentage_allocation_not_complete;
if ($coursework->allocation_enabled()) {
    echo $manual_allocation_not_complete;
}

// Add coursework id etc.
echo \html_writer::input_hidden_params($PAGE->url);



if ($coursework->sampling_enabled()) { //  Do not delete yet - refactoring...
    echo \html_writer::start_tag('form', array('id' => 'sampling_form',
        'method' => 'post'));
    $samplesetwidget = $allocationsmanager->get_sampling_set_widget();
    echo $object_renderer->render($samplesetwidget);
    echo html_writer::end_tag('form');
}





// Start form. The page has now been broken into two forms sampling section and allocation section
// Open form tag.
echo \html_writer::start_tag('form', array('id' => 'allocation_form',
    'method' => 'post'));


if ($coursework->allocation_enabled()) {
    echo $object_renderer->render($allocationwidget);
}

// Spacer so that we can float the headers next to each other.
$attributes = array(
    'class' => 'coursework_spacer'
);
echo html_writer::start_tag('div', $attributes);
echo html_writer::end_tag('div');


echo html_writer::tag('h3', get_string('assessormoderatorgrades', 'mod_coursework'));
echo html_writer::tag('div', get_string('pininfo', 'mod_coursework'), array('class'=>'pininfo'));

// Start the form with save button.
/*
$attributes = array('name' => 'save',
                    'type' => 'submit',
                    'id' => 'save_manual_allocations_1',
                    'value' => get_string('saveeverything', 'mod_coursework'));
echo html_writer::empty_tag('input', $attributes);
echo $OUTPUT->help_icon('savemanualallocations', 'mod_coursework');
*/
echo $object_renderer->render($allocationtable);

echo html_writer::end_tag('form');



echo $OUTPUT->footer();
