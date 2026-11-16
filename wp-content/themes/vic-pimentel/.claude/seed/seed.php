<?php
/**
 * Demo content seeder — run with:  lando wp eval-file wp-content/themes/vic-pimentel/.claude/seed/seed.php
 *
 * Idempotent: media is matched by a `_vp_seed` meta, projects/pages by slug, so re-running
 * updates instead of duplicating. Dev-only — never run against production content.
 */

if (!defined('ABSPATH')) exit;

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$dir = __DIR__ . '/assets';
$theme = get_template_directory();
$lorem = 'Designing is an intricate dance between utility and aesthetics, where every choice can make or break the final outcome. It encompasses a wide array of disciplines, from graphic design to industrial innovation, each with its unique challenges and opportunities. To embark on a successful design journey, one must deeply understand user needs, brainstorm imaginative ideas, and meticulously hone those concepts into polished masterpieces.';
$lorem_short = 'Design is an intricate dance between functionality and aesthetics, where every choice can profoundly influence the final outcome.';

// ------------------------------------------------------------------ media
function vp_seed_media(string $file, string $title = ''): int
{
    $found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_vp_seed', 'meta_value' => basename($file), 'fields' => 'ids', 'numberposts' => 1]);
    if ($found) return (int) $found[0];

    $tmp = wp_tempnam(basename($file));
    copy($file, $tmp);
    $id = media_handle_sideload(['name' => basename($file), 'tmp_name' => $tmp], 0, $title ?: null);

    if (is_wp_error($id)) {
        WP_CLI::warning(basename($file) . ': ' . $id->get_error_message());
        return 0;
    }

    update_post_meta($id, '_vp_seed', basename($file));
    if ($title) update_post_meta($id, '_wp_attachment_image_alt', $title);

    return (int) $id;
}

// ------------------------------------------------------------------ ACF block markup
function vp_seed_fields_index(string $block): array
{
    static $cache = [];
    if (isset($cache[$block])) return $cache[$block];

    $json = json_decode(file_get_contents(get_template_directory() . "/src/content/blocks/custom/$block/acf-json/block-$block.json"), true);
    $index = function ($fields) use (&$index) {
        $out = [];
        foreach ($fields as $f) {
            if (!$f['name']) continue;
            $out[$f['name']] = ['key' => $f['key'], 'type' => $f['type'], 'sub' => !empty($f['sub_fields']) ? $index($f['sub_fields']) : []];
        }
        return $out;
    };

    return $cache[$block] = $index($json['fields']);
}

function vp_seed_flatten(array $values, array $index, string $prefix = ''): array
{
    $data = [];

    foreach ($values as $name => $value) {
        if (!isset($index[$name])) continue;
        $field = $index[$name];
        $meta = $prefix . $name;

        if ($field['type'] === 'repeater') {
            $data[$meta] = count($value);
            $data['_' . $meta] = $field['key'];
            foreach (array_values($value) as $i => $row) {
                $data += vp_seed_flatten($row, $field['sub'], "{$meta}_{$i}_");
            }
            continue;
        }

        if ($field['type'] === 'true_false') $value = $value ? '1' : '0';

        $data[$meta] = $value;
        $data['_' . $meta] = $field['key'];
    }

    return $data;
}

function vp_seed_block(string $block, array $values): string
{
    $data = vp_seed_flatten($values, vp_seed_fields_index($block));
    $attrs = ['name' => "acf/$block", 'data' => $data, 'mode' => 'preview'];

    return '<!-- wp:acf/' . $block . ' ' . wp_json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ' /-->';
}

function vp_seed_post(string $type, string $slug, string $title, string $content, array $extra = []): int
{
    // Not get_page_by_path(): it also matches attachments sharing the slug.
    $found = get_posts(['name' => $slug, 'post_type' => $type, 'post_status' => 'any', 'numberposts' => 1]);
    $existing = $found[0] ?? null;
    $post = array_merge([
        'post_type' => $type,
        'post_name' => $slug,
        'post_title' => $title,
        'post_status' => 'publish',
        'post_content' => $content,
    ], $extra);

    if ($existing) $post['ID'] = $existing->ID;

    $id = $existing ? wp_update_post(wp_slash($post), true) : wp_insert_post(wp_slash($post), true);

    if (is_wp_error($id)) WP_CLI::error($id->get_error_message());

    return (int) $id;
}

