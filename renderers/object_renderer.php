<?php
use mod_coursework\ability;
use mod_coursework\allocation\manager;
use mod_coursework\grade_judge;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\moderation;
use mod_coursework\models\user;
use mod_coursework\models\moderation_set_rule;
use mod_coursework\models\submission;
use mod_coursework\router;
use mod_coursework\warnings;
use mod_coursework\models\personal_deadline;
use mod_coursework\render_helpers\grading_report\cells;
global $CFG;

require_once($CFG->dirroot . '/lib/plagiarismlib.php');

/**
 * This deals with the specific objects that are part of the pages. The other renderer deals with the pages themselves.
 */
class mod_coursework_object_renderer extends plugin_renderer_base {


    /**
     * Renders a coursework feedback as a row in a table. This is for the grading report when we have
     * multiple markers and we want an AJAX pop up with details of the feedback. Also for the student view.
     *
     * @param feedback $feedback
     * @return string
     */
    public function render_feedback(feedback $feedback) {

        global $PAGE, $USER;

        $out = '';

        $submission = $feedback->get_submission();
        $coursework = $feedback->get_coursework();

        $table = new html_table();
        $table->attributes['class'] = 'feedback';
        $table->id = 'feedback_'. $feedback->id;

        // Header should say what sort of feedback it is.
        if ($feedback->is_agreed_grade()) {
            $title = get_string('finalfeedback', 'mod_coursework');
        } else if ($feedback->is_moderation()) {
            $title = get_string('moderatorfeedback', 'mod_coursework');
        } else {
            $a = $feedback->get_assessor_stage_no();
            $title = get_string('componentfeedback', 'mod_coursework', $a);
        }
        $header = new html_table_cell();
        $header->colspan = 2;
        $header->text = $title;
        // Student view is only for the student, who doesn't need to be told their own name.
        $header->text .= has_capability('mod/coursework:submit', $coursework->get_context()) ? '' :
            ': ' . $submission->get_allocatable_name();
        $table->head[] = $header;

        // Assessor who gave this feedback.
        $table_row = new html_table_row();
        $table_row->cells['left'] = get_string('assessor', 'mod_coursework');

        if (!has_capability('mod/coursework:submit', $coursework->get_context()) || is_siteadmin($USER->id) ){
            $table_row->cells['right'] =  $feedback->get_assesor_username();
        } else {

            if((!$submission->get_coursework()->sampling_enabled() || $submission->sampled_feedback_exists()) &&  $feedback->assessorid == 0 && $feedback->timecreated == $feedback->timemodified) {
                $table_row->cells['right'] = get_string('automaticagreement', 'mod_coursework');
            } else {
                $table_row->cells['right'] =  $feedback->display_assessor_name();
            }
        }
        $table->data[] = $table_row;

        // Grade row.
        $table_row = new html_table_row();

        $left_cell = new html_table_cell();
        $right_cell = new html_table_cell();

        $nameforgrade = get_string('provisionalgrade', 'mod_coursework');
        $left_cell->text = $nameforgrade;
        // For final feedback, students should see the moderated grade, not the one awarded by the final grader.

        $grade_judge = new grade_judge($coursework);
        $right_cell->text = $grade_judge->grade_to_display($feedback->get_grade());
        $right_cell->id = 'final_feedback_grade';

        $table_row->cells['left'] = $left_cell;
        $table_row->cells['right'] = $right_cell;
        $table->data[] = $table_row;

        // Feedback comment.
        $comment = $feedback->feedbackcomment;

        $table_row = new html_table_row();
        $left_cell = new html_table_cell();
        $right_cell = new html_table_cell();

        $left_cell->text = get_string('feedbackcomment', 'mod_coursework');
        $right_cell->text = $comment;
        $right_cell->id = 'final_feedback_comment';

        $table_row->cells['left'] = $left_cell;
        $table_row->cells['right'] = $right_cell;
        $table->data[] = $table_row;

        $table_row = new html_table_row();
        $left_cell = new html_table_cell();
        $right_cell = new html_table_cell();

        $files = $feedback->get_feedback_files();

        if ($files) {
            $left_cell->text = get_string('feedbackfiles', 'mod_coursework');
            $right_cell->text = $this->render_feedback_files(new mod_coursework_feedback_files($files));
            $right_cell->id = 'final_feedback_files';

            $table_row->cells['left'] = $left_cell;
            $table_row->cells['right'] = $right_cell;
            $table->data[] = $table_row;
        }

        // Rubric stuff if it's there

        if ($coursework->is_using_advanced_grading()) {
            $table_row = new html_table_row();
            $left_cell = new html_table_cell();
            $right_cell = new html_table_cell();

            $controller = $coursework->get_advanced_grading_active_controller();
            $left_cell->text = 'Advanced grading';
            $right_cell->text = $controller->render_grade($PAGE, $feedback->id, null, '', false);

            $table_row->cells['left'] = $left_cell;
            $table_row->cells['right'] = $right_cell;
            $table->data[] = $table_row;
        }

        $out .= html_writer::table($table);

        return $out;
    }




    /**
     * Renders a coursework moderation as a row in a table.
     *
     * @param moderation $moderation
     * @return string
     */
    public function render_moderation(moderation $moderation){


        $title =
            get_string('moderationfor', 'coursework', $moderation->get_submission()->get_allocatable_name());

        $out = '';
        $moderatedby = fullname(user::find($moderation->moderatorid));
        $lasteditedby = fullname(user::find($moderation->lasteditedby));

        $table = new html_table();
        $table->attributes['class'] = 'moderation';
        $table->id = 'moderation'. $moderation->id;
        $header = new html_table_cell();
        $header->colspan = 2;
        $header->text = $title;
        $table->head[] = $header;

        // Moderated by
        $table_row = new html_table_row();
        $left_cell = new html_table_cell();
        $right_cell = new html_table_cell();
        $left_cell->text = get_string('moderatedby', 'coursework' );
        $right_cell->text =  $moderatedby;
        $right_cell->id = 'moderation_moderatedby';

        $table_row->cells['left'] = $left_cell;
        $table_row->cells['right'] = $right_cell;
        $table->data[] = $table_row;

        // Last edited by
        $table_row = new html_table_row();
        $left_cell = new html_table_cell();
        $right_cell = new html_table_cell();
        $left_cell->text = get_string('lasteditedby', 'coursework');
        $right_cell->text = $lasteditedby . ' on ' .
                            userdate($moderation->timemodified, '%a, %d %b %Y, %H:%M');
        $right_cell->id = 'moderation_lasteditedby';

        $table_row->cells['left'] = $left_cell;
        $table_row->cells['right'] = $right_cell;
        $table->data[] = $table_row;

        // Moderation agreement
        $table_row = new html_table_row();
        $left_cell = new html_table_cell();
        $right_cell = new html_table_cell();
        $left_cell->text = get_string('moderationagreement', 'coursework');
        $right_cell->text = get_string($moderation->agreement, 'coursework');
        $right_cell->id = 'moderation_agreement';

        $table_row->cells['left'] = $left_cell;
        $table_row->cells['right'] = $right_cell;
        $table->data[] = $table_row;

        // Moderation comment
        $table_row = new html_table_row();
        $left_cell = new html_table_cell();
        $right_cell = new html_table_cell();
        $left_cell->text = get_string('comment', 'mod_coursework');
        $right_cell->text = $moderation->modcomment;
        $right_cell->id = 'moderation_comment';

        $table_row->cells['left'] = $left_cell;
        $table_row->cells['right'] = $right_cell;
        $table->data[] = $table_row;

        $out .= html_writer::table($table);

        return $out;
    }

