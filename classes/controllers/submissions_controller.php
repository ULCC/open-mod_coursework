<?php

namespace mod_coursework\controllers;

use mod_coursework\ability;
use mod_coursework\event\assessable_submitted;
use mod_coursework\exceptions\access_denied;
use mod_coursework\exceptions\late_submission;
use mod_coursework\forms\student_submission_form;
use mod_coursework\mailer;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;

defined('MOODLE_INTERNAL' || die());

/**
 * Class submissions_controller
 * @package mod_coursework\controllers
 */
class submissions_controller extends controller_base {

    /**
     * @var submission
     */
    protected $submission;

    /**
     * Makes the page where a user can create a new submission.
     *
     * @throws \coding_exception
     * @throws \unauthorized_access_exception
     */
    protected function new_submission() {
        global $USER, $PAGE;

        $user = user::find($USER);
        $validation = false;

        $submission = submission::build(array(
                                                'allocatableid' => $this->params['allocatableid'],
                                                'allocatabletype' => $this->params['allocatabletype'],
                                                'courseworkid' => $this->coursework->id,
                                                'createdby' => $user->id()));

        $ability = new ability($user, $this->coursework);
        if (!$ability->can('new', $submission)) {
            throw new access_denied($this->coursework);
        }

        $this->check_coursework_is_open($this->coursework);

        $urlparams = array('courseworkid' => $this->params['courseworkid']);
        $PAGE->set_url('/mod/coursework/actions/submissions/new.php', $urlparams);

        $path = $this->get_router()->get_path('create submission', array('coursework' => $this->coursework));
        $submit_form = new student_submission_form($path,
                                                    array(
                                                      'coursework' => $this->coursework,
                                                      'submission' => $submission
                                                    ));
        if ($submit_form->is_submitted()) {
            $validation =  $submit_form->validate_defined_fields();
        }
        if ($validation != true) {
            $this->get_page_renderer()->new_submission_page($submit_form, $submission);
            return true;
        }

    }

    /**
     * Receives the form input from the new submission page and saves it.
     */
    protected function create_submission() {
        global $USER, $CFG, $DB;

        $coursework_page_url = $this->get_path('coursework', array('coursework' => $this->coursework));
        if ($this->cancel_button_was_pressed()) {
            redirect($coursework_page_url);
        }

        $validated = $this->new_submission();

        if ($validated == true){
            return;
        }

        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->finalised = $this->params['finalised'] ? 1 : 0;
        $submission->allocatableid = $this->params['allocatableid'];
        $submission->createdby = $USER->id;
        $submission->lastupdatedby = $USER->id;
        $submission->allocatabletype = $this->params['allocatabletype'];
        $submission->authorid   = $submission->get_author_id(); //create new function to get the author id depending on whether the current user is submitting on behalf
        $submission->timesubmitted = time();


       // Automatically finalise any submissions that's past the deadline/personal deadline and doesn't have valid extension
        if($this->coursework->personal_deadlines_enabled()){
            // Check is submission has a valid personal deadline or a valid extension
              if (!$this->has_valid_personal_deadline($submission) && !$this->has_valid_extension($submission)) {
                  $submission->finalised = 1;
              }
        } elseif($this->coursework->deadline_has_passed() && !$this->has_valid_extension($submission)){
                  $submission->finalised = 1;
        }

        $ability = new ability(user::find($USER), $this->coursework);

        $this->exception_if_late($submission);

        if (!$ability->can('create', $submission)) {
            // if submission already exists, redirect user to cw instead of throwing an error
            if ($this->submissions_exists($submission)){
                redirect($CFG->wwwroot.'/mod/coursework/view.php?id='.$this->coursemodule->id, $ability->get_last_message());
            } else {
                throw new access_denied($this->coursework, $ability->get_last_message());
            }
        }

        $this->check_coursework_is_open($this->coursework);

        $submission->save();

        $files_id = file_get_submitted_draft_itemid('submission_manager');
        $submission->save_files($files_id);

        $context = \context_module::instance($this->coursemodule->id);
        // Trigger assessable_submitted event to show files are complete.
        $params = array(
            'context' => $context,
            'objectid' => $submission->id,
            'other' => array(
                'courseworkid' => $this->coursework->id
            )
        );
        $event = assessable_submitted::create($params);
        $event->trigger();

        $submission->submit_plagiarism();

        $mailer = new mailer($this->coursework);
        if ($CFG->coursework_allsubmissionreceipt || $submission->finalised) { 
            foreach ($submission->get_students() as $student) {
                $mailer->send_submission_receipt($student, $submission->finalised);
            }
        }

         if ($submission->finalised) {
            if (!$submission->get_coursework()->has_deadline()) {

                $userids  =   explode(',',$submission->get_coursework()->get_submission_notification_users());

                if (!empty($userids)) {
                    foreach($userids as $u)   {
                        $notifyuser   = $DB->get_record('user',array('id'=>trim($u)));

                        if (!empty($notifyuser))   $mailer->send_submission_notification($notifyuser);
                    }
                }



            }

        }

        redirect($coursework_page_url);
    }

