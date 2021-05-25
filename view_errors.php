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
 * View Errors Logs.
 *
 * @package    assignsubmission_tipnc
 * @subpackage tresipuntfactory
 * @copyright  2021 Tresipunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_tipnc\output\registry_page;
use assignsubmission_tipnc\output\view_errors_page;

require_once('../../../../config.php');

global $PAGE, $OUTPUT;

require_login();

$has_capability = has_capability('assignsubmission/tipnc:view_errors',  context_system::instance());

$title = get_string('tipnc:view_errors', 'assignsubmission_tipnc');

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/assign/submission/tipnc/view_errors.php');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
if ($has_capability) {
    $page = new view_errors_page();
    $output = $PAGE->get_renderer('assignsubmission_tipnc');
    echo $output->render($page);
} else {
    throw new moodle_exception(
        get_string('tipnc:view_errors_permission', 'local_tresipuntfactory')
    );
}
echo $OUTPUT->footer();

