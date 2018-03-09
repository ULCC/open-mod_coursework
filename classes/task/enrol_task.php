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

namespace mod_coursework;
namespace mod_coursework\task;


/**
 * A scheduled task for the coursework module cron.
 *
 * @package    mod_coursework
 * @copyright  2014 ULCC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class enrol_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('enroltask', 'mod_coursework');
    }

    /**
     * Run coursework cron.
     */
    public function execute() {

        global $DB;

        $courseworkids     =   $DB->get_records('coursework',array('processenrol'=>1));

        if (!empty($courseworkids))   {
            foreach ($courseworkids as $courseworkid) {
                $coursework = \mod_coursework\models\coursework::find($courseworkid);
                if (empty($coursework)) {
                    continue;
                }

                $cache = \cache::make('mod_coursework', 'courseworkdata');
                $cache->set($coursework->id()."_teachers", '');
                $allocator = new \mod_coursework\allocation\auto_allocator($coursework);
                $allocator->process_allocations();

                $DB->set_field('coursework','processenrol',0,array('id'=>$coursework->id()));
            }
        }

        return true;
    }
}