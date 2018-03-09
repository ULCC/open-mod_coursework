<?php

namespace mod_coursework;
use context;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use stdClass;

/**
 * Class containing all logic for the coursework cron functionality
 */
class cron {

    /**
     * Email to be sent to a user
     */
    const EMAIL_TYPE_USER = 'user';

    /**
     * Email to be sent to an admin
     */
    const EMAIL_TYPE_ADMIN = 'admin';

    /**
     * Standard Moodle API function to get things going
     *
     * @return bool
     */
    public static function run() {
        echo "Starting coursework cron functions...\n";
        self::finalise_any_submissions_where_the_deadline_has_passed();
        self::send_reminders_to_students();
       // self::send_first_reminders_to_admins(); #90211934
        self::autorelease_feedbacks_where_the_release_date_has_passed();
        return true;
    }

    /**
     * Function to be run periodically according to the moodle cron
     * This function searches for things that need to be done, such
     * as sending out mail, toggling flags etc ...
     *
     * @todo this really needs refactoring :(
     *
     * @return boolean
     **/
    private static function send_reminders_to_students() {

        global $CFG, $DB;

        $counts = array(
            'emails' => 0,
            'users' => 0
        );

        $userswhoneedreminding = array();

        $raw_courseworks = $DB->get_records('coursework');
        foreach ($raw_courseworks as $raw_coursework) {
            /**
             * @var coursework $coursework
             */
            $coursework = coursework::find($raw_coursework);
           
            // if cw doesn't have personal deadlines and deadline passed and cw doesnt have any individual extensions
            if (!$coursework->personal_deadlines_enabled() && (!$coursework->has_deadline()
                || $coursework->deadline_has_passed() && !$coursework->extension_exists())) {
                continue;
            }


            $students = $coursework->get_students_who_have_not_yet_submitted();

            foreach ($students as $student) {
                $individual_extension = false;
                $personal_deadline = false;

                if ($coursework->extensions_enabled()){
                    $individual_extension = \mod_coursework\models\deadline_extension::get_extension_for_student($student, $coursework);
                }
                if ($coursework->personal_deadlines_enabled()){
                    $personal_deadline = \mod_coursework\models\personal_deadline::get_personal_deadline_for_student($student, $coursework);
                }

                $deadline = $personal_deadline ? $personal_deadline->personal_deadline : $coursework->deadline;


                if ($individual_extension){
                    // check if 1st reminder is due to be sent but has not been sent yet
                   if ($coursework->due_to_send_first_reminders($individual_extension->extended_deadline) &&
                       $student->has_not_been_sent_reminder($coursework, 1, $individual_extension->extended_deadline)) {
                           $student->deadline = $individual_extension->extended_deadline;
                           $student->extension = $individual_extension->extended_deadline;
                           $student->coursework_id = $coursework->id;
                           $student->nextremindernumber = 1;
                           $userswhoneedreminding[$student->id().'_'.$coursework->id] = $student;

                       // check if 2nd reminder is due to be sent but has not been sent yet
                   } else if ($coursework->due_to_send_second_reminders($individual_extension->extended_deadline) &&
                       $student->has_not_been_sent_reminder($coursework, 2, $individual_extension->extended_deadline)) {
                           $student->deadline = $individual_extension->extended_deadline;
                           $student->extension = $individual_extension->extended_deadline;
                           $student->coursework_id = $coursework->id;
                           $student->nextremindernumber= 2;
                           $userswhoneedreminding[$student->id().'_'.$coursework->id] = $student;
                   }



                } else if ($deadline > time()) { // coursework or personal deadline hasn't passed
                    // check if 1st reminder is due to be sent but has not been sent yet
                    if ($coursework->due_to_send_first_reminders($deadline) && $student->has_not_been_sent_reminder($coursework, 1)) {
                            $student->deadline = $deadline;
                            $student->coursework_id = $coursework->id;
                            $student->nextremindernumber = 1;
                            $userswhoneedreminding[$student->id().'_'.$coursework->id] = $student;

                        // check if 2nd reminder is due to be sent but has not been sent yet
                    } else if ($coursework->due_to_send_second_reminders($deadline) && $student->has_not_been_sent_reminder($coursework, 2)) {
                            $student->deadline = $deadline;
                            $student->coursework_id = $coursework->id;
                            $student->nextremindernumber = 2;
                            $userswhoneedreminding[$student->id().'_'.$coursework->id] = $student;

                    }
                }
            }
        }

        self::send_email_reminders_to_students($userswhoneedreminding, $counts, self::EMAIL_TYPE_USER);

        if (self::in_test_environment()) {
            mtrace("cron coursework, sent {$counts['emails']} emails to {$counts['users']} users");
        }
        return true;
    }

