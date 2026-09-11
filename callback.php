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
 * Receives what the editor says about a document.
 *
 * This is the other half of the editor being embedded by Moodle: when the editing
 * session ends —or when a save is forced— the Document Server calls here with the
 * address of the result, and Moodle stores it in NextCloud.
 *
 * Two signatures have to agree: ours, in the address, which names the document;
 * and the editor's, in the body, which proves the message comes from it.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../../../config.php');

use assignsubmission_tipnc\api\nextcloud;
use assignsubmission_tipnc\editor\callback;
use assignsubmission_tipnc\editor\token;

$signature = required_param('token', PARAM_RAW);
$payload = token::verify($signature);

if ($payload === null || ($payload['use'] ?? '') !== 'callback') {
    callback::answer(callback::ERROR_FORBIDDEN);
}

$instance = (int) ($payload['instance'] ?? 0);
$path = (string) ($payload['path'] ?? '');

if ($instance === 0 || $path === '') {
    callback::answer(callback::ERROR_FORBIDDEN);
}

$body = callback::body();
if ($body === null) {
    callback::answer(callback::ERROR_FORBIDDEN);
}

// El editor solo pide guardar en dos estados; los demás son «sigue abierto» o
// «se cerró sin cambios», y ahí no hay nada que hacer salvo decir que se recibió.
if (!callback::wants_saving((int) ($body['status'] ?? 0))) {
    callback::answer(callback::OK);
}

$url = (string) ($body['url'] ?? '');
if ($url === '') {
    callback::answer(callback::ERROR_SAVE);
}

// La clave viaja firmada en el propio token: es lo que permite saber que quien
// guarda es la sesión que Moodle abrió, y no renovársela por debajo.
$stored = callback::store($instance, $path, $url, (int) $body['status'],
    (string) ($payload['key'] ?? ''));

callback::answer($stored ? callback::OK : callback::ERROR_SAVE);
