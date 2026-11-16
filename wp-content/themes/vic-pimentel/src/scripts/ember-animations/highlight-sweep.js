export default {
    name: 'highlight-sweep',

    // Static companion properties (the gradient itself never animates, only
    // its backgroundSize below does) — applied every phase via `always`,
    // same pattern fade-motion uses for its position: relative companion.
    always: ({options}) => ({
        backgroundImage: `linear-gradient(90deg, ${options.color}, ${options.color})`,
        backgroundRepeat: 'no-repeat',
    }),

    initial: {
        opacity: 0,
        backgroundSize: '0% 100%',
    },

    completed: {
        opacity: 1,
        backgroundSize: '100% 100%',
    },

    defaultOptions: {
        color: 'rgba(255, 210, 90, 0.55)',
    },
};
