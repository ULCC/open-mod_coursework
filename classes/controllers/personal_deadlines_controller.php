<?php

namespace mod_coursework\controllers;
use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\forms\personal_deadline_form;
use mod_coursework\models\coursework;
use mod_coursework\models\personal_deadline;
use mod_coursework\models\user;

/**
 * Class personal_deadline_controller is responsible for handling restful requests related
 * to the personal_deadline.
 *
 * @property \mod_coursework\framework\table_base deadline_extension
 * @property allocatable allocatable
 * @property personal_deadline_form form
 * @package mod_coursework\controllers
 */
class personal_deadlines_controller extends controller_base{




    protected function new_personal_deadline() {
        global $USER, $PAGE;



        $coursework_page_url = (empty($this->params['setpersonaldeadlinespage'])) ? $this->get_path('coursework', array('coursework' => $this->coursework)) :
            $this->get_path('set personal deadlines', array('coursework' => $this->coursework));


        $params = $this->set_default_current_deadline();
        
        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $this->personal_deadline);

        $params['allocatableid']       =        (!is_array($params['allocatableid']))      ?    $params['allocatableid']
            :    serialize($params['allocatableid'])   ;

        $PAGE->set_url('/mod/coursework/actions/personal_deadline/new.php', $params);
        $create_url = $this->get_router()->get_path('edit personal deadline');

        $this->form = new personal_deadline_form($create_url, array('coursework' => $this->coursework));

        $this->personal_deadline->setpersonaldeadlinespage      =   $this->params['setpersonaldeadlinespage'];
        $this->personal_deadline->multipleuserdeadlines         =   $this->params['multipleuserdeadlines'];

        $this->personal_deadline->allocatableid     =       $params['allocatableid'];

        $this->form->set_data($this->personal_deadline);

        
        if ($this->cancel_button_was_pressed()) {
            redirect($coursework_page_url);
        }
        if ($this->form->is_validated()) {

            $data = $this->form->get_data();

            if  (empty($data->multipleuserdeadlines)  )  {
                if (!$this->get_personal_deadline()) { // personal deadline doesnt exist
                    // add new
                    $data->createdbyid = $USER->id;
                    $this->personal_deadline = personal_deadline::build($data);
                    $this->personal_deadline->save();

                } else {
                    // update
                    $data->lastmodifiedbyid = $USER->id;
                    $data->timemodified = time();
                    $this->personal_deadline->update_attributes($data);
                }
            } else {


                    $allocatables       =       unserialize($data->allocatableid);

                    foreach($allocatables   as  $allocatableid)   {
                        $data->allocatableid    =   $allocatableid;
                        $data->id   =   '';
                        //$data->id               =   '';
                        $findparams = array(
                            'allocatableid' => $allocatableid,
                            'allocatabletype' => $data->allocatabletype,
                            'courseworkid' => $data->courseworkid,
                        );
                        $this->personal_deadline = personal_deadline::find_or_build($findparams);

                        if (empty($this->personal_deadline->personal_deadline)) { // personal deadline doesnt exist
                            // add new
                            $data->createdbyid = $USER->id;
                            $this->personal_deadline = personal_deadline::build($data);
                            $this->personal_deadline->save();


                        } else {
                            // update
                            $data->id   =   $this->personal_deadline->id;
                            $data->lastmodifiedbyid = $USER->id;
                            $data->timemodified = time();
                            $this->personal_deadline->update_attributes($data);
                        }

                    }



            }
            redirect($coursework_page_url);
        }

