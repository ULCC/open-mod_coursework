<?php

namespace mod_coursework\stages;
use mod_coursework\models\user;

/**
 * Class marking_stage represents a stage of the marking process. For a basic single marked coursework,
 * there will only be one. Double marking will have 3 (Two initial assessors and a final grade), and if
 * moderations is enabled, there will be one more.
 *
 * @package mod_coursework
 */
class moderator extends base {

    /**
     * @return string
     */
    public function allocation_table_header() {
        return get_string('moderations', 'mod_coursework');
    }

    /**
     * @return string
     */
    protected function assessor_capability() {
        return 'mod/coursework:moderate';
    }
    /**
     * @return string
     */
    protected function strategy_name() {
        return 'none';
    }

}