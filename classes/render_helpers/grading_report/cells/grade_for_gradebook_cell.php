<?php

namespace mod_coursework\render_helpers\grading_report\cells;
use coding_exception;
use html_table_cell;
use mod_coursework\ability;
use mod_coursework\grade_judge;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\user;

/**
 * Class feedback_cell
 */
class grade_for_gradebook_cell extends cell_base {

    /**
     * @param grading_table_row_base $row_object
     * @return string
     */
    public function get_table_cell($row_object) {
        global $USER;

        $content = '';
        $ability = new ability(user::find($USER), $row_object->get_coursework());
        $judge = new grade_judge($this->coursework);
        if ($ability->can('show', $judge->get_feedback_that_is_promoted_to_gradebook($row_object->get_submission())) && !$row_object->get_submission()->editable_final_feedback_exist()) {
            $grade = $judge->get_grade_capped_by_submission_time($row_object->get_submission());
            $content .= $judge->grade_to_display($grade);
        }
        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @throws coding_exception
     * @return string
     */
    public function get_table_header($options = array()) {
        return get_string('provisionalgrade', 'mod_coursework');
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'provisionalgrade';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'grades';
    }

    /**
     * @return string
     */
    public function get_table_header_help_icon(){
        global $OUTPUT;
        return ($OUTPUT->help_icon('provisionalgrade', 'mod_coursework'));
    }

}