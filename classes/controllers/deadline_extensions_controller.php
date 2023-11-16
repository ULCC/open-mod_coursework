<?php

namespace mod_coursework\controllers;
use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\decorators\coursework_groups_decorator;
use mod_coursework\forms\deadline_extension_form;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\group;
use mod_coursework\models\user;

/**
 * Class deadline_extensions_controller is responsible for handling restful requests related
 * to the deadline_extensions.
 *
 * @property \mod_coursework\framework\table_base deadline_extension
 * @property allocatable allocatable
 * @property deadline_extension_form form
 * @package mod_coursework\controllers
 */
class deadline_extensions_controller extends controller_base {

    protected function show_deadline_extension() {
        global $USER, $PAGE;

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('show', $this->deadline_extension);

        $PAGE->set_url('/mod/coursework/actions/deadline_extensions/show.php', $this->params);

        $this->render_page('show');
    }

    protected function new_deadline_extension() {
        global $USER, $PAGE;

        $params = $this->set_default_current_deadline();

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('new', $this->deadline_extension);

        $PAGE->set_url('/mod/coursework/actions/deadline_extensions/new.php', $params);
        $create_url = $this->get_router()->get_path('create deadline extension');

        $this->form = new deadline_extension_form($create_url, array('coursework' => $this->coursework));
        $this->form->set_data($this->deadline_extension);

        $this->render_page('new');

    }

    protected function create_deadline_extension() {
        global $USER;

        $create_url = $this->get_router()->get_path('create deadline extension');
        $this->form = new deadline_extension_form($create_url, array('coursework' => $this->coursework));
        $coursework_page_url = $this->get_path('coursework', array('coursework' => $this->coursework));
        if ($this->cancel_button_was_pressed()) {
            redirect($coursework_page_url);
        }
        /**
         * @var deadline_extension $deadline_extension
         */
        if ($this->form->is_validated()) {
            $data = $this->form->get_data();
            $data->extra_information_text = $data->extra_information['text'];
            $data->extra_information_format = $data->extra_information['format'];
            $this->deadline_extension = deadline_extension::build($data);

            $ability = new ability(user::find($USER), $this->coursework);
            $ability->require_can('create', $this->deadline_extension);

            $this->deadline_extension->save();
            redirect($coursework_page_url);
        } else {
            $this->set_default_current_deadline();
            $this->render_page('new');
        }

    }

    protected function edit_deadline_extension() {
        global $USER, $PAGE;

        $params = array(
            'id' => $this->params['id'],
        );
        $this->deadline_extension = deadline_extension::find($params);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $this->deadline_extension);

        $PAGE->set_url('/mod/coursework/actions/deadline_extensions/edit.php', $params);
        $update_url = $this->get_router()->get_path('update deadline extension');

        $this->form = new deadline_extension_form($update_url, array('coursework' => $this->coursework));
        $this->deadline_extension->extra_information = array(
            'text' => $this->deadline_extension->extra_information_text,
            'format' => $this->deadline_extension->extra_information_format,
        );
        $this->form->set_data($this->deadline_extension);

