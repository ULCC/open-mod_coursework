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
 * The main coursework module configuration form. Presented to the user when they make a new
 * instance of this module
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/coursework/lib.php');

/**
 * Mod form that allows a new coursework to ber created, or for the settings of an existing one to be altered.
 */
class mod_coursework_mod_form extends moodleform_mod {

    private function form() {
        return $this->_form;
    }

    /**
     */
    public function definition() {

        global $PAGE, $CFG;

        $PAGE->requires->jquery();

        $module = array(
            'name' => 'mod_coursework',
            'fullpath' => '/mod/coursework/mod_form.js',
            'requires' => array(
                'node',
                'ajax'
            ));

        $PAGE->requires->js_init_call('M.mod_coursework.init', array(), true, $module);

        $this->set_form_attributes();

        $this->add_general_header();

        $this->add_name_field();
        $this->standard_intro_elements(get_string('description', 'coursework'));


        $this->add_availability_header();

        $this->add_start_date_field();
        $this->add_submission_deadline_field();
        $this->add_personal_deadline_field();

        
       // if (coursework_is_ulcc_digest_coursework_plugin_installed()) {
            $this->add_marking_deadline_field();
            $this->add_initial_marking_deadline_field();
            $this->add_agreed_grade_marking_deadline_field();
            $this->add_relative_initial_marking_deadline_field();
            $this->add_relative_agreed_grade_marking_deadline_field();
       // }

        $this->add_allow_early_finalisation_field();
        $this->add_allow_late_submissions_field();


        if (coursework_is_ulcc_digest_coursework_plugin_installed()) {
            $this->add_digest_header();
            $this->add_marking_reminder_warning();
            $this->add_marking_reminder_field();

        }


        $this->add_submissions_header();

        $this->add_turnitin_files_settings_waring();
        $this->add_file_types_field();
        $this->add_max_file_size_field();
        $this->add_number_of_files_field();
        $this->add_rename_file_field();
        $this->add_submission_notification_field();
        $this->add_enable_plagiarism_flag_field();

        $this->add_marking_workflow_header();

        $this->add_number_of_initial_assessors_field();
        $this->add_enable_moderation_agreement_field();

        $this->add_enable_allocation_field();
        $this->add_assessor_allocation_strategy_field();
        $this->add_enable_sampling_checkbox();
        $this->add_automatic_agreement_enabled();
        $this->add_view_initial_assessors_grade();
        $this->add_enable_agreed_grade_delay();
        $this->add_save_feedback_as_draft();
        $this->add_auto_populate_agreed_feedback_comments();

        $this->add_blind_marking_header();

        $this->add_enable_blind_marking_field();

        $this->add_assessor_anonymity_header();

        $this->add_enable_assessor_anonymity_field();


        $this->add_feedback_header();

        $this->add_general_feedback_release_date_field();
        $this->add_individual_feedback_release_date_field();
        $this->add_email_individual_feedback_notification_field();
        $this->add_all_feedbacks_field();


        $this->add_extensions_header();

        $this->add_enable_extensions_field();

        $this->add_group_submission_header();

        $this->add_use_groups_field();
        $this->add_grouping_field();


        $this->standard_grading_coursemodule_elements();
        $this->add_tweaks_to_standard_grading_form_elements();

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

    }


    /**
     * Adds all default data to the form elements.
     *
     * @global moodle_database $DB
     * @param $default_values
     * @return void
     */
    public function set_data($default_values) {
        $default_values = (array)$default_values;

        foreach ($this->_form->_elements as $element) {

            // Some form elements are replaced with static HTML e.g. if there is a submission now
            // And they should not be editable.
            //
            // For any elements that won't have any default (static thingys we are using for
            // non-editing display), add the data from its corresponding real element, which is
            // now hidden. This is the only way to get around the issue of Moodle requiring all
            // the form's required data to be resubmitted if you want to edit any part of it.
            // Using a rule to disable an element means it doesn't get submitted, which breaks
            // stuff. Just having a static element pre-fills with defaults, but won't get
            // resubmitted, so we have to use a hidden value, then another static one with
            // 'html' suffixed (arbitrarily) which we add the same default data to here.
            if (isset($element->_attributes['name']) && substr($element->_attributes['name'], -6) == 'static') {
                // TODO this is using private attributes directly. Need to switch to proper
                // Getters and setters.
                if (isset($default_values[substr($element->_attributes['name'], 0, -6)])) {
                    $default = $default_values[substr($element->_attributes['name'], 0, -6)];
                    $default_values[$element->_attributes['name']] = $default;
                }
            }
        }
        parent::set_data($default_values);
    }

