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
 * Class view_errors_page
 *
 * @package    assignsubmission_tipnc
 * @copyright  2021 Tresipunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\output;

use assignsubmission_tipnc\tables\errors_table;
use assignsubmission_tipnc\tipnc_error;
use coding_exception;
use moodle_exception;
use renderable;
use renderer_base;
use stdClass;
use templatable;

defined('MOODLE_INTERNAL') || die;

/**
 * Class view_errors_page
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_errors_page implements renderable, templatable {

    /**
     * charge_page constructor.
     *
     */
    public function __construct() {
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     *
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->table = $this->get_errors_table();
        return $data;
    }

    /**
     * Get charge table
     *
     * @return string
     * @throws coding_exception
     */
    protected function get_errors_table(): string {
        $uniqid = uniqid('', true);
        $table = new errors_table($uniqid);
        $table->is_downloadable(false);
        $select = 'te.id AS id, te.assignment AS assignment, te.submission AS submission, te.userid AS userid, 
         te.method AS method, te.error_code AS error_code, te.error_message AS error_message, te.timecreated AS timecreated';
        $from = '{' . tipnc_error::TABLE_TIPNC_ERROR .'} te';
        $where = '1=1';
        $params = [];
        $table->set_sql($select, $from, $where, $params);
        $table->sortable(true, 'id', SORT_DESC);
        $table->pageable(true);
        $table->collapsible(false);
        $table->define_baseurl('/mod/assign/submission/tipnc/view_errors.php');
        ob_start();
        $table->out(15, true, false);
        $tablecontent = ob_get_contents();
        ob_end_clean();
        return $tablecontent;
    }
}