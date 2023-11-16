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

namespace mod_coursework\framework;;


use moodle_database;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * This forms the base class for other classes that represent database table objects using the Active Record pattern.
 *
 * @property mixed fields
 */
abstract class table_base {

    /**
     * @var string
     */
    protected static $table_name;

    /**
     * Cache for the column names.
     *
     * @var array tablename => array(of column names)
     */
    protected static $column_names;

    /**
     * Tells us whether the data has been loaded at least once since instantiation.
     *
     * @var bool
     */
    private $data_loaded = false;

    /**
     * @var int
     */
    public $id;

    /**
     * Makes a new instance. Can be overridden to provide a factory
     *
     * @param \stdClass|int|array $db_record
     * @param bool $reload
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function find($db_record, $reload = true) {

        global $DB;

        if (empty($db_record)) {
            return false;
        }

        $klass = get_called_class();

        if (is_numeric($db_record) && $db_record > 0) {
            $data = $DB->get_record(self::get_table_name(), array('id' => $db_record));
            if (!$data) {
                return false;
            }
            $record = new $klass($data);
            return $record;
        }

        $db_record = (array)$db_record;

        // Supplied a partial DB stdClass record
        if (!array_key_exists('id', $db_record)) {
            $db_record = $DB->get_record(static::get_table_name(), $db_record);
            if (!$db_record) {
                return false;
            }
            return new $klass($db_record);
        }

        if ($db_record) {
            $record = new $klass($db_record);
            if ($reload) {
                $record->reload();
            }
            return $record;
        }

        return false;
    }

    /**
     * @param array $params
     * @return array
     * @throws \coding_exception
     */
    public static function find_all($params = array()) {

        if (!is_array($params)) {
            throw new \coding_exception('::all() require an array of parameters');
        }

        self::remove_non_existant_columns($params);

        return self::instantiate_objects($params);
    }


    /**
     * Makes a new object ready to save
     *
     * @param stdClass|array $data
     * @return \framework\table_base
     */
    public static function build($data) {
        $klass = get_called_class();
        /**
         * @var table_base $item
         */
        $item = new $klass();
        $item->apply_data($data);

        return $item;
    }

    /**
     * Makes a new instance and saves it.
     *
     * @param stdClass|array $params
     * @return table_base
     */
    public static function create($params) {
        $item = static::build($params);
        $item->save();
        return $item;
    }


    /**
     * Takes the supplied DB record (one row of the table) and applies it to this object. If it's a
     * number, change it to a DB row and then do it.
     *
     * @param object|bool $db_record
     */
    public function __construct($db_record = false) {

        // Allow the option to supply an id if this is not being generated as part of a massive list
        // of courseworks. If the id isn't there, throw an error. Weirdly, everything comes through
        // as a string here.
        if (!empty($db_record) && is_numeric($db_record)) {
            $this->id = $db_record;
            $this->reload();
        } else if (is_object($db_record) || is_array($db_record)) {
            // Add all of the DB row fields to this object (if the object has a matching property).
            $this->apply_data($db_record);
            $this->data_loaded = true;
        }
    }

    /**
     * @param $params
     * @return array
     */
    protected static function instantiate_objects($params) {
        global $DB;

        $raw_records = $DB->get_records(static::get_table_name(), $params);
        $objects = array();
        $klass = get_called_class();
        foreach ($raw_records as $raw_record) {
            $objects[$raw_record->id] = new $klass($raw_record);
        }
        return $objects;
    }

    /**
     * @param $params
     */
    protected static function remove_non_existant_columns($params) {
        foreach ($params as $column_name => $value) {
            if (!static::column_exists($column_name)) {
                unset($params[$column_name]);
            }
        }
    }

    /**
     * This is a convenience method so we can do things like new and edit calls to ability
     * without having to juggle build and find elsewhere.
     *
     * @param array $params
     * @return table_base
     */
    public static function find_or_build($params) {
        $object = self::find($params);
        if ($object) {
            return $object;
        } else {
            return self::build($params);
        }
    }

    /**
     * @param $colname
     * @throws \coding_exception
     */
    private static function ensure_column_exists($colname) {
        if (!static::column_exists($colname)) {
            throw new \coding_exception('Column '.$colname.' does not exist in class '.static::get_table_name());
        }
    }

    /**
     * Magic method to get data from the DB table columns dynamically.
     *
     * @param string $requested_property_name
     * @throws \coding_exception
     * @return mixed
     */
    public function __get($requested_property_name) {
        static::ensure_column_exists($requested_property_name);

        if (!$this->data_loaded) {
            $this->reload(); // Will not set the variable if we have not saved the object yet
        }

        return empty($this->$requested_property_name) ? null : $this->$requested_property_name;
    }