// ------------------------------------------------------------------ site settings
update_option('blogname', 'Vic Pimentel');
update_option('blogdescription', 'Product Designer & Illustrator');
update_option('permalink_structure', '/%postname%/');
update_option('timezone_string', 'Europe/Rome');
// The framework's max_size guidance assumes 'medium' = 768 (Settings → Media).
update_option('medium_size_w', 768);
update_option('medium_size_h', 768);

// ------------------------------------------------------------------ categories
$uncategorized = get_term(get_option('default_category'), 'category');
if ($uncategorized && $uncategorized->slug === 'uncategorized') {
    wp_update_term($uncategorized->term_id, 'category', ['name' => 'Product Design', 'slug' => 'product-design']);
}

$cats = [];
foreach (['product-design' => 'Product Design', 'branding' => 'Branding', 'illustration' => 'Illustration'] as $slug => $name) {
    $term = get_term_by('slug', $slug, 'category') ?: (object) wp_insert_term($name, 'category', ['slug' => $slug]);
    $cats[$slug] = (int) ($term->term_id ?? $term->term_id);
}
update_option('default_category', $cats['product-design']);

// Remove the default "Hello world!" post and "Sample Page".
foreach ([['hello-world', 'post'], ['sample-page', 'page']] as [$slug, $type]) {
    if ($p = get_page_by_path($slug, OBJECT, $type)) wp_delete_post($p->ID, true);
}

// ------------------------------------------------------------------ projects
$base = [
    ['tianma-america', 'Tianma America', 'product-design', true],
    ['northwind-bank', 'Northwind Bank', 'product-design', true],
    ['atlas-travel', 'Atlas Travel', 'product-design', false],
    ['lumen-health', 'Lumen Health', 'product-design', true],
    ['orbit-analytics', 'Orbit Analytics', 'product-design', false],
    ['kinfolk-coffee', 'Kinfolk Coffee', 'branding', true],
    ['sora-studio', 'Sora Studio', 'branding', false],
    ['verde-market', 'Verde Market', 'branding', false],
    ['midnight-tales', 'Midnight Tales', 'illustration', true],
    ['field-notes', 'Field Notes', 'illustration', false],
];
// Second batch reuses the alternate artwork so the grid has enough items to feel real.
$second = [
    ['tianma-america', 'Halo Mobility', 'product-design'],
    ['northwind-bank', 'Pillar Insurance', 'product-design'],
    ['atlas-travel', 'Wayfarer Hotels', 'product-design'],
    ['lumen-health', 'Cadence Fitness', 'product-design'],
    ['orbit-analytics', 'Signal Labs', 'product-design'],
    ['kinfolk-coffee', 'Ember Bakery', 'branding'],
    ['verde-market', 'Grove Botanics', 'branding'],
    ['field-notes', 'Paper Harbor', 'illustration'],
];

$project_ids = [];
$day = 0;

$all = array_merge(
    array_map(fn($p) => ['art' => $p[0], 'name' => $p[1], 'cat' => $p[2], 'video' => $p[3], 'alt' => false], $base),
    array_map(fn($p) => ['art' => $p[0], 'name' => $p[1], 'cat' => $p[2], 'video' => false, 'alt' => true], $second)
);

