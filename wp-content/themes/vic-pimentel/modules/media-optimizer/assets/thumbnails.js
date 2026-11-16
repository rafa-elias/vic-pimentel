document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-media-optimizer-thumbnails]');
    if (!root || typeof mediaOptimizerThumbnails === 'undefined') return;

    const { ajaxUrl, nonce, i18n } = mediaOptimizerThumbnails;
    const BATCH_SIZE = 3;

    const startButton = root.querySelector('[data-bulk-start]');
    const cancelButton = root.querySelector('[data-bulk-cancel]');
    const progressWrap = root.querySelector('[data-bulk-progress-wrap]');
    const progressFill = root.querySelector('[data-bulk-progress-fill]');
    const status = root.querySelector('[data-bulk-status]');
    const resultsTable = root.querySelector('[data-bulk-results-table]');
    const resultsBody = root.querySelector('[data-bulk-results-body]');
    const skipCorrectCheckbox = root.querySelector('[data-thumb-skip-correct]');
    const deleteStaleCheckbox = root.querySelector('[data-thumb-delete-stale]');

    let cancelled = false;
    let batchOptions = {};

    function setStatus(text) {
        status.textContent = text;
    }

    function setProgress(done, total) {
        const percent = total > 0 ? Math.round((done / total) * 100) : 0;
        progressFill.style.width = `${percent}%`;
    }

    function formatDuration(seconds) {
        if (seconds < 60) return `${Math.ceil(seconds)}s`;
        const minutes = Math.floor(seconds / 60);
        const remSeconds = Math.round(seconds % 60);
        return `${minutes}m ${remSeconds}s`;
    }

    // Estimated from this run's own average time-per-image so far, not a
    // fixed guess — see optimize.js for the same pattern and reasoning.
    function estimateRemaining(startTime, done, total) {
        if (done === 0 || done >= total) return '';
        const elapsedSeconds = (Date.now() - startTime) / 1000;
        const remainingSeconds = (elapsedSeconds / done) * (total - done);
        return ' ' + i18n.timeRemaining.replace('%s', formatDuration(remainingSeconds));
    }

    // Attachment/post titles are attacker-influenceable text (any role that
    // can set one), never trusted HTML — escaped before ever reaching
    // innerHTML, regardless of what WordPress core's own save-time filtering
    // already does for a given user role.
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function addResultRow(item) {
        const row = document.createElement('tr');
        const labels = { regenerated: i18n.regenerated, skipped: i18n.skipped, failed: i18n.failed };
        const badgeClasses = { regenerated: 'success', skipped: 'neutral', failed: 'error' };
        const label = labels[item.status] || i18n.failed;
        const badgeClass = badgeClasses[item.status] || 'error';

        const thumbCell = item.thumbnail
            ? `<img src="${item.thumbnail}" alt="" width="32" height="32" class="media-optimizer-result-thumb">`
            : '';

        const details = [];
        if (item.regenerated_sizes && item.regenerated_sizes.length) {
            details.push(`${i18n.regeneratedSizes}: ${item.regenerated_sizes.map(escapeHtml).join(', ')}`);
        }
        if (item.deleted_sizes && item.deleted_sizes.length) {
            details.push(`${i18n.deletedSizes}: ${item.deleted_sizes.map(escapeHtml).join(', ')}`);
        }

        row.innerHTML = `
            <td>${thumbCell} ${escapeHtml(item.title)}</td>
            <td><span class="media-optimizer-badge media-optimizer-badge-${badgeClass}">${label}</span></td>
            <td class="media-optimizer-details">${details.join('<br>')}</td>
        `;

        resultsBody.prepend(row);
    }

    async function postAjax(action, params = {}) {
        const body = new URLSearchParams({ action, nonce, ...params });
        const response = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
        return response.json();
    }

    async function processQueue(ids) {
        let done = 0;
        resultsTable.hidden = false;
        const startTime = Date.now();

        for (let i = 0; i < ids.length; i += BATCH_SIZE) {
            if (cancelled) {
                setStatus(i18n.cancelled);
                return;
            }

            const batch = ids.slice(i, i + BATCH_SIZE);
            const idParams = {};
            batch.forEach((id, index) => { idParams[`ids[${index}]`] = id; });

            const response = await postAjax('media_optimizer_thumbnails_process_batch', { ...idParams, ...batchOptions });
            const results = response?.data?.results || [];
            results.forEach(addResultRow);

            done += batch.length;
            setProgress(done, ids.length);
            setStatus(
                i18n.processing.replace('%1$d', done).replace('%2$d', ids.length)
                + estimateRemaining(startTime, done, ids.length)
            );
        }

        if (!cancelled) {
            setStatus(i18n.done.replace('%d', ids.length));
        }
    }

    startButton.addEventListener('click', async () => {
        cancelled = false;
        batchOptions = {
            skip_if_correct: skipCorrectCheckbox.checked ? '1' : '',
            delete_stale: deleteStaleCheckbox.checked ? '1' : '',
        };
        startButton.hidden = true;
        cancelButton.hidden = false;
        progressWrap.hidden = false;
        resultsBody.innerHTML = '';
        setProgress(0, 1);
        setStatus(i18n.fetchingQueue);

        const queueResponse = await postAjax('media_optimizer_thumbnails_get_queue');
        const ids = queueResponse?.data?.ids || [];

        if (ids.length === 0) {
            setStatus(i18n.nothingToDo);
            startButton.hidden = false;
            cancelButton.hidden = true;
            return;
        }

        await processQueue(ids);

        startButton.hidden = false;
        cancelButton.hidden = true;
    });

    cancelButton.addEventListener('click', () => {
        cancelled = true;
    });
});
