<?php
use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\grading_report;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\render_helpers\grading_report\cells\cell_interface;
use mod_coursework\render_helpers\grading_report\sub_rows\sub_rows_interface;

/**
 * Class mod_coursework_grading_report_renderer is responsible for
 *
 */
class mod_coursework_grading_report_renderer extends plugin_renderer_base {

    /**
     * @var
     */
    protected $row_class;

    /**
     * @var
     */
    protected $is_multiple_markers;


    /**
     * @param grading_report $grading_report
     * param $is_multiple_markers
     * @return string
     */
    public function render_grading_report($grading_report, $is_multiple_markers) {
        $langelement = $this->generate_lang_element();

        $options = $grading_report->get_options();
        $tablerows = $grading_report->get_table_rows_for_page();
        $cell_helpers = $grading_report->get_cells_helpers();
        $sub_row_helper = $grading_report->get_sub_row_helper();
        if (count($tablerows) == $grading_report->realtotalrows) {
            $options['class'] = 'full-loaded';
        }
        $this->row_class = $is_multiple_markers ? 'submissionrowmulti' : 'submissionrowsingle';

        if (empty($tablerows)) {
            return $langelement . '<div class="no-users">'.get_string('nousers', 'coursework').'</div><br>';
        }

        $table_html = $this->start_table($options);
        $table_html .= $this->make_table_headers($cell_helpers, $options, $is_multiple_markers);
        $table_html .= '</thead>';
        $table_html .= '<tbody>';
        $table_html .= $this->make_rows($tablerows, $cell_helpers, $sub_row_helper, $is_multiple_markers,);
        $table_html .= '</tbody>';
        $table_html .= $this->end_table();

        return  $langelement . $table_html;
    }


    /**
     *
     * @return mixed
     */
    private function generate_lang_element() {
        $lang_messages = [
            'download_submitted_files' => get_string('download_submitted_files', 'mod_coursework'),
            'exportfinalgrades' => get_string('exportfinalgrades', 'mod_coursework'),
            'exportgradingsheets' => get_string('exportgradingsheets', 'mod_coursework'),
            'loadingpagination' => get_string('loadingpagination', 'mod_coursework')
        ];
        $result = html_writer::empty_tag('input',array(
            'name'=>'',
            'type'=>'hidden',
            'data-lang'=>json_encode($lang_messages),
            'id'=>'element_lang_messages'
        ));
        $result = html_writer::div($result);

        return $result;
    }


    /**
     * @param cell_interface $cell_helper
     * @param array $options
     * @return string
     */
    protected function make_header_cell($cell_helper, $options) {
        $seq = empty($options['seq']) ? 0 : $options['seq'];
        $table_html = '<th class=' . $cell_helper->get_table_header_class() . ' data-seq=' . $seq . '>';

        $header_name = $cell_helper->get_table_header($options);
        $table_html .= $header_name;
        $table_html .= $cell_helper->get_table_header_help_icon();
        $table_html .= '</th>';
        return $table_html;
    }

    /**
     * @param grading_table_row_base $row_object
     * @param cell_interface[] $cell_helpers
     * @param sub_rows_interface $sub_row_helper
     * @return string
     */
    protected function make_row_for_allocatable($row_object, $cell_helpers, $sub_row_helper) {

        //$class = (!$row_object->get_coursework()->has_multiple_markers())? "submissionrowsingle": "submissionrowmulti";
        $class = $this->row_class;

        $submission = $row_object->get_submission();
        $allocatable = $row_object->get_allocatable();

        $table_html = '<tr class="'.$class.'" id="' . $this->grading_table_row_id($row_object->get_allocatable(), $row_object->get_coursework()) . '">';
        $is_multiple_markers = $this->is_multiple_markers;
        $tbl_assessor_feedbacks = $sub_row_helper->get_row_with_assessor_feedback_table($row_object, count($cell_helpers));
        if ($is_multiple_markers) {
            $table_html .= '<td class="details-control"></td>';
        }


        foreach ($cell_helpers as $cell_helper) {
            $html_td = trim($cell_helper->get_table_cell($row_object));

            if ($is_multiple_markers &&
                ($cell_helper instanceof \mod_coursework\render_helpers\grading_report\cells\user_cell ||
                    $cell_helper instanceof \mod_coursework\render_helpers\grading_report\cells\group_cell)
            ) {
                $html_td = str_replace('</td>' , $tbl_assessor_feedbacks.'</td>', $html_td);
            }

            $table_html .= $html_td;
        }
        $table_html .= '</tr>';

        if (!$is_multiple_markers) {
            $table_html .= $tbl_assessor_feedbacks;
        }

        return $table_html;
    }

