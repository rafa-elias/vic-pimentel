export default {
    name: 'iris-reveal',

    // Opacity stays 1 throughout — only the circular mask grows — but it
    // still has to be set explicitly on both sides: common/
    // _critical-overlays.scss pre-hides element.node via a plain opacity: 0
    // rule, and nothing else here would ever override that back to 1.
    initial: {
        opacity: 1,
        clipPath: 'circle(0% at 50% 50%)',
    },

    completed: {
        opacity: 1,
        // >100% guarantees full coverage regardless of the element's aspect
        // ratio — a circle's percentage radius is relative to a diagonal
        // measure, not simply half the box's width/height.
        clipPath: 'circle(150% at 50% 50%)',
    },

    defaultOptions: {
        duration: 0.7,
        easing: 'power2.inOut',
    },
};
