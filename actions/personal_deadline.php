<?php

require_once(dirname(__FILE__) . '/../../../config.php');

global $CFG, $USER, $PAGE, $DB;



$courseworkid = required_param('courseworkid', PARAM_INT);
$allocatableid_arr = optional_param_array('allocatableid_arr', false, PARAM_RAW);
$allocatableid = optional_param('allocatableid', $USER->id, PARAM_RAW);
$allocatabletype = optional_param('allocatabletype', $USER->id, PARAM_ALPHANUMEXT);
$setpersonaldeadlinespage    =   optional_param('setpersonaldeadlinespage', 0, PARAM_INT);
$multipleuserdeadlines  =   optional_param('multipleuserdeadlines', 0, PARAM_INT);
$selectedtype  =   optional_param('selectedtype','date', PARAM_RAW);
$personal_deadline_time = optional_param('personal_deadline_time',null,PARAM_RAW);

$allocatableid  =   (!empty($allocatableid_arr))    ?   $allocatableid_arr  : $allocatableid  ;


$coursework_db =   $DB->get_record('coursework',array('id'=>$courseworkid));

$coursework     =   \mod_coursework\models\coursework::find($coursework_db);

require_login($coursework->get_course(),false,$coursework->get_course_module());

$params = array(
    'courseworkid' => $courseworkid,
    'allocatableid' => $allocatableid,
    'allocatabletype' => $allocatabletype,
    'setpersonaldeadlinespage'   => $setpersonaldeadlinespage,
    'multipleuserdeadlines'  =>  $multipleuserdeadlines
);

if ($selectedtype != 'unfinalise') {
    $controller = new mod_coursework\controllers\personal_deadlines_controller($params);

    if(!empty($personal_deadline_time)) {
        $result = $controller->insert_update($personal_deadline_time);
        echo json_encode($result);
    } else {
        $controller->new_personal_deadline();
    }
} else {

    if (!has_capability('mod/coursework:revertfinalised', $PAGE->context)) {
        $message = 'You do not have permission to revert submissions';
        redirect($url, $message);
    }

    $controller =   new mod_coursework\controllers\submissions_controller($params);
    $controller->unfinalise_submission();
}