    /**
     * @return string
     */
    public function submissions_header($header_text='') {
        $submisions = (!empty($header_text))    ? $header_text  :   get_string('submissions', 'mod_coursework');

        return html_writer::tag('h3', $submisions);
    }

    /**
     * @param $cell_helpers
     * @param $options
     * @return string
     */
    protected function make_table_headers($cell_helpers, $options, $is_multiple_markers) {

        $table_html = $this->make_upper_headers($cell_helpers, $is_multiple_markers);
        $table_html .= '<tr>';
        $tr_html = '';

        $i = 0;
        if ($is_multiple_markers) {
            $tr_html .= '<th class="addition-multiple-button"></th>'; // This is for open or close buttons
            ++$i;
        }

        foreach ($cell_helpers as $cell_helper) {
            $options['seq'] = $i++;
            $tr_html .= $this->make_header_cell($cell_helper, $options);
        }
        return $table_html . $tr_html . '</tr>';
    }

    /**
     * @return string
     */
    protected function start_table($options=array()) {
        $options['width'] = '100%';
        $options['class'] = (!empty($options['class'])) ? $options['class'] : '';
        $options['class'] .= ' submissions datatabletest display compact';
        $options['id']  = 'dt_table';
        $table_html = \html_writer::start_tag('table', $options);
        $table_html .= \html_writer::start_tag('thead');
        return $table_html;
    }

    /**
     * @return string
     */
    protected function end_table() {
        return '
            </table>
        ';
    }

    /**
     * @param $tablerows
     * @param $cell_helpers
     * @param $sub_row_helper
     * @param $is_multiple_markers
     * @return string
     */
    public function make_rows($tablerows, $cell_helpers, $sub_row_helper, $is_multiple_markers) {
        $table_html = '';

        $this->row_class = $is_multiple_markers ? 'submissionrowmulti' : 'submissionrowsingle';
        $this->is_multiple_markers = $is_multiple_markers;

        foreach ($tablerows as $row_object) {
            $table_html .= $this->make_row_for_allocatable($row_object, $cell_helpers, $sub_row_helper);
        }
        return $table_html;
    }

    /**
     * Groupings for the header cells on the next row down.
     *
     * @param cell_interface[] $cell_helpers
     * @return string
     */
    private function make_upper_headers($cell_helpers, $is_multiple_markers) {
        global $OUTPUT;
        $html = '';
        $headers = $this->upper_header_names_and_colspans($cell_helpers);

        foreach ($headers as $header_name => $colspan) {
            $colspan_value = $colspan;

            if ($html == '' && $is_multiple_markers) {
                $colspan_value += 1;
            }

            $html .= '<th colspan="'.$colspan_value.'">';
            $html .= get_string($header_name.'_table_header', 'mod_coursework');
            $html .= get_string($header_name.'_table_header', 'mod_coursework')?
                    ($OUTPUT->help_icon($header_name.'_table_header', 'mod_coursework')) : '';
            $html .= '</th>';
        }

        return $html;
    }

    /**
     * @param cell_interface[] $cell_helpers
     * @return mixed
     */
    private function upper_header_names_and_colspans($cell_helpers) {
        $headers = array();

        foreach ($cell_helpers as $helper) {
            if (!array_key_exists($helper->header_group(), $headers)) {
                $headers[$helper->header_group()] = 1;
            } else {
                $headers[$helper->header_group()]++;
            }
        }
        return $headers;
    }

    /**
     * @param allocatable $allocatable
     * @param coursework $coursework
     * @return string
     */
    public function grading_table_row_id(allocatable $allocatable, coursework $coursework) {
        return 'allocatable_' . $coursework->get_allocatable_identifier_hash($allocatable);
    }


}