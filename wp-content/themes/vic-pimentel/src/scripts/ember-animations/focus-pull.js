export default {
    name: 'focus-pull',

    initial: ({options}) => ({
        opacity: 0,
        scale: options.scale,
        filter: `blur(${options.blur}px)`,
    }),

    completed: {
        opacity: 1,
        scale: 1,
        filter: 'blur(0px)',
    },

    defaultOptions: {
        scale: 1.3,
        blur: 15,
        duration: 0.8,
        easing: 'power2.out',
    },
};
