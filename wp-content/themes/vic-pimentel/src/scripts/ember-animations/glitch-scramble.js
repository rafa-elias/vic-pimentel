const SCRAMBLE_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';

function randomChar() {
    return SCRAMBLE_CHARS[Math.floor(Math.random() * SCRAMBLE_CHARS.length)];
}

function scramble(text) {
    return text.split('').map(char => char === ' ' ? ' ' : randomChar()).join('');
}

function resolveScramble(node, originalText, duration) {
    const frameRate = 40;
    const totalTicks = Math.round(duration * 1000 / frameRate);
    let tick = 0;

    const interval = setInterval(() => {
        tick++;
        const revealCount = Math.floor((tick / totalTicks) * originalText.length);

        node.innerText = originalText
            .split('')
            .map((char, i) => (char === ' ' || i < revealCount) ? char : randomChar())
            .join('');

        if (tick >= totalTicks) {
            clearInterval(interval);
            node.innerText = originalText;
            node.style.minHeight = '';
        }
    }, frameRate);
}

export default {
    name: 'glitch-scramble',

    initial: {
        opacity: 1,
    },

    completed: ({element, options}) => {
        resolveScramble(element.node, element.node.dataset.originalText, options.duration);

        return {
            opacity: 1,
        };
    },

    defaultOptions: {
        duration: 0.6,
    },

    onSetup: ({element}) => {
        const original = element.node.innerText;
        element.node.dataset.originalText = original;

        // Substitute characters can render at a slightly different width
        // than the real text, occasionally shifting where a line wraps
        // mid-reveal — a real layout shift, not just a visual quirk, since
        // (unlike every other split-based animation here, which only ever
        // touches text once, before paint) the scramble runs its interval
        // while the element is already visible. Lock the height measured
        // with the REAL text — before scrambling — to contain that.
        element.node.style.minHeight = `${element.node.offsetHeight}px`;

        element.node.innerText = scramble(original);
    },

    onComplete: ({element}) => {
        element.node.innerText = element.node.dataset.originalText;
        element.node.style.minHeight = '';
    },
};