        $this->render_page('new');

    }


    /**
     * Set the deadline to default coursework deadline if the personal deadline was never given before
     * @return array
     */
    protected function set_default_current_deadline()    {
        $params = array(
            'allocatableid' => $this->params['allocatableid'],
            'allocatabletype' => $this->params['allocatabletype'],
            'courseworkid' => $this->params['courseworkid'],
        );

        //if the allocatableid is an array then the current page will probably be setting multiple the personal deadlines
        //we use the first element in the array to setup the personal deadline object
        $params['allocatableid']    =   (is_array($this->params['allocatableid']))  ? current($this->params['allocatableid'])  : $this->params['allocatableid']  ;

         $this->personal_deadline = personal_deadline::find_or_build($params);

        $params['allocatableid']    =   $this->params['allocatableid'];

            //if the allocatableid is an array then the current page will probably be setting multiple the personal deadlines
            // of multiple allocatable ids in which case set the personal deadline to the coursework default
            if (is_array($this->params['allocatableid']) || !$this->get_personal_deadline()) { // if no personal deadline then use coursework deadline
                $this->personal_deadline->personal_deadline = $this->coursework->deadline;

            }

        return $params;
    }

    /**
     * Get the personal deadline 
     * @return mixed
     */
    protected function get_personal_deadline(){
        global $DB;
        $params = array(
            'allocatableid' => $this->params['allocatableid'],
            'allocatabletype' => $this->params['allocatabletype'],
            'courseworkid' => $this->params['courseworkid'],
        );

        return $DB->get_record('coursework_person_deadlines', $params);
    }

    /**
     * @param $time
     * @return array
     * @throws \coding_exception
     * @throws \mod_coursework\exceptions\access_denied
     * @throws \moodle_exception
     * @throws \require_login_exception
     */
    public function insert_update($time){
        global $USER;
        if (!$this->validated($time)) {
            return [
                'error' => 1,
                'message' => 'The new deadline you chose has already passed. Please select appropriate deadline'
            ];
        }
        $this->coursework = coursework::find(['id'=>$this->params['courseworkid']]);
        require_login($this->coursework->course);
        $params = $this->set_default_current_deadline();

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $this->personal_deadline);
        $params['allocatableid']       =        (!is_array($params['allocatableid']))      ?    $params['allocatableid']
            :    serialize($params['allocatableid'])   ;

        $data = (object) $this->params;
        if (empty($data->multipleuserdeadlines)) {
            if (!$this->get_personal_deadline()) { // personal deadline doesnt exist
                // add new
                $data->createdbyid = $USER->id;
                $data->personal_deadline = strtotime($time);
                $this->personal_deadline = personal_deadline::build($data);
                $this->personal_deadline->save();
            } else {
                // update
                $data->lastmodifiedbyid = $USER->id;
                $data->personal_deadline = strtotime($time);
                $data->timemodified = time();
                $this->personal_deadline->update_attributes($data);
            }
        } else {
            $allocatables       =       unserialize($data->allocatableid);

            foreach ($allocatables   as  $allocatableid) {
                $data->allocatableid    =   $allocatableid;
                $data->id   =   '';
                //$data->id               =   '';
                $findparams = array(
                    'allocatableid' => $allocatableid,
                    'allocatabletype' => $data->allocatabletype,
                    'courseworkid' => $data->courseworkid,
                );
                $this->personal_deadline = personal_deadline::find_or_build($findparams);

                if (empty($this->personal_deadline->personal_deadline)) { // personal deadline doesnt exist
                    // add new
                    $data->createdbyid = $USER->id;
                    $this->personal_deadline = personal_deadline::build($data);
                    $this->personal_deadline->save();
                } else {
                    // update
                    $data->id   =   $this->personal_deadline->id;
                    $data->lastmodifiedbyid = $USER->id;
                    $data->timemodified = time();
                    $this->personal_deadline->update_attributes($data);
                }
            }
        }
        $timestamp = $this->personal_deadline->personal_deadline;
        $date = userdate($timestamp, '%a, %d %b %Y, %H:%M');
        return [
            'error' => 0,
            'time' => $date,
            'timestamp' => $timestamp
        ];
    }

    /**
     * @param $time
     * @return bool
     */
    protected function validated($time) {
        if(strtotime($time) <= time()) {
            return false;
        }
        return true;
    }
}
    
