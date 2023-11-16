<?php

namespace mod_coursework\render_helpers\grading_report\cells;


use coding_exception;
use html_table_cell;
use html_writer;
use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\grade_judge;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\user;
use mod_coursework\stages\base as stage_base;
use moodle_url;
use pix_icon;

/**
 * Class single_assessor_feedback_cell
 */
class single_assessor_feedback_cell extends cell_base {

    /**
     * @var allocatable
     */
    protected $allocatable;

    /**
     * @var stage_base
     */
    private $stage;

    /**
     * @param array $items
     */
    protected function after_initialisation($items) {
        $this->stage = $items['stage'];
    }

    /**
     * @param grading_table_row_base $rowobject
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($rowobject) {
        $content = $this->get_content($rowobject);
        return $this->get_new_cell_with_class($content);
    }
    
    public function get_content($rowobject) {

        global $USER;

        // Single:
        // Feedback column.

        $ability = new ability(user::find($USER), $rowobject->get_coursework());

        $content = '';
        $submission = $rowobject->get_submission();
        $allocatable = $rowobject->get_allocatable();

        // Add new feedback
        if ($rowobject->has_submission() &&
            $rowobject->get_submission()->ready_to_grade() &&
            ($this->stage->user_is_assessor($USER) ||
                has_capability('mod/coursework:administergrades', $this->coursework->get_context()))) {

            $feedback_params = array(
                'submissionid' => $rowobject->get_submission()->id,
                'assessorid' => $USER->id,
                'stage_identifier' => $this->stage->identifier(),
            );
            $new_feedback = feedback::build($feedback_params);
            if ($ability->can('new', $new_feedback)) {
                $content .= $this->new_feedback_button($rowobject, user::find($USER));
                $content .= html_writer::empty_tag('br');
            }
        }

        $feedback = $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable());
        if ($feedback) {
            $judge = new grade_judge($rowobject->get_coursework());

            $allocation = $this->stage->get_allocation($rowobject->get_allocatable());

            $content .= $judge->grade_to_display($feedback->get_grade());

            if ($ability->can('edit', $feedback)) { // Edit
                $content .= $this->edit_feedback_button($rowobject);
            } else if ($ability->can('show', $feedback)) { // Show
                $content .= $this->show_feedback_button($rowobject);
            }

            $content .= html_writer::empty_tag('br');
            $content .= 'by: ' . $feedback->get_assesor_username();
            $content .= html_writer::empty_tag('br');
            if ($this->coursework->allocation_enabled() && !empty($allocation) && $allocation->assessor()->id != $feedback->get_assessor_id()) {
                $content .= '(Allocated to ' . fullname($allocation->assessor()) . ')';
            }

        } else if ($this->coursework->allocation_enabled()) {
            // Show allocated person if there is one.
            $allocation = $this->stage->get_allocation($rowobject->get_allocatable());
            if ($allocation) {
                if ($ability->can('show', $allocation)) {
                    $content .= 'Allocated to '.$allocation->assessor()->profile_link();
                } else {
                    $content .= 'Allocated to someone else';
                }
            }

        }

        return $content;
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {
        return get_string('feedbackandgrading', 'coursework');
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'feedbackandgrading';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'grades';
    }

    /**
     * @param $rowobject
     * @return array
     * @throws \coding_exception
     */
    private function edit_feedback_button($rowobject) {
        global $OUTPUT;

        $feedback_params = array(
            'feedback' => $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable())
        );
        $link = $this->get_router()->get_path('edit feedback', $feedback_params);

        $link_id = 'edit_final_feedback_' . $rowobject->get_coursework()
                ->get_allocatable_identifier_hash($rowobject->get_allocatable());

        $title = get_string('editfinalgrade', 'coursework');
        $icon = new pix_icon('edit', $title, 'coursework');


        return  $OUTPUT->action_icon($link,
                                     $icon,
                                     null,
                                     array('id' => $link_id, 'class' => 'edit_final_feedback'));

    }

    /**
     * @param $rowobject
     * @return string
     */

    private function show_feedback_button($rowobject) {
        global $OUTPUT;

        $linktitle = get_string('viewfeedback', 'mod_coursework');
        $link_id = "show_feedback_" . $rowobject->get_coursework()
            ->get_allocatable_identifier_hash($rowobject->get_allocatable());
        $link = $this->get_router()
            ->get_path('show feedback', array('feedback' => $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable())));
        $iconlink = $OUTPUT->action_link($link,
                                         $linktitle,
                                         null,
                                         array('class'=>'show_feedback','id' => $link_id));


        return $iconlink;
    }


    /**
     * @param grading_table_row_base $rowobject
     * @param user $assessor
     * @return array
     * @throws \coding_exception
     */
    private function new_feedback_button($rowobject, $assessor) {
        global $OUTPUT;

        $feedback_params = array(
            'submission' => $rowobject->get_submission(),
            'assessor' => $assessor,
            'stage' => $this->stage,
        );
        $link = $this->get_router()->get_path('ajax new final feedback', $feedback_params);

        $link_id = 'new_final_feedback_' . $rowobject->get_coursework()
                ->get_allocatable_identifier_hash($rowobject->get_allocatable());

        $title = get_string('addfinalfeedback', 'coursework');

        return  $OUTPUT->action_link($link,
                                     $title,
                                     null,
                                     array('class'=>'new_final_feedback','id' => $link_id));
    }

}
