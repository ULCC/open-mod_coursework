<?php

namespace mod_coursework;

use mod_coursework\exceptions\access_denied;
use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\personal_deadline;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\models\moderation;

/**
 * This class provides a central point where all of the can/cannot decisions are stored.
 * It differs from the built-in Moodle permissions system (which it uses), as it encapsulates
 * logic around the business rules of the plugin. For example, if students should not be able to
 * submit because groups are enabled and they are not in one of the selected groups, then this is
 * the place where that logic should go.
 *
 * @package mod_coursework
 */
class ability extends \mod_coursework\framework\ability {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * This is so we can specify the type of the user variable. The parent
     * class may be subclassed and not use the coursework user object.
     *
     * @var user
     */
    protected $user;

    /**
     * We use a different instance of the class for each user. This makes it a bit cleaner.
     *
     * @param user $user
     * @param coursework $coursework
     */
    public function __construct(user $user, $coursework) {
        parent::__construct($user);
        $this->coursework = $coursework;

        // These rules determine what will happen when can() is called. They are intended to be RESTful.

        // Coursework rules

        // Show

        $this->allow('show',
                     'mod_coursework\models\coursework',
            function (coursework $coursework) {
                return has_capability('mod/coursework:view', $coursework->get_context(), $this->get_user());
            });


        // Submission rules

        // New submission
        $this->prevent_new_submissions_if_one_already_exists();
        $this->allow_new_submissions_if_can_submit_on_behalf_of();
        $this->prevent_new_submissions_if_not_for_this_user();
        $this->prevent_new_submissions_if_no_capability();
        $this->allow_new_submissions_when_deadline_has_not_passed();
        $this->allow_new_submissions_if_late_submissions_allowed();
        $this->allow_new_submissions_if_there_is_an_active_extension();


        // Create submission
        $this->allow_create_submission_if_can_new_submission();

        // Show submission
        $this->allow_show_submission_if_submission_belongs_to_user();
        $this->allow_show_submission_if_user_has_previously_added_feedback_for_it();
        $this->allow_show_submission_if_user_can_submit_on_behalf_of();
        $this->prevent_show_submissions_that_are_not_ready_to_grade();
        $this->allow_show_submission_if_user_has_an_allocation_for_any_stage();
        $this->allow_show_submission_to_graders_when_allocations_are_disabled();
        $this->allow_show_submission_to_graders_after_feedback_release();
        $this->allow_show_submission_if_user_is_agreed_grade_assessor_and_submission_is_ready();
        $this->allow_show_submission_if_user_can_administer_grades();
        $this->allow_show_submission_if_user_can_view_all_grades_at_all_times();


        // Edit submission
        $this->prevent_edit_submission_for_unsaved_records();
        $this->allow_edit_submissions_if_can_submit_on_behalf_of();
        $this->prevent_edit_submission_past_deadline_and_no_extension();
        $this->prevent_edit_submission_when_finalised();
        $this->allow_edit_submission_when_permitted_and_own_submission();

        // Update submission
        $this->allow_update_submission_if_can_edit();

        // Revert submission
        $this->prevent_revert_submission_when_not_yet_finalised();
        $this->prevent_revert_submission_when_has_feedback();
        $this->prevent_revert_submission_when_deadline_passed_and_no_extension();
        $this->allow_revert_submission_when_has_permission();

        // Finalise submission
        $this->allow_finalisation_when_has_permission_and_settings_allow();


        // Resubmit to plagiarism submission
        $this->allow_resubmit_to_plagiarism_submission_if_user_is_assessor_for_any_stage();

        // View plagiarism submission
        $this->allow_view_plagiarism_submission_if_has_permission_or_is_assessor();
        $this->prevent_view_plagiarism_when_submission_does_not_belong_to_student();
        $this->allow_view_plagiarism_submission_when_in_submitted_state();
        $this->allow_view_plagiarism_submission_when_in_finalised_state();
        $this->allow_view_plagiarism_submission_when_in_published_state();

        // Moderations rules
        // new moderation
        $this->prevent_new_moderation_if_user_is_not_allocated_to_moderate();
        $this->allow_new_moderation_if_user_can_moderate();
        $this->allow_new_moderation_if_user_is_allocated_to_moderate();

        // edit moderation
        $this->allow_edit_moderation_if_user_created_moderation_and_can_edit();
        $this->allow_edit_moderation_if_user_is_allocated_to_moderate();
        $this->allow_edit_moderation_if_user_can_administer_grades();

        //show moderation
        $this->allow_show_moderation_if_user_can_view_grades_at_all_times();



        // Feedback rules

        // New feedback
        $this->prevent_new_feedback_with_no_submission();
       $this->prevent_new_feedback_when_submission_not_finalised();
        $this->prevent_new_feedback_when_prerequisite_stages_have_no_feedback();
        $this->prevent_new_feedback_with_empty_stage();
        $this->prevent_new_feedback_when_allocatable_already_has_feedback_for_this_stage();
        $this->prevent_new_feedback_when_allocatable_is_not_in_sample();
        $this->allow_new_feedback_if_user_can_administer_grades();
        $this->prevent_new_feedback_when_assessor_has_already_assessed_an_initial_stage();
        $this->prevent_new_feedback_on_behalf_of_others();
        $this->prevent_new_feedback_when_not_assessor_for_stage();
        $this->prevent_new_feedback_from_non_allocated_assessors();
        $this->allow_new_feedback_by_allocated_assessor();
       $this->allow_new_feedback_from_any_assessor_when_allocation_is_disabled_for_stage_or_instance();
        $this->allow_new_feedback_if_agreed_feedback_and_user_can_add_agreed_feedback();

        // Create feedback
        $this->allow_create_feedback_if_can_new_feedback();

        // Edit feedback
        $this->prevent_edit_feedback_if_submission_not_finalised();
        $this->allow_edit_feedback_if_user_can_administer_grades();
        $this->prevent_edit_feedback_if_user_is_not_stage_assessor();
        $this->allow_edit_feedback_if_user_created_feedback_and_is_initial_feedback_and_has_permission();
        $this->allow_edit_feedback_if_agreed_feedback_and_user_is_stage_assessor_and_has_permission();
        $this->allow_edit_feedback_if_agreed_feedback_and_user_marked_initial_stage_and_has_permission();
        $this->allow_edit_own_feedback_if_in_draft();

        // Update feedback
        $this->allow_update_feedback_if_can_edit_feeback();

        // Show feedback
        $this->allow_show_feedback_for_the_user_who_created_it();
        $this->allow_show_feedback_for_the_assessor_who_is_allocated_to_the_user();
        $this->allow_show_feedback_to_other_assessors_when_view_initial_grade_is_enabled();
        $this->allow_show_feedback_to_agreed_graders_once_all_initial_grades_are_done();
        $this->allow_show_feedback_to_initial_assessors_once_agreed_grade_is_done();
        $this->allow_show_feedback_promoted_to_gradebook_when_grades_have_been_released();
        $this->allow_show_feedback_when_grades_released_and_students_can_view_all_feedbacks();
        $this->allow_show_feedback_if_user_can_view_grades_at_all_times_or_administer();

        // Allocation rules

        // Show allocation
        $this->allow_show_allocation_to_the_allocated_assessor();
        $this->allow_show_allocation_to_allocators();

        // Grading table row rules

        // Show grading table row
        $this->allow_show_grading_table_row_if_allocation_enabled_and_user_has_any_allocation();

        $this->allow_show_grading_table_row_if_allocation_enabled_and_all_initial_feedback_done_and_user_can_do_agreed_grades();
        $this->allow_show_grading_table_row_if_allocation_not_enabled_and_user_is_assessor_of_any_stage();
        $this->allow_show_grading_table_row_if_user_has_added_feedback_for_this_submission();
        $this->allow_show_grading_table_row_if_user_can_administer_grades();
        $this->allow_show_grading_table_row_if_user_can_grant_extension_and_no_allocation();

        // Deadline extension rules

        // Show
        $this->allow_show_deadline_extension_with_capability();

        // New
        $this->prevent_new_deadline_extension_if_already_exists();
        $this->allow_new_deadline_extension_with_capability();

        // Create
        $this->allow_create_deadline_extension_if_can_new();

        // Edit
        $this->prevent_edit_deadline_extension_if_not_persisted();
        $this->allow_edit_deadline_extension_with_capability();

        // Update
        $this->allow_update_deadline_extension_if_can_edit();


        // Personal deadlines rules
        $this->prevent_edit_personal_deadline_if_extension_given();
        $this->allow_edit_personal_deadline_with_capability();
    }

