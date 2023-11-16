<?php

namespace mod_coursework\auto_grader;

/**
 * Interface auto_grader makes sure all of the auto grader classes can be used interchangeably.
 */
interface auto_grader {

    public function __construct($coursework, $allocatable);

    public function create_auto_grade_if_rules_match();
}