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
 * Class nextcloud
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc;

use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * Class nextcloud
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nextcloud {

    /** @var string Host */
    protected $host;

    /**
     * constructor.
     *
     */
    public function __construct() {
    }

    protected function copy_file(string $origin, string $destiny) {
        return '';
    }

    /**
     * Student Submit.
     *
     * @param int $cmid
     * @return bool
     */
    public function teacher_create(int $cmid): bool {
        global $USER;
        // TODO. NEXTCLOUD: Copiar archivo Nextcloud.
        $response = $this->copy_file('foldername/Prueba.docx', 'foldername/Prueba2.docx');

        // TODO. Permisos de edición al profesor.


        // TODO. Actualiazar Tabla enun ¿Necesitamos tabla de enunciado? AssignId, NCId


        return true;
    }

    /**
     * Student Submit.
     *
     * @param stdClass $tipncsubmission
     * @return bool
     */
    public function student_view(stdClass $tipncsubmission): bool {
        global $USER;
        // TODO. NEXTCLOUD: Si no existe submissión, se copia del archivo del profesor.
        // TODO. EL alumno, como editor.
        // TODO. Si ya existe submission, se comprueba que haya NCId vinculado
        // TODO. Si no existe NCid vinculado, se copia del archivo del profesor y se vincula.
        // TODO. Si está el NCid vinculado, se dan permisos de edición.
        return true;
    }

    /**
     * Student Submit.
     *
     * @param stdClass $tipncsubmission
     * @return bool
     */
    public function student_submits(stdClass $tipncsubmission): bool {
        global $USER;
        // TODO. NEXTCLOUD: Quitar permisos al estudiante de editor.
        return true;
    }


}
