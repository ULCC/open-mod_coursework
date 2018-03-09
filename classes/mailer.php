<?php

namespace mod_coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;

/**
 * This is where all emails are sent from. The methods need to be OK to run from a separate cron process,
 * so do not pass in entire objects. Just DB row ids, which the methods then retrieve.
 *
 * @package mod_coursework
 */
class mailer {

    /**
     * @var models\coursework
     */
    protected $coursework;

    /**
     * @param models/coursework $coursework
     */
    public function __construct($coursework) {
        $this->coursework = $coursework;
    }

    /**
     * This ought to only be triggered when the submission is finalised, not when the draft is uploaded.
     *
     * @param user $user
     * @param bool $finalised
     * @throws \coding_exception
     */

    public function send_submission_receipt($user, $finalised = false) {
        global $CFG;

        $submission = $this->coursework->get_user_submission($user);

        $email_data = new \stdClass();
        $email_data->name = $user->name();
        $dateformat = '%a, %d %b %Y, %H:%M';
        $email_data->submittedtime = userdate($submission->time_submitted(), $dateformat);
        $email_data->coursework_name = $this->coursework->name;
        $email_data->submissionid = $submission->id;
        if ($finalised) {
            $email_data->finalised = get_string('save_email_finalised', 'coursework');
        } else {
            $email_data->finalised = '';
        }

        $subject = get_string('save_email_subject', 'coursework');
        $text_body = get_string('save_email_text', 'coursework', $email_data);
        $html_body = get_string('save_email_html', 'coursework', $email_data);

        // New approach.
        $eventdata = new \core\message\message();
        $eventdata->component = 'mod_coursework';
        $eventdata->name = 'submission_receipt';
        $eventdata->userfrom =  \core_user::get_noreply_user();
        $eventdata->userto = $user->get_raw_record();
        $eventdata->subject = $subject;
        $eventdata->fullmessage = $text_body;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = $html_body;
        $eventdata->smallmessage = $text_body;
        $eventdata->notification = 1;
        $eventdata->contexturl = $CFG->wwwroot.'/mod/coursework/view.php?id='.$submission->get_coursework()->get_coursemodule_id();
        $eventdata->contexturlname = 'View your submission here';
        $eventdata->courseid = $this->coursework->course;

        message_send($eventdata);

    }

    /**
     * @param submission $submission
     */
    public function send_late_submission_notification($submission) {
        global $CFG;

        $coursework = $submission->get_coursework();
        $student_or_group = $submission->get_allocatable();
        $recipients = $coursework->initial_assessors($student_or_group);
        foreach ($recipients as $recipient) {

            // New approach.
            $eventdata =  new \core\message\message();
            $eventdata->component = 'mod_coursework';
            $eventdata->name = 'submission_receipt';
            $eventdata->userfrom = \core_user::get_noreply_user();
            $eventdata->userto = $recipient;
            $eventdata->subject = 'Late submission for '.$coursework->name;
            $message_text =
                'A late submission was just submitted for ' . $student_or_group->type() . ' ' . $student_or_group->name();
            $eventdata->fullmessage = $message_text;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = $message_text;
            $eventdata->smallmessage = $message_text;
            $eventdata->notification = 1;
            $eventdata->contexturl =
                $CFG->wwwroot . '/mod/coursework/view.php?id=' . $coursework->get_coursemodule_id();
            $eventdata->contexturlname = 'View the submission here';
            $eventdata->courseid = $this->coursework->course;

            message_send($eventdata);
        }
    }


