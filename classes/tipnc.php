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
 * tipnc
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc;

use dml_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * tipnc
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tipnc {

    const TABLE_TIPNC = 'assignsubmission_tipnc';

    /**
     * Get tipnc submission information from the database
     *
     * @param int $submissionid
     * @return mixed
     * @throws dml_exception
     */
    static public function get(int $submissionid) {
        global $DB;
        return $DB->get_record(self::TABLE_TIPNC, array('submission'=>$submissionid));
    }

    /**
     * Update tipnc submission
     *
     * @param stdClass $data
     * @return mixed
     * @throws dml_exception
     */
    static public function update(stdClass $data) {
        global $DB;
        return $DB->update_record(self::TABLE_TIPNC, $data);
    }

    /**
     * Insert tipnc submission
     *
     * @param stdClass $data
     * @return mixed
     * @throws dml_exception
     */
    static public function set(stdClass $data) {
        global $DB;
        return $DB->insert_record(self::TABLE_TIPNC, $data);
    }

    /**
     * Delete
     *
     * @param int $submissionid
     * @return mixed
     * @throws dml_exception
     */
    static public function delete(int $submissionid) {
        global $DB;
        $DB->delete_records(self::TABLE_TIPNC, ['submission' => $submissionid]);
    }

}