    /**
     * Makes the page where a user can edit an existing submission.
     * Might be someone editing the group feedback thing too, so we load based on the submission
     * user, not the current user.
     *
     * @throws \coding_exception
     * @throws \unauthorized_access_exception
     */
    protected function edit_submission() {
        global $USER, $PAGE;

        $submission = submission::find($this->params['submissionid']);
        $validation = false;

        $ability = new ability(user::find($USER), $this->coursework);
        if (!$ability->can('edit', $submission)) {
            throw new access_denied($this->coursework);
        }

        $urlparams = array('submissionid' => $this->params['submissionid']);
        $PAGE->set_url('/mod/coursework/actions/submissions/edit.php', $urlparams);

        $path = $this->get_router()->get_path('update submission', array('submission' => $submission));
        $submit_form = new student_submission_form($path,
                                                   array(
                                                       'coursework' => $this->coursework,
                                                       'submission' => $submission
                                                   ));
        if ($submit_form->is_submitted()) {
            $validation =   $submit_form->validate_defined_fields();
        }

        $submit_form->set_data($submission);

         if ($validation != true) {
            $this->get_page_renderer()->edit_submission_page($submit_form, $submission);
            return true;
         }
  }


    /**
     *
     */
    protected function update_submission() {

        global $USER, $CFG;

        $coursework_page_url = $this->get_path('coursework', array('coursework' => $this->coursework));
        if ($this->cancel_button_was_pressed()) {
            redirect($coursework_page_url);
        }

         $validated =    $this->edit_submission();

        if ($validated == true){
            return;
        }

        $submission = submission::find($this->params['submissionid']);

        $ability = new ability(user::find($USER), $this->coursework);
        $this->exception_if_late($submission);
        if (!$ability->can('update', $submission)) {
            throw new access_denied($this->coursework, $ability->get_last_message());
        }

        $notify_about_finalisation = false;
        $incoming_finalised_setting = $this->params['finalised'] ? 1 : 0;
        if ($incoming_finalised_setting == 1 && $submission->finalised == 0) {
            $notify_about_finalisation = true;
        }
        $submission->finalised = $incoming_finalised_setting;
        $submission->lastupdatedby = $USER->id;
        $submission->timesubmitted = time();

        $submission->save();

        $files_id = file_get_submitted_draft_itemid('submission_manager');
        $submission->save_files($files_id);

        $context = \context_module::instance($this->coursemodule->id);
        // Trigger assessable_submitted event to show files are complete.
        $params = array(
            'context' => $context,
            'objectid' => $submission->id,
            'other' => array(
                'courseworkid' => $this->coursework->id
            )
        );
        $event = assessable_submitted::create($params);
        $event->trigger();


        $submission->submit_plagiarism();

        if ($CFG->coursework_allsubmissionreceipt || $notify_about_finalisation) {
            $mailer = new mailer($this->coursework);
            foreach ($submission->get_students() as $student) {
                $mailer->send_submission_receipt($student, $notify_about_finalisation);
            }
        }

        redirect($coursework_page_url);

    }

    /**
     *
     */
    protected function finalise_submission() {

        global $USER;

        $coursework_page_url = $this->get_path('coursework', array('coursework' => $this->coursework));
        if ($this->cancel_button_was_pressed()) {
            redirect($coursework_page_url);
        }

        $submission = submission::find($this->params['submissionid']);

        $ability = new ability(user::find($USER), $this->coursework);
        if (!$ability->can('finalise', $submission)) {
            throw new access_denied($this->coursework);
        }

        $submission->finalised = 1;
        $submission->save();

        // Email the user. Best to do this as an event after 2.7 so as to keep the page fast.
        $mailer = new mailer($this->coursework);
        foreach ($submission->get_students() as $student) {
            $mailer->send_submission_receipt($student, true);
        }

        redirect($coursework_page_url);
    }


