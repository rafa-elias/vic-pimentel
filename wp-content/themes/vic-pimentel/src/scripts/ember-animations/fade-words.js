// top/bottom/left/right are layout properties — even under position: relative,
// Chrome's Layout Instability API still counts them as a shift (hurting CLS)
// and they force a layout recalc every tween frame. translateX/Y via GSAP's
// x/y achieve the same visual offset compositor-only, with no layout cost.
const AXIS_OFFSET = {
    top: offset => ({y: offset}),
    bottom: offset => ({y: -offset}),
    left: offset => ({x: offset}),
    right: offset => ({x: -offset}),
};

export default {
    name: 'fade-words',

    initial: ({options}) => ({
        opacity: 0,
        ...(AXIS_OFFSET[options.direction] || AXIS_OFFSET.top)(options.offset),
    }),

    completed: ({element}) => {
        // onGetNodes below retargets the tween onto the split <span.w>
        // children, so the opacity:1 returned here only ever lands on them.
        // element.node ITSELF (pre-hidden by common/_critical-overlays.scss's
        // opacity: 0 rule) is never touched by that tween — force it back on
        // explicitly.
        element.node.style.opacity = '1';

        return {
            opacity: 1,
            x: 0,
            y: 0,
        };
    },

    defaultOptions: {
        direction: 'top',
        stagger: 0.05,
        offset: 100,
    },

    onSetup: ({element}) => {
        // Split each word into letters.
        element.node.innerHTML = element.node.innerText
            .split(' ').map(word => `<span class="w">${word}</span>`).join(' ');
    },

    onGetNodes: ({element}) => {
        // GSAP should animate the letters, not the entire element.
        return element.node.querySelectorAll('span.w');
    },

    onComplete: ({element}) => {
        // Reset the HTML to only contain the text.
        element.node.innerHTML = element.node.innerText;
    },
};
