// top/bottom/left/right are layout properties — even under position: relative,
// Chrome's Layout Instability API still counts them as a shift (hurting CLS)
// and they force a layout recalc every tween frame. translateX/Y via GSAP's
// x/y achieve the same visual offset compositor-only, with no layout cost.
const AXIS_OFFSET = {
    top: offset => ({y: offset}),
    bottom: offset => ({y: -offset}),
    left: offset => ({x: offset}),
    right: offset => ({x: -offset}),
};

export default {
    name: 'fade-motion',

    initial: ({options}) => ({
        opacity: 0,
        ...(AXIS_OFFSET[options.direction] || AXIS_OFFSET.top)(options.offset),
    }),

    completed: {
        opacity: 1,
        x: 0,
        y: 0,
    },

    defaultOptions: {
        direction: 'top',
    },
};