    /**
     * Renders a feedback as a table row. We may want an empty one for the user to add their own feedback.
     *
     * @param mod_coursework_assessor_feedback_row|null $feedbackrow
     * @return \html_table_row
     */
    protected function render_mod_coursework_assessor_feedback_row(mod_coursework_assessor_feedback_row $feedbackrow) {

        /**
         * NOT USED!!!!!!
         */

        global $USER, $OUTPUT, $COURSE;

        $row = new html_table_row();

        // Row attributes

        if ($feedbackrow->get_assessor_id() == $USER->id) {
            $row->attributes['class'] = 'coursework_own_feedback';
        }
        // Unique identifier for testing.
        // We can't add ids to the row.
        // Also might be the same person marking many students so needs to have the student id.
        // Do not say 'student so that it's not obvious that this is a way that blind marking could be circumvented.
        $row->attributes['class'] =
            "feedback-{$feedbackrow->get_assessor_id()}-{$feedbackrow->get_allocatable()->id()} {$feedbackrow->get_stage()->identifier()}";

        $existing_feedback = $feedbackrow->get_feedback();

        // Assessor cell: name, image and edit link.

        $cell = new html_table_cell();
        $assessor = $feedbackrow->get_assessor();

        $cell->text = $assessor->picture();
        $cell->text .= ' &nbsp;';
        $profilelinkurl = new moodle_url('/user/profile.php', array('id' => $assessor->id(),
                                                                    'course' => $COURSE->id));
        $cell->text .= html_writer::link($profilelinkurl, $assessor->name());

        $row->cells['assessor'] = $cell;

        // Comment cell (includes edit feedback link)

        $cell = new html_table_cell();
        $cell->text = 'asdadas';
        // Edit feedback link.
        $submission = $feedbackrow->get_submission();
        $new_feedback = false;
        if (empty($existing_feedback)) {
            $params = array(
                'assessorid' => $assessor->id(),
                'stage_identifier' => $feedbackrow->get_stage()->identifier(),
            );
            if ($submission) {
                $params['submissionid'] = $submission->id;
            }
            $new_feedback = feedback::build($params);
        }

        $ability = new ability(user::find($USER), $feedbackrow->get_coursework());

        if ($existing_feedback && $ability->can('edit', $existing_feedback)) {

            $linktitle = get_string('edit');
            $icon = new pix_icon('edit', $linktitle, 'coursework', array('width' => '20px'));
            $link_id = "edit_feedback_" . $feedbackrow->get_feedback()->id;
            $link = $this->get_router()->get_path('edit feedback', array('feedback' => $feedbackrow->get_feedback()));
            $iconlink = $OUTPUT->action_icon($link, $icon, null, array('id' => $link_id));
            $cell->text .= $iconlink;
        } else if ($new_feedback && $ability->can('new', $new_feedback)) {

            // New
            $linktitle = "new_feedback";
            $icon = new pix_icon('edit', $linktitle, 'coursework', array('width' => '20px'));

            $new_feedback_params = array(
                'submission' => $feedbackrow->get_submission(),
                'assessor' => $feedbackrow->get_assessor(),
                'stage' => $feedbackrow->get_stage()
            );
            $link = $this->get_router()->get_path('new feedback', $new_feedback_params);
            $iconlink = $OUTPUT->action_icon($link, $icon, null, array('class' => "new_feedback"));
            $cell->text .= $iconlink;
        } else if ($existing_feedback && $ability->can('show', $existing_feedback)) {
            // Show - for managers and others who are reviewing the grades but who should
            // not be able to change them.

            $linktitle = get_string('viewfeedback', 'mod_coursework');
            $icon = new pix_icon('show', $linktitle, 'coursework', array('width' => '20px'));
            $link_id = "show_feedback_" . $feedbackrow->get_feedback()->id;
            $link = $this->get_router()->get_path('show feedback', array('feedback' => $feedbackrow->get_feedback()));
            $iconlink = $OUTPUT->action_icon($link, $icon, null, array('id' => $link_id));
            $cell->text .= $iconlink;

        }

        if (!is_null($feedbackrow->get_grade()) && $feedbackrow->has_feedback()) {
            $maxgrade = $feedbackrow->get_max_grade();
            $feedbackgrade = $feedbackrow->get_grade();
            $gradestring = $this->output_grade_as_string($feedbackgrade, $maxgrade);
            $cell->text .= '&nbsp;' . get_string('grade', 'coursework') . ": " . $gradestring;
        }

        $row->cells['feedbackcomment'] = $cell;

        // Feedback time submitted cell.

        $cell = new html_table_cell();
        if ($feedbackrow->has_feedback()) {
            $cell->text = $feedbackrow ? userdate($feedbackrow->get_time_modified(),'%a, %d %b %Y, %H:%M') : '';
        }
        $row->cells['timemodified'] = $cell;

        return $row;
    }

    /**
     * Outputs the files as a HTML list.
     *
     * @param mod_coursework_submission_files $files
     * @return string
     */
    public function render_submission_files(mod_coursework_submission_files $files) {

        $submission_files = $files->get_files();
        $files_array = array();

        foreach ($submission_files as $file) {
            $files_array[] = $this->make_file_link($files, $file);
        }

        $br = html_writer::empty_tag('br');
        $out = implode($br, $files_array);

        return $out;
    }

    /**
     * @param mod_coursework_feedback_files $files
     * @return string
     */

    public function render_feedback_files(mod_coursework_feedback_files $files) {

        $files_array = array();
        $submission_files = $files->get_files();
        foreach ($submission_files as $file) {
            $files_array[] = $this->make_file_link($files, $file, 'feedbackfile');
        }

        $br = html_writer::empty_tag('br');
        $out = implode($br, $files_array);

        return $out;
    }


