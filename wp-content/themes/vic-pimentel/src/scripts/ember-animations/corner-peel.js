// Every corner's hidden state is a 4-point polygon collapsed onto that
// corner (not a shorter 1-point path) — GSAP needs matching vertex counts on
// both sides of a clip-path tween to interpolate it, the same lesson learned
// fixing mask-wipe.js and slide-words.js. With all 4 points starting at the
// same corner and moving independently to the full rectangle's 4 corners,
// the shape visibly unfurls diagonally rather than sliding on one axis.
const CORNER_PATHS = {
    'top-left': 'polygon(0% 0%, 0% 0%, 0% 0%, 0% 0%)',
    'top-right': 'polygon(100% 0%, 100% 0%, 100% 0%, 100% 0%)',
    'bottom-left': 'polygon(0% 100%, 0% 100%, 0% 100%, 0% 100%)',
    'bottom-right': 'polygon(100% 100%, 100% 100%, 100% 100%, 100% 100%)',
};

export default {
    name: 'corner-peel',

    initial: ({options}) => ({
        opacity: 0.5,
        clipPath: CORNER_PATHS[options.corner] || CORNER_PATHS['top-left'],
    }),

    completed: {
        opacity: 1,
        clipPath: 'polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%)',
    },

    defaultOptions: {
        corner: 'top-left',
        duration: 0.6,
        easing: 'power2.out',
    },
};
