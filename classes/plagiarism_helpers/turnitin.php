<?php

namespace mod_coursework\plagiarism_helpers;

/**
 * Class turnitin
 * @package mod_coursework\plagiarism_helpers
 */
class turnitin extends base {

    /**
     * @return string
     */
    public function file_submission_instructions() {
        return 'Turnitin allows only one file to be submitted, and restricts the file types to those it can process.';
    }

    /**
     * @return bool
     * @throws \Exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function enabled() {
        global $DB, $CFG;

        if ($CFG->enableplagiarism) {
            $plagiarismsettings = (array)get_config('plagiarism_turnitin');
            if (!empty($plagiarismsettings['enabled'])) {
                $params = array(
                    'cm' => $this->get_coursework()->get_course_module()->id,
                    'name' => 'use_turnitin',
                    'value' => 1
                );
                if ($DB->record_exists('plagiarism_turnitin_config', $params)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return string
     * @throws \coding_exception
     */
    public function human_readable_name() {
        return get_string('turnitin', 'plagiarism_turnitin');
    }
}