<?php

namespace mod_coursework\allocation\table\cell;
use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\stages\base as stage_base;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\submission;

/**
 * This class and it's descendants are responsible for processing the data from the allocation form.
 * They know about the logic of what to do based on the data that the cell provides. The actions are carried
 * out by the stage class.
 *
 * @package mod_coursework\allocation\table\cell
 */
class builder {

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @var allocatable
     */
    private $allocatable;

    /**
     * @var stage_base
     */
    private $stage;

    /**
     * @param coursework $coursework
     * @param allocatable $allocatable
     * @param stage_base $stage
     * @param array $data_array incoming data from the allocation form
     */
    public function __construct($coursework, $allocatable, $stage, $data_array = array()) {
        $this->coursework = $coursework;
        $this->allocatable = $allocatable;
        $this->stage = $stage;
    }


    /**
     * @return \html_table_cell
     */
    public function get_renderable_allocation_table_cell() {
        return $this->prepare_allocation_table_cell();
    }

    /**
     * @return \html_table_cell
     */
    public function get_renderable_moderation_table_cell() {
        return $this->prepare_moderation_table_cell();
    }

    /**
     * Makes the dropdown showing what teachers can mark this coursework.
     *
     * @return string
     */
    private function get_potential_marker_dropdown() {

        if ($this->stage_does_not_use_allocation()) {
            return '';
        }
        if ($this->already_has_feedback()) {
            return '';
        }

        return $this->get_stage()->potential_marker_dropdown($this->get_allocatable());


    }

    /**
     * @return string
     */
    private function get_potential_moderators_dropdown(){

        if ($this->stage_does_not_use_allocation()) {
            return '';
        }
        if ($this->has_moderation()) {
            return '';
        }

        return $this->get_stage()->potential_moderator_dropdown($this->get_allocatable());
    }

