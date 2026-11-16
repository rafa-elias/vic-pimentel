import isFrontend from '../../../../scripts/global/is-frontend';

/**
 * Hide the nav pill while scrolling down, show it again on scroll up (adidas.it pattern).
 * Always visible near the top of the page and while it has keyboard focus.
 */
window.theme.component('header', el => {
    if (!isFrontend()) return;

    const nav = el.querySelector('.js-header-nav');
    if (!nav) return;

    const THRESHOLD = 8;
    const TOP_ZONE = 120;
    let last = window.scrollY;
    let ticking = false;

    const update = () => {
        ticking = false;
        const y = window.scrollY;
        const delta = y - last;

        if (y < TOP_ZONE || nav.contains(document.activeElement)) {
            nav.classList.remove('is-hidden');
        } else if (Math.abs(delta) > THRESHOLD) {
            nav.classList.toggle('is-hidden', delta > 0);
        } else {
            return;
        }

        last = y;
    };

    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(update);
    }, {passive: true});

    nav.addEventListener('focusin', () => nav.classList.remove('is-hidden'));
});
