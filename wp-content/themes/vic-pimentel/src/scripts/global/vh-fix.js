export default function makeCalcAppHeight () {
    /**
     * Updates the page height to be used on the SCSS.
     */
    const calcAppHeight = () => {
        if (!document.body) return;

        const doc = document.documentElement;
        doc.style.setProperty('--app-height', `${window.innerHeight}px`);
        doc.style.setProperty('--scrollbar-width', `${window.innerWidth - document.body.clientWidth}px`);
    };
    calcAppHeight();

    window.addEventListener('DOMContentLoaded', () => calcAppHeight());
    window.addEventListener('resize', () => calcAppHeight());
};
