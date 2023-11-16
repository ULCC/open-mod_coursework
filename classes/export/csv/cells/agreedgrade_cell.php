<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\ability;
use mod_coursework\models\user;
use mod_coursework\models\feedback;
use mod_coursework\grade_judge;
/**
 * Class agreedgrade_cell
 */
class agreedgrade_cell extends cell_base{


    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return array|mixed|null|string
     */

    public function get_cell($submission, $student, $stage_identifier){

        $agreedgrade = $submission->get_agreed_grade();
        if($this->coursework->is_using_rubric() && $this->coursework->finalstagegrading != 1){
            $gradedata = array();
            $this->get_rubric_scores_gradedata($agreedgrade, $gradedata); // multiple parts are handled here
        } else {
            $gradedata = (!$agreedgrade)? '': $this->get_actual_grade($agreedgrade->grade);
        }

        return   $gradedata;
    }

    /**
     * @param $stage
     * @return array|mixed|string
     * @throws \coding_exception
     */
    public function get_header($stage){

        if ($this->coursework->is_using_rubric() && $this->coursework->finalstagegrading != 1) {
            $strings = array();
            $criterias = $this->coursework->get_rubric_criteria();
            foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                $strings['agreedgrade'.$criteria['id']] = 'Agreed grade - '.$criteria['description'];
                $strings['agreedgrade'.$criteria['id'] . 'comment'] = 'Comment for: Agreed grade - '.$criteria['description'];
            }
        } else {
            $strings = get_string('agreedgrade', 'coursework');
        }

        return $strings;
    }

    public function validate_cell($value,$submissionid,$stage_identifier='', $uploadedgradecells = array()) {

        global $DB,$PAGE,$USER;

        $stage_identifier = 'final_agreed_1';
        $agreedgradecap    =   array('mod/coursework:addagreedgrade','mod/coursework:editagreedgrade',
                                     'mod/coursework:addallocatedagreedgrade','mod/coursework:editallocatedagreedgrade');

        if (empty($value)) return true;

        if (has_any_capability($agreedgradecap,$PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context))   {

            $errormsg   =   '';

            if (!$this->coursework->is_using_rubric() || ($this->coursework->is_using_rubric() && $this->coursework->finalstagegrading == 1)) {
                $gradejudge = new grade_judge($this->coursework);
                if (!$gradejudge->grade_in_scale($value)){
                    $errormsg = get_string('valuenotincourseworkscale', 'coursework');
                    if (is_numeric($value)) {
                        // if scale is numeric get max allowed scale
                        $errormsg .= ' '. get_string('max_cw_mark', 'coursework').' '. $this->coursework->grade;
                    }
                    return $errormsg;
                }
            } else {

                //we won't be processing this line if it has no values, empty wont tell us this as it thinks that an array with
                //keys isnt. We will use array_filter whhich will return all values from the array if this is empty then we have
                //nothing to do

                $arrayvalues    =   array_filter($value);

                //if there are no values we don't need to do anything
                if (!empty($arrayvalues)) {

                    $i = 0;
                    $s = 0;

                    $criterias = $this->coursework->get_rubric_criteria();

                    foreach ($value as $data) {

                        //check if the value is empty however it can be 0
                        if (empty($data) && $data != 0) {

                            $errormsg .= ' ' . get_string('rubric_grade_cannot_be_empty', 'coursework');

                        }

                        //only check grades fields that will be even numbered
                        if ($i % 2 == 0) {

                            //get the current criteria
                            $criteria = array_shift($criterias);

                            //lets check if the value given is valid for the current rubric criteria
                            if (!$this->value_in_rubric($criteria, $data)) {
                                // if scale is numeric get max allowed scale
                                $errormsg .= ' ' . get_string('rubric_invalid_value', 'coursework') . ' ' . $data;
                            }
                            $s++;
                        }
                        $i++;
                    }
                } else {

                    //set value to false so that a submission not ready to grade message isn't returned
                    $value  =   false;

                }

            }

            if (!empty($errormsg))  return $errormsg;

            $subdbrecord =   $DB->get_record('coursework_submissions',array('id'=>$submissionid));
            $submission = \mod_coursework\models\submission::find($subdbrecord);


            //is the submission in question ready to grade?
            if (!$submission->all_inital_graded() && !empty($value) && count($uploadedgradecells) < $submission->max_number_of_feedbacks()) return get_string('submissionnotreadyforagreedgrade','coursework');

            //has the submission been published if yes then no further grades are allowed
            if ($submission->get_state() >= submission::PUBLISHED)  return $submission->get_status_text();

            //if you have administer grades you can grade anything
            if (has_capability('mod/coursework:administergrades', $PAGE->context)) return true;

            //has this submission been graded if yes then check if the current user graded it (only if allocation is not enabled).
            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );

            $feedback = feedback::find($feedback_params);

            $ability = new ability(user::find($USER), $this->coursework);

            //does a feedback exist for this stage
            if (empty($feedback)) {

                $feedback_params = array(
                    'submissionid' => $submissionid,
                    'assessorid' => $USER->id,
                    'stage_identifier' => $stage_identifier,
                );
                $new_feedback = feedback::build($feedback_params);


                //this is a new feedback check it against the new ability checks
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !has_capability('mod/coursework:addallocatedagreedgrade', $PAGE->context) && !$ability->can('new',$new_feedback))   return get_string('nopermissiontogradesubmission','coursework');
            } else {
                //this is a new feedback check it against the edit ability checks
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !$ability->can('edit',$feedback))   return get_string('nopermissiontoeditgrade','coursework');
            }





        } else {
            return get_string('nopermissiontoimportgrade', 'coursework');
        }

        return true;

    }


    /***
     * Check that the given value is within the values that can be excepted by the given rubric criteria
     *
     * @param $criteria the criteria array, this must contain the levels element
     * @param $value the value that should be checked to see if it is valid
     * @return bool
     */
    function    value_in_rubric($criteria,    $value)         {

        global  $DB;

        $valuefound =   false;

        $levels     =   $criteria['levels'];

        if (is_numeric($value) ) {
            foreach ($levels as $level) {

                if ((int)$level['score'] == (int)$value) {

                    $valuefound = true;
                    break;
                }

            }
        }


        return $valuefound;
    }

    /**
     * Takes the given cells and returns the cells with the singlegrade cell replaced by the rubric headers if the coursework instance
     * makes use of rubrics
     *
     * @param $csv_cells
     *
     */
    function    get_rubrics($coursework,$csv_cells)        {


        if ($coursework->is_using_rubric()  && $this->coursework->finalstagegrading != 1) {

            $rubricheaders      =       array();

            $criterias = $coursework->get_rubric_criteria();

            foreach ($criterias as  $criteria)   {
                $rubricheaders[]    =   $criteria['description'];
                $rubricheaders[]    =   $criteria['description']." comment";
            }


            //find out the position of singlegrade
            $position = array_search('singlegrade',$csv_cells);
            //get all data from the position of the singlegrade to the length of rubricheaders
            // $csv_cells     =   array_splice($csv_cells,5, 1, $rubricheaders);


            $start_cells        =   array_slice($csv_cells,0,$position,true);
            $end_cells          =   array_slice($csv_cells,$position+1,count($csv_cells),true);

            $cells              =   array_merge($start_cells,$rubricheaders);

            $cells              =   array_merge($cells,$end_cells);



        }


        return $cells;
    }


}