    /**
     * @return bool
     */
    private function has_moderation(){
        if ($this->get_submission()){
            return $this->get_stage()->has_moderation($this->get_submission());
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    private function has_feedback() {
        return $this->get_stage()->has_feedback($this->get_allocatable());
    }

    /**
     * @return bool|\mod_coursework\models\feedback
     */
    private function get_feedback() {
        return $this->get_stage()->get_feedback_for_allocatable($this->get_allocatable());
    }


    /**
     * @return bool|\mod_coursework\models\moderation
     */
    private function get_moderation() {
        if ($this->get_submission()){
            return $this->get_stage()->get_moderation($this->get_submission());
        }
        return false;
    }

    /**
     * @return bool
     */
    private function has_allocation() {
        return $this->get_stage()->has_allocation($this->get_allocatable());
    }

    /**
     * @return allocation|bool
     */
    private function get_allocation() {
        return $this->get_stage()->get_allocation($this->get_allocatable());
    }

    /**
     */
    private function prepare_allocation_table_cell() {

        $class = $this->get_stage()->identifier();
        $contents = '';
        $assessor_dropdown = '';

        if ($this->coursework->sampling_enabled()){
            if ($class == 'final_agreed_1'){

            } else if ($this->get_stage()->uses_sampling()) {

                if ($this->has_automatic_sampling()){
                    $contents .= $this->get_automatically_in_sample_label();
                    $contents .= $this->sampling_hidden_checkbox();
                } else {
                    if ($this->has_feedback()) {
                        $contents .= $this->get_included_in_sample_label();
                        $contents .= $this->sampling_hidden_checkbox();
                    } else{
                        $contents .= $this->sampling_set_checkbox();
                    }
                }
            } else {
                // label for stage1 where everyone is in sample
                 $contents .= $this->get_included_in_sample_label();
            }
        }
        $contents .= '<br>';

       if ($this->coursework->allocation_enabled()) {
           $assessor_dropdown = $this->get_potential_marker_dropdown();
       }
        $assessor_name = '';
        if ($this->has_feedback()) {
            $class .= ' has-assessor-feedback ';

            $feedback = $this->get_feedback();
            $assessor = $feedback->assessor();
            $assessor_name = $assessor->profile_link();
            $assessor_name .= '<br>';
            $assessor_name .= 'Grade: ';
            $assessor_name .= $this->get_feedback()->get_grade();
        } else if ($this->has_allocation()) {
            $assessor_name .=  ' '.$this->pinned_checkbox($assessor_dropdown) ;
            $assessor_name .= $this->get_stage()->get_allocated_assessor_name($this->get_allocatable());
        }


        if ($assessor_name) {
           if ($this->get_stage()->uses_sampling() && !$this->get_feedback() && !$this->has_automatic_sampling()) {
                $contents .= '<br>';
            }
            $contents .= "<span class='existing-assessor'>{$assessor_name}</span>";
        }

        if ($assessor_dropdown) {
            $contents .= $assessor_dropdown;
        }

        return '
            <td class="' . $class . '">
            '.$contents.'
            </td>
        ';
    }

    /**
     * @return string
     */
    private function prepare_moderation_table_cell(){

        $contents = '';
        $class = 'moderators';
        $moderator_dropdown = '';

        if ($this->coursework->allocation_enabled()) {
            $moderator_dropdown = $this->get_potential_moderators_dropdown();
        }

        $moderator_name = '';
        if ($this->has_moderation()) {
            $class .= ' has-moderation-agreement ';

            $moderation = $this->get_moderation();
            $moderator = $moderation->moderator();
            $moderator_name = $moderator->profile_link();
            $moderator_name .= '<br>';
            $moderator_name .= 'Agreement: ';
            $moderator_name .= get_string($this->get_moderation()->agreement, 'coursework');
        } else if ($this->has_allocation()) {
            $moderator_name = ' ' . $this->pinned_checkbox($moderator_dropdown);
            $moderator_name .= $this->get_stage()->get_allocated_assessor_name($this->get_allocatable());
        }


        if ($moderator_name) {
            $contents .= '<br>';
            $contents .= "<span class='existing-moderator'>{$moderator_name}</span>";
        }


        if ($moderator_dropdown) {
            $contents .= '<br>';
            $contents .= $moderator_dropdown;
        }
        return '
            <td class="' . $class . '">
            '.$contents.'
            </td>
        ';
    }


    /**
     * @return allocatable
     */
    private function get_allocatable() {
        return $this->allocatable;
    }

    /**
     * @return stage_base
     */
    private function get_stage() {
        return $this->stage;
    }

    /**
     * @throws \coding_exception
     */
    private function sampling_set_checkbox() {
        $checkbox_name =
            'allocatables[' . $this->get_allocatable()->id . '][' . $this->get_stage()->identifier() . '][in_set]';
        $checkbox_checked = 0;
        if ($this->get_stage()->allocatable_is_in_sample($this->get_allocatable()) || $this->get_stage()->identifier() == 'assessor_1') {
            $checkbox_checked = 1;
        }

        $checkbox_checked   =   $this->checkbox_checked_in_session($checkbox_name,$checkbox_checked);

        $checkbox_title = 'Included in sample';

        $attributes = array('class' => 'sampling_set_checkbox',
                            'id'=>$this->get_allocatable()->type().'_' . $this->get_allocatable()->id() . '_'.$this->get_stage()->identifier() .'_samplecheckbox',
                            'title' => $checkbox_title);

        // if agreed grade given or grade published to students disable remaining sampling checkbox
        $submission = $this->get_submission();
        if ($this->has_final_feedback() || ($submission && $submission->firstpublished)) {
            $attributes['disabled'] = 'true';
        }

        return \html_writer::checkbox($checkbox_name,
                                      1,
                                      $checkbox_checked,
                                      get_string('includedinsample', 'mod_coursework'),
                                      $attributes);
    }

    /**
     * @return string
     */
    private function sampling_hidden_checkbox() {
        $checkbox_name =
            'allocatables[' . $this->get_allocatable()->id . '][' . $this->get_stage()->identifier() . '][in_set]';
        $checkbox_title = 'Included in sample';

        return \html_writer::checkbox($checkbox_name,
            1,
            1,
           '',
            array('class' => 'sampling_set_checkbox',
                'id'=>$this->get_allocatable()->type().'_' . $this->get_allocatable()->id() . '_'.$this->get_stage()->identifier() .'_samplecheckbox',
                'title' => $checkbox_title, 'hidden'=>true));
    }

    /**
     * returns whether the current record was automatically included in the sample set at the current stage
     *
     * @return bool
     * @throws \coding_exception
     */
    private function has_automatic_sampling()   {

        global $DB;

        $params =   array('courseworkid'=>$this->coursework->id(),
                          'allocatableid'=>$this->get_allocatable()->id(),
                          'stage_identifier'=>$this->get_stage()->identifier(),
                          'selectiontype' => 'automatic');



        return $DB->record_exists('coursework_sample_set_mbrs',$params);
    }

    /**
     * @return string
     */
    private function pinned_checkbox() {



        $checkbox_name =
            'allocatables[' . $this->get_allocatable()->id . '][' . $this->get_stage()->identifier() . '][pinned]';
        $checkbox_checked = 0;
        if ($this->get_stage()->has_allocation($this->get_allocatable())) {
            if ($this->get_stage()->get_allocation($this->get_allocatable())->is_pinned()) {
                $checkbox_checked = 1;
            }
        }

        $checkbox_checked   =   $this->checkbox_checked_in_session($checkbox_name,$checkbox_checked);

        $stage = substr($this->get_stage()->identifier(), -1);
        $checkbox_title = 'Pinned (auto allocations will not alter this)';
        return \html_writer::checkbox($checkbox_name,
                                                     1,
                                                     $checkbox_checked,
                                                     '',
                                                     array('class' => "pinned pin_$stage",
                                                           'title' => $checkbox_title));
    }



    private function checkbox_checked_in_session($checkboxname,$checkboxstate)  {

        global  $SESSION;

        $cm =   $this->coursework->get_course_module();

        if (!empty($SESSION->coursework_allocationsessions[$cm->id]))   {
            if (isset($SESSION->coursework_allocationsessions[$cm->id][$checkboxname]))    {
                return  $SESSION->coursework_allocationsessions[$cm->id][$checkboxname];
            }



        }

        return $checkboxstate;

    }

    /**
     * @return bool
     */
    private function already_has_feedback() {
        return $this->get_stage()->has_feedback($this->get_allocatable());
    }

    /**
     * @return bool
     */
    private function stage_does_not_use_allocation() {
        return !$this->get_stage()->uses_allocation();
    }

    /**
     * @return string
     * @throws \coding_exception
     */
    private function get_included_in_sample_label() {
        return \html_writer::label(get_string('includedinsample', 'mod_coursework'), null, true, array('class'=>'included_in_sample'));
    }

    /**
     * @return string
     * @throws \coding_exception
     */
    private function get_automatically_in_sample_label() {
        return \html_writer::label(get_string('automaticallyinsample', 'mod_coursework'), null, true, array('class'=>'included_in_sample'));
    }

    /**
     * @return bool
     */
    private function has_final_feedback(){
        global $DB;

        $sql = "SELECT *
                  FROM {coursework_submissions} s
                  JOIN {coursework_feedbacks} f
                    ON f.submissionid = s.id
                 WHERE s.courseworkid = :courseworkid
                   AND s.allocatableid = :allocatableid
                   AND s.allocatabletype = :allocatabletype
                   AND f.stage_identifier = 'final_agreed_1'";

        return $DB->record_exists_sql($sql, array('courseworkid'=>$this->coursework->id,
                                                  'allocatableid'=>$this->allocatable->id(),
                                                  'allocatabletype'=>$this->allocatable->type()));
    }

    /**
     * @return static
     */
    private function get_submission(){

        $submission_params = array('courseworkid' => $this->coursework->id,
                                   'allocatableid' => $this->allocatable->id(),
                                   'allocatabletype' => $this->allocatable->type());

        return submission::find($submission_params);
    }
}