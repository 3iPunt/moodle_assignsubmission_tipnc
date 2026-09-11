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
 * Holds back the submit button until the student says the document is saved.
 *
 * The office editor saves in its own time, so submitting right after writing
 * freezes the previous version. This does not detect that —nothing on this side
 * can— but it stops the submission being one click away from a mistake.
 *
 * @module     assignsubmission_tipnc/confirmsave
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    box: '[data-region="tipnc-confirmsave"]',
    check: '[data-action="tipnc-confirmsave"]',
    form: '#region-main form',
    submit: 'input[type="submit"], button[type="submit"]',
};

/**
 * Puts the confirmation in the form and ties the submit button to it.
 */
export const init = () => {
    const box = document.querySelector(SELECTORS.box);
    const form = document.querySelector(SELECTORS.form);
    if (!box || !form) {
        return;
    }

    const buttons = Array.from(form.querySelectorAll(SELECTORS.submit));
    if (!buttons.length) {
        // With no button to hold back, the notice stays put and is harmless.
        return;
    }

    const primary = buttons[0];

    // Above the button group and outside it: inside it would sit in the same
    // row as "Save changes" and "Cancel", as if it were another control.
    // The whole row, not the button: mform wraps each button in its own .fitem
    // and all of them in a flex container. Hanging off the .fitem left the
    // notice inside that row, in line with "Save changes" and "Cancel".
    const row = primary.closest('.d-flex') || primary.closest('.fitem, .form-group, fieldset')
        || primary.parentNode;
    row.parentNode.insertBefore(box, row);
    box.hidden = false;

    // The container is marked from here instead of guessing its structure in
    // styles.css: that way the rule targets what is there, not what we assume.
    row.classList.add('tipnc-buttons');

    const check = box.querySelector(SELECTORS.check);
    if (!check) {
        return;
    }

    const toggle = () => {
        primary.disabled = !check.checked;
    };

    check.addEventListener('change', toggle);
    toggle();
};
