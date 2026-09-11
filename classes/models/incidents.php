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
 * Reads the incident log.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\models;

use assignsubmission_tipnc\log\code;
use core_user\fields;
use assignsubmission_tipnc\log\logger;
use dml_exception;
use stdClass;

/**
 * Reads the incident log: filters, counters and the diagnosis of a common cause.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class incidents {

    /** @var int Incidents shown per page. */
    public const PER_PAGE = 20;

    /**
     * Incidents matching the filters, newest first.
     *
     * @param  array $filters Any of: severity, assignment, userid, since, search.
     * @param  int   $page    Page to read, zero based.
     * @param  int   $perpage Incidents per page.
     * @return stdClass[] The incidents.
     * @throws dml_exception If the query fails.
     */
    public function search(array $filters = [], int $page = 0, int $perpage = self::PER_PAGE): array {
        global $DB;

        [$where, $params] = $this->build_conditions($filters);

        return $DB->get_records_select(
            logger::TABLE,
            $where,
            $params,
            'lastseen DESC, id DESC',
            '*',
            $page * $perpage,
            $perpage
        );
    }

    /**
     * How many incidents match the filters.
     *
     * @param  array $filters Same filters as the search.
     * @return int The number of incidents.
     * @throws dml_exception If the query fails.
     */
    public function count(array $filters = []): int {
        global $DB;

        [$where, $params] = $this->build_conditions($filters);

        return $DB->count_records_select(logger::TABLE, $where, $params);
    }

    /**
     * Counters of the header, which are also filters.
     *
     * @return array With keys: lastday, assignments, users, total, oldest.
     * @throws dml_exception If the query fails.
     */
    public function counters(): array {
        global $DB;

        $since = time() - DAYSECS;

        return [
            'lastday' => $DB->count_records_select(logger::TABLE, 'lastseen >= ? AND severity <> ?',
                [$since, code::SEVERITY_INFO]),
            'assignments' => $DB->count_records_sql(
                'SELECT COUNT(DISTINCT assignment) FROM {' . logger::TABLE . '}
                  WHERE assignment IS NOT NULL AND severity <> ?', [code::SEVERITY_INFO]),
            'users' => $DB->count_records_sql(
                'SELECT COUNT(DISTINCT userid) FROM {' . logger::TABLE . '}
                  WHERE userid IS NOT NULL AND severity <> ?', [code::SEVERITY_INFO]),
            'total' => $DB->count_records(logger::TABLE),
            'oldest' => (int) $DB->get_field_sql('SELECT MIN(firstseen) FROM {' . logger::TABLE . '}'),
        ];
    }

    /**
     * The most repeated failure, when one clearly dominates.
     *
     * Turns a wall of incidents into a single actionable sentence, which is the
     * whole point of the screen.
     *
     * @return stdClass|null With code, occurrences and total; null when there is no clear pattern.
     * @throws dml_exception If the query fails.
     */
    public function common_cause(): ?stdClass {
        global $DB;

        $total = $DB->get_field_sql(
            'SELECT SUM(occurrences) FROM {' . logger::TABLE . '} WHERE severity <> ?',
            [code::SEVERITY_INFO]
        );
        if (empty($total)) {
            return null;
        }

        $rows = $DB->get_records_sql(
            'SELECT errorcode, SUM(occurrences) AS occurrences, COUNT(DISTINCT assignment) AS assignments
               FROM {' . logger::TABLE . '}
              WHERE severity <> ?
           GROUP BY errorcode
           ORDER BY occurrences DESC',
            [code::SEVERITY_INFO],
            0,
            1
        );
        if (!$rows) {
            return null;
        }

        $top = reset($rows);
        // Below half there is no dominant cause worth singling out.
        if ($top->occurrences < $total / 2) {
            return null;
        }

        $top->total = (int) $total;
        $top->occurrences = (int) $top->occurrences;
        $top->assignments = (int) $top->assignments;
        $top->repairable = code::kind($top->errorcode) !== code::KIND_SYSTEM;

        return $top;
    }

    /**
     * Deletes the incidents older than a date.
     *
     * @param  int $before Delete everything last seen before this moment.
     * @return int How many incidents were deleted.
     * @throws dml_exception If the deletion fails.
     */
    public function purge(int $before): int {
        global $DB;

        $deleted = $DB->count_records_select(logger::TABLE, 'lastseen < ?', [$before]);
        $DB->delete_records_select(logger::TABLE, 'lastseen < ?', [$before]);

        return $deleted;
    }

    /**
     * Incidents matching the filters, to be written to a file.
     *
     * @param  array $filters Same filters as the search.
     * @return \moodle_recordset The incidents, read one by one.
     * @throws dml_exception If the query fails.
     */
    public function export(array $filters = []) {
        global $DB;

        [$where, $params] = $this->build_conditions($filters);

        return $DB->get_recordset_select(logger::TABLE, $where, $params, 'lastseen DESC');
    }

    /**
     * One incident.
     *
     * @param  int $id Incident to read.
     * @return stdClass The incident.
     * @throws dml_exception If it does not exist.
     */
    public function get(int $id): stdClass {
        global $DB;

        return $DB->get_record(logger::TABLE, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Entities matching what the user typed in a filter.
     *
     * @param  string $type   One of: course, assign, user.
     * @param  string $query  What the user typed.
     * @param  int    $course Course the search is limited to.
     * @return array The matches, at most 30.
     * @throws dml_exception If the query fails.
     */
    public function options(string $type, string $query, int $course = 0): array {
        global $DB;

        $like = '%' . $DB->sql_like_escape($query) . '%';

        switch ($type) {
            case 'course':
                $where = $DB->sql_like('fullname', ':name', false) . ' OR '
                    . $DB->sql_like('shortname', ':shortname', false);
                $records = $DB->get_records_select('course', "id > 1 AND ($where)",
                    ['name' => $like, 'shortname' => $like], 'fullname ASC', 'id, fullname', 0, 30);
                return array_map(fn($r) => [
                    'value' => (int) $r->id,
                    'label' => format_string($r->fullname),
                ], array_values($records));

            case 'assign':
                $conditions = [$DB->sql_like('a.name', ':name', false)];
                $params = ['name' => $like];
                if ($course > 0) {
                    $conditions[] = 'a.course = :course';
                    $params['course'] = $course;
                }
                $records = $DB->get_records_sql(
                    'SELECT a.id, a.name, c.shortname
                       FROM {assign} a
                       JOIN {course} c ON c.id = a.course
                      WHERE ' . implode(' AND ', $conditions) . '
                   ORDER BY a.name ASC', $params, 0, 30);
                return array_map(fn($r) => [
                    'value' => (int) $r->id,
                    'label' => format_string($r->name) . ' · ' . format_string($r->shortname),
                ], array_values($records));

            case 'user':
                $fullname = $DB->sql_fullname('firstname', 'lastname');
                $where = $DB->sql_like($fullname, ':name', false) . ' OR '
                    . $DB->sql_like('username', ':username', false);
                $records = $DB->get_records_select('user', "deleted = 0 AND id > 1 AND ($where)",
                    ['name' => $like, 'username' => $like], 'lastname ASC',
                    'id, username, ' . implode(', ', fields::get_name_fields()), 0, 30);
                return array_map(fn($r) => [
                    'value' => (int) $r->id,
                    'label' => fullname($r) . ' (' . $r->username . ')',
                ], array_values($records));

            default:
                return [];
        }
    }

    /**
     * Resolves the names an incident refers to, in two queries for the whole page.
     *
     * @param  stdClass[] $incidents The incidents.
     * @return stdClass[] The same incidents with course, assignment and people resolved.
     * @throws dml_exception If the query fails.
     */
    public function decorate(array $incidents): array {
        global $DB;

        $assignmentids = [];
        $userids = [];
        foreach ($incidents as $incident) {
            if (!empty($incident->assignment)) {
                $assignmentids[$incident->assignment] = $incident->assignment;
            }
            foreach (['userid', 'affecteduserid'] as $field) {
                if (!empty($incident->$field)) {
                    $userids[$incident->$field] = $incident->$field;
                }
            }
        }

        $assignments = [];
        if ($assignmentids) {
            [$insql, $params] = $DB->get_in_or_equal($assignmentids);
            $assignments = $DB->get_records_sql(
                "SELECT a.id, a.name, a.course, c.fullname AS coursename, cm.id AS cmid
                   FROM {assign} a
                   JOIN {course} c ON c.id = a.course
              LEFT JOIN {modules} m ON m.name = 'assign'
              LEFT JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = m.id
                  WHERE a.id $insql",
                $params
            );
        }

        $users = [];
        if ($userids) {
            [$insql, $params] = $DB->get_in_or_equal($userids);
            $users = $DB->get_records_select('user', "id $insql", $params, '',
                'id, username, ' . implode(', ', fields::get_name_fields()));
        }

        foreach ($incidents as $incident) {
            $incident->assignmentinfo = $assignments[$incident->assignment] ?? null;
            $incident->userinfo = $users[$incident->userid] ?? null;
            $incident->affecteduserinfo = $users[$incident->affecteduserid] ?? null;
        }

        return $incidents;
    }

    /**
     * Every call of one action, so a failure can be read in its sequence.
     *
     * @param  string $traceid Trace identifier.
     * @return stdClass[] The entries, oldest first.
     * @throws dml_exception If the query fails.
     */
    public function trace(string $traceid): array {
        global $DB;

        if ($traceid === '') {
            return [];
        }

        return $DB->get_records(logger::TABLE, ['traceid' => $traceid], 'id ASC');
    }

    /**
     * Turns the filters into a where clause.
     *
     * @param  array $filters The filters.
     * @return array The clause and its parameters.
     */
    private function build_conditions(array $filters): array {
        global $DB;

        $conditions = ['1 = 1'];
        $params = [];

        if (!empty($filters['severity'])) {
            $conditions[] = 'severity = ?';
            $params[] = $filters['severity'];
        } else {
            // Successes are history: they do not clutter the list unless asked for.
            $conditions[] = 'severity <> ?';
            $params[] = code::SEVERITY_INFO;
        }

        if (!empty($filters['assignment'])) {
            $conditions[] = 'assignment = ?';
            $params[] = (int) $filters['assignment'];
        }

        if (!empty($filters['userid'])) {
            $conditions[] = '(userid = ? OR affecteduserid = ?)';
            $params[] = (int) $filters['userid'];
            $params[] = (int) $filters['userid'];
        }

        if (!empty($filters['since'])) {
            $conditions[] = 'lastseen >= ?';
            $params[] = (int) $filters['since'];
        }

        if (!empty($filters['search'])) {
            $like = $DB->sql_like('requesturl', '?', false) . ' OR ' . $DB->sql_like('documentpath', '?', false)
                . ' OR ' . $DB->sql_like('method', '?', false);
            $conditions[] = "($like)";
            $term = '%' . $DB->sql_like_escape($filters['search']) . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        return [implode(' AND ', $conditions), $params];
    }
}
