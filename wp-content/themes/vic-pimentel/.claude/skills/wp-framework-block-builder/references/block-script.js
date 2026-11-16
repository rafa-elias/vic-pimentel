window.theme.block('block-name', el => {
    // el is the block's <section> element.
    // All DOM queries should be scoped inside el.

    // Example:
    // const slider = el.querySelector('.js-slider');
    // if (!slider) return;
    // new window.theme.packages.swiper(slider, { ... });
});
