export default {
    name: 'letter-flip',

    always: {
        transformPerspective: 500,
        transformOrigin: 'top center',
        display: 'inline-block',
    },

    initial: {
        opacity: 0,
        rotationX: -90,
    },

    completed: ({element}) => {
        // onGetNodes below retargets the tween onto the split <span.l>
        // children, so element.node ITSELF (pre-hidden by
        // common/_critical-overlays.scss's opacity: 0 rule) is never touched
        // by that tween — force it back on explicitly.
        element.node.style.opacity = '1';

        return {
            opacity: 1,
            rotationX: 0,
        };
    },

    defaultOptions: {
        stagger: 0.04,
        duration: 0.5,
        easing: 'power2.out',
    },

    onSetup: ({element}) => {
        const original = element.node.innerText;
        element.node.dataset.originalText = original;

        element.node.innerHTML = original
            .split('')
            .map(char => char === ' ' ? ' ' : `<span class="l">${char}</span>`)
            .join('');
    },

    onGetNodes: ({element}) => {
        return element.node.querySelectorAll('span.l');
    },

    onComplete: ({element}) => {
        element.node.innerHTML = element.node.dataset.originalText;
    },
};
