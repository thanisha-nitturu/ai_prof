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
 * AMD module for AJAX grade saving in the Grader Report.
 * Intercepts the quick-grading form submit and saves grades without a full page reload.
 *
 * @module    gradereport_grader/ajax_grading
 * @copyright 2024 Custom
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';

/** Selectors used in this module */
const Selectors = {
    form:        '#gradereport_grader',
    gradeInput:  'input[name^="grade["]',
    gradeSelect: 'select[name^="grade["]',
    submitBtn:   '#gradersubmit',
    // Grade cells: data-userid + data-itemid on the <td>
    gradeCell:   'td.grade[data-itemid]',
};

/** Base URL of this plugin, set on init */
let SAVE_URL = '';
let COURSE_ID = 0;
let SESSKEY   = '';
let TIMEPAGELOAD = 0;

// ─── Toast / notification helper ────────────────────────────────────────────

/**
 * Display a brief floating toast message near the top-right of the screen.
 *
 * @param {string} message
 * @param {string} type  'success' | 'warning' | 'danger'
 */
const showToast = (message, type = 'success') => {
    // Remove any existing toasts so they don't stack up.
    document.querySelectorAll('.grader-ajax-toast').forEach(el => el.remove());

    const icons = {
        success: '✓',
        warning: '⚠',
        danger:  '✕',
    };

    const colors = {
        success: '#28a745',
        warning: '#ffc107',
        danger:  '#dc3545',
    };

    const toast = document.createElement('div');
    toast.className = 'grader-ajax-toast';
    toast.innerHTML = `<span style="font-size:1.1em">${icons[type] || '•'}</span> ${message}`;
    toast.style.cssText = `
        position: fixed;
        top: 70px;
        right: 24px;
        z-index: 99999;
        background: ${colors[type] || colors.success};
        color: #fff;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 0.92rem;
        font-weight: 600;
        box-shadow: 0 4px 18px rgba(0,0,0,0.18);
        display: flex;
        align-items: center;
        gap: 8px;
        animation: graderToastIn 0.25s ease;
        pointer-events: none;
    `;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'opacity 0.4s ease';
        toast.style.opacity    = '0';
        setTimeout(() => toast.remove(), 450);
    }, type === 'success' ? 2200 : 4500);
};

// ─── DOM update helpers ──────────────────────────────────────────────────────

/**
 * Flash a cell green/yellow to give visual confirmation of a save.
 *
 * @param {HTMLElement} cell
 * @param {string} colorClass  CSS class to add temporarily
 */
const flashCell = (cell, colorClass = 'grade-saved') => {
    cell.classList.add(colorClass);
    setTimeout(() => cell.classList.remove(colorClass), 1500);
};

/**
 * Given a userid and itemid, find the corresponding grade <td> in the table.
 *
 * @param {number} userid
 * @param {number} itemid
 * @returns {HTMLElement|null}
 */
const findGradeCell = (userid, itemid) => {
    // Grade data cells sit inside a <tr data-uid="..."> and have class matching i{itemid}.
    const row = document.querySelector(`tr[data-uid="${userid}"]`);
    if (!row) {
        return null;
    }
    return row.querySelector(`td.grade.i${itemid}, td[data-itemid="${itemid}"]`);
};

/**
 * Update the display text of a grade cell after an AJAX save.
 *
 * @param {number} userid
 * @param {number} itemid
 * @param {string} displayValue  Formatted grade string from the server
 */
const updateCellDisplay = (userid, itemid, displayValue) => {
    const cell = findGradeCell(userid, itemid);
    if (!cell) {
        return;
    }

    // The grade value is typically inside a span.gradevalue or just the cell text.
    const gradeSpan = cell.querySelector('.gradevalue, [data-collapse="content"] .content');
    if (gradeSpan) {
        gradeSpan.textContent = displayValue;
    }
    flashCell(cell, 'grade-saved');
};

// ─── Save logic ──────────────────────────────────────────────────────────────

/**
 * Collect all grades from the form (only changed ones via a snapshot comparison).
 *
 * @param {HTMLFormElement} form
 * @returns {Object} grade data keyed by [userid][itemid]
 */
const collectGrades = (form) => {
    const grades = {};
    const inputs = form.querySelectorAll(`${Selectors.gradeInput}, ${Selectors.gradeSelect}`);

    inputs.forEach(input => {
        // Name format: grade[userid][itemid]
        const match = input.name.match(/grade\[(\d+)\]\[(\d+)\]/);
        if (!match) {
            return;
        }
        const userid = match[1];
        const itemid = match[2];

        if (!grades[userid]) {
            grades[userid] = {};
        }
        grades[userid][itemid] = input.value;
    });

    return grades;
};

/**
 * Set the submit button into a loading state.
 *
 * @param {HTMLElement} btn
 * @param {boolean} loading
 */
