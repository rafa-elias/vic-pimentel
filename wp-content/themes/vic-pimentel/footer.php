<?php
component('footer');

wp_footer();
?>

<?php
// Template specific includes
if (!empty($args)) {
    if (!empty($args['footer_includes'])) {
        foreach ($args['footer_includes'] as $template_include) {
            versioned_javascript($template_include);
        }
    }
}

echo get_field( 'scripts_footer', 'option' );
?>
</div>
</body>
</html>