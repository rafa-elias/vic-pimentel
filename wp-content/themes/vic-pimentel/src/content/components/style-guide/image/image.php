<?php
/** @var $args */

$default_args = [
    'html_tag' => 'figure',
    'ID' => null,
    'url' => '',
    'alt' => '',
    'title' => '',
    'caption' => '',
    'max_size' => 'thumbnail',
    'post_id' => false,
    'has_overlay' => false,
    'lazyload' => true,
    'show_caption' => false,
    'use_default_image' => false,
    'default_image_field' => 'default_image',
    // Overrides the auto-calculated `sizes` attribute — see .claude/skills/wp-image-performance/SKILL.md.
    // Not named `sizes`: ACF's own image array already has a `sizes` key, which would leak
    // through as a false override on every call that doesn't set this. Don't rename it back.
    'sizes_attr' => null,
    // Eligible to claim the page's one first-image eager/fetchpriority slot — see
    // theme_is_first_priority_image(). False for anything hidden-by-default (mega-menu
    // tab, accordion, modal, dropdown) even if it renders early in PHP execution order.
    'is_priority_candidate' => true,
];

// Add custom static class
$args['class'] = 'image ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

// Destruct Args
[
    'ID' => $image_id,
    'url' => $image_url,
    'alt' => $image_alt,
    'title' => $image_title,
    'caption' => $image_caption,
    'max_size' => $max_size,
    'post_id' => $post_id,
    'lazyload' => $lazyload,
    'show_caption' => $show_caption,
    'use_default_image' => $use_default_image,
    'default_image_field' => $default_image_field,
    'sizes_attr' => $sizes_override,
    'is_priority_candidate' => $is_priority_candidate,
] = $args;

// External image
$is_external_image = false;

// Maybe add the overlay class.
if ($args['has_overlay']) {
    $args['class'] .= ' has-overlay';
}

// Bail early if there's no image ID, no custom URL and no post ID and default_image is set to false.
if (!$image_id && !$image_url && !$post_id && !$use_default_image) return;

// Get image thumbnail
if (!$image_id && $post_id && has_post_thumbnail($post_id)) {
    // Get the image ID from the post thumbnail.
    $image_id = get_post_thumbnail_id($post_id);

    // Bail early if there's no image ID.
    if (!$image_id) return;

    // Get the image meta
    $image_title = !empty($image_title) ? $image_title : get_the_title($image_id);
    $image_alt = !empty($image_alt) ? $image_alt : get_post_meta($image_id, '_wp_attachment_image_alt', true);
    $image_caption = !empty($image_caption) ? $image_caption : get_the_post_thumbnail_caption($post_id);
} else if($image_url && !$image_id && !$post_id) {
    $is_external_image = true;
} else if(!$image_url && !$image_id && !has_post_thumbnail($post_id) && $use_default_image) {
    // Get default image if its set to use it
    $image = options($default_image_field);

    // Return if cant find the field or image
    if (empty($image['image'])) return;

    // Update the vars
    $image_id = $image['image']['ID'];
    $image_title = $image['image']['title'];
    $image_alt = $image['image']['alt'];
    $image_caption = $image['image']['caption'];
}

// Fall back to the attachment's own alt/title.
if ($image_id && !$is_external_image) {
    $image_alt = !empty($image_alt) ? $image_alt : (string) get_post_meta($image_id, '_wp_attachment_image_alt', true);
    $image_title = !empty($image_title) ? $image_title : get_the_title($image_id);
}

// Update alt if its empty
$image_alt = !empty($image_alt) ? $image_alt : $image_title;
$image_alt = !empty($image_alt) ? $image_alt : get_the_title();

// First eligible image on the page gets eager-loading + fetchpriority=high (see
// .claude/skills/wp-image-performance/SKILL.md).
$is_priority_image = $lazyload && $is_priority_candidate && theme_is_first_priority_image();
if ($is_priority_image) $lazyload = false;

// Start html
ob_start();
?>
    <picture class="picture">
        <?php
        if ($is_external_image) {
            printf(
                '<img src="%s" alt="%s" loading="%s"%s class="img"/>',
                esc_url($image_url),
                esc_attr($image_alt),
                $lazyload ? 'lazy' : 'eager',
                $is_priority_image ? ' fetchpriority="high"' : ''
            );
        } else {
            // Built from the same functions wp_get_attachment_image() uses, so a webp
            // <source> (falls back to the <img> below if no webp sibling exists) can be
            // derived from the same src/srcset.
            $src = wp_get_attachment_image_url($image_id, $max_size);
            $srcset = wp_get_attachment_image_srcset($image_id, $max_size);
            $sizes = $sizes_override ?: ($srcset ? wp_get_attachment_image_sizes($image_id, $max_size) : false);

            $webp_src = $src ? theme_webp_url_if_exists($src) : null;
            $webp_srcset = $srcset ? theme_webp_srcset_if_exists($srcset) : null;

            if ($webp_src || $webp_srcset) {
                printf(
                    '<source type="image/webp" srcset="%s"%s>',
                    esc_attr($webp_srcset ?: $webp_src),
                    $sizes ? ' sizes="' . esc_attr($sizes) . '"' : ''
                );
            }

            $img_attr = [
                'class' => 'img',
                'loading' => $lazyload ? 'lazy' : 'eager',
                'fetchpriority' => $is_priority_image ? 'high' : 'auto',
                'alt' => $image_alt,
                'title' => $image_title
            ];
            if ($sizes_override) $img_attr['sizes'] = $sizes_override;

            echo wp_get_attachment_image($image_id, $max_size, false, $img_attr);
        }
        ?>
    </picture>

<?php if ($image_caption && $show_caption): ?>
    <figcaption class="caption"><?= $image_caption ?></figcaption>
<?php endif; ?>
<?php
// Render the component
render_component(ob_get_clean(), $args);