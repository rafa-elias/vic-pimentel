import Ember from 'ember-js';
import {gsap} from 'gsap';
import {ScrollTrigger} from 'gsap/ScrollTrigger';

// Uncomment the following line to import an animation from Ember.
import fadeMotionEntrance from '../ember-animations/fade-motion';
import fadeEntrance from '../ember-animations/fade';
import fadeLetters from '../ember-animations/fade-letters';
import fadeWords from '../ember-animations/fade-words';
import roll from '../ember-animations/roll';
import slideWords from '../ember-animations/slide-words';
import blurFade from '../ember-animations/blur-fade';
import scaleFade from '../ember-animations/scale-fade';
import rotateIn from '../ember-animations/rotate-in';
import maskWipe from '../ember-animations/mask-wipe';
import skewFade from '../ember-animations/skew-fade';
import elasticPop from '../ember-animations/elastic-pop';
import glitchScramble from '../ember-animations/glitch-scramble';
import lineReveal from '../ember-animations/line-reveal';
import typewriter from '../ember-animations/typewriter';
import highlightSweep from '../ember-animations/highlight-sweep';
import flipIn from '../ember-animations/flip-in';
import springSlide from '../ember-animations/spring-slide';
import staggerPop from '../ember-animations/stagger-pop';
import irisReveal from '../ember-animations/iris-reveal';
import waveLetters from '../ember-animations/wave-letters';
import focusPull from '../ember-animations/focus-pull';
import cornerPeel from '../ember-animations/corner-peel';
import underlineGrow from '../ember-animations/underline-grow';
import letterFlip from '../ember-animations/letter-flip';

// Initialize Ember.
window.ember = new Ember();
window.gsap = gsap;

// Register ScrollTrigger — background-media.js's parallax effect
// (`gsap.to(target, {..., scrollTrigger: {...}})`) needs this registered or
// GSAP silently treats `scrollTrigger` as an invalid property (logs "Missing
// plugin? gsap.registerPlugin()" to the console) and ignores it — the tween
// still runs, just as a plain one-off animation to its end state instead of
// a scroll-scrubbed one, which is what caused the background image to visibly
// jump on page load with no actual parallax motion while scrolling. Confirmed
// via git history this was never registered, since background-media.js's
// parallax code was first added — not a regression from a recent change.
gsap.registerPlugin(ScrollTrigger);

// Register Ember animation types.
window.ember.registerAnimations('entrance', [
    fadeMotionEntrance,
    fadeEntrance,
    fadeLetters,
    fadeWords,
    roll,
    slideWords,
    blurFade,
    scaleFade,
    rotateIn,
    maskWipe,
    skewFade,
    elasticPop,
    glitchScramble,
    lineReveal,
    typewriter,
    highlightSweep,
    flipIn,
    springSlide,
    staggerPop,
    irisReveal,
    waveLetters,
    focusPull,
    cornerPeel,
    underlineGrow,
    letterFlip,
]);

// Set Ember animation defaults.
window.ember.setDefaults({
    /**
     * The delay in milliseconds between each element being animated.
     */
    delayBetween: 200,

    /**
     * The offset in pixels from the bottom of the viewport before an element is animated.
     */
    rootMargin: '0px 0px -200px 0px',

    /**
     * Use the following to set the default animation categories.
     * These are useful for determining which animations to use for different element types.
     *
     * Using the default animation for a given category is as simple as appending the category
     * to the "ember-entrance" class on the element, e.g. "ember-entrance--alt".
     *
     * By default, all elements will use the "primary" animation, even if no category is specified.
     * E.g. if the element has the "ember-entrance" class, it will use the "primary" animation.
     */
    defaultAnimations: {
        // Do not remove the primary category, as it is used as a fallback.
        primary: 'fade-motion',
        static: 'fade',
        letters: 'fade-letters',
        words: 'fade-words',
        roll: 'roll',
        slideWords: 'slide-words',
    },

    /**
     * Animation options are applied based on priority. The list below shows the order
     * in which options are applied, with the highest priority at the top.
     *
     * If an option is found in one of the following places, it will be used and the search will stop.
     *
     * 1. Animation options provided in the element (HTML attributes following the pattern: [data-entrance-anim-{option}]).
     * 2. Global defaults for this animation (see perAnimationOptions).
     * 3. Animation defaults (see defaultOptions in each animation declaration).
     * 4. Global defaults for all animations (see animationOptions).
     */

    /**
     * The following options are used as a last resort if no other options are provided.
     *
     * This is also a good place to set options that all animations should use, ensuring
     * consistency across the site.
     *
     * All options you can set here can also be set per animation (see perAnimationOptions).
     */
    animationOptions: {
        /**
         * The duration in seconds for all animations.
         */
        duration: 0.3,

        /**
         * The offset in pixels to start a motion animation from.
         *
         * For example, the "fade-motion" animation will start from 50px below the bottom of the viewport.
         * If you change the animation direction to "right", the animation will start from 50px to the left of the viewport.
         */
        offset: 50,

        /**
         * The easing function to use for all animations.
         *
         * Possible values can be found here: https://gsap.com/docs/v3/Eases
         */
        easing: 'power2.out',
    },

    /**
     * The following options are applied per animation.
     *
     * This is a good place to set options that all animations of a given type should use,
     * ensuring consistency across the site.
     */
    perAnimationOptions: {
        entrance: {
            'fade': {
                duration: 0.5,
            },
            'fade-motion': {
                duration: 0.6,
            },
        },
    },
});

