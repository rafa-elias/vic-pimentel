export default {
    name: 'roll',

    initial: ({options}) => {
        switch (options.direction) {
            case 'left':
                return {
                    clipPath: 'inset(0% 0% 0% 100%)',
                };

            case 'right':
                return {
                    clipPath: 'inset(0% 100% 0% 0%)',
                };

            case 'top':
                return {
                    clipPath: 'inset(100% 0% 0% 0%)',
                };

            case 'bottom':
                return {
                    clipPath: 'inset(0% 0% 100% 0%)',
                };
        }
    },

    completed: ({element}) => {
        // Nothing in this animation's state ever touches opacity, but
        // common/_critical-overlays.scss pre-hides element.node itself via
        // opacity: 0 to prevent a flash-of-visible on load. Force it back
        // on explicitly, since nothing else in this animation ever will.
        element.node.style.opacity = '1';

        return {
            clipPath: 'inset(0% 0% 0% 0%)',
        };
    },

    defaultOptions: {
        direction: 'top',
    },
};