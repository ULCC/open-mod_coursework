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

namespace mod_coursework\allocation\table\row;

/**
 * Class file for the renderable object that makes a single row in the marker allocation table.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\allocation\allocatable;
use mod_coursework\allocation\table\builder as table_builder;
use mod_coursework\models\coursework;
use mod_coursework\render_helpers\grading_report\cells\allocatable_cell;
use mod_coursework\stages\base as stage_base;
use mod_coursework\user_row;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderable row class.
 */
class builder implements user_row {

    /**
     * @var table_builder
     */
    private $allocationtable;

    /**
     * @var allocatable user or group
     */
    private $allocatable;

    /**
     * Constructor makes new instance.
     *
     * @param table_builder $allocation_table
     * @param allocatable $allocatable
     */
    public function __construct($allocation_table, $allocatable) {
        $this->allocationtable = $allocation_table;
        $this->allocatable = $allocatable;
    }

    /**
     * @return stage_base[]
     */
    public function marking_stages() {
        return $this->allocationtable->marking_stages();
    }

    /**
     * @return allocatable
     */
    public function get_allocatable() {
        return $this->allocatable;
    }

    /**
     * @return int
     */
    public function get_allocatable_id() {
        return $this->allocatable->id;
    }

    /**
     * @return string
     */
    public function get_user_name() {
        return $this->allocatable->name();
    }

    /**
     * Assume that if someone can see the coursework allocation table then they can see the full user names.
     *
     * @return bool
     */
    public function can_view_username() {
        return true;
    }

    /**
     * @return allocatable_cell
     */
    public function get_allocatable_cell() {
        return $this->allocationtable->get_allocatable_cell();
    }

    /**
     * @return coursework
     */
    public function get_coursework() {
        return $this->allocationtable->get_coursework();
    }

    /**
     * @return string
     */
    public function get_student_firstname() {

        global $DB;

        $allocatable = $this->get_allocatable();
        if (empty($allocatable->firstname)) {
            $this->allocatable =  user::find($allocatable);
        }

        return $this->get_allocatable()->firstname;
    }

    /**
     * @return string
     */
    public function get_student_lastname() {

        global $DB;

        $allocatable = $this->get_allocatable();
        if (empty($allocatable->lastname)) {
            $this->allocatable =  user::find($allocatable);
        }

        return $this->get_allocatable()->lastname;
    }
    /**
     * @return string
     */
    public function get_idnumber() {

        global $DB;

        $allocatable = $this->get_allocatable();
        if (empty($allocatable->idnumber)) {
            $this->allocatable =  user::find($allocatable);
        }

        return $this->get_allocatable()->idnumber;
    }


    /**
     * @return string
     */
    public function get_email() {

        global $DB;

        $allocatable = $this->get_allocatable();
        if (empty($allocatable->email)) {
            $this->allocatable =  user::find($allocatable);
        }

        return $this->get_allocatable()->email;
    }


}
