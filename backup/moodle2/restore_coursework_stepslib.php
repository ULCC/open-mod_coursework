<?php

defined('MOODLE_INTERNAL') || die();

class restore_coursework_activity_structure_step extends restore_activity_structure_step
{
    // Just a handy way to spit out debugging info while in the bowels of restore
    static function cheaplog($thing,$append=true)
    {
        if($append)
        {
            $append=FILE_APPEND;
        }

        file_put_contents('/tmp/cheap.log',print_r($thing,true)."\n---------------\n",$append);
    }

    /**
     * Define the structure of the restore workflow.
     *
     * @return restore_path_element $structure
     */
    protected function define_structure()
    {
        $paths = array();

        $paths[] = new restore_path_element('coursework', '/activity/coursework');
        $paths[] = new restore_path_element('coursework_sample_set_rule', '/activity/coursework/coursework_sample_set_rules/coursework_sample_set_rule');

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        if($userinfo)
        {
            // Define each element separated.
            // Note: when I started the code I didn't realise that the names were arbitrary
            //       and that the coursework_ prefix is not needed.
            //   It would be nice to go back and take these out here and in backup.
            //       But not essential
            $bits=array('submission'=>'coursework_submissions',
                        'feedback'=>'coursework_submissions/coursework_submission/coursework_feedbacks',
                        'reminder'=>'coursework_reminders',
                        'allocation_pair'=>'coursework_allocation_pairs',
                        'mod_set_rule'=>'coursework_mod_set_rules',
                        'allocation_config'=>'coursework_allocation_configs',
                        'mod_set_member'=>'coursework_mod_set_members',
                        'sample_set_mbr'=>'coursework_sample_set_mbrs',
                        'extension'=>'coursework_extensions',
                        'person_deadline'=>'coursework_person_deadlines',
                        'mod_agreement' => 'coursework_submissions/coursework_submission/coursework_feedbacks/coursework_feedback/coursework_mod_agreements');


            foreach($bits as $bit=>$bitpath)
            {
                $p=new restore_path_element("coursework_$bit","/activity/coursework/{$bitpath}/coursework_$bit");
                $paths[]=$p;
            }
        }

        return $this->prepare_activity_structure($paths);
    }

    protected function fixallocatable(&$data)
    {
        if(!empty($data->allocatableuser))
        {
            $data->allocatableid=$this->get_mappingid('user',$data->allocatableuser);
            $data->allocatabletype='user';
        }
        else
        {
            $data->allocatableid=$this->get_mappingid('group',$data->allocatablegroup);
            $data->allocatabletype='group';
        }
    }

    protected function process_coursework_submission($data)
    {
        global $DB;
        $data=(object)$data;
        $oldid=$data->id;

        $data->courseworkid=$this->get_new_parentid('coursework');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->createdby = $this->get_mappingid('user', $data->createdby);
        $data->lastupdatedby = $this->get_mappingid('user', $data->lastupdatedby);

        $this->fixallocatable($data);

        $this->updatedate(array('timemodified',
                                'timecreated',
                                'timesubmitted',
                                'firstpublished',
                                'lastpublished',),$data);

        $now=time();
        $this->set_defaults(array('timecreated'=>$now,
                                  'timemodified'=>$now,
                                  'firstpublished'=>null,
                                  'lastpublished'=>null,
                                  'timesubmitted'=>null,
                                  'finalised'=>0,
                                  'manualsrscode'=>''),
                            $data);

        if (!$DB->record_exists('coursework_submissions', array('courseworkid'=>$data->courseworkid, 'allocatableid' =>$data->allocatableid, 'allocatabletype' => $data->allocatabletype))) {

            $newitemid = $DB->insert_record('coursework_submissions', $data);
            $this->set_mapping('coursework_submission', $oldid, $newitemid);

            //Tell system how to map the old submission id to its new one.
            $this->set_mapping('coursework_submission', $oldid, $newitemid, false, null, $this->task->get_old_contextid());
        }

    }

