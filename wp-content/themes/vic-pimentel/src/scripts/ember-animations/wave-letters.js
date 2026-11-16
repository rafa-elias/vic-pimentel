export default {
    name: 'wave-letters',

    initial: {
        opacity: 0,
        y: 20,
    },

    completed: ({element}) => {
        // onGetNodes below retargets the tween onto the split <span.l>
        // children, so element.node ITSELF (pre-hidden by
        // common/_critical-overlays.scss's opacity: 0 rule) is never touched
        // by that tween — force it back on explicitly.
        element.node.style.opacity = '1';

        return {
            opacity: 1,
            y: 0,
        };
    },

    defaultOptions: {
        // The bounce ease is what makes this read as a "wave" rather than a
        // plain fade-letters — each letter overshoots and settles slightly
        // after the one before it, so the bounce visibly ripples across the
        // word as the stagger plays out.
        stagger: 0.05,
        duration: 0.6,
        easing: 'bounce.out',
    },

    onSetup: ({element}) => {
        const original = element.node.innerText;
        element.node.dataset.originalText = original;

        element.node.innerHTML = original
            .split('')
            .map(char => char === ' ' ? ' ' : `<span class="l" style="display:inline-block;">${char}</span>`)
            .join('');
    },

    onGetNodes: ({element}) => {
        return element.node.querySelectorAll('span.l');
    },

    onComplete: ({element}) => {
        element.node.innerHTML = element.node.dataset.originalText;
    },
};
