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
 * Admin settings of the NextCloud submission plugin.
 *
 * Grouped in the order a site is set up: connect first, then say where the
 * documents live, then how they behave and how they look.
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use assignsubmission_tipnc\editor\viewmode;
use assignsubmission_tipnc\hook_callbacks;
use assignsubmission_tipnc\models\cleanup;

$logurl = new moodle_url('/mod/assign/submission/tipnc/view_errors.php');

// The log is a page of its own in the tree: that way it is found by
// browsing, without going into the settings first or knowing the URL. It
// is not secured with admin_externalpage_setup() because this file is only
// included when the full tree is loaded, and that call loads it reduced:
// the page checks the capability on its own.
if (isset($ADMIN) && $ADMIN instanceof part_of_admin_tree) {
    $ADMIN->add('assignsubmissionplugins', new admin_externalpage(
        'assignsubmission_tipnc_log',
        new lang_string('log_title', 'assignsubmission_tipnc'),
        $logurl,
        'assignsubmission/tipnc:view_errors'
    ));
}

// Connection with NextCloud.

$settings->add(new admin_setting_heading(
    'assignsubmission_tipnc/section_connection',
    new lang_string('section_connection', 'assignsubmission_tipnc'),
    new lang_string('section_connection_desc', 'assignsubmission_tipnc')
));

$settings->add(new admin_setting_configtext(
    'assignsubmission_tipnc/url',
    new lang_string('url', 'assignsubmission_tipnc'),
    new lang_string('url_help', 'assignsubmission_tipnc'),
    ''
));

$settings->add(new admin_setting_configtext(
    'assignsubmission_tipnc/host',
    new lang_string('host', 'assignsubmission_tipnc'),
    new lang_string('host_help', 'assignsubmission_tipnc'),
    ''
));

$settings->add(new admin_setting_configtext(
    'assignsubmission_tipnc/user',
    new lang_string('user', 'assignsubmission_tipnc'),
    new lang_string('user_help', 'assignsubmission_tipnc'),
    ''
));

$settings->add(new admin_setting_configpasswordunmask(
    'assignsubmission_tipnc/password',
    new lang_string('password', 'assignsubmission_tipnc'),
    new lang_string('password_help', 'assignsubmission_tipnc'),
    ''
));

// Documents.

$settings->add(new admin_setting_heading(
    'assignsubmission_tipnc/section_documents',
    new lang_string('section_documents', 'assignsubmission_tipnc'),
    new lang_string('section_documents_desc', 'assignsubmission_tipnc')
));

$settings->add(new admin_setting_configtext(
    'assignsubmission_tipnc/folder',
    new lang_string('folder', 'assignsubmission_tipnc'),
    new lang_string('folder_help', 'assignsubmission_tipnc'),
    'tasks'
));

$settings->add(new admin_setting_configtext(
    'assignsubmission_tipnc/template',
    new lang_string('template', 'assignsubmission_tipnc'),
    new lang_string('template_help', 'assignsubmission_tipnc'),
    'template.docx'
));

$settings->add(new admin_setting_configtext(
    'assignsubmission_tipnc/location',
    new lang_string('location', 'assignsubmission_tipnc'),
    new lang_string('location_help', 'assignsubmission_tipnc'),
    '/apps/onlyoffice/'
));

// In assignments.

$settings->add(new admin_setting_heading(
    'assignsubmission_tipnc/section_assignments',
    new lang_string('section_assignments', 'assignsubmission_tipnc'),
    new lang_string('section_assignments_desc', 'assignsubmission_tipnc')
));

$settings->add(new admin_setting_configcheckbox(
    'assignsubmission_tipnc/default',
    new lang_string('default', 'assignsubmission_tipnc'),
    new lang_string('default_help', 'assignsubmission_tipnc'),
    0
));

// The editor.

$settings->add(new admin_setting_heading(
    'assignsubmission_tipnc/section_editor',
    new lang_string('section_editor', 'assignsubmission_tipnc'),
    new lang_string('section_editor_desc', 'assignsubmission_tipnc')
));