    /**
     * We can't do this with $mform->addRule() because the compare function works with the raw form values, which is
     * an array of date components. Here, Moodle's internals have processed those values into a Unix timestamp, so the
     * comparison works.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {

        $errors = array();

       if ($data['startdate'] != 0 && !empty($data['deadline']) && $data['startdate'] > $data['deadline']){
           $errors['startdate'] = get_string('must_be_before_dealdine', 'mod_coursework');
       }

       if ($data['individualfeedback'] != 0 && !empty($data['deadline']) && $data['individualfeedback'] < $data['deadline']) {
            $errors['individualfeedback'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if ($data['generalfeedback'] != 0 && !empty($data['deadline']) && $data['generalfeedback'] < $data['deadline']) {
            $errors['generalfeedback'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if (isset($data['initialmarkingdeadline']) && $data['initialmarkingdeadline'] != 0 && !empty($data['deadline']) && $data['initialmarkingdeadline'] < $data['deadline']){
            $errors['initialmarkingdeadline'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if (isset($data['agreedgrademarkingdeadline']) && $data['agreedgrademarkingdeadline'] != 0 && !empty($data['deadline']) && $data['agreedgrademarkingdeadline'] < $data['deadline']){
            $errors['agreedgrademarkingdeadline'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if (isset($data['agreedgrademarkingdeadline']) && $data['agreedgrademarkingdeadline'] != 0 &&  $data['agreedgrademarkingdeadline'] < $data['initialmarkingdeadline'] ){
            $errors['agreedgrademarkingdeadline'] = get_string('must_be_after_initial_grade_dealdine', 'mod_coursework');
        }

        if (isset($data['initialmarkingdeadline']) && $data['initialmarkingdeadline'] != 0 && !empty($data['deadline']) && $data['deadline'] && $data['initialmarkingdeadline'] < $data['deadline']){
            $errors['initialmarkingdeadline'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if (isset($data['agreedgrademarkingdeadline']) && $data['agreedgrademarkingdeadline'] != 0 && !empty($data['deadline']) && $data['agreedgrademarkingdeadline'] < $data['deadline']){
            $errors['agreedgrademarkingdeadline'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if (isset($data['agreedgrademarkingdeadline']) && $data['agreedgrademarkingdeadline'] != 0 &&  $data['agreedgrademarkingdeadline'] < $data['initialmarkingdeadline'] ){
            $errors['agreedgrademarkingdeadline'] = get_string('must_be_after_initial_grade_dealdine', 'mod_coursework');
        }

        if (isset($data['relativeagreedmarkingdeadline'])  && $data['relativeagreedmarkingdeadline'] != 0 && $data['relativeagreedmarkingdeadline'] < $data['relativeinitialmarkingdeadline'] ) {
            $errors['relativeagreedmarkingdeadline'] = get_string('must_be_after_or_equal_to_relative_initial_grade_dealdine', 'mod_coursework');

        }


        $courseworkid = $this->get_coursework_id();
        if ($courseworkid) {
            $coursework = mod_coursework\models\coursework::find($courseworkid);
            if ($coursework->has_samples() && isset($data['samplingenabled']) && $data['samplingenabled'] == 0){
                $errors['samplingenabled'] = get_string('sampling_cant_be_disabled', 'mod_coursework');
            }
        }

        if ( isset($data['numberofmarkers']) && $data['numberofmarkers'] == 1 && isset($data['samplingenabled']) && $data['samplingenabled'] == 1){
            $errors['numberofmarkers'] = get_string('not_enough_assessors_for_sampling', 'mod_coursework');
        }



        $parent_errors = parent::validation($data, $files);
        return array_merge($errors, $parent_errors);

    }




    /**
     * Get data from the form and manipulate it
     * @return bool|object
     */
    function get_data() {
        global $CFG;
        $data = parent::get_data();

        if (!$data){
            return false;
        }

        if ($this->forceblindmarking() == 1){
           $data->blindmarking = $CFG->coursework_blindmarking;
        }

        if($data->numberofmarkers >1){
            $data->moderationagreementenabled = 0;
        }

        return $data;
    }

    /**
     * @throws coding_exception
     */
    protected function add_submissions_header() {
        $moodle_form =& $this->_form;

        $moodle_form->addElement('header', 'submissions', get_string('submissions', 'mod_coursework'));
        // We want it expanded by default
        $moodle_form->setExpanded('submissions');
    }

    /**
     * @throws coding_exception
     */
    protected function add_availability_header() {
        $moodle_form =& $this->_form;

        $moodle_form->addElement('header', 'availability', get_string('availability', 'mod_coursework'));
        // We want it expanded by default
        $moodle_form->setExpanded('availability');
    }

    /**
     * @throws coding_exception
     */
    protected function add_name_field() {
        $moodle_form =& $this->_form;

        $moodle_form->addElement('text',
                                 'name',
                                 get_string('courseworkname', 'coursework'),
                                 array('size' => '64'));
        $moodle_form->addRule('name', null, 'required', null, 'client');
        $moodle_form->addRule('name',
                              get_string('maximumchars', '', 255),
                              'maxlength',
                              255,
                              'client');
        $moodle_form->setType('name', PARAM_TEXT);
    }

    /**
     * @throws coding_exception
     */
    protected function add_submission_deadline_field() {
        global $CFG;

        $moodle_form =& $this->_form;

        $default_timestamp   =   strtotime('+2 weeks');
        $disabled   =   true;

        if (!empty($CFG->coursework_submission_deadline))   {
            $disabled   =   false;

            $default_timestamp   =strtotime('today');
            if ($CFG->coursework_submission_deadline  == 7 ) {
                $default_timestamp   =   strtotime('+1 weeks');
            } else if ($CFG->coursework_submission_deadline == 14 ) {
                $default_timestamp   =   strtotime('+2 weeks');
            } else if ($CFG->coursework_submission_deadline == 31 ) {
                $default_timestamp   =   strtotime('+1 month');
            }
        }

        $optional = true;
        $courseworkid = $this->get_coursework_id();
        if ($courseworkid){
            $coursework =  mod_coursework\models\coursework::find($courseworkid);
            if($coursework->extension_exists()){
                $optional = false;
            }
        }


        $moodle_form->addElement('date_time_selector',
                                 'deadline',
                                 get_string('deadline', 'coursework'),
                                 array('optional' => $optional, 'disabled'=> $disabled));



         $moodle_form->addElement('html', '<div class ="submission_deadline_info alert">');
         $moodle_form->addElement('html', get_string('submissionsdeadlineinfo','mod_coursework'));
         $moodle_form->addElement('html', '</div>');




        if (!empty($CFG->coursework_submission_deadline)) $moodle_form->setDefault('deadline', $default_timestamp);
        $moodle_form->addHelpButton('deadline', 'deadline', 'mod_coursework');
    }


    /**
     * @throws coding_exception
     */
    protected function add_personal_deadline_field(){

        $moodle_form =& $this->_form;
        $options = array(0 => get_string('no'), 1 => get_string('yes'));

        $courseworkid = $this->get_coursework_id();
        $disabled = array();
        if (coursework_personal_deadline_passed($courseworkid)){
            $moodle_form->disabledIf('personaldeadlineenabled', 'deadline[enabled]', 'notchecked');
            $disabled = array('disabled' => true);
        }
        $moodle_form->addElement('select',
                                 'personaldeadlineenabled',
                                  get_string('usepersonaldeadline', 'mod_coursework'), $options, $disabled);
        $moodle_form->setType('personaldeadlineenabled', PARAM_INT);
        $moodle_form->addHelpButton('personaldeadlineenabled', 'personaldeadlineenabled', 'mod_coursework');

        $moodle_form->setDefault('personaldeadlineenabled', 0);
        //$moodle_form->disabledIf('personaldeadlineenabled', 'deadline[enabled]', 'notchecked');

    }


