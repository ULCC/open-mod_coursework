<?php

/**
 * Step definitions for the Coursework module Behat tests.
 */

use Behat\Behat\Context\Step\Given as Given;
use Behat\Behat\Context\Step\When as When;
use Behat\Behat\Context\Step\Then as Then;
use Behat\Mink\Exception\ExpectationException as ExpectationException;
use mod_coursework\models\group;
use mod_coursework\router;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use mod_coursework\stages\base as stage_base;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');
require_once(__DIR__ . '/../../../../vendor/phpunit/phpunit/src/Framework/Assert/Functions.php');

$files = glob(dirname(__FILE__) . '/steps/*.php');
foreach($files as $filename) {
    require_once($filename);
}

/**
 * Class behat_mod_coursework
 * @property mixed teacher
 * @property mixed other_teacher
 * @property submission submission
 * @property stdClass course
 * @property mixed form
 * @property coursework coursework
 * @property mixed student
 * @property feedback feedback
 * @property mixed manager
 * @property mixed allocation
 * @property mixed final_feedback
 * @property mixed other_student
 * @property mixed group
 */
class behat_mod_coursework extends behat_base {

    /**
     * @var int numbers prepended to 'user' in order to create different roles
     * without username/email collisions.
     */
    protected $user_suffix = 0;

    /**
     * Factory that makes an instance of the page class, passing in the session context, then caches it
     * and returns it when required.
     *
     * @param string $page_name
     * @throws coding_exception
     * @return mod_coursework_behat_page_base
     */
    protected function get_page($page_name) {
        global $CFG;

        $page_name = str_replace(' ', '_', $page_name); // 'student page' => 'student_page'

        $file_path = $CFG->dirroot.'/mod/coursework/tests/behat/pages/'.$page_name.'.php';

        if (file_exists($file_path)) {
            require_once($file_path);
            $class_name = 'mod_coursework_behat_' . $page_name ;
            return new $class_name($this);
        }

        throw new coding_exception('Asked for a behat page class which does not exist: '.$page_name);

    }

    /**
     * Centralises the match between the names of paths in the module and the urls they correspond to.
     *
     * @param string $path
     * @param bool $escape
     * @throws coding_exception
     * @throws moodle_exception
     * @return string the url
     */
    protected function locate_path($path, $escape = true) {

        switch($path) {
            case 'course':
                return parent::locate_path('/course/view.php?id=' . $this->course->id);
                break;

            case 'edit coursework':
                return parent::locate_path('/mod/edit.php');
                break;

            case 'coursework settings':
                return parent::locate_path('/course/modedit.php?update=' . $this->get_coursework()->get_course_module()->id);
                break;

            case 'coursework':
                return parent::locate_path('/mod/coursework/view.php?id='. $this->get_coursework()->get_course_module()->id);
                break;

            case 'allocations':
                return parent::locate_path('/mod/coursework/actions/allocate.php?id='.$this->get_coursework()->get_course_module()->id);

            case 'assessor grading':
                return parent::locate_path('/mod/coursework/actions/feedback/new.php?submissionid=' . $this->submission->id.'&assessorid='.$this->teacher->id);

            case 'new feedback':
                return $this->get_router()->get_path('new feedback',
                                                     array('submission' => $this->submission,
                                                           'assessor' => $this->teacher,
                                                           'stage' => $this->get_first_assesor_stage()),
                                                     false,
                                                     $escape);
            case 'create feedback':
                return $this->get_router()->get_path('create feedback',
                                                     array('coursework' => $this->coursework),
                                                     false,
                                                     $escape);

            case 'new submission':
                $submission = submission::build(array(
                                                    'courseworkid' => $this->coursework->id,
                                                    'allocatableid' => $this->student->id,
                                                    'allocatabletype' => 'user'
                                                ));
                return $this->get_router()->get_path('new submission',
                                                     array('submission' => $submission), false, $escape);

            case 'create submission':
                return $this->get_router()->get_path('create submission',
                                                     array('coursework' => $this->coursework),
                                                     false,
                                                     $escape);

            case 'edit submission':
                return $this->get_router()->get_path('edit submission',
                                                     array('submission' => $this->submission),
                                                     false,
                                                     $escape);

            case 'update submission':
                return $this->get_router()->get_path('update submission',
                                                     array('submission' => $this->submission),
                                                     false,
                                                     $escape);

            case 'edit feedback':
                if (empty($this->feedback)) {
                    $this->feedback = feedback::last();
                }
                return $this->get_router()->get_path('edit feedback', array('feedback' => $this->feedback), false, $escape);

            case 'gradebook':
                return parent::locate_path('/grade/report/user/index.php?id=' . $this->course->id);
                break;

            case 'login':
                return parent::locate_path('/login/index.php');
                break;

            default:
                return parent::locate_path($path);

        }

    }

    /**
     * @Given /^I should( not)? see the file on the page$/
     *
     * @param bool $negate
     * @throws Behat\Mink\Exception\ExpectationException
     */
    public function iShouldSeeTheFileOnThePage($negate = false) {
        $file_count = count($this->getSession()->getPage()->findAll('css', '.submissionfile'));
        if (!$negate && !$file_count) {
            throw new ExpectationException('No files found', $this->getSession());
        } else if ($negate && $file_count) {
            throw new ExpectationException('Files found, but there should be none', $this->getSession());
        }
    }

    /**
     * @Then /^I should see (\d+) file(?:s)? on the page$/
     *
     * @param $numberoffiles
     * @throws Behat\Mink\Exception\ExpectationException
     */
    public function iShouldSeeFileOnThePage($numberoffiles) {
        $file_count = count($this->getSession()->getPage()->findAll('css', '.submissionfile'));

        if ($numberoffiles != $file_count) {
            throw new ExpectationException($file_count.' files found, but there should be '.$numberoffiles, $this->getSession());
        }
    }

    /**
     * @When /^the cron runs$/
     */
    public function theCronRuns() {
        coursework_cron();
    }

    /**
     * @Then /^I should( not)? see( the)? (.*)'s name on the page$/
     * @param bool $negate
     * @return \Behat\Behat\Context\Step\Given
     */
    public function iShouldSeeTheStudentSNameOnThePage($negate = false,$negate2=false,$studentrole) {


        $student    =   ($studentrole == "another student") ? $this->other_student : $this->student;

        $studentname = fullname($student);

        if ($negate) {
            return new Given("I should not see \"{$studentname}\"");
        } else {
            return new Given("I should see \"{$studentname}\"");
        }
    }





    /* Private methods follow */


    /**
     * Returns the last created coursework.
     *
     * @return false|coursework
     */
    private function get_coursework() {
        if (empty($this->coursework)) {
            $this->coursework = coursework::last();
        }

        return $this->coursework;

    }

    /**
     * @return stage_base
     */
    private function get_first_assesor_stage() {
        $stages = $this->coursework->get_assessor_marking_stages();
        return reset($stages);
    }


    /**
     *
     *
     * @param $role_name
     * @return string
     */
    private function make_role_name_into_variable_name($role_name) {
        $role_name = str_replace('other ', 'other_', $role_name);
        return str_replace(' ', '', $role_name);
    }

    /**
     * Returns an xpath string to find a tag that has a class and contains some text.
     *
     * @param string $tagname div td
     * @param string $class
     * @param string $text
     * @param bool $exact_text
     * @throws coding_exception
     * @return string
     */
    private function xpath_tag_class_contains_text($tagname = '', $class = '', $text = '', $exact_text = false) {

        if (!$class && !$text) {
            throw new coding_exception('Must supply one of class or text');
        }

        $xpath = '//';
        $xpath .= $tagname;

        if ($class) {
            $xpath .= "[contains(concat(' ', @class, ' '), ' {$class} ')]";
        }

        if ($text) {
            if ($exact_text) {
                $xpath .= "[contains(., '{$text}')]";
            } else {
                $xpath .= "[contains(., '{$text}')]";
            }
        }

        return $xpath;
    }

    /**
     * @return router
     */
    protected function get_router() {

        return router::instance();
    }

    /**
     * In case we just created a feedback with a form submission, we want to get hold of it.
     * @return mixed
     */
    protected function get_feedback() {
        if (empty($this->feedback)) {
            $this->feedback = feedback::last();
        }

        return $this->feedback;
    }

