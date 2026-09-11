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
 * What the plugin tells the core about a submission.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc;

use advanced_testcase;
use mod_assign_test_generator;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');

/**
 * What the plugin tells the core about a submission.
 *
 * The core asks one question —is there anything in this submission?— and uses
 * the answer for three things: whether to show the box, whether to offer the
 * submit button, and whether to accept the submission at all. Answering «there
 * is always something», which is what it used to do, let people hand in
 * nothing. Answering wrongly the other way stops people handing in their work.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \assign_submission_tipnc
 */
final class locallib_test extends advanced_testcase {
    use mod_assign_test_generator;

    /** @var stdClass The course. */
    private stdClass $course;

    /** @var stdClass Somebody who hands work in. */
    private stdClass $student;

    /** @var \assign The assignment. */
    private $assign;

    /**
     * An assignment with this plugin on and one student enrolled.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        set_config('folder', 'tasks', 'assignsubmission_tipnc');
        set_config('template', 'template.docx', 'assignsubmission_tipnc');

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assign = $this->create_instance($this->course, [
            'assignsubmission_tipnc_enabled' => true,
        ]);
    }

    /**
     * The plugin of the assignment under test.
     *
     * @return \assign_submission_plugin The plugin.
     */
    private function plugin() {
        return $this->assign->get_plugin_by_type('assignsubmission', 'tipnc');
    }

    /**
     * Nobody opened the document, so there is nothing to hand in. Saying
     * otherwise is what let people submit work that did not exist.
     *
     * @return void
     */
    public function test_a_submission_with_no_document_is_empty(): void {
        $this->setUser($this->student);
        $submission = $this->assign->get_user_submission($this->student->id, true);

        $this->assertTrue($this->plugin()->is_empty($submission));
    }

    /**
     * Somebody who started writing has something, even before handing it in:
     * their draft is the work.
     *
     * @return void
     */
    public function test_a_submission_with_a_draft_is_not_empty(): void {
        global $DB;

        $this->setUser($this->student);
        $submission = $this->assign->get_user_submission($this->student->id, true);

        $DB->insert_record('assignsubmission_tipnc_open', (object) [
            'assignment' => $this->assign->get_instance()->id,
            'submission' => $submission->id,
            'ncid' => 10,
            'path' => 'tasks/open.docx',
        ]);

        $this->assertFalse($this->plugin()->is_empty($submission));
    }

    /**
     * A frozen copy is content too, which is what keeps a handed-in submission
     * visible after the draft is gone.
     *
     * @return void
     */
    public function test_a_submission_with_a_frozen_copy_is_not_empty(): void {
        global $DB;

        $this->setUser($this->student);
        $submission = $this->assign->get_user_submission($this->student->id, true);

        $DB->insert_record('assignsubmission_tipnc', (object) [
            'assignment' => $this->assign->get_instance()->id,
            'submission' => $submission->id,
            'ncid' => 11,
            'path' => 'tasks/subm.docx',
        ]);

        $this->assertFalse($this->plugin()->is_empty($submission));
    }

    /**
     * Something that is not a submission at all is empty, rather than an error.
     *
     * @return void
     */
    public function test_something_that_is_not_a_submission_is_empty(): void {
        $this->assertTrue($this->plugin()->is_empty((object) []));
    }

    /**
     * Before saving, the same question with the form data: somebody who never
     * opened the document is handing in nothing, and the core refuses it.
     *
     * @return void
     */
    public function test_saving_is_refused_when_there_is_no_document(): void {
        $this->setUser($this->student);
        $this->assign->get_user_submission($this->student->id, true);

        $this->assertTrue($this->plugin()->submission_is_empty(new stdClass()));
    }

    /**
     * And accepted once there is a draft to hand in.
     *
     * @return void
     */
    public function test_saving_is_allowed_once_there_is_a_draft(): void {
        global $DB;

        $this->setUser($this->student);
        $submission = $this->assign->get_user_submission($this->student->id, true);

        $DB->insert_record('assignsubmission_tipnc_open', (object) [
            'assignment' => $this->assign->get_instance()->id,
            'submission' => $submission->id,
            'ncid' => 12,
            'path' => 'tasks/open.docx',
        ]);

        $this->assertFalse($this->plugin()->submission_is_empty(new stdClass()));
    }

    /**
     * The plugin has no full view of its own: everything it shows fits in the
     * summary. It used to answer this with a debugging message and the words
     * «TIPNC view», which reached whoever opened it.
     *
     * @return void
     */
    public function test_there_is_no_full_view(): void {
        $this->setUser($this->student);
        $submission = $this->assign->get_user_submission($this->student->id, true);

        $this->assertSame('', $this->plugin()->view($submission));
    }
}
