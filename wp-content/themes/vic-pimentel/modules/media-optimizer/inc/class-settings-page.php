<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Settings only — configuration form + the stats summary (also shown on
 * Optimize_Page, since that's the page most likely to be open while actually
 * watching progress). Menu registration lives in Admin_Menu; the bulk
 * "process existing images" tool lives in Optimize_Page.
 */
class Settings_Page
{
    public static function init(): void
    {
        add_action('admin_post_media_optimizer_save_settings', [self::class, 'handle_save']);
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;

        $settings = Settings::get_all();
        $capability = Capability::get();

        wp_enqueue_style(
            'media-optimizer-admin',
            MEDIA_OPTIMIZER_URL . '/assets/admin.css',
            [],
            filemtime(MEDIA_OPTIMIZER_DIR . '/assets/admin.css')
        );
        wp_enqueue_script(
            'media-optimizer-admin',
            MEDIA_OPTIMIZER_URL . '/assets/admin.js',
            [],
            filemtime(MEDIA_OPTIMIZER_DIR . '/assets/admin.js'),
            true
        );
        ?>
        <div class="wrap media-optimizer-settings">
            <h1><?php esc_html_e('MediaTrim — Settings', 'rear-theme'); ?></h1>

            <?php if (isset($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'rear-theme'); ?></p></div>
            <?php endif; ?>

            <?php if ($capability === 'none'): ?>
                <div class="notice notice-warning"><p><?php echo esc_html(Capability::missing_message()); ?></p></div>
            <?php else: ?>
                <div class="media-optimizer-capability-badge media-optimizer-capability-<?php echo esc_attr($capability); ?>">
                    <?php printf(
                        /* translators: %s: capability backend name, e.g. IMAGICK */
                        esc_html__('Using %s for image processing.', 'rear-theme'),
                        esc_html(strtoupper($capability))
                    ); ?>
                </div>
            <?php endif; ?>

            <?php Stats::render(); ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="media-optimizer-form">
                <input type="hidden" name="action" value="media_optimizer_save_settings">
                <?php wp_nonce_field('media_optimizer_save_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Optimize on upload', 'rear-theme'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="optimize_on_upload" value="1" <?php checked($settings['optimize_on_upload']); ?>>
                                <?php esc_html_e('Automatically process new uploads in the background', 'rear-theme'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Uncheck if you are experiencing issues while uploading images (e.g. very large files timing out on a limited host) — images already in the library are unaffected, and can still be processed manually from Optimize Images.', 'rear-theme'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Generate WebP', 'rear-theme'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="generate_webp" value="1" <?php checked($settings['generate_webp']); ?>>
                                <?php esc_html_e('Add a WebP sibling alongside the original', 'rear-theme'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Never touches the original file — zero risk, purely additive.', 'rear-theme'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Re-compress originals', 'rear-theme'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="recompress_originals" value="1" <?php checked($settings['recompress_originals']); ?>>
                                <?php esc_html_e('Shrink the original file itself, not just add WebP', 'rear-theme'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('A backup is always kept before recompressing — restore it any time from the Media Library.', 'rear-theme'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Quality', 'rear-theme'); ?></th>
                        <td>
                            <select name="quality_preset" id="media-optimizer-quality-preset">
                                <option value="low" <?php selected($settings['quality_preset'], 'low'); ?>><?php esc_html_e('Low (smallest files)', 'rear-theme'); ?></option>
                                <option value="balanced" <?php selected($settings['quality_preset'], 'balanced'); ?>><?php esc_html_e('Balanced (recommended)', 'rear-theme'); ?></option>
                                <option value="high" <?php selected($settings['quality_preset'], 'high'); ?>><?php esc_html_e('High (largest files, best quality)', 'rear-theme'); ?></option>
                                <option value="custom" <?php selected($settings['quality_preset'], 'custom'); ?>><?php esc_html_e('Custom', 'rear-theme'); ?></option>
                            </select>

                            <div class="media-optimizer-custom-quality" data-quality-preset-fields <?php echo $settings['quality_preset'] === 'custom' ? '' : 'hidden'; ?>>
                                <p>
                                    <label>
                                        <?php esc_html_e('WebP quality (1-100)', 'rear-theme'); ?>
                                        <input type="number" name="webp_quality" min="1" max="100" value="<?php echo esc_attr($settings['webp_quality']); ?>">
                                    </label>
                                </p>
                                <p>
                                    <label>
                                        <?php esc_html_e('Re-compression quality (1-100)', 'rear-theme'); ?>
                                        <input type="number" name="recompress_quality" min="1" max="100" value="<?php echo esc_attr($settings['recompress_quality']); ?>">
                                    </label>
                                </p>
                            </div>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'rear-theme')); ?>
            </form>

            <p class="description">
                <?php
                printf(
                    /* translators: %s: link to the Optimize Images page */
                    esc_html__('Want to process images already in your library? Head to %s.', 'rear-theme'),
                    '<a href="' . esc_url(admin_url('admin.php?page=' . Admin_Menu::OPTIMIZE_SLUG)) . '">' . esc_html__('Optimize Images', 'rear-theme') . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    public static function handle_save(): void
    {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'rear-theme'));
        check_admin_referer('media_optimizer_save_settings');

        $preset = sanitize_key($_POST['quality_preset'] ?? 'balanced');
        if (!in_array($preset, ['low', 'balanced', 'high', 'custom'], true)) $preset = 'balanced';

        Settings::update([
            'optimize_on_upload' => !empty($_POST['optimize_on_upload']),
            'generate_webp' => !empty($_POST['generate_webp']),
            'recompress_originals' => !empty($_POST['recompress_originals']),
            'quality_preset' => $preset,
            'webp_quality' => max(1, min(100, (int) ($_POST['webp_quality'] ?? 75))),
            'recompress_quality' => max(1, min(100, (int) ($_POST['recompress_quality'] ?? 82))),
        ]);

        Stats::clear_cache();

        wp_safe_redirect(add_query_arg(['page' => Admin_Menu::SETTINGS_SLUG, 'updated' => 1], admin_url('admin.php')));
        exit;
    }
}
