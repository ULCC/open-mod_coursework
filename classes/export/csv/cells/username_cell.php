<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;

/**
 * Class username_cell
 */
class username_cell extends cell_base {

    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     * @throws \coding_exception
     */
    public function get_cell($submission, $student, $stage_identifier){

        if ($this->can_view_hidden() || $submission->is_published()){
            $username = $student->username;
        } else {
            $username = get_string('hidden', 'coursework');
        }

        return  $username;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('studentusername', 'coursework');

    }
}