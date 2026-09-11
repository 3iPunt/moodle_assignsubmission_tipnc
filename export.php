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
 * Downloads the incident log as a CSV file.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

global $CFG;

require_once($CFG->libdir . '/csvlib.class.php');

use assignsubmission_tipnc\log\code;
use assignsubmission_tipnc\models\incidents;

require_login();
require_capability('assignsubmission/tipnc:view_errors', context_system::instance());
require_sesskey();

$filters = array_filter([
    'severity' => optional_param('severity', '', PARAM_ALPHA),
    'assignment' => optional_param('assign', 0, PARAM_INT),
    'userid' => optional_param('user', 0, PARAM_INT),
]);

$range = optional_param('range', 0, PARAM_INT);
if ($range > 0) {
    $filters['since'] = time() - ($range * DAYSECS);
}

// Headers in snake_case and with no spaces: the file opens in any
// spreadsheet and can be read back by a script.
$columns = [
    'id', 'severity', 'error_code', 'error_meaning', 'operation', 'method',
    'course', 'assignment', 'user', 'affected_user', 'http_method', 'http_code',
    'duration_ms', 'document', 'occurrences', 'first_seen', 'last_seen', 'request_url',
];

$export = new csv_export_writer();
$export->set_filename('tipnc-incidents-' . userdate(time(), '%Y%m%d-%H%M'));
$export->add_data($columns);

$model = new incidents();
$records = $model->export($filters);

foreach ($records as $incident) {
    $decorated = $model->decorate([$incident->id => $incident]);
    $incident = reset($decorated);

    $export->add_data([
        $incident->id,
        $incident->severity,
        $incident->errorcode,
        code::describe($incident->errorcode),
        $incident->operation !== ''
            ? get_string('operation_' . $incident->operation, 'assignsubmission_tipnc')
            : '',
        $incident->method,
        empty($incident->assignmentinfo) ? '' : $incident->assignmentinfo->coursename,
        empty($incident->assignmentinfo) ? '' : $incident->assignmentinfo->name,
        empty($incident->userinfo) ? '' : fullname($incident->userinfo),
        empty($incident->affecteduserinfo) ? '' : fullname($incident->affecteduserinfo),
        $incident->httpmethod ?? '',
        $incident->httpcode ?? '',
        $incident->duration ?? '',
        $incident->documentpath ?? '',
        $incident->occurrences,
        userdate($incident->firstseen, '%Y-%m-%d %H:%M:%S'),
        userdate($incident->lastseen, '%Y-%m-%d %H:%M:%S'),
        // The URL comes masked from the log itself: it never carries credentials.
        $incident->requesturl ?? '',
    ]);
}

$records->close();

$export->download_file();
