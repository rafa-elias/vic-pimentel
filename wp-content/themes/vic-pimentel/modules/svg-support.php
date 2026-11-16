<?php

/**
 * Native replacement for the "SVG Support" plugin — allows SVG uploads to
 * the Media Library, sanitizing file content on upload (scripts, event
 * handlers, external references stripped) since SVG is an XML format capable
 * of embedding executable content. Restricted to users who can manage_options
 * — SVG upload carries real XSS risk even after sanitizing, so it stays an
 * admin-only capability regardless of who could otherwise upload media.
 *
 * Gated by ALLOW_SVG_UPLOAD (app/config.php) AND the "Enable SVG Upload"
 * field on the Feature Toggles options page — both must be on.
 */

defined('ALLOW_SVG_UPLOAD') || define('ALLOW_SVG_UPLOAD', false);

function svg_upload_enabled(): bool
{
    if (!ALLOW_SVG_UPLOAD) return false;

    // get_field() returns null (not the field's default_value) until an
    // admin actually saves the Feature Toggles page once — only an
    // explicit, saved "off" (false) should count as disabled.
    return get_field('feature_svg_upload', 'option') !== false;
}

add_filter('upload_mimes', 'core_svg_upload_mimes');
function core_svg_upload_mimes(array $mimes): array
{
    if (!svg_upload_enabled() || !current_user_can('manage_options')) return $mimes;

    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}

add_filter('wp_check_filetype_and_ext', 'core_svg_check_filetype', 10, 4);
function core_svg_check_filetype($data, $file, $filename, $mimes)
{
    if (!svg_upload_enabled()) return $data;

    // finfo's real-mime detection is unreliable for SVG across hosts (often
    // reports text/plain or text/xml depending on the libmagic version) —
    // trust the extension instead, since the content itself is sanitized
    // right after upload in core_svg_sanitize_on_upload().
    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'svg') {
        $data['ext'] = 'svg';
        $data['type'] = 'image/svg+xml';
    }

    return $data;
}

add_filter('wp_handle_upload', 'core_svg_sanitize_on_upload');
function core_svg_sanitize_on_upload(array $upload): array
{
    if (($upload['type'] ?? '') !== 'image/svg+xml' || empty($upload['file'])) return $upload;

    $contents = file_get_contents($upload['file']);
    $sanitized = $contents !== false ? core_sanitize_svg($contents) : null;

    // Fail closed: if the file couldn't be parsed/sanitized, delete it and
    // reject the upload outright (media_handle_upload() and the REST media
    // controller both treat an 'error' key here as upload failure — no
    // attachment gets created). The previous behavior of falling back to
    // the raw, unsanitized content on a parse failure was a real sanitizer
    // bypass: a file crafted to be "invalid enough" for DOMDocument's strict
    // XML parser to reject, while still lenient enough for a browser's own
    // SVG rendering to execute embedded script, would have sailed through
    // untouched.
    if ($sanitized === null) {
        @unlink($upload['file']);
        return ['error' => __('This SVG file could not be safely sanitized and was rejected.', 'rear-theme')];
    }

    file_put_contents($upload['file'], $sanitized);

    return $upload;
}

/**
 * Strips the common SVG-based XSS vectors: <script>, event-handler
 * attributes (onload, onclick, ...), javascript: URIs in href/xlink:href,
 * and <foreignObject> (can embed arbitrary HTML). Not an exhaustive security
 * audit of every SVG feature, but covers the vectors every mainstream "safe
 * SVG" plugin targets.
 *
 * Returns null (never the original content) if the file can't be parsed, or
 * contains a DOCTYPE, so the caller can reject the upload outright — silently
 * falling back to unsanitized content on a parse failure would let an
 * attacker smuggle a payload past this function by crafting a file that's
 * "invalid XML" (so DOMDocument bails) but still renders as SVG in a
 * browser's more lenient parser.
 */