    /**
     * Outputs the files as a HTML list.
     *
     * @param mod_coursework_submission_files $files
     * @param bool $with_resubmit_button
     * @return string
     */
    public function render_submission_files_with_plagiarism_links(mod_coursework_submission_files $files, $with_resubmit_button = true) {

        global $USER;

        $ability = new ability(user::find($USER), $files->get_coursework());

        $coursework = $files->get_coursework();
        $submission_files = $files->get_files();
        $submission = $files->get_submission();
        $files_array = array();

        foreach ($submission_files as $file) {

            $link = $this->make_file_link($files, $file);

            if ($ability->can('view_plagiarism', $submission)) {
                // With no stuff to show, $plagiarismlinks comes back as '<br />'.
                $link .= '<div class ="percent">'. $this->render_file_plagiarism_information($file, $coursework, $submission).'</div>';
            }

            if ($with_resubmit_button) {
                $link .= '<div class ="subbutton">'. $this->render_resubmit_to_plagiarism_button($coursework, $submission).'</div>';
            }

            $files_array[] = $link;
        }

        $br = html_writer::empty_tag('br');
        $out = implode($br, $files_array);

        return $out;
    }

    /**
     * Outputs the files as a HTML list.
     *
     * @param mod_coursework_submission_files $files
     * @return string
     */
    public function render_plagiarism_links($files) {

        global $USER;

        $ability = new ability(user::find($USER), $files->get_coursework());

        $coursework = $files->get_coursework();
        $submission_files = $files->get_files();
        $submission = $files->get_submission();
        $files_array = array();

        foreach ($submission_files as $file) {

            $link = '';

            if ($ability->can('view_plagiarism', $submission)) {
                // With no stuff to show, $plagiarismlinks comes back as '<br />'.
                $link = $this->render_file_plagiarism_information($file, $coursework, $submission);
            }

            $files_array[] = $link;
        }

        $br = html_writer::empty_tag('br');
        $out = implode($br, $files_array);

        return $out;
    }

    /**
     * Displays a coursework so that we can see the intro, deadlines etc at the top of view.php
     *
     * @param mod_coursework_coursework $coursework
     * @return string html
     */
    protected function render_mod_coursework_coursework(mod_coursework_coursework $coursework) {

        global $PAGE, $USER;

        // Show the details of the assessment (Name and introduction.
        $out = html_writer::tag('h2', $coursework->name);

        if (has_capability('mod/coursework:allocate', $coursework->get_context())) {
            $warnings = new warnings($coursework);
            $out .= $warnings->not_enough_assessors();
        }

        // Intro has it's own <p> tags etc.
        $out .= '<div class="description">';
        $out .= format_module_intro('coursework', $coursework, $coursework->get_coursemodule_id());
        $out .= '</div>';

        // Deadlines section.
        $out .= html_writer::tag('h3', get_string('deadlines', 'coursework'));
        $out .= $this->coursework_deadlines_table($coursework);

        $cangrade = has_capability('mod/coursework:addinitialgrade', $PAGE->context);
        $canpublish = has_capability('mod/coursework:publish', $PAGE->context);
        $is_published = $coursework->user_grade_is_published($USER->id);
        $allowed_to_add_general_feedback = has_capability('mod/coursework:addgeneralfeedback', $coursework->get_context());
        $canaddgeneralfeedback = has_capability('mod/coursework:addgeneralfeedback', $PAGE->context);
        // Show general feedback if it's there and the deadline has passed or general feedback's date is not enabled which means it should be displayed automatically
        if (($coursework->is_general_feedback_enabled() && $allowed_to_add_general_feedback && (time() > $coursework->generalfeedback || $cangrade || $canpublish || $is_published)) || !$coursework->is_general_feedback_enabled()) {
            $out .= html_writer::tag('h3', get_string('generalfeedback', 'coursework'));
            $out .= $coursework->feedbackcomment ? html_writer::tag('p',$coursework->feedbackcomment)
                :  html_writer::tag('p',get_string('nofeedbackyet', 'coursework'));

            // General feedback Add edit link.
            if ($canaddgeneralfeedback) {
                $title = ($coursework->feedbackcomment)? get_string('editgeneralfeedback', 'coursework') : get_string('addgeneralfeedback', 'coursework');
                $class = ($coursework->feedbackcomment)? 'edit-btn' : 'add-general_feedback-btn';
                $out .= html_writer::tag('p', '', array('id' => 'feedback_text'));
                $link = new moodle_url('/mod/coursework/actions/general_feedback.php',
                                       array('cmid' => $coursework->get_coursemodule_id(),
                                             'id' => $coursework->id));
                $out .= html_writer::link($link,
                                          $title,
                                          array('class' => $class));
                $out .= html_writer::empty_tag('br');
                $out .= html_writer::empty_tag('br');
            }
        }

        return $out;
    }

    /**
     * Makes the HTML table for allocating markers to students and returns it.
     *
     * @param mod_coursework_allocation_table $allocation_table
     * @return string
     */
    protected function render_mod_coursework_allocation_table(mod_coursework_allocation_table $allocation_table) {

        global $PAGE, $OUTPUT, $SESSION;

        $table_html =   $allocation_table->get_hidden_elements();

        $table_html .= '

            <table class="allocations display">
                <thead>
                <tr>

        ';


        $options    =   $allocation_table->get_options();

        $paging_bar = new paging_bar($allocation_table->get_participant_count(), $options['page'], $options['perpage'],
            $PAGE->url, 'page');

        $all = count($allocation_table->get_coursework()->get_allocatables());

        $records_per_page = array(3 => 3,
            10 => 10,
            20 => 20,
            30 => 30,
            40 => 40,
            50 => 50,
            100 => 100,
            $all => get_string('all', 'mod_coursework')); // for boost themes instead of 'all' we can put 0, however currently it is a bug

        $single_select_params = compact('sortby', 'sorthow', 'page');
        $single_select_params['page'] = '0';
        $select = new single_select($PAGE->url, 'per_page', $records_per_page, $options['perpage'], null);
        $select->label = get_string('records_per_page', 'coursework');
        $select->class = 'jumpmenu';
        $select->formid = 'sectionmenu';
        $table_html     .=  $OUTPUT->render($select);



        //get the hidden elements used for assessors and moderators selected on other pages;

        $allocatable_cell_helper = $allocation_table->get_allocatable_cell();
        $table_html .= '<th>';
        $table_html .= $allocatable_cell_helper->get_table_header($allocation_table->get_options());
        $table_html .= '</th>';

        $no = 0;
        foreach ($allocation_table->marking_stages() as $stage) {
            if ($stage->uses_allocation()) {
                $table_html .= '<th>';
                // pin all checkbox
                $checkbox_title = get_string('selectalltopin', 'coursework');
                if ($stage->allocation_table_header() == 'Assessor') {
                    $no++;
                    if ($stage->stage_has_allocation() ) {// has any pins
                        $table_html .= '<input type="checkbox" name="" id="selectall_' . $no . '" title = "' . $checkbox_title . '">';
                    }
                    $table_html .= $stage->allocation_table_header() . ' ' . $no;
                } else if ($allocation_table->get_coursework()->moderation_agreement_enabled()) {
                    //moderator header
                    if ($stage->stage_has_allocation() ) {// has any pins
                        $table_html .= '<input type="checkbox" name="" id="selectall_mod" title = "' . $checkbox_title . '">';
                    }
                    $table_html .= get_string('moderator', 'coursework');

                } else {
                    $table_html .= $stage->allocation_table_header();
                }
                $table_html .= '</th>';
            }
        }

        $table_html .= '
                </tr>
                </thead>
                <tbody>
        ';

        $rowdata = $allocation_table->get_table_rows_for_page();
        foreach ($rowdata as $row) {
            $table_html .= $this->render_allocation_table_row($row);
        }

        $table_html .= '
                </tbody>
            </table>
        ';
        //form save button.

        $attributes = array('name' => 'save',
            'type' => 'submit',
            'id' => 'save_manual_allocations_1',
            'value' => get_string('save', 'mod_coursework'));
        $table_html .= html_writer::empty_tag('input', $attributes);

        $table_html     .=  $OUTPUT->render($select);



        $table_html .=  $PAGE->get_renderer('mod_coursework', 'object')->render($paging_bar);

        return $table_html;
    }

