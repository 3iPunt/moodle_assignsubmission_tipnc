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
 * How a document is opened.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\editor;

use dml_exception;

/**
 * How a document is opened, and what each way needs to work.
 *
 * The three ways coexist on purpose: a link asks nothing of the infrastructure
 * and works everywhere, a site that already integrated the editor on the
 * NextCloud side keeps working as it did, and letting Moodle embed the editor
 * gives something neither of the others can —the submission holding what was written—.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class viewmode {

    /** @var string Moodle embeds the Document Server and signs the access. */
    public const EDITOR = 'editor';

    /** @var string The NextCloud page in a frame: needs a reverse proxy and the same domain. */
    public const NEXTCLOUD = 'nextcloud';

    /** @var string A link and nothing else: needs nothing, and leaves Moodle. */
    public const TAB = 'tab';

    /**
     * Whether the document is shown inside the page at all.
     *
     * @return bool False when all there is is a link.
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function embeds(): bool {
        return self::current() !== self::TAB;
    }

    /**
     * The way the site has chosen, if it can actually be used.
     *
     * Choosing the embedded editor without an address or a secret would leave a
     * blank frame; better to fall back to what does work and say so in the log.
     *
     * @return string One of the constants.
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function current(): string {
        $mode = (string) get_config('assignsubmission_tipnc', 'viewmode');

        // Se cae al enlace, no al marco: embeber la página de NextCloud exige un
        // proxy inverso y el mismo dominio, así que como respaldo automático es
        // justo el que más papeletas tiene de no funcionar.
        if ($mode === self::EDITOR && !token::is_configured()) {
            return self::TAB;
        }

        return in_array($mode, [self::EDITOR, self::NEXTCLOUD, self::TAB], true)
            ? $mode
            : self::TAB;
    }
}
