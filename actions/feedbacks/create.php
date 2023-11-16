<?php

/**
 * Creates a feedback instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;


$submissionid = required_param('submissionid', PARAM_INT);
$isfinalgrade = optional_param('isfinalgrade', 0, PARAM_INT);
$assessorid = optional_param('assessorid', $USER->id, PARAM_INT);
$stage_identifier = optional_param('stage_identifier', '', PARAM_ALPHANUMEXT);
$finalised = !!optional_param('submitbutton', 0, PARAM_TEXT);
$ajax = optional_param('ajax', 0, PARAM_INT);

$params = array(
    'submissionid' => $submissionid,
    'isfinalgrade' => $isfinalgrade,
    'assessorid' => $assessorid,
    'stage_identifier' => $stage_identifier,
    'finalised' => $finalised,
    'ajax' => $ajax,
);

if ($ajax) {
    $params['cell_type'] = required_param('cell_type', PARAM_TEXT);
}

$controller = new mod_coursework\controllers\feedback_controller($params);
$controller->create_feedback();