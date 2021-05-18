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
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
     * Get tipnc submission information from the database
     *
     * @param int $submissionid
     * @return mixed
     * @throws dml_exception
     */
    private function get_tipnc_submission(int $submissionid) {
        global $DB;
        return $DB->get_record('assignsubmission_tipnc', array('submission'=>$submissionid));
    }

    /**
     * Get the default setting for file submission plugin
     *
     * @param MoodleQuickForm $mform
     */
    public function get_settings(MoodleQuickForm $mform) {

    }

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
     * Add elements to submission form
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data): bool {
        return true;
    }


    /**
     * Save the files and trigger plagiarism plugin, if enabled,
     * to scan the uploaded files via events trigger
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     * @throws dml_exception
     */
    public function save(stdClass $submission, stdClass $data): bool {
        global $DB;
        $tipncsubmission = $this->get_tipnc_submission($submission->id);
        if ($tipncsubmission) {
            $tipncsubmission->onlyoffice = 1077;
            $DB->update_record('assignsubmission_tipnc', $tipncsubmission);
        } else {
            $tipnc = new stdClass();
            $tipnc->assignment = $submission->assignment;
            $tipnc->submission = $submission->id;
            $tipnc->onlyoffice = 1077;
            $DB->insert_record('assignsubmission_tipnc', $tipnc);
        }

        return true;
    }

    /**
     * Remove files from this submission.
     *
     * @param stdClass $submission The submission
     * @return boolean
     */
    public function remove(stdClass $submission): bool {
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
        $result = array();
        return $result;
    }

    /**
     * Display the list of files  in the submission status table
     *
     * @param stdClass $submission
     * @param bool $showviewlink Set this to true if the list of files is long
     * @return string
     * @throws dml_exception
     */
    public function view_summary(stdClass $submission, & $showviewlink): string {
        $tipnc = $this->get_tipnc_submission($submission->id);
        return '<iframe src="https://nextcloud.montseny.digitaldemocratic.net/apps/onlyoffice/1077" width="100%" height="100%" align="top" frameborder="0" name="frameEditor" allowfullscreen="" onmousewheel="" allow="autoplay; camera; microphone; display-capture"></iframe>';
        return 'TIPNC summary - ' . $tipnc->onlyoffice;
    }

    /**
     * No full submission view - the summary contains the list of files and that is the whole submission
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
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
     * @throws dml_exception
     */
    public function delete_instance(): bool {
        global $DB;
        // Will throw exception on failure.
        $DB->delete_records('assignsubmission_tipnc',
            array('assignment'=>$this->assignment->get_instance()->id));

        return true;
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
    public function submission_is_empty(stdClass $data) {
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
