/**
 * Image + optional looping video (see media.php).
 * The video URL stays in data-src until it's actually needed — preload="none" alone is
 * not reliably honoured, so this is what really keeps unplayed videos off the network.
 */
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

function initMedia(el) {
    if (el.dataset.mediaReady) return;
    el.dataset.mediaReady = '1';

    const video = el.querySelector('.js-media-video');
    if (!video) return;

    const source = video.querySelector('source[data-src]');
    const toggle = el.querySelector('.js-media-toggle');
    const mode = el.classList.contains('mode-hover') ? 'hover' : 'autoplay';
    let loaded = false;
    let userPaused = false;
    let inView = false;

    const load = () => {
        if (loaded || !source) return;
        source.src = source.dataset.src;
        video.load();
        loaded = true;
    };

    const play = () => {
        load();
        const attempt = video.play();
        if (attempt && attempt.catch) attempt.catch(() => {});
    };

    const pause = () => video.pause();

    const setState = playing => {
        el.classList.toggle('is-playing', playing);

        if (toggle) {
            toggle.setAttribute('aria-label', playing ? toggle.dataset.labelPause : toggle.dataset.labelPlay);
            toggle.setAttribute('aria-pressed', playing ? 'true' : 'false');
        }
    };

    video.addEventListener('playing', () => setState(true));
    video.addEventListener('pause', () => setState(false));

    if (toggle) {
        toggle.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();

            if (video.paused) {
                userPaused = false;
                play();
            } else {
                userPaused = true;
                pause();
            }
        });
    }

    if (mode === 'hover') {
        const root = el.closest('.js-media-hover') || el;

        root.addEventListener('pointerenter', event => {
            if (event.pointerType === 'touch' || userPaused) return;
            play();
        });

        root.addEventListener('pointerleave', event => {
            if (event.pointerType === 'touch') return;
            pause();
        });

        // Warm the first frame up when the card is close to the viewport.
        new IntersectionObserver((entries, observer) => {
            if (!entries[0].isIntersecting) return;
            load();
            observer.disconnect();
        }, {rootMargin: '200px 0px'}).observe(el);

        return;
    }

    // Autoplay while in view (unless the visitor prefers reduced motion).
    new IntersectionObserver(([entry]) => {
        inView = entry.isIntersecting;

        if (inView && !userPaused && !reducedMotion.matches && !el.closest('dialog:not([open])')) {
            play();
        } else if (!inView) {
            pause();
        }
    }, {rootMargin: '100px 0px'}).observe(el);

    // Allow a parent (e.g. the media modal) to (re)start/stop playback.
    el.addEventListener('media:play', () => {
        userPaused = false;
        play();
    });

    el.addEventListener('media:pause', pause);
}

window.theme.component('media', initMedia);
window.theme.registerPackages({initMedia});
