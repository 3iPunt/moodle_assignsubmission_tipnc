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
 * Orders Moodle can give the editor.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\editor;

use coding_exception;
use core\http_client;
use dml_exception;
use Throwable;

/**
 * Orders Moodle can give the editor.
 *
 * There is only one that matters: «save now». The editor keeps what people write
 * in its own session and writes it to the document when the session ends, so
 * without this order, submitting right after typing freezes the previous version.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class command {
    /** @var int Seconds to wait for the editor to answer. */
    public const TIMEOUT = 20;

    /** @var int The editor did as it was told. */
    public const OK = 0;

    /** @var int There was nothing open to save. */
    public const NOTHING_TO_SAVE = 4;

    /** @var int The order never reached the editor. */
    public const NOT_ASKED = -1;

    /**
     * Tells the editor to write what it has into the document.
     *
     * @param  string $key Key of the editing session, the same one used to open it.
     * @return int What the editor answered; OK and NOTHING_TO_SAVE mean the
     *             document holds what was written.
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function force_save(string $key): int {
        if (!token::is_configured()) {
            return self::NOT_ASKED;
        }

        $payload = [
            'c' => 'forcesave',
            'key' => $key,
            'userdata' => 'moodle-submit',
        ];

        try {
            $response = (new http_client(['timeout' => self::TIMEOUT]))->post(
                token::server_url() . '/coauthoring/CommandService.ashx',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . token::sign(['payload' => $payload], self::TIMEOUT),
                    ],
                    'body' => json_encode($payload + ['token' => token::sign($payload, self::TIMEOUT)]),
                ]
            );

            $answer = json_decode($response->getBody()->getContents(), true);
        } catch (Throwable $e) {
            return self::NOT_ASKED;
        }

        return (int) ($answer['error'] ?? self::NOT_ASKED);
    }

    /**
     * Whether an answer means the document holds what was written.
     *
     * «Nothing to save» counts: it means nobody is editing, so what is in the
     * document is already everything there is.
     *
     * @param  int $error What the editor answered.
     * @return bool True when there is nothing left to wait for.
     */
    public static function saved(int $error): bool {
        return in_array($error, [self::OK, self::NOTHING_TO_SAVE], true);
    }

    /**
     * What an answer of the editor means, for the incident log.
     *
     * @param  int $error What the editor answered.
     * @return string A short explanation.
     * @throws coding_exception If a language string is missing.
     */
    public static function describe(int $error): string {
        $reason = match ($error) {
            self::NOT_ASKED => 'notasked',
            self::OK => 'ok',
            1 => 'key',
            2 => 'callback',
            3 => 'internal',
            self::NOTHING_TO_SAVE => 'nochanges',
            5 => 'command',
            6 => 'token',
            default => 'unknown',
        };

        return get_string('editor_answer_' . $reason, 'assignsubmission_tipnc', $error);
    }
}
