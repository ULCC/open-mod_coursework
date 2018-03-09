<?php

namespace mod_coursework\render_helpers\grading_report\sub_rows;

use html_table_row;
use html_writer;
use mod_coursework\ability;
use mod_coursework\assessor_feedback_row;
use mod_coursework\assessor_feedback_table;
use mod_coursework\grade_judge;
use mod_coursework\models\feedback;
use mod_coursework\models\user;
use mod_coursework\router;
use mod_coursework_grading_report_renderer;
use moodle_url;
use pix_icon;

/**
 * Class no_sub_rows
 */
class multi_marker_feedback_sub_rows implements sub_rows_interface {

    /**
     * @var bool
     */
    protected $already_shown_a_new_buton = false;

    /**
     * @param \mod_coursework\grading_table_row_base $row_object
     * @param int $column_width
     * @return string
     */
    public function get_row_with_assessor_feedback_table($row_object, $column_width) {

        /* @var assessor_feedback_table $assessor_feedback_table */
        $assessor_feedback_table = $row_object->get_assessor_feedback_table();

        // The number of columns will vary according to what permissions the user has.
        $assessor_feedback_table->set_column_width($column_width);

        return $this->render_assessor_feedback_table($assessor_feedback_table);

    }

    /**
     * @return \mod_coursework_object_renderer
     */
    protected function get_renderer() {
        global $PAGE;
        return $PAGE->get_renderer('mod_coursework', 'object');
    }

    /**
     * Renders the table of feedbacks from assessors, which appears under each student's submission in the
     * grading report of the multiple marker courseworks.
     *
     * @param assessor_feedback_table $assessor_feedback_table
     * @return html_table_row
     */
    protected function render_assessor_feedback_table(assessor_feedback_table $assessor_feedback_table) {

        global $USER, $PAGE;

        $coursework = $assessor_feedback_table->get_coursework();
        $ability = new ability(user::find($USER), $coursework);
        $feedbackrows = $assessor_feedback_table->get_renderable_feedback_rows();


        $allocatable = $assessor_feedback_table->get_allocatable();

        $output_rows = '';
        $table_html = '';

        $this->already_shown_a_new_buton = false;
        /* @var $feedback_row assessor_feedback_row */
        foreach ($feedbackrows as $feedback_row) {

            $stage = $feedback_row->get_stage();

            // Don't show empty rows with nothing in them
            // As a part of Release 1 we decided to show all rows to apply styling correctly,
            // this is expected to be rewritten for Release 2
           /* if (!$feedback_row->get_assessor()->id() && (!$feedback_row->get_submission() ||
                                                         !$feedback_row->get_submission()->ready_to_grade() ||
                                                          $this->already_shown_a_new_buton)) {
                continue;
            }*/


            $output_rows .= ' <tr class="' . $this->row_class($feedback_row) . '">';

            if ($coursework->sampling_enabled() && $stage->uses_sampling() && !$stage->allocatable_is_in_sample($allocatable)) {

                $output_rows .= '
                        <td class = "not_included_in_sample" colspan =3>'.get_string('notincludedinsample','mod_coursework').'</td>
                        </tr >';
            } else {
                $gradedby = ($coursework->allocation_enabled() && $feedback_row->has_feedback() && $feedback_row->get_graded_by() != $feedback_row->get_assessor())?
                                    ' (Graded by: '. $feedback_row->get_graders_name().')':'';

                $assessor_details  =   (empty($feedback_row->get_assessor()->id()) && $coursework->allocation_enabled()) ?
                     get_string('assessornotallocated','mod_coursework') : $this->profile_link($feedback_row);



                $editable = (!$feedback_row->has_feedback() || $feedback_row->get_feedback()->finalised)? '' : '</br>'.get_string('notfinalised', 'coursework');
                 $output_rows .= '
              <td>' . $assessor_details. ' </td>
              <td class="assessor_feedback_grade">' . $this->comment_for_row($feedback_row, $ability) .$gradedby. $editable.'</td >
              <td >' . $this->date_for_column($feedback_row) . '</td >
            </tr >
            ';
            }
        }

        if (!empty($output_rows)) {

            $allocation_string =  ($coursework->allocation_enabled())?
                                   get_string('allocatedtoassessor', 'mod_coursework'):
                                   get_string('assessor', 'mod_coursework');

            $table_html = '
                <tr class = "submissionrowmultisub">

                  <td colspan = "11" class="assessors" >
                  <table class="assessors" id="assessorfeedbacktable_' . $assessor_feedback_table->get_coursework()
                    ->get_allocatable_identifier_hash($assessor_feedback_table->get_allocatable()) . '">
                    <tr>
                      <th>' . $allocation_string . '</th>
                      <th>' . get_string('grade', 'mod_coursework') . '</th>
                      <th>' . get_string('tableheaddate', 'mod_coursework') . '</th>
                    </tr>';

            $table_html .= $output_rows;

            $table_html .= '
                    </table>
                  </td>
                </tr>';

            return $table_html;
        } else {

            if ($assessor_feedback_table->get_submission() &&
                    ($assessor_feedback_table->get_coursework()->deadline_has_passed() &&
                    $assessor_feedback_table->get_submission()->finalised)) {

                $table_html = '<tr><td colspan = "11" class="nograde" ><table class="nograde">';
                $table_html .= '<tr>' . get_string('nogradescomments', 'mod_coursework') . '</tr>';
                $table_html .= '</table></td></tr>';
            }
            return $table_html;
        }
    }