    /**
     * @throws coding_exception
     */
    protected function add_start_date_field() {
        global $CFG;

        $moodle_form =& $this->_form;

        $default_timestamp  =   strtotime('+2 weeks');
        $disabled   =   true;

        if (!empty($CFG->coursework_start_date))   {
            $disabled   =   false;
            $default_timestamp   =strtotime('today');
        }

        $moodle_form->addElement('date_time_selector',
                                 'startdate',
                                 get_string('startdate', 'coursework'),
                                 array('optional' => true, 'disabled' => $disabled)
        );


        if (!empty($CFG->coursework_start_date))   $moodle_form->setDefault('startdate', $default_timestamp);
        $moodle_form->addHelpButton('startdate', 'startdate', 'mod_coursework');
    }




    private function add_marking_deadline_field()   {
        global $CFG;
        $moodle_form =& $this->_form;
        $options = array(0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select',
            'markingdeadlineenabled',
            get_string('usemarkingdeadline', 'mod_coursework'), $options);
        $moodle_form->setType('markingdeadlineenabled', PARAM_INT);

        $settingdefault   =   (empty($CFG->coursework_marking_deadline) && empty($CFG->coursework_agreed_marking_deadline))  ?   0   :   1;
        $moodle_form->setDefault('markingdeadlineenabled', $settingdefault);
    }

    /**
     * @throws coding_exception
     */
    protected function add_initial_marking_deadline_field() {
        global $CFG;

        $moodle_form =& $this->_form;

        $default_timestamp   =strtotime('today');
        $disabled   =   true;

        $submission_deadline_timestamp   =strtotime('today');

        if (!empty($CFG->coursework_submission_deadline))   {
            if ($CFG->coursework_submission_deadline  == 7 ) {
                $submission_deadline_timestamp   =   strtotime('+1 weeks');
            } else if ($CFG->coursework_submission_deadline == 14 ) {
                $submission_deadline_timestamp   =   strtotime('+2 weeks');
            } else if ($CFG->coursework_submission_deadline == 31 ) {
                $submission_deadline_timestamp   =   strtotime('+1 month');
            }
        }

        if (!empty($CFG->coursework_marking_deadline))   {

            $disabled   =   false;


            if ($CFG->coursework_marking_deadline  == 7 ) {
                $default_timestamp   =   strtotime('+1 weeks',$submission_deadline_timestamp);
            } else if ($CFG->coursework_marking_deadline == 14 ) {
                $default_timestamp   =   strtotime('+2 weeks',$submission_deadline_timestamp);
            } else if ($CFG->coursework_marking_deadline == 21 ) {
                $default_timestamp   =   strtotime('+3 weeks',$submission_deadline_timestamp);
            }else if ($CFG->coursework_marking_deadline == 28 ) {
                $default_timestamp   =   strtotime('+4 weeks',$submission_deadline_timestamp);
            } else if ($CFG->coursework_marking_deadline == 35 ) {
                $default_timestamp   =   strtotime('+5 weeks',$submission_deadline_timestamp);
            } else if ($CFG->coursework_marking_deadline == 42 ) {
                $default_timestamp   =   strtotime('+6 weeks',$submission_deadline_timestamp);
            }
        }

        $moodle_form->addElement('date_time_selector',
            'initialmarkingdeadline',
            get_string('initialmarkingdeadline', 'coursework'),
            array('optional' => true, 'disabled' => $disabled)
        );


        if (!empty($CFG->coursework_marking_deadline)) $moodle_form->setDefault('initialmarkingdeadline', $default_timestamp);

        $moodle_form->addHelpButton('initialmarkingdeadline', 'initialmarkingdeadline', 'mod_coursework');
    }

    /**
     * @throws coding_exception
     */
    protected function add_agreed_grade_marking_deadline_field() {
        global $CFG;

        $moodle_form =& $this->_form;

        $default_timestamp   =strtotime('today');
        $disabled   =   true;

        $submission_deadline_timestamp   =strtotime('today');

        if (!empty($CFG->coursework_submission_deadline))   {
            if ($CFG->coursework_submission_deadline  == 7 ) {
                $submission_deadline_timestamp   =   strtotime('+1 weeks');
            } else if ($CFG->coursework_submission_deadline == 14 ) {
                $submission_deadline_timestamp   =   strtotime('+2 weeks');
            } else if ($CFG->coursework_submission_deadline == 31 ) {
                $submission_deadline_timestamp   =   strtotime('+1 month');
            }
        }

        if (!empty($CFG->coursework_agreed_marking_deadline))   {
            $disabled   =   false;
            if ($CFG->coursework_agreed_marking_deadline  == 7 ) {
                $default_timestamp   =   strtotime('+1 weeks',$submission_deadline_timestamp);
            } else if ($CFG->coursework_agreed_marking_deadline == 14 ) {
                $default_timestamp   =   strtotime('+2 weeks',$submission_deadline_timestamp);
            } else if ($CFG->coursework_agreed_marking_deadline == 21 ) {
                $default_timestamp   =   strtotime('+3 weeks',$submission_deadline_timestamp);
            } else if ($CFG->coursework_agreed_marking_deadline == 28 ) {
                $default_timestamp   =   strtotime('+4 weeks',$submission_deadline_timestamp);
            } else if ($CFG->coursework_agreed_marking_deadline == 35 ) {
                $default_timestamp   =   strtotime('+5 weeks',$submission_deadline_timestamp);
            } else if ($CFG->coursework_agreed_marking_deadline == 42 ) {
                $default_timestamp   =   strtotime('+6 weeks',$submission_deadline_timestamp);
            }
        }

        $moodle_form->addElement('date_time_selector',
            'agreedgrademarkingdeadline',
            get_string('agreedgrademarkingdeadline', 'coursework'),
            array('optional' => true, 'disabled' => $disabled)
        );

       // $moodle_form->disabledIf('agreedgrademarkingdeadline', 'numberofmarkers', 'eq', '1');

        if (!empty($CFG->coursework_agreed_marking_deadline)) $moodle_form->setDefault('agreedgrademarkingdeadline', $default_timestamp);
        $moodle_form->addHelpButton('agreedgrademarkingdeadline', 'agreedgrademarkingdeadline', 'mod_coursework');
    }