    /**
     * @Then /^I should see the student allocated to the other teacher for the first assessor$/
     */
    public function iShouldSeeTheStudentAllocatedToTheOtherTeacher() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->user_should_be_alocated_to_assessor($this->student, $this->other_teacher, 'assessor_1');
    }

    /**
     * @Then /^I should see the student allocated to the teacher for the first assessor$/
     */
    public function iShouldSeeTheStudentAllocatedToTheTeacher() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->user_should_be_alocated_to_assessor($this->student, $this->teacher, 'assessor_1');
    }

    /**
     * @Then /^there should be no allocations in the db$/
     */
    public function thereShouldBeNoAllocationsInTheDb() {
        $params = array(
            'courseworkid' => $this->coursework->id,
        );
        assertEmpty(\mod_coursework\models\allocation::count($params));
    }

    /**
     * @Then /^I should not see the finalise button$/
     */
    public function iShouldNotSeeTheFinaliseButton() {
        /**
         * @var mod_coursework_behat_student_page $page
         */
        $page = $this->get_page('student page');
        $page->should_not_have_a_finalise_button();
    }

    /**
     * @Given /^I save the submission$/
     */
    public function iSaveTheSubmission() {
        /**
         * @var mod_coursework_behat_student_submission_form $page
         */
        $page = $this->get_page('student submission form');
        $page->click_on_the_save_submission_button();
    }

    /**
     * @Given /^I save and finalise the submission$/
     */
    public function iSaveAndFinaliseTheSubmission() {
        /**
         * @var mod_coursework_behat_student_submission_form $page
         */
        $page = $this->get_page('student submission form');
        $page->click_on_the_save_and_finalise_submission_button();
    }

    /**
     * @Then /^I should not see the save and finalise button$/
     */
    public function iShouldNotSeeTheSaveAndFinaliseButton() {
        /**
         * @var mod_coursework_behat_student_submission_form $page
         */
        $page = $this->get_page('student submission form');
        $page->should_not_have_the_save_and_finalise_button();
    }

    /**
     * @Given /^the submission deadline has passed$/
     */
    public function theSubmissionDeadlineHasPassed() {
        $this->coursework->update_attribute('deadline', strtotime('1 hour ago'));
    }

    /**
     * @Given /^the coursework has moderation enabled$/
     */
    public function theCourseworkHasModerationEnabled() {
        $this->coursework->update_attribute('moderationenabled', 1);
    }

    /**
     * @Given /^the coursework has (\d) assessor$/
     * @param $number_of_assessors
     */
    public function theCourseworkHasOneAssessor($number_of_assessors) {
        $this->coursework->update_attribute('numberofmarkers', $number_of_assessors);
    }

    /**
     * @Given /^there is feedback for the submission from the teacher$/
     */
    public function thereIsFeedbackForTheSubmissionFromTheTeacher() {
        $feedback = new stdClass();
        $feedback->submissionid = $this->submission->id;
        $feedback->assessorid = $this->teacher->id;
        $feedback->grade = 58;
        $feedback->feedbackcomment = 'Blah';
        $feedback->stage_identifier = 'assessor_1';
        $this->feedback = feedback::create($feedback);
    }

    /**
     * @Then /^I should( not)? see the new moderator feedback button for the student$/
     * @param bool $negate
     * @throws coding_exception
     */
    public function iShouldSeeTheNewModeratorFeedbackButton($negate = false) {

        /**
         * @var mod_coursework_behat_single_grading_interface $page
         */
        $page = $this->get_page('single grading interface');
        if ($negate) {
            $page->should_not_have_new_moderator_feedback_button($this->student);
        } else {
            $page->should_have_new_moderator_feedback_button($this->student);
        }
    }

    /**
     * @Given /^the other student is in the moderation set$/
     */
    public function theOtherStudentIsInTheModerationSet() {
        $membership = new stdClass();
        $membership->allocatabletype = 'user';
        $membership->allocatableid = $this->other_student->id;
        $membership->courseworkid = $this->coursework->id;
        \mod_coursework\models\assessment_set_membership::create($membership);
    }

    /**
     * @Given /^the student is in the moderation set$/
     */
    public function theStudentIsInTheModerationSet() {
        $membership = new stdClass();
        $membership->allocatabletype = 'user';
        $membership->allocatableid = $this->student->id;
        $membership->courseworkid = $this->coursework->id;
        \mod_coursework\models\assessment_set_membership::create($membership);
    }

    /**
     * @Given /^the moderator allocation strategy is set to equal$/
     */
    public function theModeratorAllocationStrategyIsSetToEqual() {
        $this->coursework->update_attribute('moderatorallocationstrategy', 'equal');
    }

    /**
     * @Then /^the student should not have anyone allocated as a moderator$/
     */
    public function theStudentShouldNotHaveAnyoneAllocatedAsAModerator() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->should_not_have_moderator_allocated($this->student);
    }

    /**
     * @Then /^the student should have the manager allocated as the moderator$/
     */
    public function theStudentShouldHaveTheManagerAllocatedAsTheModerator() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->should_have_moderator_allocated($this->student, $this->manager);
    }

    /**
     * @Given /^the coursework has automatic assessor allocations disabled$/
     */
    public function theCourseworkHasAutomaticAssessorAllocationsDisabled() {
        $this->coursework->update_attribute('assessorallocationstrategy', 'none');
    }

    /**
     * @Given /^the coursework has automatic assessor allocations enabled$/
     */
    public function theCourseworkHasAutomaticAssessorAllocationsEnabled() {
        $this->coursework->update_attribute('allocationenabled', '1');
    }

    /**
     * @Given /^I click on the new feedback button for assessor (\d+)$/
     * @param $assessor_number
     * @throws coding_exception
     */
    public function iClickOnTheNewFeedbackButtonForAssessor($assessor_number) {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_assessor_new_feedback_button($assessor_number, $this->student);

    }

    /**
     * @Given /^I click on the new feedback button for assessor (\d+) for another student$/
     * @param $assessor_number
     * @throws coding_exception
     */
    public function iClickOnTheNewFeedbackButtonForAssessorForAnotherStudent($assessor_number) {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_assessor_new_feedback_button($assessor_number, $this->other_student);
    }

    /**
     * @Given /^I publish the grades$/
     */
    public function iPublishTheGrades() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');

        if ($this->running_javascript())$this->waitForSeconds(10);

        $page->press_publish_button();

        if ($this->running_javascript())$this->waitForSeconds(10);
        $page->confirm_publish_action();
        if ($this->running_javascript())$this->waitForSeconds(10);

    }

    /**
     * @Then /^the coursework general feedback is disabled$/
     */
    public function theCourseworkGeneralFeedbackIsDisabled() {
        $this->coursework->disable_general_feedback();
    }

    /**
     * @Then /^the coursework general feedback is enabled$/
     */
    public function theCourseworkGeneralFeedbackIsEnabled() {
        $this->coursework->enable_general_feedback();
    }

    /**
     * @Then /^the coursework general feedback should be disabled$/
     */
    public function theCourseworkGeneralFeedbackShouldBeDisabled() {
        $this->get_coursework()->reload();
        assertFalse($this->get_coursework()->is_general_feedback_enabled());
    }

    /**
     * @Given /^blind marking is enabled$/
     */
    public function blindMarkingIsEnabled() {
        $this->get_coursework()->update_attribute('blindmarking', 1);
    }

    /**
     * @Then /^I should not see the student's name in the user cell$/
     */
    public function iShouldNotSeeTheStudentSNameInTheUserCell() {
        /**
         * @var $page mod_coursework_behat_single_grading_interface
         */
        $page = $this->get_page('single grading interface');
        $page->should_not_have_user_name_in_user_cell($this->student);
    }

    /**
     * @Then /^I should see the student's name in the user cell$/
     */
    public function iShouldSeeTheStudentSNameInTheUserCell() {
        /**
         * @var $page mod_coursework_behat_single_grading_interface
         */
        $page = $this->get_page('single grading interface');
        $page->should_have_user_name_in_user_cell($this->student);
    }

    /**
     * @Given /^group submissions are enabled$/
     */
    public function groupSubmissionsAreEnabled() {
        $this->get_coursework()->update_attribute('use_groups', 1);
    }

    /**
     * @Given /^the group is part of a grouping for the coursework$/
     */
    public function theGroupIsPartOfAGroupingForTheCoursework() {
        $generator = testing_util::get_data_generator();
        $grouping = new stdClass();
        $grouping->courseid = $this->course->id;
        $grouping =  $generator->create_grouping($grouping);
        groups_assign_grouping($grouping->id, $this->group->id);
        $this->get_coursework()->update_attribute('grouping_id', $grouping->id);
    }

    /**
     * @Then /^I should not see the student's name in the group cell$/
     */
    public function iShouldNotSeeTheStudentSNameInTheGroupCell() {
        /**
         * @var $page mod_coursework_behat_single_grading_interface
         */
        $page = $this->get_page('single grading interface');
        $page->should_not_have_user_name_in_group_cell($this->student);
    }

    /**
     * @Then /^I should see the student's name in the group cell$/
     */
    public function iShouldSeeTheStudentSNameInTheGroupCell() {
        /**
         * @var $page mod_coursework_behat_single_grading_interface
         */
        $page = $this->get_page('single grading interface');
        $page->should_have_user_name_in_group_cell($this->student);
    }

    /**
     * @When /^I click on the view icon for the first initial assessor's grade$/
     */
    public function iClickOnTheViewIconForTheFirstInitialAssessorSGrade() {
        $feedback = $this->get_initial_assessor_feedback_for_student();
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_feedback_show_icon($feedback);
    }

    /**
     * @Given /^I should not see the show feedback link for assessor 1$/
     */
    public function iShouldNotSeeTheShowFeedbackLinkForAssesor() {
        $feedback = $this->get_initial_assessor_feedback_for_student();
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */
        $page = $this->get_page('multiple grading interface');
        $page->should_not_have_show_feedback_icon($feedback);
    }

    /**
     * @Then /^I should( not)? see the grade from the teacher in the assessor table$/
     * @param bool $negate
     * @throws coding_exception
     */
    public function iShouldNotSeeTheGradeFromTheTeacherInTheAssessorTable($negate = false) {
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */
        $page = $this->get_page('multiple grading interface');
        $feedback = $this->get_initial_assessor_feedback_for_student();

        if ($negate) {
            $page->should_not_have_grade_in_assessor_table($feedback);
        } else {
            $page->should_have_grade_in_assessor_table($feedback);
        }
    }

    /**
     * IMPORTANT: CI server borks if this is not done *before* the manager
     * logs in!
     *
     * @Given /^managers do not have the manage capability$/
     */
    public function managersDoNotHaveTheManageCapability() {
        global $DB;

        $manager_role = $DB->get_record('role', array('shortname' => 'manager'));
        $params = array('roleid' => $manager_role->id,
                        'capability' => 'mod/coursework:manage');
        $permission_setting = CAP_PROHIBIT;
        $DB->set_field('role_capabilities', 'permission', $permission_setting, $params);
    }

    /**
     * @Given /^I am allowed to view all students$/
     */
    public function iAmAllowedToViewAllStudents()   {
        global $DB;

        $teacher_role   =   $DB->get_record('role', array('shortname' => 'teacher'));
        $params =   array('roleid'      => $teacher_role->id,
                          'capability'  => 'mod/coursework:viewallstudents');
        $permission_setting =   CAP_ALLOW;
        $DB->set_field('role_capabilities', 'permission', $permission_setting, $params);
    }



    /**
     * IMPORTANT: CI server borks if this is not done *before* the manager
     * logs in!
     *
     * @Given /^teachers have the add agreed grade capability$/
     */
    public function teachersHaveTheAddAgreedGradeCapability() {
        global $DB;

        $teacher_role = $DB->get_record('role', array('shortname' => 'teacher'));
        $params = array('roleid' => $teacher_role->id,
                        'capability' => 'mod/coursework:addagreedgrade',
                        'contextid' => 1,
                        'permission' => CAP_ALLOW);
        $DB->insert_record('role_capabilities', $params);
    }

    /**
     * @Then /^I should see two feedback files on the page$/
     */
    public function iShouldSeeTwoFeedbackFilesOnThePage() {
        /**
         * @var mod_coursework_behat_student_page $page
         */
        $page = $this->get_page('student page');

        if ($this->running_javascript()) $this->waitForSeconds(10);

        $page->should_have_number_of_feedback_files(2);
    }

    /**
     * @Given /^the coursework start date is disabled$/
     */
    public function theCourseworkStartDateIsDisabled() {
        $this->coursework->update_attribute('startdate', 0);
    }

    /**
     * @Given /^the coursework start date is in the future$/
     */
    public function theCourseworkStartDateIsInTheFuture() {
        $this->coursework->update_attribute('startdate', strtotime('+1 week'));
    }

    /**
     * @Given /^the coursework start date is in the past$/
     */
    public function theCourseworkStartDateIsInThePast() {
        $this->coursework->update_attribute('startdate', strtotime('-1 week'));
    }

    /**
     * @Then /^I should( not)? see the edit feedback button for the teacher's feedback$/
     */
    public function iShouldNotSeeTheEditFeedbackButtonForTheTeacherSFeedback($negate = false) {
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */
        $page = $this->get_page('multiple grading interface');
        $feedback = $this->get_initial_assessor_feedback_for_student();

        if ($negate) {
            $page->should_not_have_edit_link_for_feedback($feedback);
        } else {
            $page->should_have_edit_link_for_feedback($feedback);
        }
    }

    /**
     * @Then /^I should( not)? see the add final feedback button$/
     * @param bool $negate
     * @throws coding_exception
     */
    public function iShouldNotSeeTheAddFinalFeedbackButton($negate = false) {
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */
        $page = $this->get_page('multiple grading interface');


        if ($negate) {
            $page->should_not_have_add_button_for_final_feedback($this->student->id());
        } else {
            $page->should_have_add_button_for_final_feedback($this->student->id());
        }

    }




    /**
     * @Then /^I should not see the edit final feedback button on the multiple marker page$/
     */
    public function iShouldNotSeeTheEditFinalFeedbackButtonOnTheMultipleMarkerPage() {
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */

        $allocatable = new stdClass();
        $allocatable->courseworkid = $this->coursework->id;
        $allocatable->allocatableid = $this->student->id();
        $allocatable->allocatabletype = $this->student->type();

        $page = $this->get_page('multiple grading interface');
        $page->should_not_have_edit_link_for_final_feedback($allocatable);
    }

    /**
     * @Given /^the coursework is set to single marker$/
     */
    public function theCourseworkIsSetToSingleMarker() {
        $this->get_coursework()->update_attribute('numberofmarkers', 1);
    }

    /**
     * @Given /^the coursework is set to double marker$/
     */
    public function theCourseworkIsSetToDoubleleMarker() {
        $this->get_coursework()->update_attribute('numberofmarkers', 2);
    }

    /**
     * @Given /^the coursework individual feedback release date has passed$/
     */
    public function theCourseworkIndividualFeedbackReleaseDateHasPassed() {
        $this->get_coursework()->update_attribute('individualfeedback', strtotime('1 week ago'));
    }

    /**
     * @Given /^the coursework individual feedback release date has not passed$/
     */
    public function theCourseworkIndividualFeedbackReleaseDateHasNotPassed() {
        $this->get_coursework()->update_attribute('individualfeedback', strtotime('+1 week'));
    }

    /**
     * @Then /^I should see the name of the teacher in the assessor feedback cell$/
     */
    public function iShouldSeeTheNameOfTheTeacherInTheAssessorFeedbackCell() {

        /**
         * @var mod_coursework_behat_single_grading_interface $page
         */
        $page = $this->get_page('single grading interface');
        $page->should_have_assessor_name_in_assessor_feedback_cell($this->teacher);
    }

    /**
     * @Given /^the coursework has assessor allocations enabled$/
     */
    public function theCourseworkHasAssessorAllocationsEnabled() {
        $this->coursework->update_attribute('allocationenabled', 1);
    }

    /**
     * @Given /^I agree to the confirm message$/
     */
    public function iAgreeToTheConfirmMessage() {
        $this->get_page('coursework page')->confirm();
    }

    /**
     * @Given /^the coursework allocation option is disabled$/
     */
    public function theCourseworkAllocationOptionIsDisabled()
    {
        $coursework = $this->get_coursework();

        $coursework->allocationenabled = 0;
        $coursework->save();
    }

    /**
     * @Given /^the manager has a capability to allocate students in samplings$/
     */
    public function theManagerHasACapabilityToAllocateStudentsInSamplings()
    {
        global $DB;

        $manager_role = $DB->get_record('role', array('shortname' => 'manager'));
        $params = array('roleid' => $manager_role->id,
            'capability' => 'mod/coursework:sampleselection');
        $DB->set_field('role_capabilities', 'permission', CAP_ALLOW, $params);
    }

    /**
     * @Given /^I (de)?select (a|another) student as a part of the sample for the second stage$/
     */
    public function iSelectTheStudentAsAPartOfTheSample($negate = false,$other)
    {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $other = ($other == 'another');
        $student = $other ? 'other_student' : 'student';

        $page = $this->get_page('allocations page');
        if ($negate){
            $page->deselect_for_sample($this->$student, 'assessor_2');
        } else {
            $page->select_for_sample($this->$student, 'assessor_2');
        }
    }

    /**
     * @Given /^the teacher has a capability to mark submissions$/
     */
    public function theTeacherHasACapabilityToMarkSubmissions()
    {
        global $DB;

        $teacher_role = $DB->get_record('role', array('shortname' => 'teacher'));

        role_change_permission($teacher_role->id,
                               $this->get_coursework()->get_context(),
                               'mod/coursework:addinitialgrade',
                               CAP_ALLOW);
    }

    /**
     * @Given /^the teacher has a capability to edit their own initial feedbacks$/
     */
    public function theTeacherHasACapabilityToEditOwnFeedbacks()
    {
        global $DB;

        $teacher_role = $DB->get_record('role', array('shortname' => 'teacher'));

        role_change_permission($teacher_role->id, $this->get_coursework()->get_context(),
                               'mod/coursework:editinitialgrade', CAP_ALLOW);

    }

    /**
     * @Given /^the teacher has a capability to edit their own agreed feedbacks$/
     */
    public function theTeacherHasACapabilityToEditOwnAgreedFeedbacks() {
        global $DB;

        $teacher_role = $DB->get_record('role', array('shortname' => 'teacher'));
        role_change_permission($teacher_role->id,
                               $this->get_coursework()->get_context(),
                               'mod/coursework:editagreedgrade',
                               CAP_ALLOW);
    }

    /**
     * @Given /^the coursework has sampling enabled$/
     */
    public function theCourseworkHasSamplingEnabled()
    {
        $this->get_coursework()->update_attribute('samplingenabled', '1');
    }

    /**
     * @Given /^there is feedback for the submission from the other teacher$/
     */
    public function thereIsFeedbackForTheSubmissionFromTheOtherTeacher()
    {
        $this->feedback = feedback::create(array(
            'submissionid'=>$this->submission->id,
            'assessorid' => $this->other_teacher->id,
            'grade'=> '78',
            'feedbackcomment'=>'Blah',
            'stage_identifier'=>'assessor_1'
        ));
    }

    /**
     * @Then /^I should (not )?be able to add the second grade for this student$/
     */
    public function iShouldNotBeAbleToAddTheSecondGradeForThisStudent($negate = false)
    {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');

        if ($negate) {
            $page->should_not_have_new_feedback_button($this->submission);
        } else {
            $page->should_have_new_feedback_button($this->submission);
        }

    }

    /**
     * @Then /^I should see the grade given by the initial teacher in the provisional grade column$/
     */
    public function iShouldSeeTheGradeGivenByTheInitialTeacherInTheProvisionalGradeColumn()
    {

        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $provisional_grade_field = $page->get_provisional_grade_field($this->submission);
        $grade_field = $page->get_grade_field($this->submission);

        assertEquals($provisional_grade_field, $grade_field);
    }

    /**
     * @Given /^there is an extension for the student that allows them to submit$/
     */
    public function thereIsAnExtensionForTheStudentThatAllowsThemToSubmit() {
        \mod_coursework\models\deadline_extension::create(array(
                                       'allocatableid' => $this->student->id(),
                                       'allocatabletype' => 'user',
                                       'courseworkid' => $this->coursework->id,
                                       'extended_deadline' => strtotime('+2 weeks 3:30pm', $this->coursework->deadline)
                                   ));
    }

    /**
     * @Given /^there is an extension for the student which has expired$/
     */
    public function thereIsAnExtensionForTheStudentWhichHasExpired() {
        $this->extension_deadline = strtotime('3:30pm', strtotime('-2 weeks ', $this->coursework->deadline));
        \mod_coursework\models\deadline_extension::create(array(
                                                              'allocatableid' => $this->student->id(),
                                                              'allocatabletype' => 'user',
                                                              'courseworkid' => $this->coursework->id,
                                                              'extended_deadline' => $this->extension_deadline
                                                          ));
    }

    /**
     * @When /^I add a new extension for the student$/
     */
    public function iAddANewExtensionForTheStudent() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigrader_page = $this->get_page('multiple grading interface');
        $multigrader_page->click_new_extension_button_for($this->student);


        /**
         * @var mod_coursework_behat_new_extension_page $new_extension_page
         */
        $new_extension_page = $this->get_page('new extension page');
        $this->extension_deadline = strtotime('3:30pm', strtotime('+1 week'));
        $new_extension_page->add_active_extension($this->extension_deadline);
    }

    /**
     * @Given /^I should see the extended deadline in the student row$/
     */
    public function iShouldSeeTheExtendedDeadlineInTheStudentRow() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigrader_page = $this->get_page('multiple grading interface');
        $multigrader_page->should_show_extension_for_allocatable($this->student, $this->extension_deadline);
    }

    /**
     * @When /^I edit the extension for the student$/
     */
    public function iAddEditTheExtensionForTheStudent() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigrader_page = $this->get_page('multiple grading interface');
        $multigrader_page->click_edit_extension_button_for($this->student);

        /**
         * @var mod_coursework_behat_edit_extension_page $edit_extension_page
         */
        $edit_extension_page = $this->get_page('edit extension page');
        $this->extension_deadline = strtotime('3:30pm', strtotime('+4 weeks'));
        $edit_extension_page->edit_active_extension($this->extension_deadline);
    }

    /**
     * @Given /^there are some extension reasons configured at site level$/
     */
    public function thereAreSomeExtensionReasonsConfiguredAtSiteLevel() {
        set_config('coursework_extension_reasons_list', "first reason\nsecond reason");
    }

    /**
     * @Given /^I should see the deadline reason in the deadline extension form$/
     */
    public function iShouldSeeTheDealineReasonInTheStudentRow() {
        /**
         * @var mod_coursework_behat_edit_extension_page $edit_extension_page
         */
        $edit_extension_page = $this->get_page('edit extension page');
        $edit_extension_page->should_show_extension_reason_for_allocatable(0);
    }

    /**
     * @Given /^I should see the extra information in the deadline extension form$/
     */
    public function iShouldSeeTheExtraInformationInTheStudentRow() {
        /**
         * @var mod_coursework_behat_edit_extension_page $edit_extension_page
         */
        $edit_extension_page = $this->get_page('edit extension page');
        $edit_extension_page->should_show_extra_information_for_allocatable('Extra info here');
    }

    /**
     * @When /^I click on the edit extension icon for the student$/
     */
    public function iClickOnTheEditExtensionIconForTheStudent() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigrader_page = $this->get_page('multiple grading interface');
        $multigrader_page->click_edit_extension_button_for($this->student);
    }

    /**
     * @Given /^I submit the extension deadline form$/
     */
    public function iSubmitTheExtensionDeadlineForm() {
        /**
         * @var mod_coursework_behat_new_extension_page $edit_extension_page
         */
        $edit_extension_page = $this->get_page('new extension page');
        $edit_extension_page->submit_form();
    }

    /**
     * @Given /^I should see the new extended deadline in the student row$/
     */
    public function iShouldSeeTheNewExtendedDeadlineInTheStudentRow() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigrader_page = $this->get_page('multiple grading interface');
        $multigrader_page->should_show_extension_for_allocatable($this->student,
                                                                 $this->extension_deadline);
    }

    /**
     * @Then /^I should see the new deadline reason in the dropdown$/
     */
    public function iShouldSeeTheNewDeadlineReasonInTheDropdown() {
        /**
         * @var mod_coursework_behat_edit_extension_page $edit_extension_page
         */
        $edit_extension_page = $this->get_page('edit extension page');
        $edit_extension_page->should_show_extension_reason_for_allocatable(1);
    }

    /**
     * @Given /^I should see the new extra deadline information in the deadline extension form$/
     */
    public function iShouldSeeTheNewExtraDeadlineInformationInTheDeadlineExtensionForm() {
        /**
         * @var mod_coursework_behat_edit_extension_page $edit_extension_page
         */
        $edit_extension_page = $this->get_page('edit extension page');
        $edit_extension_page->should_show_extra_information_for_allocatable('New info here');
    }

    /**
     * @Given /^I click on the new submission button for the student$/
     */
    public function iClickOnTheNewSubmissionButtonForTheStudent() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigrader_page = $this->get_page('multiple grading interface');
        $multigrader_page->click_new_submission_button_for($this->student);
    }

    /**
     * @Given /^I click on the edit submission button for the student$/
     */
    public function iClickOnTheEditSubmissionButtonForTheStudent() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigrader_page = $this->get_page('multiple grading interface');
        $multigrader_page->click_edit_submission_button_for($this->student);
    }

    /**
     * @Given /^the coursework individual extension option is enabled$/
     */
    public function theCourseworkIndividualExtensionOptionIsEnabled()
    {
        $coursework = $this->get_coursework();

        $coursework->extensionsenabled = 1;
        $coursework->save();
    }

    /**
     * @Then /^I should see that the student has two allcations$/
     */
    public function iShouldSeeThatTheStudentHasTwoAllcations() {
        /**
         * @var $page mod_coursework_behat_allocations_page
         */
        $page = $this->get_page('allocations page');
        $page->user_should_be_alocated_to_assessor($this->student, $this->teacher, 'assessor_1');
        $page->user_should_be_alocated_to_assessor($this->student, $this->other_teacher, 'assessor_2');
    }

    /**
     * @Then /^I should see that both students are allocated to the teacher$/
     */
    public function iShouldSeeThatBothStudentsAreAllocatedToTheTeacher() {
        /**
         * @var $page mod_coursework_behat_allocations_page
         */
        $page = $this->get_page('allocations page');
        $page->user_should_be_alocated_to_assessor($this->student, $this->teacher, 'assessor_1');
        $page->user_should_be_alocated_to_assessor($this->other_student, $this->teacher, 'assessor_1');
    }

    /**
     * @Given /^editing teachers are prevented from adding general feedback$/
     */
    public function editingTeachersArePreventedFromAddingGeneralFeedback() {
        global $DB;

        $teacher_role = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $params = array('roleid' => $teacher_role->id,
                        'capability' => 'mod/coursework:addgeneralfeedback',
                        'contextid' => 1
                        );
        $cap = $DB->get_record('role_capabilities', $params);
        $cap->permission = CAP_PREVENT;
        $DB->update_record('role_capabilities', $cap);
    }

    /**
     * To protect the blind marking aspect, the submission row does not contain any reference to the student id in
     * the HTML. We use the filename hash instead.
     */
    protected function get_submission_grading_table_row_id() {
        return '#submission_'. $this->student_hash();
    }

    /**
     * @return string
     */
    protected function new_final_feedback_link_id() {
        return '#new_final_feedback_' . $this->student_hash();
    }

    /**
     * @return string
     */
    protected function edit_final_feedback_link_id() {
        return '#edit_final_feedback_' . $this->student_hash();
    }

    /**
     * @return string
     */
    protected function new_moderator_feedback_link_id() {
        return '#new_moderator_feedback_' . $this->student_hash();
    }

    /**
     * @return string
     */
    protected function edit_moderator_feedback_link_id() {
        return '#edit_moderator_feedback_' . $this->student_hash();
    }

    /**
     * @return mixed
     */
    protected function student_hash() {
        return $this->coursework->get_allocatable_identifier_hash($this->student);
    }

    /**
     * @return mod_coursework_generator
     */
    protected function get_coursework_generator() {
        return testing_util::get_data_generator()->get_plugin_generator('mod_coursework');
    }

    /**
     * Hacky way to allow page objects to use a protected method.
     *
     * @param string $element CSS
     */
    public function wait_till_element_exists($element) {
        $this->ensure_element_exists($element, 'css_element');
    }

    /**
     * @return bool
     */
    public function running_javascript() {
        return parent::running_javascript();
    }



    // Course steps

    /**
     * @Given /^there is a course$/
     */
    public function there_is_a_course() {
        $course = new stdClass();
        $course->fullname = 'Course 1';
        $course->shortname = 'C1';
        $generator = testing_util::get_data_generator();
        $this->course = $generator->create_course($course);
    }

    /**
     * This is really just a convenience method so that we can chain together the call to create the
     * course and this one, within larger steps.
     *
     * @Given /^the course has been kept for later$/
     */
    public function the_course_has_been_kept_for_later() {
        global $DB;

        $this->course = $DB->get_record('course', array('shortname' => 'C1'));
    }

    /**
     * @Given /^the course has completion enabled$/
     */
    public function the_course_has_completion_enabled() {
        global $DB;

        set_config('enablecompletion', 1); // Global setting.
        $DB->set_field('course', 'enablecompletion', 1, array('id' => $this->course->id));
    }




    // Coursework steps

    /**
     * Assumes we are on the course page and are adding via the normal UI process.
     *
     * Probably needs to be altered to work differently if we have javascript enabled.
     *
     * @Given /^I add a new coursework$/
     */
    public function I_add_a_new_coursework() {
        return array(
            new Given('I turn editing mode on'),
            new When('I select "Coursework" from "Add an activity..."'),
            new Then('I click')
        );
    }

    /**
     * @Given /^there is a coursework$/
     */
    public function there_is_a_coursework() {

        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $coursework = new stdClass();
        $coursework->course = $this->course;
        $this->coursework = $generator->create_instance($coursework);
    }

    /**
     * @Then /^I should see the title of the coursework on the page$/
     */
    public function iShouldSeeTheTitleOfTheCourseworkOnThePage() {

        return new Given("I should see \"{$this->coursework->name}\"");
    }

    /**
     * @Then /^the coursework "([\w]+)" setting should be "([\w]*)" in the database$/
     * @param $setting_name
     * @param $seting_value
     * @throws Behat\Mink\Exception\ExpectationException
     */
    public function the_coursework_setting_should_be($setting_name, $seting_value) {
        if ($seting_value == 'NULL') {
            $seting_value = null;
        }

        if ($this->get_coursework()->$setting_name !== $seting_value) {
            throw new ExpectationException("The coursework {$setting_name} setting should have been {$seting_value} but was {$this->get_coursework()->$setting_name}",
                                           $this->getSession());
        }
    }

    /**
     * @Then /^the coursework "([\w]+)" setting is "([\w]*)" in the database$/
     * @param $setting_name
     * @param $setting_value
     */
    public function the_coursework_setting_is_in_the_database($setting_name, $setting_value) {
        $coursework = $this->get_coursework();
        if ($setting_value == 'NULL') {
            $setting_value = null;
        }
        $coursework->$setting_name = $setting_value;
        $coursework->save();
    }

    /**
     * @Then /^there should be ([\d]+) coursework$/
     * @param $expected_count
     * @throws Behat\Mink\Exception\ExpectationException
     */
    public function there_should_only_be_one_coursework($expected_count) {
        global $DB;

        $number_in_database = $DB->count_records('coursework');

        if ($number_in_database > (int)$expected_count) {
            throw new ExpectationException("Too many courseworks! There should be {$expected_count}, but there were {$DB->count_records('coursework')}",
                                           $this->getSession());
        }
    }

    /**
     * @Given /^the coursework is set to use the custom form$/
     */
    public function theCourseworkIsSetToUseTheCustomForm() {
        global $DB;

        $coursework = $this->get_coursework();
        $coursework->formid = $this->form->id;
        $coursework->save();

        if (!$DB->record_exists('coursework', array('formid' => $this->form->id))) {
            throw new ExpectationException('no field change', $this->getSession());
        }
    }

    /**
     * @Given /^the coursework deadline has passed$/
     */
    public function theCourseworkDeadlineHasPassed() {
        $deadline = strtotime('-1 week');
        $this->coursework->update_attribute('deadline', $deadline);
    }

    /**
     * @Given /^the general feedback deadline has passed$/
     */
    public function the_general_feedback_deadline_has_passed() {
        $this->get_coursework()->generalfeedback = strtotime('-1 day');
        $this->get_coursework()->save();
    }

    /**
     * @Given /^I press the publish button$/
     */
    public function iPressThePublishButton() {
        $this->find('css', '#id_publishbutton')->press();
        $this->find_button('Continue')->press();
        $this->getSession()->visit($this->locate_path('coursework')); // Quicker than waiting for a redirect
    }

    /**
     * @Given /^the managers are( not)? allowed to grade$/
     * @param bool $negate
     */
    public function theManagersAreNotAllowedToGrade($negate = false) {
        global $DB;

        $manager_role = $DB->get_record('role', array('shortname' => 'manager'));
        $params = array('roleid' => $manager_role->id,
                        'capability' => 'mod/coursework:addinitialgrade');
        if ($negate) {
            $permission_setting = CAP_PROHIBIT;
        } else {
            $permission_setting = CAP_ALLOW;
        }
        $DB->set_field('role_capabilities', 'permission', $permission_setting, $params);
    }

    /**
     * @Given /^the grades have been published$/
     */
    public function theGradesHaveBeenPublished() {
        $this->coursework->publish_grades();
    }

    /**
     * @Given /^the sitewide "([^"]*)" setting is "([^"]*)"$/
     * @param $setting_name
     * @param $setting_value
     */
    public function theSitewideSettingIs($setting_name, $setting_value) {
        set_config($setting_name, $setting_value);
    }




    // Allocation steps

    /**
     * @Given /^I manually allocate the student to the other teacher$/
     */
    public function iManuallyAllocateTheStudentToTheOtherTeacher() {

        // Identify the allocation dropdown.
        $dropdownname = 'user_' . $this->student->id . '_assessor_1';
        $node = $this->find_field($dropdownname);

        // We delegate to behat_form_field class, it will
        // guess the type properly as it is a select tag.
        $field = behat_field_manager::get_form_field($node, $this->getSession());
        $field->set_value($this->other_teacher->id);

        $this->find_button('save_manual_allocations_2')->click();
    }

    /**
     * @Given /^I manually allocate the student to the other teacher for the second assessment$/
     */
    public function iManuallyAllocateTheStudentToTheOtherTeacherForTheSecondAssessment() {

        // Identify the allocation dropdown.
        $dropdownname = 'user_' . $this->student->id . '_assessor_2';
        $node = $this->find_field($dropdownname);

        // We delegate to behat_form_field class, it will
        // guess the type properly as it is a select tag.
        $field = behat_field_manager::get_form_field($node, $this->getSession());
        $field->set_value($this->other_teacher->id);

    }

    /**
     * @Given /^I manually allocate the student to the teacher$/
     */
    public function iManuallyAllocateTheStudentToTheTeacher() {

        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->manually_allocate($this->student, $this->teacher, 'assessor_1');

    }

    /**
     * @Given /^I manually allocate the other student to the teacher$/
     */
    public function iManuallyAllocateTheOtherStudentToTheTeacher() {

        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->manually_allocate($this->other_student, $this->teacher, 'assessor_1');
        $page->save_everything();
    }

    /**
     * @Given /^I manually allocate another student to another teacher$/
     */
    public function iManuallyAllocateAnotherStudentToAnotherTeacher() {

        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->manually_allocate($this->other_student, $this->other_teacher, 'assessor_1');
        $page->save_everything();
    }

    /**
     * @Given /^I auto-allocate all students to assessors$/
     */
    public function iAutoAllocateAllStudents() {
        $this->find_button('Save everything')->press();
    }

    /**
     * @Given /^I auto-allocate all non-manual students to assessors$/
     */
    public function iAutoAllocateAllNonManualStudents() {
        $this->find_button('auto-allocate-all-non-manual-assessors')->press();
    }

    /**
     * @Given /^I auto-allocate all non-allocated students to assessors$/
     */
    public function iAutoAllocateAllNonAllocatedStudents() {
        $this->find_button('auto-allocate-all-non-allocated-assessors')->press();
    }

    /**
     * @Given /^I set the allocation strategy to (\d+) percent for the other teacher$/
     * @param $percent
     * @throws Behat\Mink\Exception\ElementNotFoundException
     */
    public function theAllocationStrategyIsPercentForTheOtherTeacher($percent) {

        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        if ($this->running_javascript()) {
            $page->show_assessor_allocation_settings();
        }
        $this->find('css', '#menuassessorallocationstrategy')->selectOption('percentages');
        $this->getSession()->getPage()->fillField("assessorstrategypercentages[{$this->other_teacher->id}]", $percent);
    }

    /**
     * @Given /^I set the allocation strategy to (\d+) percent for the teacher$/
     * @param $percent
     * @throws Behat\Mink\Exception\ElementNotFoundException
     */
    public function theAllocationStrategyIsPercentForTheTeacher($percent) {

        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        if ($this->running_javascript()) {
            $page->show_assessor_allocation_settings();
        }
        $this->find('css', '#menuassessorallocationstrategy')->selectOption('percentages');
        $this->getSession()->getPage()->fillField("assessorstrategypercentages[{$this->teacher->id}]", $percent);
        $this->find('css', '#save_manual_allocations_2')->press();
    }

    /**
     * @Given /^the student is( manually)? allocated to the teacher$/
     * @param bool $manual
     * @throws coding_exception
     */
    public function theStudentIsAllocatedToTheTeacher($manual = false) {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $allocation = new stdClass();
        $allocation->allocatableid = $this->student->id;
        $allocation->allocatabletype = 'user';
        $allocation->assessorid = $this->teacher->id;
        $allocation->stage_identifier = 'assessor_1';
        $allocation->courseworkid = $this->get_coursework()->id;
        if ($manual) {
            $allocation->manual = 1;
        }
        $generator->create_allocation($allocation);
    }

    /**
     * @Given /^the student is auto allocated to the teacher$/
     */
    public function there_is_a_student_auto_allocated_to_the_teacher() {
        return array(
            new Given("I visit the allocations page"),
            new Given("I auto-allocate all students to assessors")
        );
    }

    /**
     * @Given /^the manager is manually allocated as the moderator for the student$/
     */
    public function theManagerIsAllocatedAsTheModeratorForTheStudent() {
        $allocation = new stdClass();
        $allocation->manual = 1;
        $allocation->courseworkid = $this->coursework->id;
        $allocation->assessorid = $this->manager->id;
        $allocation->allocatableid = $this->student->id();
        $allocation->allocatabletype = $this->student->type();
        $allocation->stage_identifier = $this->coursework->get_moderator_marking_stage()->identifier();

        $this->allocation = $this->get_coursework_generator()->create_allocation($allocation);
    }

    /**
     * @Given /^the manager is automatically allocated as the moderator for the student$/
     */
    public function theManagerIsAutomaticallyAllocatedAsTheModeratorForTheStudent() {
        $allocation = new stdClass();
        $allocation->manual = 0;
        $allocation->courseworkid = $this->coursework->id;
        $allocation->assessorid = $this->manager->id;
        $allocation->allocatableid = $this->student->id();
        $allocation->allocatabletype = $this->student->type();
        $allocation->stage_identifier = $this->coursework->get_moderator_marking_stage()->identifier();

        $this->allocation = $this->get_coursework_generator()->create_allocation($allocation);
    }

    /**
     * @Given /^there are no allocations in the db$/
     */
    public function thereAreNoAllocationsInTheDb() {
        global $DB;

        $DB->delete_records('coursework_allocation_pairs');
    }

    /**
     * @Then /^the student should be allocated to an assessor$/
     */
    public function theStudentShouldBeAllocatedToAnAssessor() {
        global $DB;

        $params = array(
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
        );
        assertNotEmpty($DB->get_record('coursework_allocation_pairs', $params));
    }


    // Feedback steps

    /**
     * @Then /^I should( not)? see the final grade on the student page$/
     *
     * @param bool $negate
     */
    public function iShouldSeeTheFinalGradeOnTheStudentPage($negate = false) {

        $css_id = '#final_feedback_grade';

        if ($negate) {
            $this->ensure_element_does_not_exist($css_id, 'css_element');
        } else {
            $comment_field = $this->find('css', $css_id);
            $text = $comment_field->getText();

            assertEquals(56, $text);
        }
    }

    /**
     * @Given /^I should( not)? see the grade comment on the student page$/
     * @param bool $negate
     */
    public function iShouldSeeTheGradeCommentOnTheStudentPage($negate = false) {

        if ($negate) {
            $this->ensure_element_does_not_exist('#final_feedback_comment', 'css_element');
        } else {
            $comment_field = $this->find('css', '#final_feedback_comment');
            $text = $comment_field->getText();
            assertEquals('New comment here', $text);
        }
    }

    /**
     * @Given /^there is some general feedback$/
     */
    public function there_is_some_general_feedback() {
        $this->get_coursework()->feedbackcomment = 'Some comments';
        $this->get_coursework()->save();
    }

    /**
     * @Given /^(I|the ([\w ]+)) (?:has|have) graded the submission as assessor (\d+)$/
     *
     * @param $i
     * @param string $role_name
     * @param int $assessor_number
     * @throws coding_exception
     */
    public function theOtherTeacherHasGradedTheSubmission($i, $role_name = '', $assessor_number = 1) {

        if ($i == 'I') {
            $role_name = 'teacher';
        } else {
            // other editing teacher => other_editingteacher
            $role_name = $this->make_role_name_into_variable_name($role_name);
        }

        if (empty($this->$role_name)) {
            throw new coding_exception('no ' . $role_name . ' user was found');
        }

        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $feedback = new stdClass();
        $feedback->submissionid = $this->submission->id;
        $feedback->assessorid = $this->$role_name->id;
        $feedback->stage_identifier = 'assessor_'.$assessor_number;
        $feedback->grade = 50;
        $generator->create_feedback($feedback);
    }

    /**
     * @Then /^I should( not)? see the other teacher\'s grade as assessor (\d+)$/
     * @param bool $negate
     * @param int $assessor_number
     * @throws coding_exception
     */
    public function iShouldNotSeeTheOtherTeacherSGrade($negate = false, $assessor_number = 1) {

        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        if ($negate) {
            $page->assessor_grade_should_not_be_present($this->student, $assessor_number, '50');
        } else {
            $page->assessor_grade_should_be_present($this->student, $assessor_number, '50');
        }

    }

    /**
     * @When /^I click the new final feedback button for the group$/
     */
    public function iClickTheNewFinalFeedbackButtonGroup() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_new_final_feedback_button($this->group);
    }

    /**
     * @When /^I click the new multiple final feedback button for the student/
     */
    public function iClickTheNewMultipleFinalFeedbackButtonStudent() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_new_final_feedback_button($this->student);
    }

    /**
     * @When /^I click the new single final feedback button for the student/
     */
    public function iClickTheNewSingleFinalFeedbackButtonStudent() {
        /**
         * @var mod_coursework_behat_single_grading_interface $page
         */
        $page = $this->get_page('single grading interface');
        $page->click_new_final_feedback_button($this->student);
    }

    /**
     * @Given /^I should see the grade in the form on the page$/
     */
    public function iShouldSeeTheGradeInTheFormOnThePage() {
        $comment_field = $this->find('css', '#feedback_grade');

        assertEquals(56, $comment_field->getValue());
    }

    /**
     * @Given /^I should see the other teacher's final grade in the form on the page$/
     */
    public function iShouldSeeTheOtherTeachersFinalGradeInTheFormOnThePage() {
        $comment_field = $this->find('css', '#feedback_grade');

        assertEquals(45, $comment_field->getValue());
    }

    /**
     * @Given /^I should see the other teacher's grade in the form on the page$/
     */
    public function iShouldSeeTheOtherTeachersGradeInTheFormOnThePage() {
        $comment_field = $this->find('css', '#feedback_grade');

        assertEquals(58, $comment_field->getValue());
    }

    /**
     * @Given /^there is final feedback$/
     */
    public function thereIsFinalFeedback() {
        $generator = $this->get_coursework_generator();

        $feedback = new stdClass();
        $feedback->grade = 45;
        $feedback->feedbackcomment = 'blah';
        $feedback->isfinalgrade = 1;
        $feedback->submissionid = $this->submission->id;
        $feedback->assessorid = $this->manager->id;
        $feedback->stage_identifier = 'final_agreed_1';

        $this->final_feedback = $generator->create_feedback($feedback);
    }

    /**
     * @Given /^there is final feedback from the other teacher$/
     */
    public function thereIsFinalFeedbackFromTheOtherTeacher() {
        $generator = $this->get_coursework_generator();

        $feedback = new stdClass();
        $feedback->grade = 45;
        $feedback->feedbackcomment = 'blah';
        $feedback->isfinalgrade = 1;
        $feedback->submissionid = $this->submission->id;
        $feedback->assessorid = $this->other_teacher->id;
        $feedback->stage_identifier = 'final_agreed_1';

        $this->final_feedback = $generator->create_feedback($feedback);
    }

    /**
     * @When /^I click the new moderator feedback button$/
     */
    public function iClickTheNewModeratorFeedbackButton() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_new_moderator_feedback_button($this->student);
    }

    /**
     * @When /^I click the edit moderator feedback button$/
     */
    public function iClickTheEditModeratorFeedbackButton() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_edit_moderator_feedback_button($this->student);

    }

    /**
     * @Given /^I should see the moderator grade on the page$/
     */
    public function iShouldSeeTheModeratorGradeOnThePage() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->should_have_moderator_grade_for($this->student, '56');
