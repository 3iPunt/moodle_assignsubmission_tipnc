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
 * Hook callbacks of the plugin.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// mod_assign no ofrece ningún hueco donde un subplugin de entrega pueda pintar en
// la página de la tarea: la caja de estado exige mod/assign:viewownsubmissionsummary,
// que solo tiene el alumnado. Sin estos callbacks, nadie más ve el enunciado.
//
// Se declaran los dos destinos y el ajuste «placement» decide cuál actúa:
// before_http_headers es la única ventana a la cabecera de la actividad —después
// de que mod_assign fije la descripción y antes de que el tema la exporte—, y el
// de la región principal funciona en cualquier tema.
$callbacks = [
    [
        'hook' => core\hook\output\before_http_headers::class,
        'callback' => 'assignsubmission_tipnc\hook_callbacks::add_enunciate_to_activity_header',
    ],
    [
        'hook' => core\hook\output\after_standard_main_region_html_generation::class,
        'callback' => 'assignsubmission_tipnc\hook_callbacks::add_enunciate_to_main_region',
    ],
    [
        'hook' => core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => 'assignsubmission_tipnc\hook_callbacks::widen_assign_page',
    ],
    [
        'hook' => core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => 'assignsubmission_tipnc\hook_callbacks::warn_before_removing_submission',
    ],
    [
        'hook' => core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => 'assignsubmission_tipnc\hook_callbacks::confirm_document_saved',
    ],
];