    /**
     * @return router
     */
    private function get_router() {
        return router::instance();
    }

    /**
     * @param assessor_feedback_row $feedback_row
     * @return array
     * @throws \coding_exception
     */
    protected function edit_existing_feedback_link($feedback_row) {
        global $OUTPUT;

        $linktitle = get_string('editgrade', 'coursework');
        $icon = new pix_icon('edit', $linktitle, 'coursework');
        $link_id = "edit_feedback_" . $feedback_row->get_feedback()->id;
        $link = $this->get_router()
            ->get_path('edit feedback', array('feedback' => $feedback_row->get_feedback()));
        $iconlink = $OUTPUT->action_icon($link, $icon, null, array('id' => $link_id));
        return $iconlink;
    }

    /**
     * @param assessor_feedback_row $feedback_row
     * @param $submission
     * @return \mod_coursework\framework\table_base
     */
    protected function build_new_feedback($feedback_row, $submission) {
        global $USER;

        $params = array(
            'assessorid' => $USER->id,
            'stage_identifier' => $feedback_row->get_stage()->identifier(),
        );
        if ($submission) {
            $params['submissionid'] = $submission->id;
        }
        $new_feedback = feedback::build($params);
        return $new_feedback;
    }

    /**
     * @param assessor_feedback_row $feedback_row
     * @return string
     * @throws \coding_exception
     */
    private function show_feedback_link($feedback_row) {
        global $OUTPUT;

        $linktitle = get_string('viewfeedback', 'mod_coursework');
        $link_id = "show_feedback_" . $feedback_row->get_feedback()->id;
        $link = $this->get_router()
            ->get_path('show feedback', array('feedback' => $feedback_row->get_feedback()));
        $iconlink = $OUTPUT->action_link($link,
                                         $linktitle,
                                         null,
                                         array('class'=>'show_feedback','id' => $link_id));
        return $iconlink;
    }

    /**
     * @param assessor_feedback_row $feedback_row
     * @return string
     * @throws \coding_exception
     */
    protected function new_feedaback_link($feedback_row) {
        global $USER, $OUTPUT;

        $this->already_shown_a_new_buton = true;
        $this->displaytable = true;
        // New
        $linktitle = get_string('newfeedback', 'coursework');

        $new_feedback_params = array(
            'submission' => $feedback_row->get_submission(),
            'assessor' => user::find($USER),
            'stage' => $feedback_row->get_stage()
        );
        $link = $this->get_router()->get_path('new feedback', $new_feedback_params);
        $iconlink = $OUTPUT->action_link($link,
                                         $linktitle,
                                         null,
                                         array('class'=>'new_feedback'));
        return $iconlink;
    }

    /**
     * @param assessor_feedback_row $feedback_row
     * @return string
     */
    protected function profile_link($feedback_row) {
        global $COURSE;

        $assessor = $feedback_row->get_assessor();

        $profilelinkurl = new moodle_url('/user/profile.php', array('id' => $assessor->id(),
                                                                    'course' => $COURSE->id));
        return html_writer::link($profilelinkurl, $assessor->name());
    }

    /**
     * @param assessor_feedback_row $feedback_row
     * @return string
     */
    protected function row_class($feedback_row) {
        $assessor = $feedback_row->get_assessor();
        $row_class = 'feedback-' . $assessor->id() . '-' . $feedback_row->get_allocatable()
                ->id() . ' ' . $feedback_row->get_stage()->identifier();
        return $row_class;
    }

    /**
     * @param assessor_feedback_row $feedback_row
     * @return string
     */
    protected function date_for_column($feedback_row) {
        if ($feedback_row->has_feedback()) {
            return userdate($feedback_row->get_feedback()->timecreated, '%a, %d %b %Y, %H:%M ');
        }
        return '';
    }

    /**
     * @param assessor_feedback_row $feedback_row
     * @param ability $ability
     * @return string
     */
    protected function comment_for_row($feedback_row, $ability) {
        global $USER;

        $submission = $feedback_row->get_submission();

        $html = '';

        if ($feedback_row->has_feedback()) {

            if ($ability->can('show', $feedback_row->get_feedback()) || is_siteadmin($USER->id)) {
                $grade_judge = new grade_judge($feedback_row->get_coursework());
                $html .= $grade_judge->grade_to_display($feedback_row->get_feedback()->get_grade());
            } else {
                if (has_capability('mod/coursework:addagreedgrade', $feedback_row->get_coursework()->get_context())
                     || has_capability('mod/coursework:addallocatedagreedgrade', $feedback_row->get_coursework()->get_context())) {
                    $html .= get_string('grade_hidden_manager', 'mod_coursework');
                } else {
                    $html .= get_string('grade_hidden_teacher', 'mod_coursework');
                }
            }

            $grade_editing    =    get_config('mod_coursework','coursework_grade_editing');


            if ($ability->can('edit', $feedback_row->get_feedback()) && !$submission->already_published()) {
                $html .= $this->edit_existing_feedback_link($feedback_row);
            } else if ($ability->can('show', $feedback_row->get_feedback())) {
                $html .= $this->show_feedback_link($feedback_row);
            }
        } else {

            $new_feedback = $this->build_new_feedback($feedback_row, $submission);
            if ($ability->can('new', $new_feedback) && !$this->already_shown_a_new_buton) {
                $html .= $this->new_feedaback_link($feedback_row);
            }
        }

        return $html;
    }
}