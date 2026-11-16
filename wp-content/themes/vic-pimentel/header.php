<!DOCTYPE html>
<html lang="<?= get_bloginfo('language'); ?>">
<head>
    <meta charset="utf-8"/>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?= get_field( 'scripts_metatag', 'options' ); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php
        global $page, $paged;
        wp_title( '|', true, 'right' );
        $site_description = get_bloginfo( 'description', 'display' );
        if ( $site_description && ( is_home() || is_front_page() ) )
            echo " | $site_description";
        if ( $paged >= 2 || $page >= 2 )
            echo ' | ' . sprintf( __( 'Page %s' ), max( $paged, $page ) );
        ?>
    </title>
    <link rel="profile" href="http://gmpg.org/xfn/11">

    <?php
    // Fonts are self-hosted (src/assets/fonts/, declared in styles/static/fonts.scss)
    // — no external Google Fonts request happens anymore, so no preconnect/direct
    // <link> is needed here; fonts.css loads as a normal enqueued stylesheet.
    echo get_field('scripts_header', 'option');

    wp_head();

    // Template specific includes
    if (!empty($args)) {
        if (!empty($args['header_includes'])) {
            foreach ($args['header_includes'] as $template_include) {
                versioned_stylesheet($template_include);
            }
        }
    }
    ?>
</head>

<body <?php body_class(); ?>>

<?= get_field('scripts_body', 'option'); ?>

<div class="is-root-container">
    <?php component('header'); ?>
