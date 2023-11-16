<?php

namespace mod_coursework;
use mod_coursework\models\coursework;

/**
 * Class warnings is responsible for detecting and displaying warnings to users based on
 * system conditions and configuration.
 *
 * @package mod_coursework
 */
class warnings {

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
     * If the coursework is set to need three teachers, but there are only two enrolled, then we
     * need to notify the managers.
     *
     * @return string
     */
    public function not_enough_assessors() {

        $html = '';
        $first_stage = $this->coursework->get_stage('assessor_1');
        $actual_number = count($first_stage->get_teachers());
        $number_of_initial_assessors = $actual_number;

        if ($number_of_initial_assessors < $this->coursework->numberofmarkers) {
            // Problem!

            $strings = new \stdClass();
            $strings->actual_number = $actual_number;
            $strings->required_number = $this->coursework->numberofmarkers;

            $html .= get_string('not_enough_teachers', 'mod_coursework', $strings);
            $html = $this->alert_div($html);
        }

        return $html;

    }

    /**
     * @return bool|string
     * @throws \coding_exception
     */
    public function students_in_mutiple_grouos() {

        global $DB;
        $message = '';

        if ($this->coursework->grouping_id) {
            $sql = "SELECT * FROM (
                          SELECT gm.userid,
                                 count(gm.userid) as noofgroups,
                                 groupings.groupingid,
                                 u.firstname,
                                 u.lastname
                           FROM {groups} groups
                     INNER JOIN {groups_members} gm
                             ON groups.id = gm.groupid
                     INNER JOIN {groupings_groups} groupings
                             ON groups.id=groupings.groupid
                     INNER JOIN {user} u
                             ON u.id = gm.userid
                          WHERE groups.courseid = :courseid
                            AND groupings.groupingid = :groupingid
                       GROUP BY gm.userid, groupings.groupingid, u.firstname, u.lastname)a
                          WHERE noofgroups > 1";

            $params = array('courseid' => $this->coursework->get_course()->id,
                            'groupingid' => $this->coursework->grouping_id);
        } else {
            $sql = "SELECT * FROM (
                            SELECT gm.userid,
                                   count(gm.userid) as noofgroups,
                                   u.firstname,
                                   u.lastname
                              FROM {groups} groups
                        INNER JOIN {groups_members} gm
                                ON gm.groupid = groups.id
                        INNER JOIN {user} u
                                ON u.id = gm.userid
                             WHERE groups.courseid = :courseid
                          GROUP BY gm.userid, u.firstname, u.lastname) a
                    WHERE noofgroups > 1";

