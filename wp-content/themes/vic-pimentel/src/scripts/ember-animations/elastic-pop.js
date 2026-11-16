export default {
    name: 'elastic-pop',

    initial: ({options}) => ({
        opacity: 0,
        scale: options.scale,
    }),

    completed: {
        opacity: 1,
        scale: 1,
    },

    defaultOptions: {
        scale: 0.4,
        easing: 'back.out(1.7)',
        duration: 0.7,
    },
};