    protected function unfinalise_submission()  {

        global  $USER, $DB;



        $allocatableids     =   (!is_array($this->params['allocatableid']))  ?  array($this->params['allocatableid']) : $this->params['allocatableid']  ;

        $personaldeadline_page_url = new \moodle_url('/mod/coursework/actions/personal_deadline.php',
            array('id'=>$this->coursework->get_coursemodule_id(),'multipleuserdeadlines'=>1,'setpersonaldeadlinespage'=>1,
                'courseworkid'=>$this->params['courseworkid'],'allocatabletype'=>$this->params['allocatabletype']));

        $changedeadlines    =   false;

        foreach($allocatableids as $aid) {

            $submission_db = $DB->get_record('coursework_submissions',
                array('courseworkid' => $this->params['courseworkid'], 'allocatableid' => $aid, 'allocatabletype' => $this->params['allocatabletype']));
            if (!empty($submission_db)) {
                $submission = \mod_coursework\models\submission::find($submission_db);
                       
                if ($submission->can_be_unfinalised()) {
                    $submission->finalised = 0;
                    $submission->save();
                    $personaldeadline_page_url->param("allocatableid_arr[$aid]",$aid);
                    $changedeadlines    =   true;
                }
            }

        }



        if (!empty($changedeadlines)) {
            redirect($personaldeadline_page_url, get_string('unfinalisedchangesubmissiondate', 'mod_coursework'));
        } else {
            $setpersonaldeadline_page_url = new \moodle_url('/mod/coursework/actions/set_personal_deadlines.php',
                array('id'=>$this->coursework->get_coursemodule_id()));
            redirect($setpersonaldeadline_page_url);
        }

    }

    protected function prepare_environment() {

        if (!empty($this->params['submissionid'])) {
            $this->submission = submission::find($this->params['submissionid']);
            $this->coursework = $this->submission->get_coursework();

        }

        parent::prepare_environment();
    }

    /**
     * Tells us whether the agree to terms checkbox was used.
     *
     * @return bool
     * @throws \coding_exception
     */
    private function terms_were_agreed_to() {
        return !!optional_param('termsagreed', false, PARAM_INT);
    }

    /**
     * @param coursework $coursework
     * @throws \coding_exception
     * @throws access_denied
     */
    protected function check_coursework_is_open($coursework) {
        if (!$coursework->start_date_has_passed()) {
            throw new access_denied($coursework, get_string('notstartedyet', 'mod_coursework', userdate($coursework->startdate)));
        }
    }

    /**
     * @throws late_submission
     */
    private function exception_if_late($submission) {
        $could_have_submitted = has_capability('mod/coursework:submit', $this->coursework->get_context());
       if ($this->coursework->personal_deadlines_enabled()){
           $deadline_has_passed = !$this->has_valid_personal_deadline($submission);
       } else {
           $deadline_has_passed = $this->coursework->deadline_has_passed();
       }

        if ($could_have_submitted && $deadline_has_passed && !$this->has_valid_extension($submission) && !$this->coursework->allow_late_submissions()) {
            throw new late_submission($this->coursework);
        }
    }

    /*
     * param submission $submission
     * return bool true if a matching record exists, else false
     */
    protected function submissions_exists($submission){
        global $DB;

        $sub_exists = $DB->record_exists('coursework_submissions',
                                     array('courseworkid' => $submission->courseworkid,
                                           'allocatableid' => $submission->allocatableid,
                                           'allocatabletype' => $submission->allocatabletype));

        return $sub_exists;
    }


    /**
     * @param $submission
     * @return bool
     */
    protected function has_valid_extension($submission){
        global $DB;

        $valid_extension = false;
        $extension = $DB->get_record('coursework_extensions',
                                         array('courseworkid' => $submission->courseworkid,
                                               'allocatableid' => $submission->allocatableid,
                                               'allocatabletype' => $submission->allocatabletype));

        if ($extension) {
            if ($extension->extended_deadline > time()) {
                $valid_extension = true;
            }
        }

        return $valid_extension;
    }

    /**
     * @param $submission
     * @return bool
     */
    protected function has_valid_personal_deadline($submission){
        global $DB;

        $valid_personal_deadline = false;
        $personal_deadline = $DB->get_record('coursework_person_deadlines',
                                            array('courseworkid' => $submission->courseworkid,
                                                  'allocatableid' => $submission->allocatableid,
                                                  'allocatabletype' => $submission->allocatabletype));
        if ($personal_deadline) {
            if ($personal_deadline->personal_deadline > time()) {
                $valid_personal_deadline = true;
            }
        } else {
            $valid_personal_deadline = !$this->coursework->deadline_has_passed();
        }
        return $valid_personal_deadline;
    }
}