function core_sanitize_svg(string $svg): ?string
{
    // Legitimate SVGs exported by design tools/image editors never need a
    // DOCTYPE. Rejecting it outright closes both XXE (already blocked another
    // way on PHP 8+, but not guaranteed on an older host) and internal
    // entity-expansion ("billion laughs") resource-exhaustion attacks, rather
    // than relying on libxml's own defaults to contain either.
    if (stripos($svg, '<!doctype') !== false) return null;

    // A <style> block is common in design-tool exports (shared fill/stroke
    // classes) and is left intact, but reject outright if it carries any of
    // the handful of constructs that turn CSS into a script/tracking vector
    // (@import, url()-based script execution, legacy IE/Firefox behavior
    // bindings) — fail closed rather than trying to surgically edit CSS.
    if (preg_match('/<style\b.*?<\/style>/is', $svg, $style_match)) {
        $style_content = $style_match[0];
        foreach (['@import', 'javascript:', 'expression(', 'behavior:', '-moz-binding'] as $needle) {
            if (stripos($style_content, $needle) !== false) return null;
        }
    }

    $previous_errors = libxml_use_internal_errors(true);
    if (PHP_VERSION_ID < 80000) libxml_disable_entity_loader(true); // XXE protection; removed (no-op) in PHP 8+

    $dom = new DOMDocument();
    $loaded = $dom->loadXML($svg, LIBXML_NONET);

    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);

    if (!$loaded) return null;

    foreach (['script', 'foreignObject'] as $tag) {
        foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    $xpath = new DOMXPath($dom);
    foreach (iterator_to_array($xpath->query('//@*')) as $attr) {
        $name = strtolower($attr->nodeName);
        $value = trim($attr->nodeValue);

        if (str_starts_with($name, 'on')) {
            $attr->ownerElement->removeAttribute($attr->nodeName);
            continue;
        }

        // Only a same-document fragment reference (#icon-id, used by <use>
        // for icon sprites) is allowed — anything else (javascript:, data:,
        // http(s):) is stripped. Blocking every external/data reference,
        // not just javascript:, also closes the render-time tracking/SSRF
        // vector of a <use>/<image>/<a> pointing at an attacker-controlled
        // URL that a visitor's browser would silently fetch.
        if (in_array($name, ['href', 'xlink:href'], true) && !str_starts_with($value, '#')) {
            $attr->ownerElement->removeAttribute($attr->nodeName);
        }
    }

    return $dom->saveXML();
}

// WP's raster image editors (Imagick/GD) can't read SVG, so wp_generate_attachment_metadata()
// would otherwise return an empty array for it — build the metadata by hand instead, since
// core (image_downsize, the media grid, etc.) needs real width/height to display it properly.
add_filter('wp_generate_attachment_metadata', 'core_svg_attachment_metadata', 10, 2);
function core_svg_attachment_metadata($metadata, $attachment_id)
{
    if (get_post_mime_type($attachment_id) !== 'image/svg+xml') return $metadata;

    $file = get_attached_file($attachment_id);
    [$width, $height] = core_svg_dimensions($file);

    return [
        'width' => $width,
        'height' => $height,
        'file' => _wp_relative_upload_path($file),
        'sizes' => [],
    ];
}

/**
 * A vector has exactly one file serving every size, so a srcset is
 * meaningless for it — but core's own wp_calculate_image_srcset() doesn't
 * know that and, for any SVG attachment whose metadata['sizes'] happens to
 * be non-empty (e.g. one carried over from a different SVG plugin that
 * populated fake per-size entries), derives each candidate URL from
 * metadata['file'] via _wp_get_attachment_relative_path() — which silently
 * drops the uploads sub-directory (the year/month folder) if that stored
 * 'file' value isn't itself a relative path, producing 404s. Forcing
 * metadata['sizes'] empty here makes wp_calculate_image_srcset() hit its own
 * "nothing to build a srcset from" bail-out cleanly, regardless of whatever
 * shape a given SVG attachment's stored metadata happens to be in.
 */
add_filter('wp_calculate_image_srcset_meta', 'core_svg_disable_srcset', 10, 4);
function core_svg_disable_srcset($image_meta, $size_array, $image_src, $attachment_id)
{
    if (get_post_mime_type($attachment_id) === 'image/svg+xml') {
        $image_meta['sizes'] = [];
    }

    return $image_meta;
}

/**
 * @return array{0: int, 1: int}
 */
function core_svg_dimensions(string $path): array
{
    $default = [100, 100];
    if (!file_exists($path)) return $default;

    $contents = file_get_contents($path);
    $previous_errors = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($contents);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);

    if (!$xml) return $default;

    $attrs = $xml->attributes();
    $width = isset($attrs->width) ? (float) $attrs->width : null;
    $height = isset($attrs->height) ? (float) $attrs->height : null;

    if ((!$width || !$height) && isset($attrs->viewBox)) {
        $parts = preg_split('/[\s,]+/', trim((string) $attrs->viewBox));
        if (count($parts) === 4) {
            $width = $width ?: (float) $parts[2];
            $height = $height ?: (float) $parts[3];
        }
    }

    return [
        $width > 0 ? (int) round($width) : $default[0],
        $height > 0 ? (int) round($height) : $default[1],
    ];
}
