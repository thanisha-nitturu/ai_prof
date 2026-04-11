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
 * Provides the functionality for toggling the manual completion state of a course module through
 * the manual completion button.
 *
 * @module      core_course/manual_completion_toggle
 * @copyright   2021 Jun Pataleta <jun@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import Notification from 'core/notification';
import Modal from 'core/modal';
import { get_string as getString } from 'core/str';
import { toggleManualCompletion } from 'core_course/repository';
import * as CourseEvents from 'core_course/events';
import Pending from 'core/pending';

/**
 * Selectors in the manual completion template.
 *
 * @type {{MANUAL_TOGGLE: string}}
 */
const SELECTORS = {
    MANUAL_TOGGLE: 'button[data-action=toggle-manual-completion]',
};

/**
 * Toggle type values for the data-toggletype attribute in the core_course/completion_manual template.
 *
 * @type {{TOGGLE_UNDO: string, TOGGLE_MARK_DONE: string}}
 */
const TOGGLE_TYPES = {
    TOGGLE_MARK_DONE: 'manual:mark-done',
    TOGGLE_UNDO: 'manual:undo',
};

/**
 * Whether the event listener has already been registered for this module.
 *
 * @type {boolean}
 */
let registered = false;

/**
 * Registers the click event listener for the manual completion toggle button.
 */
export const init = () => {
    if (registered) {
        return;
    }
    document.addEventListener('click', (e) => {
        const toggleButton = e.target.closest(SELECTORS.MANUAL_TOGGLE);
        if (toggleButton) {
            e.preventDefault();
            toggleManualCompletionState(toggleButton).catch(Notification.exception);
        }
    });
    registered = true;
};

/**
 * Helper to find the section name used for analytics/events.
 *
 * @param {HTMLElement} toggleButton
 * @returns {string}
 */
const getSectionName = (toggleButton) => {
    // 1. Try closest parent with data-sectionname
    const sectionByAttr = toggleButton.closest('[data-sectionname]');
    if (sectionByAttr) {
        return sectionByAttr.getAttribute('data-sectionname') || '';
    }
    // 2. Try closest section by class
    const sectionByClass = toggleButton.closest('li.section.course-section');
    if (sectionByClass) {
        return sectionByClass.getAttribute('data-sectionname') || '';
    }
    // 3. Find activity module by cmid and check parent
    const cmid = toggleButton.getAttribute('data-cmid');
    if (cmid) {
        const activityModule = document.querySelector(`[data-cmid="${cmid}"], #module-${cmid}`);
        if (activityModule) {
            const parentSection = activityModule.closest('[data-sectionname]');
            if (parentSection) {
                return parentSection.getAttribute('data-sectionname') || '';
            }
        }
    }
    return '';
};

/**
 * Dispatches the custom event veda:completion_updated.
 *
 * @param {string} cmid
 * @param {string} activityname
 * @param {string} sectionName
 * @param {boolean} completed
 * @param {boolean} attemptQuiz
 */
const dispatchVedaEvent = (cmid, activityname, sectionName, completed, attemptQuiz = false) => {
    const courseId = M.cfg.courseId;
    const event = new CustomEvent('veda:completion_updated', {
        detail: {
            cmid,
            activityname,
            courseId,
            sectionName,
            completed,
            attemptQuiz,
            timestamp: Date.now()
        }
    });
    window.dispatchEvent(event);
};

/**
 * Shows the completion confirmation modal.
 *
 * @returns {Promise<string|null>}
 */
const showCompletionConfirmModal = async () => {
    const modal = await Modal.create({
        template: 'core_course/modal_completion_confirm',
        title: await getString('completion_confirm_title', 'core_course'),
        body: await getString('completion_confirm_message', 'core_course'),
        removeOnClose: true,
        show: true,
    });
    const root = modal.getRoot()[0];
    return new Promise((resolve, reject) => {
        let handled = false;
        root.addEventListener('click', (e) => {
            const button = e.target.closest('button[data-action]');
            if (!button || handled) {
                return;
            }
            const action = button.getAttribute('data-action');
            // Resolve with 'complete' or 'complete-quiz'
            if (action === 'complete' || action === 'complete-quiz') {
                handled = true;
                modal.destroy();
                resolve(action);
            }
        });
        modal.getRoot().on('modal:hidden', () => {
            if (!handled) {
                reject();
            }
        });
    });
};

/**
 * Toggles the manual completion state of the module for the given user.
 *
 * @param {HTMLElement} toggleButton
 * @returns {Promise<void>}
 */
