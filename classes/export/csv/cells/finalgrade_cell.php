<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;

/**
 * Class finalgrade_cell
 */
class finalgrade_cell extends cell_base {

    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return null|string
     */
    public function get_cell($submission, $student, $stage_identifier){

        return $submission->get_final_grade() == false || $submission->editable_final_feedback_exist()? '' : $this->get_actual_grade($submission->get_final_grade());

    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('provisionalgrade', 'coursework');
    }

}