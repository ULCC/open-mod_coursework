<?php
ob_start();
require_once(dirname(__FILE__) . '/../../../../../config.php');

global $CFG, $USER, $DB, $SESSION, $PAGE;

$PAGE->set_context(context_system::instance());

$group = required_param('group', PARAM_INT);
$perpage = required_param('perpage', PARAM_INT);
$sortby = required_param('sortby', PARAM_ALPHA);
$sorthow = required_param('sorthow', PARAM_ALPHA);
$courseworkid = required_param('courseworkid', PARAM_INT);
$unallocated = optional_param('unallocated', false, PARAM_BOOL);

// Grading report display options.
$report_options = array();
if ($unallocated) {
    $report_options['unallocated'] = true;
}

$report_options['group'] = $group;
$report_options['perpage'] = $perpage;
$report_options['sortby'] = $sortby;
$report_options['sorthow'] = $sorthow;
$report_options['showsubmissiongrade'] = false;
$report_options['showgradinggrade'] = false;
$report_options['courseworkid'] = $courseworkid;
$report_options['mode'] = \mod_coursework\grading_report::$MODE_GET_REMAIN_RECORDS;

//$controller = new mod_coursework\controllers\grading_controller(['courseworkid' => $report_options, 'allocatableid' => $USER->id, 'allocatabletype' => $USER->id]);
$controller = new mod_coursework\controllers\grading_controller([]);
sleep(10);
$table_html = $controller->get_remain_rows_grading_table($report_options);
if (ob_get_contents()) {
    ob_end_clean();
}

echo $table_html;
