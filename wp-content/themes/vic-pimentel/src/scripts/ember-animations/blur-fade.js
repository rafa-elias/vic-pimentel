export default {
    name: 'blur-fade',

    initial: ({options}) => ({
        opacity: 0,
        filter: `blur(${options.blur}px)`,
    }),

    completed: {
        opacity: 1,
        filter: 'blur(0px)',
    },

    defaultOptions: {
        blur: 12,
    },
};