            $params = array('courseid' => $this->coursework->get_course()->id);
        }

        // get all students that are in more than a one group
        $studentsinmultigroups = $DB->get_records_sql($sql, $params);

        if ($studentsinmultigroups) {
            $studentmessage = '';
            foreach ($studentsinmultigroups as $student) {

                if (!has_capability('mod/coursework:addinitialgrade', $this->coursework->get_context(), $student->userid)) {
                    $studentmessage .= '<li>' . $student->firstname . ' ' . $student->lastname;

                    //get group ids of these students
                    if ($this->coursework->grouping_id) {

                        $sql = "SELECT groups.id,groups.name
                               FROM {groups} groups
                         INNER JOIN {groupings_groups} groupings
                                 ON groups.id = groupings.groupid
                         INNER JOIN {groups_members} gm
                                 ON gm.groupid = groups.id
                              WHERE groups.courseid = :courseid
                                AND gm.userid = :userid
                                AND groupings.groupingid =:grouping_id";

                        $params = array(
                            'grouping_id' => $this->coursework->grouping_id,
                            'courseid' => $this->coursework->get_course()->id,
                            'userid' => $student->userid);
                    } else {

                        $sql = "SELECT groups.id,groups.name
                                FROM {groups} groups
                          INNER JOIN {groups_members} gm
                                  ON gm.groupid = groups.id
                               WHERE groups.courseid = :courseid
		                         AND gm.userid = :userid";

                        $params = array(
                            'courseid' => $this->coursework->get_course()->id,
                            'userid' => $student->userid);
                    }
                    $studentmessage .= '<ul>';
                    $groups = $DB->get_records_sql($sql, $params);

                    foreach ($groups as $group) {
                        $studentmessage .= '<li>';
                        $studentmessage .= $group->name;
                        $studentmessage .= '</li>';
                    }
                    $studentmessage .= '</ul></li>';
                }
            }

            if(!empty($studentmessage)) {
                $message  = '<div class = "multiple_groups_warning">';
                $message .= '<p>' . get_string('studentsinmultiplegroups', 'mod_coursework') . '</p>';
                $message .= '<ul>';
                $message .= $studentmessage;
                $message .= '</ul></div>';
            }
        }

        if (!empty($message)) {
            return $this->alert_div($message);
        }

        return false;
    }

    /**
     * Warns us if percentage allocations are enabled and so not add up to 100%
     *
     * @return string
     */
    public function percentage_allocations_not_complete() {
        global $DB;

        if ($this->coursework->percentage_allocations_enabled()) {
            $sql = "SELECT COALESCE(SUM(value), 0)
                      FROM {coursework_allocation_config}
                      WHERE courseworkid = ?
                      AND allocationstrategy = 'percentages'
                      ";
            $total_percentages = $DB->count_records_sql($sql, array($this->coursework->id));

            if ($total_percentages < 100) {
                return $this->alert_div(get_string('percentages_do_not_add_up', 'mod_coursework', $total_percentages));
            }
        }

        return '';
    }

    /** Warning if allocation is selected but no assessor is chosen
     * @return string
     * @throws \coding_exception
     */
    public function manual_allocation_not_completed(){
        global $DB;

        $coursework = $this->coursework;

        $coursework_stages = $coursework->numberofmarkers;
        for ($i = 1; $i <= $coursework_stages; $i++){
             $assessor = 'assessor_'.$i;

             if ($coursework->samplingenabled == 0 || $assessor == 'assessor_1') {
                 $allocatables = $coursework->get_allocatables();

                 foreach ($allocatables as $allocatable) {

                     $params = array('courseworkid' => $coursework->id,
                                     'stageidentifier' => $assessor,
                                     'allocatableid' => $allocatable->id);

                     $existing_allocations = $this->check_existing_allocations($params);

                     if ($existing_allocations == false) {
                         return $this->alert_div(get_string('assessors_no_allocated_warning', 'mod_coursework'));
                     }
                 }
             }else{

                 $params = array('courseworkid' => $coursework->id);
                 $sql = "SELECT id, stage_identifier, allocatableid
                         FROM {coursework_sample_set_mbrs}
                         WHERE courseworkid = :courseworkid";

                 $stage_identifiers = $DB->get_records_sql($sql, $params);
                 foreach ($stage_identifiers as $stage_identifier) {
                     $params = array('courseworkid' => $coursework->id,
                                     'stageidentifier' => $stage_identifier->stage_identifier,
                                     'allocatableid' => $stage_identifier->allocatableid);

                     $existing_allocations = $this->check_existing_allocations($params);

                     if ($existing_allocations == false) {
                         return $this->alert_div(get_string('assessors_no_allocated_warning', 'mod_coursework'));
                     }
                 }
             }
        }
        return '';
    }

    /** Function to check if allocation exists
     * @param $params
     * @return array
     */
    public function check_existing_allocations($params){
        global $DB;
        $sql = "SELECT 1
                FROM {coursework_allocation_pairs}
                WHERE courseworkid = :courseworkid
                AND stage_identifier = :stageidentifier
                AND allocatableid = :allocatableid";

       return $existing_allocations = $DB->get_records_sql($sql, $params);

    }

    /**
     * Alerts teachers if there is a students who is not in any group and who will therefore
     * not be able to submit anything.
     *
     * @return string
     */
    public function student_in_no_group() {
        global $DB;

        if (!$this->coursework->is_configured_to_have_group_submissions()) {
            return '';
        }

        $student_ids = array_keys(get_enrolled_users($this->coursework->get_context(), 'mod/coursework:submit'));

        if (empty($student_ids)) {
            return '';
        }

        list($student_sql, $student_params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED);

        if ($this->coursework->grouping_id != 0) {
            $students =
                $this->students_who_are_not_in_any_grouping_group($student_sql, $student_params);
            if ($students) {
                $names = $this->make_list_of_student_names($students);
                return $this->alert_div(get_string('students_in_no_group_warning', 'mod_coursework').$names);
            }
        } else {

            $students = $this->students_who_are_not_in_any_group($student_sql, $student_params);

            if ($students) {
                $names = $this->make_list_of_student_names($students);
                return $this->alert_div(get_string('students_in_no_group_warning', 'mod_coursework'). $names);
            }
        }

        return '';
    }

    /**
     * Common wrapper for alerts.
     *
     * @param string $message
     * @return string
     */
    private function alert_div($message) {
        $html = '';
        $html .= '<div class="alert">';
        $html .= $message;
        $html .= '</div>';
        return $html;
    }

    /**
     * @param $students
     * @return string
     */
    protected function make_list_of_student_names($students) {
        $names = '<ul>';
        foreach ($students as $student) {
            $names .= '<li>' . fullname($student) . '</li>';
        }
        $names .= '</ul>';
        return $names;
    }

    /**
     * @param $student_sql
     * @param $student_params
     * @return mixed
     */
    private function students_who_are_not_in_any_group($student_sql, $student_params) {
        global $DB;

        $sql = "SELECT u.*
                     FROM {user} u
                     WHERE NOT EXISTS (
                        SELECT 1
                          FROM {groups_members} m
                    INNER JOIN {groups} g
                            ON g.id = m.groupid
                         WHERE m.userid = u.id
                           AND g.courseid = :courseid
                           )
                      AND u.id $student_sql

                ";

        $params = array_merge($student_params,
                              array(
                                  'courseid' => $this->coursework->get_course()->id
                              ));
        $students = $DB->get_records_sql($sql, $params);
        return $students;
    }

    /**
     * @param $student_sql
     * @param $student_params
     * @return mixed
     */
    private function students_who_are_not_in_any_grouping_group($student_sql, $student_params) {
        global $DB;

        $sql = "SELECT u.*
                    FROM {user} u
                    WHERE NOT EXISTS (
                    SELECT 1
                      FROM {groups_members} m
                INNER JOIN {groups} g
                        ON g.id = m.groupid
                INNER JOIN {groupings_groups} gr
                        ON gr.groupid = g.id
                     WHERE m.userid = u.id
                       AND g.courseid = :courseid
                       AND gr.groupingid = :groupingid
                       )
                   AND u.id $student_sql
                ";

        $params = array_merge($student_params,
                              array(
                                  'courseid' => $this->coursework->get_course()->id,
                                  'groupingid' => $this->coursework->grouping_id,
                              ));
        $students = $DB->get_records_sql($sql, $params);
        return $students;
    }

    /**
     * Alert markers that filter A to Z filter is on
     * @return string
     * @throws \coding_exception
     */
    public function a_to_z_filter_on() {
        return $this->alert_div(get_string('namefilternon', 'mod_coursework'));
    }

    /**
     * Alert markers that there may be more submissions to grade
     * @return string
     * @throws \coding_exception
     */
    public function filters_warning() {
        return $this->alert_div(get_string('filteronwarning', 'mod_coursework'));
    }
}