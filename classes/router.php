<?php

namespace mod_coursework;
use coding_exception;
use moodle_url;

/**
 * This holds the routes that the Coursework module uses, so that we don't have to keep manually entering them
 * in multiple places. The routes should be RESTful:
 *
 * Index
 * Show
 * New - show form
 * Create - save form
 * Edit - show form
 * Update - save form
 * Destroy
 */
class router {

    /**
     * @var router
     */
    private static $instance;

    /**
     * Singleton.
     */
    private function __construct() {}

    /**
     * Singleton accessor.
     *
     * @return router
     */
    public static function instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param string $path_name e.g. 'edit feedback'
     * @param array $items named array keys so we can construct meaningful paths.
     * @param bool $as_url_object return the moodle_url or a string of the path?
     * @param bool $escaped
     * @throws \coding_exception
     * @return moodle_url|string url
     */
    public function get_path($path_name, $items = array(), $as_url_object = false, $escaped = true) {

        global $CFG;

        $context_id = false;
        $coursemodule_id = false;

        if (array_key_exists('coursework', $items)) {
            /**
             * @var coursework $coursework
             */
            $coursework = $items['coursework'];
            $context_id = $coursework->get_context_id();
            $coursemodule_id = $coursework->get_coursemodule_id();
        }

        $url = false;

        switch ($path_name) {

            case 'create feedback':
                $url = new moodle_url('/mod/coursework/actions/feedbacks/create.php');
                break;

            case 'course':
                $url = new moodle_url('/course/view.php', array('id' => $items['course']->id));
                break;

            case 'edit coursework':
                $url = new moodle_url('/mod/edit.php');
                break;

            case 'coursework settings':
                $url = new moodle_url('/course/modedit.php', array('update' => $coursemodule_id));
                break;

            case 'coursework':
                $url = new moodle_url('/mod/coursework/view.php', array('id' => $coursemodule_id));
                break;

            case 'allocations':
                $url = new moodle_url('/mod/coursework/actions/allocate.php',
                                      array('id' => $coursemodule_id));
                break;

            case 'assessor grading':

            case 'new feedback':
                $url = new moodle_url('/mod/coursework/actions/feedbacks/new.php',
                                          array('submissionid' => $items['submission']->id,
                                                'stage_identifier' => $items['stage']->identifier(),
                                                'assessorid' => $items['assessor']->id));
                break;

            case 'new final feedback':
                $params = array('submissionid' => $items['submission']->id,
                                'stage_identifier' => $items['stage']->identifier(),
                                'isfinalgrade' => 1);
                $url = new moodle_url('/mod/coursework/actions/feedbacks/new.php', $params);
                break;

            /*case 'new moderator feedback':
                $url = new moodle_url('/mod/coursework/actions/feedbacks/new.php',
                                      array('submissionid' => $items['submission']->id,
                                            'stage_identifier' => $items['stage']->identifier(),
                                            'ismoderation' => 1));
                break;*/

            case 'new submission':
                $url = new moodle_url('/mod/coursework/actions/submissions/new.php',
                                      array(
                                          'allocatableid' => $items['submission']->allocatableid,
                                          'allocatabletype' => $items['submission']->allocatabletype,
                                          'courseworkid' => $items['submission']->courseworkid,
                                      ));
                break;

            case 'edit feedback':
                $url = new moodle_url('/mod/coursework/actions/feedbacks/edit.php',
                                      array('feedbackid' => $items['feedback']->id));
                break;

            case 'update feedback':
                $url = new moodle_url('/mod/coursework/actions/feedbacks/update.php',
                                      array('feedbackid' => $items['feedback']->id));
                break;

            case 'new deadline extension':
                $url = new moodle_url('/mod/coursework/actions/deadline_extensions/new.php',
                                      $items);
                break;

            case 'edit deadline extension':
                $url = new moodle_url('/mod/coursework/actions/deadline_extensions/edit.php',
                                      $items);
                break;

            case 'edit personal deadline':
                $url = new moodle_url('/mod/coursework/actions/personal_deadline.php',
                    $items);
                break;

            case 'set personal deadlines':
                $url = new moodle_url('/mod/coursework/actions/set_personal_deadlines.php',
                    array('id' => $coursemodule_id));
                break;

            case 'new moderations':
                $params = array('submissionid' => $items['submission']->id,
                                'stage_identifier' => $items['stage']->identifier(),
                                'feedbackid' =>$items['feedbackid']);
                $url = new moodle_url('/mod/coursework/actions/moderations/new.php',$params);
                break;

            case 'create moderation agreement':
                $url = new moodle_url('/mod/coursework/actions/moderations/create.php');
                break;

            case 'edit moderation':
                $url = new moodle_url('/mod/coursework/actions/moderations/edit.php',
                                      array('moderationid' => $items['moderation']->id,
                                           'feedbackid' =>$items['moderation']->feedbackid));
                break;

            case 'update moderation':
                $url = new moodle_url('/mod/coursework/actions/moderations/update.php');
                break;

            case 'show moderation':
                $url = new moodle_url('/mod/coursework/actions/moderations/show.php',
                                        array('moderationid' => $items['moderation']->id,
                                        'feedbackid' =>$items['moderation']->feedbackid));

                break;
        }

        if (!$url) {

            // Try to auto construct it.
            $bits = explode(' ', $path_name);
            $action = array_shift($bits);
            $type = implode('_', $bits);

            $auto_path = '/mod/coursework/actions/' . $this->pluralise($type) . '/' . $action . '.php';
            if (file_exists($CFG->dirroot . $auto_path)) {

                $params = array();
                if (array_key_exists($type, $items)) {
                    $params[$type.'id'] = $items[$type]->id;
                } else if (array_key_exists('coursework', $items)) {
                    $params['courseworkid'] = $items['coursework']->id;
                } else if (array_key_exists('courseworkid', $items)) {
                    $params['courseworkid'] = $items['courseworkid'];
                }

                $url = new moodle_url($auto_path, $params);
            }
        }

        if (!$url) {
            throw new coding_exception("No target file for path: '{$path_name}'");
        }

        return $as_url_object ? $url : $url->out($escaped);
    }

    /**
     * Might need more complex pluralisation rules later.
     *
     * @param $string
     * @return mixed
     */
    protected function pluralise($string) {
        return $string . 's';
    }

}
