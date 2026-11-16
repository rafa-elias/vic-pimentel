<?php

/**
 * Custom WordPress admin Login
 */
class admin_login {
    public function __construct() {
        add_action('login_footer', array('admin_login', 'footer'));
    }

    /**
     * Black or white text, whichever reads on the given background colour.
     */
    public static function text_color(?string $hex): string
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) $hex = preg_replace('/(.)/', '$1$1', $hex);
        if (strlen($hex) !== 6) return '#ffffff';

        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#030303' : '#ffffff';
    }

    public static function footer(): void
    {
        $login = options('login_styles');

        if (isset($login['enable_custom_login']) && $login['enable_custom_login']) :
        ?>
        <style>
            .login {
                background-color: <?= $login['bg_color'] ?>;
            }

            .login h1 a {
                background-image: url('<?= $login['logo']['url']; ?>');
                background-position: center;
                background-size: contain;
                width: 100%;
            }

            .login input[type=text]:focus,
            .login input[type=password]:focus {
                border-color: <?= $login['button_color'] ?>;
                box-shadow: 0 0 0 1px <?= $login['button_color'] ?>;
            }

            .login .button.wp-hide-pw .dashicons {
                color: <?= $login['button_color'] ?>;
            }

            .login .button-primary {
                background: <?= $login['button_color'] ?>;
                border-color: <?= $login['button_color'] ?>;
                color: <?= admin_login::text_color($login['button_color']) ?>;
            }

            .login .button-primary:hover {
                background: <?= $login['button_color'] ?>;
                opacity: 0.8;
            }

            p#nav a,
            p#backtoblog a,
            a.privacy-policy-link {
                color: <?= $login['link_color'] ?> !important;
            }

            .login .language-switcher label .dashicons {
                color: <?= $login['link_color'] ?> !important;
            }
            <?php if (admin_login::text_color($login['bg_color']) === '#ffffff'): // dark page → dark form ?>
            .login #loginform,
            .login #lostpasswordform,
            .login #registerform {
                background: #0b0b0b;
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 8px;
                box-shadow: none;
            }

            .login label {
                color: rgba(255, 255, 255, 0.8);
            }

            .login input[type=text],
            .login input[type=email],
            .login input[type=password],
            .login input[type=checkbox] {
                color: #ffffff;
                background: #141414;
                border-color: rgba(255, 255, 255, 0.12);
            }

            .login input[type=text]:focus,
            .login input[type=email]:focus,
            .login input[type=password]:focus,
            .login input[type=checkbox]:focus {
                border-color: rgba(255, 255, 255, 0.6);
                box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.6);
            }

            .login input:-webkit-autofill {
                -webkit-box-shadow: 0 0 0 1000px #141414 inset;
                -webkit-text-fill-color: #ffffff;
                caret-color: #ffffff;
            }

            .login input[type=checkbox]:checked::before {
                filter: invert(1);
            }

            .login .button.wp-hide-pw .dashicons {
                color: rgba(255, 255, 255, 0.7);
            }

            .login .message,
            .login .notice,
            .login .success,
            .login #login_error {
                color: #ffffff;
                background: #141414;
            }
            <?php endif ?>
        </style>
        <?php
        endif;
    }
}
new admin_login();