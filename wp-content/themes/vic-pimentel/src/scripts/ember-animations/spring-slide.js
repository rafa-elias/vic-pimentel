const AXIS_OFFSET = {
    top: offset => ({y: offset}),
    bottom: offset => ({y: -offset}),
    left: offset => ({x: offset}),
    right: offset => ({x: -offset}),
};

export default {
    name: 'spring-slide',

    initial: ({options}) => ({
        opacity: 0,
        ...(AXIS_OFFSET[options.direction] || AXIS_OFFSET.left)(options.offset),
    }),

    completed: {
        opacity: 1,
        x: 0,
        y: 0,
    },

    defaultOptions: {
        direction: 'left',
        offset: 80,
        easing: 'elastic.out(1, 0.6)',
        duration: 0.9,
    },
};
