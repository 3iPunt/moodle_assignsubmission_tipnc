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
 * Detail of one incident.
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
 * Detail of one incident: what it means, its context, the call and the full trace.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class incident_detail implements renderable, templatable {
    /**
     * Constructor.
     *
     * @param stdClass   $incident    The incident, with its names resolved.
     * @param stdClass[] $trace       Every call of the same action.
     * @param array      $environment Versions and public address of NextCloud.
     */
    public function __construct(
        /** @var stdClass The incident, with its names resolved. */
        private readonly stdClass $incident,
        /** @var array Every call of the same action. */
        private readonly array $trace = [],
        /** @var array Versions and public address of NextCloud. */
        private readonly array $environment = []
    ) {
    }

    /**
     * Export for template.
     *
     * @param  renderer_base $output The renderer.
     * @return array The data of the detail.
     * @throws coding_exception If a language string is missing.
     */
    public function export_for_template(renderer_base $output): array {
        $incident = $this->incident;

        return [
            'id' => $incident->id,
            'severity' => $incident->severity,
            'severitylabel' => get_string('severity_' . $incident->severity, 'assignsubmission_tipnc'),
            'iserror' => $incident->severity === code::SEVERITY_ERROR,
            'iswarning' => $incident->severity === code::SEVERITY_WARNING,
            'isinfo' => $incident->severity === code::SEVERITY_INFO,
            'operation' => $incident->operation !== ''
                ? get_string('operation_' . $incident->operation, 'assignsubmission_tipnc')
                : $incident->method,
            'meaning' => code::describe($incident->errorcode),
            'errorcode' => $incident->errorcode,
            'when' => userdate($incident->lastseen),
            'repeated' => $incident->occurrences > 1,
            'occurrences' => $incident->occurrences,
            'firstseen' => userdate($incident->firstseen),
            'context' => $this->export_context(),
            'call' => $this->export_call(),
            'trace' => $this->export_trace(),
            'hastrace' => count($this->trace) > 1,
            'diagnosis' => $this->build_diagnosis(),
        ];
    }

    /**
     * Where the incident happened, all of it navigable.
     *
     * @return array The rows of the context block.
     * @throws coding_exception If a language string is missing.
     */
    private function export_context(): array {
        $incident = $this->incident;
        $rows = [];

        if (!empty($incident->assignmentinfo)) {
            $rows[] = [
                'label' => get_string('detail_course', 'assignsubmission_tipnc'),
                'value' => format_string($incident->assignmentinfo->coursename),
                'url' => (new moodle_url(
                    '/course/view.php',
                    ['id' => $incident->assignmentinfo->course]
                ))->out(false),
            ];
            $rows[] = [
                'label' => get_string('detail_assignment', 'assignsubmission_tipnc'),
                'value' => format_string($incident->assignmentinfo->name),
                'url' => empty($incident->assignmentinfo->cmid) ? '' :
                    (new moodle_url(
                        '/mod/assign/view.php',
                        ['id' => $incident->assignmentinfo->cmid]
                    ))->out(false),
            ];
        }

        if (!empty($incident->userinfo)) {
            $rows[] = [
                'label' => get_string('detail_who', 'assignsubmission_tipnc'),
                'value' => fullname($incident->userinfo),
                'url' => (new moodle_url('/user/profile.php', ['id' => $incident->userinfo->id]))->out(false),
            ];
        }

        if (!empty($incident->affecteduserinfo)) {
            $rows[] = [
                'label' => get_string('detail_affected', 'assignsubmission_tipnc'),
                'value' => fullname($incident->affecteduserinfo),
                'url' => (new moodle_url(
                    '/user/profile.php',
                    ['id' => $incident->affecteduserinfo->id]
                ))->out(false),
            ];
        }

        if (!empty($incident->documentpath)) {
            $rows[] = [
                'label' => get_string('detail_document', 'assignsubmission_tipnc'),
                'value' => $incident->documentpath,
                // A link to the folder holding it in NextCloud: the first thing to
                // check is whether the document is there.
                'url' => $this->nextcloud_folder_url($incident->documentpath),
                'external' => true,
            ];
        }

        return $rows;
    }

    /**
     * The call itself, which is the half of the diagnosis that matters.
     *
     * @return array The data of the call.
     * @throws coding_exception If a language string is missing.
     */
    private function export_call(): array {
        $incident = $this->incident;

        return [
            'method' => $incident->httpmethod ?: '—',
            'url' => $incident->requesturl ?: '',
            'httpcode' => $incident->httpcode ?: '—',
            'httpok' => !empty($incident->httpcode) && $incident->httpcode < 400,
            'duration' => $incident->duration === null ? '' : $incident->duration . ' ms',
            'body' => $incident->responsebody ?: '',
            'hasbody' => !empty($incident->responsebody),
        ];
    }

    /**
     * Every call of the same action, in order.
     *
     * @return array The steps of the trace.
     * @throws coding_exception If a language string is missing.
     */
    private function export_trace(): array {
        $steps = [];

        foreach ($this->trace as $entry) {
            $steps[] = [
                'current' => (int) $entry->id === (int) $this->incident->id,
                'severity' => $entry->severity,
                'operation' => $entry->operation !== ''
                    ? get_string('operation_' . $entry->operation, 'assignsubmission_tipnc')
                    : $entry->method,
                'method' => $entry->method,
                'httpcode' => $entry->httpcode ?: '—',
                'httpok' => !empty($entry->httpcode) && $entry->httpcode < 400,
                'when' => userdate($entry->timecreated, get_string('strftimetime')),
            ];
        }

        return $steps;
    }

    /**
     * URL of the folder holding a document in NextCloud.
     *
     * @param  string $path Path of the document.
     * @return string The URL, empty when the public address is not configured.
     */
    private function nextcloud_folder_url(string $path): string {
        $base = rtrim((string) ($this->environment['ncurl'] ?? ''), '/');
        if ($base === '') {
            return '';
        }

        $folder = trim(dirname($path), '/.');

        return $base . '/index.php/apps/files/?dir=/' . rawurlencode($folder);
    }

    /**
     * The diagnosis, written so it can be pasted straight into an assistant or a
     * ticket: says what failed, with what data, and what is being asked for.
     *
     * Never carries credentials: the log masks them before storing anything.
     *
     * @return string The diagnosis.
     * @throws coding_exception If a language string is missing.
     */
    private function build_diagnosis(): string {
        $incident = $this->incident;
        $operation = $incident->operation !== ''
            ? get_string('operation_' . $incident->operation, 'assignsubmission_tipnc')
            : $incident->method;

        $lines = [
            get_string('diagnosis_intro', 'assignsubmission_tipnc'),
            '',
            '## ' . get_string('diagnosis_what', 'assignsubmission_tipnc'),
            code::describe($incident->errorcode) . ' (' . $incident->errorcode . ')',
            get_string('detail_whatitmeans', 'assignsubmission_tipnc') . ': ' . $operation,
            get_string('log_col_severity', 'assignsubmission_tipnc') . ': ' . $incident->severity,
        ];

        if ($incident->occurrences > 1) {
            $lines[] = get_string('detail_occurrences', 'assignsubmission_tipnc') . ': '
                . $incident->occurrences . ' (' . userdate($incident->firstseen) . ' → '
                . userdate($incident->lastseen) . ')';
        } else {
            $lines[] = get_string('detail_when', 'assignsubmission_tipnc') . ': ' . userdate($incident->lastseen);
        }

        if (!empty($incident->requesturl)) {
            $lines[] = '';
            $lines[] = '## ' . get_string('detail_call', 'assignsubmission_tipnc');
            $lines[] = ($incident->httpmethod ?: '?') . ' ' . $incident->requesturl;
            $lines[] = get_string('log_col_answer', 'assignsubmission_tipnc') . ': '
                . ($incident->httpcode ?: '—')
                . ($incident->duration !== null ? ' (' . $incident->duration . ' ms)' : '');
        }

        if (!empty($incident->responsebody)) {
            $lines[] = $incident->responsebody;
        }

        $lines[] = '';
        $lines[] = '## ' . get_string('detail_context', 'assignsubmission_tipnc');
        foreach ($this->export_context() as $row) {
            $lines[] = $row['label'] . ': ' . $row['value'];
        }

        $lines[] = '';
        $lines[] = '## ' . get_string('diagnosis_environment', 'assignsubmission_tipnc');
        $lines[] = 'Moodle: ' . ($this->environment['moodle'] ?? '?');
        $lines[] = 'assignsubmission_tipnc: ' . ($this->environment['plugin'] ?? '?');
        $lines[] = 'NextCloud: ' . ($this->environment['ncurl'] ?? '?');

        $lines[] = '';
        $lines[] = '## ' . get_string('diagnosis_ask', 'assignsubmission_tipnc');
        $lines[] = get_string('diagnosis_question', 'assignsubmission_tipnc');

        return implode("\n", $lines);
    }
}
