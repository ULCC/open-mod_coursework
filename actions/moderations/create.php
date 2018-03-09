<?php

/**
 * Creates a moderation agreement instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;


$submissionid = required_param('submissionid', PARAM_INT);
$feedbackid = required_param('feedbackid', PARAM_INT);
$moderatorid = optional_param('moderatorid', $USER->id, PARAM_INT);
$stage_identifier = optional_param('stage_identifier', '', PARAM_ALPHANUMEXT);

$params = array(
    'submissionid' => $submissionid,
    'feedbackid' => $feedbackid,
    'moderatorid' => $moderatorid,
    'stage_identifier' => $stage_identifier,
);
$controller = new mod_coursework\controllers\moderations_controller($params);
$controller->create_moderation();