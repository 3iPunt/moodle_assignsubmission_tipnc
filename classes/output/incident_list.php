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
 * Listing of incidents.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\output;

use assignsubmission_tipnc\log\code;
use coding_exception;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Listing of incidents: the part of the screen that the filters repaint.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class incident_list implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param stdClass[] $incidents    Incidents of the page, already resolved.
     * @param int        $total        Incidents matching the filters.
     * @param int        $page         Page being shown, zero based.
     * @param int        $perpage      Incidents per page.
     * @param bool       $connectionok Whether NextCloud is answering right now.
     * @param bool       $filtered     Whether any filter is applied.
     */
    public function __construct(
        private readonly array $incidents,
        private readonly int $total,
        private readonly int $page,
        private readonly int $perpage,
        private readonly bool $connectionok = true,
        private readonly bool $filtered = false
    ) {
    }

    /**
     * Export for template.
     *
     * @param  renderer_base $output The renderer.
     * @return array The data of the listing.
     * @throws coding_exception If a language string is missing.
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];
        foreach ($this->incidents as $incident) {
            $rows[] = $this->export_incident($incident);
        }

        $from = $this->total === 0 ? 0 : ($this->page * $this->perpage) + 1;
        $to = min(($this->page + 1) * $this->perpage, $this->total);

        return [
            'incidents' => $rows,
            'hasincidents' => !empty($rows),
            'isempty' => empty($rows),
            'isfiltered' => $this->filtered,
            'emptyingood' => empty($rows) && !$this->filtered && $this->connectionok,
            'summary' => get_string('log_showing', 'assignsubmission_tipnc',
                (object) ['from' => $from, 'to' => $to, 'total' => $this->total]),
            'pagination' => $this->export_pagination(),
        ];
    }

    /**
     * One incident, ready to be printed.
     *
     * @param  stdClass $incident The incident.
     * @return array The data of the row.
     * @throws coding_exception If a language string is missing.
     */
    private function export_incident(stdClass $incident): array {
        $operation = $incident->operation !== ''
            ? get_string('operation_' . $incident->operation, 'assignsubmission_tipnc')
            : $incident->method;

        $row = [
            'id' => $incident->id,
            'severity' => $incident->severity,
            'severitylabel' => get_string('severity_' . $incident->severity, 'assignsubmission_tipnc'),
            'iserror' => $incident->severity === code::SEVERITY_ERROR,
            'iswarning' => $incident->severity === code::SEVERITY_WARNING,
            'isinfo' => $incident->severity === code::SEVERITY_INFO,
            'when' => $this->relative_time($incident->lastseen),
            'whenexact' => userdate($incident->lastseen),
            'repeated' => $incident->occurrences > 1,
            'occurrences' => $incident->occurrences,
            'since' => $incident->occurrences > 1
                ? userdate($incident->firstseen, get_string('log_dateformat', 'assignsubmission_tipnc'))
                : '',
            'operation' => $operation,
            'method' => $incident->method,
            'errorcode' => $incident->errorcode,
            'errorlabel' => code::describe($incident->errorcode),
            'httpcode' => $incident->httpcode ?: '—',
            'hashttpcode' => !empty($incident->httpcode),
            'httpok' => !empty($incident->httpcode) && $incident->httpcode < 400,
        ];

        if (!empty($incident->assignmentinfo)) {
            $row['assignmentname'] = format_string($incident->assignmentinfo->name);
            $row['coursename'] = format_string($incident->assignmentinfo->coursename);
            $row['assignmenturl'] = empty($incident->assignmentinfo->cmid) ? '' :
                (new moodle_url('/mod/assign/view.php', ['id' => $incident->assignmentinfo->cmid]))->out(false);
            $row['courseurl'] = (new moodle_url('/course/view.php',
                ['id' => $incident->assignmentinfo->course]))->out(false);
        }

        if (!empty($incident->userinfo)) {
            $row['username'] = fullname($incident->userinfo);
            $row['userurl'] = (new moodle_url('/user/profile.php',
                ['id' => $incident->userinfo->id]))->out(false);
        }

        if (!empty($incident->affecteduserinfo)) {
            $row['affectedname'] = fullname($incident->affecteduserinfo);
        }

        return $row;
    }

    /**
     * Pages of the listing.
     *
     * @return array The pages, empty when everything fits in one.
     */
    private function export_pagination(): array {
        $pages = (int) ceil($this->total / $this->perpage);
        if ($pages < 2) {
            return [];
        }

        $links = [];
        for ($i = 0; $i < $pages; $i++) {
            $links[] = [
                'number' => $i + 1,
                'page' => $i,
                'current' => $i === $this->page,
            ];
        }

        return $links;
    }

    /**
     * How long ago something happened, in plain words.
     *
     * @param  int $timestamp When it happened.
     * @return string The elapsed time.
     * @throws coding_exception If a language string is missing.
     */
    private function relative_time(int $timestamp): string {
        $elapsed = time() - $timestamp;

        if ($elapsed < MINSECS) {
            return get_string('log_justnow', 'assignsubmission_tipnc');
        }

        // Abreviado y de una sola unidad: «hace 55'», no «hace 55 minutos 39 segundos».
        if ($elapsed < HOURSECS) {
            $amount = get_string('log_minutes', 'assignsubmission_tipnc', (int) floor($elapsed / MINSECS));
        } else if ($elapsed < DAYSECS) {
            $amount = get_string('log_hours', 'assignsubmission_tipnc', (int) floor($elapsed / HOURSECS));
        } else {
            return userdate($timestamp, get_string('strftimedatetimeshort'));
        }

        return get_string('log_ago', 'assignsubmission_tipnc', $amount);
    }
}
