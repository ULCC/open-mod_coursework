<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

use coding_exception;
use context_module;
use file_storage;
use html_writer;
use mod_coursework\ability;
use mod_coursework\mailer;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use moodle_url;
use moodleform;
use stdClass;
use stored_file;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Allows files to be submitted by the student
 */
class student_submission_form extends moodleform {

    /**
     * Defines the form structure
     *
     * @return void
     */
    public function definition() {

        $this->add_hidden_elements_to_form();

        // if TII plagiarism enabled check if user agreed/disagreed EULA
        if(!$this->get_coursework()->plagiarism_enbled() || has_user_seen_tii_EULA_agreement()) {

            $this->add_header_to_form();

            $this->add_instructions_to_form();

            $this->add_file_manager_to_form();

            if ($this->students_are_required_to_agree_to_terms()) {
                $this->add_agree_terms_elements_to_form();
            }

            $this->add_submit_buttons_to_form();
        } else {
            $plagdisclosure = plagiarism_similarity_information($this->get_coursework()->get_course_module());
            $p = html_writer::tag('p', $plagdisclosure );
            $this->_form->addElement('html', $p);
        }

    }

    /**
     * @throws coding_exception
     * @internal param \coursework $coursework
     * @internal param \submission $submission
     * @return void
     */
    public function handle() {

        global $DB, $CFG, $USER;

        /* @var coursework $coursework */
        $coursework = $this->get_coursework();
        if (!$coursework) {
            throw new coding_exception('submit form needs a coursework instance in its custom data');
        }
        /* @var submission $submission */
        $submission = $this->get_submission();
        if (!$submission) {
            throw new coding_exception('submit form needs a submission instance in its custom data');
        }

        $cmid = $coursework->get_coursemodule_id();
        $link = $CFG->wwwroot.'/mod/coursework/view.php?id='.$cmid;

        if ($this->is_cancelled()) {
            redirect(new moodle_url('/mod/coursework/view.php', array('id' => $cmid)));
        }

        $data = $this->get_data();
        if (!$data) {
            return;
        }

        if ($submission->get_state() < submission::FINALISED && $coursework->allowed_to_submit()) {
            // Needed in case of new record.
            $submission->courseworkid = $coursework->id;
            $submission->createdby = $USER->id;
            $submission->lastupdatedby = $USER->id;
            $submission->submission_files = $data->submission_manager;
            $submission->manualsrscode = isset($data->manualsrscode) ? $data->manualsrscode : '';
            $submission->save(); // Get an id.

            // Once the submission is saved we can check whether this included any submitted files.
            $context = context_module::instance($cmid);
            $fs = get_file_storage();
            $existingfiles = $fs->get_area_files($context->id, 'mod_coursework',
                                                 'submission', $submission->id, "id", false);
            $filecount = count($existingfiles);

            if (!isset($data->finalisebutton)) {
                if ($filecount > 0) { // Only send to plagiarism if there is a file.
                    $submission->submit_plagiarism('draft');
                    $message = get_string('changessaved');
                    redirect($link, $message);
                }
            } else { // Finalise it!

                if ($filecount > 0) { // Check that there is a file before updating to finalised.
                    // Confirm finalise state.
                    $submission->finalised = 1;
                    $submission->save();

                    // Rename the uploaded file to the submission hash.
                    /* @var stored_file $file */
                    $file = $submission->get_first_submitted_file();
                    $file->get_id();

                    $file_information = new stdClass();
                    $file_information->id = $file->get_id();

                    // Some files may have more thn one dot in the name. This makes sure we get the chunk after
                    // the last dot.
                    $bits = pathinfo($file->get_filename());
                    $extension = $bits['extension'];
                    // Can we get an empty extension? Just in case...
                    if (!empty($extension)) {
                        $extension = '.'.$extension;
                    }
                    $file_information->filename = $coursework->get_username_hash($submission->userid).$extension;

                    $pathnamehash = file_storage::get_pathname_hash($file->get_contextid(),
                                                                    $file->get_component(),
                                                                    $file->get_filearea(),
                                                                    $file->get_itemid(),
                                                                    $file->get_filepath(),
                                                                    $file_information->filename);

                    $file_information->pathnamehash = $pathnamehash;

                    $DB->update_record('files', $file_information);

                    // Force submission to update file record.
                    $submission->submission_files = null;
                    $submission->get_submission_files(true);

                    if (!$submission->get_coursework()->has_deadline()) {

                        $userids  =   explode(',',$submission->get_coursework()->get_submission_notification_users());

                        if (!empty($userids)) {
                            foreach($userids as $u)   {
                                $notifyuser   = $DB->get_record('user',array('id'=>trim($u)));
                                $mailer = new mailer($coursework);

                                if (!empty($notifyuser))   $mailer->send_submission_notification($notifyuser);
                          }
                        }



                    }

                    // Must happen AFTER file attributes have been fiddled with, otherwise we get
                    // the wrong pathnamehash and turnitin can't find it.
                    $submission->submit_plagiarism('final');
                    if ($USER->id == $submission->userid) {
                        $message = get_string('changessaved');
                    } else {
                        $message = get_string('changessavedemail', 'mod_coursework');
                    }
                    redirect($link, $message);

                } else if ($filecount == 0) {
                    $message = get_string('nofinalfile', 'coursework');
                    redirect($link, $message);
                }

            }

            if ($CFG->coursework_allsubmissionreceipt || $data->finalisebutton) {
                // send the receipts to students
                $students_who_need_a_receipt = $submission->get_students();
                $mailer = new mailer($coursework);

                foreach ($students_who_need_a_receipt as $student) {
                    $mailer->send_submission_receipt($student, $data->finalisebutton);
                }
            }

        } else { // Feedback already exists, or already finalised - allow no changes.
            if (!$coursework->allowed_to_submit()) {
                $message = get_string('latesubmissionsnotallowed', 'mod_coursework');
            } else {
                $message = $submission->get_state() == submission::FINALISED
                        ? get_string('finalisedlocked', 'coursework') :
                        get_string('feedbacklocked', 'coursework');
            }
            redirect($link, $message);
        }
    }


