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
 * What this plugin knows about people, and what it does when asked to forget.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\privacy;

use assignsubmission_tipnc\models\cleanup;
use assignsubmission_tipnc\models\documents;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use dml_exception;
use mod_assign\privacy\assign_plugin_request_data;
use mod_assign\privacy\useridlist;

/**
 * What this plugin knows about people, and what it does when asked to forget.
 *
 * Two things make this plugin different from the submission plugins that keep
 * everything inside Moodle. The documents live in **another system**, and the
 * plugin tells that system who each person is —it shares each document with
 * their account name— so a deletion here has to reach over there too. And the
 * incident log keeps who did what and on which document, which is personal data
 * even though nobody thinks of a log that way.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \mod_assign\privacy\assignsubmission_provider,
        \mod_assign\privacy\assignsubmission_user_provider {

    /**
     * Everything this plugin stores or sends about a person.
     *
     * @param  collection $collection Where to declare it.
     * @return collection The collection with this plugin's data in it.
     */
    public static function get_metadata(collection $collection): collection {
        $document = [
            'assignment' => 'privacy:metadata:assignment',
            'submission' => 'privacy:metadata:submission',
            'ncid' => 'privacy:metadata:ncid',
            'path' => 'privacy:metadata:path',
        ];

        $collection->add_database_table('assignsubmission_tipnc', $document,
            'privacy:metadata:tipnc');

        $collection->add_database_table('assignsubmission_tipnc_open', $document,
            'privacy:metadata:tipnc_open');

        $collection->add_database_table('assignsubmission_tipnc_enun', [
            'assignment' => 'privacy:metadata:assignment',
            'userid' => 'privacy:metadata:enun_userid',
            'ncid' => 'privacy:metadata:ncid',
            'path' => 'privacy:metadata:path',
        ], 'privacy:metadata:tipnc_enun');

        $collection->add_database_table('assignsubmission_tipnc_log', [
            'userid' => 'privacy:metadata:log_userid',
            'affecteduserid' => 'privacy:metadata:log_affecteduserid',
            'documentpath' => 'privacy:metadata:log_documentpath',
            'requesturl' => 'privacy:metadata:log_requesturl',
            'responsebody' => 'privacy:metadata:log_responsebody',
        ], 'privacy:metadata:tipnc_log');

        // Lo que de verdad mira quien revisa la privacidad de un plugin: aquí
        // salen datos personales del sitio y van a otro sistema.
        $collection->add_external_location_link('nextcloud', [
            'username' => 'privacy:metadata:nextcloud:username',
            'document' => 'privacy:metadata:nextcloud:document',
        ], 'privacy:metadata:nextcloud');

        return $collection;
    }

    /**
     * Contexts where somebody appears without having handed anything in.
     *
     * Whoever prepared the brief and whoever shows up in the incident log are in
     * this plugin's tables with no submission of their own, so mod_assign's own
     * queries —which walk submissions— would never find them.
     *
     * @param  int         $userid      Whose contexts.
     * @param  contextlist $contextlist Where to add them.
     * @return void
     */
    public static function get_context_for_userid_within_submission(int $userid, contextlist $contextlist) {
        $params = ['modulename' => 'assign', 'contextlevel' => CONTEXT_MODULE, 'userid' => $userid];

        $sql = "SELECT ctx.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {assign} a ON a.id = cm.instance
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                  JOIN {assignsubmission_tipnc_enun} e ON e.assignment = a.id
                 WHERE e.userid = :userid";

        $contextlist->add_from_sql($sql, $params);

        $params = ['modulename' => 'assign', 'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid, 'affected' => $userid];

        $sql = "SELECT ctx.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {assign} a ON a.id = cm.instance
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                  JOIN {assignsubmission_tipnc_log} l ON l.assignment = a.id
                 WHERE l.userid = :userid OR l.affecteduserid = :affected";

        $contextlist->add_from_sql($sql, $params);
    }

    /**
     * Students of an assignment, which mod_assign already works out.
     *
     * @param  useridlist $useridlist The list, left as it is.
     * @return void
     */
    public static function get_student_user_ids(useridlist $useridlist) {
        // Los saca mod_assign de assign_submission; aquí no hay nadie más.
    }

    /**
     * People in this assignment who are in this plugin's tables.
     *
     * @param  userlist $userlist Where to add them.
     * @return void
     */
    public static function get_userids_from_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof context_module) {
            return;
        }

        $params = ['instanceid' => $context->instanceid];

        $userlist->add_from_sql('userid',
            "SELECT e.userid
               FROM {course_modules} cm
               JOIN {assignsubmission_tipnc_enun} e ON e.assignment = cm.instance
              WHERE cm.id = :instanceid", $params);

        $userlist->add_from_sql('userid',
            "SELECT l.userid
               FROM {course_modules} cm
               JOIN {assignsubmission_tipnc_log} l ON l.assignment = cm.instance
              WHERE cm.id = :instanceid AND l.userid IS NOT NULL", $params);

        $userlist->add_from_sql('affecteduserid',
            "SELECT l.affecteduserid
               FROM {course_modules} cm
               JOIN {assignsubmission_tipnc_log} l ON l.assignment = cm.instance
              WHERE cm.id = :instanceid AND l.affecteduserid IS NOT NULL", $params);
    }

    /**
     * Hands over what is known about one person's document.
     *
     * The document itself is not exported: it lives in the other system and can
     * weigh megabytes. What goes out is where it is and how to reach it, which
     * is what somebody asking «what do you have about me» actually needs.
     *
     * @param  assign_plugin_request_data $exportdata What to export and where.
     * @return void
     * @throws dml_exception If the tables cannot be read.
     */
    public static function export_submission_user_data(assign_plugin_request_data $exportdata) {
        global $DB;

        // Igual que en los plugins del core: la entrega del alumnado no se
        // exporta dentro de los datos de quien la corrige.
        if ($exportdata->get_user() != null) {
            return;
        }

        $submission = $exportdata->get_pluginobject();
        $context = $exportdata->get_context();

        foreach (['assignsubmission_tipnc' => 'submitted', 'assignsubmission_tipnc_open' => 'draft'] as $table => $kind) {
            $row = $DB->get_record($table, ['submission' => $submission->id], 'ncid, path');

            if (!$row) {
                continue;
            }

            $data = (object) [
                'kind' => get_string('privacy:export:' . $kind, 'assignsubmission_tipnc'),
                'filename' => basename((string) $row->path),
                'path' => $row->path,
                'url' => (new documents())->view_url((int) $row->ncid),
            ];

            writer::with_context($context)->export_data(
                array_merge($exportdata->get_subcontext(),
                    [get_string('pluginname', 'assignsubmission_tipnc'), $data->kind]),
                $data
            );
        }
    }

    /**
     * Forgets everything about an assignment, documents included.
     *
     * @param  assign_plugin_request_data $requestdata Which assignment.
     * @return void
     * @throws dml_exception If the tables cannot be read.
     */
    public static function delete_submission_for_context(assign_plugin_request_data $requestdata) {
        global $DB;

        $assignment = (int) $requestdata->get_assignid();

        cleanup::erase(self::paths_of($assignment), $assignment);

        $DB->delete_records('assignsubmission_tipnc', ['assignment' => $assignment]);
        $DB->delete_records('assignsubmission_tipnc_open', ['assignment' => $assignment]);
        $DB->delete_records('assignsubmission_tipnc_enun', ['assignment' => $assignment]);
        $DB->delete_records('assignsubmission_tipnc_log', ['assignment' => $assignment]);
    }

    /**
     * Forgets one person in one assignment.
     *
     * @param  assign_plugin_request_data $deletedata Who and where.
     * @return void
     * @throws dml_exception If the tables cannot be read.
     */
    public static function delete_submission_for_userid(assign_plugin_request_data $deletedata) {
        self::forget([(int) $deletedata->get_pluginobject()->id],
            [(int) $deletedata->get_user()->id],
            (int) $deletedata->get_assignid());
    }

    /**
     * Forgets several people in one assignment.
     *
     * @param  assign_plugin_request_data $deletedata Who and where.
     * @return void
     * @throws dml_exception If the tables cannot be read.
     */
    public static function delete_submissions(assign_plugin_request_data $deletedata) {
        self::forget($deletedata->get_submissionids(), $deletedata->get_userids(),
            (int) $deletedata->get_assignid());
    }

    /**
     * Takes away the documents and the traces of some people in an assignment.
     *
     * @param  array $submissionids Submissions to forget.
     * @param  array $userids       People to forget, for the brief and the log.
     * @param  int   $assignment    Assignment they belong to.
     * @return void
     * @throws dml_exception If the tables cannot be read.
     */
    private static function forget(array $submissionids, array $userids, int $assignment): void {
        global $DB;

        if ($submissionids) {
            [$insql, $params] = $DB->get_in_or_equal($submissionids, SQL_PARAMS_NAMED);
            $params['assignment'] = $assignment;

            $paths = array_merge(
                (array) $DB->get_fieldset_select('assignsubmission_tipnc', 'path',
                    "assignment = :assignment AND submission $insql AND path IS NOT NULL", $params),
                (array) $DB->get_fieldset_select('assignsubmission_tipnc_open', 'path',
                    "assignment = :assignment AND submission $insql AND path IS NOT NULL", $params)
            );

            cleanup::erase($paths, $assignment);

            $DB->delete_records_select('assignsubmission_tipnc',
                "assignment = :assignment AND submission $insql", $params);
            $DB->delete_records_select('assignsubmission_tipnc_open',
                "assignment = :assignment AND submission $insql", $params);
        }

        if (!$userids) {
            return;
        }

        // El enunciado no se borra —es de la tarea, no de una persona—, pero deja
        // de decir quién lo creó, que es el dato personal que hay en él.
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['assignment'] = $assignment;

        $DB->set_field_select('assignsubmission_tipnc_enun', 'userid', 0,
            "assignment = :assignment AND userid $insql", $params);

        $DB->delete_records_select('assignsubmission_tipnc_log',
            "assignment = :assignment AND userid $insql", $params);
        $DB->delete_records_select('assignsubmission_tipnc_log',
            "assignment = :assignment AND affecteduserid $insql", $params);
    }

    /**
     * Where every document of an assignment is.
     *
     * @param  int $assignment Assignment instance.
     * @return array Paths in NextCloud.
     * @throws dml_exception If the tables cannot be read.
     */
    private static function paths_of(int $assignment): array {
        global $DB;

        $sql = "SELECT path FROM {assignsubmission_tipnc} WHERE assignment = :a1 AND path IS NOT NULL
                UNION
                SELECT path FROM {assignsubmission_tipnc_open} WHERE assignment = :a2 AND path IS NOT NULL
                UNION
                SELECT path FROM {assignsubmission_tipnc_enun} WHERE assignment = :a3 AND path IS NOT NULL";

        return (array) $DB->get_fieldset_sql($sql,
            ['a1' => $assignment, 'a2' => $assignment, 'a3' => $assignment]);
    }
}
