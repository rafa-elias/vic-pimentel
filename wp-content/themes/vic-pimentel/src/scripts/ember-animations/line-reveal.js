function splitIntoWordSpans(text) {
    return text
        .split(' ')
        .map(word => `<span class="word" style="display:inline-block;">${word}</span>`)
        .join(' ');
}

function groupWordsIntoLines(container) {
    const wordSpans = Array.from(container.querySelectorAll('span.word'));
    const lines = [];
    let currentTop = null;
    let currentLine = [];

    wordSpans.forEach(span => {
        const top = Math.round(span.getBoundingClientRect().top);

        if (currentTop !== null && top !== currentTop) {
            lines.push(currentLine);
            currentLine = [];
        }

        currentTop = top;
        currentLine.push(span.textContent);
    });

    if (currentLine.length) lines.push(currentLine);

    return lines;
}

export default {
    name: 'line-reveal',

    initial: {
        y: '100%',
    },

    completed: ({element}) => {
        // onGetNodes below retargets the tween onto the per-line <span.line>
        // children, so element.node ITSELF (pre-hidden by
        // common/_critical-overlays.scss's opacity: 0 rule) is never touched
        // by that tween — force it back on explicitly.
        element.node.style.opacity = '1';

        return {
            y: '0%',
        };
    },

    defaultOptions: {
        stagger: 0.08,
    },

    onSetup: ({element}) => {
        const original = element.node.innerText;
        element.node.dataset.originalText = original;

        // Render words as measurable inline-block spans first, so we can
        // group them by their rendered top position (i.e. which line they
        // actually wrapped onto), then rebuild into one overflow:hidden mask
        // per line with an inner span that slides up from behind it.
        element.node.innerHTML = splitIntoWordSpans(original);
        const lines = groupWordsIntoLines(element.node);

        element.node.innerHTML = lines.map(words => (
            `<span class="line-mask" style="display:block;overflow:hidden;">` +
            `<span class="line" style="display:block;">${words.join(' ')}</span>` +
            `</span>`
        )).join('');
    },

    onGetNodes: ({element}) => {
        return element.node.querySelectorAll('span.line');
    },

    onComplete: ({element}) => {
        element.node.innerHTML = element.node.dataset.originalText;
    },
};
