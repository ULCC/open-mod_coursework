<?php

/**
 * Creates a feedback instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;


$feedbackid = required_param('feedbackid', PARAM_INT);
$finalised = !!optional_param('submitbutton', 0, PARAM_TEXT);

$params = array(
    'feedbackid' => $feedbackid,
    'finalised' => $finalised,
);
$controller = new mod_coursework\controllers\feedback_controller($params);
$controller->update_feedback();