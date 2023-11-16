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
 * This file keeps track of upgrades to the eassessment module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/lib.php');
use mod_coursework\models\coursework;

/**
 * xmldb_eassessment_upgrade
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_coursework_upgrade($oldversion) {

    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2011122000) {

        // Define field timepublished to be added to coursework_feedbacks.
        $table = new xmldb_table('coursework_feedbacks');
        $field = new xmldb_field('timepublished', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, 'feedbackcomment');

        // Conditionally launch add field timepublished.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2011122000, 'coursework');
    }

    if ($oldversion < 2012013000) {

        // Define field lasteditedbyuser to be added to coursework_feedbacks.
        $table = new xmldb_table('coursework_feedbacks');
        $field = new xmldb_field('lasteditedbyuser', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                                 XMLDB_NOTNULL, null, '0', 'timepublished');

        // Conditionally launch add field lasteditedbyuser.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Update all older feedbacks.
        $allfeedbacks = $DB->get_records('coursework_feedbacks');
        foreach ($allfeedbacks as $feedback) {
            $feedback->lasteditedbyuser = $feedback->assessorid;
            $DB->update_record('coursework_feedbacks', $feedback);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012013000, 'coursework');
    }

    // To match the new way of storing data, the old data needs to be re-done.
    if ($oldversion < 2012020100) {

        // Checkboxes
        // old format : array of arrays. Each array contains id and value of a component
        // new format : array of ids of checked boxes.

        // Get old data.
        $sql = "SELECT fdata.id, fdata.feedbackcomment
                  FROM {coursework_fdata} fdata
            INNER JOIN {coursework_field} field
                    ON field.id = fdata.fieldid
                 WHERE field.type = 'checkbox' ";
        $olddata = $DB->get_records_sql($sql);
        // Alter the comments and write them to the DB.
        foreach ($olddata as $datarow) {
            $oldcomment = unserialize($datarow->feedbackcomment);
            $newcomment = array();
            if ($oldcomment) { // Some were blank - probably an earlier experiment.
                foreach ($oldcomment as $componentarray) {
                    $newcomment[] = $componentarray['id'];
                }
            }
            $datarow->feedbackcomment = serialize($newcomment);
            $DB->update_record('coursework_fdata', $datarow);
        }

        // Multi-select
        // old format : comma separated values.
        // new format : array of ids of checked boxes.
        $sql = "SELECT fdata.id, fdata.feedbackcomment, fdata.fieldid
                          FROM {coursework_fdata} fdata
                    INNER JOIN {coursework_field} field
                            ON field.id = fdata.fieldid
                         WHERE field.type = 'select-multiple' ";
        $olddata = $DB->get_records_sql($sql);
        // Problem - DB has lots of responses containing commas, which means we can't use explode.
        // Will have to subtract component values from the string one at a time, recording their
        // ids if we find the value.
        foreach ($olddata as $datarow) {
            $newcomment = array();
            $oldcomment = $datarow->feedbackcomment; // Comma separated, with lots of extra commas.
            $components = $DB->get_records('coursework_form_component',
                                           array('fieldid' => $datarow->fieldid));
            foreach ($components as $component) {
                if (!empty($oldcomment) && strpos($component->comp_description, $oldcomment) !== false) {
                    $newcomment[] = $component->id;
                    // Remove it to prevent overlaps in next matches.
                    $oldcomment = str_replace($component->comp_description, '', $oldcomment);
                }
            }
            $datarow->feedbackcomment = serialize($newcomment);
            $DB->update_record('coursework_fdata', $datarow);

        }

        // Select
        // old format : array - all are empty
        // new format : single value - id of component to have selected.

        // Make all into empty fields.
        $sql = "SELECT fdata.id, fdata.feedbackcomment
                                  FROM {coursework_fdata} fdata
                            INNER JOIN {coursework_field} field
                                    ON field.id = fdata.fieldid
                                 WHERE field.type = 'select' ";
        $olddata = $DB->get_records_sql($sql);
        foreach ($olddata as $datarow) {
            $datarow->feedbackcomment = '';
            $DB->update_record('coursework_fdata', $datarow);

        }

        upgrade_mod_savepoint(true, 2012020100, 'coursework');
    }

    if ($oldversion < 2012053100) {

        // Rename field finalgrade on table coursework_feedbacks to cappedgrade.
        $table = new xmldb_table('coursework_feedbacks');
        $field = new xmldb_field('finalgrade', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'grade');

        // Launch rename field finalgrade.
        $dbman->rename_field($table, $field, 'cappedgrade');

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012053100, 'coursework');
    }

    if ($oldversion < 2012053102) {

        // Define field isfinalgrade to be added to coursework_feedbacks.
        $table = new xmldb_table('coursework_feedbacks');
        $field = new xmldb_field('isfinalgrade', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
                                 null, '0', 'lasteditedbyuser');

        // Conditionally launch add field isfinalgrade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('ismoderation', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
                                 null, '0', 'isfinalgrade');

        // Conditionally launch add field ismoderation.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // We need to make sure that the flags are set properly on all current records.
        // This just means that the ones that are multiple marked and which have a capped grade
        // need to be flagged as final.
        $sql = "SELECT f.*
                  FROM {coursework_feedbacks} f
            INNER JOIN {coursework_submissions} s
                    ON f.submissionid = s.id
            INNER JOIN {coursework} c
                    ON c.id = s.courseworkid
                 WHERE c.numberofmarkers > 1
                  AND f.cappedgrade IS NOT NULL
                  ";
        $feedbacks = $DB->get_records_sql($sql);
        foreach ($feedbacks as $feedback) {
            $feedback->isfinalgrade = 1;
            $DB->update_record('coursework_feedbacks', $feedback);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012053102, 'coursework');
    }

    if ($oldversion < 2012061400) {

        // Define table coursework_allocation_pairs to be created.
        $table = new xmldb_table('coursework_allocation_pairs');

        // Adding fields to table coursework_allocation_pairs.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('assessorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('manual', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table coursework_allocation_pairs.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for coursework_allocation_pairs.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012061400, 'coursework');
    }

    if ($oldversion < 2012062200) {

        // Define field allocationstrategy to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('allocationstrategy', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL,
                            null, 'coursework_allocation_strategy_none', 'etype');

        // Conditionally launch add field allocationstrategy.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012062200, 'coursework');
    }

    if ($oldversion < 2012062201) {

        // Changing nullability of field allocationstrategy on table coursework to not null.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('allocationstrategy', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL,
                            null, 'equal', 'etype');

        // Launch change of nullability for field allocationstrategy.
        $dbman->change_field_default($table, $field);

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012062201, 'coursework');

        // Add defaults to existing stuff.
        $courseworks = $DB->get_records('coursework');
        foreach ($courseworks as $coursework) {
            $coursework->assessorallocationstrategy = 'equal';
            $DB->update_record('coursework', $coursework);
        }
    }

    if ($oldversion < 2012062500) {

        // Define field moderationenabled to be added to coursework.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('moderationenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'allocationstrategy');

        // Conditionally launch add field moderationenabled.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012062500, 'coursework');
    }

    if ($oldversion < 2012062600) {

        // Define field allocationenabled to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('allocationenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'moderationenabled');

        // Conditionally launch add field allocationenabled.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012062600, 'coursework');
    }

    if ($oldversion < 2012062603) {

        // Define field moderator to be added to coursework_allocation_pairs.
        $table = new xmldb_table('coursework_allocation_pairs');
        $field = new xmldb_field('moderator', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'manual');

        // Conditionally launch add field moderator.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012062603, 'coursework');
    }

    if ($oldversion < 2012062800) {

        // Define table coursework_moderation_set_ru to be created.
        $table = new xmldb_table('coursework_mod_set_rules');

        // Adding fields to table coursework_moderation_set_ru.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE,
                          null);
        $table->add_field('courseworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null,
                          null);
        $table->add_field('rulename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ruleorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table coursework_moderation_set_ru.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('coursework_fk', XMLDB_KEY_FOREIGN, array('courseworkid'), 'coursework',
                        array('id'));

        // Conditionally launch create table for coursework_moderation_set_ru.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012062800, 'coursework');
    }

    if ($oldversion < 2012062801) {

        // Define table coursework_mod_set_rule_bg to be created.
        $table = new xmldb_table('coursework_mod_set_rule_gr');

        // Adding fields to table coursework_mod_set_rule_bg.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE,
                          null);
        $table->add_field('ruleinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null,
                          null);
        $table->add_field('upperlimit', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null,
                          null);

        // Adding keys to table coursework_mod_set_rule_bg.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('ruleinstance_fk', XMLDB_KEY_FOREIGN, array('ruleinstanceid'),
                        'coursework_mod_set_rules', array('id'));

        // Conditionally launch create table for coursework_mod_set_rule_bg.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012062801, 'coursework');
    }

    if ($oldversion < 2012062802) {

        // Define field below to be added to coursework_mod_set_rule_bg.
        $table = new xmldb_table('coursework_mod_set_rule_gr');
        $field =
            new xmldb_field('lowerlimit', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null, 'upperlimit');

        // Conditionally launch add field below.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012062802, 'coursework');
    }

    // Move the fields in the extension table into the main moderation sets table.
    if ($oldversion < 2012062900) {

        // Define field upperlimit to be added to coursework_mod_set_rules.
        $table = new xmldb_table('coursework_mod_set_rules');
        $field = new xmldb_field('upperlimit', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null, 'ruleorder');

        // Conditionally launch add field upperlimit.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('lowerlimit', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null, 'upperlimit');

        // Conditionally launch add field lowerlimit.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table coursework_mod_set_rule_gr to be dropped.
        $table = new xmldb_table('coursework_mod_set_rule_gr');

        // Conditionally launch drop table for coursework_mod_set_rule_gr.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012062900, 'coursework');
    }

    if ($oldversion < 2012070200) {

        // Define field timelocked to be added to coursework_allocation_pairs.
        $table = new xmldb_table('coursework_allocation_pairs');
        $field = new xmldb_field('timelocked', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'moderator');

        // Conditionally launch add field timelocked.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012070200, 'coursework');
    }

    if ($oldversion < 2012070300) {

        // Define table coursework_allocation_config to be created.
        $table = new xmldb_table('coursework_allocation_config');

        // Adding fields to table coursework_allocation_config.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('allocationstrategy', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('assessorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table coursework_allocation_config.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('courseworkid_fk', XMLDB_KEY_FOREIGN, array('courseworkid'), 'coursework', array('id'));
        $table->add_key('assessorid_fk', XMLDB_KEY_FOREIGN, array('assessorid'), 'user', array('id'));

        // Conditionally launch create table for coursework_allocation_config.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012070300, 'coursework');
    }

    if ($oldversion < 2012070301) {

        // Define field moderatorallocationstrategy to be added to coursework.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('moderatorallocationstrategy', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'allocationenabled');

        // Conditionally launch add field moderatorallocationstrategy.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012070301, 'coursework');
    }

    if ($oldversion < 2012070400) {

        // Rename field allocationstrategy on table coursework to assessorallocationstrategy.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('allocationstrategy', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'equal', 'etype');

        // Launch rename field allocationstrategy.
        $dbman->rename_field($table, $field, 'assessorallocationstrategy');

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012070400, 'coursework');
    }

    if ($oldversion < 2012070401) {

        // Define field purpose to be added to coursework_allocation_config.
        $table = new xmldb_table('coursework_allocation_config');
        $field = new xmldb_field('purpose', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'value');

        // Conditionally launch add field purpose.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012070401, 'coursework');
    }

    if ($oldversion < 2012070900) {

        // Define field feedbackcommentformat to be added to coursework_feedbacks.
        $table = new xmldb_table('coursework_feedbacks');
        $field = new xmldb_field('feedbackcommentformat', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'ismoderation');

        // Conditionally launch add field feedbackcommentformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012070900, 'coursework');
    }

    if ($oldversion < 2012071000) {

        // Define table mitigation to be renamed to coursework_mitigation_codes.
        $table = new xmldb_table('mitigation');

        // Launch rename table for mitigation.
        $dbman->rename_table($table, 'coursework_mitigation_codes');

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012071000, 'coursework');
    }

    if ($oldversion < 2012071001) {

        // Define table coursework_mitigations to be created.
        $table = new xmldb_table('coursework_mitigations');

        // Adding fields to table coursework_mitigations.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mitigationcode', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('mitigationcomment', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('mitigationcommentformat', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('cappedgrade', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
        $table->add_field('extradeadlineseconds', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lastmodifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table coursework_mitigations.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('courseworkid_fk', XMLDB_KEY_FOREIGN, array('courseworkid'), 'coursework', array('id'));
        $table->add_key('studentid_fk', XMLDB_KEY_FOREIGN, array('studentid'), 'user', array('id'));
        $table->add_key('lastmodifiedby_fk', XMLDB_KEY_FOREIGN, array('lastmodifiedby'), 'user', array('id'));
        $table->add_key('createdby_fk', XMLDB_KEY_FOREIGN, array('createdby'), 'user', array('id'));

        // Conditionally launch create table for coursework_mitigations.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012071001, 'coursework');
    }

    if ($oldversion < 2012071700) {

        // Define field viewothersfeedback to be added to coursework.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('viewothersfeedback', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
                            null, '0', 'moderatorallocationstrategy');

        // Conditionally launch add field viewothersfeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012071700, 'coursework');
    }

    // Capped grades are now calculated dynamically.
    if ($oldversion < 2012071800) {

        // Define field cappedgrade to be dropped from coursework_feedbacks.
        $table = new xmldb_table('coursework_feedbacks');
        $field = new xmldb_field('cappedgrade');

        // Conditionally launch drop field cappedgrade.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012071800, 'coursework');

    }

    if ($oldversion < 2012072000) {

        // Rename field reminder_type on table coursework_reminder to remindernumber.
        $table = new xmldb_table('coursework_reminder');
        $field = new xmldb_field('reminder_type', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'coursework_id');

        // Launch rename field reminder_type.
        $dbman->rename_field($table, $field, 'remindernumber');

        $sql = "
            UPDATE {coursework_reminder}
               SET remindernumber = 1
             WHERE remindernumber = 100
        ";
        $DB->execute($sql);
        $sql = "
            UPDATE {coursework_reminder}
               SET remindernumber = 2
             WHERE remindernumber = 200
        ";
        $DB->execute($sql);

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012072000, 'coursework');
    }

    if ($oldversion < 2012072300) {

        // Define field autoreleasefeedback to be added to coursework.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('autoreleasefeedback', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'viewothersfeedback');

        // Conditionally launch add field autoreleasefeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012072300, 'coursework');
    }

    if ($oldversion < 2012072301) {

        // Define field reminder to be dropped from coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('reminder');

        // Conditionally launch drop field reminder.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012072301, 'coursework');
    }

    if ($oldversion < 2012072401) {

        // Define field submissionnotneeded to be added to coursework_mitigations.
        $table = new xmldb_table('coursework_mitigations');
        $field = new xmldb_field('submissionnotneeded', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'createdby');

        // Conditionally launch add field submissionnotneeded.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012072401, 'coursework');
    }

    if ($oldversion < 2012072500) {

        // Changing nullability of field submissionnotneeded on table coursework_mitigations to null.
        $table = new xmldb_table('coursework_mitigations');
        $field = new xmldb_field('submissionnotneeded', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'createdby');

        // Launch change of nullability for field submissionnotneeded.
        $dbman->change_field_notnull($table, $field);

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012072500, 'coursework');
    }

    if ($oldversion < 2012072501) {

        // Changing the default of field submissionnotneeded on table coursework_mitigations to drop it.
        $table = new xmldb_table('coursework_mitigations');
        $field = new xmldb_field('submissionnotneeded', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'createdby');

        // Launch change of default for field submissionnotneeded.
        $dbman->change_field_default($table, $field);

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012072501, 'coursework');
    }

    if ($oldversion < 2012072601) {

        // Define field entry_id to be added to coursework_feedbacks.
        $table = new xmldb_table('coursework_feedbacks');
        $field = new xmldb_field('entry_id', XMLDB_TYPE_INTEGER, '10', null, null);

        // Conditionally launch add field viewothersfeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012072601, 'coursework');
    }

    if ($oldversion < 2012080800) {

        // Define index mitigation_id (not unique) to be dropped form coursework_submissions.
        $table = new xmldb_table('coursework_submissions');

        // Conditionally launch drop index mitigation_id.
        $index = new xmldb_index('mitigation_id', XMLDB_INDEX_NOTUNIQUE, array('mitigation_id'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Conditionally launch drop field mitigation_id.
        $field = new xmldb_field('mitigation_id');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Conditionally launch drop field mitigation_comment.
        $field = new xmldb_field('mitigation_comment');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012080800, 'coursework');
    }

    if ($oldversion < 2012080900) {

        // Define field retrospectivemoderation to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('retrospectivemoderation', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
                                 null, '0', 'autoreleasefeedback');

        // Conditionally launch add field retrospectivemoderation.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012080900, 'coursework');
    }

    if ($oldversion < 2012081000) {

        // Define field minimum to be added to coursework_mod_set_rules.
        $table = new xmldb_table('coursework_mod_set_rules');
        $field = new xmldb_field('minimum', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'lowerlimit');

        // Conditionally launch add field minimum.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012081000, 'coursework');
    }

    if ($oldversion < 2012081300) {

        // Define field studentviewcomponentfeedbacks to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('studentviewcomponentfeedbacks', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
                                 null, '0', 'retrospectivemoderation');

        // Conditionally launch add field studentviewcomponentfeedbacks.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Make sure existing multiple markers can see feedback by default.
        $sql = "
            UPDATE {coursework}
               SET studentviewcomponentfeedbacks = 1
             WHERE numberofmarkers > 1
        ";
        $DB->execute($sql);

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012081300, 'coursework');
    }

    if ($oldversion < 2012081400) {

        // Define field studentviewmoderatorfeedbacks to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('studentviewmoderatorfeedbacks', XMLDB_TYPE_INTEGER, '1',
                                 null, XMLDB_NOTNULL, null, '0', 'studentviewcomponentfeedbacks');

        // Conditionally launch add field studentviewmoderatorfeedbacks.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012081400, 'coursework');
    }

    if ($oldversion < 2012082000) {

        // Define field strictanonymity to be added to coursework.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('strictanonymity', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'studentviewmoderatorfeedbacks');

        // Conditionally launch add field strictanonymity.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012082000, 'coursework');
    }

    if ($oldversion < 2012082300) {

        // Define field strictanonymity to be added to coursework.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('srsinclude', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'deadline');

        // Conditionally launch add field strictanonymity.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012082300, 'coursework');
    }

    if ($oldversion < 2012082400) {

        // Rename field etype on table coursework to courseworktype.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('etype', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'generalfeedbacktimepublished');

        // Launch rename field etype.
        $dbman->rename_field($table, $field, 'courseworktype');

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012082400, 'coursework');
    }

    if ($oldversion < 2012082401) {

        // Define field manualsrscode to be added to coursework_submissions.
        $table = new xmldb_table('coursework_submissions');
        $field = new xmldb_field('manualsrscode', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'finalised');

        // Conditionally launch add field manualsrscode.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012082401, 'coursework');
    }

    if ($oldversion < 2012082402) {

        // Changing nullability of field manualsrscode on table coursework_submissions to not null.
        $table = new xmldb_table('coursework_submissions');

        $sql = "UPDATE {coursework_submissions} SET manualsrscode = '' WHERE manualsrscode IS NULL";
        $DB->execute($sql);

        $field = new xmldb_field('manualsrscode', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null, 'finalised');
        // Launch change of nullability for field manualsrscode.
        $dbman->change_field_notnull($table, $field);

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012082402, 'coursework');
    }

    // Fields for controlling feedback and  grade visibility.
    if ($oldversion < 2012082403) {

        // Define field studentviewfinalfeedback to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('studentviewfinalfeedback', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
                                 null, '1', 'strictanonymity');
        // Conditionally launch add field studentviewfinalfeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('studentviewcomponentgrades', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
                                 null, '1', 'studentviewfinalfeedback');
        // Conditionally launch add field studentviewcomponentgrades.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('studentviewfinalgrade', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
                                 null, '1', 'studentviewcomponentgrades');
        // Conditionally launch add field studentviewfinalgrade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('studentviewmoderatorgrade', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
                                 null, '0', 'studentviewfinalgrade');
        // Conditionally launch add field studentviewmoderatorgrade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012082403, 'coursework');
    }

    if ($oldversion < 2012082404) {

        // Define field strictanonymitymoderator to be added to coursework.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('strictanonymitymoderator', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
                            null, '0', 'studentviewmoderatorgrade');

        // Conditionally launch add field strictanonymitymoderator.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012082404, 'coursework');
    }

    if ($oldversion < 2012090500) {

        // Define field markernumber to be added to coursework_feedbacks.
        $table = new xmldb_table('coursework_feedbacks');
        $field = new xmldb_field('markernumber', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'entry_id');

        // Conditionally launch add field markernumber.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Now make it so that all the fields hold the right values.
        // All the first ones.
        $sql = "
            SELECT main.id
              FROM {coursework_feedbacks} main
             WHERE main.ismoderation = 0
               AND main.isfinalgrade = 0
               AND NOT EXISTS (SELECT 1
                                 FROM {coursework_feedbacks} comparison
                                WHERE comparison.ismoderation = 0
                                  AND comparison.isfinalgrade = 0
                                  AND comparison.timecreated < main.timecreated
                                  AND comparison.submissionid = main.submissionid)
        ";
        $feedbacks = $DB->get_records_sql($sql);
        foreach ($feedbacks as $feedback) {
            $DB->set_field('coursework_feedbacks', 'markernumber', 1, array('id' => $feedback->id));
        }

        // Then the second ones.
        $sql = "
            SELECT main.id
              FROM {coursework_feedbacks} main
             WHERE main.ismoderation = 0
               AND main.isfinalgrade = 0
               AND main.markernumber = 0
               AND NOT EXISTS (SELECT 1
                                 FROM {coursework_feedbacks} comparison
                                WHERE comparison.ismoderation = 0
                                  AND comparison.isfinalgrade = 0
                                  AND comparison.timecreated < main.timecreated
                                  AND comparison.markernumber = 0
                                  AND comparison.submissionid = main.submissionid)
        ";
        $feedbacks = $DB->get_records_sql($sql);
        foreach ($feedbacks as $feedback) {
            $DB->set_field('coursework_feedbacks', 'markernumber', 2, array('id' => $feedback->id));
        }
        // And the third.
        $sql = "
            SELECT main.id
              FROM {coursework_feedbacks} main
             WHERE main.ismoderation = 0
               AND main.isfinalgrade = 0
               AND main.markernumber = 0
               AND NOT EXISTS (SELECT 1
                                 FROM {coursework_feedbacks} comparison
                                WHERE comparison.ismoderation = 0
                                  AND comparison.isfinalgrade = 0
                                  AND comparison.timecreated < main.timecreated
                                  AND comparison.markernumber = 0
                                  AND comparison.submissionid = main.submissionid)
        ";
        $feedbacks = $DB->get_records_sql($sql);
        foreach ($feedbacks as $feedback) {
            $DB->set_field('coursework_feedbacks', 'markernumber', 3, array('id' => $feedback->id));
        }

        // Just in case we have others floating around...
        $sql = "
            SELECT main.id
              FROM {coursework_feedbacks} main
             WHERE main.ismoderation = 0
               AND main.isfinalgrade = 0
               AND main.markernumber = 0
               AND NOT EXISTS (SELECT 1
                                 FROM {coursework_feedbacks} comparison
                                WHERE comparison.ismoderation = 0
                                  AND comparison.isfinalgrade = 0
                                  AND comparison.timecreated < main.timecreated
                                  AND comparison.markernumber = 0
                                  AND comparison.submissionid = main.submissionid)
        ";
        $feedbacks = $DB->get_records_sql($sql);
        foreach ($feedbacks as $feedback) {
            $DB->set_field('coursework_feedbacks', 'markernumber', 4, array('id' => $feedback->id));
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2012090500, 'coursework');
    }

    if ($oldversion < 2013011400) {

        // Define field allowlatesubmissions to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('allowlatesubmissions', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'strictanonymitymoderator');

        // Conditionally launch add field allowlatesubmissions.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2013011400, 'coursework');
    }


    if ($oldversion < 2014031800) {

        // Define field mitigationenabled to be added to coursework.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('mitigationenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'allowlatesubmissions');

        // Conditionally launch add field mitigationenabled.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014031800, 'coursework');
    }

    if ($oldversion < 2014032500) {

        // Define field enablegeneralfeedback to be added to coursework.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('enablegeneralfeedback', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'mitigationenabled');

        // Conditionally launch add field enablegeneralfeedback.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014032500, 'coursework');
    }

    if ($oldversion < 2014042800) {

        // Define field maxfiles to be added to coursework.
        $table = new xmldb_table('coursework');
        $field =
            new xmldb_field('maxfiles', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'enablegeneralfeedback');

        // Conditionally launch add field maxfiles.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014042800, 'coursework');
    }

    if ($oldversion < 2014042900) {

        // Define field filetypes to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('filetypes', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'maxfiles');

        // Conditionally launch add field filetypes.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014042900, 'coursework');
    }

    if ($oldversion < 2014052100) {

        // Define field use_groups to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('use_groups', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'filetypes');

        // Conditionally launch add field use_groups.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field groupingid to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('grouping_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'use_groups');

        // Conditionally launch add field groupingid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014052100, 'coursework');
    }

    if ($oldversion < 2014071000) {

        // Define table coursework_mod_set_members to be created.
        $table = new xmldb_table('coursework_mod_set_members');

        // Adding fields to table coursework_mod_set_members.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table coursework_mod_set_members.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for coursework_mod_set_members.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014071000, 'coursework');
    }

    if ($oldversion < 2014071400) {

        // Define field stage_identifier to be added to coursework_allocation_pairs.
        $table = new xmldb_table('coursework_allocation_pairs');
        $field = new xmldb_field('stage_identifier', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'timelocked');

        // Conditionally launch add field stage_identifier.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014071400, 'coursework');
    }


    if ($oldversion < 2014071404) {

        // Add stage identifier to all allocations.

        $courseworks = $DB->get_records('coursework', null, '', 'id');
        foreach($courseworks as $coursework) {
            $coursework = \mod_coursework\models\coursework::find($coursework->id);
            $students_with_assessor_allocations = $DB->get_records_sql('
                SELECT DISTINCT studentid
                  FROM {coursework_allocation_pairs} ap
                 WHERE ap.courseworkid = :courseworkid
                   AND ap.moderator = 0

            ', array('courseworkid' => $coursework->id));
            foreach ($students_with_assessor_allocations as $student) {
                $params = array(
                    'studentid' => $student->studentid,
                    'courseworkid' => $coursework->id,
                    'moderator' => 0,
                );
                $allocations = $DB->get_records('coursework_allocation_pairs', $params);
                $number = 1;
                foreach ($allocations as $allocation) {
                    $allocation->stage_identifier = 'assessor_'.$number;
                    $DB->update_record('coursework_allocation_pairs', $allocation);
                    $number++;
                }
            }
        }

        $DB->execute("
            UPDATE {coursework_allocation_pairs}
               SET stage_identifier = 'moderator_1'
             WHERE moderator = 1
        ");

        upgrade_mod_savepoint(true, 2014071404, 'coursework');
    }

    if ($oldversion < 2014071500) {

        $table = new xmldb_table('coursework_fdata');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('coursework_form');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('coursework_field');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('coursework_ele_list');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }


        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014071500, 'coursework');
    }

    if ($oldversion < 2014071501) {

        // Define field stage_identifier to be added to coursework_feedbacks.
        $table = new xmldb_table('coursework_feedbacks');
        $field = new xmldb_field('stage_identifier', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'markernumber');

        // Conditionally launch add field stage_identifier.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014071501, 'coursework');
    }

    if ($oldversion < 2014071502) {

        // Set the stage identifier for each of the feedbacks
        $courseworks = $DB->get_records('coursework', null, '', 'id');
        foreach ($courseworks as $coursework) {
            $coursework = \mod_coursework\models\coursework::find($coursework->id);

            $submissions_with_feedbacks = $DB->get_records_sql('
                SELECT DISTINCT s.id
                  FROM {coursework_feedbacks} f
            INNER JOIN {coursework_submissions} s
                    ON f.submissionid = s.id
                 WHERE s.courseworkid = :courseworkid
                   AND f.isfinalgrade = 0
                   AND f.ismoderation = 0
            ', array('courseworkid' => $coursework->id));

            foreach ($submissions_with_feedbacks as $submission) {
                $params = array(
                    'submissionid' => $submission->id,
                    'isfinalgrade' => 0,
                    'ismoderation' => 0,
                );
                $feedbacks = $DB->get_records('coursework_feedbacks', $params);
                $number = 1;
                foreach ($feedbacks as $feedback) {
                    $feedback->stage_identifier = 'assessor_' . $number;
                    $DB->update_record('coursework_feedbacks', $feedback);
                    $number++;
                }
            }
        }

        $DB->execute("
            UPDATE {coursework_feedbacks}
               SET stage_identifier = 'moderator_1'
             WHERE ismoderation = 1
               AND isfinalgrade = 0
        ");

        $DB->execute("
            UPDATE {coursework_feedbacks}
               SET stage_identifier = 'final_agreed_1'
             WHERE ismoderation = 0
              AND isfinalgrade = 1
        ");


        upgrade_mod_savepoint(true, 2014071502, 'coursework');
    }

    if ($oldversion < 2014071506) {

        // Previous thing left single marker courseworks with their only feedbacks as final_agreed_1.
        // Need to change them to assessor 1.

        $feedbacks = $DB->get_records_sql("
            SELECT f.*
              FROM {coursework_feedbacks} f
        INNER JOIN {coursework_submissions} AS s
                ON f.submissionid = s.id
        INNER JOIN {coursework} c
                ON s.courseworkid = c.id
             WHERE f.ismoderation = 0
               AND f.isfinalgrade = 1
               AND c.numberofmarkers = 1
        ");

        foreach ($feedbacks as $feedback) {
            $feedback->stage_identifier = 'assessor_1';
            $DB->update_record('coursework_feedbacks', $feedback);
        }

        upgrade_mod_savepoint(true, 2014071506, 'coursework');
    }

    if ($oldversion < 2014071507) {

        // Extra fields for the submissions table

        // Define field createdby to be added to coursework_submissions.
        $table = new xmldb_table('coursework_submissions');
        $field =
            new xmldb_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'manualsrscode');

        // Conditionally launch add field createdby.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field =
            new xmldb_field('lastupdatedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'createdby');
        // Conditionally launch add field lastupdatedby.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field =
            new xmldb_field('allocatableid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'lastupdatedby');
        // Conditionally launch add field allocatableid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('allocatabletype',
                                 XMLDB_TYPE_CHAR,
                                 '20',
                                 null,
                                 XMLDB_NOTNULL,
                                 null,
                                 'user',
                                 'allocatableid');
        // Conditionally launch add field allocatabletype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014071507, 'coursework');
    }

    if ($oldversion < 2014071508) {

        $DB->execute("
            UPDATE {coursework_submissions}
               SET allocatableid = userid
        ");

        $DB->execute("
            UPDATE {coursework_submissions}
               SET createdby = userid
        ");

        $DB->execute("
            UPDATE {coursework_submissions}
               SET lastupdatedby = userid
        ");

        upgrade_mod_savepoint(true, 2014071508, 'coursework');
    }

    if ($oldversion < 2014071509) {

        // Add allocatable columns to the allocations table.

        // Define field allocatableid to be added to coursework_allocation_pairs.
        $table = new xmldb_table('coursework_allocation_pairs');
        $field = new xmldb_field('allocatableid',
                                 XMLDB_TYPE_INTEGER,
                                 '10',
                                 null,
                                 XMLDB_NOTNULL,
                                 null,
                                 '0',
                                 'stage_identifier');

        // Conditionally launch add field allocatableid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('allocatabletype',
                                 XMLDB_TYPE_CHAR,
                                 '20',
                                 null,
                                 XMLDB_NOTNULL,
                                 null,
                                 'user',
                                 'allocatableid');

        // Conditionally launch add field allocatabletype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014071509, 'coursework');
    }

    if ($oldversion < 2014071510) {

        $DB->execute("
            UPDATE {coursework_allocation_pairs}
               SET allocatableid = studentid
        ");

        upgrade_mod_savepoint(true, 2014071510, 'coursework');
    }

    if ($oldversion < 2014071512) {

        // Set group submission allocatables

        $courseworks_with_groups = $DB->get_records('coursework', array('use_groups' => 1));

        foreach ($courseworks_with_groups as $coursework) {
            $coursework = \mod_coursework\models\coursework::find($coursework);

            $params = array(
                'courseworkid' => $coursework->id,
            );
            $submissions = $DB->get_records('coursework_submissions', $params);
            foreach ($submissions as $submission) {
                $group = $coursework->get_student_group($submission->userid);
                $submission->allocatableid = $group->id;
                $submission->allocatabletype = 'group';
                $DB->update_record('coursework_submissions', $submission);
            }
        }

        upgrade_mod_savepoint(true, 2014071512, 'coursework');
    }

    if ($oldversion < 2014071800) {

        // Define field allocatableid to be added to coursework_mod_set_members.
        $table = new xmldb_table('coursework_mod_set_members');
        $field =
            new xmldb_field('allocatableid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'studentid');
        // Conditionally launch add field allocatableid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('allocatabletype',
                                 XMLDB_TYPE_CHAR,
                                 '20',
                                 null,
                                 XMLDB_NOTNULL,
                                 null,
                                 'user',
                                 'allocatableid');
        // Conditionally launch add field allocatabletype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014071800, 'coursework');
    }

    if ($oldversion < 2014071801) {

        if (in_array('studentid', $DB->get_columns('coursework_mod_set_members'))) {

            $DB->execute("
                UPDATE {coursework_mod_set_members}
                   SET allocatableid = studentid
            ");
        }

        upgrade_mod_savepoint(true, 2014071801, 'coursework');
    }

    if ($oldversion < 2014072100) {

        // Define field firstpublished to be added to coursework_submissions.
        $table = new xmldb_table('coursework_submissions');

        $field = new xmldb_field('firstpublished', XMLDB_TYPE_INTEGER, '12', null, null, null, null, 'allocatabletype');
        // Conditionally launch add field firstpublished.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('lastpublished', XMLDB_TYPE_INTEGER, '12', null, null, null, null, 'firstpublished');
        // Conditionally launch add field lastpublished.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014072100, 'coursework');
    }

    if ($oldversion < 2014072200) {

        // Define field studentid to be dropped from coursework_allocation_pairs.
        $table = new xmldb_table('coursework_allocation_pairs');
        $field = new xmldb_field('studentid');

        // Conditionally launch drop field studentid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014072200, 'coursework');
    }

    if ($oldversion < 2014072300) {

        // Define field studentid to be dropped from coursework_mod_set_members.
        $table = new xmldb_table('coursework_mod_set_members');
        $field = new xmldb_field('studentid');

        // Conditionally launch drop field studentid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014072300, 'coursework');
    }

    if ($oldversion < 2014072400) {

        // Define field gradepercent to be dropped from coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('gradepercent');

        // Conditionally launch drop field gradepercent.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014072400, 'coursework');
    }

    if ($oldversion < 2014072500) {

        // Define field allowearlyfinalisation to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('allowearlyfinalisation',
                                 XMLDB_TYPE_INTEGER,
                                 '1',
                                 null,
                                 XMLDB_NOTNULL,
                                 null,
                                 '0',
                                 'grouping_id');

        // Conditionally launch add field allowearlyfinalisation.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014072500, 'coursework');
    }


    if ($oldversion < 2014072502) {

        // Define field showallfeedbacks to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('showallfeedbacks',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'allowearlyfinalisation');

        // Conditionally launch add field showallfeedbacks.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014072502, 'coursework');
    }


    if ($oldversion < 2014082600) {

        // Define table coursework_mitigation_codes to be dropped.
        $table = new xmldb_table('coursework_mitigation_codes');
        // Conditionally launch drop table for coursework_mitigation_codes.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('coursework_mitigations');
        // Conditionally launch drop table for coursework_mitigations.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('coursework_form_component');
        // Conditionally launch drop table for coursework_form_component.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014082600, 'coursework');
    }

    if ($oldversion < 2014090200) {
        $DB->execute("UPDATE {coursework} SET moderationenabled = 0");
    }



    if ($oldversion < 2014090801) {

        // Define field timesubmitted to be added to coursework_submissions.
        $table = new xmldb_table('coursework_submissions');
        $field = new xmldb_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'lastpublished');

        // Conditionally launch add field timesubmitted.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014090801, 'coursework');
    }

    if ($oldversion < 2014091000) {

        // Define field startdate to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('startdate', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'showallfeedbacks');

        // Conditionally launch add field startdate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014091000, 'coursework');
    }


    if ($oldversion < 2014111700) {

        // Define field samplingenabled to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('samplingenabled', XMLDB_TYPE_INTEGER, '1', null, true, null, '0');

        // Conditionally launch add field startdate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014111700, 'coursework');
    }

    if ($oldversion < 2014111800) {

        // Define field stage_identifier to be added to coursework.
        $table = new xmldb_table('coursework_mod_set_members');
        $field = new xmldb_field('stage_identifier', XMLDB_TYPE_CHAR, '20', null, null, null, null);

        // Conditionally launch add field startdate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014111800, 'coursework');
    }

    if ($oldversion < 2014120400) {

        // Define table coursework_extensions to be created.
        $table = new xmldb_table('coursework_extensions');

        // Adding fields to table coursework_extensions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('allocatableid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('allocatabletype', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coursework_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('extended_deadline', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table coursework_extensions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for coursework_extensions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014120400, 'coursework');
    }

    if ($oldversion < 2014120401) {

        // Changing type of field allocatabletype on table coursework_extensions to int.
        $table = new xmldb_table('coursework_extensions');
        $field = new xmldb_field('allocatabletype',
                                 XMLDB_TYPE_CHAR,
                                 '25',
                                 null, XMLDB_NOTNULL, null, null, 'allocatableid');

        // Launch change of type for field allocatabletype.
        $dbman->change_field_type($table, $field);

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014120401, 'coursework');
    }

    if ($oldversion < 2014120403) {

        // Rename field courseworkid on table coursework_extensions to courseworkid.
        $table = new xmldb_table('coursework_extensions');
        $field = new xmldb_field('coursework_id',
                                 XMLDB_TYPE_INTEGER,
                                 '10',
                                 null,
                                 XMLDB_NOTNULL,
                                 null,
                                 null,
                                 'allocatabletype');

        // Launch rename field courseworkid.
        $dbman->rename_field($table, $field, 'courseworkid');

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014120403, 'coursework');
    }

    if ($oldversion < 2014120900) {

        // Define field pre_defined_reason to be added to coursework_extensions.
        $table = new xmldb_table('coursework_extensions');
        $field =
            new xmldb_field('pre_defined_reason', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'extended_deadline');

        // Conditionally launch add field pre_defined_reason.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014120900, 'coursework');
    }

    if ($oldversion < 2014121000) {

        // Define field createdbyid to be added to coursework_extensions.
        $table = new xmldb_table('coursework_extensions');
        $field = new xmldb_field('createdbyid',
                                 XMLDB_TYPE_INTEGER,
                                 '10',
                                 null,
                                 XMLDB_NOTNULL,
                                 null,
                                 0,
                                 'pre_defined_reason');

        // Conditionally launch add field createdbyid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('extra_information', XMLDB_TYPE_TEXT, null, null, null, null, null, 'createdbyid');

        // Conditionally launch add field extra_information.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014121000, 'coursework');
    }

    if ($oldversion < 2014121203) {

        // Rename field extra_information_text on table coursework_extensions to extra_information_text
        $table = new xmldb_table('coursework_extensions');
        $field =
            new xmldb_field('extra_information', XMLDB_TYPE_TEXT, null, null, null, null, null, 'createdbyid');

        // Launch rename field extra_information_text.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'extra_information_text');
        }

        $field = new xmldb_field('extra_information_format',
                                 XMLDB_TYPE_INTEGER,
                                 '2',
                                 null,
                                 null,
                                 null,
                                 null,
                                 'extra_information_text');

        // Conditionally launch add field extra_information_format.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014121203, 'coursework');
    }


    if ($oldversion < 2014122200) {

        // Define field extensionsenabled to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('extensionsenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'samplingenabled');

        // Conditionally launch add field extensionsenabled.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2014122200, 'coursework');
    }

    if ($oldversion < 2015060500) {

        // Define field assessoranonymity to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('assessoranonymity', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'extensionsenabled');

        // Conditionally launch add field assessoranonymity.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2015060500, 'coursework');
    }

    if ($oldversion < 2015061502) {

        // Define field automaticagreement to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('automaticagreement', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'assessoranonymity');

        // Conditionally launch add field automaticagreement.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field automaticagreementrange to be added to coursework.
        $field = new xmldb_field('automaticagreementrange', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'automaticagreement');

        // Conditionally launch add field automaticagreementrange.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2015061502, 'coursework');
    }

    if ($oldversion < 2015062500) {

        // Define field automaticagreementstrategy to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('automaticagreementstrategy',
                                 XMLDB_TYPE_CHAR,
                                 '255',
                                 null,
                                 XMLDB_NOTNULL,
                                 null,
                                 'null',
                                 'automaticagreementrange');

        // Conditionally launch add field automaticagreementstrategy.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2015062500, 'coursework');
    }

    if ($oldversion < 2015072800) {

        // Define field viewinitialgradeenabled to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('viewinitialgradeenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'assessoranonymity');

        // Conditionally launch add field viewinitialgradeenabled.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2015072800, 'coursework');
    }

    if ($oldversion < 2015082402) {

        // Define field feedbackreleaseemail to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('feedbackreleaseemail', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'automaticagreementstrategy');

        // Conditionally launch add field feedbackreleaseemail.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2015082402, 'coursework');
    }


    if ($oldversion < 2015082409) {
        // Define table coursework_sample_set_rules to be created.
        $table = new xmldb_table('coursework_sample_set_rules');

        // Adding fields to table coursework_sample_set_rules.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE,
            null);
        $table->add_field('courseworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null,
            null);
        $table->add_field('sample_set_plugin_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ruleorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('ruletype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('upperlimit', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lowerlimit', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('stage_identifier', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table coursework_moderation_set_ru.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));


        // Conditionally launch create table for coursework_moderation_set_ru.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }


        $table = new xmldb_table('coursework_sample_set_plugin');

        // Adding fields to table coursework_sample_set_rules.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE,
            null);
        $table->add_field('rulename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('pluginorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table coursework_moderation_set_ru.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));


        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);


            //now create plugin records for sample set plugins
            $plugins =   array('range_sample_type','total_sample_type');

            $i  =   1;

            foreach($plugins as $p) {
                $dbrecord = new \stdClass();
                $dbrecord->rulename = $p;
                $dbrecord->pluginorder = $i;

                $DB->insert_record('coursework_sample_set_plugin',$dbrecord);
                $i++;
            }

        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2015082409, 'coursework');

    }


    if ($oldversion < 2015082410) {

        // Define table coursework_sample_set_mbrs to be created.
        $table = new xmldb_table('coursework_sample_set_mbrs');

        // Adding fields to table coursework_sample_set_mbrs.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('allocatableid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('allocatabletype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('stage_identifier', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('selectiontype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'manual');

        // Adding keys to table coursework_sample_set_mbrs.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for coursework_sample_set_mbrs.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2015082410, 'coursework');

    }


    $allocatabletables=array('coursework_submissions',
                             'coursework_allocation_pairs',
                             'coursework_mod_set_members',
                             'coursework_extensions',
                             'coursework_sample_set_mbrs');


    if($oldversion < 2015110303) {
        $fields = array();
        //Add fields to assist backup annotation
        $fielduser = new xmldb_field('allocatableuser', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, null, '0', 'allocatabletype');
        $fieldgroup = new xmldb_field('allocatablegroup', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, null, '0', 'allocatableuser');

        $fielduser->setNotNull(false);
        $fieldgroup->setNotNull(false);

        foreach ($allocatabletables as $tablename) {
            $table = new xmldb_table($tablename);

            if (!$dbman->field_exists($table, $fielduser)) {
                $dbman->add_field($table, $fielduser);
            }

            if (!$dbman->field_exists($table, $fieldgroup)) {
                $dbman->add_field($table, $fieldgroup);
            }
        }
        upgrade_mod_savepoint(true, 2015110303, 'coursework');
    }



    if($oldversion < 2015121401) {
        $fields = array();
        //Add fields to assist backup annotation
        $fieldeditingtime = new xmldb_field('gradeeditingtime', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, null, '0', null);

        $fieldeditingtime->setNotNull(true);

        $table = new xmldb_table('coursework');


        if (!$dbman->field_exists($table, $fieldeditingtime)) {
            $dbman->add_field($table, $fieldeditingtime);
        }

        upgrade_mod_savepoint(true, 2015121401, 'coursework');
    }

    if($oldversion < 2015121402) {
        $fieldeditingtime = new xmldb_field('authorid', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, null, '0', null);

        $fieldeditingtime->setNotNull(true);

        $table = new xmldb_table('coursework_submissions');


        if (!$dbman->field_exists($table, $fieldeditingtime)) {
            $dbman->add_field($table, $fieldeditingtime);
        }

        upgrade_mod_savepoint(true, 2015121402, 'coursework');
    }

    if($oldversion < 2016110100) {
        $fields = array();
        //Add fields to hold marking deadline enabled
        $upgradefield = new xmldb_field('markingdeadlineenabled', XMLDB_TYPE_INTEGER, '1', true, XMLDB_NOTNULL, null, '0', null);

        $upgradefield->setNotNull(true);

        $table = new xmldb_table('coursework');


        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        upgrade_mod_savepoint(true, 2016110100, 'coursework');
    }

    if($oldversion < 2016110101) {
        $fields = array();
        //Add fields to hold intial marking deadline
        $upgradefield = new xmldb_field('initialmarkingdeadline', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, null, '0', null);

        $upgradefield->setNotNull(true);

        $table = new xmldb_table('coursework');


        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        //Add fields to hold agreed grade marking deadline
        $upgradefield = new xmldb_field('agreedgrademarkingdeadline', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, null, '0', null);

        $upgradefield->setNotNull(true);

        $table = new xmldb_table('coursework');


        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        upgrade_mod_savepoint(true, 2016110101, 'coursework');
    }

    if($oldversion < 2016110102) {
        $fields = array();
        //Add fields to hold intial marking deadline
        $upgradefield = new xmldb_field('markingreminderenabled', XMLDB_TYPE_INTEGER, '1', true, XMLDB_NOTNULL, null, '0', null);

        $upgradefield->setNotNull(true);

        $table = new xmldb_table('coursework');


        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }


        upgrade_mod_savepoint(true, 2016110102, 'coursework');
    }


    if($oldversion < 2016112300) {
        //Add a field to hold extension value if granted
        $fieldextension = new xmldb_field('extension', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, null, '0', null);

        $fieldextension->setNotNull(true);

        $table = new xmldb_table('coursework_reminder');


        if (!$dbman->field_exists($table, $fieldextension)) {
            $dbman->add_field($table, $fieldextension);
        }


        upgrade_mod_savepoint(true, 2016112300, 'coursework');
    }

    if($oldversion < 2016121500) {
        //Add fields to hold personal deadline enabled
        $upgradefield = new xmldb_field('personaldeadlineenabled', XMLDB_TYPE_INTEGER, '1', true, XMLDB_NOTNULL, null, '0', null);

        $upgradefield->setNotNull(true);

        $table = new xmldb_table('coursework');


        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        upgrade_mod_savepoint(true, 2016121500, 'coursework');
    }


    if ($oldversion < 2016121501) {

        // Define table coursework_sample_set_mbrs to be created.
        $table = new xmldb_table('coursework');

        $upgradefield   =   new xmldb_field('submissionnotification', XMLDB_TYPE_TEXT, null, null, null, null, null);

        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        upgrade_mod_savepoint(true, 2016121501, 'coursework');
    }

    

    if ($oldversion < 2016121600) {

        // Define table coursework_person_deadlines to be created.
        $table = new xmldb_table('coursework_person_deadlines');

        // Adding fields to table coursework_person_deadlines.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('allocatableid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('allocatableuser', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('allocatablegroup', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('allocatabletype', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('personal_deadline', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('createdbyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastmodifiedbyid', XMLDB_TYPE_INTEGER, '10', null, false, null, null);

        // Adding keys to table coursework_person_deadlines.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for coursework_person_deadlines.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2016121600, 'coursework');
    }


    if ($oldversion < 2017030801) {

        // Define table coursework_sample_set_mbrs to be created.
        $table = new xmldb_table('coursework');

        $relativeinitial = new xmldb_field('relativeinitialmarkingdeadline', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, null, '0', null);


        if (!$dbman->field_exists($table, $relativeinitial)) {
            $dbman->add_field($table, $relativeinitial);
        }

        $relativeagreed = new xmldb_field('relativeagreedmarkingdeadline', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, null, '0', null);

        if (!$dbman->field_exists($table, $relativeagreed)) {
            $dbman->add_field($table, $relativeagreed);
        }

        upgrade_mod_savepoint(true, 2017030801, 'coursework');
    }

    if ($oldversion < 2017040500) {

        $table = new xmldb_table('coursework');
        $upgradefield = new xmldb_field('autopopulatefeedbackcomment', XMLDB_TYPE_INTEGER, '1', true, XMLDB_NOTNULL, null, '0', null);

        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2017040500, 'coursework');
    }

    if ($oldversion < 2017052301) {

        // update default value of automaticagreementstrategy field from NULL to none

        $allcourseworks = $DB->get_records('coursework', array('automaticagreementstrategy' => 'NULL')); // get all courseworks with automaticagreementstrategy set to NULL
        foreach ($allcourseworks as $coursework) {
            $coursework->automaticagreementstrategy = 'none';
            $DB->update_record('coursework', $coursework);
        }
        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2017052301, 'coursework');
    }

    if ($oldversion < 2017081102) {

        // create events for initialmarkingdeadline and agreedgrademarkingdeadline
        $allcourseworks = $DB->get_records('coursework');
        foreach ($allcourseworks as $coursework) {

            // coursework obejct
            $coursework = coursework::find($coursework);

            if($coursework->marking_deadline_enabled() && $coursework->initialmarkingdeadline){
                //create initialgradingdue event
                coursework_update_events($coursework, 'initialgradingdue');
            }

            if($coursework->marking_deadline_enabled() && $coursework->agreedgrademarkingdeadline){
                //create agreedgradegradingdue event
                coursework_update_events($coursework, 'agreedgradingdue');
            }
        }
        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2017081102, 'coursework');
    }



    if ($oldversion < 2017081103) {

        // Changing the default of field automaticagreementstrategy on table coursework
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('automaticagreementstrategy', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, false, 'none');


        // Launch change of default for field automaticagreementstrategy.
        $dbman->change_field_default($table, $field);


        // again update default value of automaticagreementstrategy field from NULL to none
        $allcourseworks = $DB->get_records('coursework', array('automaticagreementstrategy' => 'NULL')); // get all courseworks with automaticagreementstrategy set to NULL
        foreach ($allcourseworks as $coursework) {
            $coursework->automaticagreementstrategy = 'none';
            $DB->update_record('coursework', $coursework);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2017081103, 'coursework');
    }


    if ($oldversion < 2017091400) {

        $table = new xmldb_table('coursework');
        $upgradefield = new xmldb_field('moderationagreementenabled', XMLDB_TYPE_INTEGER, '1', true, XMLDB_NOTNULL, null, '0', null);

        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2017091400, 'coursework');
    }



    if ($oldversion < 2017100300) {

        // Define table coursework_person_deadlines to be created.
        $table = new xmldb_table('coursework_mod_agreements');

        // Adding fields to table coursework_person_deadlines.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('feedbackid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('moderatorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('agreement', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lasteditedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('modcomment', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('modcommentformat', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        // Adding keys to table coursework_person_deadlines.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for coursework_person_deadlines.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2017100300, 'coursework');
    }

    if ($oldversion < 2017100501) {

        $table = new xmldb_table('coursework');
        $upgradefield = new xmldb_field('draftfeedbackenabled', XMLDB_TYPE_INTEGER, '1', true, XMLDB_NOTNULL, null, '0', null);

        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        $table = new xmldb_table('coursework_feedbacks');
        $upgradefield = new xmldb_field('finalised', XMLDB_TYPE_INTEGER, '1', true, XMLDB_NOTNULL, null, '0', null);

        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        // Update all old feedbacks to be finalised
        $allfeedbacks = $DB->get_records('coursework_feedbacks');
        foreach ($allfeedbacks as $feedback) {
            $feedback->finalised = 1;
            $DB->update_record('coursework_feedbacks', $feedback);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2017100501, 'coursework');
    }


    if ($oldversion < 2018021501) {

        $table = new xmldb_table('coursework');
        $upgradefield = new xmldb_field('processunenrol', XMLDB_TYPE_INTEGER, '1', true, XMLDB_NOTNULL, null, '0', null);

        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        $upgradefield = new xmldb_field('processenrol', XMLDB_TYPE_INTEGER, '1', true, XMLDB_NOTNULL, null, '0', null);

        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->add_field($table, $upgradefield);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2018021501, 'coursework');

    }

    if ($oldversion < 2018042401){

        $table = new xmldb_table('coursework_mod_agreements');
        $upgradefield = new xmldb_field('lasteditedbyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        if ($dbman->field_exists($table, $upgradefield)) {

            $dbman->rename_field($table, $upgradefield, 'lasteditedby', $continue = true, $feedback = true);
        }
        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2018042401, 'coursework');
    }


    if($oldversion < 2019071100) {

        //Add fields for finalstagegrading
        $fieldfinalstagegrading = new xmldb_field('finalstagegrading', XMLDB_TYPE_INTEGER, '1', true, XMLDB_NOTNULL, null, '0', 'numberofmarkers');

        $table = new xmldb_table('coursework');

        if (!$dbman->field_exists($table, $fieldfinalstagegrading)) {
            $dbman->add_field($table, $fieldfinalstagegrading);
        }

        upgrade_mod_savepoint(true, 2019071100, 'coursework');
    }


    if ($oldversion < 2019110700) {

        // Define field plagiarismflagenabled to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('plagiarismflagenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'processenrol');

        // Conditionally launch add field plagiarismflagenabled.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2019110700, 'coursework');
    }

    if ($oldversion < 2019110800) {

        // Define table coursework_plagiarism_flag to be created.
        $table = new xmldb_table('coursework_plagiarism_flags');

        // Adding fields to table coursework_plagiarism_flag.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('comment_format',XMLDB_TYPE_INTEGER,'2',null,null,null,null);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lastmodifiedby', XMLDB_TYPE_INTEGER, '10', null, false, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');


        // Adding keys to table coursework_plagiarism_flag.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for coursework_plagiarism_flag.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2019110800, 'coursework');
    }

    if ($oldversion < 2019121403) {

        // Define table coursework_plagiarism_flag to be created.
        $table = new xmldb_table('coursework');

        $field     =   $table->add_field('renamefiles',XMLDB_TYPE_INTEGER,'1',null,null,null,null);

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }


        $courseworkinstances       =       $DB->get_records('coursework');

        foreach($courseworkinstances   as  $cwk) {
            $courseworkhassubmissions = ($DB->get_records('coursework_submissions', array('courseworkid' => $cwk->id)))
                ? true : false;

            $cwk->renamefiles   = ($cwk->blindmarking == 1 || $courseworkhassubmissions)   ?   1 :  0 ;

            $DB->update_record('coursework',$cwk);
         }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2019121403, 'coursework');
    }

    if ($oldversion < 2020111602) {

        // Define field averagerounding to be added to coursework.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('roundingrule',
            XMLDB_TYPE_CHAR,
            '255',
            null,
            XMLDB_NOTNULL,
            null,
            'mid',
            'automaticagreementstrategy');

        // Conditionally launch add field averagerounding.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2020111602, 'coursework');
    }



    // Always needs to return true.
    return true;
}
