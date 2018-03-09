<?php


use \mod_coursework\models\coursework;
use \mod_coursework\allocation\widget;

require_once(dirname(__FILE__).'/../../../config.php');



global $CFG, $OUTPUT, $DB, $PAGE;

require_once($CFG->dirroot.'/mod/coursework/lib.php');

$coursemoduleid =   required_param('coursemoduleid', PARAM_INT);
$stagenumber    =   required_param('stage', PARAM_INT);
$coursemodule = get_coursemodule_from_id('coursework', $coursemoduleid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $coursemodule->course), '*', MUST_EXIST);
$coursework = $DB->get_record('coursework', array('id' => $coursemodule->instance), '*', MUST_EXIST);
$coursework = coursework::find($coursework);
$assessorallocationstrategy = optional_param('assessorallocationstrategy', false, PARAM_TEXT);




if ($stagenumber > 0)   {

    if (!isset($SESSION->allocate_page_selectentirestage[$coursework->id()]['assessor_'.$stagenumber])) {
        $SESSION->allocate_page_selectentirestage[$coursework->id()]['assessor_'.$stagenumber]  =   0;

    }

    $SESSION->allocate_page_selectentirestage[$coursework->id()]['assessor_'.$stagenumber]   =   !$SESSION->allocate_page_selectentirestage[$coursework->id()]['assessor_'.$stagenumber];

} else {
    if (!isset($SESSION->allocate_page_selectentirestage[$coursework->id()]['moderator'])) {
        $SESSION->allocate_page_selectentirestage[$coursework->id()]['moderator']  =   0;

    }

    $SESSION->allocate_page_selectentirestage[$coursework->id()]['moderator']   =   !$SESSION->allocate_page_selectentirestage[$coursework->id()]['moderator'];
}


