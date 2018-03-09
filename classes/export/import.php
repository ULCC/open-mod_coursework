<?php
namespace mod_coursework\export;
use mod_coursework\export\csv;
use mod_coursework\grade_judge;
use mod_coursework\controllers;
use mod_coursework\models\coursework;
use mod_coursework\auto_grader\auto_grader;

global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');





class import extends grading_sheet{


    public function validate_submissionfileid(){


        $submissions = $this->get_submissions();



    }

    /**
     * Validate csv content cell by cell
     *
     * @param $content
     * @param $encoding
     * @param $delimeter
     * @param $csv_cells
     * @return array|bool
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function validate_csv($content,$encoding,$delimeter,$csv_cells)    {

        global $DB, $USER;

        $iid = \csv_import_reader::get_new_iid('courseworkgradingdata');
        $csvreader = new \csv_import_reader($iid, 'courseworkgradingdata');

        $readcount = $csvreader->load_csv_content($content, $encoding, $delimeter);
        $csvloaderror = $csvreader->get_error();

        if (!is_null($csvloaderror)) {
            print_error('csvloaderror', '', 'returnurl', $csvloaderror);
        }


        $columns = $csvreader->get_columns();

        if (empty($columns)) {
            $csvreader->close();
            $csvreader->cleanup();
            print_error('courseworkemptycsv', 'error', '');
        }

        $csvreader->init();

        $errors =   array();
        $s = 0;

        $submissions = $this->get_submissions();


        while ($line = $csvreader->next()) {

          /*  if(in_array('otherassessors', $csv_cells)){
              // find position of otherassesors so we know from which key to unset
                $key = array_search('otherassessors', $csv_cells);
                unset($csv_cells['otherassessors']);
                $othercells = $this->other_assessors_cells();

                for ($i = $key; $i <= $othercells ; $i++) {
                    unset($line[$key]);
                }
            }*/

            $csv = $this->remove_other_assessors_grade($csv_cells, $line);

            $cells = $csv;

            $i = 0;


            $id = false;
            $submissionid = false;


