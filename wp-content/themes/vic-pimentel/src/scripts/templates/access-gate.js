/**
 * Portfolio gate.
 * - Login: checked over AJAX, so a wrong password shows the message inline (no reload).
 *   Success navigates to the "confidential" loading screen. Without JS the form still
 *   posts to admin-post.php.
 * - Loading: hold for the configured seconds (the ring fills), then continue.
 */
document.addEventListener('DOMContentLoaded', () => {
    const loading = document.querySelector('.js-gate-loading');

    if (loading) {
        const seconds = parseInt(loading.dataset.seconds, 10) || 4;
        setTimeout(() => window.location.replace(loading.dataset.target), seconds * 1000);
    }

    const form = document.querySelector('.js-gate-form');
    if (!form || !window.fetch) return;

    const input = form.querySelector('input[name="vp_password"]');
    const button = form.querySelector('button[type="submit"]');
    const error = form.querySelector('.js-gate-error');

    const showError = message => {
        error.textContent = message;
        error.hidden = false;
        input.setAttribute('aria-invalid', 'true');
        input.classList.remove('has-error');
        void input.offsetWidth; // restart the shake animation
        input.classList.add('has-error');
        input.select();
    };

    input.addEventListener('input', () => {
        input.classList.remove('has-error');
        input.removeAttribute('aria-invalid');
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        if (!input.value.trim()) {
            input.focus();
            return;
        }

        const body = new FormData(form);
        body.set('action', form.querySelector('input[name="action"]').value);
        button.disabled = true;

        try {
            const response = await fetch(form.dataset.ajaxUrl, {method: 'POST', body, credentials: 'same-origin'});
            const json = await response.json();

            if (json.success) {
                window.location.assign(json.data.redirect);
                return;
            }

            showError(json.data?.message || error.textContent);
        } catch (e) {
            form.submit(); // network/JSON trouble: fall back to the classic post
            return;
        }

        button.disabled = false;
    });
});
