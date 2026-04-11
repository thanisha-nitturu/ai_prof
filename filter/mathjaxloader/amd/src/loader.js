// This file is part of Moodle - http://moodle.org/ //
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
 * Mathjax JS Loader.
 *
 * @module filter_mathjaxloader/loader
 * @copyright 2014 Damyon Wiese  <damyon@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {
    eventTypes,
    notifyFilterContentRenderingComplete,
} from 'core_filters/events';

/**
 * URL to MathJax.
 * @type {string|null}
 */
let mathJaxUrl = null;

/**
 * Promise that is resolved when MathJax was loaded.
 * @type {Promise|null}
 */
let mathJaxLoaded = null;


/**
 * Highlights MathJax formula containers (.mj-selected class) that fall within
 * the user's current text selection. This is needed because SVG elements do not
 * receive the browser's native selection highlight colour.
 * @private
 */
const setupFormulaSelectionHighlight = () => {
    document.addEventListener('selectionchange', () => {
        // Clear any previously highlighted containers.
        document.querySelectorAll('mjx-container.mj-selected').forEach((el) => {
            el.classList.remove('mj-selected');
        });

        const selection = window.getSelection();
        if (!selection || selection.isCollapsed || selection.rangeCount === 0) {
            return;
        }

        const range = selection.getRangeAt(0);

        // Check every rendered formula container against the selection range.
        document.querySelectorAll('mjx-container').forEach((container) => {
            const containerRange = document.createRange();
            containerRange.selectNode(container);

            // Ranges overlap when neither ends before the other starts.
            const startsAfterSelection =
                range.compareBoundaryPoints(Range.START_TO_END, containerRange) <= 0;
            const endsBeforeSelection =
                range.compareBoundaryPoints(Range.END_TO_START, containerRange) >= 0;

            if (!startsAfterSelection && !endsBeforeSelection) {
                container.classList.add('mj-selected');
            }
        });
    });
};

/**
 * Extract the original LaTeX source from an mjx-container element.
 *
 * @param {HTMLElement} mjx The mjx-container element.
 * @returns {string} The LaTeX source, or empty string if not found.
 * @private
 */
const getTexFromMjxContainer = (mjx) => {
    // MathJax 3 stores the TeX in <annotation encoding="application/x-tex">.
    const annotation = mjx.querySelector('annotation[encoding="application/x-tex"]');
    if (annotation && annotation.textContent) {
        return annotation.textContent;
    }
    return '';
};

/**
 * Intercepts the browser copy event so that when formulas are in the selection,
 * the clipboard receives the original LaTeX commands instead of flattened text.
 * @private
 */
const setupFormulaCopyHandler = () => {
    document.addEventListener('copy', (e) => {
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed || selection.rangeCount === 0) {
            return;
        }

        const range = selection.getRangeAt(0);

        // Quick check: are there any mjx-containers in the page that overlap the selection?
        const selectedMjx = document.querySelectorAll('mjx-container.mj-selected');
        if (selectedMjx.length === 0) {
            // No formulas selected — let the browser handle copy normally.
            return;
        }

        // Build the clipboard text by walking through the selected content.
        // Clone the selection fragment so we can manipulate it.
        const fragment = range.cloneContents();

        // Replace every mjx-container in the cloned fragment with its TeX source.
        fragment.querySelectorAll('mjx-container').forEach((mjxClone) => {
            // The cloned fragment may not have the annotation (it can be stripped).
            // So find the original mjx-container in the DOM by matching position.
            let tex = getTexFromMjxContainer(mjxClone);

            if (!tex) {
                // Fallback: find the matching original element and get TeX from it.
                selectedMjx.forEach((originalMjx) => {
                    if (!tex) {
                        tex = getTexFromMjxContainer(originalMjx);
                    }
                });
            }

            if (tex) {
                // Wrap in \( \) for inline math context.
                const texNode = document.createTextNode('\\(' + tex + '\\)');
                mjxClone.parentNode.replaceChild(texNode, mjxClone);
            }
        });

        // Extract the text from the modified fragment.
        const div = document.createElement('div');
        div.appendChild(fragment);
        const resultText = div.textContent || div.innerText || '';

        if (resultText.trim()) {
            e.preventDefault();
            e.clipboardData.setData('text/plain', resultText.trim());
        }
    });
};

/**
 * Called by the filter when it is active on any page.
 * This does not load MathJAX yet - it adds the configuration in case it gets loaded later.
 * It also subscribes to the filter-content-updated event so MathJax can respond to content loaded by Ajax.
 *
 * @param {Object} params List of configuration params containing mathjaxurl, mathjaxconfig (text) and lang
 */
