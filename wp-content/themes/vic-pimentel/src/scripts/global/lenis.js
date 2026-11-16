import Lenis from 'lenis';
import isFrontend from './is-frontend';

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

let lenis = null;

if (isFrontend() && !prefersReducedMotion) {
    lenis = new Lenis({
        duration: 1.2, // scroll duration in seconds
        easing: t => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        orientation: 'vertical', // 'vertical' | 'horizontal'
        gestureOrientation: 'vertical', // 'vertical' | 'horizontal' | 'both'
        smoothWheel: true, // smooth mouse wheel scrolling
        syncTouch: false, // smooth touch drags too (native usually feels better on mobile)
        touchMultiplier: 1, // scroll distance per touch-drag pixel
        wheelMultiplier: 1, // scroll distance per wheel tick
        infinite: false,
    });

    function raf(time) {
        lenis.raf(time);
        requestAnimationFrame(raf);
    }

    requestAnimationFrame(raf);
}

window.theme.registerPackages({
    lenis
});
