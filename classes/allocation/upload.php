<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_coursework\allocation;

use mod_coursework\models;

/**
 * @package    mod
 * @subpackage coursework
 * @copyright  2016 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Class to manage assessor allocations upload
 */
class upload {

    public function __construct($coursework) {
        $this->coursework = $coursework;
    }

    /**
     * Validate csv content cell by cell
     *
     * @param $content
     * @param $encoding
     * @param $delimeter
     * @return array|bool
     * @throws \moodle_exception
     */
    public function validate_csv($content,$encoding,$delimeter){
        global $CFG, $DB;

        $assessor_identifier = $CFG->coursework_allocation_identifier;

        $iid = \csv_import_reader::get_new_iid('courseworkallocationsdata');
        $csvreader = new \csv_import_reader($iid, 'courseworkallocationsdata');

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
        // find out if this is a group or individual coursework
        $allocatabletype = $this->coursework->get_allocatable_type();
        // find all individual users or groups in this coursework
        $allocatables = $this->coursework->get_allocatables();
        $allocatables = ($allocatabletype == 'group')? array_keys($allocatables) : $allocatables;
        // find all assessors for this coursework
        $assessors  = get_enrolled_users($this->coursework->get_context(), 'mod/coursework:addinitialgrade');
        $assessors  = array_keys($assessors); // keep only assessors' ids
        $allocatablesinfile = array();

        $csv_cells = array('allocatable');
        $stages = $this->coursework->get_max_markers();
        for ($i=1; $i<=$stages; $i++){
            $csv_cells[] = 'assessor_'.$i;
        }

        while ($line = $csvreader->next()) {

            $cells = $csv_cells;
            $assessorsinfile = array();

            if (sizeof($line) != sizeof($csv_cells)) {$errors = get_string('incorrectfileformat', 'coursework'); break;}
            foreach ($line as $keynum => $value) {

                // validate allocatable (user or group)
                if ($cells[$keynum] == 'allocatable')   {
                    // check if allocatable exists in the file
                    if (empty($value)){$errors[$s] = get_string($allocatabletype .'namemissing', 'coursework'); break;}

                    if ($allocatabletype == 'user'){
                        // get user id
                        $suballocatable  = $DB->get_record('user', array($assessor_identifier=>$value));
                        $allocatable = ($suballocatable)? \mod_coursework\models\user::find($suballocatable->id): '';
                    } else {
                        // get group id
                        $suballocatable  = $DB->get_record('groups', array('courseid'=>$this->coursework->course,
                                                                        'name'=>$value));
                        $allocatable = ($suballocatable)? \mod_coursework\models\group::find($suballocatable->id) : '';
                    }

                    // check if allocatable exists in this coursework
                    if(!$allocatable || !in_array($allocatable->id, $allocatables)){$errors[$s] = get_string($allocatabletype .'notincoursework', 'coursework'); break;}
                    // duplicate user or group
                    if($allocatable && in_array($allocatable->id, $allocatablesinfile)){$errors[$s] = get_string('duplicate'. $allocatabletype , 'coursework') ; break;}
                    $allocatablesinfile[] = $allocatable->id;
                }


                // validate assessor if exists in the coursework and has one of the capabilities allowing them to grade
                // in initial stage
                if (substr($cells[$keynum],0,8) == 'assessor'){
                    // skip empty assessors fields
                    if(empty($value)){ continue;}

                    $assessor =  $DB->get_record('user', array($assessor_identifier=>$value));

                    if(!$assessor ||!in_array($assessor->id, $assessors)){$errors[$s] = get_string('assessornotincoursework', 'coursework', $keynum ); continue;}

                    // check if current assessor is not already allocated for this allocatable in different stage
                    // or is not already in the file in previous stage
                    if($assessor && ($this->coursework->assessor_has_allocation_for_student_not_in_current_stage($allocatable, $assessor->id, $cells[$keynum])
                        || in_array($assessor->id, $assessorsinfile))){
                        $errors[$s] = get_string('assessoralreadyallocated', 'coursework', $keynum); continue;
                    }
                    $assessorsinfile[] = $assessor->id;

                }
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
    public function process_csv($content, $encoding, $delimiter, $processingresults){

        global $CFG, $DB, $PAGE;

        $assessor_identifier = $CFG->coursework_allocation_identifier;

        $iid = \csv_import_reader::get_new_iid('courseworkallocationsdata');
        $csvreader = new \csv_import_reader($iid, 'courseworkallocationsdata');

        $readcount = $csvreader->load_csv_content($content, $encoding, $delimiter);
        $csvloaderror = $csvreader->get_error();

        if (!is_null($csvloaderror)) {
            print_error('csvloaderror', '', 'returnurl', $csvloaderror);
        }

        // find out if this is a group or individual coursework
        $allocatabletype = $this->coursework->get_allocatable_type();
        $columns = $csvreader->get_columns();

        if (empty($columns)) {
            $csvreader->close();
            $csvreader->cleanup();
            print_error('courseworkemptycsv', 'error', '');
        }

        $csvreader->init();

        $s = 0;
        $csv_cells = array('allocatable');
        $stages = $this->coursework->get_max_markers();
        for ($i=1; $i<=$stages; $i++){
            $csv_cells[] = 'assessor_'.$i;
        }

        while ($line = $csvreader->next()) {

            //we will not process the content of any line that has been flagged up with an error
            if ( is_array($processingresults) && array_key_exists($s,$processingresults) ) {
                $s++;
                continue;
            }

            $cells = $csv_cells;

            if (sizeof($line) != sizeof($csv_cells)) {$errors = get_string('incorrectfileformat', 'coursework'); break;}

            foreach ($line as $keynum => $value) {

                // create record in coursework_allocation_pairs
                // or update it

                // get allocatable
                if ($cells[$keynum] == 'allocatable')   {
                    if ($allocatabletype == 'user'){
                        // get user id
                        $suballocatable  = $DB->get_record('user', array($assessor_identifier=>$value));
                        $allocatable = ($suballocatable)? \mod_coursework\models\user::find($suballocatable->id): '';
                    } else {
                        // get group id
                        $suballocatable  = $DB->get_record('groups', array('courseid'=>$this->coursework->course,
                            'name'=>$value));
                        $allocatable = ($suballocatable)? \mod_coursework\models\group::find($suballocatable->id): '';
                    }
                }
                if (substr($cells[$keynum],0,8) == 'assessor' && !(empty($value))){

                    $assessor =  $DB->get_record('user', array($assessor_identifier=>$value));

                    $params = array('courseworkid' => $this->coursework->id,
                                    'allocatableid' => $allocatable->id,
                                    'allocatabletype' => $allocatabletype,
                                    'stage_identifier' => $cells[$keynum]);

                    $allocation = $DB->get_record('coursework_allocation_pairs', $params);

                    if(!$allocation){
                        // create allocation
                        $this->add_allocation($assessor->id, $cells[$keynum], $allocatable);

                    } else {
                        // update allocation if submission was not marked yet
                        $subdbrecord = $DB->get_record('coursework_submissions', array('courseworkid'=>$this->coursework->id,
                                                                                       'allocatabletype'=>$allocatabletype,
                                                                                       'allocatableid'=>$allocatable->id));
                        $submission = \mod_coursework\models\submission::find($subdbrecord);

                        if (!$submission || !$submission->get_assessor_feedback_by_stage($cells[$keynum])) {
                            $this->update_allocation($allocation->id, $assessor->id);
                        }
                    }

                }
            }

            $s++;
        }

        return (!empty($errors)) ?  $errors : false;

    }


    /**
     * Add allocation pair
     *
     * @param $assessorid
     * @param $stage_identifier
     * @param $allocatableid
     * @param $allocatabletype
     * @return bool|int
     */
    public function add_allocation($assessorid, $stage_identifier, $allocatable){
        global $DB;

        $add_allocation                      =   new \stdClass();
        $add_allocation->id                  =   '';
        $add_allocation->courseworkid        =   $this->coursework->id;
        $add_allocation->assessorid          =   $assessorid;
        $add_allocation->manual              =   1;
        $add_allocation->stage_identifier    =   $stage_identifier;
        $add_allocation->allocatableid       =   $allocatable->id();
        $add_allocation->allocatabletype     =   $allocatable->type();

        $allocationid = $DB->insert_record('coursework_allocation_pairs', $add_allocation, true);

        return $allocationid;

    }


    /**
     * Update allocation pair
     *
     * @param $allocationid
     * @param $assessorid
     * @return bool
     */
    public function update_allocation($allocationid, $assessorid){
        global $DB;

        $update_allocation              =   new \stdClass();
        $update_allocation->id          =   $allocationid;
        $update_allocation->manual      =   1;
        $update_allocation->assessorid  =   $assessorid;

        $update = $DB->update_record('coursework_allocation_pairs', $update_allocation);
        return $update;
    }

}