    protected function process_coursework_feedback($data)
    {
        global $DB;

        $data=(object)$data;
        $oldid=$data->id;

        $data->submissionid=$this->get_mappingid('coursework_submission',$data->submissionid);
        $data->assessorid=$this->get_mappingid('user',$data->assessorid);
        $data->lasteditedbyuser=$this->get_mappingid('user',$data->lasteditedbyuser);
        $data->markernumber=$this->get_mappingid('user',$data->markernumber);

        $this->updatedate(array('timemodified',
                                'timecreated',
                                'timepublished'),$data);

        $this->check_grade('grade',$data);
        $this->check_grade('cappedgrade',$data);

        $now=time();
        $this->set_defaults(array('assessorid'=>0,
                                  'timecreated'=>$now,
                                  'timemodified'=>$now,
                                  'grade'=>'',
                                  'cappedgrade'=>'',
                                  'feedbackcomment'=>'',
                                  'timepublished'=>$now,
                                  'lasteditedbyuser'=>0,
                                  'isfinalgrade'=>0,
                                  'ismoderation'=>0,
                                  'feedbackcommentformat'=>FORMAT_HTML,
                                  'entry_id'=>0,
                                  'markernumber'=>0,
                                  'stage_identifier'=>'',
                                  'finalised'=>0),$data);

        $newitemid = $DB->insert_record('coursework_feedbacks', $data);

        $this->set_mapping('coursework_feedback', $oldid, $newitemid, false, null, $this->task->get_old_contextid());
    }

    protected function process_coursework_reminder($data)
    {
        $data=(object)$data;
        $oldid=$data->id;

        $data->coursework_id=$this->get_new_parentid('coursework');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $this->set_defaults(array('remindernumber'=>0),$data);

        global $DB;
        $newitemid = $DB->insert_record('coursework_reminder', $data);
    }

    protected function process_coursework_allocation_pair($data)
    {
        $data=(object)$data;
        $oldid=$data->id;

        $data->courseworkid=$this->get_new_parentid('coursework');
        $data->assessorid = $this->get_mappingid('user', $data->assessorid);
        $this->updatedate(array('timelocked'),$data);

        $this->fixallocatable($data);

        $this->set_defaults(array('assessorid'=>0,
                                  'manual'=>0,
                                  'moderator'=>0,
                                  'timelocked'=>time(),
                                  'stage_identifier'=>''),$data);

        global $DB;
        $newitemid = $DB->insert_record('coursework_allocation_pairs', $data);
    }

    protected function process_coursework_mod_set_rule($data)
    {
        $data=(object)$data;
        $oldid=$data->id;

        $data->courseworkid=$this->get_new_parentid('coursework');

        $this->set_defaults(array('rulename'=>'',
                                  'ruleorder'=>0,
                                  'upperlimit'=>0,
                                  'lowerlimit'=>0,
                                  'minimum'=>0),$data);

        global $DB;
        $newitemid = $DB->insert_record('coursework_mod_set_members', $data);
    }

    protected function process_coursework_sample_set_rule($data)
    {
        $data=(object)$data;
        $oldid=$data->id;

        $data->courseworkid=$this->get_new_parentid('coursework');

        $this->set_defaults(array('ruletype'=>'',
            'ruleorder'=>0,
            'upperlimit'=>0,
            'lowerlimit'=>0,
            'sample_set_plugin_id'=>0,
            'stage_identifier'=>''),$data);

        global $DB;
        $newitemid = $DB->insert_record('coursework_sample_set_rules', $data);
    }

    protected function process_coursework_allocation_config($data)
    {
        $data=(object)$data;
        $oldid=$data->id;

        $data->courseworkid=$this->get_new_parentid('coursework');
        $data->assessorid = $this->get_mappingid('user', $data->assessorid);

        $this->set_defaults(array('allocationstrategy'=>'',
                                  'value'=>0,
                                  'purpose'=>''),
                            $data);

        global $DB;
        $newitemid = $DB->insert_record('coursework_allocation_config', $data);
    }

