export default {
    name: 'flip-in',

    always: ({options}) => ({
        transformPerspective: 600,
        transformOrigin: options.axis === 'y' ? 'left center' : 'top center',
    }),

    initial: ({options}) => (
        options.axis === 'y'
            ? {opacity: 0, rotationY: options.rotation}
            : {opacity: 0, rotationX: options.rotation}
    ),

    completed: {
        opacity: 1,
        rotationX: 0,
        rotationY: 0,
    },

    defaultOptions: {
        axis: 'x',
        rotation: -90,
    },
};