           if (sizeof($line) != sizeof($cells)) {$errors = get_string('incorrectfileformat', 'coursework'); break;}
            foreach ($line as $keynum => $value) {

                $stage_identifier = $this->get_stage_identifier($submissionid,$cells[$i]);

                // remove numbers from cell names so they can be dynamically validated
                if(substr($cells[$i],0,8) == 'assessor'){
                    $cells[$i] = substr($cells[$i], 0, -1);
                }

                $class = "mod_coursework\\export\\csv\\cells\\".$cells[$i]."_cell";
                $cell = new $class($this->coursework);
                //submission id field should always be first in the csv_cells array
                //
                if ($cells[$i] == 'submissionid')   {
                    $submissionid =   $value;
                }

                if (empty($submissionid)) $errors[$s][]     =   get_string('emptysubmissionid','coursework');

                $result =   $cell->validate_cell($value,$submissionid,$stage_identifier);



               if ($result !== true)   {
                   $errors[$s] = $result;
                   break; //go to next line on error
               }
                $i++;
            }
            $s++;
        }

        return (!empty($errors)) ?  $errors : false  ;
    }


    /**
     * Process csv and add records to the DB
     *
     * @param $content
     * @param $encoding
     * @param $delimiter
     * @param $csv_cells
     * @param $processingresults
     * @return array|bool
     * @throws \moodle_exception
     */
    public function process_csv($content, $encoding, $delimiter, $csv_cells, $processingresults){

        global $DB, $PAGE;

        $iid = \csv_import_reader::get_new_iid('courseworkgradingdata');
        $csvreader = new \csv_import_reader($iid, 'courseworkgradingdata');

        $readcount = $csvreader->load_csv_content($content, $encoding, $delimiter);
        $csvloaderror = $csvreader->get_error();

        if (!is_null($csvloaderror)) {
            print_error('csvloaderror', '', 'returnurl', $csvloaderror);
        }


        $columns = $csvreader->get_columns();

        if (empty($columns)) {
            $csvreader->close();
            $csvreader->cleanup();
            print_error('courseworkemptycsv', 'error', '');
        }

        $csvreader->init();

        $s = 0;


        $submissionid = false;

        while ($line = $csvreader->next()) {

            //we will not process the content of any line that has been flagged up with an error
            if ( is_array($processingresults) && array_key_exists($s,$processingresults) ) {
                $s++;
                continue;
            }


            $csv = $this->remove_other_assessors_grade($csv_cells, $line);
            $cells = $csv;

            $i = 0;

            $csvline =  array();
            if (sizeof($line) != sizeof($cells)) {$errors = get_string('incorrectfileformat', 'coursework'); break;}

            foreach ($line as $keynum => $value) {

                if ($cells[$i] == 'submissionid')   {
                    $submissionid =   $value;
                }

                //save the value into the csvline with the relevant pointer
                $csvline[$cells[$i]] = $value;

                $i++;
            }

            $subdbrecord = $DB->get_record('coursework_submissions', array('id'=>$submissionid));
            $submission = \mod_coursework\models\submission::find($subdbrecord);


            //is this submission graded? if yes did this user grade it?


            $stages = array();

            if (array_key_exists('singlegrade',$csvline)) {
                $stages['singlegrade'] = $this->get_stage_identifier($csvline['submissionid'], 'singlegrade');
                if (array_key_exists('agreedgrade',$csvline)) {
                    $stages['agreedgrade'] =  'final_agreed_1';
                }
            } else {

                foreach($csvline as $k => $v)   {
                    if (substr($k,0,13) == 'assessorgrade') {
                       $stages[$k] =  $this->get_stage_identifier($csvline['submissionid'], $k);
                    } elseif (substr($k,0,11) == 'agreedgrade') {
                       $stages[$k] =  'final_agreed_1';
                    }
                }
            }


            $a = 1;
            foreach($stages as $k => $stage) {

                // check for initial grade capability otherwise ignore it
                if ($stage != 'final_agreed_1' && (!has_capability('mod/coursework:addinitialgrade', $PAGE->context)) &&
                    (!has_capability('mod/coursework:administergrades', $PAGE->context))){
                    continue;
                }

                $grade = $submission->get_assessor_feedback_by_stage($stage);

                $feedbackpointer    = 'assessorfeedback'.$a;
                $gradepointer = 'assessorgrade'.$a;
                if ($k == 'singlegrade') {
                    $feedbackpointer    = 'feedbackcomments';
                    $gradepointer = 'singlegrade';
                } else if ($k == 'agreedgrade') {
                    $feedbackpointer    = 'agreedfeedback';
                    $gradepointer = 'agreedgrade';
                }

                // if sampling enabled check if this grade should be included in sample
                if ($this->coursework->sampling_enabled() && $stage != 'final_agreed_1'){
                    $in_sample = $submission->get_submissions_in_sample_by_stage($stage);
                    if (!$in_sample && $stage != 'assessor_1'){
                        continue;
                    }
                }

                // don't create/update feedback if grade is empty
                if (!empty($csvline[$gradepointer])) {
                    if (empty($grade)) {
                        $cwfeedbackid =  $this->add_grade($csvline['submissionid'], $csvline[$k], $csvline[$feedbackpointer], $stage);

                    } else {
                        $cwfeedbackid = $this->get_coursework_feedback_id($csvline['submissionid'], $stage);
                        $this->edit_grade($cwfeedbackid, $csvline[$k], $csvline[$feedbackpointer]);
                    }
                    // if feedback created and coursework has automatic grading enabled update agreedgrade
                    if ($cwfeedbackid && $this->coursework->automaticagreement_enabled()) {
                        $this->auto_agreement($cwfeedbackid);
                    }
                }
                $a++;

            }

            $s++;
        }

        return (!empty($errors)) ?  $errors : false  ;



    }

    /**
     * Add grade and feedback record
     *
     * @param $submissionid
     * @param $grade
     * @param $feedback
     * @param $stage_identifier
     * @return bool|int
     */
    public function add_grade($submissionid, $grade, $feedback, $stage_identifier){
        global $DB, $USER;

        // workout markernumber
        if ($stage_identifier == 'assessor_1'){
           // assessor_1 is always marker 1
            $markernumber = 1;
        } else {
            //  get all feedbacks and add 1
            $feedbacks = $DB->count_records('coursework_feedbacks',array('submissionid'=>$submissionid));
            $markernumber = $feedbacks +1;
        }

        $gradejudge = new grade_judge($this->coursework);
        $grade  =   $gradejudge->get_grade($grade);

        $add_grade                    =   new \stdClass();
        $add_grade->id                =   '';
        $add_grade->submissionid      =   $submissionid;
        $add_grade->assessorid        =   $USER->id;
        $add_grade->timecreated       =   time();
        $add_grade->timemodified      =   time();
        $add_grade->grade             =   $grade;
        $add_grade->feedbackcomment   =   $feedback;
        $add_grade->lasteditedbyuser  =   $USER->id;
        $add_grade->markernumber      =   $markernumber;
        $add_grade->stage_identifier  =   $stage_identifier;
        $add_grade->finalised         =   1;

        $feedbackid = $DB->insert_record('coursework_feedbacks', $add_grade, true);

        return $feedbackid;
    }


    /**
     * Get feedbackid of existing feedback
     *
     * @param $submissionid
     * @param $stage_identifier
     * @return mixed
     */
    public function get_coursework_feedback_id($submissionid, $stage_identifier){
        global $DB;

        $record = $DB->get_record('coursework_feedbacks', array('submissionid' => $submissionid,
                                                               'stage_identifier' => $stage_identifier),
                                  'id');

        return $record->id;
    }

    /**
     * Edit grade and feedback record
     *
     * @param $cwfeedbackid
     * @param $grade
     * @param $feedback
     * @return bool]
     */
    public function edit_grade($cwfeedbackid, $grade, $feedback){
        global $DB, $USER;

        $gradejudge = new grade_judge($this->coursework);
        $grade  =   $gradejudge->get_grade($grade);

        $update = false;

        // update record only if the value of grade or feedback is changed
        $current_feedback = $DB->get_record('coursework_feedbacks', array('id' => $cwfeedbackid));

        if ($current_feedback->grade != $grade || strip_tags($current_feedback->feedbackcomment) != $feedback){

            $edit_grade = new \stdClass();
            $edit_grade->id = $cwfeedbackid;
            $edit_grade->timemodified = time();
            $edit_grade->grade = $grade;
            $edit_grade->feedbackcomment = $feedback;
            $edit_grade->lasteditedbyuser = $USER->id;
            $edit_grade->finalised = 1;

             $update = $DB->update_record('coursework_feedbacks', $edit_grade);

             // if record updated and coursework has automatic grading enabled update agreedgrade
             if ($update && $this->coursework->automaticagreement_enabled()) {
                  $this->auto_agreement($cwfeedbackid);
             }
        }

        return $update;

    }

    /**
     * Get stage_identifier for the current submission
     *
     * @param $submissionid
     * @param $cell_identifier
     * @return string
     * @throws \dml_missing_record_exception
     * @throws \dml_multiple_records_exception
     */
    public function get_stage_identifier($submissionid,$cell_identifier) {

        global $DB, $USER;
        $submission = $DB->get_record('coursework_submissions', array('id'=>$submissionid));


        $submission =  \mod_coursework\models\submission::find($submission);

        // single marked - singlegrade - allocated/notallocated
        $stage_identifier = 'assessor_1';


        //double marked - singlegrade - allocated
        if($this->coursework->get_max_markers()>1 && ($cell_identifier == 'singlegrade' || $cell_identifier == 'feedbackcomments')
            && $this->coursework->allocation_enabled()){

            $dbrecord = $DB->get_record('coursework_allocation_pairs',
                                               array('courseworkid'=>$this->coursework->id,
                                                     'allocatableid'=>$submission->allocatableid,
                                                     'allocatabletype'=>$submission->allocatabletype,
                                                     'assessorid' => $USER->id
                                                     ));
            $stage_identifier = $dbrecord->stage_identifier;
        }


        //double marked - singlegrade - notallocated
        if($this->coursework->get_max_markers()>1 && ($cell_identifier == 'singlegrade' || $cell_identifier == 'feedbackcomments')
            && !$this->coursework->allocation_enabled()){

            // if any part of initial submission graded by the user then get stage_identifier from feedback
            // else workout
            $sql = "SELECT stage_identifier FROM {coursework_feedbacks}
                    WHERE submissionid = $submissionid
                    AND assessorid = $USER->id
                    AND stage_identifier <> 'final_agreed_1'";
            $record = $DB->get_record_sql($sql);
            if (!empty($record)) {
                $stage_identifier = $record->stage_identifier;
            }else if (!$this->coursework->sampling_enabled()){ //samplings disabled
                // workout if any stage is still available
                $sql = "SELECT count(*) as graded FROM {coursework_feedbacks}
                        WHERE submissionid = $submissionid
                        AND stage_identifier <> 'final_agreed_1'";
                $record = $DB->get_record_sql($sql);

                if ($this->coursework->get_max_markers()>$record->graded) {
                    $stage = $record->graded+1;
                    $stage_identifier = 'assessor_' . $stage;
                }
            } else if ($this->coursework->sampling_enabled()){ // samplings enabled
                $in_sample = ($subs = $submission->get_submissions_in_sample()) ? sizeof($subs) : 0;
                $feedback = $DB->record_exists('coursework_feedbacks', array('submissionid'=>$submissionid,
                                                                             'stage_identifier'=>'assessor_1'));
                // no sample or no feedback for sample yet
                if (!$in_sample || ($in_sample && !$feedback)){
                   $stage_identifier = 'assessor_1';
                } else { // find out which sample wasn't graded yet
                   $samples = $submission->get_submissions_in_sample();
                   foreach ($samples as $sample){
                      $feedback = $DB->record_exists('coursework_feedbacks', array('submissionid'=>$submissionid,
                                                                                    'stage_identifier'=>$sample->stage_identifier));
                        // if feedback doesn't exist, we'll use this stage identifier for a new feedback
                       if (!$feedback){
                           $stage_identifier = $sample->stage_identifier;
                           break;
                       }
                   }
               }
            }

        }


        // double marked - multiplegrade - allocated/notallocated
        if($this->coursework->get_max_markers()>1 && ($cell_identifier != 'singlegrade' && $cell_identifier != 'feedbackcomments')) {
            if (substr($cell_identifier, 0, 8) == 'assessor') {
                $stage_identifier = 'assessor_' . (substr($cell_identifier, -1));
                //$cells[$i] = substr($cells[$i], 0, -1);
            } else if(substr($cell_identifier, 0, 6) == 'agreed')  {
                $stage_identifier = 'final_agreed_1';
            }
        }


        return $stage_identifier;
    }


    /**
     * Create agreed grade if all initial grade are present
     * @param $cwfeedbackid
     */
    public function auto_agreement($cwfeedbackid){
        global $DB;

        $feedback = $DB->get_record('coursework_feedbacks', array('id' => $cwfeedbackid));
        $feedback = \mod_coursework\models\feedback::find($feedback);

        $auto_feedback_classname = '\mod_coursework\auto_grader\\' . $this->coursework->automaticagreementstrategy;
        /**
         * @var auto_grader $auto_grader
         */
        $auto_grader = new $auto_feedback_classname($this->coursework,
                                                    $feedback->get_submission()->get_allocatable(),
                                                    $this->coursework->automaticagreementrange);
        $auto_grader->create_auto_grade_if_rules_match();
    }


   public function remove_other_assessors_grade($csv_cells, &$line){

       $otherassessors = false;

        if(in_array('otherassessors', $csv_cells)){
            // find position of otherassesors so we know from which key to unset
            $key = array_search('otherassessors', $csv_cells);
            unset($csv_cells[$key]);
            $othercells = $this->other_assessors_cells();

            for ($i = $key; $i < $key+$othercells ; $i++) {
                unset($line[$i]);
            }
            $csv_cells  =array_values($csv_cells);
            $line = array_values($line);

        }

       return $csv_cells;


    }
}