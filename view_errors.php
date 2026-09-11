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
 * Incident log of the calls made to NextCloud.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

global $PAGE, $OUTPUT;

use assignsubmission_tipnc\api\health;
use assignsubmission_tipnc\models\incidents;
use assignsubmission_tipnc\output\incident_list;
use assignsubmission_tipnc\output\incident_log_page;

require_login();
$context = context_system::instance();
require_capability('assignsubmission/tipnc:view_errors', $context);

$title = get_string('log_title', 'assignsubmission_tipnc');

$PAGE->set_context($context);
$PAGE->set_url('/mod/assign/submission/tipnc/view_errors.php');
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$page = optional_param('page', 0, PARAM_INT);

$filters = array_filter([
    'severity' => optional_param('severity', '', PARAM_ALPHA),
    'assignment' => optional_param('assignment', 0, PARAM_INT),
    'userid' => optional_param('userid', 0, PARAM_INT),
    'search' => optional_param('search', '', PARAM_TEXT),
]);

$model = new incidents();
$rows = $model->decorate($model->search($filters, $page));
$connection = (new health())->check();

$list = new incident_list(
    $rows,
    $model->count($filters),
    $page,
    incidents::PER_PAGE,
    $connection->ok,
    !empty($filters)
);

$view = new incident_log_page($list, $model->counters(), $model->common_cause(), $connection, $filters);

$renderer = $PAGE->get_renderer('assignsubmission_tipnc');

echo $OUTPUT->header();
echo $renderer->render_incident_log_page($view);
echo $OUTPUT->footer();
