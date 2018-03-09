<?php

/**
 * Creates a moderation instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;


$moderationid = required_param('moderationid', PARAM_INT);

$params = array(
    'moderationid' => $moderationid,
);
$controller = new mod_coursework\controllers\moderations_controller($params);
$controller->update_moderation();