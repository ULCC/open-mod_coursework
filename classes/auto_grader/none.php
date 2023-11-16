<?php

namespace mod_coursework\auto_grader;

/**
 * Class none is responsible for implementing the none object pattern for the auto grader mechanism.
 *
 * @package mod_coursework\auto_grader
 */
class none implements auto_grader {

    /**
     * @param $coursework
     * @param $allocatable
     * @param $percentage
     */
    public function __construct($coursework, $allocatable) {
    }

    public function create_auto_grade_if_rules_match() {
    }
}