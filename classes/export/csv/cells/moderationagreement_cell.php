<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\models\moderation;

/**
 * Class moderationagreement_cell
 */
class moderationagreement_cell extends cell_base {

    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return array
     */
    public function get_cell($submission, $student, $stage_identifier){
        global $DB;

        $data = array();
        $moderation_agreement = '';
        $moderation = '';

        if($this->coursework->allocation_enabled()) {
            $allocation = $submission->get_assessor_allocation_by_stage('moderator');
            if ($allocation) {
                $data[] = $this->get_assessor_name($allocation->assessorid);
                $data[] = $this->get_assessor_username($allocation->assessorid);
            } else {
                $data[] = get_string('moderatornotallocated', 'mod_coursework');
                $data[] = get_string('moderatornotallocated', 'mod_coursework');
            }
        }
        $feedback = $submission->get_assessor_feedback_by_stage('assessor_1');
        if ($feedback) $moderation = moderation::find(array('feedbackid'=>$feedback->id));

        if ($moderation) $moderation_agreement = $moderation->get_moderator_agreement($feedback);

        if ($moderation_agreement) {
                $data[] = $moderation_agreement->agreement;
                $data[] = $this->get_assessor_name($moderation_agreement->moderatorid);
                $data[] = $this->get_assessor_username($moderation_agreement->moderatorid);
                $data[] = userdate($moderation_agreement->timemodified, $this->dateformat);
            } else {
                $data[] = '';
                $data[] = '';
                $data[] = '';
                $data[] = '';
            }
        return $data;
    }

    /**
     * @param $stage
     * @return array
     * @throws \coding_exception
     */
    public function get_header($stage){

        $fields = array();
        if($this->coursework->allocation_enabled()){
            $fields['allocatedmoderatorname'] = 'Allocated moderator name';
            $fields['allocatedmoderatorusername'] = 'Allocated moderator username';
        }

        $fields['moderatoragreement'] = 'Moderator agreement';
        $fields['moderatorname'] = 'Moderator name';
        $fields['moderatorusername'] = 'Moderator username';
        $fields['moderatedon'] = 'Moderated on';

        return $fields;
    }

}