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
 *
 * @module     tiny_videolesson/filtercontent
 * @author     BitKea Technologies LLP
 * @copyright  2024 BitKea Technologies LLP (https://www.bitkea.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {registerPlaceholderSelectors} from 'editor_tiny/options';

export const setup = async(editor) => {
    const className = 'videolesson-placeholder';
    const classSelector = `.${className}`;
    registerPlaceholderSelectors(editor, [classSelector]);
    editor.on('PreInit', () => {
        editor.formatter.register('videolesson', {
            inline: 'div',
            classes: className,
        });
    });

    editor.on('SetContent', () => {
        editor.getBody().querySelectorAll(`${classSelector}:not([contenteditable])`).forEach((node) => {
            node.contentEditable = false;
        });
    });
};
