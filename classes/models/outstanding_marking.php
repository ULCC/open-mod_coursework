<?php
namespace mod_coursework\models;

class outstanding_marking   {

    private     $day_in_secs;


    public function __construct()   {

        $this->day_in_secs             =   86400;
    }


    /**
     * @param $cwkrecord
     * @param $userid
     * @return int
     */
    public function get_to_grade_initial_count($cwkrecord,$userid){

        $coursework     =   new \mod_coursework\models\coursework($cwkrecord);

        $initialsubmissions =   array();

        if($this->should_get_to_mark_initial_grade_info($coursework->id,$userid)){

            if (!$coursework->has_multiple_markers()) {
                $initialsubmissions = $this->get_single_marker_initial_grade_submissions_to_mark($coursework->id, $userid,$coursework->allocation_enabled());

            } else if ($coursework->sampling_enabled() && !$coursework->allocation_enabled()) { //

                $initialsubmissions = $this->get_multiple_to_mark_sampled_initial_grade_submissions($coursework->id,$userid);

            } else {
                $initialsubmissions =   $this->get_multiple_to_mark_initial_grade_submissions($coursework->id,$userid,$coursework->get_max_markers(),$coursework->allocation_enabled());

            }
        }

        return  (!empty($initialsubmissions))   ?  count($initialsubmissions) : 0  ;
    }


    /**
     * @param $cwkrecord
     * @param $userid
     * @return int
     */
    public function get_to_grade_agreed_count($cwkrecord,$userid) {

        $coursework     =   new \mod_coursework\models\coursework($cwkrecord);

        $agreedsubmissions  =   array();

            //AGREED GRADE INFORMATION

            if ($this->should_get_to_mark_agreed_grade_info($coursework->id,$userid) && $coursework->has_multiple_markers())  {
                if (!$coursework->sampling_enabled()) {
                    $agreedsubmissions = $this->get_to_grade_agreed_grade_submissions($coursework->id,$coursework->get_max_markers());
                } else {
                    $agreedsubmissions = $this->get_to_grade_agreed_grade_sampled_submissions($coursework->id);
                }
            }

        return  (!empty($agreedsubmissions))    ?   count($agreedsubmissions)   :   0;
    }


    /**
     * @param $courseworkid
     * @param bool $userid
     * @param bool $allocationenabled
     * @return array
     */
    private function get_single_marker_initial_grade_submissions_to_mark($courseworkid, $userid=false, $allocationenabled=false)    {

        global  $DB;

        $sqlparams  =   array();
        $sqltable  =    "";
        $sqlextra   =   "";

        if ($allocationenabled)  {
            //we only have to check for submissions allocated to this user
            $sqltable  =   ", {coursework_allocation_pairs}  cap ";

            $sqlextra   =   "	
	                                    AND cap.courseworkid = cs.courseworkid
		                                AND cap.allocatableid = cs.allocatableid
	                                    AND cap.allocatabletype = cs.allocatabletype
	                                    AND cap.assessorid = :assessorid ";

            $sqlparams['assessorid']    =   $userid;
        }

        $sql =      "SELECT     cs.id as submissionid
                                 FROM       {coursework_submissions}    cs
                                 LEFT JOIN  {coursework_feedbacks}   f
                                 ON          cs.id = f.submissionid
                                 {$sqltable}
                                 WHERE     f.id IS NULL
                                 AND cs.finalised = 1
                                 AND cs.courseworkid = :courseworkid              
                                  {$sqlextra}                                  
                                 ";

        $sqlparams['courseworkid']      =   $courseworkid;

        return  $DB->get_records_sql($sql, $sqlparams);
    }


