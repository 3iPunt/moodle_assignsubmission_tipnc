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
 * The document of a submission, in the submission status box.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\output;

use assignsubmission_tipnc\api\document;
use coding_exception;
use renderable;
use renderer_base;
use templatable;

/**
 * The document of a submission: which one it is, what state it is in and how to
 * reach it.
 *
 * Where the document is the person's own work it is shown; where the box is one
 * cell among many —the grading table— it is described and linked.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_summary implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param string      $url          Address where the document is read.
     * @param string      $mode         Which of the three documents this is.
     * @param bool        $embed        Show the document, or just describe it.
     * @param int         $timemodified When the submission last changed, 0 when unknown.
     * @param string|null $filename     Name in NextCloud, when it is known cheaply.
     * @param bool        $own          Whether the document belongs to whoever is looking.
     * @param string      $heightclass  Class that sets the height of the frame.
     * @param document_viewer|null $viewer How the document is shown, when it is shown.
     */
    public function __construct(
        private readonly string $url,
        private readonly string $mode,
        private readonly bool $embed = false,
        private readonly int $timemodified = 0,
        private readonly ?string $filename = null,
        private readonly bool $own = true,
        private readonly string $heightclass = '',
        private readonly ?document_viewer $viewer = null
    ) {
    }

    /**
     * Export for template.
     *
     * @param  renderer_base $output The renderer.
     * @return array The data of the box.
     * @throws coding_exception If a language string is missing.
     */
    public function export_for_template(renderer_base $output): array {
        [$state, $label, $action] = match ($this->mode) {
            document::MODE_OPEN => ['draft', 'subm_draft', 'subm_open_draft'],
            document::MODE_SUBMISSION => ['submitted', 'subm_submitted', 'subm_open_submitted'],
            default => ['enun', 'subm_enunciate', 'subm_open_enunciate'],
        };

        // The wording changes with who reads: the owner is addressed directly, and
        // whoever marks is told whose document it is.
        $whose = $this->own ? '_own' : '_other';
        $title = get_string("subm_title_$state" . $whose, 'assignsubmission_tipnc');

        return [
            'url' => $this->url,
            'state' => $state,
            'embed' => $this->embed,
            'heightclass' => $this->heightclass,
            'viewer' => $this->viewer?->export_for_template($output),
            'isdraft' => $state === 'draft',
            'issubmitted' => $state === 'submitted',
            'title' => $title,
            'label' => get_string($label, 'assignsubmission_tipnc'),
            'hint' => get_string("subm_hint_$state" . $whose, 'assignsubmission_tipnc'),
            'action' => get_string($action, 'assignsubmission_tipnc'),
            'frametitle' => $title,
            'filename' => $this->filename,
            'hasfilename' => $this->filename !== null,
            'modified' => $this->timemodified > 0
                ? get_string('subm_modified', 'assignsubmission_tipnc',
                    userdate($this->timemodified, get_string('strftimedatetimeshort', 'core_langconfig')))
                : null,
            'hasmodified' => $this->timemodified > 0,
        ];
    }
}
