export default {
    name: 'underline-grow',

    always: {
        position: 'relative',
        display: 'inline-block',
    },

    initial: {
        opacity: 0,
    },

    completed: ({element, options}) => {
        element.node.style.opacity = '1';

        // The bar is a decorative child, not part of onGetNodes/gsapState —
        // it needs its own tween call, same pattern as typewriter.js's
        // cursor. It's left in the DOM afterward (no onComplete cleanup)
        // since, unlike the text-splitting animations, this never touches
        // the actual text content — it's just a permanent underline flourish.
        const bar = element.node.querySelector('span.underline-bar');

        if (bar) {
            gsap.to(bar, {
                scaleX: 1,
                duration: options.duration,
                ease: options.easing,
                delay: 0.15,
            });
        }

        return {
            opacity: 1,
        };
    },

    defaultOptions: {
        color: 'currentColor',
        duration: 0.5,
        easing: 'power2.out',
    },

    onSetup: ({element, options}) => {
        const bar = document.createElement('span');
        bar.className = 'underline-bar';
        bar.style.cssText = `position:absolute;left:0;bottom:-0.1em;width:100%;height:0.08em;`
            + `background:${options.color};transform-origin:left center;transform:scaleX(0);`;

        element.node.appendChild(bar);
    },
};
