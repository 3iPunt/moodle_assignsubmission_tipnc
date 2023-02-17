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
 * Class errors_table
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\tables;

use coding_exception;
use dml_exception;
use moodle_exception;
use moodle_url;
use stdClass;
use table_sql;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot.'/lib/tablelib.php');

/**
 * Class errors_table
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class errors_table extends table_sql {

    /**
     * Constructor.
     *
     * @param string $uniqueid
     * @throws coding_exception
     */
    public function __construct(string $uniqueid) {
        parent::__construct($uniqueid);
        $this->define_columns([
            'id',
            'coursemodule',
            'assignment',
            'submission',
            'userid',
            'method',
            'error_code',
            'error_message',
            'timecreated'
        ]);
        $this->define_headers([
            get_string('tableerrors_id', 'assignsubmission_tipnc'),
            get_string('tableerrors_coursemodule', 'assignsubmission_tipnc'),
            get_string('tableerrors_assignment', 'assignsubmission_tipnc'),
            get_string('tableerrors_submission', 'assignsubmission_tipnc'),
            get_string('tableerrors_userid', 'assignsubmission_tipnc'),
            get_string('tableerrors_method', 'assignsubmission_tipnc'),
            get_string('tableerrors_error_code', 'assignsubmission_tipnc'),
            get_string('tableerrors_error_message', 'assignsubmission_tipnc'),
            get_string('tableerrors_timecreated', 'assignsubmission_tipnc')
        ]);

        // Allow pagination.
        $this->pageable(true);

        $this->no_sorting('coursemodule');

        $this->sortable(true);
        $this->column_style('id', 'text-align', 'center');
        $this->column_style('coursemodule', 'text-align', 'left');
        $this->column_style('assignment', 'text-align', 'left');
        $this->column_style('submission', 'text-align', 'center');
        $this->column_style('userid', 'text-align', 'center');
        $this->column_style('method', 'text-align', 'left');
        $this->column_style('error_code', 'text-align', 'center');
        $this->column_style('error_message', 'text-align', 'left');
        $this->column_style('timecreated', 'text-align', 'center');
    }

    /**
     * Col Id.
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_id(stdClass $row): string {
        return $row->id;
    }

    /**
     * Col coursemodule
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws moodle_exception
     */
    public function col_coursemodule(stdClass $row): string {
        try {
            list($course, $assigncm) = get_course_and_cm_from_instance($row->assignment, 'assign');
            $cmid = $assigncm->id;
            $urlcmid = new moodle_url('/mod/assign/view.php', array('id' => $cmid));
            $cmid = '<a href="' . $urlcmid . '" target="_blank" class="btn btn-primary btn-sm">' . $cmid . '</a>';
        } catch (moodle_exception $e) {
            if ($e->errorcode === 'invalidrecordunknown') {
                $cmid = get_string('tableerrors_coursemodule_delete', 'assignsubmission_tipnc');
            } else {
                $cmid = $e->errorcode;
            }
        }
        return $cmid;
    }

    /**
     * Col assignment
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_assignment(stdClass $row): string {
        return $row->assignment;
    }

    /**
     * Col submission
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_submission(stdClass $row): string {
        if (isset($row->submission)) {
            return $row->submission;
        } else {
            return '-';
        }
    }

    /**
     * Col Userid
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws dml_exception
     */
    public function col_userid(stdClass $row): string {
        global $DB;
        if (!empty($row->userid)) {
            $user = $DB->get_record('user', array('id' => $row->userid), 'username');
            if (!empty($user)) {
                return $user->username;
            } else {
                return '';
            }
        } else {
            return '';
        }
    }

    /**
     * Col Method
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_method(stdClass $row): string {
        return $row->method;
    }

    /**
     * Col error_code
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_error_code(stdClass $row): string {
        return $row->error_code;
    }

    /**
     * Col error_message
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     */
    public function col_error_message(stdClass $row): string {
        return $row->error_message;
    }

    /**
     * Col TimeCreated
     *
     * @param stdClass $row Full data of the current row.
     * @return string
     * @throws coding_exception
     */
    public function col_timecreated(stdClass $row): string {
        return userdate($row->timecreated, get_string('strftimedatetimeshort'));
    }

}