//        if (!$this->find('xpath', $this->xpath_tag_class_contains_text('td', 'moderated', '56'))) {
//            throw new ExpectationException('Could not find the moderated grade', $this->getSession());
//        }
    }

    /**
     * @When /^I click the edit final feedback button$/
     */
    public function iClickTheEditFinalFeedbackButton() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_edit_final_feedback_button($this->student);
    }

    /**
     * @When /^I click the edit single assessor feedback button$/
     */
    public function iClickTheEditSingleFeedbackButton() {
        /**
         * @var mod_coursework_behat_single_grading_interface $page
         */
        $page = $this->get_page('single grading interface');
        $page->click_edit_feedback_button($this->student);
    }

    /**
     * @Given /^there are feedbacks from both me and another teacher$/
     */
    public function thereAreFeedbacksFromMeAndAnotherTeacher() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $feedback = new stdClass();
        $feedback->assessorid = $this->manager->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->feedbackcomment = 'New comment here';
        $feedback->stage_identifier = 'assessor_1';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 67;

        $generator->create_feedback($feedback);

        $feedback = new stdClass();
        $feedback->assessorid = $this->other_teacher->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->stage_identifier = 'assessor_2';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 63;

        $generator->create_feedback($feedback);
    }

    /**
     * @Given /^there are feedbacks from both teachers$/
     */
    public function thereAreFeedbacksFromBothTeachers() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $feedback = new stdClass();
        $feedback->assessorid = $this->teacher->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->feedbackcomment = 'New comment here';
        $feedback->stage_identifier = 'assessor_1';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 67;

        $generator->create_feedback($feedback);

        $feedback = new stdClass();
        $feedback->assessorid = $this->other_teacher->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->stage_identifier = 'assessor_2';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 63;

        $generator->create_feedback($feedback);
    }

    /**
     * @Then /^I should( not)? see the final grade(?: as )?(\d+)? on the multiple marker page$/
     * @param bool $negate
     * @param int $grade
     * @throws Behat\Mink\Exception\ExpectationException
     * @throws coding_exception
     */
    public function iShouldSeeTheFinalMultipleGradeOnThePage($negate = false, $grade = 56) {
        try {
            $grade = count($this->find_all('xpath', $this->xpath_tag_class_contains_text('td', 'multiple_agreed_grade_cell', $grade)));
        } catch(Exception $e) {
            $grade = false;
        }
        $ishouldseegrade = $negate == false;
        $ishouldnotseegrade = $negate == true;
        if (!$grade && $ishouldseegrade) {
            throw new ExpectationException('Could not find the final grade', $this->getSession());
        } else {
            if ($grade && $ishouldnotseegrade) {
                throw new ExpectationException('Grade found, but there should be none', $this->getSession());
            }
        }
    }

    /**
     * @Then /^I should see the final grade(?: as )?(\d+)? on the single marker page$/
     * @param int $grade
     * @throws Behat\Mink\Exception\ExpectationException
     * @throws coding_exception
     */
    public function iShouldSeeTheFinalSingleGradeOnThePage($grade = 56) {
        $actual_grade = $this->find('css', 'td.single_assessor_feedback_cell')->getText();
        if (strpos($actual_grade, (string)$grade) === false) {
            throw new ExpectationException('Could not find the final grade. Got '.$actual_grade.' instead', $this->getSession());
        }
    }

    /**
     * @Given /^I have an assessor feedback$/
     */
    public function iHaveAnAssessorFeedback() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');
        $feedback = new stdClass();
        $feedback->assessorid = $this->teacher->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->stage_identifier = 'assessor_1';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 67;

        $this->feedback = $generator->create_feedback($feedback);
    }

    /**
     * @Then /^I should see the grade comment (?:as ")?(\w+)?(?:" )?in the form on the page$/
     * @param string $comment
     */
    public function iShouldSeeTheGradeCommentInTheFormOnThePage($comment = 'New comment here') {
        $comment_field = $this->find('css', '#feedback_comment');

        assertEquals($comment, $comment_field->getValue());
    }

    /**
     * @Given /^I click on the edit feedback icon$/
     */
    public function iClickOnTheEditFeedbackIcon() {

        if ($this->running_javascript())$this->waitForSeconds(10);

        $this->find('css', "#edit_feedback_{$this->get_feedback()->id}")->click();

        if ($this->running_javascript())$this->waitForSeconds(10);
    }

    /**
     * @Then /^I should see the grade on the page$/
     */
    public function iShouldSeeTheGradeOnThePage() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->assessor_grade_should_be_present($this->student, 1, 56);
