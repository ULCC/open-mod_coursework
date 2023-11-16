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

defined('MOODLE_INTERNAL') || die;

global $CFG, $DB, $PAGE;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/coursework/lib.php');

    $settings_header = new admin_setting_heading('settings_header','' ,get_string('settings_header', 'mod_coursework'));
    $settings->add($settings_header);

    // Set site-wide option for late submission
    $availability_header = new admin_setting_heading('availability_header', get_string('availability', 'mod_coursework'),'');
    $settings->add($availability_header);
    $allow_late_submission_name =  get_string('allowlatesubmissions', 'coursework');
    $allow_late_submission_description = get_string('allowlatesubmissions_desc', 'coursework');
    $options = array( 0 => get_string('no'), 1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('coursework_allowlatesubmissions',
                   $allow_late_submission_name, $allow_late_submission_description, 0,$options));


    // Set site-wide limit on submissions sizes.
    if (isset($CFG->maxbytes)) {
        $submissions_header = new admin_setting_heading('submissions_header', get_string('submissions', 'mod_coursework'),'');
        $settings->add($submissions_header);
        $configmaxbytesstring = get_string('configmaxbytes', 'coursework');
        $maximumsizestring = get_string('maximumsize', 'coursework');
        $maxbytessetting = new admin_setting_configselect('coursework_maxbytes',
                                                          $maximumsizestring,
                                                          $configmaxbytesstring,
                                                          1048576,
                                                          get_max_upload_sizes($CFG->maxbytes));
        $settings->add($maxbytessetting);
    }


    // Submissions
    $submissions_header = new admin_setting_heading('submissions_header', get_string('submissions', 'mod_coursework'),'');
    $settings->add($submissions_header);
    $options =  array( 0 => get_string('no'), 1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('coursework_plagiarismflag', get_string('plagiarism_flag_enable', 'mod_coursework'), get_string('plagiarism_flag_enable_desc', 'mod_coursework'), 0, $options));


    // Submission receipt
    $submissionreceipt_header = new admin_setting_heading('submissionreceipt_header', get_string('submissionreceipt', 'mod_coursework'),'');
    $settings->add($submissionreceipt_header);
    $options =  array( 0 => get_string('no'), 1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('coursework_allsubmissionreceipt', get_string('allsubmission', 'mod_coursework'), get_string('allsubmission_desc', 'mod_coursework'), 0, $options));



    // Blind marking
    $blindmarking_header = new admin_setting_heading('blindmarking_header', get_string('blindmarking', 'mod_coursework'),'');
    $settings->add($blindmarking_header);
    $blind_marking_name =  get_string('blindmarking', 'coursework');
    $blind_marking_description = get_string('blindmarking_desc', 'coursework');
    $options =  array( 0 => get_string('no'), 1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('coursework_blindmarking', $blind_marking_name, $blind_marking_description, 0, $options));
    $settings->add(new admin_setting_configcheckbox('coursework_forceblindmarking', get_string('forceblindmarking', 'mod_coursework'), get_string('forceblindmarking_desc', 'mod_coursework'), 0));

    // Assessor anonymity
    $assessoranonymity_header = new admin_setting_heading('assessoranonymity_header', get_string('assessoranonymity', 'mod_coursework'),'');
    $settings->add($assessoranonymity_header);
    $assessor_anonymity_name =  get_string('assessoranonymity', 'coursework');
    $assessor_anonymity_description = get_string('assessoranonymity_desc', 'coursework');
    $options =  array( 0 => get_string('no'), 1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('coursework_assessoranonymity', $assessor_anonymity_name, $assessor_anonymity_description, 0, $options));

    // Set site-wide options for when feedback is due.
    $weeks = array();
    for ($i = 1; $i <= 10; $i++) {
        $weeks[$i] = $i;
    }
    $feedbacktypes_header = new admin_setting_heading('feedbacktypes_header', get_string('feedbacktypes', 'mod_coursework'),'');
    $settings->add($feedbacktypes_header);
    $generalfeedbackstring = get_string('generalfeedback', 'coursework');
    $configgeneralfeedbackstring = get_string('configgeneralfeedback', 'coursework');
    $generalfeedbacksetting = new admin_setting_configselect('coursework_generalfeedback',
                                                             $generalfeedbackstring,
                                                             $configgeneralfeedbackstring,
                                                             2, $weeks);
    $settings->add($generalfeedbacksetting);

    // enable auto-release of individual feedback
    $individual_feedback_auto_release_name =  get_string('individual_feedback_auto_release', 'coursework');
    $individual_feedback_auto_release_name_description = get_string('individual_feedback_auto_release_desc', 'coursework');
    $options =  array( 0 => get_string('no'), 1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('coursework_auto_release_individual_feedback', $individual_feedback_auto_release_name, $individual_feedback_auto_release_name_description, 0, $options));
    $settings->add(new admin_setting_configcheckbox('coursework_forceauto_release_individual_feedback', get_string('forceautoauto_release_individual_feedback', 'mod_coursework'), get_string('forceautoauto_release_individual_feedback_desc', 'mod_coursework'), 0));


    $individualfeedbackstring = get_string('individualfeedback', 'coursework');
    $configindfeedbackstring = get_string('configindividualfeedback', 'coursework');
    $individualfeedbacksetting = new admin_setting_configselect('coursework_individualfeedback',
                                                                $individualfeedbackstring,
                                                                $configindfeedbackstring,
                                                                4, $weeks);
    $settings->add($individualfeedbacksetting);

    // Feedback release email
    $feedbackreleaseemail_name =  get_string('feedbackreleaseemail', 'coursework');
    $feedbackreleaseemail_description = get_string('feedbackreleaseemail_help', 'coursework');
    $options =  array( 0 => get_string('no'), 1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('coursework_feedbackreleaseemail', $feedbackreleaseemail_name, $feedbackreleaseemail_description, 1, $options));



    $day_reminder = array();
    for ($i = 2; $i <= 7; $i++) {
        $day_reminder[$i] = $i;
    }
    $studentreminder_header = new admin_setting_heading('studentreminder_header', get_string('studentreminder', 'mod_coursework'),'');
    $settings->add($studentreminder_header);
    $reminderstring = get_string('coursework_reminder', 'coursework');
    $confreminderstring = get_string('config_coursework_reminder', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_day_reminder', $reminderstring,
                       $confreminderstring, 7, $day_reminder));

    $secondreminderstring = get_string('second_reminder', 'coursework');
    $confsecondreminderstring = get_string('config_second_reminder', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_day_second_reminder', $secondreminderstring,
                                                  $confsecondreminderstring, 3, $day_reminder));


    // Sitewide message that students will see and agree to before submitting or editing.
    $termsagreement_header = new admin_setting_heading('termsagreement_header', get_string('termsagreement', 'mod_coursework'),'');
    $settings->add($termsagreement_header);
    $agree_terms_name = get_string('agreeterms', 'coursework');
    $agree_terms_description = get_string('agreetermsdescription', 'coursework');
    $options = array( 0 => get_string('no'), 1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('coursework_agree_terms',
                                                    $agree_terms_name, $agree_terms_description, 0,$options));

    $agree_terms_text = get_string('agreetermstext', 'coursework');
    $settings->add(new admin_setting_confightmleditor('coursework_agree_terms_text',
                                                      $agree_terms_text, '', ''));


    // Extensions
    $extensions_header =
        new admin_setting_heading('extensions_header', get_string('extensions', 'mod_coursework'), '');
    $settings->add($extensions_header);

    // Enable coursework individual extensions
    $individual_extension_name =  get_string('individual_extension', 'coursework');
    $individual_extension_description = get_string('individual_extension_desc', 'coursework');
    $options =  array( 0 => get_string('no'), 1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('coursework_individual_extension', $individual_extension_name, $individual_extension_description, 1, $options));


    // Allow people to specify a list of extension reasons here so that they can be quickly chosen
    $extension_list_label = get_string('extension_reasons', 'coursework');
    $extension_list_description = get_string('extension_reasons_desc', 'coursework');
    $settings->add(new admin_setting_configtextarea('coursework_extension_reasons_list',
                                                    $extension_list_label, $extension_list_description, ''));

    // maximum extension deadline
    $settings->add(new admin_setting_configtext('coursework_max_extension_deadline',get_string('maximum_extension_deadline', 'coursework'),
                                                                                    get_string('maximum_extension_deadline_desc', 'coursework'),
                                                                                    18, PARAM_INT, 2));

   // Default per page

    $options    =   array('3'=>'3', '10'=>'10', '20'=>'20', '30'=>'30', '40'=>'40', '50'=>'50', '100'=>'100');


    $grading_page_header = new admin_setting_heading('grading_page_header', get_string('grading_page', 'mod_coursework'),'');
    $settings->add($grading_page_header);

    $per_page =  get_string('per_page', 'coursework');
    $per_page_description = get_string('per_page_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_per_page', $per_page, $per_page_description, '10', $options));

    //automatic agreement delay

    $options    =   array('0'=>get_string('disabled', 'mod_coursework'));
    $options['1800']  =   get_string('timedminutes', 'mod_coursework','30');
    $options['3600']  = get_string('timedhour', 'mod_coursework','1');
    $options['7200']  = get_string('timedhours', 'mod_coursework','2');
    $options['18000']  = get_string('timedhours', 'mod_coursework','5');
    $options['43200']  = get_string('timedhours', 'mod_coursework','12');
    $options['86400']  = get_string('aday', 'mod_coursework');


    $grade_editing_header = new admin_setting_heading('grade_editing_header', get_string('grade_editing', 'mod_coursework'),'');
    $settings->add($grade_editing_header);

    $grade_editing_name =  get_string('grade_editing_enabled', 'coursework');
    $grade_editing_description = get_string('grade_editing_enabled_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_grade_editing', $grade_editing_name, $grade_editing_description, '0', $options));


    //deadline defaults
    $deadline_defaults_header = new admin_setting_heading('deadline_defaults_header', get_string('deadline_defaults', 'mod_coursework'), '');
    $settings->add($deadline_defaults_header);


    //marking deadline
    $options = array('0' => get_string('disabled', 'mod_coursework'));
    $options['7'] = get_string('oneweekoption', 'mod_coursework');
    $options['14'] = get_string('twoweeksoption', 'mod_coursework');
    $options['21'] = get_string('threeweeksoption', 'mod_coursework');
    $options['28'] = get_string('fourweeksoption', 'mod_coursework');
    $options['35'] = get_string('fiveweeksoption', 'mod_coursework');
    $options['42'] = get_string('sixweeksoption', 'mod_coursework');
    $marking_deadline_name = get_string('marking_deadline_default', 'coursework');
    $marking_deadline_description = get_string('marking_deadline_enabled_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_marking_deadline', $marking_deadline_name, $marking_deadline_description, '0', $options));

    //marking deadline
    $options = array('0' => get_string('disabled', 'mod_coursework'));
    $options['7'] = get_string('oneweekoption', 'mod_coursework');
    $options['14'] = get_string('twoweeksoption', 'mod_coursework');
    $options['21'] = get_string('threeweeksoption', 'mod_coursework');
    $options['28'] = get_string('fourweeksoption', 'mod_coursework');
    $options['35'] = get_string('fiveweeksoption', 'mod_coursework');
    $options['42'] = get_string('sixweeksoption', 'mod_coursework');

    $agreed_marking_deadline_name = get_string('agreed_marking_deadline_default', 'coursework');
    $agreed_marking_deadline_description = get_string('agreed_marking_deadline_default_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_agreed_marking_deadline', $agreed_marking_deadline_name, $agreed_marking_deadline_description, '0', $options));


    //start date
    $options = array('0' => get_string('disabled', 'mod_coursework'));
    $options['1'] = get_string('today', 'mod_coursework');

    $start_date_name = get_string('startdate', 'coursework');
    $start_date_description = get_string('start_date_enabled_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_start_date', $start_date_name, $start_date_description, '0', $options));

    //submission deadline
    $options = array('0' => get_string('disabled', 'mod_coursework'));
    $options['1'] = get_string('today', 'mod_coursework');
    $options['7'] = get_string('sevendays', 'mod_coursework');
    $options['14'] = get_string('fourteendays', 'mod_coursework');
    $options['31'] = get_string('onemonth', 'mod_coursework');

    $submission_deadline_name = get_string('submissiondeadline', 'coursework');
    $submission_deadline_description = get_string('submission_deadline_enabled_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_submission_deadline', $submission_deadline_name, $submission_deadline_description, '0', $options));

    // Assessor allocations
    $assessor_allocations_header = new admin_setting_heading('assessor_allocations_header_header', get_string('assessorallocations', 'mod_coursework'), '');
    $settings->add($assessor_allocations_header);

    $options =  array( 'username' => get_string('username'), 'email' => get_string('email'));
    $settings->add(new admin_setting_configselect('coursework_allocation_identifier',  get_string('allocationidentifier', 'coursework'), get_string('allocationidentifier_desc', 'coursework'), 'username', $options));

}
