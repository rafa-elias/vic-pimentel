<?php

/**
 * Allow administrators to upload Unicorn Studio scene exports (.json) for
 * Site Settings → Animations. JSON files are inert data, but stay admin-only.
 */
add_filter('upload_mimes', function (array $mimes): array {
    if (current_user_can('manage_options')) {
        $mimes['json'] = 'application/json';
    }

    return $mimes;
});

// PHP's finfo reports JSON as text/plain, which WP would otherwise reject.
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename) {
    if (current_user_can('manage_options') && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'json') {
        $data['ext'] = 'json';
        $data['type'] = 'application/json';
        $data['proper_filename'] = $data['proper_filename'] ?? false;
    }

    return $data;
}, 10, 3);
