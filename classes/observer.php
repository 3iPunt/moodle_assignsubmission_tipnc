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
 * Class Observer assignsubmission_tipnc
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 */

use assignsubmission_tipnc\api\nextcloud;
use assignsubmission_tipnc\tipnc_error;
use core\event\course_module_created;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Class Event observer for assignsubmission_tipnc.
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class assignsubmission_tipnc_observer {

    /**
     * Evento que controla la creación del curso.
     *
     * @param course_module_created $event
     * @return bool
     * @throws moodle_exception
     */
    public static function course_module_created(course_module_created $event): bool {
        $cmid = $event->objectid;
        try {
            list($course, $cm) = get_course_and_cm_from_cmid($cmid);
            if ($cm->modname === 'assign') {
                $nextcloud = new nextcloud($cm->instance);
                $res = $nextcloud->teacher_create();
                if (!$res->success) {
                    tipnc_error::log('course_module_created', $res->error, $cm->instance);
                    return false;
                }
            }
        } catch (moodle_exception $e) {
            $assignment = isset($event->other->instanceid) ? $event->other->instanceid : 0;
            tipnc_error::log('course_module_created',
                new \assignsubmission_tipnc\api\error(3000, $e->getMessage()), $assignment);
            return false;
        }

        return true;
    }

}