    protected function process_coursework_mod_set_member($data)
    {
        $data=(object)$data;

        $data->courseworkid=$this->get_new_parentid('coursework');

        $this->fixallocatable($data);

        $this->set_defaults(array('stage_identifier'=>''),$data);

        global $DB;
        $newitemid = $DB->insert_record('coursework_mod_set_members', $data);
    }

    protected function process_coursework_sample_set_mbr($data)
    {
        $data=(object)$data;

        $data->courseworkid=$this->get_new_parentid('coursework');

        $this->fixallocatable($data);

        $this->set_defaults(array('allocatableid'=>0,
                                  'allocatabletype'=>'',
                                  'allocatableuser'=>0,
                                  'allocatablegroup'=>0,
                                  'stage_identifier'=>'',
                                  'selectiontype'=>''),$data);

        global $DB;
        $newitemid = $DB->insert_record('coursework_sample_set_mbrs', $data);
    }

    protected function process_coursework_extension($data)
    {
        $data=(object)$data;

        $data->courseworkid=$this->get_new_parentid('coursework');
        $data->createdbyid = $this->get_mappingid('user', $data->createdbyid);

        $this->fixallocatable($data);

        $this->updatedate(array('extended_deadline'),$data);

        $this->set_defaults(array('extended_deadline'=>0,
                                  'pre_defined_reason'=>'',
                                  'extra_information_text'=>'',
                                  'extra_information_format'=>FORMAT_HTML)
                            ,$data);

        global $DB;
        $newitemid = $DB->insert_record('coursework_extensions', $data);

    }

    protected function process_coursework_person_deadline($data)
    {
        $data=(object)$data;

        $data->courseworkid=$this->get_new_parentid('coursework');
        $data->createdbyid = $this->get_mappingid('user', $data->createdbyid);
        $data->lastmodifiedbyid=$this->get_mappingid('user',$data->lastmodifiedbyid);

        $this->fixallocatable($data);

        $this->updatedate(array('personal_deadline',
                                'timecreated',
                                'timemodified'),$data);

        $now=time();
        $this->set_defaults(array('personal_deadline'=>0,
                                  'timecreated'=>$now,
                                  'timemodified'=>0,
                                  'lastmodifiedbyid'=>0),$data);

        global $DB;
        $newitemid = $DB->insert_record('coursework_person_deadlines', $data);
    }





    protected function process_coursework_mod_agreement($data)
    {
        $data=(object)$data;

        $data->feedbackid=$this->get_new_parentid('coursework_feedback');
        $data->moderatorid = $this->get_mappingid('user', $data->moderatorid);
        $data->lastmodifiedby=$this->get_mappingid('user',$data->lastmodifiedby);

        $this->fixallocatable($data);

        $this->updatedate(array('timecreated',
                                'timemodified'),$data);

        $now=time();
        $this->set_defaults(array('timecreated'=>$now,
                                  'timemodified'=>$now,
                                  'lasteditedby'=>0,
                                  'modcomment'=>'',
                                  'modcommentformat'=>1),$data);

        global $DB;
        $newitemid = $DB->insert_record('coursework_mod_agreements', $data);
    }

