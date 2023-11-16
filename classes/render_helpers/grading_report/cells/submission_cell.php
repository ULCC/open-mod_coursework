<?php

namespace mod_coursework\render_helpers\grading_report\cells;
use coding_exception;
use html_writer;
use mod_coursework\ability;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework_submission_files;
use moodle_url;
use pix_icon;

/**
 * Class feedback_cell
 */
class submission_cell extends cell_base {

    /**
     * @param grading_table_row_base $rowobject
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($rowobject) {

        global $USER, $OUTPUT, $DB, $CFG;

        $content = '';

        $ability = new ability(user::find($USER), $this->coursework);

        if ($rowobject->has_submission() && $ability->can('show', $rowobject->get_submission())) {
            // The files and the form to resubmit them.
            $submission_files = $rowobject->get_submission_files();
            if ($submission_files) {
                $content .= $this->get_renderer()->render_submission_files(new mod_coursework_submission_files($submission_files));
            }

            if ($ability->can('revert', $rowobject->get_submission())) {
                $url = new moodle_url('/mod/coursework/actions/revert.php',
                                      array('cmid' => $rowobject->get_course_module_id(),
                                            'submissionid' => $rowobject->get_submission_id()));
                $content .= html_writer::empty_tag('br');
                $revertstring = get_string('revert', 'coursework');
                $content .= html_writer::link($url, $revertstring);
            }
        } else {
            $content .= $ability->get_last_message();
        }

        $ability = new ability(user::find($USER), $rowobject->get_coursework());

        $submission_on_behalf_of_allocatable = submission::build(array(
                                                                     'allocatableid' => $rowobject->get_allocatable()
                                                                         ->id(),
                                                                     'allocatabletype' => $rowobject->get_allocatable()
                                                                         ->type(),
                                                                     'courseworkid' => $rowobject->get_coursework()->id,
                                                                     'createdby' => $USER->id,
                                                                 ));


        if (($rowobject->get_submission()&& !$rowobject->get_submission()->finalised)
            || !$rowobject->get_submission()) {

            if ($ability->can('new', $submission_on_behalf_of_allocatable) && (!$rowobject->get_coursework()->has_deadline()
                    || $rowobject->get_coursework()->allow_late_submissions() || ($rowobject->get_personal_deadlines() >= time() || ($rowobject->has_extension() && $rowobject->get_extension()->extended_deadline > time())))) {

                // New submission on behalf of button

                $url = $this->get_router()
                    ->get_path('new submission', array('submission' => $submission_on_behalf_of_allocatable), true);

                $label =
                    'Submit on behalf';

                $content .= $OUTPUT->action_link($url,
                                                 $label,
                                                 null,
                                                 array('class' => 'new_submission'));
            } else if ($rowobject->has_submission() &&
                       $ability->can('edit', $rowobject->get_submission()) &&
                       !$rowobject->has_feedback() ) {

                // Edit submission on behalf of button

                $url = $this->get_router()
                    ->get_path('edit submission', array('submission' => $rowobject->get_submission()), true);

                $label =
                    'Edit submission on behalf of this ' . ($rowobject->get_coursework()
                        ->is_configured_to_have_group_submissions() ?
                        'group' : 'student');
                $icon = new pix_icon('edit', $label, 'coursework');

                $content .= ' '.$OUTPUT->action_icon($url,
                                                 $icon,
                                                 null,
                                                 array('class' => 'edit_submission'));
            }
        }

        // File id
        if ($rowobject->has_submission()) {
            $content .= html_writer::empty_tag('br');
            $content .= $rowobject->get_filename_hash();
        }

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {

        $tablename  =   (isset($options['tablename']))  ? $options['tablename']  : ''  ;

        $fileid = $this->helper_sortable_heading(get_string('tableheadid', 'coursework'),
                                                 'hash',
                                                  $options['sorthow'],
                                                  $options['sortby'],
                                                  $tablename);

        return get_string('tableheadfilename', 'coursework') .' /<br>' . $fileid ;
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'tableheadfilename';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'submission';
    }
}