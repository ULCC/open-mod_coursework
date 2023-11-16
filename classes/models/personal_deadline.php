<?php

namespace mod_coursework\models;

use  mod_coursework\framework\table_base;

/**
 * Class personal_deadline is responsible for representing one row of the personal_deadline table.

 *
 * @property mixed personal_deadline
 * @property mixed courseworkid
 * @property mixed allocatabletype
 * @property mixed allocatableid
 * @package mod_coursework\models
 */
class personal_deadline extends table_base {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string
     */
    protected static $table_name = 'coursework_person_deadlines';

    /**
     * @return mixed|\mod_coursework_coursework
     */
    public function get_coursework() {
        if (!isset($this->coursework)) {
            coursework::fill_pool_coursework($this->courseworkid);
            $this->coursework = coursework::get_object($this->courseworkid);
        }

        return $this->coursework;
    }

    public function get_allocatable() {
        $class_name = "\\mod_coursework\\models\\{$this->allocatabletype}";
        return $class_name::find($this->allocatableid);
    }

    /**
     * Function to check if extension for this personal deadline (alloctable) exists
     * @return static
     */
    public function extension_exists(){
        $coursework = $this->get_coursework();

        $params = array('courseworkid' => $coursework->id,
                        'allocatableid' => $this->allocatableid,
                        'allocatabletype' => $this->allocatabletype);

        return   deadline_extension::find($params);
    }

    /**
     * @param user $student
     * @param coursework $coursework
     * @return personal_deadline|bool
     */
    public static function get_personal_deadline_for_student($student, $coursework) {
        if ($coursework->is_configured_to_have_group_submissions()) {
            $allocatable = $coursework->get_student_group($student);
        } else {
            $allocatable = $student;
        }
        if ($allocatable) {
            return static::find(array('courseworkid' => $coursework->id,
                                      'allocatableid' => $allocatable->id(),
                                      'allocatabletype' => $allocatable->type(),
            ));
        }
    }


    /**
     * cache array
     *
     * @var
     */
    public static $pool;

    /**
     *
     * @param $coursework_id
     * @return array
     */
    protected static function get_cache_array($coursework_id) {
        global $DB;
        $records = $DB->get_records(static::$table_name, ['courseworkid' => $coursework_id]);
        $result = [
            'allocatableid-allocatabletype' => []
        ];
        if ($records) {
            foreach ($records as $record) {
                $object = new self($record);
                $result['allocatableid-allocatabletype'][$record->allocatableid . '-' . $record->allocatabletype][] = $object;
            }
        }
        return $result;
    }

    /**
     *
     * @param $coursework_id
     * @param $key
     * @param $params
     * @return bool
     */
    public static function get_object($coursework_id, $key, $params) {
        if (!isset(self::$pool[$coursework_id])) {
            self::fill_pool_coursework($coursework_id);
        }
        $value_key = implode('-', $params);
        return self::$pool[$coursework_id][$key][$value_key][0] ?? false;
    }

    /**
     *
     */
    protected function post_save_hook() {
        self::remove_cache($this->courseworkid);
    }

    /**
     *
     */
    protected function after_destroy() {
        self::remove_cache($this->courseworkid);
    }

}