    /********
     *  Adds the relative initial marking deadline fields to the settings
     */
    protected function add_relative_initial_marking_deadline_field()    {
        global $CFG;

        $moodle_form    =&  $this->_form;

        $options    =   array('0'=>get_string('disabled', 'mod_coursework'));
        $options['7']      = get_string('oneweekoption', 'mod_coursework');
        $options['14']     = get_string('twoweeksoption', 'mod_coursework');
        $options['21']     = get_string('threeweeksoption', 'mod_coursework');
        $options['28']      = get_string('fourweeksoption', 'mod_coursework');
        $options['35']     = get_string('fiveweeksoption', 'mod_coursework');
        $options['42']     = get_string('sixweeksoption', 'mod_coursework');

        $moodle_form->addElement('select',
            'relativeinitialmarkingdeadline',
            get_string('relativeinitialmarkingdeadline', 'mod_coursework'), $options);

        if (!empty($CFG->coursework_marking_deadline)) $moodle_form->setDefault('relativeinitialmarkingdeadline', $CFG->coursework_marking_deadline);
        $moodle_form->addHelpButton('relativeinitialmarkingdeadline', 'agreedgrademarkingdeadline', 'mod_coursework');



    }


    /********
     *  Adds the relative agreed grade marking deadline fields to the settings
     */
    protected function add_relative_agreed_grade_marking_deadline_field()    {
        global $CFG;

        $moodle_form    =&  $this->_form;

        $options    =   array('0'=>get_string('disabled', 'mod_coursework'));
        $options['7']      = get_string('oneweekoption', 'mod_coursework');
        $options['14']     = get_string('twoweeksoption', 'mod_coursework');
        $options['21']     = get_string('threeweeksoption', 'mod_coursework');
        $options['28']      = get_string('fourweeksoption', 'mod_coursework');
        $options['35']     = get_string('fiveweeksoption', 'mod_coursework');
        $options['42']     = get_string('sixweeksoption', 'mod_coursework');

        $moodle_form->addElement('select',
            'relativeagreedmarkingdeadline',
            get_string('relativeagreedmarkingdeadline', 'mod_coursework'), $options);

        if (!empty($CFG->coursework_agreed_marking_deadline)) $moodle_form->setDefault('relativeagreedmarkingdeadline', $CFG->coursework_agreed_marking_deadline);
        $moodle_form->addHelpButton('relativeagreedmarkingdeadline', 'agreedgrademarkingdeadline', 'mod_coursework');

    }

    /**
     * @throws coding_exception
     */
    protected function add_digest_header() {
        $moodle_form =& $this->_form;

        $moodle_form->addElement('header', 'digest', get_string('digest', 'mod_coursework'));
        // We want it expanded by default
        $moodle_form->setExpanded('digest');
    }


    private function add_marking_reminder_field() {
        global $CFG;
        $moodle_form =& $this->_form;
        $options = array(0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select',
            'markingreminderenabled',
            get_string('sendmarkingreminder', 'mod_coursework'), $options);
        $moodle_form->setType('markingreminderenabled', PARAM_INT);

        $settingdefault   =   (empty($CFG->coursework_marking_deadline))  ?   0   :   1;
        $moodle_form->setDefault('markingreminderenabled', $settingdefault);

    }

    protected function add_marking_reminder_warning() {
        $this->_form->addElement('html', '<div class ="notification_tii">');
        $this->_form->addElement('html',
            get_string('relativedeadlinesreminder', 'mod_coursework'));
        $this->_form->addElement('html', '</div>');
    }


    /**
     * @throws coding_exception
     */
    protected function add_allow_early_finalisation_field() {
        $moodle_form =& $this->_form;
        $options = array( 0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select',
                                 'allowearlyfinalisation',
                                 get_string('allowearlyfinalisation', 'mod_coursework'),$options);
        $moodle_form->setType('allowearlyfinalisation', PARAM_INT);
        $moodle_form->disabledIf('allowearlyfinalisation', 'deadline[enabled]', 'notchecked');
    }


    /**
     * @throws coding_exception
     */
    protected function add_group_submission_header(){

        $moodle_form =& $this->_form;

        $moodle_form->addElement('header', 'group_submission', get_string('groupsubmissionsettings', 'mod_coursework'));
        // We want it expanded by default
        $moodle_form->setExpanded('group_submission');


    }

