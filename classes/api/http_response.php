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
 * Answer of a call to NextCloud.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\api;

/**
 * Answer of a call to NextCloud, judged by its status code and not by its text.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class http_response {
    /**
     * Constructor.
     *
     * @param int    $httpcode  Status code; 0 when the server could not be reached.
     * @param string $body      Response body.
     * @param int    $duration  Time the call took, in milliseconds.
     * @param string $transport Transport error, empty when there was none.
     */
    public function __construct(
        /** @var int HTTP status code returned by the server. */
        public readonly int $httpcode,
        /** @var string Response body. */
        public readonly string $body,
        /** @var int Time the call took, in milliseconds. */
        public readonly int $duration,
        /** @var string Transport error, empty when there was none. */
        public readonly string $transport = ''
    ) {
    }

    /**
     * Whether the call succeeded.
     *
     * @return bool True on a 2xx status code with no transport error.
     */
    public function is_success(): bool {
        return $this->transport === '' && $this->httpcode >= 200 && $this->httpcode < 300;
    }

    /**
     * Whether the server could not be reached at all.
     *
     * @return bool True when there is no status code.
     */
    public function is_unreachable(): bool {
        return $this->transport !== '' || $this->httpcode === 0;
    }

    /**
     * Whether the service credentials were rejected.
     *
     * @return bool True on 401 or 403.
     */
    public function is_unauthorised(): bool {
        return $this->httpcode === 401 || $this->httpcode === 403;
    }

    /**
     * Whether the server failed on its side.
     *
     * @return bool True on 5xx.
     */
    public function is_server_error(): bool {
        return $this->httpcode >= 500;
    }

    /**
     * Body decoded as JSON.
     *
     * @return array The decoded body, empty when it is not valid JSON.
     */
    public function json(): array {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
