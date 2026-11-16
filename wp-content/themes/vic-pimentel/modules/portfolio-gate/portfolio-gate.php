<?php

/**
 * Portfolio Access — site-wide password gate (Figma: Login / Loading).
 *
 * Not the WP login: a single shared password (Site Settings → Portfolio Access) unlocks
 * every page for N days via a signed cookie. Logged-in editors are never gated.
 *
 * - The password is a plain ACF text field (visible to admins on purpose — client request);
 *   the cookie is signed with it, so changing it signs every visitor out.
 * - Cookie name starts with STYXKEY_ so Pantheon's edge cache varies on it; every gate
 *   response is sent no-store.
 * - REST and the listing AJAX endpoint are gated too. Uploaded media files are served
 *   directly by the web server and are NOT covered (would need server-level rules).
 */

defined('PORTFOLIO_GATE_ENABLED') || define('PORTFOLIO_GATE_ENABLED', false);

const VP_ACCESS_COOKIE = 'STYXKEY_vp_access';
const VP_ACCESS_ACTION = 'vp_access';
const VP_ACCESS_MAX_ATTEMPTS = 8;
const VP_ACCESS_LOCK_MINUTES = 15;

/**
 * Whether the gate is active right now.
 */
function vp_gate_enabled(): bool
{
    if (!PORTFOLIO_GATE_ENABLED || !function_exists('get_field')) return false;

    return get_field('access_enabled', 'option') !== false && vp_access_password() !== '';
}

function vp_access_password(): string
{
    return trim((string) get_field('access_password', 'option'));
}

function vp_access_setting(string $name, mixed $fallback = ''): mixed
{
    $value = get_field('access_' . $name, 'option');

    return ($value === null || $value === '' || $value === false) ? $fallback : $value;
}

function vp_access_token(int $expires): string
{
    return hash_hmac('sha256', $expires . '|' . vp_access_password(), wp_salt('auth'));
}

/**
 * True when the current visitor may see the site.
 */
function vp_has_access(): bool
{
    if (!vp_gate_enabled()) return true;

    if (is_user_logged_in() && current_user_can('edit_posts')) return true;

    $cookie = $_COOKIE[VP_ACCESS_COOKIE] ?? '';

    if (!$cookie || !str_contains($cookie, '|')) return false;

    [$expires, $token] = explode('|', $cookie, 2);
    $expires = (int) $expires;

    return $expires > time() && hash_equals(vp_access_token($expires), $token);
}

