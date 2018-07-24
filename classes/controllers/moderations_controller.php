<?php

namespace mod_coursework\controllers;

use mod_coursework\ability;
use mod_coursework\exceptions\access_denied;
use mod_coursework\forms\moderator_agreement_mform;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\models\moderation;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL' || die());

global $CFG;

require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/mod/coursework/renderer.php');

/**
 * Class mod_coursework_controller controls the page generation for all of the pages in the coursework module.
 *
 * It is the beginning of the process of tidying things up to make them a bit more MVC where possible.
 *
 */
class moderations_controller extends controller_base {

    /**
     * @var moderation
     */
    protected $moderation;


    /**
     * This deals with the page that the assessors see when they want to add component feedbacks.
     *
     * @throws \moodle_exception
     */
    protected function new_moderation() {

        global $PAGE, $USER;

        $moderator_agreement = new moderation();
        $moderator_agreement->submissionid = $this->params['submissionid'];
        $moderator_agreement->moderatorid = $this->params['moderatorid'];
        $moderator_agreement->stage_identifier = $this->params['stage_identifier'];
        $moderator_agreement->courseworkid = $this->params['courseworkid'];
        $moderator_agreement->feedbackid = $this->params['feedbackid'];


        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('new', $moderator_agreement);

        $this->check_stage_permissions($this->params['stage_identifier']);

        $urlparams = array();
        $urlparams['submissionid'] = $moderator_agreement->submissionid;
        $urlparams['moderatorid'] = $moderator_agreement->moderatorid;
        $urlparams['stage_identifier'] = $moderator_agreement->stage_identifier;
        $urlparams['feedbackid'] = $moderator_agreement->feedbackid;
        $PAGE->set_url('/mod/coursework/actions/moderations/new.php', $urlparams);

        $renderer = $this->get_page_renderer();
        $renderer->new_moderation_page($moderator_agreement);

    }

    /**
     * This deals with the page that the assessors see when they want to add component moderations.
     *
     * @throws moodle_exception
     */
    protected function edit_moderation() {

        global $DB, $PAGE, $USER;


        $moderation = new moderation($this->params['moderationid']);
        $this->check_stage_permissions($moderation->stage_identifier);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $moderation);

        $urlparams = array('moderationid' => $this->params['moderationid']);
        $PAGE->set_url('/mod/coursework/actions/moderations/edit.php', $urlparams);

        $moderator = $DB->get_record('user', array('id' => $moderation->moderatorid));
        if (!empty($moderation->lasteditedby)) {
            $editor = $DB->get_record('user', array('id' => $moderation->lasteditedby));
        } else {
            $editor = $moderator;
        }

        $renderer = $this->get_page_renderer();
        $renderer->edit_moderation_page($moderation, $moderator, $editor);
    }

    /**
     * Saves the new feedback form for the first time.
     */
    protected function create_moderation() {

        global $USER, $PAGE, $CFG;

        $this->check_stage_permissions($this->params['stage_identifier']);

        $moderatoragreement = new moderation();
        $moderatoragreement->submissionid = $this->params['submissionid'];
        $moderatoragreement->moderatorid = $this->params['moderatorid'];
        $moderatoragreement->stage_identifier = $this->params['stage_identifier'];
        $moderatoragreement->lasteditedby = $USER->id;
        $moderatoragreement->feedbackid = $this->params['feedbackid'];

        $submission = submission::find($this->params['submissionid']);
        $path_params = array(
            'submission' => $submission,
            'moderator' => \core_user::get_user($this->params['moderatorid']),
            'stage' => 'moderator',
        );
        $url = $this->get_router()->get_path('new moderation', $path_params, true);
        $PAGE->set_url($url);


        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('new', $moderatoragreement);

        $form = new moderator_agreement_mform(null, array('moderation' => $moderatoragreement));

        $coursework_page_url = $this->get_path('coursework', array('coursework' => $moderatoragreement->get_coursework()));
        if ($form->is_cancelled()) {
            redirect($coursework_page_url);
        }

        $data = $form->get_data();

        if ($data) {
            $moderatoragreement = $form->process_data($moderatoragreement);
            $moderatoragreement->save();

            redirect($coursework_page_url);
        } else {
            $renderer = $this->get_page_renderer();
            $renderer->new_moderation_page($moderatoragreement);
        }
    }

    /**
     * Saves the new feedback form for the first time.
     */
    protected function update_moderation() {

        global $USER, $CFG;

        $moderatoragreement = new moderation($this->params['moderationid']);
        $moderatoragreement->lasteditedby = $USER->id;

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $moderatoragreement);

        $this->check_stage_permissions($moderatoragreement->stage_identifier);

        $form = new moderator_agreement_mform(null, array('moderation' => $moderatoragreement));

        $coursework_page_url = $this->get_path('coursework', array('coursework' => $moderatoragreement->get_coursework()));
        if ($form->is_cancelled()) {
            redirect($coursework_page_url);
        }

        $moderatoragreement = $form->process_data($moderatoragreement);
        $moderatoragreement->save();

        redirect($coursework_page_url);
    }

    /**
     * Shows the moderation as 'view only'
     *
     * @throws \coding_exception
     * @throws access_denied
     */
    protected function show_moderation() {
        global $PAGE, $USER;

        $urlparams = array('moderationid' => $this->params['moderationid']);
        $PAGE->set_url('/mod/coursework/actions/moderations/show.php', $urlparams);

        $moderation = new moderation($this->params['moderationid']);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('show', $moderation);

        $renderer = $this->get_page_renderer();
        $renderer->show_moderation_page($moderation);
    }


    /**
     * Get any feedback-specific stuff.
     */
    protected function prepare_environment() {
        global $DB;

        if (!empty($this->params['feedbackid'])) {
            $feedback = $DB->get_record('coursework_feedbacks',
                array('id' => $this->params['feedbackid']),
                '*',
                MUST_EXIST);
            $this->feedback = new feedback($feedback);
            $this->params['courseworkid'] = $this->feedback->get_coursework()->id;
        }

        if (!empty($this->params['submissionid'])) {
            $submission = $DB->get_record('coursework_submissions',
                array('id' => $this->params['submissionid']),
                '*',
                MUST_EXIST);
            $this->submission = submission::find($submission);
            $this->params['courseworkid'] = $this->submission->courseworkid;
        }

        if (!empty($this->params['moderationid'])) {
            $moderation = $DB->get_record('coursework_mod_agreements',
                array('id' => $this->params['moderationid']),
                '*',
                MUST_EXIST);
            $this->moderation = moderation::find($moderation);
            $this->params['courseworkid'] = $this->moderation->get_coursework()->id;
        }

        parent::prepare_environment();
    }

    /**
     * @param string $identifier
     * @throws access_denied
     */
    protected function check_stage_permissions($identifier) {
        global $USER;

        $stage = $this->coursework->get_stage($identifier);
        if (!$stage->user_is_moderator($USER)) {
            if (!(has_capability('mod/coursework:moderate', $this->coursework->get_context()) ) ){
                throw new access_denied($this->coursework, 'You are not authorised to moderte this feedback');
            }
        }
    }
}