foreach ($all as $i => $p) {
    $art = $p['art'];
    $image = vp_seed_media("$dir/$art" . ($p['alt'] ? '-alt' : '') . '.jpg', $p['name']);
    $image_alt = vp_seed_media("$dir/$art" . ($p['alt'] ? '' : '-alt') . '.jpg', $p['name'] . ' — detail');
    $square = file_exists("$dir/$art-square.jpg") ? vp_seed_media("$dir/$art-square.jpg", $p['name'] . ' — square') : $image_alt;
    $favicon = vp_seed_media("$dir/favicon-$art.png", $p['name'] . ' favicon');
    $video = $p['video'] && file_exists("$dir/$art.mp4") ? vp_seed_media("$dir/$art.mp4", $p['name'] . ' loop') : 0;

    $content = implode("\n\n", [
        vp_seed_block('post-intro', ['text' => 'Designing is an intricate dance between utility and aesthetics, where every choice can make or break the final outcome. It encompasses a wide array of disciplines, from graphic design to industrial innovation, each with its unique challenges and opportunities.']),
        vp_seed_block('post-text', ['heading' => 'About The Project', 'text' => "<p>$lorem</p>"]),
        vp_seed_block('post-media', ['image' => $image_alt, 'video' => $video ?: '', 'show_caption' => true, 'caption' => 'To embark on a successful design journey, one must deeply understand user needs, brainstorm imaginative ideas, and meticulously hone']),
        vp_seed_block('post-media-pair', ['show_captions' => true, 'items' => [
            ['image' => $square, 'caption' => 'To embark on a successful design journey, one must deeply understand user needs, brainstorm imaginative ideas, and meticulously hone'],
            ['image' => $image, 'caption' => 'To embark on a successful design journey, one must deeply understand user needs, brainstorm imaginative ideas, and meticulously hone'],
        ]]),
        vp_seed_block('post-text', ['heading' => 'The Challenge', 'text' => "<p>$lorem</p>"]),
        vp_seed_block('post-text', ['heading' => 'The Solution', 'text' => "<p>$lorem</p>"]),
        vp_seed_block('post-text', ['heading' => 'The Results', 'text' => "<p>$lorem</p>"]),
        vp_seed_block('post-stats', ['stats' => [
            ['overline' => 'Up To', 'value' => '48x Faster', 'text' => 'This process not only involves creativity but also a keen insight into market'],
            ['overline' => 'Up To', 'value' => '3.2x Conversion', 'text' => 'This process not only involves creativity but also a keen insight into market'],
            ['overline' => 'Over', 'value' => '1M Users', 'text' => 'This process not only involves creativity but also a keen insight into market'],
        ]]),
        vp_seed_block('post-project-info', ['title' => 'Project Info', 'rows' => [
            ['label' => 'Client', 'value' => $p['name']],
            ['label' => 'Agency', 'value' => 'Blacksmith Agency'],
            ['label' => 'Year', 'value' => '2025'],
            ['label' => 'Account Executive', 'value' => 'Brandi Dockalon'],
            ['label' => 'Project Manager', 'value' => 'Kara Akers'],
            ['label' => 'Art Director', 'value' => 'Juan Sellai'],
            ['label' => 'Principal Designer', 'value' => 'Victor Pimentel'],
            ['label' => 'Tech Lead', 'value' => 'Rufat Mammadyarov'],
            ['label' => 'Principal Developer', 'value' => 'Steven Choi'],
        ]]),
    ]);

    $slug = sanitize_title($p['name']);
    $id = vp_seed_post('post', $slug, $p['name'], $content, [
        'post_date' => wp_date('Y-m-d H:i:s', strtotime("-{$day} days")),
        'post_category' => [$cats[$p['cat']]],
    ]);
    $day += 9;

    update_field('company_name', $p['name'], $id);
    update_field('media_image', $image, $id);
    update_field('media_video', $video ?: '', $id);
    update_field('favicon', $favicon, $id);
    update_field('show_favicon', $i % 7 === 6 ? 0 : 1, $id); // a couple without favicon
    set_post_thumbnail($id, $image);

    // Highlights on every other web project.
    $highlights = [];
    if ($p['cat'] === 'product-design' && $i % 2 === 0) {
        foreach (['Hero', 'Navigation', 'Pricing', 'Testimonials', 'Footer', 'Onboarding'] as $h => $label) {
            $highlights[] = ['title' => $label, 'image' => $h % 2 ? $image : $image_alt, 'video' => ($h === 0 && $video) ? $video : ''];
        }
    }
    update_field('show_highlights', $highlights ? 1 : 0, $id);
    update_field('highlights', $highlights, $id);

    $project_ids[] = $id;
    WP_CLI::log("Project: {$p['name']} (#$id)" . ($video ? ' + video' : ''));
}

// Featured (stored on the Project Listing block): up to 16, in order.
$featured = array_map('strval', array_slice($project_ids, 0, 16));

// ------------------------------------------------------------------ pages
$home = vp_seed_post('page', 'work', 'Work', implode("\n\n", [
    vp_seed_block('hero-home', [
        'intro' => 'Designing is an intricate dance between utility and aesthetics, where every choice can make or break the final outcome. It encompasses a wide array of disciplines,',
        'intro_muted' => 'from graphic design to industrial innovation, each with its unique challenges and opportunities.',
        'headline_left' => 'Creative',
        'headline_center' => '&',
        'headline_right' => 'Cool Stuff',
    ]),
    vp_seed_block('project-listing', [
        'show_featured' => true,
        'featured_label' => 'Featured',
        'featured_posts' => $featured,
        'results_label' => 'Showing { %d Results }',
    ]),
]));

$portrait = vp_seed_media("$dir/about-portrait.jpg", 'Victor Pimentel');
$cv = vp_seed_media("$dir/victor-pimentel-curriculum.pdf", 'Victor Pimentel — Curriculum');
$card = vp_seed_media("$dir/victor-pimentel-digital-card.pdf", 'Victor Pimentel — Digital Card');
$text = "<p>$lorem</p>";

