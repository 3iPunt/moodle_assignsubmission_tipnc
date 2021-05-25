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
 * tipnc_enun
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
 * tipnc_enun
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tipnc_enun {

    const TABLE_TIPNC_ENUN = 'assignsubmission_tipnc_enun';

    /**
     * Get tipnc enunciate information from the database
     *
     * @param int $assignment
     * @return mixed
     * @throws dml_exception
     */
    static public function get(int $assignment) {
        global $DB;
        return $DB->get_record(self::TABLE_TIPNC_ENUN, array('assignment'=> $assignment));
    }

    /**
     * Set tipnc enunciate
     *
     * @param stdClass $data
     * @return mixed
     * @throws dml_exception
     */
    static public function set(stdClass $data) {
        global $DB;
        return $DB->insert_record(self::TABLE_TIPNC_ENUN, $data);
    }


}