    /**
     * This will tell teachers that they have students who's deadlines are approaching.
     *
     * @static
     * @return bool
     */
    private static function send_first_reminders_to_admins() {

        global $CFG, $DB;

        require_once($CFG->dirroot.'/mod/coursework/lib.php');
        $timenow = time();
        $firstreminderdays = $CFG->coursework_day_reminder * 60 * 60 * 24;

        // TODO this runs off role names and doesn't use permissions.
        // Get all courseworks that have students who need reminding, along with a count of those students.
        $sql = "SELECT coursework.id AS coursework_id,
                       COUNT(u.id) AS numberofstudents,
                       coursework.name AS coursework_name,
                       c.id AS contextid
                  FROM {user} u
            INNER JOIN {user_enrolments} ue
                    ON (ue.userid = u.id )
            INNER JOIN {enrol} e
                    ON (e.id = ue.enrolid)
            INNER JOIN {coursework} coursework
                    ON coursework.course = e.courseid
            INNER JOIN {context} c
                    ON c.instanceid = coursework.course
            INNER JOIN {role_assignments} ra
                    ON (ra.userid = u.id
                        AND ra.contextid = c.id)
            INNER JOIN {role} r
                    ON r.id = ra.roleid
             LEFT JOIN {coursework_reminder} firstreminder
                    ON (firstreminder.userid = u.id
                        AND firstreminder.coursework_id = coursework.id
                        AND firstreminder.remindernumber = 1)
             LEFT JOIN {coursework_submissions} sub
                    ON sub.courseworkid = coursework.id
                        AND sub.allocatableid = ue.userid
                        AND sub.allocatabletype = 'user'

                 WHERE r.archetype = 'student'
                   AND sub.id IS NULL
                   AND coursework.deadline > {$timenow}
                   AND (coursework.deadline - $firstreminderdays) < {$timenow}
                   AND firstreminder.id IS NULL
              GROUP BY coursework.id, c.id, coursework.name
                HAVING COUNT(u.id) > 0
                    ";

        $courseworks = $DB->get_records_sql($sql);

        // We have to loop like this as getting users by capability in SQL for multiple contexts is too complex.
        $emailssent = 0;
        foreach ($courseworks as $coursework) {

            /* @var coursework $coursework_instance */
            $coursework_instance = coursework::find($coursework->coursework_id);

            if (empty($coursework)) {
                continue;
            }

            $context = context::instance_by_id($coursework_instance->get_context_id());

            $users = self::get_admins_and_teachers($context);

            foreach ($users as $user) {

                if ($DB->record_exists('coursework_reminder', array(
                    'coursework_id' => $coursework_instance->id,
                    'userid' => $user->id,
                    'remindernumber' => 1,
                ))) {
                    continue;
                }

                $user->coursework_name = $coursework_instance->name;
                $user->deadline = userdate($coursework_instance->get_deadline(),'%a, %d %b %Y, %H:%M');
                $user->day_hour = coursework_seconds_to_string($coursework_instance->get_deadline() - time());

                $subject = get_string('cron_email_subject_admin', 'mod_coursework', $user);
                $text = get_string('cron_email_text_admin', 'mod_coursework', $user);
                $html = get_string('cron_email_html_admin', 'mod_coursework', $user);

                // TODO don't use email, use the moodle messaging system as the users may have configured the system
                // to send messages via different channels.
                email_to_user($user, 'Course admin', $subject, $text, $html);
                $emailssent++;

                // Need to record this so they don;t get another one.
                $number_of_existing_reminders = $DB->count_records('coursework_reminder', array('coursework_id' => $coursework_instance->id,
                                                                                                 'userid' => $user->id,
                ));
                $reminder = new stdClass();
                $reminder->userid = $user->id;
                $reminder->coursework_id = $coursework_instance->id;
                $reminder->remindernumber = $number_of_existing_reminders + 1;
                $DB->insert_record('coursework_reminder', $reminder);

            }
        }

        $numberofcourseworks = count($courseworks);

        if (self::in_test_environment()) {
            mtrace("cron coursework, sent {$emailssent} reminder emails to the teachers and managers of {$numberofcourseworks}");
        }

        return true;
    }

