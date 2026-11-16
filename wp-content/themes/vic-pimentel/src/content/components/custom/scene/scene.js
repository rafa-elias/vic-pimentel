import isFrontend from '../../../../scripts/global/is-frontend';
import {startFlow} from '../../../../scripts/global/scene-flow';

/**
 * Lazily loads the Unicorn Studio SDK the first time a configured scene approaches the
 * viewport, then lets it render every [data-us-project-src] element. The static CSS
 * fallback stays until the canvas exists; reduced-motion visitors keep the fallback.
 */
let sdkPromise = null;

/*
 * Cap for Unicorn "while scrolling" events. The runtime has no maximum: each frame adds
 * (scroll delta × event.delta), so a very fast flick drives the property far past the
 * authored target (the About light went blinding). We swap each such event's `delta` for
 * a getter that scales down on big frames so one frame never contributes more than
 * SCROLL_PULSE_MAX. Measured on the About light: normal wheel scrolling peaks ~4.3
 * (untouched), an uncapped fast flick hit ~75 (blinding). Raise/lower this number to
 * allow a brighter/dimmer maximum.
 */
const SCROLL_PULSE_MAX = 10;
let frameDelta = 0;

function trackScrollDelta() {
    if (window.__vpScrollDeltaTracked) return;
    window.__vpScrollDeltaTracked = true;

    const lenis = window.theme.packages.lenis;
    let last = window.scrollY;

    const update = y => {
        frameDelta = y - last;
        last = y;
    };

    if (lenis) {
        lenis.on('scroll', ({scroll}) => update(scroll));
    } else {
        window.addEventListener('scroll', () => update(window.scrollY), {passive: true});
    }
}

function capScrollEvents(sdk) {
    (sdk.scenes || []).forEach(scene => {
        (scene.layers || []).forEach(layer => {
            (layer.states?.scroll || []).forEach(event => {
                if (event.mode !== 'whileScrolling' || event.__vpCapped) return;

                const base = Number(event.delta) || 0.01;
                event.__vpCapped = true;

                Object.defineProperty(event, 'delta', {
                    configurable: true,
                    get: () => {
                        const d = Math.abs(frameDelta);
                        return d > 0 ? Math.min(base, SCROLL_PULSE_MAX / d) : base;
                    },
                    set: () => {},
                });
            });
        });
    });
}

function loadSdk(url) {
    if (window.UnicornStudio) return Promise.resolve(window.UnicornStudio);
    if (sdkPromise) return sdkPromise;

    sdkPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = url;
        script.async = true;
        script.onload = () => resolve(window.UnicornStudio);
        script.onerror = reject;
        document.head.appendChild(script);
    });

    return sdkPromise;
}

window.theme.component('scene', el => {
    // Editor canvas / reduced motion: keep the static stand-in instead of the live scene.
    if (!isFrontend() || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        el.classList.add('is-fallback');
        return;
    }

    if (el.classList.contains('has-flow')) {
        startFlow(el, el.dataset.key || 'hero', el.dataset.interactive === '1');
        return;
    }

    if (!el.classList.contains('has-scene')) return;

    const canvasHost = el.querySelector('.js-scene-canvas');
    if (!canvasHost) return;

    const observer = new IntersectionObserver(([entry]) => {
        if (!entry.isIntersecting) return;
        observer.disconnect();

        loadSdk(el.dataset.sdk)
            .then(sdk => {
                if (!sdk) return null;

                // Track the per-frame scroll delta first, so it's updated before Unicorn reads it.
                trackScrollDelta();

                // Feed Lenis' scroll position to the scroll-driven events (Unicorn docs).
                const lenis = window.theme.packages.lenis;
                if (lenis && sdk.setScroll && !window.__vpUnicornScroll) {
                    window.__vpUnicornScroll = true;
                    lenis.on('scroll', ({scroll}) => sdk.setScroll(scroll));
                }

                return Promise.resolve(sdk.init ? sdk.init() : null).then(() => capScrollEvents(sdk));
            })
            .then(() => {
                // Reveal once the SDK has injected its canvas.
                const reveal = () => el.classList.add('is-ready');
                if (canvasHost.querySelector('canvas')) return reveal();

                new MutationObserver((records, mo) => {
                    if (canvasHost.querySelector('canvas')) {
                        mo.disconnect();
                        reveal();
                    }
                }).observe(canvasHost, {childList: true, subtree: true});
            })
            .catch(() => el.classList.add('is-fallback'));
    }, {rootMargin: '200px 0px'});

    observer.observe(el);
});