    /**
     * Sets the data, tweaking the submission to conform to the form's field names
     *
     * @param submission $submission
     */
    public function set_data($submission) {

        // The files area needs to be called 'submission' in the form for the plagiarism thing to
        // handle it properly. Looks weird in the DB though.

        // Get any files that were previously submitted. This fetches an itemid from the $_GET
        // params.
        $draft_item_id = file_get_submitted_draft_itemid('submission_manager');
        // Put them into a draft area.
        file_prepare_draft_area($draft_item_id,
                                $this->get_coursework()->get_context_id(),
                                'mod_coursework',
                                'submission',
                                $this->get_submission()->id,
                                $this->get_coursework()->get_file_options());
        // Load that area into the form.
        $submission->submission_manager = $draft_item_id;

        $data = new stdClass();
        $data->submission_manager = $draft_item_id;
        $data->courseworkid = $this->get_coursework()->id;
        $data->userid = $submission->userid;
        $data->submissionid = $this->get_submission()->id;

        parent::set_data($data);

    }


    /**
     * @return coursework
     */
    protected function get_coursework() {
        return $this->_customdata['coursework'];
    }

    /**
     * @return submission
     */
    protected function get_submission() {
        return $this->_customdata['submission'];
    }

    /**
     * @throws \coding_exception
     */
    protected function add_agree_terms_elements_to_form() {
        global $CFG;

        $terms_html = '';
        $terms_html .= html_writer::start_tag('h4');
        $terms_html .= get_string('youmustagreetotheterms', 'mod_coursework');
        $terms_html .= html_writer::end_tag('h4');
        $terms_html .= $CFG->coursework_agree_terms_text;
        $this->_form->addElement('html', $terms_html);
        $this->_form->addElement('checkbox', 'termsagreed', get_string('iagreetotheterms', 'mod_coursework'));
        $this->_form->setType('termsagreed', PARAM_INT);
        $this->_form->addRule('termsagreed', null, 'required');
    }

