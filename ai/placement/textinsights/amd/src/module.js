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
 * Module initialization
 *
 * @module     aiplacement_textinsights/module
 * @copyright  2025 DeveloperCK <developerck@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

/**
 * Initialize the module
 *
 * @param {number} courseId The course ID
 */
export const init = (courseId) => {
    // Create context menu element
    const menuHtml = `
        <div class="textinsights-menu" style="display:none;">
            <div class="list-group">
                <a href="#" class="list-group-item list-group-item-action" data-action="ask_veda">
                    <img src="https://cdn-icons-png.flaticon.com/512/2462/2462719.png"
                         style="width: 16px; height: 16px; margin-right: 5px;" alt="Ask Veda"> Ask Veda
                </a>
            </div>
        </div>`;

    const $menu = $(menuHtml).appendTo('body');

    // Add styles
    $('head').append(`
        <style>
            .textinsights-menu {
                position: absolute;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                z-index: 1000;
                padding: 0;
                overflow: hidden;
            }
            .textinsights-menu .list-group-item {
                border: none;
                padding: 8px 15px;
                display: flex;
                align-items: center;
                font-weight: 500;
                color: #333;
                transition: background-color 0.2s;
            }
            .textinsights-menu .list-group-item:hover {
                background-color: #f8f9fa;
                text-decoration: none;
            }
            /* Selection highlight for MathJax formulas */
            mjx-container.mj-selected {
                position: relative !important;
                background-color: rgba(191, 219, 254, 0.5) !important;
                border-radius: 2px;
                z-index: 1;
            }
            /* Overlay layer for cross-browser visibility (Edge/Chrome) */
            mjx-container.mj-selected::after {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(59, 130, 246, 0.15);
                border-radius: 2px;
                pointer-events: none;
                z-index: 2;
            }
            mjx-container.mj-selected svg {
                background-color: transparent !important;
            }
        </style>
    `);

    // Helper to find the section name
    const getSectionName = (node) => {
        if (!node) {
            return 'Unknown Section';
        }

        let $element = $(node);

        // If we are inside an mjx-container, start from the container itself
        const $formulaContainer = $element.closest('mjx-container');
        if ($formulaContainer.length) {
            $element = $formulaContainer;
        }

        // 1. Look for closest section or activity with data-sectionname attribute
        const $sectionWithAttr = $element.closest('[data-sectionname]');
        if ($sectionWithAttr.length) {
            return $sectionWithAttr.attr('data-sectionname');
        }

        // 2. Look for closest course-section and get header
        const $section = $element.closest('.course-section, .section');
        if ($section.length) {
            if ($section.attr('data-sectionname')) {
                return $section.attr('data-sectionname');
            }
            const $header = $section.find('.sectionname, h3.sectionname a, h3.sectionname').first();
            if ($header.length) {
                return $header.text().trim();
            }
        }

        // 3. Fallback for activities
        const $activity = $element.closest('.activity, .modtype_page, .block');
        if ($activity.length) {
            const $header = $activity.find('.activity-name, .block_header span, h2').first();
            if ($header.length) {
                return $header.text().trim();
            }
        }

        return 'Unknown Section';
    };

    /**
     * Dispatches the custom event veda:text_selected.
     *
     * @param {string} selectedText - The text selected by the user
     * @param {string} sectionName - The section/module name where text was selected
     * @param {number} courseId - The course ID
     */
    const dispatchVedaTextSelectionEvent = (selectedText, sectionName, courseId) => {
        const event = new CustomEvent('veda:text_selected', {
            detail: {
                selectedText,
                sectionName,
                courseId,
                timestamp: Date.now()
            }
        });
        window.dispatchEvent(event);
    };

    /**
     * Extract the original LaTeX source from an mjx-container element.
     *
     * @param {HTMLElement} mjx The mjx-container element.
     * @returns {string} The LaTeX source, or empty string if not found.
     */
    const getTexFromMjxContainer = (mjx) => {
        if (!mjx) {
            return '';
        }

        // 1. Try MathJax 3 API (Most reliable).
        if (window.MathJax && window.MathJax.startup && window.MathJax.startup.document) {
            try {
                const mathItems = window.MathJax.startup.document.math;
                for (const math of mathItems) {
                    if (math.typesetRoot === mjx || mjx.contains(math.typesetRoot) || math.typesetRoot.contains(mjx)) {
                        return math.math.trim();
                    }
                }
            } catch (e) {
                // Ignore API errors.
            }
        }

        // 2. Try standard MathJax 3 annotation.
        let annotation = mjx.querySelector('annotation[encoding="application/x-tex"]');
        if (!annotation) {
            annotation = mjx.getElementsByTagName('annotation')[0];
        }
        if (annotation && annotation.textContent) {
            return annotation.textContent.trim();
        }

        // 3. Try data-tex attribute.
        if (mjx.hasAttribute('data-tex')) {
            return mjx.getAttribute('data-tex').trim();
        }

        // 4. Try searching for a hidden script tag.
        const script = mjx.querySelector('script[type^="math/tex"]');
        if (script) {
            return script.textContent.trim();
        }

        return '';
    };

    /**
     * Find all mjx-container elements that overlap with the given range.
     *
     * @param {Range} range The selection range.
     * @returns {HTMLElement[]} Array of overlapping mjx-container elements.
     */
    const getOverlappingMjxContainers = (range) => {
        const containers = document.querySelectorAll('mjx-container');
        const overlapping = [];

        containers.forEach((container) => {
            const containerRange = document.createRange();
            containerRange.selectNode(container);

            const startsAfterSelection =
                range.compareBoundaryPoints(Range.START_TO_END, containerRange) <= 0;
            const endsBeforeSelection =
                range.compareBoundaryPoints(Range.END_TO_START, containerRange) >= 0;

            if (!startsAfterSelection && !endsBeforeSelection) {
                overlapping.push(container);
            }
        });

        return overlapping;
    };

    /**
     * Build a LaTeX-enriched text string from the selection range.
     *
     * @param {Range} range The selection range.
     * @param {HTMLElement[]} overlappingMjx Original mjx-containers that overlap the selection.
     * @returns {string} The enriched text with LaTeX formulas.
     */
    const buildEnrichedText = (range, overlappingMjx) => {
        const fragment = range.cloneContents();
        const mjxInFragment = fragment.querySelectorAll('mjx-container');

        // CASE 1: The cloned fragment has NO mjx-containers.
        if (mjxInFragment.length === 0 && overlappingMjx.length > 0) {
            const texParts = [];
            overlappingMjx.forEach((originalMjx) => {
                const tex = getTexFromMjxContainer(originalMjx);
                if (tex) {
                    texParts.push('\\(' + tex + '\\)');
                }
            });

            const fragmentDiv = document.createElement('div');
            fragmentDiv.appendChild(fragment);
            const fragmentText = (fragmentDiv.textContent || fragmentDiv.innerText || '').trim();

            let result;
            if (fragmentText) {
                result = fragmentText + ' ' + texParts.join(' ');
            } else {
                result = texParts.join(' ');
            }
            return result.trim();
        }

        // CASE 2: Mixed selection.
        mjxInFragment.forEach((mjxClone, index) => {
            let tex = getTexFromMjxContainer(mjxClone);

            if (!tex && overlappingMjx[index]) {
                tex = getTexFromMjxContainer(overlappingMjx[index]);
            }

            if (!tex) {
                for (const originalMjx of overlappingMjx) {
                    tex = getTexFromMjxContainer(originalMjx);
                    if (tex) {
                        break;
                    }
                }
            }

            if (tex) {
                const texNode = document.createTextNode('\\(' + tex + '\\)');
                mjxClone.parentNode.replaceChild(texNode, mjxClone);
            }
        });

        const div = document.createElement('div');
        div.appendChild(fragment);
        return (div.textContent || div.innerText || '').trim();
    };

    // Function to get selected text and its container
    const getSelectedText = () => {
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed || !selection.rangeCount) {
            return null;
        }

        const range = selection.getRangeAt(0);
        const plainText = range.toString().trim();
        const overlappingMjx = getOverlappingMjxContainers(range);

        if (!plainText && overlappingMjx.length === 0) {
            return null;
        }

        const startNode = range.startContainer;
        const endNode = range.endContainer;
        if (startNode.nodeType === Node.TEXT_NODE) {
            const startOffset = range.startOffset;
            const nodeText = startNode.textContent;
            let start = startOffset;
            while (start > 0 && /\S/.test(nodeText[start - 1])) {
                start--;
            }
            range.setStart(startNode, start);
        }
        if (endNode.nodeType === Node.TEXT_NODE) {
            const endOffset = range.endOffset;
            const nodeText = endNode.textContent;
            let end = endOffset;
            while (end < nodeText.length && /\S/.test(nodeText[end])) {
                end++;
            }
            range.setEnd(endNode, end);
        }

        // Force the browser selection UI to update to the expanded range
        selection.removeAllRanges();
        selection.addRange(range);

        let finalText;
        if (overlappingMjx.length > 0) {
            finalText = buildEnrichedText(range, overlappingMjx);
        } else {
            finalText = range.toString().trim();
        }

        if (!finalText) {
            return null;
        }

        return {
            text: finalText,
            range: range
        };
    };

    // Handle context menu positioning and display
    $(document).on('mouseup', '.course-content, .course-content *', async () => {
        const selection = getSelectedText();

        // Clear existing highlights first to avoid stale highlights
        document.querySelectorAll('mjx-container.mj-selected').forEach(el => el.classList.remove('mj-selected'));

        if (!selection || !selection.text) {
            $menu.hide();
            return;
        }

        // Apply visual highlight to all formulas included in the selection
        // We find overlapping containers using the same range that determined our text
        const overlappingMjx = getOverlappingMjxContainers(selection.range);
        overlappingMjx.forEach(container => container.classList.add('mj-selected'));

        // Position menu near selection
        const rect = selection.range.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        $menu.css({
            top: rect.bottom + scrollTop + 5 + 'px',
            left: rect.left + scrollLeft + 'px'
        }).show();

        // Store selection data
        $menu.data('selection', selection);
    });

    // Handle menu item clicks
    $menu.on('click', '[data-action="ask_veda"]', async function (e) {
        e.preventDefault();
        const selection = $menu.data('selection');
        if (!selection) { return; }

        $menu.hide();

        // Get context information
        const sectionName = getSectionName(selection.range.startContainer);

        // Dispatch custom event for Veda to handle
        dispatchVedaTextSelectionEvent(selection.text, sectionName, courseId);
    });

    // Hide menu and tooltip on click outside
    $(document).on('mousedown', (e) => {
        if (!$(e.target).closest('.textinsights-menu').length) {
            $menu.hide();
        }
    });

    // Handle window resize and scroll
    $(window).on('resize scroll', () => {
        /*
        if ($tooltip.is(':visible')) {
            const selection = $menu.data('selection');
            if (selection) {
                positionTooltip(selection.range);
            }
        }
        */
        $menu.hide(); // Hide menu on scroll/resize for better UX
    });
};
