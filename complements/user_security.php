<?php
/**
 * User Security Functions
 * Enhanced login security using WordPress core functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if user account is locked
 */
function check_account_locked($user_id) {
    return get_user_meta($user_id, 'account_locked', true) === '1';
}

/**
 * Lock user account
 */
function lock_user_account($user_id) {
    update_user_meta($user_id, 'account_locked', '1');
    update_user_meta($user_id, 'lockout_timestamp', current_time('timestamp'));
    
    // Log lockout event
    $lockout_logs = get_user_meta($user_id, 'lockout_logs', true);
    if (!is_array($lockout_logs)) {
        $lockout_logs = [];
    }
    
    $lockout_logs[] = [
        'timestamp' => current_time('mysql'),
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ];
    
    // Keep only last 10 lockout records
    if (count($lockout_logs) > 10) {
        $lockout_logs = array_slice($lockout_logs, -10);
    }
    
    update_user_meta($user_id, 'lockout_logs', $lockout_logs);
}

/**
 * Unlock user account
 */
function unlock_user_account($user_id) {
    delete_user_meta($user_id, 'account_locked');
    delete_user_meta($user_id, 'failed_login_attempts');
    delete_user_meta($user_id, 'lockout_timestamp');
}

/**
 * Get failed login attempts count
 */
function get_failed_attempts($user_id) {
    return (int) get_user_meta($user_id, 'failed_login_attempts', true);
}

/**
 * Increment failed login attempts
 */
function increment_failed_attempts($user_id) {
    $attempts = get_failed_attempts($user_id) + 1;
    update_user_meta($user_id, 'failed_login_attempts', $attempts);
    return $attempts;
}

/**
 * Clear failed login attempts
 */
function clear_failed_attempts($user_id) {
    delete_user_meta($user_id, 'failed_login_attempts');
    delete_user_meta($user_id, 'account_locked');
    delete_user_meta($user_id, 'lockout_timestamp');
}

/**
 * Get remaining attempts before lockout
 */
function get_remaining_attempts($user_id) {
    $attempts = get_failed_attempts($user_id);
    return max(0, 3 - $attempts);
}

/**
 * Check IP rate limiting
 */
function check_ip_rate_limit() {
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $attempts_key = 'login_attempts_ip_' . $user_ip;
    $attempts = get_transient($attempts_key);
    
    if ($attempts && $attempts >= 5) {
        return false; // Rate limited
    }
    
    return true;
}

/**
 * Increment IP rate limiting
 */
function increment_ip_rate_limit() {
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $attempts_key = 'login_attempts_ip_' . $user_ip;
    $attempts = get_transient($attempts_key);
    $attempts = $attempts ? $attempts + 1 : 1;
    set_transient($attempts_key, $attempts, 15 * MINUTE_IN_SECONDS);
}

/**
 * Clear IP rate limiting
 */
function clear_ip_rate_limit() {
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $attempts_key = 'login_attempts_ip_' . $user_ip;
    delete_transient($attempts_key);
}

/**
 * Get user by login (email or username)
 */
function get_user_by_login_or_email($login) {
    $user = get_user_by('login', $login);
    if (!$user) {
        $user = get_user_by('email', $login);
    }
    return $user;
}

/**
 * Create test user for testing purposes
 */
function create_test_user($username = 'testuser', $email = 'test@example.com', $password = 'testpass123') {
    $user_id = wp_create_user($username, $password, $email);
    
    if (!is_wp_error($user_id)) {
        // Add basic user meta
        update_user_meta($user_id, 'first_name', 'Test');
        update_user_meta($user_id, 'last_name', 'User');
        update_user_meta($user_id, 'faculty', 'Test Faculty');
        
        // Give faculty role by default
        $user = new WP_User($user_id);
        $user->set_role('faculty');
        
        return $user_id;
    }
    
    return false;
}

/**
 * Create test user with specific university role
 */
function create_test_user_with_role($username = 'testuser', $email = 'test@example.com', $password = 'testpass123', $role = 'faculty') {
    $user_id = wp_create_user($username, $password, $email);
    
    if (!is_wp_error($user_id)) {
        // Add basic user meta
        update_user_meta($user_id, 'first_name', 'Test');
        update_user_meta($user_id, 'last_name', 'User');
        update_user_meta($user_id, 'faculty', 'Test Faculty');
        
        // Assign specified university role
        $user = new WP_User($user_id);
        $user->set_role($role);
        
        return $user_id;
    }
    
    return false;
}

/**
 * Simulate failed login attempts for testing
 */
function simulate_failed_attempts($user_id, $count = 3) {
    for ($i = 0; $i < $count; $i++) {
        increment_failed_attempts($user_id);
    }
    
    if (get_failed_attempts($user_id) >= 3) {
        lock_user_account($user_id);
    }
}

/**
 * Get lockout status for admin display
 */
function get_user_lockout_status($user_id) {
    if (check_account_locked($user_id)) {
        $lockout_time = get_user_meta($user_id, 'lockout_timestamp', true);
        $time_ago = human_time_diff($lockout_time, current_time('timestamp'));
        return "Locked ({$time_ago} ago)";
    }
    
    $attempts = get_failed_attempts($user_id);
    if ($attempts > 0) {
        return "{$attempts} failed attempts";
    }
    
    return "Active";
}