    /**
     * @throws \coding_exception
     */
    protected function add_submit_buttons_to_form() {
        global $USER;

        $ability = new ability(user::find($USER), $this->get_coursework());

        $button_array = array();
        // If submitting on behalf of someone else, we want to make sure that we don't have people leaving it in a draft
        // state because the reason for doing submit on behalf of in the first place is that the student cannot use the
        // interface themselves, so they are unable to come back later to finalise it themselves.
        if (($ability->can('create', $this->get_submission()) || $ability->can('update', $this->get_submission()))
            &&  $this->get_submission()->get_coursework()->has_deadline() ) {
            $button_array[] = $this->_form->createElement('submit', 'submitbutton', get_string('submit'));
        }
        if ($ability->can('finalise', $this->get_submission())) {
            $button_array[] =
                $this->_form->createElement('submit', 'finalisebutton', get_string('submitandfinalise', 'coursework'));
        }
        $button_array[] = $this->_form->createElement('cancel');
        $this->_form->addGroup($button_array, 'buttonar', '', array(' '), false);
        $this->_form->closeHeaderBefore('buttonar');
    }

    /**
     * @return bool
     */
    protected function students_are_required_to_agree_to_terms() {
        global $CFG;
        return !empty($CFG->coursework_agree_terms);
    }

    /**
     */
    protected function add_hidden_elements_to_form() {
        $submission = $this->get_submission();

        $this->_form->addElement('hidden', 'courseworkid', $this->get_coursework()->id);
        $this->_form->setType('courseworkid', PARAM_INT);
        $this->_form->addElement('hidden', 'userid', $submission->userid);
        $this->_form->setType('userid', PARAM_INT);

        $this->_form->addElement('hidden', 'submissionid', $submission->id);
        $this->_form->setType('submissionid', PARAM_INT);

        $this->_form->addElement('hidden', 'allocatableid', $submission->allocatableid);
        $this->_form->setType('allocatableid', PARAM_INT);

        $this->_form->addElement('hidden', 'allocatabletype', $submission->allocatabletype);
        $this->_form->setType('allocatabletype', PARAM_ALPHANUMEXT);
    }

    /**
     * @throws \coding_exception
     */
    protected function add_file_manager_to_form() {
        $uploadfilestring = get_string('uploadafile');
        $this->_form->addElement('filemanager',
                                 'submission_manager',
                                 $uploadfilestring,
                                 null,
                                 $this->get_file_manager_options());
        $this->_form->addRule('submission_manager', 'You must upload file(s) into the box below before you can save', 'required', null,'server',false,true);

    }

    /**
     * @throws \coding_exception
     */
    protected function add_instructions_to_form() {
        $file_manager_options = $this->get_file_manager_options();

        $usernamehash = $this->get_coursework()->get_username_hash($this->get_submission()->userid);
        $filerenamestring = get_string('file_rename', 'coursework', $usernamehash);
        $filerenamestring .= $this->make_plagiarism_instructions();
        $filerenamestring .= html_writer::empty_tag('br');
        if ($file_manager_options['accepted_types'] != '*') {
            $filerenamestring .= 'Allowed file types: ' . implode(' ', $file_manager_options['accepted_types']);
        } else {
            $filerenamestring .= 'All file types are allowed.';
        }
        $p = html_writer::tag('p', $filerenamestring);
        $this->_form->addElement('html', $p);
    }

    /**
     * @throws \coding_exception
     */
    protected function add_header_to_form() {
        $file_manager_options = $this->get_file_manager_options();

        $files_string = ($file_manager_options['maxfiles'] == 1) ? 'yoursubmissionfile_upload'
            : 'yoursubmissionfiles';

        $this->_form->addElement('header', 'submitform', get_string($files_string, 'coursework'));
    }

    /**
     * @return array
     */
    protected function get_file_manager_options() {
        $file_manager_options = $this->get_coursework()->get_file_options();
        return $file_manager_options;
    }

    /**
     * @return string
     */
    protected function make_plagiarism_instructions() {
        $plagiarism_helpers = $this->get_coursework()->get_plagiarism_helpers();
        $plagiarism_instructions = array();
        foreach ($plagiarism_helpers as $helper) {
            if ($helper->file_submission_instructions()) {
                $plagiarism_instructions[] = $helper->file_submission_instructions();
            }
        }
        $plagiarism_instructions = implode(' ', $plagiarism_instructions);
        if ($plagiarism_instructions) {
            $plagiarism_instructions = '<br>' . $plagiarism_instructions;
            return $plagiarism_instructions;
        }
        return $plagiarism_instructions;
    }
}