    /**
     * Process a courswork restore.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_coursework($data)
    {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $this->updatedate(array('timemodified',
                                'timecreated',
                                'startdate',
                                'generalfeedbacktimepublished',
                                'deadline'),$data);

        $now=time();
        //Taken from install.xml
        $this->set_defaults(array('formid'=>0,
                                  'course'=>0,
                                  'name'=>'',
                                  'intro'=>'',
                                  'introformat'=>FORMAT_HTML,
                                  'timecreated'=>$now,
                                  'timemodified'=>$now,
                                  'grade'=>0,
                                  'deadline'=>0,
                                  'srsinclude'=>0,
                                  'numberofmarkers'=>1,
                                  'blindmarking'=>1,
                                  'maxbytes'=>100000,
                                  'generalfeedback'=>0,
                                  'individualfeedback'=>0,
                                  'feedbackcomment'=>'',
                                  'feedbackcommentformat'=>FORMAT_HTML,
                                  'generalfeedbacktimepublished'=>0,
                                  'courseworktype'=>0,
                                  'assessorallocationstrategy'=>'equal',
                                  'moderationenabled'=>0,
                                  'allocationenabled'=>0,
                                  'moderatorallocationstrategy'=>0,
                                  'viewothersfeedback'=>0,
                                  'autoreleasefeedback'=>0,
                                  'retrospectivemoderation'=>0,
                                  'studentviewcomponentfeedbacks'=>0,
                                  'studentviewmoderatorfeedbacks'=>0,
                                  'strictanonymity'=>0,
                                  'studentviewfinalfeedback'=>1,
                                  'studentviewcomponentgrades'=>1,
                                  'studentviewfinalgrade'=>1,
                                  'studentviewmoderatorgrade'=>0,
                                  'strictanonymitymoderator'=>0,
                                  'allowlatesubmissions'=>0,
                                  'mitigationenabled'=>0,
                                  'enablegeneralfeedback'=>0,
                                  'maxfiles'=>1,
                                  'filetypes'=>'',
                                  'use_groups'=>0,
                                  'grouping_id'=>0,
                                  'allowearlyfinalisation'=>0,
                                  'showallfeedbacks'=>0,
                                  'startdate'=>0,
                                  'samplingenabled'=>0,
                                  'extensionsenabled'=>0,
                                  'assessoranonymity'=>0,
                                  'viewinitialgradeenabled'=>0,
                                  'automaticagreement'=>0,
                                  'automaticagreementrange'=>0,
                                  'automaticagreementstrategy'=>'',
                                  'feedbackreleaseemail'=>0,
                                    'gradeeditingtime'=>0,
                                    'markingdeadlineenabled'=>0,
                                    'initialmarkingdeadline'=>0,
                                    'agreedgrademarkingdeadline'=>0,
                                    'markingreminderenabled'=>0,
                                    'submissionnotification'=>'',
                                    'extension'=>0,
                                  'relativeinitialmarkingdeadline'=>0,
                                  'relativeagreedmarkingdeadline'=>0,
                                  'autopopulatefeedbackcomment'=>0,
                                  'moderationagreementenabled'=>0,
                                  'draftfeedbackenabled'=>0,
                                    'processenrol'=>0,
                                    'processunenrol'=>0), $data);

        $this->check_grade('grade',$data);

        $newitemid = $DB->insert_record('coursework', $data);

        $this->apply_activity_instance($newitemid);

    }

    protected function check_grade($field,&$data)
    {
        if ($data->$field < 0) // Scale found, get mapping.
        {
            $data->$field = -($this->get_mappingid('scale', abs($data->$field)));
        }
    }

    protected function set_defaults($fields,&$data)
    {
        foreach($fields as $name=>$default)
        {
            if(!isset($data->$name))
            {
                $data->$name=$default;
            }
        }
    }

    protected function updatedate($fields,&$data)
    {
        foreach($fields as $field)
        {
            $data->$field=$this->apply_date_offset($data->$field);
        }
    }

    protected function after_execute()
    {
        global $DB;
        $this->add_related_files('mod_coursework', 'submission', 'coursework_submission');
        $this->add_related_files('mod_coursework', 'feedback', 'coursework_feedback');

        //Fixup names
        $fs=get_file_storage();
        $ctx=context::instance_by_id($this->task->get_contextid());

        $files=$fs->get_area_files($ctx->id,'mod_coursework','submission'); //Array of stored_file

        foreach($files as $file)
        {
            if(!$file->is_directory())
            {
                $itemid=$file->get_itemid();

                $entry=$DB->get_record('coursework_submissions',
                                       array('id'=>$itemid));

                $submission = \mod_coursework\models\submission::find($entry->id);
                $submission->rename_files(); // use cw function to handle file renaming as submission may have few files

            }
        }
    }
}
