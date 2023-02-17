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
 * This file defines the admin settings for this plugin
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG, $ADMIN;

$settings->add(new admin_setting_description('assignsubmission_tipnc/desc',
    new lang_string('error_log_desc', 'assignsubmission_tipnc'),
    '<a class="btn btn-primary" href="'. $CFG->wwwroot . '/mod/assign/submission/tipnc/view_errors.php' .
    '" target="_blank">' . get_string('tipnc:view_errors', 'assignsubmission_tipnc') . '</a></br><hr>'));

$settings->add(new admin_setting_configtext('assignsubmission_tipnc/host',
    new lang_string('host', 'assignsubmission_tipnc'),
    new lang_string('host_help', 'assignsubmission_tipnc'), 'dd-apps-nextcloud-nginx'));

$settings->add(new admin_setting_configtext('assignsubmission_tipnc/url',
    get_string('url', 'assignsubmission_tipnc'),
    get_string('url_help', 'assignsubmission_tipnc'), 'https://nextcloud.dd.3ip.eu'));

$settings->add(new admin_setting_configtext('assignsubmission_tipnc/user',
    new lang_string('user', 'assignsubmission_tipnc'),
    '', ''));

$settings->add(new admin_setting_configpasswordunmask('assignsubmission_tipnc/password',
    new lang_string('password', 'assignsubmission_tipnc'),
    '', ''));

$settings->add(new admin_setting_configtext('assignsubmission_tipnc/folder',
    new lang_string('folder', 'assignsubmission_tipnc'),
    new lang_string('folder_help', 'assignsubmission_tipnc'), 'tasks'));

$settings->add(new admin_setting_configtext('assignsubmission_tipnc/template',
    new lang_string('template', 'assignsubmission_tipnc'),
    new lang_string('template_help', 'assignsubmission_tipnc'), 'template.docx'));

$settings->add(new admin_setting_configtext('assignsubmission_tipnc/location',
    new lang_string('location', 'assignsubmission_tipnc'),
    new lang_string('location_help', 'assignsubmission_tipnc'), '/apps/onlyoffice/'));


