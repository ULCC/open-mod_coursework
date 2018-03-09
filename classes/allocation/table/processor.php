<?php

/**
 * This class is here to take the data from the table on the allocation page and process each
 * of the rows. It's job is to know which other classes need to be called to process each rom
 */

namespace mod_coursework\allocation\table;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\group;
use mod_coursework\models\user;

/**
 * Class form_table_processor
 * @package mod_coursework\allocation
 */
class processor {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @param coursework $coursework
     */
    public function __construct($coursework) {
        $this->coursework = $coursework;
    }

    /**
     * @param array $table_data
     */
    public function process_data($table_data = array()) {
        $clean_data = $this->clean_data($table_data);
        $allocatables = $this->coursework->get_allocatables();

        foreach ($allocatables as $allocatable) {
            if (array_key_exists($allocatable->id(), $clean_data)) {
                $row_data = $clean_data[$allocatable->id()];
            } else {
                $row_data = array();
            }

            $allocatable = $this->get_allocatable_from_id($allocatable->id());
            $row_object = $this->get_row($allocatable);
            $row_object->process($row_data);
        }
    }

    /**
     * @param allocatable $allocatable
     * @return row\processor
     */
    private function get_row($allocatable) {
        return new row\processor($this->coursework, $allocatable);
    }

    /**
     * Sanitises the data, mostly making sure that we have ony valid student ids and valid stage identifiers.
     * The stages will deal with sanitising the data for each cell.
     *
     * @param array $raw_data
     * @return array
     */
    private function clean_data($raw_data) {

        // Data looks like this:
//        $example_data = array(
//            4543 => array( // Student id
//                'assessor_1' => array(
//                    'allocation_id' => 43,
//                    'assessor_id' => 232,
//                ),
//                'moderator_1' => array(
//                    'allocation_id' => 46,
//                    'assessor_id' => 235,
//                    'in_set' => 1,
//                )
//            )
//        );

        $clean_data = array();
        foreach ($raw_data as $allocatable_id => $datarrays) {

            if (!$this->allocatable_id_is_valid($allocatable_id)) { // Should be the id of a student.
                continue;
            }

            $clean_data[$allocatable_id] = array();

            foreach ($this->coursework->marking_stages() as $stage) {

                if (array_key_exists($stage->identifier(), $datarrays)) {
                    $stage_data = $datarrays[$stage->identifier()];
                    $clean_data[$allocatable_id][$stage->identifier()] = $stage_data;
                }
            }
           /* if (array_key_exists('moderator', $datarrays)) {
                $moderator_data = $datarrays['moderator'];
                $clean_data[$allocatable_id]['moderator'] = $moderator_data;
            }*/
        }
        return $clean_data;
    }

    /**
     * @param int $student_id
     * @return bool
     */
    private function allocatable_id_is_valid($student_id) {
        $allocatable = $this->get_allocatable_from_id($student_id);
        return $allocatable && $allocatable->is_valid_for_course($this->coursework->get_course());
    }

    /**
     * @param int $allocatable_id
     * @return allocatable
     */
    private function get_allocatable_from_id($allocatable_id) {
        if ($this->coursework->is_configured_to_have_group_submissions()) {
            return group::find($allocatable_id);
        } else {
            return user::find($allocatable_id);
        }
    }
}

