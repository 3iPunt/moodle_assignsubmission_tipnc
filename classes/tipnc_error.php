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
use assignsubmission_tipnc\log\logger;
use dml_exception;

/**
 * tipnc_error
 *
 * @deprecated  Se mantiene mientras queden llamadas del código antiguo.
 *              El registro vive ahora en assignsubmission_tipnc\log\logger.
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tipnc_error {

    /**
     * Records an incident coming from the old code.
     *
     * @param  string   $method       Point in the code.
     * @param  error    $error        The failure.
     * @param  int      $instance     Assignment instance.
     * @param  int|null $submissionid Submission, when there is one.
     * @return int The id of the entry.
     * @throws dml_exception If the entry cannot be stored.
     */
    public static function log(string $method, error $error, int $instance, ?int $submissionid = null): int {
        return logger::error((string) $error->code, '', [
            'method' => $method,
            'assignment' => $instance,
            'submission' => $submissionid,
            'responsebody' => (string) $error->message,
        ]);
    }
}
