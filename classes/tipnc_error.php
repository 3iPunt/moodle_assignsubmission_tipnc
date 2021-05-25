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
 * tipnc_error
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc;

use assignsubmission_tipnc\api\error;
use dml_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * tipnc_error
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tipnc_error {

    const TABLE_TIPNC_ERROR = 'assignsubmission_tipnc_error';

    /**
     * Insert tipnc submission
     *
     * @param string $method
     * @param error $error
     * @param int $instance
     * @param int|null $submissionid
     * @return mixed
     * @throws dml_exception
     */
    static public function log(string $method, error $error, int $instance, int $submissionid = null) {
        global $DB, $USER;
        $data = new stdClass();
        $data->method = $method;
        $data->assignment = $instance;
        $data->submission = $submissionid;
        $data->userid = $USER->id;
        $data->error_code = $error->code;
        $data->error_message = $error->message;
        $data->timecreated = time();
        return $DB->insert_record(self::TABLE_TIPNC_ERROR, $data);
    }

}