$settings->add(new admin_setting_configselect(
    'assignsubmission_tipnc/viewmode',
    new lang_string('viewmode', 'assignsubmission_tipnc'),
    new lang_string('viewmode_help', 'assignsubmission_tipnc'),
    viewmode::NEXTCLOUD,
    [
        viewmode::EDITOR => new lang_string('viewmode_editor', 'assignsubmission_tipnc'),
        viewmode::NEXTCLOUD => new lang_string('viewmode_nextcloud', 'assignsubmission_tipnc'),
        viewmode::TAB => new lang_string('viewmode_tab', 'assignsubmission_tipnc'),
    ]
));

$settings->add(new admin_setting_configtext(
    'assignsubmission_tipnc/docserverurl',
    new lang_string('docserverurl', 'assignsubmission_tipnc'),
    new lang_string('docserverurl_help', 'assignsubmission_tipnc'),
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configpasswordunmask(
    'assignsubmission_tipnc/docserversecret',
    new lang_string('docserversecret', 'assignsubmission_tipnc'),
    new lang_string('docserversecret_help', 'assignsubmission_tipnc'),
    ''
));

$settings->add(new admin_setting_configcheckbox(
    'assignsubmission_tipnc/confirmsaved',
    new lang_string('confirmsaved', 'assignsubmission_tipnc'),
    new lang_string('confirmsaved_help', 'assignsubmission_tipnc'),
    1
));

// Without the embedded editor those two values are not used at all.
$settings->hide_if(
    'assignsubmission_tipnc/docserverurl',
    'assignsubmission_tipnc/viewmode',
    'neq',
    viewmode::EDITOR
);
$settings->hide_if(
    'assignsubmission_tipnc/docserversecret',
    'assignsubmission_tipnc/viewmode',
    'neq',
    viewmode::EDITOR
);

// With the embedded editor, Moodle forces the save before freezing the copy:
// asking the student to confirm on top of that adds nothing.
$settings->hide_if(
    'assignsubmission_tipnc/confirmsaved',
    'assignsubmission_tipnc/viewmode',
    'eq',
    viewmode::EDITOR
);


// Access to the documents.

$settings->add(new admin_setting_heading(
    'assignsubmission_tipnc/section_access',
    new lang_string('section_access', 'assignsubmission_tipnc'),
    new lang_string('section_access_desc', 'assignsubmission_tipnc')
));

$settings->add(new admin_setting_configselect(
    'assignsubmission_tipnc/shareexpiry',
    new lang_string('shareexpiry', 'assignsubmission_tipnc'),
    new lang_string('shareexpiry_help', 'assignsubmission_tipnc'),
    '90',
    [
        '0' => new lang_string('shareexpiry_never', 'assignsubmission_tipnc'),
        '30' => new lang_string('numdays', 'core', 30),
        '90' => new lang_string('numdays', 'core', 90),
        '180' => new lang_string('numdays', 'core', 180),
        '365' => new lang_string('numdays', 'core', 365),
    ]
));

// When deleting.

$settings->add(new admin_setting_heading(
    'assignsubmission_tipnc/section_delete',
    new lang_string('section_delete', 'assignsubmission_tipnc'),
    new lang_string('section_delete_desc', 'assignsubmission_tipnc')
));

$settings->add(new admin_setting_configselect(
    'assignsubmission_tipnc/ondeletesubmission',
    new lang_string('ondeletesubmission', 'assignsubmission_tipnc'),
    new lang_string('ondeletesubmission_help', 'assignsubmission_tipnc'),
    cleanup::KEEP,
    [
        cleanup::KEEP => new lang_string('ondelete_keep', 'assignsubmission_tipnc'),
        cleanup::DROP_FROZEN => new lang_string('ondelete_frozen', 'assignsubmission_tipnc'),
        cleanup::DROP_ALL => new lang_string('ondelete_all', 'assignsubmission_tipnc'),
    ]
));

$settings->add(new admin_setting_configselect(
    'assignsubmission_tipnc/ondeleteassign',
    new lang_string('ondeleteassign', 'assignsubmission_tipnc'),
    new lang_string('ondeleteassign_help', 'assignsubmission_tipnc'),
    cleanup::ASSIGN_KEEP,
    [
        cleanup::ASSIGN_KEEP => new lang_string('ondeleteassign_keep', 'assignsubmission_tipnc'),
        cleanup::ASSIGN_DELETE => new lang_string('ondeleteassign_delete', 'assignsubmission_tipnc'),
    ]
));

// How the brief is shown.

$settings->add(new admin_setting_heading(
    'assignsubmission_tipnc/section_display',
    new lang_string('section_display', 'assignsubmission_tipnc'),
    new lang_string('section_display_desc', 'assignsubmission_tipnc')
));

$settings->add(new admin_setting_configselect(
    'assignsubmission_tipnc/placement',
    new lang_string('placement', 'assignsubmission_tipnc'),
    new lang_string('placement_help', 'assignsubmission_tipnc'),
    hook_callbacks::PLACE_HEADER,
    [
        hook_callbacks::PLACE_HEADER => new lang_string('placement_header', 'assignsubmission_tipnc'),
        hook_callbacks::PLACE_MAIN => new lang_string('placement_main', 'assignsubmission_tipnc'),
        hook_callbacks::PLACE_SELECTOR => new lang_string('placement_selector', 'assignsubmission_tipnc'),
    ]
));

$settings->add(new admin_setting_configtext(
    'assignsubmission_tipnc/placementselector',
    new lang_string('placementselector', 'assignsubmission_tipnc'),
    new lang_string('placementselector_help', 'assignsubmission_tipnc'),
    '',
    PARAM_RAW_TRIMMED
));

// The custom destination only makes sense with the placement that uses it.
$settings->hide_if(
    'assignsubmission_tipnc/placementselector',
    'assignsubmission_tipnc/placement',
    'neq',
    hook_callbacks::PLACE_SELECTOR
);

$settings->add(new admin_setting_configselect(
    'assignsubmission_tipnc/pagewidth',
    new lang_string('pagewidth', 'assignsubmission_tipnc'),
    new lang_string('pagewidth_help', 'assignsubmission_tipnc'),
    hook_callbacks::WIDTH_THEME,
    [
        hook_callbacks::WIDTH_THEME => new lang_string('pagewidth_theme', 'assignsubmission_tipnc'),
        '1200' => new lang_string('pagewidth_1200', 'assignsubmission_tipnc'),
        '1400' => new lang_string('pagewidth_1400', 'assignsubmission_tipnc'),
        '1600' => new lang_string('pagewidth_1600', 'assignsubmission_tipnc'),
        hook_callbacks::WIDTH_FULL => new lang_string('pagewidth_full', 'assignsubmission_tipnc'),
    ]
));

$settings->add(new admin_setting_configselect(
    'assignsubmission_tipnc/frameheight',
    new lang_string('frameheight', 'assignsubmission_tipnc'),
    new lang_string('frameheight_help', 'assignsubmission_tipnc'),
    '560',
    [
        '420' => new lang_string('frameheight_420', 'assignsubmission_tipnc'),
        '560' => new lang_string('frameheight_560', 'assignsubmission_tipnc'),
        '700' => new lang_string('frameheight_700', 'assignsubmission_tipnc'),
        '860' => new lang_string('frameheight_860', 'assignsubmission_tipnc'),
    ]
));

// Incident log.

$settings->add(new admin_setting_heading(
    'assignsubmission_tipnc/section_log',
    new lang_string('section_log', 'assignsubmission_tipnc'),
    new lang_string('section_log_desc', 'assignsubmission_tipnc')
));

$settings->add(new admin_setting_description(
    'assignsubmission_tipnc/desc',
    new lang_string('log_title', 'assignsubmission_tipnc'),
    html_writer::link(
        $logurl,
        get_string('log_open', 'assignsubmission_tipnc'),
        ['class' => 'btn btn-secondary']
    )
));

$settings->add(new admin_setting_configtext(
    'assignsubmission_tipnc/logretention',
    new lang_string('logretention', 'assignsubmission_tipnc'),
    new lang_string('logretention_help', 'assignsubmission_tipnc'),
    90,
    PARAM_INT
));
