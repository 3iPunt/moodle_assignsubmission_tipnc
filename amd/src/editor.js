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
 * Puts the office editor inside the page.
 *
 * The editor is served by another host, so its library has to be fetched at
 * runtime. If it cannot be fetched —wrong address, service down, a certificate
 * the browser does not trust— the page says so instead of leaving a hole: an
 * empty box is the worst possible answer for somebody who has to hand in work.
 *
 * @module     assignsubmission_tipnc/editor
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

const SELECTORS = {
    fallback: '[data-region="tipnc-editor-fallback"]',
};

/** @var {Promise|null} loading The library is fetched once per page. */
let loading = null;

/**
 * Fetches the editor library from the Document Server.
 *
 * @param {String} serverUrl Public address of the Document Server.
 * @return {Promise} Resolved when the library is ready.
 */
const loadApi = (serverUrl) => {
    if (window.DocsAPI) {
        return Promise.resolve();
    }

    if (loading) {
        return loading;
    }

    loading = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = serverUrl.replace(/\/+$/, '') + '/web-apps/apps/api/documents/api.js';
        script.onload = resolve;
        script.onerror = () => reject(new Error('api.js'));
        document.head.appendChild(script);
    });

    return loading;
};

/**
 * Says that the editor could not be loaded, where the editor should have been.
 *
 * @param {HTMLElement} container Element that was going to hold the editor.
 */
const explain = async(container) => {
    const message = await getString('editor_unavailable', 'assignsubmission_tipnc');
    const notice = container.querySelector(SELECTORS.fallback);

    if (notice) {
        notice.textContent = message;
        notice.hidden = false;
    }
};

/**
 * Mounts the editor for one document.
 *
 * @param {String} elementId Id of the element that holds the editor.
 * @param {Object} config Configuration built and signed by Moodle.
 * @param {String} serverUrl Public address of the Document Server.
 */
export const init = async(elementId, config, serverUrl) => {
    const container = document.getElementById(elementId);
    if (!container) {
        return;
    }

    try {
        await loadApi(serverUrl);

        // El editor se queda vivo aunque la página se vaya. Cerrarlo al entregar
        // parecía limpio, pero mata la sesión un instante antes de que el servidor
        // le pida guardar, y entonces lo que se congela es la versión anterior.
        new window.DocsAPI.DocEditor(elementId, config);
    } catch (error) {
        explain(container.parentNode || container);
    }
};
