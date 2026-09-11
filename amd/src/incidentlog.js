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
 * Filters and detail of the incident log.
 *
 * @module     assignsubmission_tipnc/incidentlog
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import * as Autocomplete from 'core/form-autocomplete';
import {get_string as getString} from 'core/str';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';

const SELECTORS = {
    listing: '[data-region="tipnc-incident-listing"]',
    drawer: '[data-region="tipnc-incident-detail"]',
    drawerBody: '[data-region="tipnc-detail-body"]',
    clear: '[data-action="clear"]',
};

/** @type {Object} Filters currently applied. */
let filters = {severity: '', course: 0, assign: 0, user: 0, range: 0, page: 0};

/** @type {Element|null} The element that opened the panel, to give the focus back. */
let opener = null;

/**
 * Reads the incidents matching the filters and repaints the listing.
 *
 * @param {Element} root The screen.
 * @returns {Promise<void>}
 */
const reload = async(root) => {
    const listing = root.querySelector(SELECTORS.listing);
    // Se atenúa en lugar de vaciarse: la tabla no debe saltar mientras carga.
    listing.classList.add('tipnc-log__listing--busy');

    try {
        const response = await Ajax.call([{
            methodname: 'assignsubmission_tipnc_search_incidents',
            args: filters,
        }])[0];

        listing.innerHTML = response.html;
    } catch (error) {
        const message = await getString('log_loaderror', 'assignsubmission_tipnc');
        listing.innerHTML = `<div class="tipnc-log__empty"><p class="tipnc-log__empty-title">${message}</p></div>`;
        Notification.exception(error);
    } finally {
        listing.classList.remove('tipnc-log__listing--busy');
        updateClearButton(root);
    }
};

/**
 * Shows the button that clears the filters only when there is something to clear.
 *
 * @param {Element} root The screen.
 * @returns {void}
 */
const updateClearButton = (root) => {
    const active = filters.severity !== '' || filters.course || filters.assign
        || filters.user || filters.range;

    root.querySelectorAll(SELECTORS.clear).forEach((button) => {
        button.hidden = !active;
    });
};

/**
 * Marks the chosen option of a segmented group.
 *
 * @param {Element} root   The screen.
 * @param {string}  action The group, severity or range.
 * @param {string}  value  The chosen value.
 * @returns {void}
 */
const markSegment = (root, action, value) => {
    root.querySelectorAll(`[data-action="${action}"]`).forEach((button) => {
        const chosen = button.dataset.value === value;
        button.classList.toggle('tipnc-log__segment--active', chosen);
        button.setAttribute('aria-pressed', chosen ? 'true' : 'false');
    });
};

/**
 * Opens the detail panel of one incident.
 *
 * @param {Element} root The screen.
 * @param {number}  id   The incident.
 * @returns {Promise<void>}
 */
const openDetail = async(root, id) => {
    const drawer = root.querySelector(SELECTORS.drawer);
    const body = root.querySelector(SELECTORS.drawerBody);

    drawer.hidden = false;
    body.innerHTML = '';

    try {
        const response = await Ajax.call([{
            methodname: 'assignsubmission_tipnc_get_incident',
            args: {id: id},
        }])[0];

        body.innerHTML = response.html;
        const close = body.querySelector('[data-action="close"]');
        if (close) {
            close.focus();
        }
    } catch (error) {
        drawer.hidden = true;
        Notification.exception(error);
    }
};

/**
 * Closes the detail panel and gives the focus back to where it came from.
 *
 * @param {Element} root The screen.
 * @returns {void}
 */
const closeDetail = (root) => {
    const drawer = root.querySelector(SELECTORS.drawer);
    drawer.hidden = true;
    if (opener) {
        opener.focus();
        opener = null;
    }
};

/**
 * Copies the diagnosis of an incident, ready to be pasted into a ticket.
 *
 * @param {Element} button The button holding the text.
 * @returns {Promise<void>}
 */
