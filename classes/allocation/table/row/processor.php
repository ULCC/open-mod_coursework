<?php

namespace mod_coursework\allocation\table\row;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;

/**
 * Class row represents one row in the allocation table. It really just acts as a factory for the calls and the
 * allocatable.
 *
 * @package mod_coursework\allocation\table
 */
class processor {

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * User or group
     *
     * @var allocatable
     */
    private $allocatable;

    /**
     * @param $coursework
     * @param allocatable $allocatable
     */
    public function __construct($coursework, $allocatable) {
        $this->coursework = $coursework;
        $this->allocatable = $allocatable;
    }

    /**
     * Processes all the data in order to save it.
     * @param array $data
     */
    public function process($data) {
        $stages = $this->coursework->marking_stages();

        foreach ($stages as $stage) {
            if ($data) {
                $stage->process_allocation_form_row_data($this->allocatable, $data);
            }
        }
    }


}