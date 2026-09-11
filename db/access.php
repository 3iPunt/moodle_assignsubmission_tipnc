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
 * Capability definitions for this module.
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$capabilities = [

    // El registro de incidencias enseña rutas de documentos y nombres de cuenta
    // de todo el sitio, así que de serie solo lo ve quien administra.
    //
    // Los demás papeles no se declaran: no tenerla es no tenerla, y así un sitio
    // que quiera dársela a su profesorado puede hacerlo con una anulación.
    // CAP_PROHIBIT lo impediría para siempre, que es más de lo que aquí hace
    // falta —está pensado para lo que nunca debe permitirse—.
    'assignsubmission/tipnc:view_errors' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
