document.addEventListener('DOMContentLoaded', () => {
    if (typeof mediaOptimizerAdminBar === 'undefined') return;

    const { ajaxUrl, nonce, i18n } = mediaOptimizerAdminBar;
    const BATCH_SIZE = 3;

    const node = document.getElementById('wp-admin-bar-media-optimizer-process-now');
    if (!node) return;

    const link = node.querySelector('.ab-item');

    async function postAjax(action, params = {}) {
        const body = new URLSearchParams({ action, nonce, ...params });
        const response = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
        return response.json();
    }

    link.addEventListener('click', async (event) => {
        event.preventDefault();
        if (node.classList.contains('is-processing')) return;

        node.classList.add('is-processing');
        link.textContent = i18n.processing;

        const queueResponse = await postAjax('media_optimizer_get_queue', { force: '' });
        const ids = queueResponse?.data?.ids || [];

        for (let i = 0; i < ids.length; i += BATCH_SIZE) {
            const batch = ids.slice(i, i + BATCH_SIZE);
            const idParams = {};
            batch.forEach((id, index) => { idParams[`ids[${index}]`] = id; });

            await postAjax('media_optimizer_process_batch', idParams);
            link.textContent = `${i18n.processing} (${Math.min(i + BATCH_SIZE, ids.length)}/${ids.length})`;
        }

        link.textContent = i18n.done;
        setTimeout(() => window.location.reload(), 800);
    });
});
