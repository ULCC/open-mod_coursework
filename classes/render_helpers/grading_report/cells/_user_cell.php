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
class user_cell extends cell_base implements allocatable_cell {

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

  /*      if ($rowobject->can_view_username()) {
            $content .= $OUTPUT->user_picture($user->get_raw_record());
        } else {
            $renderer = $PAGE->get_renderer('core');
            // Just output the image for an anonymous user.
            $defaulturl = $renderer->pix_url('u/f2'); // Default image.
            $attributes = array('src' => $defaulturl);
            $content .= html_writer::empty_tag('img', $attributes);
        } */
        // TODO CSS for the space!!
        $content .= ' ' . $rowobject->get_user_name(true);

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
           $sort_by_first_name = $this->helper_sortable_heading(get_string('firstname'),
                                                                'firstname',
                                                                $options['sorthow'],
                                                                $options['sortby'],
                                                                $tablename);
           $sort_by_last_name = $this->helper_sortable_heading(get_string('lastname'),
                                                               'lastname',
                                                               $options['sorthow'],
                                                               $options['sortby'],
                                                                $tablename);
       } else { // otherwise display header without sorting
           $sort_by_first_name = get_string('firstname');
           $sort_by_last_name =get_string('lastname');
       }

        if ($this->fullname_format() == 'lf') {
            $sort_by_name = $sort_by_last_name . ' / ' . $sort_by_first_name;
        } else {
            $sort_by_name = $sort_by_first_name . ' / ' . $sort_by_last_name;
        }
        return $sort_by_name;
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'studentname';
    }

    /**
     * Tries to guess the full name format set at the site.
     *
     * @return string fl|lf
     */
    private function fullname_format() {
        $fake = new stdclass(); // Fake user.
        $fake->lastname = 'LLLL';
        $fake->firstname = 'FFFF';
        $fullname = get_string('fullnamedisplay', '', $fake);
        if (strpos($fullname, 'LLLL') < strpos($fullname, 'FFFF')) {
            return 'lf';
        } else {
            return 'fl';
        }
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}