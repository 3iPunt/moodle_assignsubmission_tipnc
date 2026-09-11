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
 * Scheduled purge of the incident log.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\task;

use assignsubmission_tipnc\models\incidents;
use coding_exception;
use core\task\scheduled_task;
use dml_exception;

/**
 * Scheduled purge of the incident log: without it the table grows without end.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_log extends scheduled_task {
    /**
     * Name shown in the list of scheduled tasks.
     *
     * @return string The name.
     * @throws coding_exception If the language string is missing.
     */
    public function get_name(): string {
        return get_string('task_purge_log', 'assignsubmission_tipnc');
    }

    /**
     * Deletes the incidents older than the configured retention.
     *
     * @return void
     * @throws dml_exception If the deletion fails.
     */
    public function execute(): void {
        $days = (int) get_config('assignsubmission_tipnc', 'logretention');

        // A retention of zero means keeping everything: that is a decision of
        // the administrator, not an unconfigured value.
        if ($days <= 0) {
            mtrace('Retention disabled: nothing is purged.');
            return;
        }

        $before = time() - ($days * DAYSECS);
        $deleted = (new incidents())->purge($before);

        mtrace("Incidents older than {$days} days deleted: {$deleted}.");
    }
}
