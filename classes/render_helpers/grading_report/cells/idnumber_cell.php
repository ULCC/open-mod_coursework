<?php

namespace mod_coursework\render_helpers\grading_report\cells;
use mod_coursework\models\user;
use mod_coursework\user_row;
use stdClass;

/**
 * Class idnumber_cell
 */
class idnumber_cell extends cell_base implements allocatable_cell {

    /**
     * @param user_row $rowobject
     * @return string
     */
    public function get_table_cell($rowobject) {
        global $OUTPUT, $PAGE;

        $content = '';
        $content .= $rowobject->get_idnumber();

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {

        $viewanonymous = has_capability('mod/coursework:viewanonymous', $this->coursework->get_context());

        //adding this line so that the sortable heading function will make a sortable link unique to the table
        //if tablename is set
        $tablename  =   (!empty($options['tablename']))  ? $options['tablename']  : ''  ;

        // allow to sort users only if CW is not set to blind marking or a user has capability to view anonymous
        if($viewanonymous || !$this->coursework->blindmarking) {
            $sort_header= $this->helper_sortable_heading(get_string('idnumber'),
                'idnumber',
                $options['sorthow'],
                $options['sortby'],
                $tablename);

        } else { // otherwise display header without sorting
            $sort_header = get_string('idnumber');
        }

        return $sort_header;
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'tableheadidnumber';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}
