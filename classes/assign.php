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
 * assign
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc;

use cm_info;
use coding_exception;
use context_course;
use dml_exception;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * assign
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign {

    /**
     * Is Submission NextCloud?
     *
     * @param cm_info $cm
     * @return mixed
     * @throws dml_exception
     */
    static public function is_submission_nextcloud(cm_info $cm): bool {
        global $DB;
        $is = false;
        $sql = "SELECT apc.value 'is_active'
                FROM {assign_plugin_config} apc
                WHERE apc.assignment = ?
                AND apc.`plugin` = 'tipnc'
                AND apc.subtype = 'assignsubmission'
                AND apc.name = 'enabled'";
        $result = $DB->get_records_sql($sql, [ $cm->instance ]);
        if (count($result) > 0) {
            $plug = current($result);
            if ($plug->is_active === '1') {
                $is = true;
            }
        }
        return $is;
    }

    /**
     * Is Teacher?
     *
     * @param int $instance
     * @return bool
     * @throws coding_exception
     * @throws moodle_exception
     */
    static public function is_teacher(int $instance): bool {
        list($course, $cm) = get_course_and_cm_from_instance($instance, 'assign');
        $coursecontext = context_course::instance($course->id);
        return has_capability('moodle/course:update', $coursecontext);
    }
}