// Initialize Ember when the DOM is ready.
window.addEventListener('DOMContentLoaded', () => {
    // Frontend only — in the editor iframe Ember would hide every entrance element and never reveal it.
    if (!document.body.classList.contains('is-frontend')) {
        return;
    }

    /**
     * The first block on the page is almost always the LCP element, and Chrome
     * doesn't count an opacity:0 element as "painted" for LCP purposes — so on
     * mobile, skip Ember for it entirely rather than making it wait out the full
     * choreography (this script's own load/parse + the ~300ms observer delay in
     * _observeEntranceElements() + each element's delay + tween duration).
     *
     * common/_critical-overlays.scss handles the opacity side (skips the pre-hide
     * for the first block's entrance elements below the 'mobile-large' breakpoint), but
     * that alone isn't enough: newer animation types also hide via
     * transform/filter/rotate/skew/clip-path, applied by Ember's own 'initial'
     * phase a moment after this fires. Removing just the group class here, before
     * ember.init() runs, is enough to stop that: _gatherEntranceElements() (see
     * ember-js/modules/ember-entrances-module.js) only ever discovers entrance
     * elements by querying .ember-entrance-group at the top level and then
     * looking for .ember-entrance descendants WITHIN each matched group — there's
     * no separate document-wide query for .ember-entrance itself, so once the
     * group is gone those descendants are simply never reached, whatever classes
     * they still carry. Desktop is untouched; keep the 768px check in sync with
     * the 'mobile-large' breakpoint this class references in SCSS.
     */
    if (window.innerWidth < 600) {
        const firstBlock = document.querySelector('.block');

        if (firstBlock) {
            firstBlock.querySelectorAll('.ember-entrance-group').forEach(group => {
                group.classList.remove('ember-entrance-group');
            });
        }
    }

    /**
     * common/_critical-overlays.scss pre-hides every .ember-entrance-group
     * element (opacity: 0) so the page never flashes fully visible before
     * Ember applies its own initial animation state. But ember-js's own
     * _buildEntranceGroup() skips (never animates, never processes at all) a
     * group whose top is already above the viewport at this exact point —
     * e.g. scroll position restored mid-page on a refresh or back/forward
     * navigation. Left alone, those elements never get a single gsap.set()
     * call, so they'd stay stuck on the pre-hide CSS's opacity: 0 forever.
     *
     * Mirror that same skip check here, before ember.init() runs, and for
     * every element in a skipped group manually replay both animation
     * phases (initial then completed) instead of just patching opacity back
     * on. A bare opacity fix was enough for the original fade/fade-motion
     * animations (opacity was their only hidden property), but newer
     * animations also hide via filter/scale/rotation/skew/clip-path — an
     * opacity-only patch left those stuck mid-transform (blurred, scaled
     * down, rotated, clipped) instead of resolving to their real, finished
     * look. Replaying both phases resolves every property the same way the
     * normal IntersectionObserver path would have.
     */
    document.querySelectorAll('.ember-entrance-group').forEach(group => {
        if (group.getBoundingClientRect().top >= 0) return;

        group.querySelectorAll('.ember-entrance, [class*=" ember-entrance--"]').forEach(el => {
            let anim = el.dataset.entranceAnim;

            if (!anim) {
                const emberClass = Array.from(el.classList).find(c => c.startsWith('ember-entrance--'));
                const category = emberClass ? emberClass.replace('ember-entrance--', '') : 'primary';
                anim = ember.defaults.defaultAnimations[category] || ember.defaults.defaultAnimations.primary;
            }

            const element = {node: el, anim, delay: 0};

            ember.animations.processAnimationPhase('entrance', element, 'initial');
            ember.animations.processAnimationPhase('entrance', element, 'completed');
        });
    });

    ember.init();
});