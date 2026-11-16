document.addEventListener('DOMContentLoaded', () => {
    const preset = document.getElementById('media-optimizer-quality-preset');
    const customFields = document.querySelector('[data-quality-preset-fields]');

    if (!preset || !customFields) return;

    preset.addEventListener('change', () => {
        customFields.hidden = preset.value !== 'custom';
    });
});