$about = vp_seed_post('page', 'about', 'About', implode("\n\n", [
    vp_seed_block('about-hero', [
        'image' => $portrait,
        'headline' => "Hi! I’m Victor, a *Designer and Illustrator* from Italy. I enjoy creating visuals that tell stories and inspire creativity.",
        'text' => '<p><strong>Design is an intricate dance between functionality and aesthetics,</strong> where every choice can profoundly influence the final outcome. It encompasses a wide array of disciplines, from graphic design to product development, each offering unique challenges and opportunities. To embark on a successful design journey, one must deeply understand user needs, delve into imaginative concepts, and meticulously refine those ideas into breathtaking final products.</p>',
    ]),
    vp_seed_block('timeline', ['rows' => [
        ['label' => 'The Beginning', 'years' => '2017 - 2019', 'title' => 'Designing is an intricate dance between utility and aesthetics.', 'text' => $text],
        ['label' => 'The Middle', 'years' => '2019 - 2021', 'title' => 'Designing is an intricate dance between utility and aesthetics.', 'text' => $text],
        ['label' => 'Right Now', 'years' => '2021 - Present', 'title' => 'Designing is an intricate dance between utility and aesthetics.', 'text' => $text],
    ]]),
    vp_seed_block('services-list', ['heading' => 'What Are *My Main Services?*', 'items' => [
        ['title' => 'Product Design', 'text' => $lorem_short . ' It encompasses a wide array of disciplines, from graphic design'],
        ['title' => 'Branding', 'text' => $lorem_short . ' It encompasses a wide array of disciplines, from graphic design'],
        ['title' => 'Illustration', 'text' => $lorem_short . ' It encompasses a wide array of disciplines, from graphic design'],
        ['title' => 'Design Systems', 'text' => $lorem_short . ' It encompasses a wide array of disciplines, from graphic design'],
    ]]),
    vp_seed_block('text-section', ['heading' => 'My *Approach*', 'text' => $text, 'show_divider' => true]),
    vp_seed_block('text-section', ['heading' => 'Skills *& Tools*', 'show_divider' => true, 'chips' => array_map(fn($l) => ['label' => $l], [
        'Figma', 'Adobe Creative Suite', 'Design Systems', 'Design Ops', 'Figma Variables', 'Adobe Firefly', 'Claude Design', 'Claude Code', 'Google Nano Banana', 'Figma Make',
    ])]),
    vp_seed_block('text-section', ['heading' => 'What Inspires Me and *Why Design?*', 'text' => $text, 'show_divider' => true, 'list' => [
        ['icon' => 'square-stack', 'title' => 'Product Design', 'text' => 'Design is an intricate dance between functionality'],
        ['icon' => 'sparkle', 'title' => 'Branding', 'text' => 'Design is an intricate dance between functionality'],
        ['icon' => 'circle', 'title' => 'Illustration', 'text' => 'Design is an intricate dance between functionality'],
        ['icon' => 'arrow-up-right', 'title' => 'Motion', 'text' => 'Design is an intricate dance between functionality'],
    ]]),
    vp_seed_block('text-section', ['heading' => 'What Are My *Future Goals?*', 'text' => $text, 'show_divider' => true, 'buttons' => [
        ['style' => 'pill', 'link' => ['title' => 'Get In Touch', 'url' => '#contact', 'target' => '']],
        ['style' => 'pill', 'link' => ['title' => 'Download My Curriculum', 'url' => wp_get_attachment_url($cv), 'target' => '_blank']],
        ['style' => 'pill', 'link' => ['title' => 'Check My LinkedIn', 'url' => 'https://www.linkedin.com/', 'target' => '_blank']],
    ]]),
]));

update_option('show_on_front', 'page');
update_option('page_on_front', $home);

// ------------------------------------------------------------------ menu
$menu_name = 'Header Navigation';
$menu = wp_get_nav_menu_object($menu_name);
$menu_id = $menu ? $menu->term_id : wp_create_nav_menu($menu_name);

foreach (wp_get_nav_menu_items($menu_id) ?: [] as $item) wp_delete_post($item->ID, true);

wp_update_nav_menu_item($menu_id, 0, ['menu-item-title' => 'Work', 'menu-item-object' => 'page', 'menu-item-object-id' => $home, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => 1]);
wp_update_nav_menu_item($menu_id, 0, ['menu-item-title' => 'About', 'menu-item-object' => 'page', 'menu-item-object-id' => $about, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => 2]);
wp_update_nav_menu_item($menu_id, 0, ['menu-item-title' => 'Contact', 'menu-item-url' => '#contact', 'menu-item-type' => 'custom', 'menu-item-status' => 'publish', 'menu-item-position' => 3]);

