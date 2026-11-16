export default {
    name: 'scale-fade',

    initial: ({options}) => ({
        opacity: 0,
        scale: options.scale,
    }),

    completed: {
        opacity: 1,
        scale: 1,
    },

    defaultOptions: {
        scale: 0.92,
    },
};
