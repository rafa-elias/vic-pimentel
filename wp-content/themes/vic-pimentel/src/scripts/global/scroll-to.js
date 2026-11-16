import isFrontend from './is-frontend';

window.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.component-header');
    const HASH_SCROLL_DELAY = 500; // lets late-loading fonts/images settle before offsets are measured

    function getHeaderHeight() {
        return header ? header.clientHeight : 0;
    }

    function smoothScrollTo(targetId) {
        const targetSection = document.getElementById(targetId);
        if (!targetSection) return;

        const lenis = window.theme.packages.lenis;
        const offset = -getHeaderHeight();

        if (lenis) {
            lenis.scrollTo(targetSection, {offset});
        } else {
            const offsetTop = targetSection.getBoundingClientRect().top + window.scrollY + offset;
            window.scrollTo({behavior: 'smooth', top: offsetTop});
        }
    }

    function handlePageLoadWithHash() {
        const initialHash = window.location.hash;
        if (!initialHash) return;

        window.history.replaceState(null, null, ' ');
        window.scrollTo(0, 0);
        setTimeout(() => {
            const targetId = initialHash.substring(1);
            window.history.replaceState(null, null, initialHash);
            smoothScrollTo(targetId);
        }, HASH_SCROLL_DELAY);
    }

    function handleLinkClick(event, targetId) {
        if (!document.getElementById(targetId)) return;

        event.preventDefault();
        window.history.pushState(null, null, `#${targetId}`);
        smoothScrollTo(targetId);
    }

    function initializeLinks() {
        document.addEventListener('click', event => {
            const link = event.target.closest('a[href*="#"]');
            if (!link) return;

            const targetId = link.getAttribute('href').split('#')[1];
            if (!targetId) return;

            handleLinkClick(event, targetId);
        });
    }

    function initialize() {
        if (!isFrontend()) return;

        handlePageLoadWithHash();
        initializeLinks();
        window.addEventListener('hashchange', () => {
            const targetId = window.location.hash.substring(1);
            smoothScrollTo(targetId);
        });
    }

    initialize();
});