    /**
     * Takes an object representing a DB row and applies it to this instance
     *
     * @param array|stdClass $data_object
     * @return void
     */
    protected function apply_data($data_object) {
        $data = (array)$data_object;
        foreach (static::get_column_names() as $column_name) {
            if (isset($data[$column_name])) {
                $this->{$column_name} = $data[$column_name];
            }
        }
    }

    /**
     * Saves the record or creates a new one if needed. Allow subclasses to add bits if needed, before calling.
     *
     * @global moodle_database $DB
     * @param bool $sneakily If true, do not update the timemodified stamp. Useful for cron.
     * @return int|bool
     */
    public final function save($sneakily = false) {

        global $DB;

        $this->pre_save_hook();

        $save_data = $this->build_data_object_to_save($sneakily);

        // Update if there's an id, otherwise make a new one. Check first for an id?
        if ($this->persisted()) {
            $DB->update_record(static::get_table_name(), $save_data);
        } else {
            $this->id = $DB->insert_record(static::get_table_name(), $save_data);
        }

        // Possibly we just saved only some fields and some were created as defaults. Update with the missing ones.
        $this->reload();

        $this->post_save_hook();
    }

    /**
     * Returns the table in the DB that this data object will be written to.
     * @throws \coding_exception
     * @return string
     */
    public static final function get_table_name() {

        if (empty(static::$table_name)) {
            $class_name = get_called_class(); // 'mod_coursework\models\deadline_extension'
            $pieces = explode('\\', $class_name); // 'mod_coursework', 'models', 'deadline_extension'
            $table_name = end($pieces); // 'deadline_extension'
            $table_name .= 's'; // 'deadline_extensions'
        } else {
            $table_name = static::$table_name;
        }

        return $table_name;
    }

    /**
     * Allows subclasses to alter data before it hits the DB.
     */
    protected function pre_save_hook() {
    }

    /**
     * Allows subclasses to do other stuff after after the DB save.
     */
    protected function post_save_hook() {
    }

    /**
     * Tells us whether this record has been saved to the DB yet.
     *
     * @return bool
     */
    public function persisted() {
        global $DB;

        return !empty($this->id) && $DB->record_exists(static::$table_name, array('id' => $this->id));
    }

    /**
     * Returns the most recently created record
     *
     * @return mixed
     */
    public static final function last() {
        global $DB;

        $tablename = static::get_table_name();

        $sql = "SELECT *
                  FROM {{$tablename}}
              ORDER BY id DESC
                 LIMIT 1";
        return new static($DB->get_record_sql($sql));

    }

    /**
     * Returns the most recently created record
     *
     * @return mixed
     */
    public static final function first() {
        global $DB;

        $tablename = static::get_table_name();

        $sql = "SELECT *
                  FROM {{$tablename}}
              ORDER BY id ASC
                 LIMIT 1";
        return $DB->get_record_sql($sql);
    }

    /**
     * Reads the columns from the database
     */
    protected static function get_column_names() {
        global $DB;

        $tablename = static::get_table_name();

        if (isset(static::$column_names[$tablename])) {
            return static::$column_names[$tablename];
        }

        $columns = $DB->get_columns($tablename);

        static::$column_names[$tablename] = array_keys($columns);

        return static::$column_names[$tablename];
    }

    /**
     * Tells us if the column is present in the Moodle database table.
     *
     * @param string $requested_property_name
     * @return bool
     */
    private static function column_exists($requested_property_name) {
        return in_array($requested_property_name, static::get_column_names());
    }

    /**
     * Reloads the data from the DB columns.
     * @param bool $complain_if_not_found
     * @return $this
     * @throws \coding_exception
     */
    public function reload($complain_if_not_found = true) {
        global $DB;

        if (empty($this->id)) {
            return $this;
        }

        $strictness = $complain_if_not_found ? MUST_EXIST : IGNORE_MISSING;
        $db_record = $DB->get_record(static::get_table_name(), array('id' => $this->id), '*', $strictness);

        if ($db_record) {
            $this->apply_data($db_record);
            $this->data_loaded = true;
        }

        return $this;
    }

    /**
     * Updates a single attribute and saves the model.
     *
     * @param string $name
     * @param mixed $value
     * @param bool $sneakily If true, do not update the timemodified stamp. Useful for cron.
     * @return bool|int
     */
    public function update_attribute($name, $value, $sneakily = false) {
        $this->apply_column_value_to_self($name, $value);
        return $this->save($sneakily);
    }

    /**
     * Updates a single attribute and saves the model.
     *
     * @param mixed $values key-value pairs
     * @return bool|int
     */
    public function update_attributes($values) {
        foreach ($values as $col => $val) {
            $this->apply_column_value_to_self($col, $val, false);
        }
        return $this->save();
    }

    /**
     * Wipes out the record from the database.
     *
     * @throws \coding_exception
     */
    public function destroy() {
        global $DB;

        if (empty($this->id)) {
            throw new \coding_exception('Cannot destroy an object that has not yet been saved');
        }

        $this->before_destroy();

        $DB->delete_records(static::get_table_name(), array('id' => $this->id));
    }

