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

// There is no gap in mod_assign where a submission subplugin can paint on the
// assignment page: the status box requires mod/assign:viewownsubmissionsummary,
// which only students have. Without these callbacks, nobody else sees the brief.
//
// Both destinations are declared and the "placement" setting decides which acts:
// before_http_headers is the only window into the activity header —after
// mod_assign sets the description and before the theme exports it— and the
// one on the main region works in any theme.
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