function vp_access_grant(): void
{
    $days = max(1, (int) vp_access_setting('cookie_days', 2));
    $expires = time() + $days * DAY_IN_SECONDS;

    setcookie(VP_ACCESS_COOKIE, $expires . '|' . vp_access_token($expires), [
        'expires' => $expires,
        'path' => COOKIEPATH ?: '/',
        'domain' => COOKIE_DOMAIN ?: '',
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function vp_access_client_key(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip = trim(explode(',', $ip)[0]);

    return 'vp_access_attempts_' . md5($ip);
}

/**
 * Render the gate document ('login' or 'loading') and stop.
 */
function vp_access_render(string $state, array $data = []): void
{
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');

    $dir = get_template_directory();
    $uri = get_template_directory_uri();

    foreach (['css' => 'style', 'js' => 'script'] as $ext => $type) {
        $file = "/public/templates/access-gate.$ext";

        if (!file_exists($dir . $file)) continue;

        if ($type === 'style') {
            wp_enqueue_style('vp-access-gate', $uri . $file, [], filemtime($dir . $file));
        } else {
            wp_enqueue_script('vp-access-gate', $uri . $file, [], filemtime($dir . $file), true);
        }
    }

    add_filter('body_class', fn($classes) => array_merge($classes, ['access-gate', 'access-' . $state]));

    $state_data = $data;
    include __DIR__ . '/template.php';
    exit;
}

/**
 * Front-end gate.
 */
add_action('template_redirect', function (): void {
    if (!vp_gate_enabled() || is_robots() || is_favicon()) return;

    $state = sanitize_key($_GET['vp-access'] ?? '');

    if (vp_has_access()) {
        // Right after a successful login: show the "confidential" loading screen once.
        if ($state === 'welcome') {
            $target = remove_query_arg('vp-access');

            vp_access_render('loading', [
                'target' => wp_validate_redirect($target, home_url('/')),
                'seconds' => max(1, min(10, (int) vp_access_setting('loading_seconds', 4))),
            ]);
        }

        return;
    }

    $requested = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . remove_query_arg('vp-access', $_SERVER['REQUEST_URI'] ?? '/');

    vp_access_render('login', [
        'error' => $state === 'error' ? vp_access_setting('error', __('That password is not correct. Please try again.', 'rear-theme')) : '',
        'locked' => $state === 'locked',
        'redirect' => wp_validate_redirect($requested, home_url('/')),
    ]);
}, 0);

/**
 * Check a password attempt (rate-limited per IP) and grant the cookie on success.
 *
 * @return string 'ok' | 'error' | 'locked'
 */
function vp_access_attempt(string $password): string
{
    $key = vp_access_client_key();
    $attempts = (int) get_transient($key);

    if ($attempts >= VP_ACCESS_MAX_ATTEMPTS) return 'locked';

    $expected = vp_access_password();

    if ($password === '' || $expected === '' || !hash_equals($expected, trim($password))) {
        set_transient($key, $attempts + 1, VP_ACCESS_LOCK_MINUTES * MINUTE_IN_SECONDS);
        return 'error';
    }

    delete_transient($key);
    vp_access_grant();

    return 'ok';
}

function vp_access_success_url(string $redirect): string
{
    $target = vp_access_setting('redirect', 'home') === 'requested' ? $redirect : home_url('/');

    return add_query_arg('vp-access', 'welcome', $target);
}

function vp_access_locked_message(): string
{
    return sprintf(__('Too many attempts. Please try again in %d minutes.', 'rear-theme'), VP_ACCESS_LOCK_MINUTES);
}

/**
 * Login form handler — no-JS fallback (admin-post.php, full page round trip).
 */
function vp_access_handle_login(): void
{
    $redirect = wp_validate_redirect(wp_unslash($_POST['redirect_to'] ?? ''), home_url('/'));
    $back = fn($state) => add_query_arg('vp-access', $state, $redirect);

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_vp_access_nonce'] ?? '')), VP_ACCESS_ACTION)) {
        wp_safe_redirect($back('error'));
        exit;
    }

    $result = vp_access_attempt((string) wp_unslash($_POST['vp_password'] ?? ''));

    wp_safe_redirect($result === 'ok' ? vp_access_success_url($redirect) : $back($result));
    exit;
}
add_action('admin_post_nopriv_' . VP_ACCESS_ACTION, 'vp_access_handle_login');
add_action('admin_post_' . VP_ACCESS_ACTION, 'vp_access_handle_login');

/**
 * Login form handler — AJAX (access-gate.js): wrong passwords never reload the page.
 */
function vp_access_handle_ajax(): void
{
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_vp_access_nonce'] ?? '')), VP_ACCESS_ACTION)) {
        wp_send_json_error(['message' => __('Your session expired. Please reload the page.', 'rear-theme')], 403);
    }

    $redirect = wp_validate_redirect(wp_unslash($_POST['redirect_to'] ?? ''), home_url('/'));
    $result = vp_access_attempt((string) wp_unslash($_POST['vp_password'] ?? ''));

    if ($result === 'ok') {
        wp_send_json_success(['redirect' => vp_access_success_url($redirect)]);
    }

    wp_send_json_error([
        'message' => $result === 'locked'
            ? vp_access_locked_message()
            : vp_access_setting('error', __('That password is not correct. Please try again.', 'rear-theme')),
    ], $result === 'locked' ? 429 : 401);
}
add_action('wp_ajax_nopriv_' . VP_ACCESS_ACTION, 'vp_access_handle_ajax');
add_action('wp_ajax_' . VP_ACCESS_ACTION, 'vp_access_handle_ajax');

/**
 * Gate the REST API for anonymous visitors without access.
 */
add_filter('rest_authentication_errors', function ($result) {
    if (!empty($result) || vp_has_access()) return $result;

    return new WP_Error('vp_access_required', __('This site is password protected.', 'rear-theme'), ['status' => 401]);
});

/**
 * A password-protected portfolio should never be indexed.
 */
add_filter('wp_robots', function (array $robots): array {
    if (vp_gate_enabled()) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
    }

    return $robots;
});