const setButtonLoading = (btn, loading) => {
    if (!btn) {
        return;
    }
    if (loading) {
        btn.disabled = true;
        btn.dataset.originalText = btn.value;
        btn.value = 'Saving…';
    } else {
        btn.disabled = false;
        btn.value = btn.dataset.originalText || 'Save changes';
    }
};

/**
 * Send grades to the server via fetch and process the response.
 *
 * @param {HTMLFormElement} form
 * @returns {Promise<void>}
 */
const saveGrades = async(form) => {
    const submitBtn = form.querySelector(Selectors.submitBtn);
    setButtonLoading(submitBtn, true);

    const grades = collectGrades(form);

    // Build FormData for the POST request.
    const formData = new FormData();
    formData.append('courseid', COURSE_ID);
    formData.append('sesskey', SESSKEY);
    formData.append('timepageload', TIMEPAGELOAD);

    // Flatten grades into grade[userid][itemid] fields.
    for (const [userid, items] of Object.entries(grades)) {
        for (const [itemid, value] of Object.entries(items)) {
            formData.append(`grade[${userid}][${itemid}]`, value);
        }
    }

    let response;
    try {
        const fetchResponse = await fetch(SAVE_URL, {
            method:      'POST',
            body:        formData,
            credentials: 'same-origin',
        });

        if (!fetchResponse.ok) {
            throw new Error(`HTTP error: ${fetchResponse.status}`);
        }

        response = await fetchResponse.json();
    } catch (err) {
        setButtonLoading(submitBtn, false);
        Notification.exception(err);
        return;
    }

    setButtonLoading(submitBtn, false);

    if (!response.success) {
        showToast(response.error || 'An error occurred.', 'danger');
        return;
    }

    // Update grade cells in the DOM.
    if (response.updated && response.updated.length > 0) {
        response.updated.forEach(item => {
            updateCellDisplay(item.userid, item.itemid, item.display);
        });
    }

    // Update course totals and category totals.
    if (response.totals && response.totals.length > 0) {
        response.totals.forEach(item => {
            updateCellDisplay(item.userid, item.itemid, item.display);
        });
    }

    // Show warnings if any.
    if (response.warnings && response.warnings.length > 0) {
        response.warnings.forEach(warning => {
            showToast(warning, 'warning');
        });
    } else if (response.updated && response.updated.length > 0) {
        showToast('Grades saved successfully.', 'success');
    } else {
        showToast('No changes to save.', 'warning');
    }

    // Update the timepageload so subsequent saves don't trigger the "modified during editing" warning.
    TIMEPAGELOAD = Math.floor(Date.now() / 1000);
};

// ─── Event listeners ─────────────────────────────────────────────────────────

/**
 * Register all event listeners using document-level delegation so we are
 * guaranteed to intercept the form submit even if it is rendered after AMD init.
 */
const registerEventListeners = () => {
    // ── Primary: intercept form submit at the document level.
    // Using capture phase (true) so we fire BEFORE Moodle's own bubbling listeners.
    document.addEventListener('submit', (e) => {
        const form = e.target.closest(Selectors.form);
        if (!form) {
            return; // Not our form.
        }
        // Block the native form submission completely.
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        saveGrades(form);
    }, true); // capture = true is critical

    // ── Secondary: Enter key in grade input cells.
    // Use delegation so it works even if inputs are added dynamically.
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') {
            return;
        }
        const input = e.target.closest(`${Selectors.form} ${Selectors.gradeInput}`);
        if (!input) {
            return;
        }
        e.preventDefault();
        const form = input.closest(Selectors.form);
        if (form) {
            saveGrades(form);
        }
    });
};

// ─── Inject keyframe animation CSS ───────────────────────────────────────────

const injectStyles = () => {
    if (document.getElementById('grader-ajax-styles')) {
        return;
    }
    const style = document.createElement('style');
    style.id = 'grader-ajax-styles';
    style.textContent = `
        @keyframes graderToastIn {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);
};

// ─── Initialization ───────────────────────────────────────────────────────────

/**
 * Initialize the AJAX grading module.
 *
 * PHP's js_call_amd passes arguments as positional params, so our single
 * config object arrives as the first argument (config[0] in the AMD call).
 *
 * @param {Object} config  { courseid, sesskey, timepageload, saveurl }
 */
export const init = (config) => {
    // js_call_amd wraps the array in another array, so unwrap if needed.
    const cfg = Array.isArray(config) ? config[0] : config;

    COURSE_ID    = cfg.courseid;
    SESSKEY      = cfg.sesskey;
    TIMEPAGELOAD = cfg.timepageload;
    SAVE_URL     = cfg.saveurl;

    injectStyles();
    registerEventListeners();
};