    /**
     * @param string $action
     * @param mixed $thing
     * @throws access_denied
     */
    public function require_can($action, $thing) {
        if (!$this->can($action, $thing)) {
            throw new access_denied($this->get_coursework(), $this->get_last_message());
        }
    }

    /**
     * For non-standard things like decorated classes:
     *
     * @return array
     */
    protected function classname_mappings() {
        return array(
            'submission_groups_decorator' => 'submission',
            'coursework_groups_decorator' => 'coursework',
        );
    }

    /**
     * @return coursework
     */
    private function get_coursework() {
        return $this->coursework;
    }

    /**
     * Only here so that the type hinting is more accurate.
     *
     * @return user
     */
    protected function get_user() {
        return $this->user;
    }

    protected function prevent_new_submissions_if_not_for_this_user() {
        $this->prevent('new',
                       'mod_coursework\models\submission',
            function (submission $submission) {
                return !$submission->belongs_to_user($this->get_user());
            });
    }

    protected function allow_new_submissions_if_can_submit_on_behalf_of() {
        $this->allow('new',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return has_capability('mod/coursework:submitonbehalfof', $submission->get_coursework()->get_context());
            });
    }

    protected function allow_edit_submissions_if_can_submit_on_behalf_of() {
        $this->allow('edit',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return has_capability('mod/coursework:submitonbehalfof', $submission->get_coursework()->get_context());
            });
    }

    protected function prevent_new_submissions_if_one_already_exists() {
        $this->prevent('new',
                       'mod_coursework\models\submission',
            function (submission $submission) {
                $exists_params = array(
                    'courseworkid' => $submission->courseworkid,
                    'allocatableid' => $submission->allocatableid,
                    'allocatabletype' => $submission->allocatabletype,
                );
                if (submission::exists($exists_params)) {
                    $this->set_message('Submission already exists');
                    return true;
                }
                return false;
            });
    }

    protected function allow_new_submissions_when_deadline_has_not_passed() {
        $this->allow('new',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                if (!$submission->get_coursework()->deadline_has_passed() || !$submission->get_coursework()->has_deadline()) {
                    return true;
                }
                return false;
            });
    }

    protected function allow_new_submissions_if_late_submissions_allowed() {
        $this->allow('new',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                if ($submission->get_coursework()->allow_late_submissions()) {
                    return true;
                }
                return false;
            });
    }

    protected function allow_new_submissions_if_there_is_an_active_extension() {
        $this->allow('new',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                $coursework = $submission->get_coursework();
                $submitting_allocatable = $coursework->submiting_allocatable_for_student($this->get_user());
                return deadline_extension::allocatable_extension_allows_submission($submitting_allocatable,
                                                                                   $coursework);
            });
    }

    protected function prevent_new_submissions_if_no_capability() {
        $this->prevent('new',
                       'mod_coursework\models\submission',
            function (submission $submission) {
                return !has_capability('mod/coursework:submit', $submission->get_context(), $this->get_user());
            });
    }

    protected function allow_create_submission_if_can_new_submission() {
        $this->allow('create',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return $this->can('new', $submission);
            });
    }

    protected function allow_show_submission_if_submission_belongs_to_user() {
        $this->allow('show',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return $submission->belongs_to_user($this->get_user());
            });
    }

    protected function allow_show_submission_if_user_has_previously_added_feedback_for_it() {
        $this->allow('show',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return feedback::exists(array('submissionid' => $submission->id,
                                              'assessorid' => $this->get_user()->id()));
            });
    }

    protected function prevent_show_submissions_that_are_not_ready_to_grade() {
        $this->prevent('show',
                       'mod_coursework\models\submission',
            function (submission $submission) {
                return !$submission->ready_to_grade();
            });
    }

    protected function allow_show_submission_if_user_has_an_allocation_for_any_stage() {
        $this->allow('show',
                     'mod_coursework\models\submission',
            function (submission $submission) {

                // Should be visible to those who are OK to mark it.
                $allocation_enabled = $submission->get_coursework()->allocation_enabled();
                $user_has_any_allocation = $submission->get_coursework()
                    ->assessor_has_any_allocation_for_student($submission->reload()->get_allocatable());
                return $allocation_enabled && $user_has_any_allocation;
            });
    }

    protected function allow_show_submission_to_graders_when_allocations_are_disabled() {
        $this->allow('show',
                     'mod_coursework\models\submission',
            function (submission $submission) {

                // Should be visible to those who are OK to mark it.
                $allocation_disabled = !$submission->get_coursework()->allocation_enabled();
                $allowed_to_grade = has_capability('mod/coursework:addinitialgrade',
                                                   $submission->get_coursework()->get_context());
                $allowed_to_agree_grades = has_capability('mod/coursework:addagreedgrade',
                                                          $submission->get_coursework()->get_context());
                return $allocation_disabled && ($allowed_to_grade || $allowed_to_agree_grades);
            });
    }

    protected function allow_show_submission_to_graders_after_feedback_release() {
// Show to graders after release
        $this->allow('show',
                     'mod_coursework\models\submission',
            function (submission $submission) {

                $allowed_to_grade = has_capability('mod/coursework:addinitialgrade',
                                                   $submission->get_coursework()->get_context());
                $allowed_to_agree_grades = has_capability('mod/coursework:addagreedgrade',
                                                          $submission->get_coursework()->get_context());
                $allocation_disabled = !$submission->get_coursework()->allocation_enabled();

                return $allocation_disabled && $submission->is_published() && ($allowed_to_grade || $allowed_to_agree_grades);
            });
    }


    protected function allow_show_submission_if_user_is_agreed_grade_assessor_and_submission_is_ready() {
        $this->allow('show',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                $state =  $submission->get_state();
                $allowed_to_agree_grades = has_capability('mod/coursework:addagreedgrade', $submission->get_coursework()->get_context());
                $allowed_to_edit_agree_grades = has_capability('mod/coursework:editagreedgrade', $submission->get_coursework()->get_context());
                return (($allowed_to_agree_grades && $state == submission::FULLY_GRADED) || ($allowed_to_edit_agree_grades && $state >= submission::FULLY_GRADED));
            });
    }

    protected function allow_show_submission_if_user_can_administer_grades()    {
        $this->allow('show',
            'mod_coursework\models\submission',
            function (submission $submission) {
                return has_capability('mod/coursework:administergrades',
                    $submission->get_coursework()->get_context());
            });
    }

    protected function allow_show_submission_if_user_can_view_all_grades_at_all_times()    {
        $this->allow('show',
            'mod_coursework\models\submission',
            function (submission $submission) {
                return has_capability('mod/coursework:viewallgradesatalltimes',
                    $submission->get_coursework()->get_context());
            });
    }
    

    protected function prevent_edit_submission_for_unsaved_records() {
        $this->prevent('edit',
                       'mod_coursework\models\submission',
            function (submission $submission) {
                return !$submission->persisted();
            });
    }

    protected function prevent_edit_submission_past_deadline_and_no_extension() {
        $this->prevent('edit',
            'mod_coursework\models\submission',
            function (submission $submission) {
                // take into account courseworks with personal deadlines
                if ($submission->get_coursework()->personal_deadlines_enabled()){
                    $deadline_passed = ($submission->submission_personal_deadline() < time())? true : false;
                 } else {
                     $deadline_passed = $submission->get_coursework()->deadline_has_passed();
                }
                $ok_to_submit_late = $submission->get_coursework()->allow_late_submissions();
                $coursework = $submission->get_coursework();
                $submitting_allocatable = $coursework->submiting_allocatable_for_student($this->get_user());
                if ($deadline_passed && !deadline_extension::allocatable_extension_allows_submission($submitting_allocatable,$coursework)) {
                    if (!$ok_to_submit_late) {
                        $this->set_message('Cannot submit past the deadline');
                        return true;
                    } else {
                        if ($submission->persisted()) {
                            $this->set_message('Cannot update submissions past the deadline');
                            return true;
                        }
                    }
                }
                return false;
            });
    }

    protected function prevent_edit_submission_when_finalised() {
        $this->prevent('edit',
                       'mod_coursework\models\submission',
            function (submission $submission) {
                return $submission->ready_to_grade();
            });
    }

    protected function allow_edit_submission_when_permitted_and_own_submission() {
        $this->allow('edit',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                $can_submit = has_capability('mod/coursework:submit', $submission->get_context(), $this->get_user());
                return $can_submit && $submission->belongs_to_user($this->get_user());
            });
    }

    protected function allow_update_submission_if_can_edit() {
        $this->allow('update',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return $this->can('edit', $submission);
            });
    }

    protected function allow_revert_submission_when_has_permission() {
        $this->allow('revert',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return has_capability('mod/coursework:revertfinalised', $submission->get_context(), $this->get_user());
            });
    }

    protected function prevent_revert_submission_when_deadline_passed_and_no_extension() {
        $this->prevent('revert',
            'mod_coursework\models\submission',
            function (submission $submission) {
                return $submission->get_coursework()->deadline_has_passed() && !$submission->has_extension()
                && !$submission->get_coursework()->allow_late_submissions();
            });
    }

    protected function prevent_revert_submission_when_not_yet_finalised() {
        $this->prevent('revert',
                       'mod_coursework\models\submission',
            function (submission $submission) {
                return !$submission->ready_to_grade();
            });
    }

    protected function prevent_revert_submission_when_has_feedback() {
        $this->prevent('revert',
                       'mod_coursework\models\submission',
            function (submission $submission) {
                return $submission->get_state() >= submission::PARTIALLY_GRADED;
            });
    }

    protected function allow_finalisation_when_has_permission_and_settings_allow() {
        $this->allow('finalise',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                /**
                 * @var submission $submission
                 */
                $not_already_finalised = !$submission->ready_to_grade();
                $early_finalisation_allowed = $submission->get_coursework()->early_finalisation_allowed();
                $coursework_has_no_deadline    =  !$submission->get_coursework()->has_deadline();
                $allowed_to = $this->can('new', $submission) || $this->can('edit', $submission);


                return $allowed_to && $not_already_finalised && ($early_finalisation_allowed or $coursework_has_no_deadline) ;
            });
    }



    protected function allow_resubmit_to_plagiarism_submission_if_user_is_assessor_for_any_stage() {
        $this->allow('resubmit_to_plagiarism',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return $submission->get_coursework()->is_assessor($this->get_user());
            });
    }

    protected function allow_view_plagiarism_submission_if_has_permission_or_is_assessor() {
        $this->allow('view_plagiarism',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return $submission->get_coursework()->is_assessor($this->get_user())
                    || has_capability('mod/coursework:grade', $submission->get_context());
            });
    }

    protected function allow_view_plagiarism_submission_when_in_submitted_state() {
        $this->allow('view_plagiarism',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return $submission->get_state() === submission::SUBMITTED;
            });
    }

    protected function allow_view_plagiarism_submission_when_in_finalised_state() {
        $this->allow('view_plagiarism',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return $submission->get_state() === submission::FINALISED;
            });
    }

    protected function allow_view_plagiarism_submission_when_in_published_state() {
        $this->allow('view_plagiarism',
                     'mod_coursework\models\submission',
            function (submission $submission) {
                return $submission->get_state() === submission::PUBLISHED;
            });
    }

    protected function prevent_view_plagiarism_when_submission_does_not_belong_to_student() {
        $this->prevent('view_plagiarism',
                       'mod_coursework\models\submission',
            function (submission $submission) {
                return !$submission->belongs_to_user($this->get_user());
            });
    }

    protected function allow_new_moderation_if_user_can_moderate() {
        $this->allow('new',
            'mod_coursework\models\moderation',
            function (moderation $moderation) {
                return has_capability('mod/coursework:moderate',
                    $moderation->get_coursework()
                        ->get_context());
            });
    }

    protected function allow_new_moderation_if_user_is_allocated_to_moderate() {
        $this->allow('new',
            'mod_coursework\models\moderation',
            function (moderation $moderation) {
                $is_allocated = false;
                if($moderation->get_coursework()->allocation_enabled()) {
                    $is_allocated = $moderation->is_moderator_allocated();
                }
                return  $is_allocated;
            });
    }


    protected function prevent_new_moderation_if_user_is_not_allocated_to_moderate() {
        $this->prevent('new',
            'mod_coursework\models\moderation',
            function (moderation $moderation) {
                $is_allocated = false;
                if($moderation->get_coursework()->allocation_enabled() && !is_siteadmin()) {
                    $is_allocated = !$moderation->is_moderator_allocated();
                }
                return  $is_allocated;
            });
    }



    protected function allow_edit_moderation_if_user_created_moderation_and_can_edit() {
        $this->allow('edit',
            'mod_coursework\models\moderation',
            function (moderation $moderation) {
                   $has_capability = has_capability('mod/coursework:moderate',  $moderation->get_coursework()
                    ->get_context());
                $is_creator = $moderation->moderatorid == $this->get_user()->id;
                return $has_capability && ($is_creator || is_siteadmin()) ;
            });
    }

    protected function allow_edit_moderation_if_user_is_allocated_to_moderate() {
        $this->allow('edit',
            'mod_coursework\models\moderation',
            function (moderation $moderation) {
                $is_allocated = $moderation->is_moderator_allocated();
                return  $is_allocated;
            });
    }

    protected function allow_edit_moderation_if_user_can_administer_grades(){
        $this->allow('edit',
            'mod_coursework\models\moderation',
            function (moderation $moderation) {
                return has_capability('mod/coursework:administergrades',
                    $moderation->get_coursework()
                        ->get_context());
            });
    }

    protected function allow_show_moderation_if_user_can_view_grades_at_all_times() {
        $this->allow('show',
            'mod_coursework\models\moderation',
            function (moderation $moderation) {
                return  has_capability('mod/coursework:viewallgradesatalltimes',
                        $moderation->get_coursework()
                            ->get_context());
            });
    }




    protected function prevent_new_feedback_with_no_submission() {
        $this->prevent('new',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $this->set_message('Feedback does not have a submission');
                $submission = $feedback->get_submission();
                return empty($submission);
            });
    }

    protected function prevent_new_feedback_when_submission_not_finalised() {
        $this->prevent('new',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $this->set_message('Submission is not finalised');
                return !$feedback->get_submission()->ready_to_grade();
            });
    }

    protected function prevent_new_feedback_on_behalf_of_others() {
        $this->prevent('new',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                return $feedback->assessorid != $this->get_user()->id();
            });
    }

    protected function prevent_new_feedback_with_empty_stage() {
        $this->prevent('new',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $this->set_message('Feedback has an empty stage');
                $stage = $feedback->get_stage();
                return empty($stage);
            });
    }

    protected function prevent_new_feedback_when_not_assessor_for_stage() {
        $this->prevent('new',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $this->set_message('User is not an assessor for this stage');
                return !$feedback->get_stage()->user_is_assessor($this->user)
                && !(has_capability('mod/coursework:addallocatedagreedgrade',
                    $feedback->get_coursework()
                        ->get_context()) && $feedback->get_submission()->is_assessor_initial_grader());
            });
    }

    protected function prevent_new_feedback_when_prerequisite_stages_have_no_feedback() {
        $this->prevent('new',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $this->set_message('Prerequisite stage has no feedback');
                $stage = $feedback->get_stage();
                return !$stage->prerequisite_stages_have_feedback($feedback->get_allocatable()) && !is_siteadmin();
            });
    }

    protected function prevent_new_feedback_when_assessor_has_already_assessed_an_initial_stage() {
        $this->prevent('new',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $this->set_message('Assessor has already assessed an initial stage');
                $stage = $feedback->get_stage();
                return $stage->other_parallel_stage_has_feedback_from_this_assessor($this->get_user(),
                                                                                    $feedback->get_submission());
            });
    }

    protected function prevent_new_feedback_when_allocatable_already_has_feedback_for_this_stage() {
        $this->prevent('new',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $this->set_message('Allocatable already has feedback for this stage');
                $stage = $feedback->get_stage();
                $allocatable = $feedback->get_submission()->get_allocatable();
                return $stage->has_feedback($allocatable);
            });
    }

    protected function prevent_new_feedback_when_allocatable_is_not_in_sample() {
        $this->prevent('new',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $this->set_message('Allocatable is not in sample');
                $stage = $feedback->get_stage();
                $allocatable = $feedback->get_submission()->get_allocatable();
                return $stage->uses_sampling() && $stage->allocatable_is_not_in_sampling($allocatable);
            });
    }

    protected function allow_new_feedback_by_allocated_assessor() {
        $this->allow('new',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $stage = $feedback->get_stage();
                $allocatable = $feedback->get_submission()->get_allocatable();

                if ($stage->uses_allocation() && $feedback->get_coursework()->allocation_enabled()) {
                    $allocated_teacher = $stage->allocated_teacher_for($allocatable);
                    if ($allocated_teacher) {
                        if ($allocated_teacher->id == $this->user->id) {
                            return true;
                        }
                    }
                }
                return false;
            });
    }

    protected function prevent_new_feedback_from_non_allocated_assessors() {
        $this->prevent('new',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $this->set_message('Assessors are not allocated');
                $stage = $feedback->get_stage();
                $allocatable = $feedback->get_submission()->get_allocatable();

                if ($stage->uses_allocation() && $feedback->get_coursework()->allocation_enabled()) {
                    $allocated_teacher = $stage->allocated_teacher_for($allocatable);
                    if ($allocated_teacher) {
                        if ($allocated_teacher->id() != $this->get_user()->id()) {
                            return true;
                        }
                    }
                }
                return false;
            });
    }

    protected function allow_new_feedback_from_any_assessor_when_allocation_is_disabled_for_stage_or_instance() {
        $this->allow('new',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {

                $has_editable_feedbacks =   false;

                // find out if the previous grades are editable
                if ($feedback->is_agreed_grade())   {
                    $has_editable_feedbacks  =   $feedback->get_submission()->editable_feedbacks_exist();
                }

                if ((!$feedback->get_coursework()->allocation_enabled() || !$feedback->get_stage()->uses_allocation()) && !$has_editable_feedbacks ) {
                    return true;
                }
                return false;
            });
    }

    protected function allow_create_feedback_if_can_new_feedback() {
        $this->allow('create',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                return $this->can('new', $feedback);
            });
    }

    protected function prevent_edit_feedback_if_submission_not_finalised() {
        $this->prevent('edit',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                if ($feedback->get_submission() && !$feedback->get_submission()->ready_to_grade()) {
                    $this->set_message('feedback submission is not ready to grade');

                    return true;
                }
                return false;
            });
    }

    protected function prevent_edit_feedback_if_user_is_not_stage_assessor() {
        $this->prevent('edit',
                       'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $stage = $feedback->get_stage();
                if (!$stage->user_is_assessor($this->get_user()) &&
                    !(has_capability('mod/coursework:editallocatedagreedgrade',
                            $feedback->get_coursework()
                                ->get_context()) && $feedback->get_submission()->is_assessor_initial_grader()) && !$feedback->get_submission()->editable_final_feedback_exist()) {
                    $this->set_message('user is not assessor to edit the feedback');
                    return true;
                }
                return false;
            });
    }

    protected function allow_edit_feedback_if_user_created_feedback_and_is_initial_feedback_and_has_permission() {
        $this->allow('edit',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                global $CFG;

                $is_initial_grade = $feedback->is_initial_assessor_feedback();
                $has_capability = has_capability('mod/coursework:editinitialgrade', $feedback->get_context());
                $is_creator = $feedback->assessorid == $this->get_user()->id;
                $is_allocated = $feedback->is_assessor_allocated();

                $submission =   $feedback->get_submission();

                $in_editable_period     =   (!empty($feedback->get_coursework()->get_grade_editing_time()) && $feedback->timecreated + $feedback->get_coursework()->get_grade_editing_time() > time());

                return $is_initial_grade && ($has_capability || $in_editable_period) && ($is_creator || $is_allocated);
            });
    }

    protected function allow_edit_feedback_if_agreed_feedback_and_user_is_stage_assessor_and_has_permission() {
        $this->allow('edit',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $stage = $feedback->get_stage();
                $allowed = has_capability('mod/coursework:editagreedgrade', $feedback->get_context());
                return $allowed && $stage->identifier() == 'final_agreed_1' && $stage->user_is_assessor($this->get_user());
            });
    }

    protected function allow_edit_feedback_if_agreed_feedback_and_user_marked_initial_stage_and_has_permission() {
        $this->allow('edit',
            'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $stage = $feedback->get_stage();
                $allowed = has_capability('mod/coursework:editallocatedagreedgrade', $feedback->get_context());
                return  $allowed && $stage->identifier() == 'final_agreed_1' && $feedback->get_submission()->is_assessor_initial_grader();
            });
    }

    protected function allow_edit_own_feedback_if_in_draft() {
        $this->allow('edit',
            'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $is_creator = $feedback->assessorid == $this->get_user()->id;
                $stage = $feedback->get_stage();
                return  $is_creator && ($feedback->get_submission()->editable_feedbacks_exist() || $feedback->get_submission()->editable_final_feedback_exist()
                        && ((!$feedback->get_coursework()->has_multiple_markers() && $stage->is_initial_assesor_stage() ) || !$stage->is_initial_assesor_stage()));
            });
    }

    protected function allow_update_feedback_if_can_edit_feeback() {
        $this->allow('update',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                return $this->can('edit', $feedback);
            });
    }

    protected function allow_show_feedback_for_the_user_who_created_it() {
        $this->allow('show',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                return $feedback->assessorid == $this->get_user()->id;
            });
    }

    protected function allow_show_feedback_for_the_assessor_who_is_allocated_to_the_user() {
        $this->allow('show',
            'mod_coursework\models\feedback',
            function (feedback $feedback) {
                return $feedback->is_assessor_allocated();
            });
    }


    protected function allow_show_feedback_to_other_assessors_when_view_initial_grade_is_enabled() {
        $this->allow('show',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $stage = $feedback->get_stage();
                if ($stage->user_is_assessor($this->get_user()) &&
                    $feedback->get_coursework()->viewinitialgradeenabled()
                ) {
                    return true;
                }
                return false;
            });
    }

    protected function allow_show_feedback_to_agreed_graders_once_all_initial_grades_are_done() {
        $this->allow('show',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {

                if (has_capability('mod/coursework:addagreedgrade', $feedback->get_coursework()->get_context())
                    || has_capability('mod/coursework:addallocatedagreedgrade', $feedback->get_coursework()->get_context())) {

                    if ($feedback->get_submission()->get_state() >= submission::FULLY_GRADED) {
                        return true;
                    }
                }
                return false;
            });
    }

    protected function allow_show_feedback_to_initial_assessors_once_agreed_grade_is_done() {
        $this->allow('show',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $is_assessor =
                    has_capability('mod/coursework:addinitialgrade', $feedback->get_coursework()->get_context());
                $agreed_grade_done = $feedback->get_submission()->final_grade_agreed();
                return $is_assessor && $agreed_grade_done;
            });
    }

    protected function allow_show_feedback_promoted_to_gradebook_when_grades_have_been_released() {
        $this->allow('show',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $judge = new grade_judge($feedback->get_coursework());
                return $feedback->get_submission()->is_published() &&
                $judge->is_feedback_that_is_promoted_to_gradebook($feedback) &&
                $feedback->get_submission()->belongs_to_user($this->get_user());
            });
    }

    protected function allow_show_feedback_when_grades_released_and_students_can_view_all_feedbacks() {
        $this->allow('show',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                return $feedback->get_submission()->is_published() &&
                $feedback->get_coursework()->students_can_view_all_feedbacks() &&
                $feedback->get_submission()->belongs_to_user($this->get_user());
            });
    }

    protected function allow_show_allocation_to_the_allocated_assessor() {
        $this->allow('show',
                     'mod_coursework\models\allocation',
            function (allocation $allocation) {
                return $this->user->id == $allocation->assessorid;
            });
    }

    protected function allow_show_allocation_to_allocators() {
        $this->allow('show',
                     'mod_coursework\models\allocation',
            function (allocation $allocation) {
                return has_capability('mod/coursework:allocate', $allocation->get_coursework()->get_context());
            });
    }

    protected function allow_show_grading_table_row_if_allocation_enabled_and_user_has_any_allocation() {
        $this->allow('show',
                     'mod_coursework\grading_table_row_base',
            function (grading_table_row_base $grading_table_row) {
                $allocatable = $grading_table_row->get_allocatable();

                if ($grading_table_row->get_coursework()->allocation_enabled()) {
                    if ($grading_table_row->get_coursework()->assessor_has_any_allocation_for_student($allocatable)) {
                        return true;
                    }
                }
                return false;
            });
    }

    protected function allow_show_grading_table_row_if_allocation_enabled_and_all_initial_feedback_done_and_user_can_do_agreed_grades() {
        $this->allow('show',
                     'mod_coursework\grading_table_row_base',
            function (grading_table_row_base $grading_table_row) {
                $can_add_agreed_grade = has_capability('mod/coursework:addagreedgrade',
                                                       $grading_table_row->get_coursework()
                                                           ->get_context());

                if ($grading_table_row->get_coursework()
                        ->allocation_enabled() && $grading_table_row->has_submission()
                ) {

                    $submission_has_all_initial_assessor_feedbacks = $grading_table_row->get_submission()
                            ->get_state() >= submission::FULLY_GRADED;
                    if ($can_add_agreed_grade &&
                        $submission_has_all_initial_assessor_feedbacks
                    ) {
                        $submission_in_sample = $grading_table_row->get_submission()->sampled_feedback_exists();
                        return (!$grading_table_row->get_coursework()->sampling_enabled() || $submission_in_sample) ? true : false;
                    }
                }
                return false;
            });
    }

    protected function allow_show_grading_table_row_if_allocation_not_enabled_and_user_is_assessor_of_any_stage() {
        $this->allow('show',
                     'mod_coursework\grading_table_row_base',
            function (grading_table_row_base $grading_table_row) {
                if (!$grading_table_row->get_coursework()->allocation_enabled()) {
                    foreach ($grading_table_row->get_coursework()->marking_stages() as $stage) {
                        if ($stage->user_is_assessor($this->get_user())) {
                            return true;
                        }
                    }
                }
                return false;
            });
    }

    protected function allow_show_grading_table_row_if_user_has_added_feedback_for_this_submission() {
        $this->allow('show',
                     'mod_coursework\grading_table_row_base',
            function (grading_table_row_base $grading_table_row) {
                if ($grading_table_row->has_submission()) {
                    if (feedback::exists(array('submissionid' => $grading_table_row->get_submission()->id,
                                               'assessorid' => $this->get_user()->id()))
                    ) {
                        return true;
                    }
                }
                return false;
            });
    }

    protected function allow_show_grading_table_row_if_user_can_view_grades_at_all_times() {
        $this->allow('show',
                     'mod_coursework\grading_table_row_base',
            function (grading_table_row_base $grading_table_row) {
                return has_capability('mod/coursework:viewallgradesatalltimes',
                                      $grading_table_row->get_coursework()->get_context());
            });
    }

    protected function allow_show_grading_table_row_if_user_can_submit_on_behalf_of() {
        $this->allow('show',
                     'mod_coursework\grading_table_row_base',
            function (grading_table_row_base $grading_table_row) {
                return has_capability('mod/coursework:submitonbehalfof',
                                      $grading_table_row->get_coursework()->get_context());
            });
    }





    protected function allow_show_grading_table_row_if_user_can_export_final_grades() {
        $this->allow('show',
                     'mod_coursework\grading_table_row_base',
            function (grading_table_row_base $grading_table_row) {
                return has_capability('mod/coursework:canexportfinalgrades',
                                      $grading_table_row->get_coursework()->get_context());
            });
    }

    protected function allow_show_feedback_if_user_can_view_grades_at_all_times_or_administer() {
        $this->allow('show',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                return  has_capability('mod/coursework:viewallgradesatalltimes',
                                                                      $feedback->get_coursework()
                                                                          ->get_context())
                || has_capability('mod/coursework:administergrades',
                    $feedback->get_coursework()
                        ->get_context());
            });
    }

    private function allow_new_feedback_if_agreed_feedback_and_user_can_add_agreed_feedback() {
        $this->allow('new',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                $this->set_message('User can not add new agreed feedback.');

                $has_editable_feedbacks =   false;

                // find out if the previous grades are editable
                if ($feedback->is_agreed_grade())   {

                    $has_editable_feedbacks  =   $feedback->get_submission()->editable_feedbacks_exist();

                }


                return $feedback->is_agreed_grade() && !$has_editable_feedbacks && (has_capability('mod/coursework:addagreedgrade',
                                                                      $feedback->get_coursework()
                                                                          ->get_context())
                                                        || has_capability('mod/coursework:addallocatedagreedgrade',
                                                                         $feedback->get_coursework()
                                                                            ->get_context())
                                                            && $feedback->get_submission()->is_assessor_initial_grader());
            });
    }

    private function allow_new_feedback_if_user_can_administer_grades() {
        $this->allow('new',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                return has_capability('mod/coursework:administergrades',
                                      $feedback->get_coursework()
                                          ->get_context());
            });
    }

    private function allow_show_grading_table_row_if_user_can_administer_grades() {
        $this->allow('show',
                     'mod_coursework\grading_table_row_base',
            function (grading_table_row_base $row) {
                return has_capability('mod/coursework:administergrades',
                                      $row->get_coursework()
                                          ->get_context());
            });
    }




    private function allow_show_grading_table_row_if_user_can_grant_extension_and_no_allocation() {
        $this->allow('show',
            'mod_coursework\grading_table_row_base',
            function (grading_table_row_base $row) {

               return  (!$row->get_coursework()->allocation_enabled() && has_capability('mod/coursework:grantextensions',
                        $row->get_coursework()
                            ->get_context()));

            });
    }



    private function allow_edit_feedback_if_user_can_administer_grades() {
        $this->allow('edit',
                     'mod_coursework\models\feedback',
            function (feedback $feedback) {
                return has_capability('mod/coursework:administergrades',
                                      $feedback->get_coursework()
                                          ->get_context());
            });
    }

    private function allow_new_deadline_extension_with_capability() {
        $this->allow('new',
                     'mod_coursework\models\deadline_extension',
            function (deadline_extension $deadline_extension) {
                return $deadline_extension->get_coursework()->has_deadline() && has_capability('mod/coursework:grantextensions',
                                      $deadline_extension->get_coursework()
                                          ->get_context());
            });
    }

    private function allow_show_deadline_extension_with_capability() {
        $this->allow('show',
                     'mod_coursework\models\deadline_extension',
            function (deadline_extension $deadline_extension) {
                return has_capability('mod/coursework:viewextensions',
                                      $deadline_extension->get_coursework()
                                          ->get_context());
            });
    }

    private function allow_edit_deadline_extension_with_capability() {
        $this->allow('edit',
                     'mod_coursework\models\deadline_extension',
            function (deadline_extension $deadline_extension) {
                return has_capability('mod/coursework:grantextensions',
                                      $deadline_extension->get_coursework()
                                          ->get_context());
            });
    }

    private function prevent_new_deadline_extension_if_already_exists() {
        $this->prevent('new',
                       'mod_coursework\models\deadline_extension',
            function (deadline_extension $deadline_extension) {
                $conditions = array(
                    'allocatableid' => $deadline_extension->allocatableid,
                    'allocatabletype' => $deadline_extension->allocatabletype,
                    'courseworkid' => $deadline_extension->courseworkid,
                );
                return deadline_extension::exists($conditions);
            });
    }

    private function prevent_edit_deadline_extension_if_not_persisted() {
        $this->prevent('edit',
                       'mod_coursework\models\deadline_extension',
            function (deadline_extension $deadline_extension) {
                return !$deadline_extension->persisted();
            });
    }

    private function allow_create_deadline_extension_if_can_new() {
        $this->allow('create',
                     'mod_coursework\models\deadline_extension',
            function (deadline_extension $deadline_extension) {
                return $this->can('new', $deadline_extension);
            });
    }

    private function allow_update_deadline_extension_if_can_edit() {
        $this->allow('update',
                     'mod_coursework\models\deadline_extension',
            function (deadline_extension $deadline_extension) {
                return $this->can('edit', $deadline_extension);
            });
    }

    private function allow_show_submission_if_user_can_submit_on_behalf_of() {
        $this->allow('show',
            'mod_coursework\models\submission',
            function (submission $submission) {

                return has_capability('mod/coursework:submitonbehalfof', $submission->get_context());
            });
    }

    private function prevent_show_grading_table_row_if_user_can_submit_on_behalf_of_and_view_all_students() {
        $this->prevent('show',
            'mod_coursework\models\submission',
            function (submission $submission) {
                return (has_capability('mod/coursework:submitonbehalfof', $submission->get_context()) && has_capability('mod/coursework:viewallstudents', $submission->get_context())) ? false : true;
            });
    }

    private function allow_edit_personal_deadline_with_capability() {
        $this->allow('edit',
            'mod_coursework\models\personal_deadline',
            function (personal_deadline $personal_deadline) {
                return $personal_deadline->get_coursework()->personal_deadlines_enabled() 
                        && has_capability('mod/coursework:editpersonaldeadline',
                    $personal_deadline->get_coursework()
                        ->get_context());
            });
    }

    private function prevent_edit_personal_deadline_if_extension_given() {
        $this->prevent('edit',
            'mod_coursework\models\personal_deadline',
            function (personal_deadline $personal_deadline) {
                // check if extension for this PD exists
                return $personal_deadline->extension_exists();
            });
    }

}