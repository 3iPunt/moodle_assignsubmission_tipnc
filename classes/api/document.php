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
 * Document
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\api;

use dml_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Document
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document {

    const PREFIX_ENUN = 'enun_';
    const PREFIX_OPEN = 'open_';
    const PREFIX_SUBMISSION = 'subm_';

    const MODE_ENUM = 'enun';
    const MODE_OPEN = 'open';
    const MODE_SUBMISSION = 'submission';

    /** @var int Instance */
    protected $instance;

    /** @var string Folder */
    protected $folder;

    /** @var string Template */
    protected $template;

    /** @var string Extension file */
    protected $extension;

    /**
     * Document constructor.
     *
     * @param int $instance
     * @throws dml_exception
     */
    public function __construct(int $instance) {
        $this->folder = get_config('assignsubmission_tipnc', 'folder');
        $this->template = get_config('assignsubmission_tipnc', 'template');
        $this->instance = $instance;
        $this->set_extension();
    }

    /**
     * Get Template.
     *
     * @return string
     */
    public function get_template(): string {
        return $this->folder . '/' . $this->template;
    }

    /**
     * Get Enunciate.
     *
     * @return string
     */
    public function get_enunciate(): string {
        return $this->folder . '/' . self::PREFIX_ENUN . $this->instance . $this->extension;
    }

    /**
     * Get Open.
     *
     * @param string $student
     * @return string
     */
    public function get_open(string $student): string {
        return $this->folder . '/' .
            self::PREFIX_OPEN . $this->instance  . '_' . $student . $this->extension;
    }

    /**
     * Get Enunciate.
     *
     * @param string $student
     * @return string
     */
    public function get_submission(string $student): string {
        return $this->folder . '/' .
            self::PREFIX_SUBMISSION . $this->instance  . '_' . $student . $this->extension;
    }

    /**
     * Set extension.
     *
     */
    protected function set_extension() {
        $extension = '';
        $pos_point = strpos($this->template, '.');
        if ($pos_point !== false) {
            $extension = substr($this->template, $pos_point);
        }
        $this->extension = $extension;
    }

    /**
     * Get URL.
     *
     * @param int $ncid
     * @return string
     * @throws dml_exception
     */
    static public function get_url(int $ncid): string {
        $host = get_config('assignsubmission_tipnc', 'host');
        $location = get_config('assignsubmission_tipnc', 'location');
        return $host . $location . $ncid;
    }

}
