export default {
    name: 'skew-fade',

    always: {
        transformOrigin: 'left center',
    },

    initial: ({options}) => ({
        opacity: 0,
        skewX: options.skew,
        x: options.offset,
    }),

    completed: {
        opacity: 1,
        skewX: 0,
        x: 0,
    },

    defaultOptions: {
        skew: -12,
        offset: 60,
    },
};
