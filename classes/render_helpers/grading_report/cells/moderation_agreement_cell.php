<?php

namespace mod_coursework\render_helpers\grading_report\cells;
use coding_exception;
use html_table_cell;
use html_writer;
use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\grade_judge;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\moderation;
use mod_coursework\models\user;
use mod_coursework\stages\base as stage_base;
use pix_icon;
/**
 * Class moderation_agreement_cell
 */
class moderation_agreement_cell extends cell_base {


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
     * @return string
     */
    public function get_table_cell($rowobject) {
        global $USER;

        $ability = new ability(user::find($USER), $rowobject->get_coursework());

        $content = '';
        $moderation  = '';
        if ($rowobject->has_submission()) {
            if ($rowobject->get_single_feedback()){
                $moderation = $this->stage->get_moderation_for_feedback($rowobject->get_single_feedback());
            }
            // Add new moderations agreement
            if (!$moderation &&
                $rowobject->get_submission()->final_grade_agreed() &&
                ($this->stage->user_is_moderator($USER))) {


                $moderation_params = array(
                    'submissionid' => $rowobject->get_submission()->id,
                    'moderatorid' => $USER->id,
                    'stage_identifier' => $this->stage->identifier(),
                    'feedbackid' => $rowobject->get_single_feedback()->id
                );
                // allow moderations if feedback exists
                $new_moderation = moderation::build($moderation_params);
                if ($ability->can('new', $new_moderation) && ($rowobject->get_single_feedback()->finalised || is_siteadmin($USER->id))) {
                    $content .= $this->new_moderation_button($rowobject, user::find($USER));
                    $content .= html_writer::empty_tag('br');
                }
            }
        }
        if ($moderation) {

            $allocation = $this->stage->get_allocation($rowobject->get_allocatable());

            $content .= get_string($moderation->agreement, 'coursework');

            if ($ability->can('edit', $moderation)) { // Edit
                $content .= $this->edit_moderation_button($rowobject);
            } else if ($ability->can('show', $moderation)) { // Show
                $content .= $this->show_moderation_button($rowobject);
            }

            $content .= html_writer::empty_tag('br');
            $content .= 'by: ' . $moderation->get_moderator_username();
            $content .= html_writer::empty_tag('br');
            if ($this->coursework->allocation_enabled() && !empty($allocation) && $allocation->assessor()->id != $moderation->get_moderator_id()) {
                $content .= '(Allocated to ' . fullname($allocation->assessor()) . ')';
            }

        } else if ($this->coursework->allocation_enabled()) {
            // Show allocated person if there is one.
            $allocation = $this->stage->get_allocation($rowobject->get_allocatable());
            if ($allocation) {
                if ($ability->can('show', $allocation)) {
                    $content .= 'Allocated to ' . $allocation->assessor()->profile_link();
                } else {
                    $content .= 'Allocated to moderator';
                }
            }
        }
        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {
        return get_string('tableheadmoderationagreement', 'coursework');
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'tableheadmoderationagreement';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'grades';
    }

    /**
     * @return string
     */
    public function get_table_header_help_icon(){
        global $OUTPUT;
        return ($OUTPUT->help_icon('moderationagreement', 'coursework'));
    }


    /**
     * @param grading_table_row_base $rowobject
     * @param user $assessor
     * @return array
     * @throws \coding_exception
     */
    private function new_moderation_button($rowobject, $assessor) {
        global $OUTPUT;
        // moderation only done for single marking courseworks
        $feedback = $rowobject->get_submission()->get_assessor_feedback_by_stage('assessor_1');

       $moderation_params = array(
            'submission' => $rowobject->get_submission(),
            'assessor' => $assessor,
            'stage' => $this->stage,
            'feedbackid' => $feedback->id
        );
        $link = $this->get_router()->get_path('new moderations', $moderation_params);

        $link_id = 'new_moderation_' . $rowobject->get_coursework()
                ->get_allocatable_identifier_hash($rowobject->get_allocatable());

        $title = get_string('moderate', 'coursework');

        return  $OUTPUT->action_link($link,
                                     $title,
                                null,
                                array('class'=>'new_moderation','id' => $link_id));
    }


    /**
     * @param $rowobject
     * @return array
     * @throws \coding_exception
     */
    private function edit_moderation_button($rowobject) {
        global $OUTPUT;

        $feedback = $rowobject->get_submission()->get_assessor_feedback_by_stage('assessor_1');
        $feedback_params = array(
            'moderation' => $this->stage->get_moderation_for_feedback($feedback)
        );
        $link = $this->get_router()->get_path('edit moderation', $feedback_params);

        $link_id = 'edit_moderation_' . $rowobject->get_coursework()
                ->get_allocatable_identifier_hash($rowobject->get_allocatable());

        $title = get_string('editmoderation', 'coursework');
        $icon = new pix_icon('edit', $title, 'coursework');


        return  $OUTPUT->action_icon($link,
            $icon,
            null,
            array('id' => $link_id));

    }

    /**
     * @param $rowobject
     * @return string
     */

    private function show_moderation_button($rowobject) {
        global $OUTPUT;

        $feedback = $rowobject->get_submission()->get_assessor_feedback_by_stage('assessor_1');
        $moderation_params = array(
            'moderation' => $this->stage->get_moderation_for_feedback($feedback)
        );

        $linktitle = get_string('viewmoderation', 'mod_coursework');
        $link_id = "show_moderation_" . $rowobject->get_coursework()
                ->get_allocatable_identifier_hash($rowobject->get_allocatable());
        $link = $this->get_router()->get_path('show moderation', $moderation_params);

        return $OUTPUT->action_link($link,
                                    $linktitle,
                                null,
                                      array('class'=>'show_moderation','id' => $link_id));
    }
}