    /**
     * This is not used for output, but just converts the parametrised query to one that
     * can be copy/pasted into an SQL GUI in order to debug SQL errors
     *
     * @param string $query
     * @param array $params
     * @global  $CFG
     * @return string
     */
    public static function coursework_debuggable_query($query, $params = array()) {

        global $CFG;

        // Substitute all the {tablename} bits.
        $query = preg_replace('/\{/', $CFG->prefix, $query);
        $query = preg_replace('/}/', '', $query);

        // Now put all the params in place.
        foreach ($params as $name => $value) {
            $pattern = '/:' . $name . '/';
            $replace_value = (is_numeric($value) ? $value : "'" . $value . "'");
            $query = preg_replace($pattern, $replace_value, $query);
        }

        return $query;
    }

    /**
     * Reminds students that they need to submit work.
     *
     * @param array $users
     * @param array $counts user and email cumulative counts so we can set log messages.
     * @internal param string $email_type Check class constants
     * @internal param int $type Check class constants
     * @return void
     */
    private static function send_email_reminders_to_students(array $users, array &$counts) {

        global $DB;

        $emailcounter = 0;
        $usercounter = array();

        foreach ($users as $user) {

            $coursework_instance = coursework::find($user->coursework_id);

            $mailer = new mailer($coursework_instance);

            if ($mailer->send_student_deadline_reminder($user)) {

                $emailcounter++;
                if (!isset($usercounter[$user->id])) {
                    $usercounter[$user->id] = 1;
                } else {
                    $usercounter[$user->id]++;
                }

                $extension =  isset($user->extension)? $user->extension : 0;
                $email_reminder = new stdClass();
                $email_reminder->userid = $user->id;
                $email_reminder->coursework_id = $user->coursework_id;
                $email_reminder->remindernumber = $user->nextremindernumber;
                $email_reminder->extension = $extension;
                $DB->insert_record('coursework_reminder', $email_reminder);
            }
        }

        $counts['emails'] += array_sum($usercounter);
        $counts['users'] += count($usercounter);
    }

    /**
     * Updates all DB columns where the deadline was before now, so that finalised = 1
     */
    private static function finalise_any_submissions_where_the_deadline_has_passed() {

        echo 'Finalising submissions for courseworks where the deadlines have passed...';

        $submissions = submission::unfinalised_past_deadline();
        foreach ($submissions as $submission) {
            // Doing this one at a time so that the email will arrive with finalisation already
            // done. Would not want them to check straight away and then find they could still
            // edit it.
            $submission->update_attribute('finalised', 1);

            // Slightly wasteful to keep re-fetching the coursework :-/
            $mailer = new mailer($submission->get_coursework());
            foreach ($submission->get_students() as $student) {
                $mailer->send_submission_receipt($student, true);
            }
        }

    }

    /**
     * @return bool
     */
    public static function in_test_environment() {
        $in_phpunit = defined('PHPUNIT_TEST') ? PHPUNIT_TEST : false;
        $in_behat = defined('BEHAT_TEST') ? BEHAT_TEST : false;
        if (!empty($in_phpunit) || !empty($in_behat)) {
            return true;
        }
        return false;
    }

    /**
     * Auto release feedback of marked submission if the coursework has individual feedback enabled
     * @throws \coding_exception
     */

    private static function autorelease_feedbacks_where_the_release_date_has_passed() {

        global $DB;
        echo 'Auto releasing feedbacks for courseworks where the release date have passed...';


       $sql = "SELECT *
                 FROM {coursework} c
                 JOIN {coursework_submissions} cs
                   ON c.id = cs.courseworkid
                WHERE c.individualfeedback <= :now
                  AND c.individualfeedback != 0
                  AND c.individualfeedback IS NOT NULL
                  AND cs.firstpublished IS NULL";

        $coursework_submissions = $DB->get_records_sql($sql, array('now' => time()));

        foreach ($coursework_submissions as $coursework_submission) {

            $submission = submission::find($coursework_submission);
            $feedback_autorelease_deadline = $submission->get_coursework()->get_individual_feedback_deadline();
            $allocatable = $submission->get_allocatable();
            if (empty($allocatable)) {
                continue;
            }

            if ($feedback_autorelease_deadline < time() && $submission->ready_to_publish()) {
                $submission->publish();
            }
        }

    }

    /**
     * @param $context
     * @return array
     */
    public static function get_admins_and_teachers($context){


        $graders = get_enrolled_users($context, 'mod/coursework:addinitialgrade');
        $managers = get_enrolled_users($context, 'mod/coursework:addagreedgrade');

        $users = array_merge($graders, $managers);
        $users = array_map("unserialize", array_unique(array_map("serialize", $users)));

        foreach ($users as &$user) {
            $user = user::find($user);
        }

        return $users;

    }




}