    /**
     * @param $courseworkid
     * @param $userid
     * @return array
     */
    private function get_multiple_to_mark_sampled_initial_grade_submissions($courseworkid,$userid)    {

        global  $DB;

        $countsamples = 'CASE WHEN a.id = NULL THEN 0 ELSE COUNT(a.id)+1 END';
        $sql    =   "     SELECT  *,
                                  $countsamples AS count_samples,
                                  COUNT(a.id) AS ssmID  FROM(
                                                  SELECT  cs.id AS csid, f.id AS fid, cs.allocatableid ,ssm.id, COUNT(f.id) AS count_feedback,
                                                      cs.courseworkid
                                                  FROM {coursework_submissions} cs  LEFT JOIN
                                                       {coursework_feedbacks} f ON f.submissionid= cs.id
                                                  LEFT JOIN {coursework_sample_set_mbrs} ssm
                                                  ON  cs.courseworkid = ssm.courseworkid AND cs.allocatableid =ssm.allocatableid
                                                  WHERE cs.courseworkid = :courseworkid

                                                  AND       cs.id NOT IN (SELECT      sub.id
                                                          FROM        {coursework_feedbacks} feed
                                                          JOIN       {coursework_submissions} sub ON sub.id = feed.submissionid
                                     WHERE assessorid = :subassessorid AND sub.courseworkid= :subcourseworkid)
                                                  GROUP BY cs.allocatableid, ssm.stage_identifier, f.id, cs.id, ssm.id
                                                ) a
                                   GROUP BY a.allocatableid, a.csid, a.fid, a.id, a.count_feedback, a.courseworkid
                                   HAVING (count_feedback < $countsamples  )";

        $sqlparams  =   array();
        $sqlparams['subassessorid']             =   $userid;
        $sqlparams['subcourseworkid']           =   $courseworkid;
        $sqlparams['courseworkid']              =   $courseworkid;


        return  $DB->get_records_sql($sql, $sqlparams);
    }


    /**
     * @param $courseworkid
     * @param $userid
     * @param $numberofmarkers
     * @param $allocationenabled
     * @return array
     */
    private function get_multiple_to_mark_initial_grade_submissions($courseworkid,$userid,$numberofmarkers,$allocationenabled)    {

        global      $DB;

        $sqlparams  =   array();
        $sqltable   =   '';
        $sqlextra   =   '';

        if ($allocationenabled)  {
            //we only have to check for submissions allocated to this user
            $sqltable  =   ", {coursework_allocation_pairs}  cap ";

            $sqlextra   =   "	
	                                    AND cap.courseworkid = cs.courseworkid
		                                AND cap.allocatableid = cs.allocatableid
	                                    AND cap.allocatabletype = cs.allocatabletype
	                                    AND cap.assessorid = :assessorid2 ";

            $sqlparams['assessorid2']    =   $userid;
        }


        $sql    =   "SELECT cs.id AS submissionid, COUNT(f.id) AS count_feedback
                                      FROM 	{coursework_submissions}	cs LEFT JOIN
                                            {coursework_feedbacks} f ON   cs.id = f.submissionid
                                            {$sqltable}
                                     WHERE cs.finalised = 1
                                       AND cs.courseworkid = :courseworkid                
                                          AND (f.assessorid != :assessorid OR f.assessorid IS NULL)
                                          {$sqlextra}
                                          AND cs.id NOT IN (SELECT      sub.id  FROM 
                                                                        {coursework_feedbacks} feed JOIN 
                                                                        {coursework_submissions} sub ON sub.id = feed.submissionid 
                                                                        WHERE assessorid = :subassessorid AND sub.courseworkid= :subcourseworkid)
                                          GROUP BY cs.id, f.id
                                          HAVING (COUNT(f.id) < :numofmarkers)";


        $sqlparams['subassessorid']     =   $userid;
        $sqlparams['subcourseworkid']   =   $courseworkid;
        $sqlparams['courseworkid']      =   $courseworkid;
        $sqlparams['numofmarkers']      =   $numberofmarkers;
        $sqlparams['assessorid']        =   $userid;


        return  $DB->get_records_sql($sql, $sqlparams);
    }


