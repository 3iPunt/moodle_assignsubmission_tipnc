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
 * This file contains the definition for the library class for file submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_tipnc\api\document;
use assignsubmission_tipnc\api\nextcloud;
use assignsubmission_tipnc\api\error as error_log;
use assignsubmission_tipnc\output\url_submission_component;
use assignsubmission_tipnc\output\view_submission_component;
use assignsubmission_tipnc\tipnc;
use assignsubmission_tipnc\tipnc_enun;
use assignsubmission_tipnc\tipnc_error;
use assignsubmission_tipnc\tipnc_open;

defined('MOODLE_INTERNAL') || die();

/**
 * Library class for file submission plugin extending submission plugin base class
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_tipnc extends assign_submission_plugin {

    /**
     * Get the name of the file submission plugin
     * @return string
     * @throws coding_exception
     */
    public function get_name(): string {
        return get_string('pluginname', 'assignsubmission_tipnc');
    }

    /**
     * Get the default setting for file submission plugin
     *
     * @param MoodleQuickForm $mform
     */
    public function get_settings(MoodleQuickForm $mform) {}

    /**
     * Save the settings for file submission plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data): bool {
        return true;
    }

    /**
     * Add elements to submission form [ OPEN ]
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     * @throws coding_exception|dml_exception
     * @throws moodle_exception
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data): bool {
        global $PAGE;
        $tipnc_enun = tipnc_enun::get($submission->assignment);
        if (!empty($tipnc_enun->ncid)) {
            $tipnc_open = tipnc_open::get($submission->id);
            if ($tipnc_open) {
                if (empty($tipnc_open->ncid)) {
                    tipnc_error::log(
                        'get_form_elements',
                        new error_log(1000, 'The NextCloud ID could not be retrieved'),
                        $submission->assignment, $submission->id);
                    return false;
                } else {
                    $ncid = $tipnc_open->ncid;
                }
            } else {
                $nextcloud = new nextcloud($submission->assignment);
                $response = $nextcloud->student_open($submission);
                if ($response->success) {
                    $ncid = $response->data;
                } else {
                    return false;
                }
            }
            $url_open = document::get_url($ncid);
            $output = $PAGE->get_renderer('assignsubmission_tipnc');
            $component = new view_submission_component($url_open, document::MODE_OPEN);
            $render = $output->render($component);
            $mform->addElement('static', 'iframe', '', $render);
            return true;
        } else {
            tipnc_error::log(
                'get_form_elements',
                new error_log(1001, 'The Enunciate does not exist'),
                $submission->assignment, $submission->id);
            return false;
        }
    }

    /**
     * Save the files and trigger plagiarism plugin, if enabled,
     * to scan the uploaded files via events trigger
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function save(stdClass $submission, stdClass $data): bool {
        $tipnc_enun = tipnc_enun::get($submission->assignment);
        if (!$tipnc_enun) {
            tipnc_error::log(
                'save',
                new error_log(1100, 'The Enunciate does not exist'),
                $submission->assignment, $submission->id);
            return false;
        }
        $tipnc_open = tipnc_open::get($submission->id);
        if (!$tipnc_open) {
            tipnc_error::log(
                'save',
                new error_log(1101, 'The Open Submission does not exist'),
                $submission->assignment, $submission->id);
            return false;
        }
        switch ($submission->status) {
            case ASSIGN_SUBMISSION_STATUS_DRAFT:
            case ASSIGN_SUBMISSION_STATUS_REOPENED:
            case ASSIGN_SUBMISSION_STATUS_NEW:
                return true;
            case ASSIGN_SUBMISSION_STATUS_SUBMITTED:
                $nextcloud = new nextcloud($submission->assignment);
                $response = $nextcloud->student_submit($submission, $tipnc_enun->userid);
                if ($response->success) {
                    return true;
                } else {
                    print_error($response->error->message);
                    return false;
                }
            default:
                tipnc_error::log(
                    'save',
                    new error_log(1102, 'Assign status unknown'),
                    $submission->assignment, $submission->id);
                return false;
        }
    }

    /**
     * Remove files from this submission.
     *
     * @param stdClass $submission
     * @return bool
     * @throws moodle_exception
     */
    public function remove(stdClass $submission): bool {
        $submissionid = $submission ? $submission->id : 0;
        if ($submissionid) {
            try {
                tipnc::delete_by_submissionid($submission->id, $submission->assignment);
                tipnc_open::delete_by_submissionid($submission->id, $submission->assignment);
            } catch (moodle_exception $e) {
                tipnc_error::log(
                    'remove',
                    new error_log(1200, $e->getMessage()),
                    $submission->assignment, $submission->id);
                return false;
            }
        }
        return true;
    }

    /**
     * Produce a list of files suitable for export that represent this feedback or submission
     *
     * @param stdClass $submission The submission
     * @param stdClass $user The user record - unused
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user): array {
        return [];
    }

    /**
     * Display the list of files in the submission status table
     *
     * @param stdClass $submission
     * @param bool $showviewlink Set this to true if the list of files is long
     * @return string
     * @throws dml_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function view_summary(stdClass $submission, & $showviewlink): string {
        global $PAGE;
        $tipnc_enun = tipnc_enun::get($submission->assignment);
        if (!$tipnc_enun) {
            tipnc_error::log(
                'view_summary',
                new error_log(1300, 'The Enunciate does not exist'),
                $submission->assignment, $submission->id);
            return '';
        }
        $is_teacher = \assignsubmission_tipnc\assign::is_teacher($submission->assignment);

        if ($is_teacher) {
            switch ($submission->status) {
                case ASSIGN_SUBMISSION_STATUS_DRAFT:
                case ASSIGN_SUBMISSION_STATUS_REOPENED:
                case ASSIGN_SUBMISSION_STATUS_NEW:
                    $mode = document::MODE_ENUN;
                    $ncid = $tipnc_enun->ncid;
                    break;
                case ASSIGN_SUBMISSION_STATUS_SUBMITTED:
                    $mode = document::MODE_SUBMISSION;
                    $tipnc_sub = tipnc::get($submission->id);
                    $ncid = $tipnc_sub->ncid;
                    break;
                default:
                    if (is_null($submission->status)) {
                        $sub = \assignsubmission_tipnc\assign::get_submission($submission->id);
                        if ($sub->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                            $mode = document::MODE_SUBMISSION;
                            $tipnc_sub = tipnc::get($submission->id);
                            $ncid = $tipnc_sub->ncid;
                        } else {
                            $mode = document::MODE_ENUN;
                            $ncid = $tipnc_enun->ncid;
                        }
                    } else {
                        tipnc_error::log(
                            'view_summary',
                            new error_log(1301, 'Assign status unknown'),
                            $submission->assignment, $submission->id);
                        return '';
                    }
            }
        } else {
            $nextcloud = new nextcloud($submission->assignment);
            switch ($submission->status) {
                case ASSIGN_SUBMISSION_STATUS_DRAFT:
                case ASSIGN_SUBMISSION_STATUS_REOPENED:
                    $mode = document::MODE_OPEN;
                    $tipnc_open = tipnc_open::get($submission->id);
                    if (empty($tipnc_open->ncid)) {
                        $response = $nextcloud->student_open($submission);
                        if ($response->success) {
                            $ncid = $response->data;
                        } else {
                            print_error($response->error->message);
                            return '';
                        }
                    } else {
                        $ncid = $tipnc_open->ncid;
                    }
                    break;
                case ASSIGN_SUBMISSION_STATUS_NEW:
                    $mode = document::MODE_ENUN;
                    $ncid = $tipnc_enun->ncid;
                    $response = $nextcloud->student_view_summary();
                    if (!$response->success) {
                        print_error($response->error->message);
                        return '';
                    }
                    break;
                case ASSIGN_SUBMISSION_STATUS_SUBMITTED:
                    $mode = document::MODE_SUBMISSION;
                    $tipnc_sub = tipnc::get($submission->id);
                    $ncid = $tipnc_sub->ncid;
                    break;
                default:
                    tipnc_error::log(
                        'view_summary',
                        new error_log(1302, 'Assign status unknown'),
                        $submission->assignment, $submission->id);
                    return '';
            }
        }

        if (empty($ncid)) {
            tipnc_error::log(
                'view_summary',
                new error_log(1303, 'The NextCloud ID could not be retrieved'),
                $submission->assignment, $submission->id);
            return '';
        }

        $url = document::get_url($ncid);
        $pagesview = ['mod-assign-gradingpanel', 'mod-assign-view'];
        $output = $PAGE->get_renderer('assignsubmission_tipnc');

        if (in_array($PAGE->pagetype, $pagesview)) {
            $component = new view_submission_component($url, $mode);
            $render = $output->render($component);
        } else {
            $component = new url_submission_component($url, $mode);
            $render = $output->render($component);
        }
        return $render;
    }

    /**
     * No full submission view - the summary contains the list of files and that is the whole submission
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission): string {
        return 'TIPNC view';
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type
     * and version.
     *
     * @param string $type
     * @param int $version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version): bool {
        return false;
    }


    /**
     * Upgrade the settings from the old assignment
     * to the new plugin based one
     *
     * @param context $oldcontext - the old assignment context
     * @param stdClass $oldassignment - the old assignment data record
     * @param string $log record log events here
     * @return bool Was it a success? (false will trigger rollback)
     */
    public function upgrade_settings(context $oldcontext, stdClass $oldassignment, & $log): bool {
        return true;
    }

    /**
     * Upgrade the submission from the old assignment to the new one
     *
     * @param context $oldcontext The context of the old assignment
     * @param stdClass $oldassignment The data record for the old oldassignment
     * @param stdClass $oldsubmission The data record for the old submission
     * @param stdClass $submission The data record for the new submission
     * @param string $log Record upgrade messages in the log
     * @return bool true or false - false will trigger a rollback
     */
    public function upgrade(context $oldcontext, stdClass $oldassignment,
                            stdClass $oldsubmission,stdClass $submission,& $log): bool {
        return true;
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     * @throws moodle_exception
     */
    public function delete_instance(): bool {
        try {
            tipnc::delete($this->assignment->get_instance()->id);
            tipnc_enun::delete($this->assignment->get_instance()->id);
            tipnc_open::delete($this->assignment->get_instance()->id);
            return true;
        } catch (moodle_exception $e) {
            tipnc_error::log(
                'delete_instance',
                new error_log(1400, $e->getMessage()),
                $this->assignment->get_instance()->id);
            print_error($e->getMessage());
            return false;
        }
    }

    /**
     * Formatting for log info
     *
     * @param stdClass $submission The submission
     * @return string
     */
    public function format_for_log(stdClass $submission): string {
        return 'format_log';
    }

    /**
     * Return true if there are no submission files
     * @param stdClass $submission
     * @return bool
     */
    public function is_empty(stdClass $submission): bool {
        return false;
    }

    /**
     * Determine if a submission is empty
     *
     * This is distinct from is_empty in that it is intended to be used to
     * determine if a submission made before saving is empty.
     *
     * @param stdClass $data The submission data
     * @return bool
     */
    public function submission_is_empty(stdClass $data): bool {
        return false;
    }

    /**
     * Copy the student's submission from a previous submission. Used when a student opts to base their resubmission
     * on the last submission.
     * @param stdClass $sourcesubmission
     * @param stdClass $destsubmission
     * @return bool
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission): bool {
        return true;
    }

    /**
     * Determine if the plugin allows image file conversion
     * @return bool
     */
    public function allow_image_conversion(): bool {
        return true;
    }
}
