<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/tests/behat/pages/page_base.php');

use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\ElementNotFoundException;

/**
 * Holds the functions that know about the HTML structure of the student page.
 *
 *
 */
class mod_coursework_behat_student_page extends mod_coursework_behat_page_base {

    public function should_have_two_submission_files() {

        $files = $this->getPage()->findAll('css', '.submissionfile');
        $number_of_files = count($files);

        $expected_number_of_files = 2;
        if (!$number_of_files == $expected_number_of_files) {
            $message = 'Expected 2 submission files but there were ' . $number_of_files;
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @param int $expected_number_of_files
     * @throws ExpectationException
     */
    public function should_have_number_of_feedback_files($expected_number_of_files) {

        $files = $this->getPage()->findAll('css', '.feedbackfile');
        $number_of_files = count($files);

        if (!$number_of_files == $expected_number_of_files) {
            $message = 'Expected '.$expected_number_of_files.' feedback files but there were ' . $number_of_files;
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @param $rolename
     */
    public function should_show_the_submitter_as($rolename) {
        $submission_user_cell = $this->getPage()->find('css', 'td.submission-user');
        $cell_contents = $submission_user_cell->getText();
        $student_name = fullname($this->getContext()->$rolename);
        assertContains($student_name, $cell_contents, "Expected the submission to have been made by {$student_name}, but got {$cell_contents}");
    }

    /**
     * @param mixed $grade
     */
    public function should_have_visible_grade($grade) {
        // final_feedback_grade
        $final_grade_cell = $this->getPage()->find('css', '#final_feedback_grade');
        $cell_contents = $final_grade_cell ? $final_grade_cell->getText() : false;
        assertEquals($grade,
                       $cell_contents,
                       "Expected the final grade to be '{$grade}', but got '{$cell_contents}'");
    }

    /**
     * @param $feedback_text
     */
    public function should_have_visible_feedback($feedback_text) {
        // final_feedback_grade
        $final_grade_cell = $this->getPage()->find('css', '#final_feedback_comment');
        $cell_contents = $final_grade_cell->getText();
        assertEquals($feedback_text,
                     $cell_contents,
                     "Expected the final feedback comment to be '{$feedback_text}', but got '{$cell_contents}'");
    }

    public function click_on_the_edit_submission_button() {
       $locator = "//div[@class='editsubmissionbutton']";
       $this->pressButtonXpath($locator);
    }

    public function click_on_the_finalise_submission_button() {
        $locator = "//div[@class='finalisesubmissionbutton']";
        $this->pressButtonXpath($locator);
    }

    public function click_on_the_new_submission_button() {
        $locator = "//div[@class='newsubmissionbutton']";
        $this->pressButtonXpath($locator);
    }

    public function should_not_have_a_finalise_button() {
        $buttons = $this->getPage()->findAll('css', '.finalisesubmissionbutton');
        assertEmpty($buttons);
    }

    public function click_on_the_save_submission_button() {
        $locator = "//div[@class='newsubmissionbutton']";
        $this->pressButtonXpath($locator);
    }
}