const copyDiagnosis = async(button) => {
    try {
        await navigator.clipboard.writeText(button.dataset.diagnosis);
        button.textContent = await getString('detail_copied', 'assignsubmission_tipnc');
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Asks for confirmation and empties the log.
 *
 * Borrar es irreversible: la confirmación dice cuántos registros se pierden.
 *
 * @param {Element} trigger The button, which carries the count.
 * @returns {Promise<void>}
 */
const confirmPurge = async(trigger) => {
    const total = trigger.dataset.total;

    const modal = await ModalSaveCancel.create({
        title: await getString('purge_title', 'assignsubmission_tipnc'),
        body: Templates.render('assignsubmission_tipnc/purge_confirm', {total: total}),
        buttons: {save: await getString('log_purge', 'assignsubmission_tipnc')},
    });

    modal.getRoot().addClass('tipnc-modal');

    modal.getRoot().on(ModalEvents.save, async() => {
        try {
            await Ajax.call([{
                methodname: 'assignsubmission_tipnc_purge_log',
                args: {},
            }])[0];

            window.location.reload();
        } catch (error) {
            Notification.exception(error);
        }
    });

    await modal.show();
};

/**
 * Wires every control of the screen.
 *
 * @param {Element} root The screen.
 * @returns {void}
 */
export const init = (root) => {
    if (!root) {
        return;
    }

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger || !root.contains(trigger)) {
            return;
        }

        const action = trigger.dataset.action;

        if (action === 'severity' || action === 'range') {
            event.preventDefault();
            filters[action === 'severity' ? 'severity' : 'range'] =
                action === 'severity' ? trigger.dataset.value : parseInt(trigger.dataset.value, 10);
            filters.page = 0;
            markSegment(root, action, trigger.dataset.value);
            reload(root);
            return;
        }

        if (action === 'page') {
            event.preventDefault();
            filters.page = parseInt(trigger.dataset.page, 10);
            reload(root);
            return;
        }

        if (action === 'clear') {
            event.preventDefault();
            filters = {severity: '', course: 0, assign: 0, user: 0, range: 0, page: 0};
            markSegment(root, 'severity', '');
            markSegment(root, 'range', '0');
            root.querySelectorAll('.tipnc-log__select').forEach((select) => {
                select.value = '';
                select.dispatchEvent(new Event('change', {bubbles: true}));
            });
            reload(root);
            return;
        }

        if (action === 'detail') {
            event.preventDefault();
            opener = trigger;
            openDetail(root, parseInt(trigger.dataset.id, 10));
            return;
        }

        if (action === 'close') {
            event.preventDefault();
            closeDetail(root);
            return;
        }

        if (action === 'copy') {
            event.preventDefault();
            copyDiagnosis(trigger);
            return;
        }

        if (action === 'purge') {
            event.preventDefault();
            confirmPurge(trigger);
        }
    });

    // El panel es un diálogo: se cierra con Escape y pulsando fuera.
    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !root.querySelector(SELECTORS.drawer).hidden) {
            closeDetail(root);
        }
    });

    root.querySelector(SELECTORS.drawer).addEventListener('click', (event) => {
        if (event.target === event.currentTarget) {
            closeDetail(root);
        }
    });

    root.querySelectorAll('.tipnc-log__select').forEach((select) => {
        select.addEventListener('change', () => {
            const key = select.dataset.filter;
            filters[key] = parseInt(select.value, 10) || 0;
            filters.page = 0;
            reload(root);
        });

        // Curso, tarea y usuario pueden ser miles: se buscan, no se listan.
        enhanceFilter(select);
    });
};

/**
 * Turns a filter into a field that searches as the user types.
 *
 * @param {Element} select The filter.
 * @returns {Promise<void>}
 */
const enhanceFilter = async(select) => {
    const placeholder = await getString(`log_filter_${select.dataset.filter}_hint`, 'assignsubmission_tipnc');

    try {
        await Autocomplete.enhance(
            `#${select.id}`,
            false,
            'assignsubmission_tipnc/filtersource',
            placeholder,
            false,
            true,
            '',
            true
        );
    } catch (error) {
        // Sin autocompletado el filtro sigue siendo utilizable: no se molesta al usuario.
        window.console.debug(error);
    }
};
