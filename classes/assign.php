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
use context_module;
use dml_exception;
use moodle_exception;

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
    public static function is_submission_nextcloud(cm_info $cm): bool {
        global $DB;

        // Sin SQL propio: los backticks y el alias entrecomillado de la versión
        // anterior eran sintaxis exclusiva de MySQL y rompían en PostgreSQL.
        $enabled = $DB->get_field('assign_plugin_config', 'value', [
            'assignment' => $cm->instance,
            'plugin' => 'tipnc',
            'subtype' => 'assignsubmission',
            'name' => 'enabled',
        ], IGNORE_MISSING);

        return $enabled === '1';
    }

    /**
     * Get submission
     *
     * @param int $submissionid
     * @return mixed
     * @throws dml_exception
     */
    public static function get_submission(int $submissionid) {
        global $DB;
        return $DB->get_record('assign_submission', array('id' => $submissionid));
    }

    /**
     * Whether the person looking marks this assignment.
     *
     * It asks what actually matters —marking this assignment— and asks it about
     * this assignment. «Can edit the course» is neither: a non-editing teacher
     * marks and would be told no, and someone who manages the course but is not
     * marking would be told yes and shown the submissions.
     *
     * @param  int $instance Assignment instance.
     * @return bool True when this person marks it.
     * @throws coding_exception If the capability does not exist.
     * @throws moodle_exception If the assignment cannot be found.
     */
    public static function is_teacher(int $instance): bool {
        [, $cm] = get_course_and_cm_from_instance($instance, 'assign');

        return has_capability('mod/assign:grade', context_module::instance($cm->id));
    }

}
