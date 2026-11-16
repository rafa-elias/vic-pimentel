'use strict';

/**
 * Push information to initialize javascript in the admin.
 *
 * @returns {Readonly<{initBlock: initBlock, isAdmin: boolean, onInitBlock: onInitBlock}>}
 */
function buildInitializer() {
    const blockInitListeners = [];

    return Object.freeze({
        initBlock,
        onInitBlock,
        // Getter: initializerData's <script> can be printed after this deferred file.
        get isAdmin() {
            const data = window.initializerData || {};
            return data.isAdmin === '1' || data.isAdmin === true;
        },
    });

    /**
     * Initialize a block.
     *
     * @param {String} name
     * @param {String} id
     * @param {Object|null} data
     */
    function initBlock(name, id, data = null) {
        blockInitListeners.forEach(l => l(name, id, data));
    }

    /**
     * Do something when a block is initialized.
     *
     * @param callback
     */
    function onInitBlock(callback) {
        blockInitListeners.push(callback);
    }
}

window.initializer = buildInitializer()

// This file is meant to clean up the admin editor, removing unnecessary styles.
// It is loaded in the admin editor only.
window.addEventListener('load', () => {
    const editorStylesWrapper = document.querySelector('.editor-styles-wrapper');

    // Remove the style tags that are siblings of the editor-styles-wrapper div.
    if (editorStylesWrapper) {
        const siblings = Array.from(editorStylesWrapper.parentNode.children);
        siblings.filter(s => s.tagName === 'STYLE').forEach(s => s.remove());
    }
});