$locations = get_theme_mod('nav_menu_locations', []);
$locations['header'] = $menu_id;
set_theme_mod('nav_menu_locations', $locations);

// ------------------------------------------------------------------ options
update_field('header', [
    'logo' => '',
    'location_title' => 'Based in Italy',
    'location_subtitle' => 'Working Globally',
], 'option');

update_field('footer', [
    'heading' => 'Get *In Touch*',
    'email' => 'mr.vicpimentel@gmail.com',
    'columns' => [
        ['title' => 'Network', 'links' => [
            ['link' => ['title' => 'LinkedIn', 'url' => 'https://www.linkedin.com/', 'target' => '_blank'], 'is_download' => 0],
            ['link' => ['title' => 'Instagram', 'url' => 'https://www.instagram.com/', 'target' => '_blank'], 'is_download' => 0],
            ['link' => ['title' => 'Behance', 'url' => 'https://www.behance.net/', 'target' => '_blank'], 'is_download' => 0],
        ]],
        ['title' => 'Site Map', 'links' => [
            ['link' => ['title' => 'Work', 'url' => home_url('/'), 'target' => ''], 'is_download' => 0],
            ['link' => ['title' => 'About Me', 'url' => get_permalink($about), 'target' => ''], 'is_download' => 0],
        ]],
        ['title' => 'Quick Links', 'links' => [
            ['link' => ['title' => 'Download my curriculum', 'url' => wp_get_attachment_url($cv), 'target' => ''], 'is_download' => 1],
            ['link' => ['title' => 'Download my digital card', 'url' => wp_get_attachment_url($card), 'target' => ''], 'is_download' => 1],
        ]],
    ],
    'signature' => '*Vic*Pimentel',
    'text' => 'Designing is an intricate dance between utility and aesthetics, where every choice can make or break the final outcome. It encompasses',
    'label' => '*Portfolio* {year}',
], 'option');

update_field('default_image', ['image' => vp_seed_media("$dir/default-image.jpg", 'Vic Pimentel')], 'option');

// Portfolio access — demo password "portfolio" (change it in Site Settings → Portfolio Access).
update_field('access_enabled', 1, 'option');
update_field('access_cookie_days', 2, 'option');
update_field('access_redirect', 'home', 'option');
update_field('access_loading_seconds', 4, 'option');
foreach ([
    'title' => 'Welcome To My Portfolio!',
    'text' => 'Designing is an intricate dance between utility and aesthetics, where every choice can make or break the final outcome. It encompasses.',
    'placeholder' => 'Password',
    'button' => 'Access Now',
    'contact_text' => "If you don't have a password contact me",
    'contact_email' => 'mr.vicpimentel@gmail.com',
    'error' => 'That password is not correct. Please try again.',
    'loading_title' => 'This Contains Confidential Information',
    'loading_text' => 'Design is an intricate dance between functionality and aesthetics, where every choice can profoundly influence the final outcome. It encompasses a wide array of disciplines, from graphic design to product development, each offering unique challenges and opportunities. To embark on a successful design journey.',
] as $name => $value) {
    update_field('access_' . $name, $value, 'option');
}
if (!get_field('access_password', 'option')) {
    update_field('access_password', 'portfolio', 'option');
}
delete_option('vp_access_password_hash'); // legacy (hashed) storage

// wp-admin login screen branding (SVG upload needs an admin user — modules/svg-support.php).
wp_set_current_user(1);
$login_logo = vp_seed_media("$dir/logo-white.svg", 'Vic Pimentel logo');
update_field('login_styles', [
    'enable_custom_login' => 1,
    'logo' => $login_logo,
    'bg_color' => '#030303',
    'button_color' => '#ffffff',
    'link_color' => '#ffffff',
], 'option');

// Site icon (favicon / app icon) — "Vp" monogram, generator/favicon.js.
update_option('site_icon', vp_seed_media("$dir/site-icon.png", 'Vic Pimentel icon'));

// Animated backgrounds: no Unicorn scenes yet — the built-in WebGL flow (scene-flow.js)
// renders until Victor's exports are uploaded on Site Settings → Animations.

flush_rewrite_rules();

WP_CLI::success(sprintf('Seeded %d projects, Work (#%d) and About (#%d).', count($project_ids), $home, $about));