const toggleManualCompletionState = async (toggleButton) => {
    const pendingPromise = new Pending('core_course:toggleManualCompletionState');
    // Make a copy of the original content of the button.
    const originalInnerHtml = toggleButton.innerHTML;

    // Get button data.
    const toggleType = toggleButton.getAttribute('data-toggletype');
    const cmid = toggleButton.getAttribute('data-cmid');
    const activityname = toggleButton.getAttribute('data-activityname');

    // 1. Undo Confirmation
    if (toggleType === TOGGLE_TYPES.TOGGLE_UNDO) {
        try {
            await Notification.saveCancelPromise(
                await getString('completion_undo_confirm_title', 'core_course'),
                await getString('completion_undo_confirm_message', 'core_course'),
                await getString('completion_undo_confirm_button', 'core_course'),
                { triggerElement: toggleButton }
            );
        } catch {
            pendingPromise.resolve();
            return;
        } // User cancelled
    }

    // 2. Mark as Done Confirmation
    if (toggleType === TOGGLE_TYPES.TOGGLE_MARK_DONE) {
        const nameLower = activityname ? activityname.toLowerCase() : '';
        const isModuleContent = nameLower.includes('module content');
        const isModuleOutline = nameLower.includes('outline');

        try {
            if (isModuleContent) {
                // SHOW CUSTOM 2-BUTTON MODAL ("Great Job... content")
                const action = await showCompletionConfirmModal(); // Custom function below
                if (!action) {
                    pendingPromise.resolve();
                    return;
                }

                // "Take Quiz Now" -> vedaAttemptQuiz = true
                // "Take Quiz Later" -> vedaAttemptQuiz = false
                toggleButton.vedaAttemptQuiz = (action === 'complete-quiz');
            } else {
                let titleKey = 'completion_video_title';
                if (isModuleOutline) {
                    titleKey = 'completion_outline_title';
                }

                // SHOW STANDARD CONFIRMATION
                await Notification.saveCancelPromise(
                    await getString(titleKey, 'core_course'),
                    await getString('completion_video_message', 'core_course'),
                    await getString('completion_video_complete', 'core_course'),
                    { triggerElement: toggleButton }
                );
            }
        } catch {
            pendingPromise.resolve();
            return;
        } // User cancelled
    }

    // Disable the button to prevent double clicks.
    toggleButton.setAttribute('disabled', 'disabled');

    // Get the target completion state.
    const completed = toggleType === TOGGLE_TYPES.TOGGLE_MARK_DONE;

    // Replace the button contents with the loading icon.
    Templates.renderForPromise('core/loading', {})
        .then((loadingHtml) => {
            Templates.replaceNodeContents(toggleButton, loadingHtml, '');
            return;
        }).catch(() => { });

    try {
        // Call the webservice to update the manual completion status.
        await toggleManualCompletion(cmid, completed);

        // All good so far. Refresh the manual completion button to reflect its new state by re-rendering the template.
        const templateContext = {
            cmid: cmid,
            activityname: activityname,
            overallcomplete: completed,
            overallincomplete: !completed,
            istrackeduser: true, // We know that we're tracking completion for this user given the presence of this button.
            normalbutton: !toggleButton.classList.contains('btn-sm'),
        };
        const renderObject = await Templates.renderForPromise('core_course/completion_manual', templateContext);

        // Replace the toggle button with the newly loaded template.
        const replacedNode = await Templates.replaceNode(toggleButton, renderObject.html, renderObject.js);
        const newToggleButton = replacedNode.pop();

        // Build manualCompletionToggled custom event.
        const withAvailability = toggleButton.getAttribute('data-withavailability');
        const toggledEvent = new CustomEvent(CourseEvents.manualCompletionToggled, {
            bubbles: true,
            detail: {
                cmid,
                activityname,
                completed,
                withAvailability,
            }
        });
        // Dispatch the manualCompletionToggled custom event.
        newToggleButton.dispatchEvent(toggledEvent);

        // 3. Dispatch Event AFTER Success
        // Capture section name BEFORE button replacement if possible, or use helper
        const sectionName = getSectionName(newToggleButton);
        // Dispatch only for "Take Quiz Now" OR Undo
        if (toggleButton.vedaAttemptQuiz || !completed) {
            dispatchVedaEvent(cmid, activityname, sectionName, completed, toggleButton.vedaAttemptQuiz || false);
        }

    } catch (exception) {
        // In case of an error, revert the original state and appearance of the button.
        toggleButton.removeAttribute('disabled');
        toggleButton.innerHTML = originalInnerHtml;

        // Show the exception.
        Notification.exception(exception);
    }
    pendingPromise.resolve();
};
