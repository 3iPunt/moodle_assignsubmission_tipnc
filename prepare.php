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
 * Prepares the brief of an assignment, at the request of whoever marks it.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_tipnc\api\nextcloud;
use assignsubmission_tipnc\log\code;
use assignsubmission_tipnc\tipnc_enun;

require_once(__DIR__ . '/../../../../config.php');

$cmid = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/assign:grade', $context);

$back = new moodle_url('/mod/assign/view.php', ['id' => $cmid]);

// Que ya exista no es un error: dos personas pueden pulsar a la vez, o el cron
// puede haberse adelantado.
if (tipnc_enun::get((int) $cm->instance)) {
    redirect($back, get_string('prepare_already', 'assignsubmission_tipnc'),
        null, \core\output\notification::NOTIFY_INFO);
}

$response = (new nextcloud((int) $cm->instance))->teacher_create();

if (!$response->success) {
    redirect($back, get_string('prepare_failed', 'assignsubmission_tipnc'),
        null, \core\output\notification::NOTIFY_ERROR);
}

// El enunciado está hecho y la tarea funciona, pero quien lo creó no puede
// escribirlo: decir qué cuenta falta ahorra abrir el registro para averiguarlo.
if ((string) $response->error->code === code::SHARE_NO_ACCOUNT) {
    redirect($back, get_string('prepare_noaccount', 'assignsubmission_tipnc', $USER->username),
        null, \core\output\notification::NOTIFY_WARNING);
}

redirect($back, get_string('prepare_done', 'assignsubmission_tipnc'),
    null, \core\output\notification::NOTIFY_SUCCESS);
