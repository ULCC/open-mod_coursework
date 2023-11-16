<?php

namespace mod_coursework\render_helpers\grading_report\cells;
use coding_exception;
use mod_coursework\ability;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\personal_deadline;
use mod_coursework\models\user;
use pix_icon;

/**
 * Class personal_deadline_cell
 */
class personal_deadline_cell extends cell_base {

    /**
     * @param grading_table_row_base $row_object
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($row_object) {
        global $OUTPUT, $USER;

        $coursework = $row_object->get_coursework();
        $deadline = $coursework->get_deadline();
        $content = '<div class="show_personal_dealine">';

        $new_personal_deadline_params = array(
            'allocatableid' => $row_object->get_allocatable()->id(),
            'allocatabletype' => $row_object->get_allocatable()->type(),
            'courseworkid' => $row_object->get_coursework()->id,
        );

        $personal_deadline = personal_deadline::find_or_build($new_personal_deadline_params);
        if ($personal_deadline->personal_deadline){
            $deadline = $personal_deadline->personal_deadline;
        }
        $date = userdate($deadline, '%a, %d %b %Y, %H:%M');
        $content .= '<div class="content_personal_deadline">'.$date.'</div>';
        $ability = new ability(user::find($USER, false), $row_object->get_coursework());
        $class = 'edit_personal_deadline';
        if(!$ability->can('edit', $personal_deadline)) {
            $class .= ' display-none';
        }

        $link = $this->get_router()->get_path('edit personal deadline', $new_personal_deadline_params);
        // $link = '/';
        $icon = new pix_icon('edit', 'Edit personal deadline', 'coursework');
        $new_personal_deadline_params['multipleuserdeadlines'] = 0;

        $content .= $OUTPUT->action_icon($link,
            $icon,
            null,
            array('class' => $class,'data-get' =>json_encode($new_personal_deadline_params), 'data-time' => date('d-m-Y H:i', $deadline) ));
        $content .= '</div><div class="show_edit_personal_dealine display-none"> </div>';

        return $this->get_new_cell_with_order_data(['display' => $content, '@data-order' => $deadline]);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {

        $tablename  =   (!empty($options['tablename']))  ? $options['tablename']  : ''  ;

        return $this->helper_sortable_heading(get_string('tableheadpersonaldeadline', 'coursework'),
            'personaldeadline',
            $options['sorthow'],
            $options['sortby'],
            $tablename);
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'tableheadpersonaldeadline';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}
