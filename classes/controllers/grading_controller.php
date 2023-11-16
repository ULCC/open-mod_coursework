<?php

namespace mod_coursework\controllers;

use mod_coursework\models\coursework;

/**
 * Class grading_controller
 * @package mod_coursework\controllers
 */
class grading_controller extends controller_base {

    public function get_remain_rows_grading_table($options) {

        global $DB, $PAGE;
        $coursework_record = $DB->get_record('coursework', array('id' => $options['courseworkid']), '*', MUST_EXIST);
        //$coursework = mod_coursework\models\coursework::find($coursework_record);
        $coursework = coursework::find($coursework_record, false);
        require_login($coursework->course);

        $coursework->coursemodule = get_coursemodule_from_instance('coursework', $coursework->id, $coursework->course, false, MUST_EXIST);
        $grading_report = $coursework->renderable_grading_report_factory($options);

        $tablerows = $grading_report->get_table_rows_for_page();
        $cell_helpers = $grading_report->get_cells_helpers();
        $sub_row_helper = $grading_report->get_sub_row_helper();

        $grading_report_renderer = $PAGE->get_renderer('mod_coursework', 'grading_report');
        $table_html = $grading_report_renderer->make_rows($tablerows, $cell_helpers, $sub_row_helper, $coursework->has_multiple_markers());
        return $table_html;
    }

}