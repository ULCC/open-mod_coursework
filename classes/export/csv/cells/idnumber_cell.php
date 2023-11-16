<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;

/**
 * Class idnumber_cell
 */
class idnumber_cell extends cell_base {

    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     * @throws \coding_exception
     */
    public function get_cell($submission, $student, $stage_identifier){

        if ($this->can_view_hidden() || $submission->is_published()){
            $name = $student->idnumber;
        } else {
            $name = get_string('hidden', 'coursework');
        }

        return  $name;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('idnumber');
    }
}