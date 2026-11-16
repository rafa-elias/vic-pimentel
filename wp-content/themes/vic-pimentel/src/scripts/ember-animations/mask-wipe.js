// All four values use "%" on both sides (here and in `completed` below) —
// GSAP needs matching units at each clip-path position to interpolate it
// smoothly; mixing "100%" with a unitless "0" makes it snap instead of tween.
const CLIP_PATHS = {
    left: 'inset(0% 100% 0% 0%)',
    right: 'inset(0% 0% 0% 100%)',
    top: 'inset(0% 0% 100% 0%)',
    bottom: 'inset(100% 0% 0% 0%)',
};

export default {
    name: 'mask-wipe',

    initial: ({options}) => ({
        opacity: 0.4,
        scale: 1.08,
        clipPath: CLIP_PATHS[options.direction] || CLIP_PATHS.left,
    }),

    completed: {
        opacity: 1,
        scale: 1,
        clipPath: 'inset(0% 0% 0% 0%)',
    },

    defaultOptions: {
        direction: 'left',
        duration: 0.65,
        easing: 'power3.inOut',
    },
};
