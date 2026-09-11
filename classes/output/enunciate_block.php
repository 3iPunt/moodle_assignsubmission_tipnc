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
 * The brief of the assignment, shown on its page.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\output;

use assignsubmission_tipnc\models\documents;
use coding_exception;
use dml_exception;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * The brief of the assignment: the document the teacher has to write, which the
 * core never shows them.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enunciate_block implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param string      $viewurl    Address of the document, empty when there is none.
     * @param string      $filename   Name of the document in NextCloud.
     * @param string|null $folderurl   Address of the folder holding it.
     * @param bool        $embed       Show the document, or just link to it.
     * @param string      $heightclass Class that sets the height of the frame.
     * @param document_viewer|null $viewer How the document is shown, when it is shown.
     * @param moodle_url|null $prepareurl Where to ask for the document to be made,
     *                                    only for whoever is allowed to ask.
     */
    public function __construct(
        private readonly string $viewurl,
        private readonly string $filename,
        private readonly ?string $folderurl = null,
        private readonly bool $embed = true,
        private readonly string $heightclass = '',
        private readonly ?document_viewer $viewer = null,
        private readonly ?moodle_url $prepareurl = null
    ) {
    }

    /**
     * Builds the block for one assignment.
     *
     * @param  int  $assignment Assignment instance.
     * @param  bool $embed      Show the document, or just link to it.
     * @return self The block.
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function for_assignment(int $assignment, bool $embed = true,
                                          ?moodle_url $prepareurl = null,
                                          bool $canedit = false): self {
        global $USER;

        $model = new documents();
        $enunciate = $model->get_enunciate($assignment);

        $path = $model->enunciate_path_of($assignment);

        // Quien califica escribe el enunciado; el resto lo lee. Abrirlo siempre en
        // solo lectura obligaba al profesorado a salir a NextCloud para escribirlo.
        $viewer = ($enunciate && $embed)
            ? document_viewer::for_document($assignment, $path, (int) $enunciate->ncid,
                get_string('enunciate_frametitle', 'assignsubmission_tipnc'),
                $USER, $canedit, $model->frame_height_class())
            : null;

        return new self(
            $enunciate ? $model->view_url((int) $enunciate->ncid) : '',
            $model->enunciate_filename($assignment),
            $model->folder_url(),
            $embed,
            $model->frame_height_class(),
            $viewer,
            $prepareurl
        );
    }

    /**
     * Export for template.
     *
     * @param  renderer_base $output The renderer.
     * @return array The data of the block.
     * @throws coding_exception If a language string is missing.
     */
    public function export_for_template(renderer_base $output): array {
        $ready = $this->viewurl !== '';

        return [
            'ready' => $ready,
            'embed' => $ready && $this->embed,
            'heightclass' => $this->heightclass,
            'viewer' => $this->viewer?->export_for_template($output),
            'viewurl' => $this->viewurl,
            'filename' => $this->filename,
            'folderurl' => $this->folderurl,
            'hasfolder' => !empty($this->folderurl),

            // Quien puede arreglarlo ve el diagnóstico y el botón; quien no, una
            // frase que le diga qué pasa y a quién avisar.
            'canprepare' => $this->prepareurl !== null,
            'prepareurl' => $this->prepareurl?->out(false),
            'frametitle' => get_string('enunciate_frametitle', 'assignsubmission_tipnc'),
        ];
    }
}
