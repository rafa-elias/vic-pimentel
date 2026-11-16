document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-media-optimizer-bulk]');
    if (!root || typeof mediaOptimizerBulk === 'undefined') return;

    const { ajaxUrl, nonce, i18n } = mediaOptimizerBulk;
    const BATCH_SIZE = 3;

    const forceCheckbox = root.querySelector('[data-bulk-force]');
    const startButton = root.querySelector('[data-bulk-start]');
    const cancelButton = root.querySelector('[data-bulk-cancel]');
    const progressWrap = root.querySelector('[data-bulk-progress-wrap]');
    const progressFill = root.querySelector('[data-bulk-progress-fill]');
    const status = root.querySelector('[data-bulk-status]');
    const resultsTable = root.querySelector('[data-bulk-results-table]');
    const resultsBody = root.querySelector('[data-bulk-results-body]');
    const statCount = document.querySelector('[data-stat-count]');
    const statSaved = document.querySelector('[data-stat-saved]');

    let cancelled = false;

    function updateStats(stats) {
        if (!stats) return;
        if (statCount) statCount.textContent = stats.count;
        if (statSaved) statSaved.textContent = stats.saved;
    }

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

    // Estimated from this run's own average time-per-image so far rather
    // than a fixed guess — host speed and image size vary too much for a
    // static number to mean anything, but the real running average gets more
    // accurate as the batch progresses.
    function estimateRemaining(startTime, done, total) {
        if (done === 0 || done >= total) return '';
        const elapsedSeconds = (Date.now() - startTime) / 1000;
        const remainingSeconds = (elapsedSeconds / done) * (total - done);
        return ' ' + i18n.timeRemaining.replace('%s', formatDuration(remainingSeconds));
    }

    function formatBytes(bytes) {
        if (!bytes && bytes !== 0) return '—';
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    function statusLabel(item) {
        if (item.status === 'skipped') return i18n.skipped;
        if (item.status === 'failed' || item.status === 'unsupported') return i18n.failed;
        return i18n.optimized;
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

        const savings = item.savings;
        const before = savings ? formatBytes(savings.original_bytes) : '—';
        const after = savings ? formatBytes(savings.optimized_bytes) : '—';
        const saved = savings ? `-${savings.percent}%` : '—';

        const thumbCell = item.thumbnail
            ? `<img src="${item.thumbnail}" alt="" width="32" height="32" class="media-optimizer-result-thumb">`
            : '';

        row.innerHTML = `
            <td>${thumbCell} ${escapeHtml(item.title)}</td>
            <td><span class="media-optimizer-badge media-optimizer-badge-${item.status === 'optimized' ? 'success' : (item.status === 'failed' ? 'error' : 'neutral')}">${statusLabel(item)}</span></td>
            <td>${before}</td>
            <td>${after}</td>
            <td>${saved}</td>
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

            const response = await postAjax('media_optimizer_process_batch', idParams);
            const results = response?.data?.results || [];
            results.forEach(addResultRow);
            updateStats(response?.data?.stats);

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
        startButton.hidden = true;
        cancelButton.hidden = false;
        progressWrap.hidden = false;
        resultsBody.innerHTML = '';
        setProgress(0, 1);
        setStatus(i18n.fetchingQueue);

        const queueResponse = await postAjax('media_optimizer_get_queue', {
            force: forceCheckbox.checked ? '1' : '',
        });

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