    /**
     * Hook method to allow subclasses to get stuff done like destruction of dependent records.
     */
    protected function before_destroy() {

    }

    /**
     * @param $col
     * @param $val
     * @param bool $with_errors_for_missing_columns
     * @throws \coding_exception
     */
    private function apply_column_value_to_self($col, $val, $with_errors_for_missing_columns = true) {
        if ($with_errors_for_missing_columns) {
            static::ensure_column_exists($col);
        }
        if ($this->column_exists($col)) {
            $this->$col = $val;
        }
    }

    /**
     * @param bool $sneakily If true, do not update the timemodified stamp. Useful for cron.
     * @return stdClass
     */
    private function build_data_object_to_save($sneakily = false) {
        // Don't just use $this as it will try to save any missing value as null. We may only want.
        // to update  some fields e.g. leaving timecreated alone.
        $save_data = new stdClass();

        // Only save the non-null fields.
        foreach (static::get_column_names() as $column_name) {
            if (!is_null($this->$column_name)) {
                $save_data->$column_name = $this->$column_name;
            }
        }

        if (static::column_exists('timecreated') && !$this->persisted()) {
            $save_data->timecreated = time();
        }

        if (!$sneakily && static::column_exists('timemodified')) {
            $save_data->timemodified = time();
        }

        return $save_data;
    }

    /**
     * @param array|table_base $conditions key value pairs of DB columns
     * @return bool
     */
    public static function exists($conditions = array()) {
        global $DB;

        if (is_number($conditions)) {
            $conditions = array('id' => $conditions);
        }
        if (method_exists($conditions, 'to_array')) {
            $conditions = $conditions->to_array();
        }

        foreach($conditions as $colname => $value) {
            static::ensure_column_exists($colname);
        }
        return $DB->record_exists(static::get_table_name(), $conditions);
    }

    /**
     * @param array $conditions
     * @return int
     */
    public static function count($conditions = array()) {
        global $DB;

        foreach ($conditions as $colname => $value) {
            static::ensure_column_exists($colname);
        }
        return $DB->count_records(static::get_table_name(), $conditions);
    }

    /**
     * @return stdClass|bool
     * @throws \coding_exception
     */
    public function get_raw_record() {
        global $DB;
        return $DB->get_record(static::get_table_name(), array('id' => $this->id));
    }

    /**
     * @param string $field
     * @param mixed $value
     */
    protected function apply_value_if_column_exists($field, $value) {
        if (static::column_exists($field)) {
            $this->{$field} = $value;
        }
    }

    /**
     * @param string $sql The bit after WHERE
     * @param array $params
     * @return array
     * @throws \coding_exception
     * @throws \dml_missing_record_exception
     * @throws \dml_multiple_records_exception
     */
    public static function find_by_sql($sql, $params) {
        global $DB;

        $sql = 'SELECT * FROM {' . static::get_table_name() . '} WHERE ' . $sql;
        $records = $DB->get_record_sql($sql, $params);
        $klass = get_called_class();
        foreach ($records as &$record) {
            $record = new $klass($record);
        }
        return $records;
    }

    /**
     * @return string
     * @throws \coding_exception
     */
    public function __toString() {
        $string = $this->get_table_name().' '.$this->id.' ';
        foreach ($this->get_column_names() as $column) {
            $string .= $column.' '.$this->$column.' ';
        }
        return $string;
    }

    /**
     * @return array
     */
    public function to_array() {
        $data = array();

        // Only save the non-null fields.
        foreach (static::get_column_names() as $column_name) {
            if (!is_null($this->$column_name)) {
                $data[$column_name] = $this->$column_name;
            }
        }
        return $data;
    }

    /**
     * @return int|string
     * @throws \coding_exception
     */
    public function id() {
        if (empty($this->id)) {
            throw new \coding_exception('Asking for the id of an unsaved object');
        }
        return $this->id;
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
     * @throws \dml_exception
     */
    public static function fill_pool_coursework($coursework_id) {
        if (isset(static::$pool[$coursework_id])) {
            return;
        }
        $key = static::$table_name;
        $cache = \cache::make('mod_coursework', 'courseworkdata', ['id' => $coursework_id]);

        $data = $cache->get($key);
        if ($data === false) {
            // no cache found
            $data = static::get_cache_array($coursework_id);
            $cache->set($key, $data);
        }

        static::$pool[$coursework_id] = $data;
    }

    /**
     * @param $coursework_id
     */
    public static function remove_cache($coursework_id) {
        global $SESSION;
        if (!empty($SESSION->keep_cache_data)) {
            return;
        }
        static::$pool[$coursework_id] = null;
        $cache = \cache::make('mod_coursework', 'courseworkdata', ['id' => $coursework_id]);
        $cache->delete(static::$table_name);
    }

}