    /**
     * Makes a single row for the HTML table and returns it. The row contains form elements, but if we try
     * to use the mforms library, we can't use the html_table library as we would have to hand code the
     * start and end of each table row in the form.
     *
     * @param mod_coursework_allocation_table_row $allocation_row
     * @return \html_table_row
     */
    protected function render_mod_coursework_allocation_table_row(mod_coursework_allocation_table_row $allocation_row) {

        $row = new html_table_row();
        $row->id = $allocation_row->get_allocatable()->type().'_'.$allocation_row->get_allocatable()->id();

        $allocatable_cell_helper = $allocation_row->get_allocatable_cell();

        $allocatable_cell = $allocatable_cell_helper->get_table_cell($allocation_row);
        $row->cells['allocatable'] = $allocatable_cell;

        $stages = $allocation_row->marking_stages();

        foreach ($stages as $stage) {
            $row->cells[$stage->identifier()] = $stage->get_allocation_table_cell($allocation_row->get_allocatable());
        }

        return $row;
    }


    /**
     * Outputs the buttons etc to choose and trigger the auto allocation mechanism. Do this as part of the main form so we
     * can choose some allocations, then click a button to auto-allocate the rest.
     * @param mod_coursework_allocation_widget $allocationwidget
     * @throws \coding_exception
     * @return string
     */
    public function render_mod_coursework_allocation_widget(mod_coursework_allocation_widget $allocationwidget) {

        global $OUTPUT;

        $lang_str = ($allocationwidget->get_coursework()->moderation_agreement_enabled())? 'allocateassessorsandmoderators':'allocateassessors';
        $html = html_writer::tag('h2',get_string($lang_str, 'mod_coursework'));

        $html .= '<div class="assessor-allocation-wrapper accordion">';

        $html .= html_writer::start_tag('h3', array('id' => 'assessor_allocation_settings_header'));
        $html .= get_string('assessorallocationstrategy', 'mod_coursework');
        //$html .= $OUTPUT->help_icon('allocationstrategy', 'mod_coursework');
        $html .= html_writer::end_tag('h3');

        $html .= '<div class="allocation-strategy"';
        // Allow allocation method to be changed.
        $html .= html_writer::label(get_string('allocationstrategy', 'mod_coursework'),'assessorallocationstrategy');


        $options = manager::get_allocation_classnames();
        $html .= html_writer::select($options,
                                     'assessorallocationstrategy',
                                     $allocationwidget->get_assessor_allocation_strategy(),
                                     '');

        // We want to allow the allocation strategy to add configuration options.
        $html .= html_writer::start_tag('div', array('class' => 'assessor-strategy-options-configs'));
        $html .= $this->get_allocation_strategy_form_elements($allocationwidget->get_coursework());
        $html .= html_writer::end_tag('div');
        $html .= "<br>";
        $attributes = array('id' => 'coursework_input_buttons');
        $html .= html_writer::start_tag('div', $attributes);
        // Spacer so we get the button underneath the form stuff.
        $attributes = array('class' => 'coursework_spacer');
        $html .= html_writer::start_tag('div', $attributes);
        $html .= html_writer::end_tag('div');




        // Save button.
        $attributes = array('name' => 'save',
            'type' => 'submit',
            'id' => 'save_assessor_allocation_strategy',
            'class' => 'coursework_assessor_allocation',
            'value' => get_string('apply', 'mod_coursework'));
        $html .= html_writer::empty_tag('input', $attributes);

        $attributes = array('name' => 'saveandexit',
            'type' => 'submit',
            'id' => 'save_and_exit_assessor_allocation_strategy',
            'class' => 'coursework_assessor_allocation',
            'value' => get_string('save_and_exit', 'mod_coursework'));
        $html .= html_writer::empty_tag('input', $attributes);
        $html .= html_writer::end_tag('div');
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @return router
     */
    protected function get_router() {
        return router::instance();
    }


    public function render_mod_coursework_sampling_set_widget(mod_coursework_sampling_set_widget $samplingwidget)   {

        global $OUTPUT, $DB;

        $html = html_writer::tag('h2',get_string('sampling', 'mod_coursework'));


        $html .= html_writer::start_tag('div', array('class'=>'assessor-sampling-wrapper accordion'));

        $html .= html_writer::start_tag('h3', array('id' => 'sampling_strategy_settings_header'));
        $html .= get_string('samplingstrategy', 'mod_coursework');
        $html .= html_writer::end_tag('h3');

        $html .= html_writer::start_tag('div', array('class'=>'sampling-rules'));


        // We want to allow the allocation strategy to add configuration options.


        $html .= html_writer::start_tag('div',array('class'=>'sampling-select'));

        $script =   "
            var samplingValidateHdl = Array();
        ";

        $html  .= html_writer::script($script);


        $table  =   new html_table();
        $table->attributes['class'] =   'sampling';
        $table->head    =   array('');

        $assessorheaders    =   array();

        for($i = 0; $i < $samplingwidget->get_coursework()->get_max_markers(); $i++)   {
            $assessorheaders[]    =   get_string('assessorheading','mod_coursework',$i+1);
        }

        $scale = "";

        if     ($samplingwidget->get_coursework()->grade > 0) {

            $comma  =   "";

                for($i=0;$i <=  $samplingwidget->get_coursework()->grade; $i++)   {
                    $scale .=   $comma.$i;
                    $comma  =   ",";
                }
        } else {
            $grade_scale    =   \grade_scale::fetch(array('id' => abs($samplingwidget->get_coursework()->grade)));
            $scale          =   $grade_scale->scale;
        }

        $html  .= "<input id='scale_values' type='hidden' value='".$scale."' />";


        $table->head  =   $assessorheaders;

        $assessor1cell  =   html_writer::start_tag('div',array('class'=>'samples_strategy'));
        $assessor1cell  .=  get_string('assessoronedefault','mod_coursework');
        $assessor1cell  .=  html_writer::end_tag('div');

        $columndata      =   array(new html_table_cell($assessor1cell));

        $percentage_options = array();

        for($i = 0;$i < 110; $i = $i + 10)   {
            $percentage_options[$i] = "{$i}%";
        }

        $javascript     =   false;

        for ($i = 2; $i <= $samplingwidget->get_coursework()->get_max_markers(); $i++)   {

            //create the secon

            $sampling_strategies    =   array('0' => get_string('sampling_manual','mod_coursework'),
                                              '1' => get_string('sampling_automatic','mod_coursework'));

            //check whether any rules have been saved for this stage
            $selected   =   ($samplingwidget->get_coursework()->has_automatic_sampling_at_stage('assessor_'.$i)) ? '1' : false;

            $sampling_cell   = html_writer::start_tag('div',array('class'=>'samples_strategy'));
            $sampling_cell   .= html_writer::label(get_string('sampletype', 'mod_coursework'), "assessor_{$i}_samplingstrategy");

            $sampling_cell   .=    html_writer::select($sampling_strategies,
                "assessor_{$i}_samplingstrategy",
                $selected,
                false,
                array('id'=>"assessor_{$i}_samplingstrategy",'class'=>"assessor_sampling_strategy sampling_strategy_detail"));

            $sampling_cell   .= html_writer::end_tag('div');

            if ($i ==  $samplingwidget->get_coursework()->get_max_markers()) $javascript = true;

            $graderules =

            $graderules = html_writer::start_tag('h4');
            $graderules .= get_string('graderules', 'mod_coursework');
            $graderules .= html_writer::end_tag('h4');


            $graderules .= $this->get_sampling_strategy_form_elements($samplingwidget->get_coursework(),$i,$javascript);


            $sampling_cell   .=     html_writer::div($graderules,'', array('id'=>"assessor_{$i}_automatic_rules"));

            $columndata[]       =  new html_table_cell($sampling_cell);
        }

        $table->data[]  =   $columndata;

          //= array($asessoronecell,$asessortwocell);

        $html  .=   html_writer::table($table);


        // End the form with save button.
        $attributes = array('name' => 'save_sampling',
            'type' => 'submit',
            'id' => 'save_manual_sampling',
            'value' => get_string('save', 'mod_coursework'));
        $html  .= html_writer::empty_tag('input', $attributes);

        /**
         *  Ok this is either some really clever or really hacky code depending on where you stand, time of day and your mood :)
         *  The following script creates a global Array var (samplingValidateHdl)that holds the names of the validation functions for each plugin
         *  (as  I write none but....) It also creates an event handler for the submit button. The event handler calls each of the
         *  functions defined in samplingValidateHdl  allowing the plugins to validate their own sections (function names to be called plugin_name_validation)
         *  returning 0 or 1 depending on whether and error was found. (Was that verbose...yeah...oh well) - ND
         */

        $script =   "

            $('#save_manual_sampling').on('click', function (e)   {

                validationresults   =   Array();

                $.each(samplingValidateHdl, function(i,functionname) {
                     validationresults.push(eval(functionname+'()'));
                })

                if (validationresults.lastIndexOf(1) != -1) e.preventDefault();
            })

        ";



        $html  .= html_writer::script($script);

        $html .= html_writer::end_tag('div');

        $html .= html_writer::end_tag('div');

        $html .= html_writer::end_tag('div');

        return $html;
    }


    private function sampling_strategy_column($samplingwidget,$suffix = '')     {

        $percentage_options = array();

        for($i = 0;$i < 110; $i = $i + 10)   {
            $percentage_options[$i] = "{$i}%";
        }

        //hidden input containing scale values
        $scale  =   array();
        $sampling_column  = "<input id='scale_values' type='hidden' value='".implode(',',$scale)."' />";

        $sampling_column    .=  html_writer::tag('br','');
        $sampling_column    .=  html_writer::tag('strong',get_string('selectrules','mod_coursework'));
        $sampling_column    .=  html_writer::tag('br','');

        $sampling_column  .= html_writer::start_tag('div');

        for($i = 0; $i < 1; $i++) {
            $sampling_column .= html_writer::start_tag('span', array('class' => "assessor_{$suffix}_grade_rules", 'id' => "assessor_{$suffix}_grade_rules"));

            $sampling_column .= html_writer::checkbox("assessor_{$suffix}_samplerules[]", 1, false, get_string('grade', 'mod_coursework'),
                array('id' => "assessor_{$suffix}_samplerules_{$i}", 'class'=>"assessor_{$suffix} sampling_strategy_detail"));

            $options = array('0' => get_string('percentagesign', 'mod_coursework'),
                '1' => get_string('gradescale', 'mod_coursework'));

            $sampling_column .= html_writer::select($options,
                "assessor_{$suffix}_sampletype[]",
                $samplingwidget->get_sampling_strategy(),
                false,
                array('id' => "assessor_{$suffix}_sampletype_{$i}", 'class'=>"grade_type assessor_{$suffix} sampling_strategy_detail"));

            $sampling_column .= html_writer::label(get_string('from', 'mod_coursework'), 'assessortwo_samplefrom[0]');

            $rule_options = $percentage_options;

            $sampling_column .= html_writer::select($rule_options,
                "assessor_{$suffix}_samplefrom[]",
                $samplingwidget->get_sampling_strategy(),
                false,
                array('id' => "assessor_{$suffix}_samplefrom_{$i}", 'class'=>"assessor_{$suffix} sampling_strategy_detail"));

            $sampling_column .= html_writer::label(get_string('to', 'mod_coursework'), "assessor_{$suffix}_sampleto[0]");

            $sampling_column .= html_writer::select($rule_options,
                "assessor_{$suffix}_sampleto[]",
                $samplingwidget->get_sampling_strategy(),
                false,
                array('id' => "assessor_{$suffix}_sampleto_{$i}", 'class'=>"assessor_{$suffix} sampling_strategy_detail"));


            $sampling_column .= html_writer::end_tag('span', '');
        }

        $sampling_column  .= html_writer::end_tag('div');


        $sampling_column      .=  html_writer::link('#',get_string('addgraderule','mod_coursework'),array('id'=>"assessor_{$suffix}_addgradderule", 'class'=>'addgradderule sampling_strategy_detail'));
        $sampling_column      .=  html_writer::link('#',get_string('removegraderule','mod_coursework'),array('id'=>"assessor_{$suffix}_removegradderule", 'class'=>'removegradderule sampling_strategy_detail'));


        $sampling_column    .=  html_writer::checkbox("assessor_{$suffix}_samplertopup",1,false,get_string('topupto','mod_coursework'),
            array('id'=>"assessor_{$suffix}_samplerules[]",'class'=>"assessor_{$suffix} sampling_strategy_detail"));



        $sampling_column   .= html_writer::select($percentage_options,
            "assessor_{$suffix}_sampletopup",
            $samplingwidget->get_sampling_strategy(),
            false,
            array('id'=>"assessor_{$suffix}_sampletopup", 'class' => "assessor_{$suffix} sampling_strategy_detail"));
        $sampling_column    .= html_writer::label(get_string('ofallstudents', 'mod_coursework'),'assessortwo_sampleto[]');

        return $sampling_column ;

    }

    /**
     * Deals with grades which may be unset as yet, or which may be scales.
     * @param $grade
     * @param $maxgrade
     * @throws coding_exception
     * @return float|string
     */
    private function output_grade_as_string($grade, $maxgrade) {

        global $DB;

        // String.
        $out = '';

        if ($maxgrade < -1) { // Coursework is graded with a scale.
            // TODO cache these.
            $scalegrade = -$maxgrade;
            $scale = $DB->get_record('scale', array('id' => ($scalegrade)));

            if ($scale) {
                $items = explode(',', $scale->scale);
                $out = $items[$grade - 1]; // Scales always start fom 1.
            }
        } else {
            if ($grade == -1 || $grade === false || is_null($grade)) {
                $out = get_string('nograde');
            } else {
                // Grade has been set, although it may be zero.
                $out = round($grade, 2);
            }
        }

        return $out;
    }

    /**
     * Outputs a rule object on screen so we can see what it does.
     *
     * @param moderation_set_rule $rule
     * @throws coding_exception
     * @return \html_table_row
     */
    protected function make_moderation_set_rule_row(moderation_set_rule $rule) {

        $row = new html_table_row();

        $rulecell = new html_table_cell();

        $numbers = new stdClass();
        $numbers->upperlimit = $rule->upperlimit;
        $numbers->lowerlimit = $rule->lowerlimit;
        $numbers->minimum = $rule->minimum;
        $rulecell->text .= get_string($rule->get_name() . 'desc', 'mod_coursework', $numbers);

        $row->cells[] = $rulecell;

        $controlscell = new html_table_cell();
        // Add a delete button. Ideally, we submit the whole form in case people have changed any bit of it.
        // Can intercept with AJAX later if needs be.
        $linktitle = get_string('delete');

        $attributes = array(
            'type' => 'submit',
            'name' => 'delete-mod-set-rule[' . $rule->id . ']',
            'value' => $linktitle
        );
        $controlscell->text .= html_writer::empty_tag('input', $attributes);
        $row->cells[] = $controlscell;

        return $row;
    }

    /**
     * Gives us the form elements that allow us to configure the allocation strategies.
     *
     * @param coursework $coursework
     * @return string HTML form elements
     */
    protected function get_allocation_strategy_form_elements($coursework) {

        global $CFG;

        $html = '';

        $classdir = $CFG->dirroot . '/mod/coursework/classes/allocation/strategy';
        $fullclasspaths = glob($classdir . '/*.php');
        foreach ($fullclasspaths as $fullclassname) {
            if (strpos($fullclassname, 'base') !== false) {
                continue;
            }
            preg_match('/([^\/]+).php/', $fullclassname, $matches);
            $classname = $matches[1];
            $full_class_name = '\mod_coursework\allocation\strategy\\' . $classname;
            // We want the elements from all the strategies so we can show/hide them.
            /* @var \mod_coursework\allocation\strategy\base $strategy */
            $strategy = new $full_class_name($coursework);

            $attributes = array(
                'class' =>  'assessor-strategy-options',
                'id' => 'assessor-strategy-' . $classname
            );
            // Hide this if it's not currently selected.
            $strategytype = 'assessorallocationstrategy';
            if ($classname !== $coursework->$strategytype) {
                $attributes['style'] = 'display:none';
            }
            $html .= html_writer::start_tag('div', $attributes);
            $html .= $strategy->add_form_elements('assessor');
            $html .= html_writer::end_tag('div');
        }

        return $html;
    }


    protected function get_sampling_strategy_form_elements($coursework,$assessor_number,$load_javascript=false) {

        global $CFG, $DB;

        $html = '';
        $javascript     =   '';
        $classdir = $CFG->dirroot . '/mod/coursework/classes/sample_set_rule/';

        $sampleplugins  =   $DB->get_records('coursework_sample_set_plugin',null,'pluginorder');



        //$fullclasspaths = glob($classdir . '/*.php');
        foreach ($sampleplugins as $plugin) {
        /*    if (strpos($fullclassname, 'base') !== false) {
                continue;
            }*/
            preg_match('/([^\/]+).php/', $classdir."/".$plugin->rulename.".php", $matches);
            $classname = $matches[1];
            $full_class_name = '\mod_coursework\sample_set_rule\\' . $classname;

            $sampling_rule = new $full_class_name($coursework);

            $html .= $sampling_rule->add_form_elements($assessor_number);

            if ($load_javascript)   $javascript   .=  $sampling_rule->add_form_elements_js($assessor_number);


        }

        return $html." ".$javascript;

    }


    /**
     * @param coursework $coursework
     * @param submission $submission
     * @throws coding_exception
     * @return string
     */
    protected function resubmit_to_plagiarism_button($coursework, $submission) {
        global $PAGE;
        $html = '';
        $html .= html_writer::start_tag('form',
                                                  array('action' => $PAGE->url,
                                                        'method' => 'POST'));
        $html .= html_writer::empty_tag('input',
                                                  array('type' => 'hidden',
                                                        'name' => 'submissionid',
                                                        'value' => $submission->id));
        $html .= html_writer::empty_tag('input',
                                                  array('type' => 'hidden',
                                                        'name' => 'id',
                                                        'value' => $coursework->get_coursemodule_id()));
        $plagiarism_plugin_names = array();
        foreach ($coursework->get_plagiarism_helpers() as $helper) {
            $plagiarism_plugin_names[] = $helper->human_readable_name();
        }
        $plagiarism_plugin_names = implode(' ', $plagiarism_plugin_names);

        $resubmit = get_string('resubmit', 'coursework', $plagiarism_plugin_names);
        $html .= html_writer::empty_tag('input',
                                                  array('type' => 'submit',
                                                        'value' => $resubmit,
                                                        'name' => 'resubmit'));
        $html .= html_writer::end_tag('form');
        return $html;
    }

    /**
     * @param stored_file $file
     * @param coursework $coursework
     * @return string
     */
    protected function render_file_plagiarism_information($file, $coursework) {

        $plagiarism_links_params = array(
            'userid' => $file->get_userid(),
            'file' => $file,
            'cmid' => $coursework->get_coursemodule_id(),
            'course' => $coursework->get_course(),
            'coursework' => $coursework->id,
            'modname' => 'coursework'
        );
        $plagiarsmlinks = plagiarism_get_links($plagiarism_links_params);

        return $plagiarsmlinks;
    }

    /**
     * @param mod_coursework_submission_files $files
     * @param stored_file $file
     * @param string $class_name
     * @return string
     */
    protected function make_file_link($files, $file, $class_name = 'submissionfile') {
        global $CFG;

        $url = "{$CFG->wwwroot}/pluginfile.php/{$file->get_contextid()}" .
            "/mod_coursework/{$files->get_file_area_name()}";
        $filename = $file->get_filename();
        $fileurl = $url . $file->get_filepath() . $file->get_itemid() . '/' . rawurlencode($filename);
        return html_writer::link($fileurl, $filename, array('class' => $class_name));
    }

    /**
     * @param coursework $coursework
     * @param submission $submission
     * @return string
     */
    protected function render_resubmit_to_plagiarism_button($coursework, $submission) {
        global $USER;

        $ability = new ability(user::find($USER), $coursework);
        $html = '';
        if ($coursework->plagiarism_enbled() && $ability->can('resubmit_to_plagiarism', $submission)) {
            // Show the resubmit to plagiarism button if the user is allowed to do this.
            $html .= $this->resubmit_to_plagiarism_button($coursework, $submission);
        }
        return $html;
    }

    /**
     * @param mod_coursework_coursework $coursework
     * @return string
     * @throws coding_exception
     */
    protected function coursework_deadlines_table(mod_coursework_coursework $coursework) {
        global $PAGE, $USER;

        $dealine_extension =
            \mod_coursework\models\deadline_extension::get_extension_for_student(user::find($USER), $coursework);

        $personal_deadline =
            \mod_coursework\models\personal_deadline::get_personal_deadline_for_student(user::find($USER), $coursework);

        $normaldeadline = $coursework->deadline;

        if ($personal_deadline){
            $normaldeadline = $personal_deadline->personal_deadline;
        }
        $deadline_header_text = get_string('deadline', 'coursework');
        if($coursework->personal_deadlines_enabled() && (!has_capability('mod/coursework:submit', $PAGE->context) || is_siteadmin($USER))){
            $deadline_header_text .= "<br>". get_string('default_deadline', 'coursework');
        }
        $deadline_date = '';

        if ($dealine_extension) {
            $deadline_date .= '<span class="crossed-out">';
            $deadline_date .= userdate($normaldeadline, '%a, %d %b %Y, %H:%M');
            $deadline_date .= '</span>';
        } else if ($coursework->has_deadline()) {
            $deadline_date .= userdate($normaldeadline,'%a, %d %b %Y, %H:%M');
        } else {
            $deadline_date .= get_string('nocourseworkdeadline', 'mod_coursework');
        }

        $deadline_message = '';
        if ($coursework->has_deadline()) {
            if ($coursework->allow_late_submissions()) {
                $latemessage = get_string('latesubmissionsallowed', 'mod_coursework');
                $lateclass = 'text-success';
            } else {
                $latemessage = get_string('nolatesubmissions', 'mod_coursework');
                $lateclass = $coursework->deadline_has_passed() ? 'text-error' : 'text-warning';
            }
            $latemessage .= ' ';
            $deadline_message = html_writer::start_tag('span', array('class' => $lateclass));
            $deadline_message .= $latemessage;
            $deadline_message .= html_writer::end_tag('span');
        }

        // Does the user have an extension?

        $deadline_extension_message = '';
        if ($dealine_extension) {
            $deadline_extension_message .= html_writer::start_tag('div');
            $deadline_extension_message .= '<span class="text-success">You have an extension!</span><br> Your deadine is: '.userdate($dealine_extension->extended_deadline).'';
            $deadline_extension_message .= html_writer::end_tag('div');
        }

        if ($coursework->has_deadline()) {
            $deadline_message .= html_writer::start_tag('div', array('class' => 'autofinalise_info'));
            $deadline_message .= ($coursework->personal_deadlines_enabled() && (!has_capability('mod/coursework:submit', $PAGE->context) || is_siteadmin($USER)))?
                get_string('personal_deadline_warning', 'mod_coursework') : get_string('deadline_warning', 'mod_coursework');
            $deadline_message .= html_writer::end_tag('div');
        }


        $table_html = '
        <table class="deadlines display">
          <tbody>
            <tr class="r0">
              <th >'.$deadline_header_text.'</th>
              <td >'. $deadline_date.'<br />
                '.$deadline_extension_message.'
                '. $deadline_message.'</td>
            </tr>
        ';

        if ($coursework->is_general_feedback_enabled() && $coursework->generalfeedback) {
            $general_feedback_header = get_string('generalfeedbackdeadline', 'coursework') . ': ';
            $general_feedback_deadline = $coursework->get_general_feedback_deadline();
            $general_feedback_deadline_message = $general_feedback_deadline ? userdate($general_feedback_deadline,'%a, %d %b %Y, %H:%M')
                : get_string('notset', 'coursework');

            $table_html .= '
                <tr class="r1">
                  <th>'. $general_feedback_header.'</th>
                  <td class="cell c1">'. $general_feedback_deadline_message.'</td>
                </tr>

            ';
        }

        if ($coursework->individualfeedback) {

            $individual_feedback_header = get_string('individualfeedback', 'coursework');
            $individual_feedback_deadline = $coursework->get_individual_feedback_deadline();
            $indivisual_feedback_message = $individual_feedback_deadline ? userdate($individual_feedback_deadline,'%a, %d %b %Y, %H:%M')
                : get_string('notset', 'coursework');

            $table_html .= '
                <tr class="r1">
                  <th>'. $individual_feedback_header.'</th>
                  <td class="cell c1">'. $indivisual_feedback_message.'</td>
                </tr>

            ';
        }

        $table_html .= '
            </tbody>
        </table>
        ';

        return $table_html;
    }

    /**
     * @param \mod_coursework\allocation\table\row\builder $allocation_row
     * @return string
     */
    private function render_allocation_table_row($allocation_row) {

        $row_html = '
            <tr id="'. $allocation_row->get_allocatable()->type() . '_' . $allocation_row->get_allocatable()->id().'">
        ';

        $allocatable_cell_helper = $allocation_row->get_allocatable_cell();
        $row_html .= $allocatable_cell_helper->get_table_cell($allocation_row);

        foreach ($allocation_row->marking_stages() as $stage) {
            if ($stage->uses_allocation() && $stage->identifier() != 'moderator') {
                $row_html .= $stage->get_allocation_table_cell($allocation_row->get_allocatable());
            }
        }

        // moderator
        if($allocation_row->get_coursework()->moderation_agreement_enabled()) {
            $row_html .= $stage->get_moderation_table_cell($allocation_row->get_allocatable());
        }

        $row_html .= '</tr>';

        return $row_html;
    }

    /**
     * Makes the HTML table for allocating markers to students and returns it.
     *
     * @param mod_coursework_personal_deadlines_table $personal_deadlines_table
     * @return string
     */
    protected function render_mod_coursework_personal_deadlines_table(mod_coursework_personal_deadlines_table $personal_deadlines_table){

        $coursework_page_url = $this->get_router()->get_path('coursework', array('coursework' => $personal_deadlines_table->get_coursework())) ;
        $table_html =   '<div class="return_to_page">'.html_writer::link($coursework_page_url,get_string('returntocourseworkpage','mod_coursework')).'</div>';

        $table_html .=   '<div class="alert">'.get_string('nopersonaldeadlineforextensionwarning','mod_coursework').'</div>';



        $usergroups =   $personal_deadlines_table->get_coursework()->get_allocatable_type();

        $table_html .=   '<div class="largelink">'.html_writer::link('#', get_string('setdateforselected','mod_coursework',$personal_deadlines_table->get_coursework()->get_allocatable_type()), array('id' => 'selected_dates')).'</div>';
        $table_html .=  '<br />';

        $url    =   $this->get_router()->get_path('edit personal deadline', array());

        $table_html .=   '<form  action="'.$url.'" id="coursework_personal_deadline_form" method="post">';


        $table_html .=  '<input type="hidden" name="courseworkid" value="'.$personal_deadlines_table->get_coursework()->id().'" />';
        $table_html .=  '<input type="hidden" name="allocatabletype" value="'.$personal_deadlines_table->get_coursework()->get_allocatable_type().'" />';
        $table_html .=  '<input type="hidden" name="setpersonaldeadlinespage" value="1" />';
        $table_html .=  '<input type="hidden" name="multipleuserdeadlines" value="1" />';


        $table_html .= '

            <table class="personal_deadline display">
                <thead>
                <tr>

        ';


        $allocatable_cell_helper = $personal_deadlines_table->get_allocatable_cell();
        $personaldeadlines_cell_helper = $personal_deadlines_table->get_personal_deadline_cell();
        $table_html .= '<th>';
        $table_html .= '<input type="checkbox" name="" id="selectall">';
        $table_html .= '</th>';
        $table_html .= '<th>';
        $table_html .= $allocatable_cell_helper->get_table_header($personal_deadlines_table->get_options());
        $table_html .= '</th>';
        $table_html .= '<th>';
        $table_html .= $personaldeadlines_cell_helper->get_table_header($personal_deadlines_table->get_options());
        $table_html .= '</th>';



        $table_html .= '
                </tr>
                </thead>
                <tbody>
        ';

        $rowdata = $personal_deadlines_table->get_rows();
        foreach ($rowdata as $row) {
            $table_html .= $this->render_personal_deadline_table_row($row);
        }

        $table_html .= '
                </tbody>
            </table>
        ';

        $table_html .=   '</form>';

        return $table_html;


    }

    /**
     * @param \mod_coursework\personal_deadline\table\row\builder $personal_deadline_row
     * @return string
     */
    private function render_personal_deadline_table_row($personal_deadline_row) {

        global $OUTPUT, $USER;

        $coursework     =   $personal_deadline_row->get_coursework();

        $new_personal_deadline_params = array(
            'allocatableid' => $personal_deadline_row->get_allocatable()->id(),
            'allocatabletype' => $personal_deadline_row->get_allocatable()->type(),
            'courseworkid' => $personal_deadline_row->get_coursework()->id,
        );

        //$personal_deadline = \mod_coursework\models\personal_deadline::find($new_personal_deadline_params);

        $personal_deadline =
            \mod_coursework\models\personal_deadline::get_personal_deadline_for_student(user::find($personal_deadline_row->get_allocatable()->id()), $coursework);



        if (!$personal_deadline) {
            $personal_deadline  =   \mod_coursework\models\personal_deadline::build($new_personal_deadline_params);
        }


        $ability = new ability(user::find($USER), $coursework);
        $disabledelement   =   (!$personal_deadline ||($personal_deadline && $ability->can('edit', $personal_deadline)) )   ?   ""    :  " disabled='disabled' ";


        $row_html = '<tr id="'. $personal_deadline_row->get_allocatable()->type() . '_' . $personal_deadline_row->get_allocatable()->id().'">';
        $row_html .= '<td>';
        $row_html .= '<input type="checkbox" name="allocatableid_arr['.$personal_deadline_row->get_allocatable()->id().']" id="date_'. $personal_deadline_row->get_allocatable()->type() . '_' . $personal_deadline_row->get_allocatable()->id().'" class="date_select" value="'.$personal_deadline_row->get_allocatable()->id().'" '.$disabledelement.' >';
        $row_html .=  '<input type="hidden" name="allocatabletype_'.$personal_deadline_row->get_allocatable()->id().'" value="'.$personal_deadline_row->get_allocatable()->type().'" />';
        $row_html .= '</td>';

        $new_personal_deadline_params = array(
            'allocatableid' => $personal_deadline_row->get_allocatable()->id(),
            'allocatabletype' => $personal_deadline_row->get_allocatable()->type(),
            'courseworkid' => $personal_deadline_row->get_coursework()->id,
            'setpersonaldeadlinespage'   => '1'
        );
        

        $allocatable_cell_helper = $personal_deadline_row->get_allocatable_cell();
        $personaldeadlines_cell_helper = $personal_deadline_row->get_personal_deadline_cell();
        $row_html .= $allocatable_cell_helper->get_table_cell($personal_deadline_row);
        $row_html   .= $personaldeadlines_cell_helper->get_table_cell($personal_deadline_row);
        $row_html .= '</tr>';

        return $row_html;
    }


}