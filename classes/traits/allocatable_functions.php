<?php

namespace mod_coursework\traits;
use mod_coursework\models\assessment_set_membership;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;

/**
 * Class allocatable
 * @package mod_coursework\traits
 */
trait allocatable_functions {

    /**
     * For when a student leaves. All assessment and moderation stuff can be taken away.
     *
     * @param $coursework
     */
    public function delete_all_submission_allocations($coursework) {

        global $DB;

        $sql = "DELETE FROM {coursework_allocation_pairs}
                 WHERE courseworkid = :courseworkid
                   AND NOT EXISTS (SELECT 1
                                     FROM {coursework_feedbacks} f
                               INNER JOIN {coursework_submissions} s
                                       ON f.submissionid = s.id
                                    WHERE s.userid = studentid
                                      AND f.assessorid = assessorid
                                      AND s.courseworkid = :courseworkid2
                                   )
                   AND allocatableid = :id
                   AND allocatabletype = :type
                 ";

        $params = array(
            'courseworkid' => $coursework->id,
            'courseworkid2' => $coursework->id,
            'id' => $this->id(),
            'type' => $this->type(),
        );
        $DB->execute($sql, $params);
    }

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function has_agreed_feedback($coursework) {
        global $DB;
        $sql = "
            SELECT COUNT(*)
              FROM {coursework_feedbacks} f
        INNER JOIN {coursework_submissions} s
                ON f.submissionid = s.id
             WHERE f.stage_identifier LIKE 'final%'
               AND s.allocatableid = :id
               AND s.courseworkid = :courseworkid
        ";
        $result = $DB->count_records_sql($sql, array('id' => $this->id(), 'courseworkid' => $coursework->id()));
        return !empty($result);
    }


    /**
     * @param coursework $coursework
     * @return bool
     */
    public function get_agreed_feedback($coursework) {
        global $DB;
        $sql = "
            SELECT f.*
              FROM {coursework_feedbacks} f
        INNER JOIN {coursework_submissions} s
                ON f.submissionid = s.id
             WHERE f.stage_identifier = 'final_agreed_1'
               AND s.allocatableid = :id
               AND s.courseworkid = :courseworkid";

        return $DB->get_record_sql($sql, array('id' => $this->id(), 'courseworkid' => $coursework->id()));
    }


    /**
     * @param coursework $coursework
     * @return bool
     */
    public function has_all_initial_feedbacks($coursework) {
        global $DB;

        $expected_markers = $coursework->numberofmarkers;

        $sql = "
            SELECT COUNT(*)
              FROM {coursework_feedbacks} f
        INNER JOIN {coursework_submissions} s
                ON f.submissionid = s.id
             WHERE f.stage_identifier LIKE 'assess%'
               AND s.allocatableid = :id
               AND s.courseworkid = :courseworkid
        ";
        $feedbacks = $DB->count_records_sql($sql,
            array('id' => $this->id(),
                'courseworkid' => $coursework->id()));

        // when sampling is enabled, calculate how many stages are in sample
        if ($coursework->sampling_enabled()) {

            $sql = "SELECT COUNT(*)
                  FROM {coursework_sample_set_mbrs}
                  WHERE courseworkid = :courseworkid
                  AND allocatableid = :allocatableid
                  AND allocatabletype = :allocatabletype";

            $markers = $DB->count_records_sql($sql,
                array('courseworkid' => $coursework->id(),
                    'allocatableid' => $this->id(),
                    'allocatabletype' => $this->type()));

            $expected_markers = $markers + 1; // there is always a marker for stage 1
        }

        return $feedbacks == $expected_markers;
    }

    /**
     * @param $coursework
     * @return array
     */
    public function get_initial_feedbacks($coursework) {
        $this->fill_submission_and_feedback($coursework);
        $result = [];
        $submission = $this->get_submission($coursework);
        if ($submission) {
            $result = isset(feedback::$pool[$coursework->id]['submissionid-stage_identifier_index'][$submission->id . '-others']) ?
                feedback::$pool[$coursework->id]['submissionid-stage_identifier_index'][$submission->id . '-others'] : [];
        }
        return $result;
    }

    /**
     * @param $coursework
     * @return submission
     */
    public function get_submission($coursework) {
        $this->fill_submission_and_feedback($coursework);
        $result = submission::get_object($coursework->id, 'allocatableid', [$this->id]);
        return $result;
    }

    /**
     *
     * @param $coursework
     */
    private function fill_submission_and_feedback($coursework) {
        $coursework_id = $coursework->id;
        submission::fill_pool_coursework($coursework_id);
        feedback::fill_pool_coursework($coursework_id);
    }
}