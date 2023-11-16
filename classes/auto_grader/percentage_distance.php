<?php

namespace mod_coursework\auto_grader;

use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;

/**
 * Class percentage_distance is responsible for calculating and applying the automatically agreed grade if the initial
 * assessor grades are within a certain percentage of one another.
 *
 * @package mod_coursework\auto_grader
 */
class percentage_distance implements auto_grader {

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @var int
     */
    private $percentage;

    /**
     * @var allocatable
     */
    private $allocatable;

    /**
     * @param coursework $coursework
     * @param allocatable $allocatable
     * @param int $percentage
     */
    public function __construct($coursework, $allocatable) {
        $this->coursework = $coursework;
        $this->percentage = (int)$this->coursework->automaticagreementrange;
        $this->allocatable = $allocatable;
    }

    /**
     * This will test whether there is a grade already present, test whether the rules for this class match the
     * state of the initial assessor grades and make an automatic grade if they do.
     *
     */
    public function create_auto_grade_if_rules_match() {

        // bounce out if conditions are not right/
        if (!$this->get_allocatable()->has_all_initial_feedbacks($this->get_coursework())) {
            return;
        }
        if ($this->get_coursework()->numberofmarkers == 1) {
            return;
        }


        if ($this->grades_are_close_enough()  ) {
            if (!$this->get_allocatable()->has_agreed_feedback($this->get_coursework())) {
                $this->create_final_feedback();
            } else  {
                // update only if AgreedGrade has been automatic
                $agreed_feedback = $this->get_allocatable()->get_agreed_feedback($this->get_coursework());
                if($agreed_feedback->timecreated == $agreed_feedback->timemodified || $agreed_feedback->lasteditedbyuser == 0) {
                    $this->update_final_feedback($agreed_feedback);
                }
            }

        }

        // trigger events?

    }

    /**
     * @return coursework
     */
    private function get_coursework() {
        return $this->coursework;
    }

    /**
     * @return int
     */
    private function automatic_grade() {
        $grades = $this->grades_as_percentages();
        return max($grades);
    }

    /**
     * @return bool
     */
    private function grades_are_close_enough() {
        // test if the rules apply
        $grades = $this->grades_as_percentages();
        $max_grade = max($grades);
        $min_grade = min($grades);

        return ($max_grade - $min_grade) <= $this->percentage;
    }

    /**
     * @return allocatable
     */
    private function get_allocatable() {
        return $this->allocatable;
    }

    /**
     *
     */
    private function create_final_feedback() {
        feedback::create(array(
                             'stage_identifier' => 'final_agreed_1',
                             'submissionid' => $this->get_allocatable()->get_submission($this->get_coursework())->id(),
                             'grade' => $this->automatic_grade()

                         ));
    }


    /**
     *
     */
    private function update_final_feedback($feedback) {
        global $DB;

        $updated_feedback = new \stdClass();
        $updated_feedback->id = $feedback->id;
        $updated_feedback->grade = $this->automatic_grade();
        $updated_feedback->lasteditedbyuser = 0;

        $DB->update_record('coursework_feedbacks', $updated_feedback);

    }



    /**
     * @return array
     */
    private function grades_as_percentages() {
        $initial_feedbacks = $this->get_allocatable()->get_initial_feedbacks($this->get_coursework());
        $grades = array_map(function ($feedback) {
            return ($feedback->get_grade() / $this->get_coursework()->get_max_grade()) * 100;
        },
            $initial_feedbacks);
        return $grades;
    }
}