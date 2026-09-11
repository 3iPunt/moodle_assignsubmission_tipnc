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
 * State of the connection with NextCloud.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\api;

use assignsubmission_tipnc\log\code;
use coding_exception;
use dml_exception;
use stdClass;

/**
 * State of the connection with NextCloud, checked against the working folder.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class health {
    /**
     * Checks whether NextCloud answers right now.
     *
     * @return stdClass With configured, ok and detail.
     * @throws coding_exception If a language string is missing.
     * @throws dml_exception If the configuration cannot be read.
     */
    public function check(): stdClass {
        $state = new stdClass();
        $client = new client();

        $state->configured = $client->is_configured();
        $state->ok = false;
        $state->detail = '';

        if (!$state->configured) {
            return $state;
        }

        $folder = (string) get_config('assignsubmission_tipnc', 'folder');
        $user = (string) get_config('assignsubmission_tipnc', 'user');

        $answer = $client->request('PROPFIND', '/remote.php/dav/files/' . $user . '/' . $folder, [
            'headers' => ['OCS-APIRequest' => 'true', 'Depth' => '0'],
            'log' => false,
        ]);

        $state->ok = $answer->is_success();
        if (!$state->ok) {
            $state->detail = code::describe($this->reason($answer));
        }

        return $state;
    }

    /**
     * Why the check failed.
     *
     * @param  http_response $answer The answer.
     * @return string A code of the catalogue.
     */
    private function reason(http_response $answer): string {
        if ($answer->is_unreachable()) {
            return code::SYSTEM_UNREACHABLE;
        }
        if ($answer->is_unauthorised()) {
            return code::SYSTEM_UNAUTHORISED;
        }
        if ($answer->httpcode === 404) {
            return code::CONFIG_NO_TEMPLATE;
        }

        return code::SYSTEM_ERROR;
    }
}
