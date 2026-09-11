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
 * Serves a document to the editor.
 *
 * The Document Server has no Moodle session and never will: it is another server
 * fetching a file. What stands for permission here is the signature, which names
 * one document and expires. Nothing else is accepted, and nothing else is read
 * from the request.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../../../config.php');

use assignsubmission_tipnc\api\nextcloud;
use assignsubmission_tipnc\editor\token;

$signature = required_param('token', PARAM_RAW);
$payload = token::verify($signature);

// Una firma que no es nuestra, o que ha caducado, no merece una explicación.
if ($payload === null || ($payload['use'] ?? '') !== 'download') {
    header('HTTP/1.1 403 Forbidden');
    die();
}

$instance = (int) ($payload['instance'] ?? 0);
$path = (string) ($payload['path'] ?? '');

if ($instance === 0 || $path === '') {
    header('HTTP/1.1 403 Forbidden');
    die();
}

$answer = (new nextcloud($instance))->download_file($path, [
    'operation' => 'serve_document',
    'method' => 'document.php',
    'assignment' => $instance,
]);

if (!$answer->success) {
    header('HTTP/1.1 404 Not Found');
    die();
}

$content = (string) $answer->data;

header('Content-Type: application/octet-stream');
header('Content-Length: ' . strlen($content));
header('Content-Disposition: attachment; filename="' . rawurlencode(basename($path)) . '"');
header('Cache-Control: private, max-age=0, no-cache');

echo $content;
