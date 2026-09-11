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
 * Takes an embedded document to full screen, with its bar.
 *
 * The bar goes too: it carries the notice that says the document cannot be
 * edited, and hiding it right when the document fills the screen would be the
 * worst moment to hide it.
 *
 * A document in a frame inside a page is small; reading or writing one deserves
 * the whole screen without leaving Moodle.
 *
 * @module     assignsubmission_tipnc/fullscreen
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    button: '[data-action="tipnc-fullscreen"]',
    // The button lives in the block header, not over the viewer: on top it
    // covered the editor toolbar.
    block: '[data-region="tipnc-enunciate"], [data-region="assignsubmission_tipnc"]',
    viewer: '[data-region="tipnc-viewer"]',
};

/** @var {Boolean} listening Whether the page level listener is already set. */
let listening = false;

/**
 * Listens for the full screen buttons of the page.
 *
 * One listener on the document covers every frame, and covers the ones that
 * arrive later —the grading panel loads its content by fragment—.
 */
export const init = () => {
    if (listening) {
        return;
    }
    listening = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest(SELECTORS.button);
        if (!button) {
            return;
        }

        const block = button.closest(SELECTORS.block);
        const viewer = block ? block.querySelector(SELECTORS.viewer) : null;
        if (!viewer || !viewer.requestFullscreen) {
            return;
        }

        if (document.fullscreenElement === viewer) {
            document.exitFullscreen();
            return;
        }

        // A refusal from the browser —permission denied, or the tab in the
        // background— leaves the document where it was, an acceptable outcome.
        viewer.requestFullscreen().catch(() => {
            return;
        });
    });
};
