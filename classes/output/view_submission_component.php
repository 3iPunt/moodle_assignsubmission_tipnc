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
 * Class view_submission_component
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\output;

use assignsubmission_tipnc\api\document;
use coding_exception;
use core_user;
use dml_exception;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use theme_cbe\user;

defined('MOODLE_INTERNAL') || die;

/**
 * Class view_submission_component
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_submission_component implements renderable, templatable {

    /** @var string URL */
    protected $url;

    /** @var string Mode */
    protected $mode;

    /**
     * constructor.
     * @param string $url
     * @param string $mode
     */
    public function __construct(string $url, string $mode) {
        $this->url = $url;
        $this->mode = $mode;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return stdClass
     * @throws coding_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->url = $this->url;
        switch ($this->mode) {
            case document::MODE_ENUM:
                $strbutton = get_string('view_enun', 'assignsubmission_tipnc');
                break;
            case document::MODE_SUBMISSION:
                $strbutton = get_string('view_submission', 'assignsubmission_tipnc');
                break;
            default:
                $strbutton = get_string('view', 'assignsubmission_tipnc');
        }
        $data->strbutton = $strbutton;
        return $data;
    }
}
