/**
 * True only in the real frontend document — `is-frontend` (core/core.php body_class) is
 * absent in wp-admin and in the block editor's canvas iframe, where window.initializer
 * is undefined and isAdmin guards silently pass.
 */
export default function isFrontend() {
    return !!document.body && document.body.classList.contains('is-frontend');
}
