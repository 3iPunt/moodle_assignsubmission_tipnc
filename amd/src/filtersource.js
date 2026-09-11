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
 * Data source of the course, assignment and user filters.
 *
 * @module     assignsubmission_tipnc/filtersource
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Asks the server for the entities matching what the user typed.
 *
 * @param {String}   selector Selector of the field being filled.
 * @param {String}   query    What the user typed.
 * @param {Function} success  Called with the matches.
 * @param {Function} failure  Called when the search fails.
 * @returns {void}
 */
export const transport = (selector, query, success, failure) => {
    const field = document.querySelector(selector);
    if (!field) {
        failure(new Error('Unknown filter field'));
        return;
    }

    // The assignment is narrowed to the chosen course, if any: searching
    // across every assignment on the site helps nobody.
    const course = document.querySelector('[data-filter="course"]');
    const args = {
        type: field.dataset.filter,
        query: query,
        course: field.dataset.filter === 'assign' && course ? parseInt(course.value, 10) || 0 : 0,
    };

    Ajax.call([{
        methodname: 'assignsubmission_tipnc_filter_options',
        args: args,
    }])[0].then(success).catch(failure);
};

/**
 * Turns the answer into what the autocomplete field expects.
 *
 * @param {String} selector Selector of the field being filled.
 * @param {Array}  results  The matches.
 * @returns {Array} The options.
 */
export const processResults = (selector, results) => {
    return results.map((entity) => {
        return {
            value: entity.value,
            label: entity.label,
        };
    });
};
