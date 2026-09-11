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
 * What is shown while the documents cannot be reached.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\output;

use coding_exception;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * What is shown while the documents cannot be reached.
 *
 * The worst answer to «the service is down» is an empty box: whoever has work to
 * hand in reads it as their own fault, or as the assignment being broken, and
 * has no idea whether to wait or to worry. So this says what is happening, who
 * it is happening to, and that nothing written is lost.
 *
 * What it says depends on who is reading. Students get a plain sentence and a
 * reason to come back later; whoever marks gets the same plus since when and
 * where to look, because they are the ones who can escalate it.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unavailable implements renderable, templatable {
    /** @var string This person has no account in the document service. */
    public const NO_ACCOUNT = 'noaccount';

    /** @var string The submission says it was handed in, but no document was frozen. */
    public const NO_DOCUMENT = 'nodocument';

    /** @var string Nobody has opened the document of this submission yet. */
    public const NOT_STARTED = 'notstarted';

    /**
     * Constructor.
     *
     * @param int             $since       When it stopped answering, 0 when unknown.
     * @param bool            $candiagnose Whether this person can act on the cause.
     * @param moodle_url|null $logurl      Where the incidents are, for those who can.
     * @param string          $reason      Which of the reasons it is: empty for the
     *                                     service being down, NO_ACCOUNT or
     *                                     NO_DOCUMENT for the ones that do not pass
     *                                     on their own.
     */
    public function __construct(
        /** @var int When it stopped answering, 0 when unknown. */
        private readonly int $since = 0,
        /** @var bool Whether this person can act on the cause. */
        private readonly bool $candiagnose = false,
        /** @var ?moodle_url Where the incidents are, for those who can. */
        private readonly ?moodle_url $logurl = null,
        /** @var string Which of the reasons it is: empty for the. */
        private readonly string $reason = ''
    ) {
    }

    /**
     * Export for template.
     *
     * @param  renderer_base $output The renderer.
     * @return array The data of the notice.
     * @throws coding_exception If a language string is missing.
     */
    public function export_for_template(renderer_base $output): array {
        $since = $this->since > 0
            ? userdate($this->since, get_string('strftimetime', 'core_langconfig'))
            : '';

        // Telling somebody with no account, or somebody looking at a submission
        // that never came to exist, to "try again in a few minutes" would be
        // sending them to wait for something that will not happen on its own.
        if ($this->reason !== '') {
            return [
                'title' => get_string($this->reason . '_title', 'assignsubmission_tipnc'),
                'message' => get_string($this->reason . '_message', 'assignsubmission_tipnc'),
                'candiagnose' => $this->candiagnose,
                'detail' => $this->candiagnose
                    ? get_string($this->reason . '_detail', 'assignsubmission_tipnc')
                    : '',
                'logurl' => $this->logurl?->out(false),
                'haslog' => $this->candiagnose && $this->logurl !== null,
                'logtext' => get_string('unavailable_log', 'assignsubmission_tipnc'),
            ];
        }

        return [
            'title' => get_string('unavailable_title', 'assignsubmission_tipnc'),
            'message' => get_string('unavailable_student', 'assignsubmission_tipnc'),
            'candiagnose' => $this->candiagnose,
            'detail' => $since !== ''
                ? get_string('unavailable_since', 'assignsubmission_tipnc', $since)
                : get_string('unavailable_teacher', 'assignsubmission_tipnc'),
            'logurl' => $this->logurl?->out(false),
            'haslog' => $this->candiagnose && $this->logurl !== null,
            'logtext' => get_string('unavailable_log', 'assignsubmission_tipnc'),
        ];
    }
}
