<?php

namespace mod_coursework\render_helpers\grading_report\cells;
use core_user;
use html_table_cell;
use html_writer;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\user;
use mod_coursework\user_row;
use stdClass;

/**
 * Class user_cell
 */
class first_name_letter_cell extends cell_base implements allocatable_cell {

    /**
     * @param user_row $rowobject
     * @return string
     */
    public function get_table_cell($rowobject) {
        global $OUTPUT, $PAGE;

        $content = '';

        /**
         * @var user $user
         */
        $user = $rowobject->get_allocatable();

        mb_internal_encoding('utf-8');
        $content .= ' ' . mb_substr($user->firstname, 0, 1);

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {
        return "First Letter - First Name";
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'firstname_letter_cell';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}