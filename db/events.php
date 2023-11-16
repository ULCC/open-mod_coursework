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

/**
 * Event handlers. Mostly for dealing with auto allocation of markers.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



$observers = array(

    array(
        'eventname'   => '\core\event\role_assigned',
        'callback'    => 'mod_coursework_observer::autoallocate_when_user_added',
    ),
    array(
        'eventname'   => '\core\event\role_unassigned',
        'callback'    => 'mod_coursework_observer::autoallocate_when_user_removed',
    ),
    array(
        'eventname'   => '\mod_coursework\event\coursework_deadline_changed',
        'callback'    => 'mod_coursework_observer::coursework_deadline_changed',
        'schedule' => 'cron'
    ),
    array(
        'eventname'   => '\core\event\course_module_updated',
        'callback'    => 'mod_coursework_observer::process_allocation_after_update',
    ),
    array(
        'eventname'   => '\core\event\course_module_created',
        'callback'    => 'mod_coursework_observer::process_allocation_after_creation',
    ),
    array(
        'eventname' => '\core\event\group_member_added',
        'callback' => 'mod_coursework_observer::process_allocations_when_group_member_added',
    ),
    array(
        'eventname' => '\core\event\group_member_removed',
        'callback' => 'mod_coursework_observer::process_allocations_when_group_member_removed',
    ),
    array(
        'eventname' => 'core\event\role_assigned',
        'callback' => 'mod_coursework_observer::add_teacher_to_dropdown_when_enrolled',
    ),
    array(
        'eventname' => 'core\event\role_unassigned',
        'callback' => 'mod_coursework_observer::remove_teacher_from_dropdown_when_unenrolled',
    ),
);