    /**
     * @param $courseworkid
     * @param $numberofmarkers
     * @return array
     */
    private function get_to_grade_agreed_grade_submissions($courseworkid,$numberofmarkers){

        global $DB;

        $sql = "SELECT cs.id as submissionid, COUNT(cs.id) AS count_feedback
                                      FROM 	{coursework_submissions}	cs ,
                                            {coursework_feedbacks} f 
                                     WHERE  f.submissionid= cs.id
                                        AND cs.finalised = 1
                                        AND cs.courseworkid = :courseworkid
                                        GROUP BY cs.id
                                        HAVING (COUNT(cs.id) = :numofmarkers)";


        $sqlparams['numofmarkers'] = $numberofmarkers;
        $sqlparams['courseworkid'] = $courseworkid;


        return $DB->get_records_sql($sql, $sqlparams);
    }


    /**
     * @param $courseworkid
     * @return array
     */
    private function get_to_grade_agreed_grade_sampled_submissions($courseworkid) {

        global  $DB;

        $countsamples = 'CASE WHEN a.id = NULL THEN 0 ELSE COUNT(a.id)+1 END';
        $sql        =   "SELECT  *,
                                  $countsamples AS count_samples,
                                   COUNT(a.id) AS ssmID  FROM(
                                                  SELECT f.id AS fid, cs.id AS csid, cs.allocatableid ,ssm.id, COUNT(f.id) AS count_feedback,
                                                      cs.courseworkid
                                                  FROM {coursework_submissions} cs  LEFT JOIN
                                                       {coursework_feedbacks} f ON f.submissionid= cs.id
                                                  LEFT JOIN {coursework_sample_set_mbrs} ssm
                                                  ON  cs.courseworkid = ssm.courseworkid AND cs.allocatableid =ssm.allocatableid
                                                  WHERE cs.courseworkid = :courseworkid
                                                  GROUP BY cs.allocatableid, ssm.stage_identifier, f.id, cs.id, ssm.id
                                                ) a
                                   GROUP BY a.allocatableid, a.csid, a.fid, a.id, a.count_feedback, a.courseworkid
                                   HAVING (count_feedback = $countsamples AND $countsamples > 1 );";    

        $sqlparams['courseworkid'] = $courseworkid;

        return $DB->get_records_sql($sql, $sqlparams);
    }


    /**
     * @param $course_id
     * @param $user_id
     * @return bool
     */
    private function has_agreed_grade($course_id,$user_id)     {

        $coursecontext  =   \context_course::instance($course_id);

        return  has_capability('mod/coursework:addagreedgrade',$coursecontext,$user_id) || has_capability('mod/coursework:addallocatedagreedgrade',$coursecontext,$user_id);
    }


    /**
     * @param $course_id
     * @param $user_id
     * @return bool
     */
    private function has_initial_grade($course_id,$user_id)     {

        $coursecontext  =   \context_course::instance($course_id);

        return  has_capability('mod/coursework:addinitialgrade',$coursecontext,$user_id);
    }


    /**
     * @param $courseworkid
     * @param $userid
     * @return bool
     */
    private function should_get_to_mark_initial_grade_info($courseworkid,$userid)    {

        $coursework     =   new \mod_coursework\models\coursework($courseworkid);

        //findout if the user can create an initial grade
        $user_has_initial_grade_capability =   $this->has_initial_grade($coursework->get_course()->id, $userid);

        return  $user_has_initial_grade_capability;
    }

    /**
     * @param $courseworkid
     * @param $userid
     * @return bool
     */
    private function should_get_to_mark_agreed_grade_info($courseworkid,$userid)    {

        $coursework     =   new \mod_coursework\models\coursework($courseworkid);

        //findout if the user can create an initial grade
        $user_has_agreed_grade_capability   =   $this->has_agreed_grade($coursework->get_course()->id, $userid);

        return  $user_has_agreed_grade_capability;

    }
}