export const configure = (params) => {
    let config = {};
    try {
        if (params.mathjaxconfig !== '') {
            config = JSON.parse(params.mathjaxconfig);
        }
    }
    catch (e) {
        window.console.error('Invalid JSON in mathjaxconfig.', e);
    }
    if (typeof config != 'object') {
        config = {};
    }
    if (typeof config.loader !== 'object') {
        config.loader = {};
    }
    if (!Array.isArray(config.loader.load)) {
        config.loader.load = [];
    }
    if (typeof config.startup !== 'object') {
        config.startup = {};
    }

    // Force matchFontWeight to false to prevent formulas from looking overly bold.
    // This addresses the issue where MathJax attempts to mimic the surrounding text
    // weight, often resulting in "thick" or "dirty" looking math symbols.
    if (!config.chtml) {
        config.chtml = {};
    }
    if (config.chtml.matchFontWeight === undefined) {
        config.chtml.matchFontWeight = false;
    }
    // Prevent MathJax from auto-scaling to "guess" surrounding font height (~121%).
    // This is the primary cause of formulas appearing larger/heavier than body text.
    if (config.chtml.matchFontHeight === undefined) {
        config.chtml.matchFontHeight = false;
    }
    // Adjust the x-height factor. 0.5 works well with sans-serif system fonts.
    // Tweak between 0.45–0.55 if letters like 'a', 'x' don't perfectly align with body text.
    if (config.chtml.exFactor === undefined) {
        config.chtml.exFactor = 0.5;
    }
    if (!config.svg) {
        config.svg = {};
    }
    if (config.svg.matchFontWeight === undefined) {
        config.svg.matchFontWeight = false;
    }
    if (config.svg.matchFontHeight === undefined) {
        config.svg.matchFontHeight = false;
    }

    // Always ensure that ui/safe is in the list. Otherwise, there is a risk of XSS.
    // https://docs.mathjax.org/en/v3.2-latest/options/safe.html.
    if (!config.loader.load.includes('ui/safe')) {
        config.loader.load.push('ui/safe');
    }

    // This filter controls what elements to typeset.
    config.startup.typeset = false;

    // Let's still set the locale even if the localization is not yet ported to version 3.2.2
    // https://docs.mathjax.org/en/v3.2-latest/upgrading/v2.html#not-yet-ported-to-version-3.
    config.locale = params.lang;

    mathJaxUrl = params.mathjaxurl;
    window.MathJax = config;

    // Listen for events triggered when new text is added to a page that needs
    // processing by a filter.
    document.addEventListener(eventTypes.filterContentUpdated, contentUpdated);

    // Enable visible selection highlighting on MathJax formula containers.
    // SVG does not receive the browser's native selection highlight, so we
    // detect overlapping mjx-containers manually and apply a CSS class.
    setupFormulaSelectionHighlight();

    // Intercept copy so formulas are copied as LaTeX commands.
    setupFormulaCopyHandler();
};

/**
 * Add the node to the typeset queue.
 *
 * @param {HTMLElement} node The Node to be processed by MathJax
 * @private
 */
const typesetNode = (node) => {
    if (!(node instanceof HTMLElement)) {
        // We may have been passed a #text node.
        // These cannot be formatted.
        return;
    }

    loadMathJax().then(() => {
        // Chain the calls to typesetPromise as it is recommended.
        // https://docs.mathjax.org/en/v3.2-latest/web/typeset.html#handling-asynchronous-typesetting.
        window.MathJax.startup.promise = window.MathJax.startup.promise
            .then(() => window.MathJax.typesetPromise([node]))
            .then(() => {
                notifyFilterContentRenderingComplete([node]);
            })
            .catch(e => {
                window.console.log(e);
            });
    });
};

/**
 * Called by the filter when an equation is found while rendering the page.
 */
export const typeset = () => {
    const elements = document.getElementsByClassName('filter_mathjaxloader_equation');
    for (const element of elements) {
        typesetNode(element);
    }
};

/**
 * Handle content updated events - typeset the new content.
 *
 * @param {CustomEvent} event - Custom event with "nodes" indicating the root of the updated nodes.
 */
export const contentUpdated = (event) => {
    let listOfElementContainMathJax = [];
    let hasMathJax = false;
    // The list of HTMLElements in an Array.
    event.detail.nodes.forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            // We may have been passed a #text node.
            return;
        }
        const mathjaxElements = node.querySelectorAll('.filter_mathjaxloader_equation');
        if (mathjaxElements.length > 0) {
            hasMathJax = true;
        }
        listOfElementContainMathJax.push(mathjaxElements);
    });

    if (!hasMathJax) {
        return;
    }

    listOfElementContainMathJax.forEach((mathjaxElements) => {
        mathjaxElements.forEach((node) => typesetNode(node));
    });
};

/**
 * Load the MathJax script.
 *
 * @return Promise that is resolved when MathJax was loaded.
 */
export const loadMathJax = () => {
    if (!mathJaxLoaded) {
        if (!mathJaxUrl) {
            return Promise.reject(new Error('URL to MathJax not set.'));
        }

        mathJaxLoaded = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.onload = resolve;
            script.onerror = reject;
            script.src = mathJaxUrl;
            document.getElementsByTagName('head')[0].appendChild(script);
        });
    }
    return mathJaxLoaded;
};