//        $xpath = $this->xpath_tag_class_contains_text('td', 'cfeedbackcomment', '56');
//        if (!$this->getSession()->getPage()->has('xpath', $xpath)) {
//            throw new ExpectationException('Should have seen the grade ("56"), but it was not there',
//                                           $this->getSession());
//        }
    }

    /**
     * @Then /^I should see the rubric grade on the page$/
     */
    public function iShouldSeeTheRubricGradeOnThePage() {
        $cell_text = $this->find('css', '#final_feedback_grade')->getText();

        assertContains('50', $cell_text);
    }

    /**
     * @When /^I grade the submission(?: as )?(\d+)?( without comments)? using the simple form$/
     *
     * @param int $grade
     * @throws Behat\Mink\Exception\ElementException
     * @throws Behat\Mink\Exception\ElementNotFoundException
     */
    public function iGradeTheSubmissionUsingTheSimpleForm($grade = 56, $withoutcomments=false) {
        $nodeElement = $this->getSession()->getPage()->findById('feedback_grade');
        if ($nodeElement) {
            $nodeElement->selectOption($grade);
        }


        if (empty($withoutcomments)) {
            $nodeElement1 = $this->find('css', '#feedback_comment');
            if ($nodeElement1) {
                $nodeElement1->setValue('New comment here');
            }
        }

        $this->getSession()->getPage()->findButton('submitbutton')->press();

        $this->feedback = feedback::last();
    }

    /**
     * @Then /^I should see the final grade for the group in the grading interface$/
     * 
     */
    public function iShouldSeeTheFinalGradeForTheGroupInTheGradingInterface() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->group_should_have_a_final_multiple_grade($this->group);
    }

    /**
     * @When /^I fill in the rest of the form after the rubric and submit it$/
     */
    public function iFillInTheRestOfTheFormAterTheRubric() {
        $this->find('css', '#feedback_comment')->setValue('New comment here');
        $this->getSession()->getPage()->findButton('submitbutton')->press();

        $this->feedback = feedback::last();
    }

    /**
     * @Given /^I should see the group grade assigned to the other student$/
     */
    public function iShouldSeeTheGroupGradeAssignedToTheOtherStudent() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->student_should_have_a_final_grade($this->other_student);
    }

    /**
     * @Then /^I should see the grade for the group submission$/
     */
    public function iShouldSeeTheGradeForTheGroupSubmission() {
        /**
         * @var mod_coursework_behat_student_page $page
         */
        $page = $this->get_page('student page');
        $page->should_have_visible_grade(45);
    }

    /**
     * @Then /^I should see the feedback for the group submission$/
     */
    public function iShouldSeeTheFeedbackForTheGroupSubmission() {
        /**
         * @var mod_coursework_behat_student_page $page
         */
        $page = $this->get_page('student page');
        $page->should_have_visible_feedback('blah');
    }

    /**
     * @Then /^I should see the grade in the gradebook$/
     */
    public function iShouldSeeTheGradeInTheGradebook() {
        /**
         * @var mod_coursework_behat_gradebook_page $page
         */
        $page = $this->get_page('gradebook page');
        $page->should_have_coursework_grade_for_student($this->coursework, $this->student, 45);
    }

    /**
     * @Then /^I should see the rubric grade in the gradebook$/
     */
    public function iShouldSeeTheRubricGradeInTheGradebook() {
        /**
         * @var mod_coursework_behat_gradebook_page $page
         */
        $page = $this->get_page('gradebook page');
        $page->should_have_coursework_grade_for_student($this->coursework, $this->student, 50);
    }

    /**
     * @Given /^there is a rubric defined for the coursework$/
     */
    public function thereIsARubricDefinedForTheCoursework() {
        global $DB;

        $grading_area = new stdClass();
        $grading_area->contextid = $this->coursework->get_context_id();
        $grading_area->component = 'mod_coursework';
        $grading_area->areaname = 'submissions';
        $grading_area->activemethod = 'rubric';
        $grading_area->id = $DB->insert_record('grading_areas', $grading_area);

        // Make the rubric
        $grading_definition = new stdClass();
        $grading_definition->areaid = $grading_area->id;
        $grading_definition->method = 'rubric';
        $grading_definition->name = 'Test rubric';
        $grading_definition->description = 'Rubric description';
        $grading_definition->descriptionformat = 1;
        $grading_definition->status = 20;
        $grading_definition->timecreated = time();
        $grading_definition->usercreated = 2;
        $grading_definition->timemodified = time();
        $grading_definition->usermodified = 2;
        $grading_definition->options =
            '{"sortlevelsasc":"1","alwaysshowdefinition":"1","showdescriptionteacher":"1","showdescriptionstudent":"1","showscoreteacher":"1","showscorestudent":"1","enableremarks":"1","showremarksstudent":"1"}';
        $grading_definition->id = $DB->insert_record('grading_definitions', $grading_definition);

        $rubric_criteria = new stdClass();
        $rubric_criteria->definitionid = $grading_definition->id;
        $rubric_criteria->sortorder = 1;
        $rubric_criteria->description = 'first criterion';
        $rubric_criteria->descriptionformat = 0;
        $rubric_criteria->id = $DB->insert_record('gradingform_rubric_criteria', $rubric_criteria);

        $rubric_level = new stdClass();
        $rubric_level->criterionid = $rubric_criteria->id;
        $rubric_level->score = 0;
        $rubric_level->definition = 'Bad';
        $rubric_level->definitionformat = 0;
        $DB->insert_record('gradingform_rubric_levels', $rubric_level);
        $rubric_level->score = 1;
        $rubric_level->definition = 'OK';
        $DB->insert_record('gradingform_rubric_levels', $rubric_level);
        $rubric_level->score = 2;
        $rubric_level->definition = 'Good';
        $DB->insert_record('gradingform_rubric_levels', $rubric_level);
    }

    /**
     * @Then /^I should not see a link to add feedback$/
     */
    public function iShouldNotSeeALinkToAddFeedback() {
        /**
         * @var mod_coursework_behat_single_grading_interface $grading_interface
         */
        if ($this->coursework->has_multiple_markers()) {
            $grading_interface = $this->get_page('multiple grading interface');
        } else {
            $grading_interface = $this->get_page('single grading interface');
        }
        $grading_interface->there_should_not_be_a_feedback_icon($this->student);
    }


    // General web steps

    /**
     * @Given /^I (?:am on|visit) the ([\w ]+) page$/
     * @param $path_name
     */
    public function visit_page($path_name) {
        $this->getSession()->visit($this->locate_path($path_name, false));
    }

    /**
     * @Then /^I should be on the ([\w ]+) page(, ignoring parameters)?$/
     * @param $page_name
     * @param bool $ignore_params
     */
    public function i_should_be_on_the_page($page_name, $ignore_params = false) {
        $ignore_params = !!$ignore_params;

        if ($this->running_javascript())$this->waitForSeconds(10);

        $currentUrl = $this->getSession()->getCurrentUrl();
        $current_anchor = parse_url($currentUrl, PHP_URL_FRAGMENT);
        $currentUrlwithoutAnchor = str_replace('#' . $current_anchor, '', $currentUrl);

        $desirtedUrl = $this->locate_path($page_name, false);

        // Strip the params if we need to. Can be handy if we have unpredictable landing page e.g. after create there will
        // possibly be a new id in there.
        if ($ignore_params) {
            $current_path = parse_url($currentUrl, PHP_URL_PATH);
//            $desired_path = parse_url($desirtedUrl, PHP_URL_PATH);
            $message = "Should be on the " . $desirtedUrl . " page but instead the url is " . $current_path;
            assertEquals($current_path, $desirtedUrl, $message);
        } else {
            $message = "Should be on the " . $desirtedUrl . " page but instead the url is " . $currentUrlwithoutAnchor;
            assertEquals($currentUrlwithoutAnchor, $desirtedUrl, $message);
        }
    }

    /**
     * @Then /^show me a screenshot$/
     * @param string $file_name
     */
    public function show_me_a_screenshot($file_name = 'behat_screenshot.jpg') {
        global $CFG;

        $this->saveScreenshot($file_name, $CFG->dataroot . '/temp');
        $this->open_screenshot($file_name, $CFG->dataroot . '/temp');
    }

    /**
     * @Then /^show me the page$/
     * @param string $filename
     */
    public function show_me_the_page($filename = 'behat_page.html') {
        global $CFG;

        $html_data = $this->getSession()->getDriver()->getContent();
        $file_and_path = $CFG->dataroot . '/temp/'.$filename;
        file_put_contents($file_and_path, $html_data);
        $this->open_html_page($file_and_path);
    }

    /**
     * @Given /^max debugging$/
     */
    public function maxDebugging() {
        set_config('debug', DEBUG_DEVELOPER);
        set_config('debugdisplay', 1);
    }

    /**
     * @Given /^wait for (\d+) seconds$/
     * @param $seconds
     */
    public function waitForSeconds($seconds) {
        $this->getSession()->wait($seconds * 1000);
    }

    //     And I click on ".moodle-dialogue-focused.filepicker .yui3-button.closebutton" "css_element"
    public function dismiss() {
        $this->find('css', ".moodle-dialogue-focused.filepicker .yui3-button.closebutton")->click();
    }





    // Submission steps

    /**
     * @Given /^(?:I have|the student has) a submission$/
     */
    public function iHaveASubmission() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $submission = new stdClass();
        $submission->allocatableid = $this->student->id();
        $submission->allocatabletype = $this->student->type();
        $this->submission = $generator->create_submission($submission, $this->coursework);
    }

    /**
     * @Given /^another student has another submission$/
     */
    public function anotherStudentHasAnotherSubmission() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $submission = new stdClass();
        $submission->allocatableid = $this->other_student->id();
        $submission->allocatabletype = $this->other_student->type();
        $this->other_submission = $generator->create_submission($submission, $this->coursework);
    }


    /**
     * @Given /^the group has a submission$/
     */
    public function itheGroupHasASubmission() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $submission = new stdClass();
        $submission->allocatableid = $this->group->id();
        $submission->allocatabletype = $this->group->type();
        $submission->lastupdatedby = $this->student->id();
        $submission->createdby = $this->student->id();
        $this->submission = $generator->create_submission($submission, $this->get_coursework());
    }

    /**
     * @Then /^the submission should( not)? be finalised$/
     * @param bool $negate
     */
    public function theSubmissionShouldBeFinalised($negate = false) {
        global $DB;

        $finalised = $DB->get_field('coursework_submissions', 'finalised', array('id' => $this->submission->id));

        assertEquals($negate ? 0 : 1, $finalised);
    }

    /**
     * @Then /^I should( not)? see the student\'s submission on the page$/
     * @param bool $negate
     */
    public function iShouldSeeTheStudentSSubmissionOnThePage($negate = false) {
        $fields =
            $this->getSession()->getPage()->findAll('css', ".submission-{$this->submission->id}");
        assertCount($negate ? 0 : 1, $fields);
    }

    /**
     * @Given /^the submission is finalised$/
     */
    public function theSubmissionIsFinalised() {
        $this->submission->finalised = 1;
        $this->submission->save();
    }

    /**
     * @Then /^the file upload button should not be visible$/
     */
    public function theFileUploadButtonShouldNotBeVisible() {

        $button = $this->find('css', 'div.fp-btn-add a');

        assertFalse($button->isVisible(), "The file picker upload buton should be hidden, but it isn't");
    }

    /**
     * @Given /^I click on the (\w+) submission button$/
     * @param $action
     */
    public function iClickOnTheNewSubmissionButton($action) {
        /**
         * @var mod_coursework_behat_student_page $page
         */
        $page = $this->get_page('student page');
        if ($action == 'edit') {
            $page->click_on_the_edit_submission_button();
        } else if ($action == 'new') {
            $page->click_on_the_new_submission_button();
        } else if ($action == 'finalise') {
            $page->click_on_the_finalise_submission_button();
        } else if ($action == 'save') {
            $page->click_on_the_save_submission_button();
        }
    }

    /**
     * @Then /^I should( not)? see the (\w+) submission button$/
     * @param bool $negate
     * @param string $action
     */
    public function iShouldNotSeeTheEditSubmissionButton($negate = false, $action = 'new') {
        // behat generates button type submit whereas code does input
        $input = $this->getSession()->getPage()
            ->findAll('xpath', "//div[@class='{$action}submissionbutton']//input[@type='submit']");
        $button = $this->getSession()->getPage()
            ->findAll('xpath', "//div[@class='{$action}submissionbutton']//button[@type='submit']");
        $buttons = ($input)? $input : $button;// check how element was created and use it to find the button

        assertCount(($negate ? 0 : 1), $buttons);
    }

    /**
     * @Then /^I should see both the submission files on the page$/
     */
    public function iShouldSeeBothTheFilesOnThePage() {

        /**
         * @var mod_coursework_behat_student_page $student_page
         */
        $student_page = $this->get_page('student page');
        $student_page->should_have_two_submission_files();
    }

    /**
     * @Given /^I should see that the submission was made by the (.+)$/
     * @param string $role_name
     */
    public function iShouldSeeThatTheSubmissionWasMadeByTheOtherStudent($role_name) {
        $role_name = str_replace(' ', '_', $role_name);

        /**
         * @var mod_coursework_behat_student_page $student_page
         */
        $student_page = $this->get_page('student page');
        $student_page->should_show_the_submitter_as($role_name);
    }




    // User steps

    /**
     * @Given /^I (?:am logged|log) in as (?:a|an|the) (?P<role_name_string>(?:[^"]|\\")*)$/
     * @param $role_name
     * @throws coding_exception
     */
    public function i_am_logged_in_as_a($role_name) {

        $role_name = $this->make_role_name_into_variable_name($role_name);

        if (empty($this->$role_name)) {
            $this->$role_name = $this->create_user($role_name);
        }

        /**
         * @var mod_coursework_behat_login_page $login_page
         */
        $login_page = $this->get_page('login page');
        $login_page->load();
        $login_page->login($this->$role_name);
    }

    /**
     * This is really just a convenience method so that we can chain together the call to create the
     * course and this one, within larger steps.
     *
     * @Given /^the ([\w]+) user has been kept for later$/
     * @param $role_name
     */
    public function the_user_has_been_kept_for_later($role_name) {
        global $DB;

        $this->$role_name = $DB->get_record('user', array('username' => "user{$this->user_suffix}"));
    }

    /**
     * Role names might be fed through from another step that has already removed the spaces, so
     * make sure you add both options. Don't use a wildcard, as it causes collisions with other steps.
     *
     * @Given /^there is (a|another|an) (teacher|editing teacher|editingteacher|manager|student)$/
     * @param $other
     * @param $role_name
     * @throws coding_exception
     */
    public function thereIsAnotherTeacher($other, $role_name) {

        $other = ($other == 'another');

        $role_name = str_replace(' ', '', $role_name);

        $role_name_to_save = $other ? 'other_' . $role_name : $role_name;

        $this->$role_name_to_save = $this->create_user($role_name, $role_name_to_save);
    }

    /**
     * @param $role_name
     * @param string $display_name
     * @throws coding_exception
     * @return mixed|moodle_database|mysqli_native_moodle_database
     */
    protected function create_user($role_name, $display_name = '') {
        global $DB;

        $this->user_suffix++;

        $generator = testing_util::get_data_generator();

        $user = new stdClass();
        $user->username = 'user' . $this->user_suffix;
        $user->password = 'user' . $this->user_suffix;
        $user->firstname = $display_name ? $display_name : $role_name . $this->user_suffix;
        $user->lastname = $role_name . $this->user_suffix;
        $user = $generator->create_user($user);
        $user = \mod_coursework\models\user::find($user);
        $user->password = 'user' . $this->user_suffix;

        $role_id = $DB->get_field('role', 'id', array('shortname' => $role_name), MUST_EXIST);

        if (empty($this->course)) {
            throw new coding_exception('Must have a course to enrol the user onto');
        }

        $generator->enrol_user($user->id,
                               $this->course->id,
                               $role_id);

        return $user;
    }

    /**
     * @Given /^the student is a member of a group$/
     */
    public function iAmAMemberOfAGroup() {

        $generator = testing_util::get_data_generator();

        $group = new stdClass();
        $group->name = 'My group';
        $group->courseid = $this->course->id;
        $group = $generator->create_group($group);
        $this->group = group::find($group);


        $membership = new stdClass();
        $membership->groupid = $this->group->id;
        $membership->userid = $this->student->id;
        $generator->create_group_member($membership);
    }

    /**
     * @Given /^the other student is a member of the group$/
     */
    public function theOtherStudentIsAMemberOfTheGroup() {

        $generator = testing_util::get_data_generator();

        $membership = new stdClass();
        $membership->groupid = $this->group->id;
        $membership->userid = $this->other_student->id;
        $generator->create_group_member($membership);
    }

    /**
     * @Given /^I save everything$/
     */
    public function iSaveEverything() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->save_everything();
    }

    /**
     * @Given /^I should see the date when the individual feedback will be released$/
     */
    public function iSeeTheDateWhenIndividualFeedbackIsReleased() {
        /**
         * @var mod_coursework_behat_coursework_page $page
         */
        $page = $this->get_page('coursework page');
        assertTrue($page->individual_feedback_date_present());
    }

    /**
     * @Given /^I should not see the date when the individual feedback will be released$/
     */
    public function iDoNotSeeTheDateWhenIndividualFeedbackIsReleased() {
        /**
         * @var mod_coursework_behat_coursework_page $page
         */
        $page = $this->get_page('coursework page');
        assertFalse($page->individual_feedback_date_present());
    }

    /**
     * @Given /^I should not see the date when the general feedback will be released$/
     */
    public function iDoNotSeeTheDateWhenGeneralFeedbackIsReleased() {
        /**
         * @var mod_coursework_behat_coursework_page $page
         */
        $page = $this->get_page('coursework page');
        assertFalse($page->general_feedback_date_present());
    }

    /**
     * @Given /^I should see the date when the general feedback will be released$/
     */
    public function iDoSeeTheDateWhenGeneralFeedbackIsReleased() {
        /**
         * @var mod_coursework_behat_coursework_page $page
         */
        $page = $this->get_page('coursework page');
        assertTrue($page->general_feedback_date_present());
    }

    /**
     * @Then /^I should see the first initial assessors grade and comment$/
     */
    public function iShouldSeeTheFirstInitialAssessorsGradeAndComment() {
        /**
         * @var mod_coursework_behat_show_feedback_page $page
         */
        $page = $this->get_page('show feedback page');
        $page->set_feedback($this->get_initial_assessor_feedback_for_student());
        $page->should_have_comment('New comment here');
        $page->should_have_grade('67');
    }



    /**
     * @return mixed
     */
    protected function get_initial_assessor_feedback_for_student() {
        $submission_params = array('courseworkid' => $this->get_coursework()->id,
                                   'allocatableid' => $this->student->id,
                                   'allocatabletype' => 'user');
        $submission = submission::find($submission_params);

        $feedback_params = array(
            'stage_identifier' => 'assessor_1',
            'submissionid' => $submission->id,
        );
        $feedback = \mod_coursework\models\feedback::find($feedback_params);
        return $feedback;
    }

    /**
     * @param string $filename
     * @param string $file_path
     */
    private function open_screenshot($filename, $file_path) {
        if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
            exec('open -a "Preview.app" ' . $file_path.'/'.$filename);
        }
    }

    /**
     * @param string $file_and_path
     */
    private function open_html_page($file_and_path) {
        if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
            exec('open -a "Safari.app" ' . $file_and_path);
        }
    }

    /**
     * @Given /^I click show all students button$/
     */
    public function iClickOnShowAllStudentsButton() {
        //$this->find('id', "id_displayallstudentbutton")->click();
        $page = $this->get_page('coursework page');
       // $page->clickLink("Show submissions for other students");

        $page->show_hide_non_allocated_students();


    }


    /**
     *
     * @When /^I enable automatic sampling for stage ([1-3])$/
     *
     */
    public function IEnableAutomaticSamplingForStage($stage)   {

        $page = $this->get_page('allocations page');
        $page->enable_atomatic_sampling_for($stage);
    }


    /**
     * @Given /^I enable total rule for stage (\d+)$/
     *
     * @param $stage
     * @throws coding_exception
     */
    public function IEnableTotalRuleForStage($stage)  {
        $page = $this->get_page('allocations page');
        $page->enable_total_rule_for_stage($stage);
    }


    /**
     * @Given /^I add grade range rule for stage (\d+)$/
     *
     * @param $stage
     * @throws coding_exception
     */
    public function IAddGradeRangeRuleForStage($stage)  {
        $page = $this->get_page('allocations page');
        $page->add_grade_range_rule_for_stage($stage);
    }

    /**
     * @Given /^I enable grade range rule (\d+) for stage (\d+)$/
     *
     * @param $ruleno
     * @param $stage
     * @throws coding_exception
     */
    public function IEnableGradeRangeRuleForStage($ruleno, $stage)  {
        $ruleno = $ruleno - 1;
        $page = $this->get_page('allocations page');
        $page->enable_grade_range_rule_for_stage($stage, $ruleno);
    }


    /**
     * @Then  /^I select limit type for grade range rule (\d+) in stage (\d+) as "([\w]*)"$/
     *
     * @param $ruleno
     * @param $stage
     * @param $type
     * @throws coding_exception
     */
    public function ISelectLimitTypeForGradeRangeRuleInStageAs($ruleno, $stage, $type)  {
        $ruleno = $ruleno - 1;
        $page = $this->get_page('allocations page');
        $page->select_type_of_grade_range_rule_for_stage($stage, $ruleno, $type);
    }


    /**
     * @Then  /^I select "([\w]*)" grade limit for grade range rule (\d+) in stage (\d+) as "(\d+)"$/
     *
     * @param $range
     * @param $ruleno
     * @param $stage
     * @param $value
     * @throws coding_exception
     */
    public function ISelectGradeLimitTypeForGradeRangeRuleInStageAs($range, $ruleno, $stage, $value)  {
        $ruleno = $ruleno - 1;
        $page = $this->get_page('allocations page');
        $page->select_range_for_grade_range_rule_for_stage($range, $stage, $ruleno, $value);
    }


    /**
     * @Given /^I select (\d+)% of total students in stage (\d+)$/
     *
     * @param $percentage
     * @param $stage
     * @throws coding_exception
     */
    public function ISelectTotalSubmissionsInStage($percentage,$stage)   {
        $page = $this->get_page('allocations page');
        $page->select_total_percentage_for_stage($percentage,$stage);
    }

    /**
     * @Then /^(a|another) student( or another student)? should( not)? be automatically included in sample for stage (\d+)$/
     *
     * @param $stage
     * @throws coding_exception
     */
    public function StudentAutomaticallyIncludedInSampleForStage($other,$another,$negate,$stage)  {
        $page = $this->get_page('allocations page');
        $another = (!empty($another))? $this->other_student: '';
        $other = ($other == 'another');
        $student = $other ? 'other_student' : 'student';

        $page->automatically_included_in_sample($this->coursework,$this->$student,$another,$stage,$negate);
    }


    /**
     * @Given /^I save sampling strategy$/
     */
    public function iSaveSamplingStrategy() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->save_sampling_strategy();
    }

    /**
     * Take screenshot when step fails. Works only with Selenium2Driver.
     *
     * Screenshot is saved at [Date]/[Feature]/[Scenario]/[Step].jpg .
     *
     * @AfterStep
     * @param \Behat\Behat\Event\StepEvent $event
     */
//    public function takeScreenshotAfterFailedStep(Behat\Behat\Event\StepEvent $event) {
//        if ($event->getResult() === Behat\Behat\Event\StepEvent::FAILED) {
//
//            $step = $event->getStep();
//            $path = array(
//                'date' => date("Ymd-Hi"),
//                'feature' => $step->getParent()->getFeature()->getTitle(),
//                'scenario' => $step->getParent()->getTitle(),
//                'step' => $step->getType() . ' ' . $step->getText(),
//            );
//            $path = preg_replace('/[^\-\.\w]/', '_', $path);
//            $filename = implode($path);
//
//            $driver = $this->getSession()->getDriver();
//            if ($driver instanceof Behat\Mink\Driver\Selenium2Driver) {
//                $filename .= '_screenshot.jpg';
//                $this->show_me_a_screenshot($filename);
//            } else {
//                $filename .= '_page.html';
//                $this->show_me_the_page($filename);
//            }
//        }
//    }
}