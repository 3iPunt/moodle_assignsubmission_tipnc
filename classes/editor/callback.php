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
 * What the editor says about a document, and what Moodle does with it.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\editor;

use assignsubmission_tipnc\api\nextcloud;
use assignsubmission_tipnc\log\code;
use assignsubmission_tipnc\log\logger;
use assignsubmission_tipnc\models\sessions;
use core\http_client;
use dml_exception;
use Throwable;

/**
 * What the editor says about a document, and what Moodle does with it.
 *
 * The Document Server tells Moodle how an editing session went. Only two of its
 * states carry a document to store; the rest are news, and news is answered and
 * forgotten.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callback {

    /** @var int Everything went fine. It is what the editor expects to read. */
    public const OK = 0;

    /** @var int The message is not trusted. */
    public const ERROR_FORBIDDEN = 403;

    /** @var int The document could not be stored. */
    public const ERROR_SAVE = 1;

    /** @var int The editing session ended and there is a document to store. */
    public const STATUS_READY = 2;

    /** @var int A save was forced while people keep editing. */
    public const STATUS_FORCED = 6;

    /** @var int How long we wait for the editor to hand over the document. */
    public const TIMEOUT = 60;

    /**
     * Answers the editor and ends the request.
     *
     * The Document Server reads this and only this: a JSON with an error code. If
     * it does not get a zero it will call again, which is a feature —a save is not
     * lost because Moodle was down for a minute— as long as we answer honestly.
     *
     * @param  int $error One of the constants.
     * @return void
     */
    public static function answer(int $error): void {
        header('Content-Type: application/json');
        echo json_encode(['error' => $error === self::OK ? 0 : 1]);
        die();
    }

    /**
     * The body of the request, if it can be trusted.
     *
     * @return array|null What the editor said, or null when it cannot be trusted.
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function body(): ?array {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return null;
        }

        $body = json_decode($raw, true);
        if (!is_array($body)) {
            return null;
        }

        // El editor firma su mensaje con el mismo secreto: es lo que distingue una
        // llamada suya de cualquiera que haya visto pasar la dirección.
        $signature = $body['token'] ?? self::bearer();
        if (!is_string($signature) || $signature === '') {
            return null;
        }

        $verified = token::verify($signature);
        if ($verified === null) {
            return null;
        }

        // Lo firmado manda sobre lo que venga suelto en el cuerpo.
        return array_merge($body, $verified['payload'] ?? $verified);
    }

    /**
     * Whether this state of the editor carries a document to store.
     *
     * @param  int $status State the editor reports.
     * @return bool True when there is something to save.
     */
    public static function wants_saving(int $status): bool {
        return in_array($status, [self::STATUS_READY, self::STATUS_FORCED], true);
    }

    /**
     * Takes the document from the editor and stores it in NextCloud.
     *
     * @param  int    $instance Assignment the document belongs to.
     * @param  string $path     Where it goes in NextCloud.
     * @param  string $url      Where the editor left the result.
     * @param  int    $status   State the editor reported.
     * @param  string $key      Key of the session that is saving.
     * @return bool True when the document is stored.
     * @throws dml_exception If the incident cannot be recorded.
     */
    public static function store(int $instance, string $path, string $url, int $status,
                                 string $key = ''): bool {
        $context = [
            'operation' => $status === self::STATUS_FORCED ? 'forced_save' : 'editor_save',
            'method' => 'callback.php',
            'assignment' => $instance,
            'documentpath' => $path,
            'requesturl' => $url,
        ];

        try {
            $content = (new http_client(['timeout' => self::TIMEOUT]))->get($url)->getBody()->getContents();
        } catch (Throwable $e) {
            logger::error(code::DOWNLOAD_FAILED, $context['operation'], array_merge($context, [
                'responsebody' => $e->getMessage(),
            ]));

            return false;
        }

        if ($content === '') {
            logger::error(code::DOWNLOAD_FAILED, $context['operation'], array_merge($context, [
                'responsebody' => get_string('log_save_empty', 'assignsubmission_tipnc'),
            ]));

            return false;
        }

        $nextcloud = new nextcloud($instance);

        $stored = $nextcloud->upload_file($path, $content, $context);
        if (!$stored->success) {
            return false;
        }

        // El documento acaba de cambiar, pero lo cambió la sesión que sigue abierta:
        // se anota la fecha nueva y se le deja su clave, o al siguiente pintado se
        // le daría una nueva y ya no habría a quién pedirle que guarde.
        (new sessions())->note_save($key, $nextcloud->modified_time($path));

        // Un guardado que sale bien también se registra: es la prueba de que lo que
        // hay en NextCloud es lo que la persona escribió, y en qué momento.
        logger::info(code::OK, $context['operation'], array_merge($context, [
            'responsebody' => 'Guardado ' . strlen($content) . ' bytes.',
        ]));

        return true;
    }

    /**
     * The signature that came in the Authorization header, if any.
     *
     * @return string The signature, empty when there is none.
     */
    private static function bearer(): string {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';
    }
}