    /**
     * Send feedback notifications to users whose feedback was released
     *
     * @param submission $submission
     * @throws \coding_exception
     */
    public function send_feedback_notification($submission) {
        global $CFG;

        $email_data = new \stdClass();
        $email_data->coursework_name = $this->coursework->name;

        $subject = get_string('feedback_released_email_subject', 'coursework');

        // get a student or all students from a group
        $students = $submission->students_for_gradebook();

        foreach ($students as $student) {
            $student = \mod_coursework\models\user::find($student);

            $email_data->name = $student->name();
            $text_body = get_string('feedback_released_email_text', 'coursework', $email_data);
            $html_body = get_string('feedback_released_email_html', 'coursework', $email_data);

            $eventdata =  new \core\message\message();
            $eventdata->component = 'mod_coursework';
            $eventdata->name = 'feedback_released';
            $eventdata->userfrom = \core_user::get_noreply_user();
            $eventdata->userto = $student->get_raw_record();
            $eventdata->subject = $subject;
            $eventdata->fullmessage = $text_body;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = $html_body;
            $eventdata->smallmessage = $text_body;
            $eventdata->notification = 1;
            $eventdata->contexturl = $CFG->wwwroot . '/mod/coursework/view.php?id=' . $submission->get_coursework()->get_coursemodule_id();
            $eventdata->contexturlname = 'View your submission here';
            $eventdata->courseid = $this->coursework->course;

            message_send($eventdata);
        }
    }

    /**
     *  Send deadline reminder notifications to users who haven't submitted yet
     *
     * @param $user
     * @return mixed
     * @throws \coding_exception
     */
    public function send_student_deadline_reminder($user)   {

        global $CFG;

        $email_data = new \stdClass();
        $email_data->coursework_name = $this->coursework->name;
        $email_data->coursework_name_with_link = \html_writer::link($CFG->wwwroot . '/mod/coursework/view.php?id=' . $this->coursework->get_coursemodule_id(), $this->coursework->name);
        $email_data->deadline = $user->deadline;
        $email_data->human_deadline = userdate($user->deadline,'%a, %d %b %Y, %H:%M');

        $secondstodeadline = $user->deadline - time();
        $days = floor($secondstodeadline / 86400);
        $hours = floor($secondstodeadline / 3600) % 24;
        $days_to_deadline = '';
        if ($days > 0) {
            $days_to_deadline = $days . ' days and ';
        }
        $days_to_deadline .= $hours . ' hours';
        $email_data->day_hour = $days_to_deadline;

        $subject = get_string('cron_email_subject', 'mod_coursework', $email_data);

        $student = \mod_coursework\models\user::find($user);

        $email_data->name = $student->name();
        $text_body = get_string('cron_email_text', 'mod_coursework', $email_data);
        $html_body = get_string('cron_email_html', 'mod_coursework', $email_data);

        $eventdata =  new \core\message\message();
        $eventdata->component = 'mod_coursework';
        $eventdata->name = 'student_deadline_reminder';
        $eventdata->userfrom = \core_user::get_noreply_user();
        $eventdata->userto = $student->get_raw_record();
        $eventdata->subject = $subject;
        $eventdata->fullmessage = $text_body;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = $html_body;
        $eventdata->smallmessage = $text_body;
        $eventdata->notification = 1;
        $eventdata->contexturl = $CFG->wwwroot . '/mod/coursework/view.php?id=' . $this->coursework->get_coursemodule_id();
        $eventdata->contexturlname = 'View the coursework here';
        $eventdata->courseid = $this->coursework->course;

        return message_send($eventdata);
    }



    public function send_submission_notification($userstonotify)  {

        global $CFG;

        $email_data = new \stdClass();
        $email_data->coursework_name = $this->coursework->name;

        $subject = get_string('submission_notification_subject', 'coursework',$email_data->coursework_name);

        $userstonotify = \mod_coursework\models\user::find($userstonotify);

        $email_data->name = $userstonotify->name();
        $text_body = get_string('submission_notification_text', 'coursework', $email_data);
        $html_body = get_string('submission_notification_html', 'coursework', $email_data);

        $eventdata =  new \core\message\message();
        $eventdata->component = 'mod_coursework';
        $eventdata->name = 'coursework_submission';
        $eventdata->userfrom = \core_user::get_noreply_user();
        $eventdata->userto = $userstonotify->get_raw_record();
        $eventdata->subject = $subject;
        $eventdata->fullmessage = $text_body;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = $html_body;
        $eventdata->smallmessage = $text_body;
        $eventdata->notification = 1;
        $eventdata->contexturl = $CFG->wwwroot . '/mod/coursework/view.php?id=' . $this->coursework->id();
        $eventdata->contexturlname = 'coursework submission';
        $eventdata->courseid = $this->coursework->course;

        message_send($eventdata);


    }
}