    /**
     * @throws coding_exception
     */
    protected function add_use_groups_field() {
        $moodle_form =& $this->_form;

        $options = array( 0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select', 'use_groups', get_string('use_groups', 'mod_coursework'),$options);
        $moodle_form->addHelpButton('use_groups', 'use_groups', 'mod_coursework');
    }

    /**
     * @throws coding_exception
     */
    protected function add_grouping_field() {
        global $COURSE, $DB;

        $moodle_form =& $this->_form;

        $groups_options_result = $DB->get_records('groupings', array('courseid'=>$COURSE->id), 'name', 'id, name');
        $groups_options = array();
        if ($groups_options_result !== false) {
            foreach ($groups_options_result as $result) {
                $groups_options[$result->id] = $result->name;
            }
        }

        // Not calling it groupingid as this conflicts with the groupingid field in the common module
        // settings.
        $default_groups_options = array(0 => 'Use all groups');
        $groups_options = $default_groups_options + $groups_options;
        $moodle_form->addElement('select',
                                 'grouping_id',
                                 get_string('grouping_id', 'mod_coursework'),
                                 $groups_options);
        $moodle_form->addHelpButton('grouping_id', 'grouping_id', 'mod_coursework');
        $moodle_form->disabledIf('grouping_id', 'use_groups', 'eq', 0);
    }

    /**
     * @throws coding_exception
     */
    protected function add_general_header() {
        $moodle_form =& $this->_form;

        $moodle_form->addElement('header', 'generalstuff', get_string('general', 'form'));
    }

    /**
     * @throws coding_exception
     */
    protected function add_file_types_field() {
        $moodle_form =& $this->_form;

        $moodle_form->addElement('text',
                                 'filetypes',
                                 get_string('filetypes', 'coursework'),
                                 array('placeholder' => 'e.g. doc, docx, txt, rtf'));
        $moodle_form->addHelpButton('filetypes', 'filetypes', 'mod_coursework');
        $moodle_form->setType('filetypes', PARAM_TEXT);
        $moodle_form->disabledIf('filetypes', 'use_turnitin', 'eq', '1');
    }

    /**
     * @throws coding_exception
     */
    protected function add_max_file_size_field() {
        global $CFG, $COURSE;

        $moodle_form =& $this->_form;

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        //$choices[0] = get_string('maximumupload') . ' (' . display_size($COURSE->maxbytes) . ')';
        $choices[0] = get_string('maximumupload'). ' set in course';
        $moodle_form->addElement('select',
                                 'maxbytes',
                                 get_string('maximumsize', 'coursework'),
                                 $choices);
        $moodle_form->setDefault('maxbytes', $CFG->coursework_maxbytes);
       /* $moodle_form->addElement('static',
                                 'maxbyteslabel',
                                 '',
                                 get_string('maximumsizelabel', 'coursework'));*/
        $moodle_form->addHelpButton('maxbytes','maximumsize','mod_coursework');
        $moodle_form->disabledIf('maxbytes', 'use_turnitin', 'eq', '1');

        $moodle_form->closeHeaderBefore('submissiontype');

    }

    /**
     * @throws coding_exception
     */
    protected function add_number_of_files_field() {

        $moodle_form =& $this->_form;

        // Maximum number of files:
        $choices = array(1 => 1,
                         2 => 2,
                         3 => 3,
                         4 => 4,
                         5 => 5,
                         6 => 6,
                         7 => 7,
                         8 => 8,
                         9 => 9,
                         10 => 10);
        $moodle_form->addElement('select',
                                 'maxfiles',
                                 get_string('maxfiles', 'coursework'),
                                 $choices);
        $moodle_form->setDefault('maxfiles', 1);
        $moodle_form->setType('maxfiles', PARAM_INT);
        $moodle_form->addHelpButton('maxfiles','maxfiles','mod_coursework');

    }


    protected function add_rename_file_field()  {

        global  $DB, $PAGE;

        $moodle_form =& $this->_form;

        $choices    =   array('0'=>get_string('no'),'1'=>get_string('yes'));
        $courseworkid   =   $this->get_coursework_id();
        $courseworkhassubmissions   =   (!empty($courseworkid)) ?
            $courseworkhassubmissions = $DB->get_records('coursework_submissions', array('courseworkid' => $courseworkid))
            : false;

        if (empty($courseworkid) || empty($courseworkhassubmissions)) {

            $moodle_form->addElement('select', 'renamefiles',
                get_string('renamefiles', 'mod_coursework'), $choices);

            $moodle_form->addHelpButton('renamefiles','renamefiles','mod_coursework');

            $moodle_form->disabledIf('renamefiles', 'blindmarking', 'eq', '1');

            $PAGE->requires->js_amd_inline("
            require(['jquery'], function() {
                       $('#id_blindmarking').change(function()  {
                            console.log($(this).val());
                            
                            if ($(this).val()== 1)  {
                                $('#id_renamefiles').val(1);
                            } 
                            
                        });
                });
            ");


        } else  {

            $sql    =   "SELECT     *
                         FROM       {coursework}     
                         WHERE      id    =   :courseworkid
                         AND        renamefiles = 1";


            $settingvalue    =   ($DB->get_records_sql($sql,array('courseworkid'=>$courseworkid))) ? get_string('yesrenamefile','mod_coursework')  : get_string('norenamefile','mod_coursework')  ;

            $moodle_form->addElement('static', 'renamefilesdescription', get_string('renamefiles', 'mod_coursework'),
                $settingvalue);
        }

    }

    /**
     * @throws coding_exception
     */
    protected function add_marking_workflow_header() {
        $moodle_form =& $this->_form;

        $moodle_form->addElement('header', 'markingworkflow', get_string('markingworkflow', 'mod_coursework'));
    }

    /**
     * @param $moodle_form
     * @throws coding_exception
     */
    protected function add_number_of_initial_assessors_field() {

        $moodle_form =& $this->_form;
        $courseworkid = $this->get_coursework_id();

        $multi_options = array(
            // Don't want to give the option for 0!
            1 => 1,
            2 => 2,
            3 => 3
        );
        // Remove all options lower than the current maximum number of feedbacks that any student has.
        $currentmaxfeedbacks = coursework_get_current_max_feedbacks($courseworkid);
        if ($currentmaxfeedbacks) {
            foreach ($multi_options as $key => $option) {
                if ($key < $currentmaxfeedbacks) {
                    unset($multi_options[$key]);
                }
            }
        }
        $moodle_form->addElement('select',
                                 'numberofmarkers',
                                 get_string('numberofmarkers', 'mod_coursework'),
                                 $multi_options);
        $moodle_form->addHelpButton('numberofmarkers', 'numberofmarkers', 'mod_coursework');
        $moodle_form->setDefault('numberofmarkers', 1);
    }


    /**
     * @param $moodle_form
     * @throws coding_exception
     */
    protected function add_enable_moderation_agreement_field(){
        $moodle_form =& $this->_form;

        $options = array(0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select', 'moderationagreementenabled', get_string('moderationagreementenabled', 'mod_coursework'),$options);
        $moodle_form->addHelpButton('moderationagreementenabled', 'moderationagreementenabled', 'mod_coursework');
        $moodle_form->setDefault('moderationagreementenabled', 0);
        $moodle_form->disabledIf('moderationagreementenabled', 'numberofmarkers', 'neq', 1);
    }


    /**
     * @return int
     * @throws coding_exception
     */
    protected function get_coursework_id() {
        $upcmid = optional_param('update', -1, PARAM_INT);
        $cm = get_coursemodule_from_id('coursework', $upcmid);
        $courseworkid = 0;
        if ($cm) {
            $courseworkid = $cm->instance;
            return $courseworkid;
        }
        return $courseworkid;
    }

    /**
     * @throws coding_exception
     */
    protected function add_enable_allocation_field() {
        $moodle_form =& $this->_form;

        $options = array( 0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select', 'allocationenabled', get_string('allocationenabled', 'mod_coursework'),$options);
        $moodle_form->addHelpButton('allocationenabled', 'allocationenabled', 'mod_coursework');
    }

    /**
     * @throws coding_exception
     */
    protected function add_assessor_allocation_strategy_field_rdb() {
        $moodle_form =& $this->_form;

        $options = mod_coursework\allocation\manager::get_allocation_classnames();

        $radioarray = array();
        $keys = array_keys($options);

        foreach ($keys as $key) {
            $radioarray[] =& $moodle_form->createElement('radio', 'assessorallocationstrategy', '',$options[$key], $key, '');
        }
        $moodle_form->addGroup($radioarray, 'radioarray',  get_string('assessorallocationstrategy', 'mod_coursework'), array(' '), false);
        $moodle_form->addHelpButton('radioarray', 'assessorallocationstrategy', 'mod_coursework');
        $moodle_form->disabledIf('radioarray', 'allocationenabled', 'eq', 0);
    }

    protected function add_assessor_allocation_strategy_field() {
        $moodle_form =& $this->_form;

        $options = mod_coursework\allocation\manager::get_allocation_classnames();

        $moodle_form->addElement('select', 'assessorallocationstrategy', get_string('assessorallocationstrategy', 'mod_coursework'),$options);
        $moodle_form->addHelpButton('assessorallocationstrategy', 'assessorallocationstrategy', 'mod_coursework');
        $moodle_form->disabledIf('assessorallocationstrategy', 'allocationenabled', 'eq', 0);
    }

    /**
     * @throws coding_exception
     */
    protected function add_blind_marking_header() {

        $moodle_form =& $this->_form;

        $moodle_form->addElement('header', 'anonymity', get_string('blindmarking', 'mod_coursework'));
        $moodle_form->addElement('html', '<div class ="blind_marking_info">');
        $moodle_form->addElement('html',
            get_string('anonymitydescription', 'mod_coursework'));
        $moodle_form->addElement('html', '</div>');

    }

    /**
     * @throws coding_exception
     */
    protected function add_assessor_anonymity_header(){
        $moodle_form =& $this->_form;

        $moodle_form->addElement('header', 'assessoranonymityheader', get_string('assessoranonymity', 'mod_coursework'));
        $moodle_form->addElement('html', '<div class ="assessor_anonymity_info">');
        $moodle_form->addElement('html',
            get_string('assessoranonymity_desc', 'mod_coursework'));
        $moodle_form->addElement('html', '</div>');
    }

    /**
     * @throws coding_exception
     */
    protected function add_enable_blind_marking_field() {
        global $CFG;

        $moodle_form =& $this->_form;

        $options = array( 0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select', 'blindmarking', get_string('blindmarking', 'mod_coursework'),$options);
        $moodle_form->addHelpButton('blindmarking', 'blindmarking', 'mod_coursework');
        $moodle_form->setDefault('blindmarking', $CFG->coursework_blindmarking);

        $submission_exists = 0;
        // disable the setting if at least one submission exists
        $courseworkid = $this->get_coursework_id();
        if ($courseworkid && mod_coursework\models\coursework::find($courseworkid)->has_any_submission()) {
            $submission_exists = 1;
        }

        $moodle_form->addElement('hidden','submission_exists', $submission_exists);
        $moodle_form->setType('submission_exists', PARAM_INT);
        $moodle_form->disabledIf('blindmarking', 'submission_exists', 'eq', 1);

        //disable blindmarking if forceblindmarking is enabled, process data for DB in get_data()
        if ($this->forceblindmarking() == 1) {
            $moodle_form->addElement('hidden','forceblindmarking', $this->forceblindmarking());
            $moodle_form->setType('forceblindmarking', PARAM_INT);
            $moodle_form->disabledIf('blindmarking', 'forceblindmarking', 'eq', 1);
            $moodle_form->addElement('static', 'forceblindmarking_explanation', '', get_string('forcedglobalsetting', 'mod_coursework'));
        }
    }

    /**
     * @throws coding_exception
     */
    protected function add_enable_assessor_anonymity_field(){
        global $CFG;

        $moodle_form =& $this->_form;
        $options = array(0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select', 'assessoranonymity', get_string('assessoranonymity', 'mod_coursework'),$options);
        $moodle_form->addHelpButton('assessoranonymity', 'assessoranonymity', 'mod_coursework');
        $moodle_form->setDefault('assessoranonymity', $CFG->coursework_assessoranonymity);

    }


    /**
     * @throws coding_exception
     */
    protected function add_feedback_header() {
        $moodle_form =& $this->_form;

        $moodle_form->addElement('header', 'feedbacktypes', get_string('feedbacktypes', 'mod_coursework'));
    }

    /**
     * @throws coding_exception
     */
    protected function add_individual_feedback_release_date_field() {

        global $CFG;

        $moodle_form =& $this->_form;

        $timestamp = strtotime('+' . $CFG->coursework_individualfeedback . ' weeks');

        $default = array(
            'day' => date('j', $timestamp),
            'month' => date('n', $timestamp),
            'year' => date('Y', $timestamp),
            'hour' => date('G', $timestamp),
            'minute' => date('i', $timestamp),
        );
        $options = array('optional' => true);
        if ($CFG->coursework_auto_release_individual_feedback == 0){
            $options['disabled'] =   true;

        } else{
            $default['enabled'] = 1;
        }

        if ($CFG->coursework_forceauto_release_individual_feedback == 1){
            $options['optional'] =   false;
        }


        $moodle_form->addElement('date_time_selector',
                                 'individualfeedback',
                                 get_string('individualfeedback', 'coursework'),
                                 $options);
        $moodle_form->setDefault('individualfeedback', $default);
        $moodle_form->addHelpButton('individualfeedback', 'individualfeedback', 'mod_coursework');

        if ($this->forceautorelease() == 1 && $CFG->coursework_auto_release_individual_feedback == 0) {
            $moodle_form->addElement('hidden','forceautorelease', $this->forceautorelease());
            $moodle_form->setType('forceautorelease', PARAM_INT);
            $moodle_form->disabledIf('individualfeedback', 'forceautorelease', 'eq', 1);
        }
        $moodle_form->disabledIf('individualfeedback', 'deadline[enabled]', 'notchecked');
//        $moodle_form->addRule(array('individualfeedback', 'deadline'), get_string('must_be_after_dealdine', 'mod_coursework'), 'compare', 'gt');
    }

    /**
     * @throws coding_exception
     */
    protected function add_email_individual_feedback_notification_field(){
        global $CFG;

        $moodle_form =& $this->_form;
        $options = array(0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select', 'feedbackreleaseemail', get_string('feedbackreleaseemail', 'mod_coursework'),$options);
        $moodle_form->addHelpButton('feedbackreleaseemail', 'feedbackreleaseemail', 'mod_coursework');
        $moodle_form->setDefault('feedbackreleaseemail', $CFG->coursework_feedbackreleaseemail);
    }

    /**
     * @throws coding_exception
     */
    protected function add_general_feedback_release_date_field() {
        global $CFG;

        $moodle_form =& $this->_form;

        $moodle_form->addElement('date_time_selector',
                                 'generalfeedback',
                                 get_string('generalfeedbackreleasedate', 'coursework'),
                                 array('optional' => true, 'disabled' => true));
        // We have a field which is sometimes disabled. Disabled fields are not sent back to the
        // server, so the default is used.
        $timestamp = strtotime('+' . $CFG->coursework_generalfeedback . ' weeks');

        $default = array(
            'day' => date('j', $timestamp),
            'month' => date('n', $timestamp),
            'year' => date('Y', $timestamp),
            'hour' => date('G', $timestamp),
            'minute' => date('i', $timestamp),
        );
        $moodle_form->setDefault('generalfeedback', $default);
        $moodle_form->addHelpButton('generalfeedback', 'generalfeedback', 'mod_coursework');
        $moodle_form->disabledIf('generalfeedback', 'deadline[enabled]', 'notchecked');
    }


    /**
     */
    protected function add_tweaks_to_standard_grading_form_elements() {
        $moodle_form =& $this->_form;

        $moodle_form->addHelpButton('grade', 'grade', 'mod_coursework');
        $moodle_form->setExpanded('modstandardgrade');

        $options = array(0=> get_string('sameforallstages', 'mod_coursework'),
                         1=> get_string('simpledirectgrading', 'mod_coursework'));

        $moodle_form->addElement('select', 'finalstagegrading', get_string('finalstagegrading', 'mod_coursework'), $options);
        $moodle_form->addHelpButton('finalstagegrading', 'finalstagegrading', 'mod_coursework');
        $moodle_form->setDefault('finalstagegrading',0);
        $moodle_form->disabledIf('finalstagegrading', 'numberofmarkers', 'eq', 1);
        $moodle_form->disabledIf('finalstagegrading', 'advancedgradingmethod_submissions', 'eq', "");


        $feedbackexists = 0;
        // disable the setting if at least one feedback exists
        $courseworkid = $this->get_coursework_id();
        if ($courseworkid && mod_coursework\models\coursework::find($courseworkid)->has_any_final_feedback()) {
            $feedbackexists = 1;
        }

        $moodle_form->addElement('hidden','feedbackexists', $feedbackexists);
        $moodle_form->setType('feedbackexists', PARAM_INT);
        $moodle_form->disabledIf('finalstagegrading', 'feedbackexists', 'eq', 1);


        // Don't think this belongs here...
//        $options = array(0 => get_string('no'), 1 => get_string('yes'));
//        $moodle_form->addElement('select', 'automaticagreement', get_string('automaticagreement', 'mod_coursework'),$options);
//        $moodle_form->addHelpButton('automaticagreement', 'automaticagreement', 'mod_coursework');
//        $moodle_form->setDefault('automaticagreement',0);
//        $moodle_form->disabledIf('automaticagreement', 'numberofmarkers', 'eq', '1');
//
//        $moodle_form->addElement('text', 'automaticagreementrange', get_string('automaticagreementrange', 'mod_coursework'),array('size'=>3));
//        $moodle_form->addHelpButton('automaticagreementrange', 'automaticagreementrange', 'mod_coursework');
//        $moodle_form->setDefault('automaticagreementrange',0);
//        $moodle_form->disabledIf('automaticagreementrange', 'automaticagreement', 'eq', '0');

    }

    /**
     */
    protected function set_form_attributes() {
        $moodle_form =& $this->_form;

        $moodle_form->_attributes['name'] = 'ocm_update_form';
    }

    protected function add_turnitin_files_settings_waring() {
        $this->_form->addElement('html', '<div class ="notification_tii">');
        $this->_form->addElement('html',
                                 get_string('turnitinfilesettingswarning', 'mod_coursework'));
        $this->_form->addElement('html', '</div>');
    }

    private function add_allow_late_submissions_field() {
        global $CFG;
        $moodle_form =& $this->_form;
        $options = array( 0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select',
                                 'allowlatesubmissions',
                                 get_string('allowlatesubmissions', 'mod_coursework'),$options);
        $moodle_form->setType('allowlatesubmissions', PARAM_INT);
        $moodle_form->setDefault('allowlatesubmissions', $CFG->coursework_allowlatesubmissions);
        $moodle_form->disabledIf('allowlatesubmissions', 'deadline[enabled]', 'notchecked');

    }


    protected function add_all_feedbacks_field(){

        $moodle_form =& $this->_form;

        $options = array( 0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select',
                                 'showallfeedbacks',
                                 get_string('showallfeedbacks', 'mod_coursework'),$options);
        $moodle_form->setDefault('showallfeedbacks', 0);
        $moodle_form->disabledIf('showallfeedbacks', 'numberofmarkers', 'eq', 1);
        $moodle_form->addHelpButton('showallfeedbacks', 'showallfeedbacks', 'mod_coursework');
    }

    /**
     * @throws coding_exception
     */
    private function add_enable_sampling_checkbox() {
        $moodle_form =& $this->_form;

        $moodle_form->addElement('selectyesno', 'samplingenabled', get_string('samplingenabled', 'mod_coursework'));
        $moodle_form->addHelpButton('samplingenabled', 'samplingenabled', 'mod_coursework');


        $courseworkid = $this->get_coursework_id();
        if (!$courseworkid ||  ($courseworkid && !mod_coursework\models\coursework::find($courseworkid)->has_samples()) ) {
            $moodle_form->disabledIf('samplingenabled', 'numberofmarkers', 'eq', 1);

        }
        

    }

    /**
     * @throws coding_exception
     */
    private function add_view_initial_assessors_grade(){

        $moodle_form =& $this->_form;

        $moodle_form->addElement('selectyesno', 'viewinitialgradeenabled', get_string('viewinitialgradeenabled', 'mod_coursework'));
        $moodle_form->addHelpButton('viewinitialgradeenabled', 'viewinitialgradeenabled', 'mod_coursework');

        $moodle_form->disabledIf('viewinitialgradeenabled', 'numberofmarkers', 'eq', 1);
    }


    private function add_enable_agreed_grade_delay()    {

        global  $CFG;

        $moodle_form =& $this->_form;

        $options    =   array('0'=>get_string('disabled', 'mod_coursework'));
        $options['1800']  =   get_string('timedminutes', 'mod_coursework','30');
        $options['3600']  = get_string('timedhour', 'mod_coursework','1');
        $options['7200']  = get_string('timedhours', 'mod_coursework','2');
        $options['18000']  = get_string('timedhours', 'mod_coursework','5');
        $options['43200']  = get_string('timedhours', 'mod_coursework','12');
        $options['86400']  = get_string('aday', 'mod_coursework');

        $moodle_form->addElement('select', 'gradeeditingtime', get_string('gradeeditingtime', 'mod_coursework'), $options);
        $moodle_form->addHelpButton('gradeeditingtime', 'gradeeditingtime', 'mod_coursework');
        $moodle_form->setDefault('gradeeditingtime', $CFG->coursework_grade_editing);



        $moodle_form->disabledIf('gradeeditingtime', 'numberofmarkers', 'eq', 1);

    }

    /**
     *
     */
    private function add_save_feedback_as_draft()    {

        $moodle_form =& $this->_form;

        $options = array(0 => get_string('no'), 1 => get_string('yes'));

        $moodle_form->addElement('select', 'draftfeedbackenabled', get_string('savefeedbackasdraft', 'mod_coursework'), $options);
        $moodle_form->addHelpButton('draftfeedbackenabled', 'savefeedbackasdraft', 'mod_coursework');
        $moodle_form->setDefault('draftfeedbackenabled', 1);

    }

    /**
     * @throws coding_exception
     */
    private function add_auto_populate_agreed_feedback_comments(){
       
        $moodle_form =& $this->_form;
        $moodle_form->addElement('selectyesno', 'autopopulatefeedbackcomment', get_string('autopopulatefeedbackcomment', 'mod_coursework'));
        $moodle_form->addHelpButton('autopopulatefeedbackcomment', 'autopopulatefeedbackcomment', 'mod_coursework');
   
        $moodle_form->disabledIf('autopopulatefeedbackcomment', 'numberofmarkers', 'eq', 1);
        
    }
    

    private function forceblindmarking(){
        global $CFG;
        return $CFG->coursework_forceblindmarking;

    }


    private function forceautorelease(){
        global $CFG;
        return $CFG->coursework_forceauto_release_individual_feedback;

    }

    private function add_extensions_header(){
        $moodle_form =& $this->_form;

        $moodle_form->addElement('header', 'extensions', get_string('extensions', 'mod_coursework'));
    }

    private function add_enable_extensions_field(){
        global $CFG;
        $moodle_form =& $this->_form;

        $options = array( 0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select', 'extensionsenabled', get_string('individual_extension', 'mod_coursework'), $options);
        $moodle_form->addHelpButton('extensionsenabled', 'individual_extension', 'mod_coursework');
        $moodle_form->setDefault('extensionsenabled', $CFG->coursework_individual_extension);
    }


    private function add_submission_notification_field()    {

        global  $COURSE;

        $moodle_form =& $this->_form;


        $selectableusers    =   array();

        // capability for user allowed to receive submission notifications
        $enrolledusers  =   get_enrolled_users(context_course::instance($COURSE->id), 'mod/coursework:receivesubmissionnotifications');
        if($enrolledusers) {
            foreach ($enrolledusers as $u) {
                $selectableusers[$u->id] = fullname($u);
            }
        }

        $select = $moodle_form->addElement('select', 'submissionnotification', get_string('submissionnotification', 'mod_coursework'), $selectableusers);
        $select->setMultiple(true);
        $moodle_form->disabledIf('submissionnotification', 'deadline[enabled]', 'checked');

    }

    private function add_automatic_agreement_enabled() {
        $options = array('none' => 'none',
                         'percentage_distance' => 'percentage distance',
                         'average_grade' =>'average grade');
        $this->form()->addelement('select',
                                  'automaticagreementstrategy',
                                  get_string('automaticagreementofgrades', 'mod_coursework'),
                                  $options);
        $this->form()->settype('automaticagreementstrategy', PARAM_ALPHAEXT);
        $this->form()->addhelpbutton('automaticagreementstrategy', 'automaticagreement', 'mod_coursework');

        $this->form()->disabledif('automaticagreementstrategy', 'numberofmarkers', 'eq', 1);
        $this->form()->disabledIf('automaticagreementrange', 'automaticagreementstrategy', 'neq', 'percentage_distance');


        $this->form()->addElement('select',
                                  'automaticagreementrange',
                                  get_string('automaticagreementrange', 'mod_coursework'),
                                  range(0, 100));
        $this->form()->setType('automaticagreementrange', PARAM_INT);
        $this->form()->setDefault('automaticagreementrange', 10);

        // rounding of the average grade
        $roundingoptions = array('mid' => get_string('roundmid', 'mod_coursework'),
                                 'up' => get_string('roundup', 'mod_coursework'),
                                 'down' => get_string('rounddown', 'mod_coursework'));

        $this->form()->addElement('select',
                                   'roundingrule',
                                    get_string('roundingrule', 'mod_coursework'),
                                    $roundingoptions);
        $this->form()->addhelpbutton('roundingrule', 'roundingrule', 'mod_coursework');

        $this->form()->setType('roundingrule', PARAM_ALPHAEXT);
        $this->form()->setDefault('roundingrule', 'mid');
        $this->form()->disabledIf('roundingrule', 'automaticagreementstrategy', 'neq', 'average_grade');


    }

    private function add_enable_plagiarism_flag_field(){
        global $CFG;
        $moodle_form =& $this->_form;

        $options = array( 0 => get_string('no'), 1 => get_string('yes'));
        $moodle_form->addElement('select', 'plagiarismflagenabled', get_string('plagiarism_flag_enable', 'mod_coursework'), $options);
        $moodle_form->addHelpButton('plagiarismflagenabled', 'plagiarism_flag_enable', 'mod_coursework');
        $moodle_form->setDefault('plagiarismflagenabled', $CFG->coursework_plagiarismflag);
    }

}
