<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Serves WebP URLs wherever WordPress core renders an <img> tag — regardless
 * of which theme, template, or field type produced it. Deliberately does NOT
 * rely on any theme-specific component (this project's own image.php already
 * handles its own ACF-driven calls with a <picture><source> fallback) —
 * featured images, direct wp_get_attachment_image() calls, and static <img>
 * tags baked into post content never go through that component at all, so
 * this class exists to cover those cases on this project and on any other
 * theme this module is ever used with.
 *
 * Two hooks, two different scenarios:
 * - wp_get_attachment_image_attributes: any dynamic wp_get_attachment_image()
 *   call (the_post_thumbnail(), direct calls, etc.)
 * - wp_content_img_tag: static <img> markup already baked into post content
 *   (the_content/the_excerpt/widget text) — core's own tag-processing pass.
 *
 * Swaps src/srcset directly to the WebP URL rather than wrapping in
 * <picture><source> — a deliberate simplification versus image.php's more
 * cautious approach, justified by WebP's now-near-universal browser support;
 * revisit only if a real non-browser consumer (feed reader, scraper) turns
 * out to choke on it.
 */
class Serving
{
    public static function init(): void
    {
        add_filter('wp_get_attachment_image_attributes', [self::class, 'filter_image_attributes'], 10, 2);
        add_filter('wp_content_img_tag', [self::class, 'filter_content_img_tag'], 10, 3);
        // Deliberately NOT hooked: wp_get_attachment_image_src. Rewriting the src there to
        // .webp breaks WordPress's own srcset calculation (wp_calculate_image_srcset() drops
        // the srcset when the src no longer matches a size filename in the attachment
        // metadata), so every image fell back to its single max_size file — e.g. 300px
        // 'medium' images upscaled into 450px+ boxes. The attribute/content filters above
        // already swap the final src/srcset to WebP after srcset is built.
    }

    /**
     * Lower-level than wp_get_attachment_image_attributes — doesn't build an
     * <img> tag itself, just returns [url, width, height, is_intermediate].
     * Anything calling this directly (REST API media responses, custom
     * markup, a background-image style) should get the same treatment.
     *
     * @param array{0: string, 1: int, 2: int, 3: bool}|false $image
     */
    public static function filter_image_src($image, int $attachment_id)
    {
        if (!$image || !Tracking::is_optimized($attachment_id)) return $image;

        $webp = Tracking::get_webp_for_url($attachment_id, $image[0]);
        if ($webp) $image[0] = $webp;

        return $image;
    }

    /**
     * @param array<string, string> $attr
     */
    public static function filter_image_attributes(array $attr, \WP_Post $attachment): array
    {
        if (!Tracking::is_optimized($attachment->ID)) return $attr;

        if (!empty($attr['src'])) {
            $webp = Tracking::get_webp_for_url($attachment->ID, $attr['src']);
            if ($webp) $attr['src'] = $webp;
        }

        if (!empty($attr['srcset'])) {
            $attr['srcset'] = self::swap_srcset($attachment->ID, $attr['srcset']);
        }

        return $attr;
    }

    public static function filter_content_img_tag(string $filtered_image, string $context, int $attachment_id): string
    {
        if (!$attachment_id || !Tracking::is_optimized($attachment_id)) return $filtered_image;

        return preg_replace_callback(
            '/\s(src|srcset)="([^"]+)"/i',
            function (array $matches) use ($attachment_id): string {
                if (strtolower($matches[1]) === 'srcset') {
                    return ' srcset="' . self::swap_srcset($attachment_id, $matches[2]) . '"';
                }

                $webp = Tracking::get_webp_for_url($attachment_id, $matches[2]);
                return $webp ? ' src="' . esc_url($webp) . '"' : $matches[0];
            },
            $filtered_image
        );
    }

    private static function swap_srcset(int $attachment_id, string $srcset): string
    {
        $entries = array_map('trim', explode(',', $srcset));

        $swapped = array_map(function (string $entry) use ($attachment_id): string {
            if ($entry === '') return $entry;

            $parts = preg_split('/\s+/', $entry, 2);
            $webp = Tracking::get_webp_for_url($attachment_id, $parts[0]);
            $url = $webp ?: $parts[0];

            return isset($parts[1]) ? "$url {$parts[1]}" : $url;
        }, $entries);

        return implode(', ', $swapped);
    }
}
