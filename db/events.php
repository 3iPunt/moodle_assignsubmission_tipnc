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
 * Events the plugin listens to.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Los dos caminos por los que alguien deja de tener que ver con un curso. Ambos
// solo encolan: quitar el acceso documento a documento no cabe en el clic que
// desmatricula, y si algo falla la tarea adhoc se reintenta sola.
$observers = [
    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => '\assignsubmission_tipnc\observer::user_enrolment_deleted',
    ],
    [
        'eventname' => '\core\event\role_unassigned',
        'callback' => '\assignsubmission_tipnc\observer::role_unassigned',
    ],
];
