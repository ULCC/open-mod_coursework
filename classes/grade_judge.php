<?php


namespace mod_coursework;

use mod_coursework\allocation\allocatable;
use mod_coursework\models\assessment_set_membership;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\null_feedback;
use mod_coursework\models\submission;
use mod_coursework\stages\base as stage_base;

/**
 * Class grade_judge is responsible for deciding what the student's final grade should be, given
 * various capping settings.
 *
 * @package mod_coursework
 */
class grade_judge {

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @param $coursework
     */
    public function __construct($coursework) {

        $this->coursework = $coursework;
    }

    /**
     * @param submission $submission
     * @return mixed
     */
    public function get_grade_capped_by_submission_time($submission) {

        if (empty($submission)) {
            return null;
        }

        return $this->get_submission_grade_to_use($submission);
    }

    /**
     * @param $grade
     * @return float
     */
    private function round_grade_decimals($grade) {
        return round($grade, 2);
    }

    /**
     * @param int $grade
     * @return null
     */
    public function grade_to_display($grade) {
        if (is_null($grade)) {
            return '';
        } else if ($this->coursework->grade >= 1) {
            // Numeric grade
            return $this->round_grade_decimals($grade);
        } else if ($this->coursework->grade == 0) {
            // No grade
            return null;
        } else if ($this->coursework->grade <= -1) {
            // Scale
            $scale = \grade_scale::fetch(array('id' => abs($this->coursework->grade)));
            return $scale->get_nearest_item($grade);
        }
    }

    /**
     * The grade to send to the gradebook when the publish action happens
     *
     * @param submission $submission
     * @return float
     */
    public function get_grade_for_gradebook($submission) {
        return $this->round_grade_decimals($this->get_grade_capped_by_submission_time($submission));
    }

    /**
     * @param submission $submission
     * @return int
     */
    private function get_submission_grade_to_use($submission) {

        $gradebook_feedback = $this->get_feedback_that_is_promoted_to_gradebook($submission);

        if ($gradebook_feedback && ($submission->ready_to_publish()) || $submission->already_published()) {
            return $gradebook_feedback->get_grade();
        }
        return null;
    }

    /**
     * @param submission $submission
     * @return bool|feedback|null|static
     */
    public function get_feedback_that_is_promoted_to_gradebook($submission) {

        if (!isset($submission->id)) {
            return new null_feedback();
        }

        if ($this->allocatable_needs_more_than_one_feedback($submission->get_allocatable())) {
            $feedback = feedback::find(array('submissionid' => $submission->id, 'stage_identifier' => 'final_agreed_1'));
        } else {
            $feedback = feedback::find(array('submissionid' => $submission->id, 'stage_identifier' => 'assessor_1'));
        }

        return $feedback ? $feedback : new null_feedback();
    }

    /**
     * @param submission $submission
     * @return bool
     */
    public function has_feedback_that_is_promoted_to_gradebook($submission) {
        return $this->get_feedback_that_is_promoted_to_gradebook($submission)->id != 0;
    }

    /**
     * @param submission $submission
     * @return int
     */
    public function get_time_graded($submission) {
        return $this->get_feedback_that_is_promoted_to_gradebook($submission)->timemodified;
    }

    /**
     * @param feedback $feedback
     * @return bool
     */
    public function is_feedback_that_is_promoted_to_gradebook(feedback $feedback) {
        $gradebook_feedback = $this->get_feedback_that_is_promoted_to_gradebook($feedback->get_submission());
        return $gradebook_feedback && $gradebook_feedback->id == $feedback->id;
    }


    /**
     * @param allocatable $allocatable
     * @return bool
     */
    public function allocatable_needs_more_than_one_feedback ($allocatable){

        if ($this->coursework->sampling_enabled()) {
            assessment_set_membership::fill_pool_coursework($this->coursework->id);
            $record = assessment_set_membership::get_object($this->coursework->id, 'allocatableid-allocatabletype', [$allocatable->id(), $allocatable->type()]);
            return !empty($record);
        } else {
            return $this->coursework->has_multiple_markers();
        }


    }

    public function grade_in_scale($value)    {
        if (is_null($value)) {
            return true;
        } else if ($this->coursework->grade >= 1) {
            // Numeric grade
            return is_numeric($value) && $value < $this->coursework->grade +1 && $value > 0;
        } else if ($this->coursework->grade == 0) {
            // No grade
            return true;
        } else if ($this->coursework->grade <= -1) {
            // Scale
            $scale = \grade_scale::fetch(array('id' => abs($this->coursework->grade)));
            $scale->load_items();
            return in_array($value,$scale->scale_items);
        }
    }

    /**
     * Returns the grade
     *
     * @param $value
     * @return mixed
     */
    public function get_grade($value)   {

        if ($this->coursework->grade <= -1) {
            // Scale
            $scale = \grade_scale::fetch(array('id' => abs($this->coursework->grade)));
            $scale->load_items();
            return array_search($value,$scale->scale_items)+1;
        } else {
            return $value;
        }


    }




}