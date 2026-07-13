/* eslint-disable no-console */
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
 * Tiny Video Lesson Content configuration.
 *
 * @module    tiny_videolesson/ui
 * @author     BitKea Technologies LLP
 * @copyright  2024 BitKea Technologies LLP (https://www.bitkea.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {component} from './common';
import {getPermissions} from './options';
import {renderForPromise} from 'core/templates';
import Modal from 'tiny_videolesson/modal';
import ModalEvents from 'core/modal_events';
import Pending from 'core/pending';
import Ajax from 'core/ajax';
import {initTable} from 'mod_videolesson/table';
let openingSelection = null;

export const handleAction = (editor) => {
    openingSelection = editor.selection.getBookmark();
    displayDialogue(editor);
};

/**
 * Get the template context for the dialogue.
 *
 * @param {Editor} editor
 * @param {object} data
 * @returns {object} data
 */

const getTemplateContext = async (editor, data) => {
    const permissions = getPermissions(editor);

    const canUpload = true;
    const canEmbed = permissions.embed ?? false;
    const canUploadAndEmbed = canUpload && canEmbed;

    // Fetch videos from the database
    const videos = await fetchVideos();
    return Object.assign({}, {
        elementid: editor.id,
        canUpload,
        canEmbed,
        canUploadAndEmbed,
        showOptions: false,
        videos,
        uploadlink: true,
        tableid: Date.now(),
    }, data);
};

/**
 * Fetch videos from the database.
 *
 * @returns {Promise<Array>} A promise that resolves to an array of video objects.
 */
const fetchVideos = async () => {
    try {
        const request = {
            methodname: 'tiny_videolesson_get_videos',
            args: {}
        };
        const responses = await Ajax.call([request])[0];
        const videos = responses.list;
        return videos || [];
    } catch (error) {
        console.error('Error fetching videos:', error);
        return [];
    }
};

const handleDialogueSubmission = async (editor, modal, data) => {
    const pendingPromise = new Pending('tiny_videolesson:handleDialogueSubmission');

    const tbl = modal.getRoot().find('table').get(0);
    if (!tbl) {
        console.error('Table element not found.');
        pendingPromise.reject();
        return;
    }

    const submittedHash = tbl.dataset.selected;
    const submittedTitle = tbl.dataset.title;
    if (!submittedHash) {
        console.error(' No hash selected, represent the dialogue with an error');
        modal.destroy();
        displayDialogue(editor, Object.assign({}, data, {
            hash: submittedHash,
            invalidHash: true,
        }));
        pendingPromise.resolve();
        return;
    }

    try {
        const content = await renderForPromise(`${component}/content`, {
            hash: submittedHash.toString(),
            title: submittedTitle.toString(),
        });

        // Insert the content into the editor
        editor.selection.moveToBookmark(openingSelection);
        editor.execCommand('mceInsertContent', false, content.html);
        editor.selection.moveToBookmark(openingSelection);

        tbl.dataset.selected = '';
        tbl.dataset.title = '';
        const rows = tbl.querySelectorAll('tbody tr');
        rows.forEach(row => row.classList.remove('selected'));

        console.log('Dialog submission handled and table cleared.');
    } catch (error) {
        console.error('Error handling dialog submission:', error);
    } finally {
        pendingPromise.resolve();
    }
    modal.destroy();
};

const displayDialogue = async(editor, data = {}) => {

    const templatecontext = await getTemplateContext(editor, data);
    const modal = await Modal.create({
        templateContext: templatecontext,
    });

    const $root = modal.getRoot();

    $root.one(ModalEvents.shown, (e, modalInstance) => {
        initTable('videolist' + templatecontext.tableid, 'videolistsearchinput' + templatecontext.tableid);
        const tbl = modalInstance.getRoot().find('table').get(0);
        if (!tbl) {
            console.error('Table with id #videolist not found.');
            return;
        }

        tbl.addEventListener('click', (event) => {
            const row = event.target.closest('tr');
            if (!row || !tbl.contains(row)) {
                return;
            }
            const hash = row.dataset.contenthash;
            const title = row.dataset.title;
            if (hash) {
                const rows = tbl.querySelectorAll('tbody tr');
                rows.forEach(r => r.classList.remove('selected'));
                row.classList.add('selected');
                tbl.dataset.selected = hash;
                tbl.dataset.title = title;
            } else {
                console.warn('No content hash found for the selected row.');
            }
        });
    });

    $root.on(ModalEvents.save, (event, modalInstance) => {
        event.preventDefault();
        handleDialogueSubmission(editor, modalInstance, data);
    });

};
