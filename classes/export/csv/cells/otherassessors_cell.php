<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\grade_judge;
use mod_coursework\ability;
use mod_coursework\models\user;
use mod_coursework\models\feedback;

/**
 * Class otherassessors_cell
 */
class otherassessors_cell extends cell_base{


    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return null|string
     */
    public function get_cell($submission, $student, $stage_identifier){
        global $DB, $USER;
        // find out current user stage identifier

      //  $stage_identifier =
    // retrieve all feedbacks without currents user feedback


        $params = array(
            'submissionid' => $submission->id,
            'assessorid' => $USER->id,
            'stageidentifier' => $stage_identifier
        );

        $sql = "SELECT * FROM {coursework_feedbacks}
                WHERE submissionid = :submissionid
                AND assessorid <> :assessorid
                AND stage_identifier <> 'final_agreed_1'";

        $feedbacks = $DB->get_records_sql($sql, $params);
        $gradedata = array();

       // $stage_identifier = ($this->coursework->get_max_markers() == 1) ? "assessor_1" : $this->get_stage_identifier_for_assessor($submission, $student);
        foreach($feedbacks as $feedback){

            $grade = $submission->get_assessor_feedback_by_stage($feedback->stage_identifier);
            if ($grade){
                // skip if you are allocated but someone else graded it
                $allocation = $submission->get_assessor_allocation_by_stage($feedback->stage_identifier);
                if ($allocation && $allocation->assessorid == $USER->id) continue;
                $ability = new ability(user::find($USER), $this->coursework);
                if ((($ability->can('show', $feedback)  || has_capability('mod/coursework:addallocatedagreedgrade', $submission->get_coursework()->get_context())) &&
                    (!$submission->any_editable_feedback_exists() && count($submission->get_assessor_feedbacks()) <= $submission->max_number_of_feedbacks())) || is_siteadmin($USER->id)) {

                    if($this->coursework->is_using_rubric()){
                        $this->get_rubric_scores_gradedata($grade, $gradedata); // multiple parts are handled here
                    } else {
                        $gradedata[] = $this->get_actual_grade($grade->grade);
                    }
                    $gradedata[] = strip_tags($grade->feedbackcomment);

                } else {

                    $gradedata[] = get_string('grade_hidden_manager', 'mod_coursework');
                    $gradedata[] = '';
                }


            } else {

                if($this->coursework->is_using_rubric()){
                    $criterias = $this->coursework->get_rubric_criteria();
                    foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                        $gradedata['assessor' . $stage_identifier . '_' . $criteria['id']] = get_string('grade_hidden_manager', 'mod_coursework');
                        $gradedata['assessor' . $stage_identifier . '_' . $criteria['id'] . 'comment'] = '';
                    }
                }else {
                    $gradedata[] = '';
                }
                $gradedata[] = '';
            }

        }

        $numothereassessorfeedbacks    =   $submission->max_number_of_feedbacks() -1;

        if ($numothereassessorfeedbacks - count($feedbacks) != 0 )  {

            $blankcolumns   =   $numothereassessorfeedbacks - count($feedbacks);

            for($i = 0; $i < $blankcolumns; $i++) {
                if ($this->coursework->is_using_rubric()) {
                    $criterias = $this->coursework->get_rubric_criteria();
                    foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                        $gradedata['assessor' . $stage_identifier.$i. '_' . $criteria['id']] = '';
                        $gradedata['assessor' . $stage_identifier.$i. '_' . $criteria['id'] . 'comment'] = '';
                    }
                } else {
                    $gradedata[] = '';
                }
                $gradedata[] = '';

            }

        }

        return   $gradedata;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){

        $fields = array();

        for ($i = 1; $i < $this->stages ; $i++) {
            if ($this->coursework->is_using_rubric()) {
                $criterias = $this->coursework->get_rubric_criteria();
                foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                    $fields['otherassessorgrade'.$i.$stage.'_'.$criteria['id']] = 'Other assessor ('.$i.') - '.$criteria['description'];
                    $fields['otherassessorgrade'.$i.$stage.'_'.$criteria['id'] . 'comment'] = 'Comment for: Other assessor ('.$i.') - '.$criteria['description'];
                }
            } else {
                $fields['otherassessorgrade' . $i] = get_string('otherassessorgrade', 'coursework', $i);
            }
                $fields['otherassessorfeedback' . $i] = get_string('otherassessorfeedback', 'coursework', $i);
        }
       return $fields;
    }



}