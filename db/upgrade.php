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
 * Database upgrade steps for the NextCloud submission plugin.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs the upgrade steps.
 *
 * @param  int $oldversion The currently installed version.
 * @return bool True on success.
 * @throws ddl_exception If a table or field cannot be created.
 * @throws dml_exception If the data migration fails.
 * @throws downgrade_exception If the installed version is newer.
 * @throws moodle_exception If the upgrade savepoint cannot be stored.
 * @throws upgrade_exception If a step fails.
 */
function xmldb_assignsubmission_tipnc_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090800) {

        $table = new xmldb_table('assignsubmission_tipnc_log');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('severity', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'error');
        $table->add_field('traceid', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('operation', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('method', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('errorcode', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('assignment', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('submission', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('affecteduserid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('httpmethod', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('requesturl', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
        $table->add_field('httpcode', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('documentpath', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('responsebody', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('occurrences', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('firstseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('lastseen', XMLDB_INDEX_NOTUNIQUE, ['lastseen']);
        $table->add_index('severity-lastseen', XMLDB_INDEX_NOTUNIQUE, ['severity', 'lastseen']);
        $table->add_index('assignment', XMLDB_INDEX_NOTUNIQUE, ['assignment']);
        $table->add_index('traceid', XMLDB_INDEX_NOTUNIQUE, ['traceid']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('dedupe', XMLDB_INDEX_NOTUNIQUE, ['errorcode', 'method', 'assignment', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026090800, 'assignsubmission', 'tipnc');
    }

    if ($oldversion < 2026090801) {

        // The old log is discarded rather than migrated: it only held the
        // method, the code and a message that was almost always an empty
        // response, with no URL, no HTTP status and no document. Useless.
        $old = new xmldb_table('assignsubmission_tipnc_error');
        if ($dbman->table_exists($old)) {
            $dbman->drop_table($old);
        }

        upgrade_plugin_savepoint(true, 2026090801, 'assignsubmission', 'tipnc');
    }

    if ($oldversion < 2026090805) {

        // Where each document really is. It is stored rather than rebuilt
        // because the path carries the course and assignment names, and
        // renaming either of them would make the documents untraceable.
        foreach ([
            'assignsubmission_tipnc',
            'assignsubmission_tipnc_open',
            'assignsubmission_tipnc_enun',
        ] as $tablename) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field('path', XMLDB_TYPE_CHAR, '1333', null, null, null, null, 'ncid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Existing documents sit loose in the base folder. Moving them talks
        // to NextCloud, and an upgrade that waits on the network is an
        // upgrade that can hang: cron does it.
        \core\task\manager::queue_adhoc_task(
            new \assignsubmission_tipnc\task\migrate_documents(), true);

        upgrade_plugin_savepoint(true, 2026090805, 'assignsubmission', 'tipnc');
    }

    if ($oldversion < 2026090806) {

        // The editing session key. It is stored because it has to do two
        // things at once: change when the document changes —or the editor
        // keeps serving the copy it had— and stay put while it is open, or
        // there is no way to ask it to save what it holds.
        foreach ([
            'assignsubmission_tipnc',
            'assignsubmission_tipnc_open',
            'assignsubmission_tipnc_enun',
        ] as $tablename) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field('editorkey', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'path');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026090806, 'assignsubmission', 'tipnc');
    }

    if ($oldversion < 2026090807) {

        // Which state of the document the key was minted for. Without this
        // there is no telling whether the editor session still serves what is
        // there: the key is renewed when the content changed, and only then.
        foreach ([
            'assignsubmission_tipnc',
            'assignsubmission_tipnc_open',
            'assignsubmission_tipnc_enun',
        ] as $tablename) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field('editorversion', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'editorkey');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026090807, 'assignsubmission', 'tipnc');
    }

    return true;
}
