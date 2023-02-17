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

use assignsubmission_tipnc\api\error;
use dml_exception;
use moodle_exception;
use stdClass;

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
    public static function get(int $submissionid) {
        global $DB;
        return $DB->get_record(self::TABLE_TIPNC, array('submission' => $submissionid));
    }

    /**
     * Update tipnc submission
     *
     * @param stdClass $data
     * @throws dml_exception
     */
    public static function update(stdClass $data) {
        global $DB;
        try {
            $DB->update_record(self::TABLE_TIPNC, $data);
        } catch (moodle_exception $e) {
            $assignment = empty($data->assignment) ? 0 : $data->assignment;
            $submission = empty($data->submission) ? null : $data->submission;
            tipnc_error::log('tipnc:update', new error('2000', $e->getMessage()), $assignment, $submission);
        }
    }

    /**
     * Insert tipnc submission
     *
     * @param stdClass $data
     * @throws dml_exception
     */
    public static function set(stdClass $data) {
        global $DB;
        try {
            $DB->insert_record(self::TABLE_TIPNC, $data);
        } catch (moodle_exception $e) {
            $assignment = empty($data->assignment) ? 0 : $data->assignment;
            $submission = empty($data->submission) ? null : $data->submission;
            tipnc_error::log('tipnc:set', new error('2001', $e->getMessage()), $assignment, $submission);
        }
    }

    /**
     * Delete by submission_id
     *
     * @param int $submissionid
     * @param int $assignment
     * @throws dml_exception
     */
    public static function delete_by_submissionid(int $submissionid, int $assignment) {
        global $DB;
        try {
            $DB->delete_records(self::TABLE_TIPNC, ['submission' => $submissionid]);
        } catch (moodle_exception $e) {
            tipnc_error::log('tipnc:delete_by_submissionid',
                new error('2002', $e->getMessage()), $assignment, $submissionid);
        }
    }

    /**
     * Delete by Instance (Alls submission)
     *
     * @param int $instance
     * @throws dml_exception
     */
    public static function delete(int $instance) {
        global $DB;
        try {
            $DB->delete_records(self::TABLE_TIPNC, ['assignment' => $instance]);
        } catch (moodle_exception $e) {
            tipnc_error::log('tipnc:delete', new error('2003', $e->getMessage()), $instance);
        }
    }

}
