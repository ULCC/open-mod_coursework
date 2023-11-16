<?php

/**
 * Creates a feedback instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;


$feedbackid = required_param('feedbackid', PARAM_INT);
$finalised = !!optional_param('submitbutton', 0, PARAM_TEXT);
$ajax = optional_param('ajax', 0, PARAM_INT);
$remove = !!optional_param('removefeedbackbutton', 0, PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$params = array(
    'feedbackid' => $feedbackid,
    'finalised' => $finalised,
    'remove' => $remove,
    'confirm' => $confirm,
    'ajax' => $ajax
);

if ($ajax) {
    $params['cell_type'] = required_param('cell_type', PARAM_TEXT);
}

$controller = new mod_coursework\controllers\feedback_controller($params);
$controller->update_feedback();