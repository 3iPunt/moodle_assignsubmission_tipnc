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
 * Event observers of the plugin.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc;

use assignsubmission_tipnc\task\revoke_person;
use context;
use core\event\role_unassigned;
use core\event\user_enrolment_deleted;

/**
 * Event observers of the plugin.
 *
 * Somebody who stops belonging to a course keeps whatever access to its
 * documents NextCloud gave them, because NextCloud has no idea Moodle changed
 * its mind. These observers are what closes that gap on the same day.
 *
 * They only write down what has to happen. Doing it here would mean a course
 * with five assignments and a hundred students making hundreds of calls inside
 * the click that removed one enrolment —and it would be lost for good if any of
 * them failed. The adhoc task Moodle retries on its own.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Somebody was unenrolled from a course.
     *
     * @param  user_enrolment_deleted $event The event.
     * @return void
     */
    public static function user_enrolment_deleted(user_enrolment_deleted $event): void {
        revoke_person::queue((int) $event->courseid, (int) $event->relateduserid);
    }

    /**
     * A role was taken away from somebody.
     *
     * Only roles given in a course or below matter here: the access this plugin
     * hands out is per assignment, and a role given at the site level does not
     * decide who teaches what.
     *
     * @param  role_unassigned $event The event.
     * @return void
     */
    public static function role_unassigned(role_unassigned $event): void {
        $context = context::instance_by_id($event->contextid, IGNORE_MISSING);

        if (!$context) {
            return;
        }

        $coursecontext = $context->get_course_context(false);

        if (!$coursecontext) {
            return;
        }

        revoke_person::queue((int) $coursecontext->instanceid, (int) $event->relateduserid);
    }
}
