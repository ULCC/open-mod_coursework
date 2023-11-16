<?php

namespace mod_coursework\render_helpers\grading_report\cells;


use html_writer;
use mod_coursework\models\coursework;
use mod_coursework\router;

/**
 * Class cell_base
 */
abstract class cell_base implements cell_interface {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @param array $items
     */
    public function __construct($items = array()) {
        $this->coursework = $items['coursework'];
        $this->after_initialisation($items);
    }

    /**
     * Makes the links to go in the top of a sortable column.
     *
     * @param string $display_name
     * @param string $field
     * @param string $sort_how ASC or DESC.
     * @param string $sortby The current sort from the URL.
     * @return string
     */
    protected function helper_sortable_heading($display_name, $field, $sort_how, $sortby = '',$tablename='') {

        global $PAGE;

        $params = array('id' => optional_param('id', 0, PARAM_INT));

        $tablename  =   (!empty($tablename))  ? $tablename.'_'  : ''  ;

        if (optional_param($tablename.'page', 0, PARAM_INT) > 0) {
            $params[$tablename.'page'] = optional_param($tablename.'page', 0, PARAM_INT);
        }
        $params[$tablename.'sortby'] = $field;
        if ($field == $sortby) {
            $params[$tablename.'sorthow'] = $sort_how == 'ASC' ? 'DESC' : 'ASC';
        } else {
            // Default for columns not currently being sorted.
            $params[$tablename.'sorthow'] = 'ASC';
        }



        // $url = clone($PAGE->url);
        // $url->params($params);

        // Need a little icon to show ASC or DESC.
        // if ($field == $sortby) {
        //     $display_name .= '&nbsp;'; // Keep them on the same line.
        //     $display_name .= $sort_how == 'ASC' ? '&#x25B2;' : '&#x25BC;'; // Small unicode triangles.
        // }

        // return html_writer::link($url, $display_name);
        return $display_name;
    }

    /**
     * @return router
     */
    protected function get_router() {
        return router::instance();
    }

    /**
     * @return \mod_coursework_object_renderer
     */
    protected function get_renderer() {
        global $PAGE;

        return $PAGE->get_renderer('mod_coursework', 'object');
    }

    /**
     * @return string|void
     */
    public function cell_name() {
        $namespaced_class = get_class($this);
        $bits = explode('\\', $namespaced_class);
        return end($bits);
    }

    /**
     * @param string $content
     * @return \html_table_cell
     */
    protected function get_new_cell_with_class($content = '') {
        return '
        <td data-class-name="' . get_class($this) . '" class="'. $this->cell_name().'">'.$content.'
        </td>
        ';
    }

    /**
     * @param array $data
     * @return \html_table_cell
     */
    protected function get_new_cell_with_order_data($data) {
        return '<td class="' . $this->cell_name() .'" data-order="' . $data['@data-order'] . '">' . $data['display'] . '</td>';
    }

    /**
     * Override for specific constructor stuff.
     *
     * @param array $items
     * @return void
     */
    protected function after_initialisation($items) {

    }

    /**
     * Override for the header help message
     */
    public function get_table_header_help_icon(){

    }


}
