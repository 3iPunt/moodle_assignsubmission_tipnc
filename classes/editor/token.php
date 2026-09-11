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
 * The signature Moodle and the editor use to trust each other.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\editor;

use dml_exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use moodle_exception;
use Throwable;

/**
 * The signature Moodle and the editor use to trust each other.
 *
 * With the editor embedded by Moodle there is no session in between: what says
 * who may open a document is this signature, so it is also what protects the
 * address that serves it. Short lived and for one document at a time.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token {

    /** @var string The only algorithm accepted, on both sides. */
    public const ALGORITHM = 'HS256';

    /** @var int How long a signature is good for, in seconds. */
    public const LIFETIME = HOURSECS;

    /**
     * Whether the site has told the plugin how to talk to the editor.
     *
     * @return bool True when there is an address and a secret.
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function is_configured(): bool {
        return self::server_url() !== '' && self::secret() !== '';
    }

    /**
     * Public address of the Document Server, as the browser reaches it.
     *
     * @return string The address, without a trailing slash.
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function server_url(): string {
        return rtrim((string) get_config('assignsubmission_tipnc', 'docserverurl'), '/');
    }

    /**
     * Signs a payload for the editor.
     *
     * @param  array $payload What is being said.
     * @param  int   $lifetime Seconds it stays valid.
     * @return string The signature.
     * @throws moodle_exception If the site has no secret configured.
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function sign(array $payload, int $lifetime = self::LIFETIME): string {
        $secret = self::secret();
        if ($secret === '') {
            throw new moodle_exception('editor_nosecret', 'assignsubmission_tipnc');
        }

        $payload['iat'] = time();
        $payload['exp'] = time() + $lifetime;

        return JWT::encode($payload, $secret, self::ALGORITHM);
    }

    /**
     * Reads a signature, refusing anything that is not ours and current.
     *
     * @param  string $jwt The signature.
     * @return array|null What it said, or null when it cannot be trusted.
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function verify(string $jwt): ?array {
        $secret = self::secret();
        if ($secret === '' || $jwt === '') {
            return null;
        }

        try {
            // Expiry and signature are checked by the library itself; a failure here
            // is an empty response, not an exception leaking to the screen.
            $payload = JWT::decode($jwt, new Key($secret, self::ALGORITHM));
        } catch (Throwable $e) {
            return null;
        }

        return json_decode(json_encode($payload), true);
    }

    /**
     * Secret shared with the Document Server.
     *
     * @return string The secret, empty when the site has not set one.
     * @throws dml_exception If the configuration cannot be read.
     */
    private static function secret(): string {
        return trim((string) get_config('assignsubmission_tipnc', 'docserversecret'));
    }
}
