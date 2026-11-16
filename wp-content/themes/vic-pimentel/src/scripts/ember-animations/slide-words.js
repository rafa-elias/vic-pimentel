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
    name: 'slide-words',

    initial: ({options, element}) => {
        // We need to use width and height in the clipPath and offset because they need to be
        // the same, but we can't rely on a generic offset because the element may be a different
        // size than the offset.
        const width = element.node.offsetWidth;
        const height = element.node.offsetHeight;

        // All four values are explicitly "px" on both this and the completed
        // state below — GSAP needs matching units at each clip-path position
        // to interpolate it smoothly, the same bug fixed in mask-wipe.js.
        const clipPaths = {
            left: `inset(0px ${width}px 0px 0px)`,
            right: `inset(0px 0px 0px ${width}px)`,
            top: `inset(0px 0px ${height}px 0px)`,
            bottom: `inset(${height}px 0px 0px 0px)`,
        };

        const offset = ['left', 'right'].includes(options.direction) ? width : height;

        return {
            clipPath: clipPaths[options.direction] || clipPaths.top,
            ...(AXIS_OFFSET[options.direction] || AXIS_OFFSET.top)(offset),
        };
    },

    completed: ({element}) => {
        // Nothing in this animation's state ever touches opacity, and
        // onGetNodes retargets the tween onto the split <span> children
        // below anyway — but common/_critical-overlays.scss pre-hides
        // element.node ITSELF via opacity: 0. Force it back on explicitly,
        // since nothing else in this animation ever will.
        element.node.style.opacity = '1';

        return {
            clipPath: 'inset(0px 0px 0px 0px)',
            x: 0,
            y: 0,
        };
    },

    defaultOptions: {
        direction: 'top',
        stagger: 0.05,
        offset: 150,
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
