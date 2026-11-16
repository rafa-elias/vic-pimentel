export default {
    name: 'rotate-in',

    always: {
        transformOrigin: 'bottom left',
    },

    initial: ({options}) => ({
        opacity: 0,
        rotation: options.rotation,
        y: options.offset,
    }),

    completed: {
        opacity: 1,
        rotation: 0,
        y: 0,
    },

    defaultOptions: {
        rotation: -6,
        offset: 30,
    },
};
