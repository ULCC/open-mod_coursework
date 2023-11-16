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

            $csv = $this->remove_other_assessors_grade($csv_cells, $line);

            $cells = $csv;

            $i = 0;


            $id = false;
            $submissionid = false;


            // if the csv headers count is different than expected return error
            if ((!$this->coursework->is_using_rubric() && sizeof($line) != sizeof($cells)) || ($this->coursework->is_using_rubric() && !$this->rubric_count_correct($cells,$line))) {$errors = get_string('incorrectfileformat', 'coursework'); break;}

            $offset     =   0;

            //holds details on grades that have been successfully uploaded for the current line
            $uploadedgradecells    =   array();


            for($z = 0; $z < count($line); $z++)   {

                $value  =   $line[$z];
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


                //offsets the position of that we extract the data from $line based on data that has been extracted before


                if (($cells[$i] == "singlegrade" || $cells[$i] == "assessorgrade" || $cells[$i] == "agreedgrade")
                    && $this->coursework->is_using_rubric() && !($cells[$i] == "agreedgrade" && $this->coursework->finalstagegrading == 1)) {

                    //get the headers that would contain the rubric grade data
                    $rubricheaders      =       $cell->get_header(null);

                    //find out the position of singlegrade
                    $position = $i;

                    //get all data from the position of the grade to the length of rubricheaders
                    $rubriclinedata     =   array_slice($line,$position + $offset, count($rubricheaders), true);

                    //pass the rubric data in
                    $result = $cell->validate_cell($rubriclinedata, $submissionid, $stage_identifier, $uploadedgradecells);

                    $z  = $z    +   count($rubricheaders)-1;
                    $offset     =   $offset + count($rubricheaders)-1;

                }   else    {
                    $result = $cell->validate_cell($value, $submissionid, $stage_identifier, $uploadedgradecells);
                }


               if ($result !== true)   {
                   $errors[$s] = $result;
                   break; //go to next line on error
               } else if ($cells[$i] == "singlegrade" || $cells[$i] == "assessorgrade" || $cells[$i] == "agreedgrade" && !empty($value)) {

                   $uploadedgradecells[]   =   $stage_identifier;

               }
               $i++;
            }
            $s++;
        }

        return (!empty($errors)) ?  $errors : false  ;
    }


    function rubric_count_correct($csvheader,$linefromimportedcsv)         {

        // get criteria of rubrics and match it to grade cells
        if ($this->coursework->is_using_rubric()) {

            $types  =   array("singlegrade","assessorgrade");

            if ($this->coursework->finalstagegrading == 0 ) $types[] = "agreedgrade";

            foreach($types  as $type) {

                $typefound  =   false;

                //$typepositions holds the places in the array of all vcolumns with headers that
                //match the type e.g a column named singlegrade1 will match a singlegrade type
                $typepositions   =   false;
                $i  =   0;

                foreach($csvheader  as  $ch)   {

                    if (strpos($ch,$type) !== false)  {

                        if (empty($typepositions))   $typepositions   =   array();

                        $typefound  =   true;
                        $typepositions[]   =   $i;
                        //break;
                    }
                    $i++;
                }

                if(!empty($typefound)) {

                    //this var is needed to provide an offset so the positions in the array we are looking for
                    //are correct even after a splice and add is carried out
                    $offset   =   0;

                    foreach($typepositions as $position) {
                        //if  ($position  =   array_search($type,$csvheader)) {
                            $class = "mod_coursework\\export\\csv\\cells\\{$type}_cell";
                        $cell = new $class($this->coursework);

                        $headers = $cell->get_header(null);

                            unset($csvheader[$position + $offset]);
                            unset($linefromimportedcsv[$position + $offset]);
//                        if ($type == 'agreedgrade' && $this->coursework->finalstagegrading == 0) {
                            array_splice($csvheader, $position + $offset, 0, array_keys($headers));
                            array_splice($linefromimportedcsv, $position + $offset, 0, array(''));
//                        }
                            $offset = $offset + count($headers) - 1;
                            $expectedsize = (int)sizeof($csvheader);
                            $actualsize = (int)sizeof($linefromimportedcsv);


                    }

                }
            }

            return  $expectedsize != $actualsize ? false : true;


        }
    }

    function get_rubric_headers($csvheader)         {

        // get criteria of rubrics and match it to grade cells
        if ($this->coursework->is_using_rubric()) {

            $types  =   array("singlegrade","assessorgrade");

            foreach($types  as $type) {

                if ($position = array_search($type, $csvheader)) {

                    $class = "mod_coursework\\export\\csv\\cells\\{$type}_cell";
                    $cell = new $class($this->coursework);

                    $headers = $cell->get_header(null);
                    unset($csvheader[$position]);

                    array_splice($csvheader, $position, 0, array_keys($headers));

                }
            }


        }

        return $csvheader;
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

        global $DB, $PAGE, $USER;

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
            //gets the headers that should be being used in the uploaded csv
            //$cells = $this->get_rubric_headers($csv);
            $cells = $csv;
            $i = 0;

            $csvline =  array();


            //if ((!$this->coursework->is_using_rubric() && sizeof($line) != sizeof($cells)) || ($this->coursework->is_using_rubric() && !$this->rubric_count_correct($csv,$line))) {
            //if (sizeof($line) != sizeof($cells)) {
            if ((!$this->coursework->is_using_rubric() && sizeof($line) != sizeof($cells)) || ($this->coursework->is_using_rubric() && !$this->rubric_count_correct($csv,$line))) {

                $errors = get_string('incorrectfileformat', 'coursework');
                break;
            }

            $idfound    =   false;

            foreach ($line as $keynum => $value) {

                if (empty($idfound) && $cells[$i] == 'submissionid')   {
                    $submissionid =   $value;
                    $idfound    =   true;
                }

                //save the value into the csvline with the relevant pointer
                if (isset($cells[$i]))   $csvline[$cells[$i]] = $value;

                $i++;
            }

            $subdbrecord = $DB->get_record('coursework_submissions', array('id'=>$submissionid));
            $submission = \mod_coursework\models\submission::find($subdbrecord);


            //is this submission graded? if yes did this user grade it?


            $coursework     =   $submission->get_coursework();

            $stages = array();

            if (!$coursework->has_multiple_markers()) {
                $stages['singlegrade'] = $this->get_stage_identifier($csvline['submissionid'], 'singlegrade');
                if (array_key_exists('agreedgrade',$csvline)) {
                    $stages['agreedgrade'] =  'final_agreed_1';
                }
            } else {

                foreach($csvline as $k => $v)   {
                    if (substr($k,0,13) == 'assessorgrade' || substr($k,0,11) == 'singlegrade') {
                       $stages[$k] =  $this->get_stage_identifier($csvline['submissionid'], $k);
                    } else if (substr($k,0,11) == 'agreedgrade') {
                       $stages[$k] =  'final_agreed_1';
                    }
                }
            }


            $a = 1;

            //defines the start offest to be used when searching for a rubric in a uploaded csv, if the format of upload
            //csv is changed this will require changing

            $rubricoffset     =   $rubricoffsetstart     =   ($coursework->is_configured_to_have_group_submissions()) ?   4   :   5;



            $numberofstages   =     count($stages);

            foreach($stages as $k => $stage) {

                //when allocation is enabled
                if (has_capability('mod/coursework:administergrades', $PAGE->context) && $coursework->allocation_enabled() && $stage != 'final_agreed_1' && $coursework->has_multiple_markers() == true)  {
                    $rubricoffset   +=   1;
                    if ($a == 1) $rubricoffsetstart  += 1;
                }
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

                //we need to carry out a further check to see if the coursework is using advanced grades.
                //if yes then we may need to generate the grade for the grade pointer as
                //they dont have grades



                if ($coursework->is_using_rubric() && !($stage == 'final_agreed_1' && $this->coursework->finalstagegrading == 1)) {


                        //array that will hold the advanced grade data
                        $criteriagradedata                  =   array();
                        $criteriagradedata['criteria']      =   array();

                        $criterias = $this->coursework->get_rubric_criteria();

                        $criteriacount = 0;

                        $numberofrubrics            =    count($criterias)  *   2;

                    //if the stage is  final agrade we need to make sure the offset is set to the position of the
                    //agreed grades in the csv, this is needed as some users will only have agreed grade capability
                    if ($stage == 'final_agreed_1') {
                        $stagemultiplier    =   $numberofstages -1;

                        //the calculation below finds the position of the agreed grades in the uploaded csv


                        $rubricoffset      =       $rubricoffsetstart + $stagemultiplier + ($numberofrubrics * $stagemultiplier);


                        if ($coursework->allocation_enabled())  $rubricoffset  += 1;
                        $rubricdata = array_slice($line, $rubricoffset, $numberofrubrics);

                        $feedbackdata = array_slice($line, $rubricoffset+$numberofrubrics, 1);

                        $csvline[$feedbackpointer]  =   $feedbackdata[0];

                    } else {

                        $rubricdata = array_slice($line, $rubricoffset, $numberofrubrics);

                        $feedbackdata = array_slice($line, $rubricoffset+$numberofrubrics, 1);

                        $csvline[$feedbackpointer]  =   $feedbackdata[0];

                        $rubricoffset      =       $rubricoffset + $numberofrubrics + 1;
                    }




                        $arrayvalues    =   array_filter($rubricdata);

                        if (!empty($arrayvalues)) {

                            //for ( $critidx < $numberofrubrics; ) {
                            $critidx = 0;
                            
                            //this assumes that the data in the csv is in the correct criteria order.....it should be
                            foreach ($criterias as $c) {
                                $criteriagrade = array();

                                //we need to get the levelid for the value that the criteria has been given

                                $levelid = $this->get_value_rubric_levelid($c, $rubricdata[$critidx]);


                                $criteriagrade['levelid'] = $levelid;
                                $criteriagrade['remark'] = $rubricdata[$critidx + 1];


                                $criteriagradedata['criteria'][$c['id']] = $criteriagrade;

                                $critidx = $critidx + 2;

                            }
                        } else {
                            $criteriagradedata      =   false;
                        }


                        //need to decide where the grade instance submit and get grade should be put as in order

                        //pass the criteria  data into the csvline position for the grade data so we can generate a grade
                        $csvline[$gradepointer]     =   $criteriagradedata;

                        //in case there is another rubric to be extracted from the csv set the new value of the rubric offset



                    } else  if ($coursework->is_using_rubric() && ($stage == 'final_agreed_1' && $this->coursework->finalstagegrading == 1)) {


                        if (!isset($numberofrubrics))   {

                            $criterias = $this->coursework->get_rubric_criteria();

                            $numberofrubrics            =    count($criterias)  *   2;

                        }

                        $stagemultiplier    =   $numberofstages -1;

                        //the calculation below finds the position of the agreed grades in the uploaded csv


                        $rubricoffset      =       $rubricoffsetstart + $stagemultiplier + ($numberofrubrics * $stagemultiplier);


                        if ($coursework->allocation_enabled())  $rubricoffset  += 1;

                        $gradearrvalue = array_slice($line, $rubricoffset, 2);

                        $csvline[$gradepointer]     =   $gradearrvalue[0];
                        $csvline[$feedbackpointer]  =   $gradearrvalue[1];

                    }


                // don't create/update feedback if grade is empty
                if (!empty($csvline[$gradepointer])) {

                    $stageusesrubric    =   ($this->coursework->is_using_rubric()
                        && !($stage == 'final_agreed_1' && $this->coursework->finalstagegrading)) ? true : false;

                    if (empty($grade)) {
                        $cwfeedbackid =  $this->add_grade($csvline['submissionid'], $csvline[$k], $csvline[$feedbackpointer], $stage,$stageusesrubric);

                    } else {
                        $cwfeedbackid = $this->get_coursework_feedback_id($csvline['submissionid'], $stage);
                        $this->edit_grade($cwfeedbackid, $csvline[$k], $csvline[$feedbackpointer], $stageusesrubric);
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


    /***
     * Returns the levelid for the given rubric value
     *
     * @param $criteria the criteria array, this must contain the levels element
     * @param $value the value that we will retrieve the levelid for
     * @return bool
     */
    function    get_value_rubric_levelid($criteria,    $value)         {

        global  $DB;

        $idfound =   false;

        $levels     =   $criteria['levels'];

        if (is_numeric($value) ) {
            foreach ($levels as $level) {

                if ((int)$level['score'] == (int)$value) {

                    $idfound = $level['id'];
                    break;
                }

            }
        }


        return $idfound;
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
    public function add_grade($submissionid, $grade, $feedback, $stage_identifier,$uses_rubric=false){
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

        //we cant save the grade if this coursework uses rubrics as the grade has not been generated and the grade var contains
        //criteria that will be used to genenrate the grade. We need the feedback id to do this so we need to make the feedback
        //first
        $add_grade->grade             =   (!$uses_rubric)    ?   $grade  :   null;
        $add_grade->feedbackcomment   =   $feedback;
        $add_grade->lasteditedbyuser  =   $USER->id;
        $add_grade->markernumber      =   $markernumber;
        $add_grade->stage_identifier  =   $stage_identifier;
        $add_grade->finalised         =   1;

        $feedbackid = $DB->insert_record('coursework_feedbacks', $add_grade, true);

        if  ($uses_rubric)   {
            $controller = $this->coursework->get_advanced_grading_active_controller();
            //find out how many criteria there are
            $gradinginstance = $controller->get_or_create_instance(0, $USER->id,$feedbackid);
            $rubricgrade     =   $gradinginstance->submit_and_get_grade($grade, $feedbackid);

            $add_grade->id          =   $feedbackid;
            $add_grade->grade       =   $rubricgrade;

            $DB->update_record('coursework_feedbacks', $add_grade);

        }


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
    public function edit_grade($cwfeedbackid, $grade, $feedback,$uses_rubric=false){
        global $DB, $USER;

        if (!$uses_rubric) {
            $gradejudge = new grade_judge($this->coursework);
            $grade = $gradejudge->get_grade($grade);
        }   else    {
            $controller = $this->coursework->get_advanced_grading_active_controller();
            //find out how many criteria there are
            $gradinginstance = $controller->get_or_create_instance(0, $USER->id,$cwfeedbackid);
            $rubricgrade     =   $gradinginstance->submit_and_get_grade($grade, $cwfeedbackid);
            $grade           =   $rubricgrade;

        }

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
            if ($this->coursework->is_using_rubric())    {

                $singlegradeposition = array_search('singlegrade', $csv_cells);

                $criterias = $this->coursework->get_rubric_criteria();

                $startposition   =   $singlegradeposition+ ((count($criterias) *2) +1);

            } else {
                $startposition = array_search('otherassessors', $csv_cells);
            }

            for ($i = $startposition; $i < $startposition+$othercells ; $i++) {
                unset($line[$i]);
            }
            $csv_cells  =array_values($csv_cells);
            $line = array_values($line);

        }

       return $csv_cells;


    }
}