document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-media-optimizer-restore]');
    if (!root || typeof mediaOptimizerRestore === 'undefined') return;

    const { ajaxUrl, nonce, i18n } = mediaOptimizerRestore;
    const BATCH_SIZE = 3;

    const startButtons = root.querySelectorAll('[data-restore-start]');
    const cancelButton = root.querySelector('[data-restore-cancel]');
    const progressWrap = root.querySelector('[data-restore-progress-wrap]');
    const progressFill = root.querySelector('[data-restore-progress-fill]');
    const status = root.querySelector('[data-restore-status]');
    const resultsTable = root.querySelector('[data-restore-results-table]');
    const resultsBody = root.querySelector('[data-restore-results-body]');
    const statCount = document.querySelector('[data-stat-count]');
    const statSaved = document.querySelector('[data-stat-saved]');

    const confirmText = {
        restore_all: i18n.confirmRestoreAll,
        remove_webp: i18n.confirmRemoveWebp,
    };
    const resultLabel = {
        restore_all: i18n.restored,
        remove_webp: i18n.webpRemoved,
    };

    let cancelled = false;

    function setStatus(text) {
        status.textContent = text;
    }

    function updateStats(stats) {
        if (!stats) return;
        if (statCount) statCount.textContent = stats.count;
        if (statSaved) statSaved.textContent = stats.saved;
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

    function addResultRow(item, type) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(item.title)}</td>
            <td><span class="media-optimizer-badge media-optimizer-badge-success">${resultLabel[type]}</span></td>
        `;
        resultsBody.prepend(row);
    }

    async function postAjax(action, params = {}) {
        const body = new URLSearchParams({ action, nonce, ...params });
        const response = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
        return response.json();
    }

    async function processQueue(ids, type) {
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

            const response = await postAjax('media_optimizer_process_restore_batch', { ...idParams, type });
            const results = response?.data?.results || [];
            results.forEach((item) => addResultRow(item, type));
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

    function setRunning(running) {
        startButtons.forEach((button) => { button.hidden = running; });
        cancelButton.hidden = !running;
    }

    startButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const type = button.dataset.restoreStart;
            if (!window.confirm(confirmText[type])) return;

            cancelled = false;
            setRunning(true);
            progressWrap.hidden = false;
            resultsBody.innerHTML = '';
            setProgress(0, 1);
            setStatus(i18n.fetchingQueue);

            const queueResponse = await postAjax('media_optimizer_get_restore_queue', { type });
            const ids = queueResponse?.data?.ids || [];

            if (ids.length === 0) {
                setStatus(i18n.nothingToDo);
                setRunning(false);
                return;
            }

            await processQueue(ids, type);

            setRunning(false);
        });
    });

    cancelButton.addEventListener('click', () => {
        cancelled = true;
    });
});
