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
 * Whether NextCloud is answering.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\models;

use assignsubmission_tipnc\log\code;
use cache;
use stdClass;

/**
 * Whether NextCloud is answering, as the last real call left it.
 *
 * Pages need this to say «not right now» instead of showing an empty box, but
 * they cannot ask the network: view_summary() runs once per row of the grading
 * table. So nobody asks on purpose —every call that the plugin already makes
 * writes down how it went, and the pages read that.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class health {
    /** @var string Where the state is kept. */
    private const KEY = 'nextcloud';

    /**
     * Writes down how a call went.
     *
     * @param  string $errorcode Code of the catalogue, OK when it worked.
     * @return void
     */
    public static function note(string $errorcode): void {
        if ($errorcode === code::OK) {
            self::cache()->set(self::KEY, (object) ['down' => false, 'since' => 0, 'code' => code::OK]);

            return;
        }

        // Which is a failure of the service and which of a document is said by
        // the catalogue, which is where it is decided: having it here too would
        // be the same truth twice, and every new code would have to remember both.
        if (!code::is_system_failure($errorcode)) {
            return;
        }

        $state = self::cache()->get(self::KEY);

        self::cache()->set(self::KEY, (object) [
            'down' => true,

            // Since when, not when it was last seen: if it was already down the
            // first moment is kept, which is what says how long it has been like this.
            'since' => (!empty($state->down) && !empty($state->since)) ? $state->since : time(),
            'code' => $errorcode,
        ]);
    }

    /**
     * Whether the last thing that happened was a failure of the service.
     *
     * @return bool True when it is known to be down.
     */
    public static function is_down(): bool {
        $state = self::cache()->get(self::KEY);

        return !empty($state->down);
    }

    /**
     * What is known about the failure.
     *
     * @return stdClass|null Code and time it started, null when nothing is wrong.
     */
    public static function state(): ?stdClass {
        $state = self::cache()->get(self::KEY);

        return !empty($state->down) ? $state : null;
    }

    /**
     * Forgets what was known, so the next call decides again.
     *
     * @return void
     */
    public static function forget(): void {
        self::cache()->delete(self::KEY);
    }

    /**
     * The store holding the state.
     *
     * @return cache The cache.
     */
    private static function cache(): cache {
        return cache::make('assignsubmission_tipnc', 'health');
    }
}