        $this->render_page('edit');
    }

    protected function update_deadline_extension() {
        global $USER;

        $update_url = $this->get_router()->get_path('update deadline extension');
        $this->form = new deadline_extension_form($update_url, array('coursework' => $this->coursework));
        $coursework_page_url = $this->get_path('coursework', array('coursework' => $this->coursework));
        if ($this->cancel_button_was_pressed()) {
            redirect($coursework_page_url);
        }
        /**
         * @var deadline_extension $deadline_extension
         */

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('update', $this->deadline_extension);

        $values = $this->form->get_data();
        if ($this->form->is_validated()) {
            $values->extra_information_text = $values->extra_information['text'];
            $values->extra_information_format = $values->extra_information['format'];
            $this->deadline_extension->update_attributes($values);
            redirect($coursework_page_url);
        } else {
            $this->render_page('edit');
        }

    }

    /**
     * Set the deadline to default current deadline if the extension was never given before
     * @return array
     */
    protected function set_default_current_deadline()
    {
        global $DB;
        $params = array(
            'allocatableid' => $this->params['allocatableid'],
            'allocatabletype' => $this->params['allocatabletype'],
            'courseworkid' => $this->params['courseworkid'],
        );
        $this->deadline_extension = deadline_extension::build($params);
        // Default to current deadline
        // check for personal deadline first o
        if ($this->coursework->personaldeadlineenabled){
            $personal_deadline =  $DB->get_record('coursework_person_deadlines', $params);
            if ($personal_deadline) {
                $this->coursework->deadline = $personal_deadline->personal_deadline;
            }
        }
        $this->deadline_extension->extended_deadline = $this->coursework->deadline;
        return $params;
    }



    /**
     * Begin Ajax functions
     */
    public function ajax_submit_mitigation($data_params)
    {
        global $USER, $OUTPUT;
        $extended_deadline = false;
        $response = [];
        $this->coursework = coursework::find(['id' => $this->params['courseworkid']]);
        require_login($this->coursework->course);
        $params = $this->set_default_current_deadline();
        $ability = new ability(user::find($USER), $this->coursework);
        $errors = $this->validation($data_params);
        $data = (object) $data_params;
        if (!$errors) {
            if ($data->id > 0) {
                $this->deadline_extension = deadline_extension::find(['id' => $data->id]);
                $ability->require_can('edit', $this->deadline_extension);
                $data->createdbyid = $USER->id;
                $this->deadline_extension->update_attributes($data);
            } else {
                $this->deadline_extension = deadline_extension::build($data);
                $ability->require_can('new', $this->deadline_extension);
                $this->deadline_extension->save();
                $data_params['id'] = $this->deadline_extension->id;
            }

            $content = $this->table_cell_response($data_params);

            $response = [
                'error' => 0,
                'data' => $data_params,
                'content' => $content
            ];
            echo json_encode($response);
        } else {
            $response = [
                'error' => 1,
                'messages' => $errors
            ];

            echo json_encode($response);
        }
    }

    public function validation($data){
        global $CFG;
        $max_deadline = $CFG->coursework_max_extension_deadline;


        if ($this->coursework->personaldeadlineenabled && $personal_deadline = $this->personal_deadline()) {
            $deadline = $personal_deadline->personal_deadline;
        } else {
            $deadline = $this->coursework->deadline;
        }


        if ( $data['extended_deadline'] <= $deadline) {
            return $errors = 'The new deadline must be later than the current deadline';
        }

        return false;
    }

    public function submission_exists($data){
        global $DB;

        return  $DB->record_exists('coursework_submissions', array(
                            'courseworkid' => $data['courseworkid'],
                            'allocatableid' => $data['allocatableid'],
                            'allocatabletype' => $data['allocatabletype']
        ));
    }

    public function personal_deadline(){
        global $DB;

        $extensionid = optional_param('id', 0,  PARAM_INT);

        if ($extensionid != 0) {
            $ext =  $DB->get_record('coursework_extensions', array('id' => $extensionid));
            $allocatableid = $ext->allocatableid;
            $allocatabletype = $ext->allocatabletype;
            $courseworkid = $ext->courseworkid;
        } else {

            $allocatableid = required_param('allocatableid', PARAM_INT);
            $allocatabletype = required_param('allocatabletype', PARAM_ALPHANUMEXT);
            $courseworkid =  required_param('courseworkid', PARAM_INT);
        }

        $params = array(
            'allocatableid' => $allocatableid ?? 0,
            'allocatabletype' => $allocatabletype ?? 0,
            'courseworkid' => $courseworkid ?? 0,
        );

        return  $personal_deadline = $DB->get_record('coursework_person_deadlines', $params);
    }

    public function ajax_edit_mitigation($data_params) {
        global $USER;
        $response = [];
        if ($data_params['id'] > 0) {
            $this->coursework = coursework::find(['id' => $this->params['courseworkid']]);
            require_login($this->coursework->course);

            $ability = new ability(user::find($USER), $this->coursework);
            $deadline_extension = deadline_extension::find(['id' => $data_params['id']]);
            if (empty($deadline_extension)) {
                $response = [
                    'error' => 1,
                    'message' => 'This Deadline Extension does not exist!',
                ];
            } else {
                $ability->require_can('edit', $deadline_extension);
                $time_content = '';
                $time = '';
                if ($this->coursework->personaldeadlineenabled &&  $personal_deadline = $this->personal_deadline()) {
                    $time_content = 'Personal deadline: ' . userdate($personal_deadline->personal_deadline);
                    $time = date('d-m-Y H:i', $personal_deadline->personal_deadline);
                } else if ($this->coursework->deadline) {
                    // Current deadline for comparison
                    $time_content = 'Default deadline: ' . userdate($this->coursework->deadline);
                    $time = date('d-m-Y H:i', $this->coursework->deadline);
                }

                if(!empty($deadline_extension->extended_deadline) && $deadline_extension->extended_deadline > 0) {
                    $time = date('d-m-Y H:i', $deadline_extension->extended_deadline);
                }

                $deadline_extension_transform = [
                    'time_content' => $time_content,
                    'time'  => $time,
                    'text' => $deadline_extension->extra_information_text,
                    'allocatableid' => $deadline_extension->allocatableid,
                    'allocatabletype' => $deadline_extension->allocatabletype,
                    'courseworkid' => $deadline_extension->courseworkid,
                    'id'    => $deadline_extension->id,
                    'pre_defined_reason' => $deadline_extension->pre_defined_reason,
                ];
                $response = [
                    'error' => 0,
                    'data' => $deadline_extension_transform
                ];
            }
        } else {
            $response = [
                'error' => 1,
                'message' => 'ID can not be lower than 1!'
            ];
        }
        echo json_encode($response);
    }

    public function ajax_new_mitigation($data_params){
        global $USER, $DB;
        $response = [];
        $this->coursework = coursework::find(['id' => $this->params['courseworkid']]);
        require_login($this->coursework->course);

        $params = array(
            'allocatableid' => $this->params['allocatableid'],
            'allocatabletype' => $this->params['allocatabletype'],
            'courseworkid' => $this->params['courseworkid'],
        );

        $ability = new ability(user::find($USER), $this->coursework);
        $deadline_extension= deadline_extension::build($params);
        $ability->require_can('new', $deadline_extension);
        $time_content = '';
        $time = '';
        if ($this->coursework->deadline){
            $personal_deadline =  $DB->get_record('coursework_person_deadlines', $params);
            if ($personal_deadline) {
                $time_content = 'Personal deadline: ' . userdate($personal_deadline->personal_deadline);
                // $this->coursework->deadline = $personal_deadline->personal_deadline;
                $time = date('d-m-Y H:i', $personal_deadline->personal_deadline);
            } else {
                $time_content = 'Default deadline: ' . userdate($this->coursework->deadline);
                $time = date('d-m-Y H:i', $this->coursework->deadline);
            }
        }

        $deadline_extension_transform = [
            'time_content' => $time_content,
            'time'  => $time,
        ];

        $response = [
            'error' => 0,
            'data' => $deadline_extension_transform
        ];

        echo json_encode($response);
    }


    /**
     * function table_cell_response
     * @param array $data_params
     *
     * Generate html cell base on time_submitted_cell
     * @return html_string $content
     *
     */

    public function table_cell_response($data_params) {
        $participant = ($data_params['allocatabletype'] && $data_params['allocatabletype'] == 'group') ? group::find($data_params['allocatableid']) : user::find($data_params['allocatableid']);
        $coursework = ($this->coursework instanceof coursework_groups_decorator) ? $this->coursework->wrapped_object() : $this->coursework;
        if ($this->coursework->has_multiple_markers()) {
            $row_object = new \mod_coursework\grading_table_row_multi($coursework, $participant);
        } else {
            $row_object = new \mod_coursework\grading_table_row_single($coursework, $participant);
        }

        $time_submitted_cell = new  \mod_coursework\render_helpers\grading_report\cells\time_submitted_cell(array('coursework'=>$this->coursework));

        $content = $time_submitted_cell->prepare_content_cell($row_object);

        return $content;
    }


}