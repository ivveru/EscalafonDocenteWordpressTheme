<?php
/**
 * Admin Bar Customization
 * Admin bar removal and UI overrides
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Remove WordPress admin bar completely
add_filter('show_admin_bar', '__return_false');

// Remove admin bar CSS and JS
add_action('get_header', 'remove_admin_bar_css');
function remove_admin_bar_css() {
    remove_action('wp_head', '_admin_bar_bump_cb');
}

// Hide admin bar with CSS as backup
add_action('wp_head', 'hide_admin_bar_css');
function hide_admin_bar_css() {
    echo '<style>
        #wpadminbar { display: none !important; }
        html { margin-top: 0 !important; }
        body.admin-bar { padding-top: 0 !important; }
    </style>';
}
