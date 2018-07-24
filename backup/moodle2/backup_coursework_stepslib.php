<?php

class backup_coursework_activity_structure_step extends backup_activity_structure_step
{
    protected function define_structure()
    {
        global $DB;
        
        foreach(array('coursework_submissions',
                      'coursework_allocation_pairs',
                      'coursework_mod_set_members',
                      'coursework_sample_set_mbrs',
                      'coursework_extensions',
                      'coursework_person_deadlines') as $tablename)
        {
            $DB->execute("update {{$tablename}} set `allocatableuser`=0, `allocatablegroup`=0");
            $DB->execute("update {{$tablename}} set `allocatableuser`=`allocatableid` where allocatabletype='user'");
            $DB->execute("update {{$tablename}} set `allocatablegroup`=`allocatableid` where allocatabletype='group'");
        }
        
        $userinfo = $this->get_setting_value('userinfo');
        
        $coursework=new backup_nested_element('coursework',array('id'),
                                              array('formid',
                                                    'course',
                                                    'name',
                                                    'intro',
                                                    'introformat',
                                                    'timecreated',
                                                    'timemodified',
                                                    'grade',
                                                    'deadline',
                                                    'srsinclude',
                                                    'numberofmarkers',
                                                    'blindmarking',
                                                    'maxbytes',
                                                    'generalfeedback',
                                                    'individualfeedback',
                                                    'feedbackcomment',
                                                    'feedbackcommentformat',
                                                    'generalfeedbacktimepublished',
                                                    'courseworktype',
                                                    'assessorallocationstrategy',
                                                    'moderationenabled',
                                                    'allocationenabled',
                                                    'moderatorallocationstrategy',
                                                    'viewothersfeedback',
                                                    'autoreleasefeedback',
                                                    'retrospectivemoderation',
                                                    'studentviewcomponentfeedbacks',
                                                    'studentviewmoderatorfeedbacks',
                                                    'strictanonymity',
                                                    'studentviewfinalfeedback',
                                                    'studentviewcomponentgrades',
                                                    'studentviewfinalgrade',
                                                    'studentviewmoderatorgrade',
                                                    'strictanonymitymoderator',
                                                    'allowlatesubmissions',
                                                    'mitigationenabled',
                                                    'enablegeneralfeedback',
                                                    'maxfiles',
                                                    'filetypes',
                                                    'use_groups',
                                                    'grouping_id',
                                                    'allowearlyfinalisation',
                                                    'showallfeedbacks',
                                                    'startdate',
                                                    'samplingenabled',
                                                    'extensionsenabled',
                                                    'assessoranonymity',
                                                    'viewinitialgradeenabled',
                                                    'automaticagreement',
                                                    'automaticagreementrange',
                                                    'automaticagreementstrategy',
                                                    'feedbackreleaseemail',
                                                    'gradeeditingtime',
                                                    'markingdeadlineenabled',
                                                    'initialmarkingdeadline',
                                                    'agreedgrademarkingdeadline',
                                                    'markingreminderenabled',
                                                    'submissionnotification',
                                                    'personaldeadlineenabled',
                                                    'relativeinitialmarkingdeadline',
                                                    'relativeagreedmarkingdeadline',
                                                    'autopopulatefeedbackcomment',
                                                    'moderationagreementenabled',
                                                    'draftfeedbackenabled',
                                                    'processenrol',
                                                    'processunenrol'
                                                  ));


            $sample_strategies  =new backup_nested_element('coursework_sample_set_rules');

            $sample_strategy    =   new backup_nested_element('coursework_sample_set_rule',array('id'),
                                                                array('courseworkid',
                                                                     'sample_set_plugin_id',
                                                                     'ruleorder',
                                                                     'ruletype',
                                                                     'upperlimit',
                                                                     'lowerlimit',
                                                                     'stage_identifier'));

            $coursework->add_child($sample_strategies);

            $sample_strategies->add_child($sample_strategy);

            $sample_strategy->set_source_table('coursework_sample_set_rules',
                                        array('courseworkid'=>backup::VAR_PARENTID));

        if($userinfo)
        {

            $moderation_agreements  =   new backup_nested_element('coursework_mod_agreements');

            $moderation_agreement =   new backup_nested_element('coursework_mod_agreement', array('id'),
                                                    array(
                                                        "feedbackid",
                                                        "moderatorid",
                                                        "agreement",
                                                        "timecreated",
                                                        "timemodified",
                                                        "lasteditedby",
                                                        "modcomment",
                                                        "modecommentformat"
                                                    ));



            $feedbacks=new backup_nested_element('coursework_feedbacks');

            $feedback= new backup_nested_element('coursework_feedback',array('id'),
                                                 array(
                                                     "submissionid",
                                                     "assessorid",
                                                     "timecreated",
                                                     "timemodified",
                                                     "grade",
                                                     "cappedgrade",
                                                     "feedbackcomment",
                                                     "timepublished",
                                                     "lasteditedbyuser",
                                                     "isfinalgrade",
                                                     "ismoderation",
                                                     "feedbackcommentformat",
                                                     "entry_id",
                                                     "markernumber",
                                                     "stage_identifier",
                                                     "finalised"
                                                 ));

            $submissions=new backup_nested_element('coursework_submissions');

            $submission=new backup_nested_element('coursework_submission',array('id'),
                                                  array(
                                                      "courseworkid",
                                                      "userid",
                                                      "authorid",
                                                      "timecreated",
                                                      "timemodified",
                                                      "finalised",
                                                      "manualsrscode",
                                                      "createdby",
                                                      "lastupdatedby",
                                                      "allocatableid",
                                                      "allocatabletype",
                                                      'allocatableuser',
                                                      'allocatablegroup',
                                                      "firstpublished",
                                                      "lastpublished",
                                                      "timesubmitted"
                                                  ));
            $reminders=new backup_nested_element('coursework_reminders');

            $reminder=new backup_nested_element('coursework_reminder',array('id'),
                                                array(
                                                    "userid",
                                                    "coursework_id",
                                                    "remindernumber",
                                                    "extension"
                                                ));

            $pairs=new backup_nested_element('coursework_allocation_pairs');

            $pair=new backup_nested_element('coursework_allocation_pair',array('id'),
                                            array(
                                                "courseworkid",
                                                "assessorid",
                                                "manual",
                                                "moderator",
                                                "timelocked",
                                                "stage_identifier",
                                                "allocatableid",
                                                "allocatabletype",
                                                'allocatableuser',
                                                'allocatablegroup'
                                            ));

            $modsetrules=new backup_nested_element('coursework_mod_set_rules');

            $modsetrule=new backup_nested_element('coursework_mod_set_rule',array('id'),
                                                  array(
                                                      "courseworkid",
                                                      "rulename",
                                                      "ruleorder",
                                                      "upperlimit",
                                                      "lowerlimit",
                                                      "minimum"
                                                  ));

            $allocation_configs=new backup_nested_element('coursework_allocation_configs');

            $allocation_config=new backup_nested_element('coursework_allocation_config',array('id'),
                                                         array(
                                                             "courseworkid",
                                                             "allocationstrategy",
                                                             "assessorid",
                                                             "value",
                                                             "purpose"
                                                         ));

            $modsetmembers= new backup_nested_element('coursework_mod_set_members');

            $modsetmember=new backup_nested_element('coursework_mod_set_member',array('id'),
                                                    array(
                                                        "courseworkid",
                                                        "allocatableid",
                                                        "allocatabletype",
                                                        'allocatableuser',
                                                        'allocatablegroup',
                                                        "stage_identifier"
                                                    ));

            $extensions=new backup_nested_element('coursework_extensions');

            $extension=new backup_nested_element('coursework_extension', array('id'),
                                                 array(
                                                     "allocatableid",
                                                     "allocatabletype",
                                                     'allocatableuser',
                                                     'allocatablegroup',
                                                     "courseworkid",
                                                     "extended_deadline",
                                                     "pre_defined_reason",
                                                     "createdbyid",
                                                     "extra_information_text",
                                                     "extra_information_format"
                                                 ));


            $personal_deadlines=new backup_nested_element('coursework_person_deadlines');

            $personal_deadline=new backup_nested_element('coursework_person_deadline', array('id'),
                                                array(
                                                    "allocatableid",
                                                    'allocatableuser',
                                                    'allocatablegroup',
                                                    "allocatabletype",
                                                    "courseworkid",
                                                    "personal_deadline",
                                                    "createdbyid",
                                                    "timecreated",
                                                    "timemodified",
                                                    "lastmodifiedbyid"
                                                ));


            $sample_members  =   new backup_nested_element('coursework_sample_set_mbrs');

            $sample_member  =   new backup_nested_element('coursework_sample_set_mbr', array('id'),
                                                array(
                                                        "courseworkid",
                                                        "allocatableid",
                                                        "allocatabletype",
                                                        'allocatableuser',
                                                        'allocatablegroup',
                                                        "stage_identifier",
                                                        "selectiontype"
                                                ));


            //A coursework instance has submissions.
            $coursework->add_child($submissions);
            //Each coursework may have reminders
            $coursework->add_child($reminders);
            //And allocations pairs
            $coursework->add_child($pairs);
            //And moderation sets
            $coursework->add_child($modsetrules);
            //And a set of extensionsenabled
            $coursework->add_child($extensions);
            //And a set of personaldeadlines
            $coursework->add_child($personal_deadlines);
            //And a set of moderation rule sets
            $coursework->add_child($modsetmembers);
            //And allocation configs
            $coursework->add_child($allocation_configs);
            //And sample members
            $coursework->add_child($sample_members);
        
            //And submissions are made up from individual submission instances
            $submissions->add_child($submission);
            //Submissions have multiple feedback items
            $submission->add_child($feedbacks);

            //Feedbacks is a set of individual items
            $feedbacks->add_child($feedback);

            $feedback->add_child($moderation_agreements);
            $moderation_agreements->add_child($moderation_agreement);

            //as are reminders, pairs, extensions, modsets and modsetrules,
            // and allocation configs
            $reminders->add_child($reminder);
            $pairs->add_child($pair);
            $extensions->add_child($extension);
            $personal_deadlines->add_child($personal_deadline);
            $modsetrules->add_child($modsetrule);
            $modsetmembers->add_child($modsetmember);
            $sample_members->add_child($sample_member);
            $allocation_configs->add_child($allocation_config);

            $submission->set_source_table('coursework_submissions',
                                          array('courseworkid'=>backup::VAR_PARENTID));

            $feedback->set_source_table('coursework_feedbacks',
                                        array('submissionid'=>backup::VAR_PARENTID));

            $moderation_agreement->set_source_table('coursework_mod_agreements',
                                        array('feedbackid'=>backup::VAR_PARENTID));

            $reminder->set_source_table('coursework_reminder',
                                        array('coursework_id'=>backup::VAR_PARENTID));

            $pair->set_source_table('coursework_allocation_pairs',
                                    array('courseworkid'=>backup::VAR_PARENTID));

            $modsetrule->set_source_table('coursework_mod_set_rules',
                                          array('courseworkid'=>backup::VAR_PARENTID));

            $extension->set_source_table('coursework_extensions',
                                         array('courseworkid'=>backup::VAR_PARENTID));

            $personal_deadline->set_source_table('coursework_person_deadlines',
                                        array('courseworkid'=>backup::VAR_PARENTID));

            $modsetmember->set_source_table('coursework_mod_set_members',
                array('courseworkid'=>backup::VAR_PARENTID));

            $sample_member->set_source_table('coursework_sample_set_mbrs',
                                array('courseworkid'=>backup::VAR_PARENTID));

            $allocation_config->set_source_table('coursework_allocation_config',
                                                 array('courseworkid'=>backup::VAR_PARENTID));
                                        
            //Mark important foreign keys
            $feedback->annotate_ids('user','assessorid');
            $feedback->annotate_ids('user','lasteditedbyuser');
            $feedback->annotate_ids('user','markernumber');
        
            $submission->annotate_ids('user','userid');
            $submission->annotate_ids('user','createdby');
            $submission->annotate_ids('user','lastupdatedby');
            $submission->annotate_ids('user','allocatableuser');   
            $submission->annotate_ids('group','allocatablegroup');

            $reminder->annotate_ids('user','userid');

            $pair->annotate_ids('user','assessorid');
            $pair->annotate_ids('user','allocatableuser');   
            $pair->annotate_ids('group','allocatablegroup');
        
            $allocation_config->annotate_ids('user','assessorid');

            $modsetmember->annotate_ids('user','allocatableuser');   
            $modsetmember->annotate_ids('group','allocatablegroup');

            $extension->annotate_ids('user','allocatableuser');   
            $extension->annotate_ids('group','allocatablegroup');

            $personal_deadline->annotate_ids('user','allocatableuser');
            $personal_deadline->annotate_ids('group','allocatablegroup');

            $sample_member->annotate_ids('user','allocatableuser');
            $sample_member->annotate_ids('group','allocatablegroup');

            $moderation_agreement->annotate_ids('user','moderatorid');
            $moderation_agreement->annotate_ids('user','lasteditedby');


            $coursework->annotate_files('mod_coursework','feedback',null);
            $coursework->annotate_files('mod_coursework','submission',null);

        }

        $coursework->annotate_ids('grouping','grouping_id');

        $coursework->set_source_table('coursework',array('id'=>backup::VAR_ACTIVITYID));
        
        return $this->prepare_activity_structure($coursework);        
        
    }
}