<?php
/**
 * Asset Management
 * Handles CSS/JS enqueuing and nonce management for admin dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue admin dashboard styles and scripts
add_action('admin_enqueue_scripts', 'enqueue_admin_dashboard_assets');
function enqueue_admin_dashboard_assets($hook) {
    if (strpos($hook, 'faculty-management') !== false || 
        strpos($hook, 'escalafon-') !== false ||
        $hook == 'index.php') {
        
        wp_enqueue_style(
            'escalafon-admin-dashboard',
            get_template_directory_uri() . '/assets/css/admin-dashboard.css',
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'escalafon-admin-dashboard',
            get_template_directory_uri() . '/assets/js/admin-dashboard.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('escalafon-admin-dashboard', 'escalafon_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('escalafon_admin_nonce')
        ]);
    }
}

// Add nonce field to admin pages
add_action('admin_head', 'add_escalafon_nonce_field');
function add_escalafon_nonce_field() {
    if (strpos(get_current_screen()->id, 'faculty-management') !== false || 
        strpos(get_current_screen()->id, 'escalafon-') !== false) {
        echo '<input type="hidden" id="escalafon_nonce" value="' . wp_create_nonce('escalafon_admin_nonce') . '">';
    }
}
