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

namespace mod_coursework\export;

use csv_export_writer;
use mod_coursework\ability;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Class csv is responsible for managing exports to a CSV file
 *
 * @package mod_coursework\export
 */
class csv {
    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var csv_export_writer
     */
    protected $csvexport;

    protected $dateformat;
    /**
     * @var array
     */
    protected $csv_cells;
    /**
     * @var string
     */
    protected $filename;

    /**
     * @param $coursework
     * @param $csv_cells
     * @param $filename
     */
    public function __construct($coursework, $csv_cells, $filename) {
        $this->coursework = $coursework;
        $this->dateformat = '%a, %d %b %Y, %H:%M';
        $this->csv_cells = $csv_cells;
        $this->filename = $filename;
    }

    /**
     * @throws \coding_exception
     */
    public function export(){

        $this->csvexport = new csv_export_writer();
        $this->add_filename($this->filename);

        $csv_data = array();
        // headers
        $this->add_headers($this->csv_cells);

        /**
         * @var submission[] $submissions
         */
        $submissions = $this->get_submissions();

        // sort submissions by lastname
        usort($submissions, array($this, "sort_by_lastname"));

        // loop through each submission in the coursework
        foreach ($submissions as $submission){
            // add data to cvs
            $data =  $this->add_csv_data($submission);
            $csv_data = array_merge($csv_data, $data);
        }

        $this->add_data_to_csv($csv_data);
        $this->csvexport->download_file();

        die;
    }

    /**
     * Create CSV cells
     * @param $submission
     * @param $student
     * @param $csv_cells
     * @return array
     */
    public function add_cells_to_array($submission,$student,$csv_cells){

        foreach($csv_cells as $csv_cell) {
            if(substr($csv_cell,0,8) == 'assessor'){
                $stage_dentifier = 'assessor_'.(substr($csv_cell,-1));
                $csv_cell = substr($csv_cell, 0, -1);
            }
            $class = "mod_coursework\\export\\csv\\cells\\".$csv_cell."_cell";
            $cell = new $class($this->coursework);
            if(substr($csv_cell,0,8) == 'assessor'){
                $row[] = $cell->get_cell($submission, $student, $stage_dentifier);
            } else if ($csv_cell != 'stages' && $csv_cell != 'moderationagreement' && $csv_cell != 'otherassessors'){
                $row[] = $cell->get_cell($submission, $student, false);
            } else {

                $stages = $cell->get_cell($submission, $student, false);
                $row =  array_merge($row,$stages);
            }

        }
        return $row;
    }


    /**
     * create headers for CSV
     * @param $csv_headers
     */
        public function add_headers($csv_headers){

            foreach($csv_headers as $header) {
                if(substr($header,0,8) == 'assessor'){
                    $stage = (substr($header,-1));
                    $header = substr($header, 0, -1);
                }
                $class = "mod_coursework\\export\\csv\\cells\\".$header."_cell";
                $cell = new $class($this->coursework);
                if(substr($header,0,8) == 'assessor'){
                    $headers[$header.$stage] = $cell->get_header($stage);
                } else if ($header != 'stages' && $header != 'moderationagreement' && $header != 'otherassessors' ) {
                    $headers[$header] = $cell->get_header(false);
                } else {
                    $array_headers = $cell->get_header(false);
                    $headers =  array_merge($headers,$array_headers);
                }
            }

            $this->csvexport->add_data($headers);

        }

    /**
     * Add filename to the CSV
     * @param $filename
     * @return string
     */
        public function add_filename($filename){

            $filename = clean_filename($filename);
            return $this->csvexport->filename = $filename.'.csv';
        }

    /**
     * Function to sort array in order of submission's lastname
     * @param $a
     * @param $b
     * @return int
     */
    protected function sort_by_lastname($a, $b){

        return strcmp($a->lastname, $b->lastname);

    }

    /**
     * @param array $csv_data
     */
    private function add_data_to_csv($csv_data) {
        foreach ($csv_data as $data){
            $this->csvexport->add_data($data);
        }
    }

    /**
     * @return array
     * @throws \coding_exception
     */
    public function get_submissions(){

        $params = array(
            'courseworkid' => $this->coursework->id
        );
        return submission::find_all($params);

    }

    /**
     * Function to add data to csv
     * @param submission $submission
     * @return array
     */
    public function add_csv_data($submission){

        $csv_data = array();
        // retrieve all students (even if group coursework)
        $students = $submission->get_students();

        foreach ($students as $student) {
            $csv_data[] = $this->add_cells_to_array($submission, $student, $this->csv_cells);
        }

        return $csv_data;
    }

    public function other_assessors_cells(){

        $cells = 0;
        for ($i = 1; $i < $this->coursework->get_max_markers() ; $i++) {
           $cells = $cells + 2; // one for grade, one for feedback
        }

        return $cells;


    }

}