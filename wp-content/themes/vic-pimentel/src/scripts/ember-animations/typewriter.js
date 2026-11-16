export default {
    name: 'typewriter',

    initial: {
        opacity: 0,
    },

    completed: ({element, options}) => {
        // onGetNodes below retargets the tween onto the split <span.char>
        // children, so element.node ITSELF (pre-hidden by
        // common/_critical-overlays.scss's opacity: 0 rule) is never touched
        // by that tween — force it back on explicitly.
        element.node.style.opacity = '1';

        const cursor = element.node.querySelector('span.cursor');
        const chars = element.node.querySelectorAll('span.char');
        const preFlash = 0.5; // seconds the cursor flashes alone before typing starts

        // `baseDelay` is when the framework's own setTimeout fires its
        // gsap.to() call (element.delay + 100, fixed by ember-animations-
        // module.js, outside our control) — `delay: preFlash` below pushes
        // that tween's actual start (and every staggered character in it)
        // back by the same amount, so this local schedule for the cursor's
        // side effects can line up against the same typingStart offset.
        const baseDelay = element.delay + 100;
        const typingStart = baseDelay + preFlash * 1000;

        // Hard on/off blink (steps(1), not a smooth fade) — a real cursor
        // snaps, it doesn't glow in and out. window.gsap is exposed globally
        // by ember.config.js for exactly this kind of side-effect tween that
        // falls outside the normal initial/completed state contract.
        const blink = () => cursor && gsap.to(cursor, {
            opacity: 0,
            duration: 0.5,
            repeat: -1,
            yoyo: true,
            ease: 'steps(1)',
        });

        if (cursor) {
            // Flash alone for a beat first...
            blink();

            // ...go solid right as typing starts...
            setTimeout(() => {
                gsap.killTweensOf(cursor);
                cursor.style.opacity = '1';
            }, typingStart);

            // ...and resume flashing once the last character has landed.
            setTimeout(() => {
                blink();
            }, typingStart + chars.length * options.stagger * 1000);
        }

        chars.forEach((char, index) => {
            setTimeout(() => {
                if (cursor && char.parentNode) {
                    char.after(cursor);
                }
            }, typingStart + index * options.stagger * 1000);
        });

        return {
            opacity: 1,
            delay: preFlash,
        };
    },

    defaultOptions: {
        // A near-instant per-character reveal with a tight stagger reads as
        // "typed", not "faded in" — a normal 0.3-0.6s duration per character
        // would make them visibly overlap into a blur instead.
        duration: 0.01,
    },

    // A fixed stagger looks great on a paragraph but way too rushed on a
    // short headline (10 characters at 0.035s each is done in a third of a
    // second). Aim for a roughly constant TOTAL typing time instead, and
    // derive the per-character stagger from that — clamped so a one-word
    // headline doesn't crawl and a long paragraph doesn't drag. This runs
    // before onSetup on the very first (initial-phase) call, so it has to
    // fall back to the untouched innerText rather than the not-yet-set
    // dataset.originalText.
    onParseOptions: ({element, options}) => {
        const text = element.node.dataset.originalText || element.node.innerText;
        const charCount = text.replace(/\s/g, '').length || 1;
        const targetDuration = 1.2;

        options.stagger = Math.min(0.09, Math.max(0.02, targetDuration / charCount));
    },

    onSetup: ({element}) => {
        const original = element.node.innerText;
        element.node.dataset.originalText = original;

        const chars = original
            .split('')
            .map(char => char === ' ' ? ' ' : `<span class="char">${char}</span>`)
            .join('');

        // Cursor starts before the first character; completed() below walks
        // it forward as each one appears. It disappears along with
        // everything else once onComplete resets to plain text. The small
        // left margin keeps it a few px clear of whichever letter now
        // precedes it, instead of jammed right up against it.
        element.node.innerHTML = `<span class="cursor" style="margin-left:3px;display:inline-block;">|</span>${chars}`;
    },

    onGetNodes: ({element}) => {
        return element.node.querySelectorAll('span.char');
    },

    onComplete: ({element}) => {
        // Let the cursor keep blinking for a bit after the last character
        // lands, like it's waiting, instead of vanishing the instant typing
        // finishes.
        setTimeout(() => {
            const cursor = element.node.querySelector('span.cursor');

            if (cursor) {
                gsap.killTweensOf(cursor);
            }

            element.node.innerHTML = element.node.dataset.originalText;
        }, 2000);
    },
};
