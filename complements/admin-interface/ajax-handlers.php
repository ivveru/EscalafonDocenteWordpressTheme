<?php
/**
 * AJAX Handlers
 * AJAX endpoints for dashboard functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// AJAX handlers for dashboard functionality
add_action('wp_ajax_escalafon_refresh_stats', 'handle_refresh_stats_ajax');
function handle_refresh_stats_ajax() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    
    $stats = get_system_statistics();
    $activities = get_recent_activity();
    
    wp_send_json_success([
        'total_users' => $stats['total_users'],
        'pending_documents' => $stats['pending_documents'],
        'locked_accounts' => $stats['locked_accounts'],
        'recent_activity_count' => count($activities)
    ]);
}

add_action('wp_ajax_escalafon_create_test_user', 'handle_create_test_user_ajax');
function handle_create_test_user_ajax() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    
    $role = sanitize_text_field($_POST['role']);
    $result = create_test_user_with_role('test_user_' . time(), 'test@example.com', 'password123', $role);
    
    if ($result) {
        wp_send_json_success(['username' => $result]);
    } else {
        wp_send_json_error('Error creating test user');
    }
}

add_action('wp_ajax_escalafon_unlock_user', 'handle_unlock_user_ajax');
function handle_unlock_user_ajax() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    
    $user_id = intval($_POST['user_id']);
    unlock_user_account($user_id);
    
    wp_send_json_success();
}
