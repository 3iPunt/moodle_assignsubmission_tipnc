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
 * Class renderer
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace assignsubmission_tipnc\output;

defined('MOODLE_INTERNAL') || die;

use moodle_exception;
use plugin_renderer_base;

/**
 * Class renderer
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Defer to template.
     *
     * @param view_submission_component $component
     *
     * @return string html for the page
     * @throws moodle_exception
     */
    public function render_view_submission_component(view_submission_component $component): string {
        $data = $component->export_for_template($this);
        return parent::render_from_template('assignsubmission_tipnc/view_submission_component', $data);
    }
    /**
     * Defer to template.
     *
     * @param url_submission_component $component
     *
     * @return string html for the page
     * @throws moodle_exception
     */
    public function render_url_submission_component(url_submission_component $component): string {
        $data = $component->export_for_template($this);
        return parent::render_from_template('assignsubmission_tipnc/url_submission_component', $data);
    }

}
