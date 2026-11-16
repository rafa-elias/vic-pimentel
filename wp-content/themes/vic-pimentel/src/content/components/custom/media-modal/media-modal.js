/**
 * Media modal — native <dialog>. Triggers: [data-media-modal="{dialog id}"].
 * Wired once at document level so triggers rendered anywhere (or later) work.
 */
let wired = false;

function lenis() {
    return window.theme.packages.lenis || null;
}

function openModal(dialog) {
    if (dialog.open) return;

    dialog.showModal();
    lenis()?.stop();

    const media = dialog.querySelector('.component-media');
    media?.dispatchEvent(new CustomEvent('media:play'));
}

function wire() {
    if (wired) return;
    wired = true;

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-media-modal]');

        if (trigger) {
            const dialog = document.getElementById(trigger.dataset.mediaModal);
            if (dialog) openModal(dialog);
            return;
        }

        if (event.target.closest('.js-media-modal-close')) {
            event.target.closest('dialog')?.close();
        }
    });
}

window.theme.component('media-modal', dialog => {
    wire();

    // Click on the backdrop (the dialog itself, outside the panel) closes it.
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });

    dialog.addEventListener('close', () => {
        dialog.querySelector('.component-media')?.dispatchEvent(new CustomEvent('media:pause'));
        lenis()?.start();
    });
});
