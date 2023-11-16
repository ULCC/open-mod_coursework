<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_coursework\models;

use mod_coursework\framework\table_base;
use mod_coursework\ability;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a row in the coursework_allocation_pairings table.
 *
 * @property string stage_identifier
 * @property int moderator
 * @property mixed allocatableid
 * @property mixed allocatabletype
 */
class allocation extends table_base {

    /**
     * @var string
     */
    protected static $table_name = 'coursework_allocation_pairs';

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $courseworkid;

    /**
     * @var coursework
     */
    public $coursework;

    /**
     * @var int
     */
    public $assessorid;

    /**
     * @var int
     */
    public $studentid;

    /**
     * @var int
     */
    public $manual;

    /**
     * @var int UNIX timestamp for the point at which this started to be marked. If it's within a set timeframe, we prevent
     * reallocation in case marking is in progress.
     */
    public $timelocked;

    /**
     * @var array
     */
    protected $fields = array(
        'id',
        'courseworkid',
        'assessorid',
        'studentid',
        'manual',
        'timelocked'
    );

    /**
     * @return coursework|mixed
     */
    public function get_coursework() {

        if (!isset($this->coursework)) {
            $this->coursework = coursework::find($this->courseworkid);
        }

        return $this->coursework;
    }

    /**
     * @return user|bool
     */
    public function assessor() {
        return user::get_object($this->assessorid);
    }

    /**
     * @return string
     */
    public function assessor_name() {
        return $this->assessor()->profile_link();
    }

    /**
     * @return bool
     */
    public function is_pinned() {
        return !!$this->manual;
    }

    /**
     * @param user $assessor
     */
    public function set_assessor($assessor) {
        $this->update_attribute('assessorid', $assessor->id);
    }

    /**
     *
     */
    public function pin() {
        if (empty($this->manual)) {
            $this->update_attribute('manual', 1);
        }
    }

    /**
     *
     */
    public function unpin() {
        if ($this->manual) {
            $this->update_attribute('manual', 0);
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
            'id' => [],
            'stage_identifier' => [],
            'allocatableid-allocatabletype-stage_identifier' => [],
            'allocatableid-allocatabletype-assessorid' => [],
            'assessorid-allocatabletype' => []
        ];
        if ($records) {
            foreach ($records as $record) {
                $object = new self($record);
                $result['id'][$record->id] = $object;
                $result['stage_identifier'][$record->stage_identifier][] = $object;
                $result['allocatableid-allocatabletype-stage_identifier'][$record->allocatableid . '-' . $record->allocatabletype . '-' . $record->stage_identifier][] = $object;
                $result['allocatableid-allocatabletype-assessorid'][$record->allocatableid . '-' . $record->allocatabletype . '-' . $record->assessorid][] = $object;
                $result['assessorid-allocatabletype'][$record->assessorid . '-' . $record->allocatabletype][] = $object;
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
