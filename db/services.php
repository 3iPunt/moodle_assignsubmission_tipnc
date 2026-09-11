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
 * Web services of the plugin.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'assignsubmission_tipnc_search_incidents' => [
        'classname' => 'assignsubmission_tipnc\external\incidents_external',
        'methodname' => 'search',
        'description' => 'Incidents matching the filters of the incident log.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'assignsubmission/tipnc:view_errors',
    ],
    'assignsubmission_tipnc_get_incident' => [
        'classname' => 'assignsubmission_tipnc\external\incidents_external',
        'methodname' => 'detail',
        'description' => 'One incident of the log with its full trace.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'assignsubmission/tipnc:view_errors',
    ],
    'assignsubmission_tipnc_purge_log' => [
        'classname' => 'assignsubmission_tipnc\external\incidents_external',
        'methodname' => 'purge',
        'description' => 'Empties the incident log.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'assignsubmission/tipnc:view_errors',
    ],
    'assignsubmission_tipnc_filter_options' => [
        'classname' => 'assignsubmission_tipnc\external\incidents_external',
        'methodname' => 'options',
        'description' => 'Courses, assignments or users matching what was typed in a filter.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'assignsubmission/tipnc:view_errors',
    ],
];
