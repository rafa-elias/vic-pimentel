/**
 * Project listing tabs (cosmos.so-style filters).
 *
 * Tab switching is AJAX (project-listing-ajax.php → admin-ajax), each tab cached after the
 * first load, ?tab=slug kept in the URL. Transition: the active pill slides to the new tab,
 * current cards fade out, skeleton cards show while a request is still in flight, then the
 * new cards fade/rise in with a short stagger.
 */
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const wait = ms => new Promise(resolve => setTimeout(resolve, reducedMotion.matches ? 0 : ms));

window.theme.block('project-listing', el => {
    const tabList = el.querySelector('.listing-tabs');
    const tabs = [...el.querySelectorAll('.js-listing-tab')];
    const grid = el.querySelector('.js-listing-grid');
    const results = el.querySelector('.js-listing-results');
    if (!tabs.length || !grid) return;

    const LEAVE_MS = 200;
    const SKELETON_AFTER_MS = 120;
    const cache = new Map();
    let controller = null;
    let request = 0;

    // Sliding active-pill indicator.
    const indicator = document.createElement('span');
    indicator.className = 'listing-tab-indicator';
    indicator.setAttribute('aria-hidden', 'true');
    tabList.prepend(indicator);
    tabList.classList.add('has-indicator');

    const moveIndicator = (tab, animate = true) => {
        indicator.style.transition = animate ? '' : 'none';
        indicator.style.width = `${tab.offsetWidth}px`;
        indicator.style.transform = `translateX(${tab.offsetLeft}px)`;
    };

    const current = () => tabs.find(tab => tab.classList.contains('is-active')) || tabs[0];
    moveIndicator(current(), false);
    window.addEventListener('resize', () => moveIndicator(current(), false));
    document.fonts?.ready.then(() => moveIndicator(current(), false));

    cache.set(current().dataset.tab, {html: grid.innerHTML, count: grid.children.length});

    const setCount = count => {
        if (!results) return;
        results.classList.add('is-updating');
        setTimeout(() => {
            results.textContent = results.dataset.label.replace('%d', count);
            results.classList.remove('is-updating');
        }, reducedMotion.matches ? 0 : 150);
    };

    const showSkeleton = count => {
        grid.classList.remove('is-leaving');
        grid.innerHTML = Array.from({length: Math.max(1, Math.min(count || 4, 8))}, () =>
            '<li class="listing-skeleton" aria-hidden="true"><span class="skeleton-box"></span><span class="skeleton-line"></span></li>'
        ).join('');
    };

    const render = ({html, count}) => {
        grid.classList.remove('is-leaving');
        grid.innerHTML = html;
        [...grid.children].forEach((card, index) => card.style.setProperty('--i', Math.min(index, 11)));
        grid.classList.add('is-entering');
        setTimeout(() => grid.classList.remove('is-entering'), reducedMotion.matches ? 0 : 900);

        grid.querySelectorAll('.component-media').forEach(media => window.theme.packages.initMedia?.(media));
        setCount(count);
    };

    const fetchTab = key => {
        if (cache.has(key)) return Promise.resolve(cache.get(key));

        controller?.abort();
        controller = new AbortController();

        const body = new FormData();
        body.append('action', 'vp_project_listing');
        body.append('nonce', window.themeAjax.nonce);
        body.append('tab', key);
        body.append('featured', tabList.dataset.featured || '');

        return fetch(window.themeAjax.ajaxUrl, {method: 'POST', body, signal: controller.signal, credentials: 'same-origin'})
            .then(response => response.json())
            .then(json => {
                if (!json.success) throw new Error('listing request failed');
                cache.set(key, json.data);
                return json.data;
            });
    };

    const select = async tab => {
        const id = ++request;
        const previousCount = grid.children.length;

        tabs.forEach(t => {
            const on = t === tab;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
        });
        moveIndicator(tab);

        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab.dataset.slug);
        window.history.replaceState(null, '', url);

        grid.setAttribute('aria-busy', 'true');
        grid.classList.remove('is-entering');
        grid.classList.add('is-leaving');

        const data = fetchTab(tab.dataset.tab);
        let settled = false;
        data.then(() => { settled = true; }, () => { settled = true; });

        await wait(LEAVE_MS);
        if (id !== request) return;

        // Still waiting on the network: show skeleton cards instead of an empty gap.
        if (!settled) {
            await wait(SKELETON_AFTER_MS);
            if (id !== request) return;
            if (!settled) showSkeleton(cache.get(tab.dataset.tab)?.count ?? previousCount);
        }

        try {
            const result = await data;
            if (id === request) render(result);
        } catch (e) {
            if (e.name !== 'AbortError' && id === request) {
                grid.classList.remove('is-leaving');
                console.error(e);
            }
        } finally {
            if (id === request) grid.setAttribute('aria-busy', 'false');
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => {
            if (!tab.classList.contains('is-active')) select(tab);
        });

        // Arrow-key navigation between tabs (WAI-ARIA tabs pattern).
        tab.addEventListener('keydown', event => {
            const dir = {ArrowRight: 1, ArrowLeft: -1}[event.key];
            if (!dir) return;

            event.preventDefault();
            const next = tabs[(index + dir + tabs.length) % tabs.length];
            next.focus();
            select(next);
        });
    });
});
