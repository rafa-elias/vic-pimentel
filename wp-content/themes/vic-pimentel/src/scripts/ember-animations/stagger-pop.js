export default {
    name: 'stagger-pop',

    initial: {
        opacity: 0,
        scale: 0.3,
    },

    completed: ({element}) => {
        // onGetNodes below retargets the tween onto the split <span.w>
        // children, so element.node ITSELF (pre-hidden by
        // common/_critical-overlays.scss's opacity: 0 rule) is never touched
        // by that tween — force it back on explicitly.
        element.node.style.opacity = '1';

        return {
            opacity: 1,
            scale: 1,
        };
    },

    defaultOptions: {
        stagger: 0.04,
        easing: 'back.out(2)',
        duration: 0.5,
    },

    onSetup: ({element}) => {
        const original = element.node.innerText;
        element.node.dataset.originalText = original;

        element.node.innerHTML = original
            .split(' ')
            .map(word => `<span class="w" style="display:inline-block;">${word}</span>`)
            .join(' ');
    },

    onGetNodes: ({element}) => {
        return element.node.querySelectorAll('span.w');
    },

    onComplete: ({element}) => {
        element.node.innerHTML = element.node.dataset.originalText;
    },
};
