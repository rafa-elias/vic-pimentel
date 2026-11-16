/**
 * Copy the email to the clipboard, show "Copied" for 2s, then revert (designer spec).
 * Falls back to the mailto: link when the Clipboard API is unavailable.
 */
const RESET_AFTER = 2000;

async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return true;
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    const ok = document.execCommand('copy');
    field.remove();

    return ok;
}

window.theme.component('copy-email', el => {
    const trigger = el.querySelector('.js-copy-email');
    const label = el.querySelector('.js-copy-label');
    const status = el.querySelector('.js-copy-status');
    if (!trigger || !label) return;

    const original = label.textContent;
    let timer = null;

    trigger.addEventListener('click', async event => {
        event.preventDefault();

        let copied = false;

        try {
            copied = await copyText(el.dataset.email);
        } catch (e) {
            copied = false;
        }

        if (!copied) {
            window.location.href = `mailto:${el.dataset.email}`;
            return;
        }

        el.classList.add('is-copied');
        label.textContent = el.dataset.copied;
        if (status) status.textContent = el.dataset.copied;

        clearTimeout(timer);
        timer = setTimeout(() => {
            el.classList.remove('is-copied');
            label.textContent = original;
            if (status) status.textContent = '';
        }, RESET_AFTER);
    });
});
