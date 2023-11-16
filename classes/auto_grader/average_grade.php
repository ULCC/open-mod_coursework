<?php


namespace mod_coursework\auto_grader;

use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;

/**
 * Class average_grade is responsible for calculating and applying the automatically agreed grade based on the initial
 * assessor grades
 *
 * @package mod_coursework\auto_grader
 */
class average_grade implements auto_grader {

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @var
     */
    private $roundingrule;

    /**
     * @var allocatable
     */
    private $allocatable;

    /**
     * @param coursework $coursework
     * @param allocatable $allocatable
     */
    public function __construct($coursework, $allocatable){
        $this->coursework = $coursework;
        $this->roundingrule = $this->coursework->roundingrule;
        $this->allocatable = $allocatable;
    }

    /**
     * This will test whether there is a grade already present, test whether the rules for this class match the
     * state of the initial assessor grades and make an automatic grade if they do.
     *
     */
    public function create_auto_grade_if_rules_match(){

        // bounce out if conditions are not right/
        if (!$this->get_allocatable()->has_all_initial_feedbacks($this->get_coursework())) {
            return;
        }
        if ($this->get_coursework()->numberofmarkers == 1) {
            return;
        }



        if (!$this->get_allocatable()->has_agreed_feedback($this->get_coursework())) {
            $this->create_final_feedback();
        } else {
            // update only if AgreedGrade has been automatic
            $agreed_feedback = $this->get_allocatable()->get_agreed_feedback($this->get_coursework());
            if ($agreed_feedback->timecreated == $agreed_feedback->timemodified || $agreed_feedback->lasteditedbyuser == 0) {
                $this->update_final_feedback($agreed_feedback);
            }
        }



        // trigger events?

    }

    /**
     * @return coursework
     */
    private function get_coursework()
    {
        return $this->coursework;
    }

    /**
     * @return int
     */
    private function automatic_grade(){

        $grades = $this->grades_as_percentages();

        // calculate average
        $avggrade = array_sum($grades) / count($grades);

        // round it according to the chosen rule
        switch ($this->roundingrule) {
            case 'mid':
                $avggrade =  round($avggrade);
                break;
            case 'up':
                $avggrade =  ceil($avggrade);
                break;
            case 'down':
                $avggrade =  floor($avggrade);
                break;
        }


        return $avggrade;
    }


    /**
     * @return allocatable
     */
    private function get_allocatable(){
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
    private function update_final_feedback($feedback){
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