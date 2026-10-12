<?php
/**
 * Authentication System
 * Login/logout handlers, security checks, and redirect management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function check_login()
{
	if( !is_user_logged_in() )
	{
		wp_logout();
		wp_redirect( home_url() );
		exit();
	}
}

// Override WordPress default login redirect behavior
function custom_login_redirect($redirect_to, $request, $user) {
    // Check if user is logged in and has the right capabilities
    if (is_wp_error($user)) {
        return $redirect_to;
    }
    
    // For users with edit_posts capability (like authors), redirect to frontend
    if (current_user_can('edit_posts')) {
        return home_url('/bienvenida');
    }
    
    // For administrators, allow default behavior
    if (current_user_can('manage_options')) {
        return admin_url();
    }
    
    // Default redirect for other users
    return home_url('/bienvenida');
}

// Hook into WordPress login redirect filter
add_filter('login_redirect', 'custom_login_redirect', 10, 3);

// Hook into WordPress login process to handle custom redirects
add_action('wp_login', 'custom_login_redirect_handler', 10, 2);
function custom_login_redirect_handler($user_login, $user) {
    // Only redirect if this is not an admin login and user is not an administrator
    if (!is_admin() && !current_user_can('manage_options')) {
        wp_redirect(home_url('/bienvenida'));
        exit();
    }
}

// Add custom login endpoint handler
add_action('init', 'handle_custom_login');
function handle_custom_login() {
    // Check if this is a POST request to our custom-login endpoint
    if (isset($_POST['log']) && isset($_POST['pwd']) && isset($_POST['login_nonce'])) {
        // Call the existing login_handler logic directly
        login_handler();
    }
}

add_shortcode( 'login_handler', 'login_handler' );
function login_handler()
{
	// CSRF Protection
	if (!wp_verify_nonce($_POST['login_nonce'], 'custom_login_action')) {
		wp_redirect(home_url() . '?error=security_check_failed');
		exit();
	}
	
	// Check IP rate limiting
	if (!check_ip_rate_limit()) {
		wp_redirect(home_url() . '?error=too_many_attempts');
		exit();
	}
	
	// Enhanced input validation
	$user_login = sanitize_user($_POST["log"]);
	$user_password = $_POST["pwd"];
	
	if (empty($user_login) || empty($user_password)) {
		wp_redirect(home_url() . '?error=empty_fields');
		exit();
	}
	
	// Get user by login or email
	$user = get_user_by_login_or_email($user_login);
	
	if (!$user) {
		// User doesn't exist - generic error
		increment_ip_rate_limit();
		wp_redirect(home_url() . '?error=invalid_credentials');
		exit();
	}
	
	// Check if account is locked
	if (check_account_locked($user->ID)) {
		wp_redirect(home_url() . '?error=account_locked');
		exit();
	}
	
	// WordPress core authentication
	$credentials = [
		'user_login' => $user_login,
		'user_password' => $user_password,
		'remember' => false // No remember me for public computers
	];
	
	$login_result = wp_signon($credentials, true);
	
	if (is_wp_error($login_result)) {
		// Failed login - increment attempts
		$attempts = increment_failed_attempts($user->ID);
		increment_ip_rate_limit();
		
		// Check if account should be locked
		if ($attempts >= 3) {
			lock_user_account($user->ID);
			wp_redirect(home_url() . '?error=account_locked');
			exit();
		}
		
		// Progressive warning messages
		$remaining = get_remaining_attempts($user->ID);
		if ($remaining == 2) {
			wp_redirect(home_url() . '?error=attempts_2');
		} elseif ($remaining == 1) {
			wp_redirect(home_url() . '?error=attempts_1');
		} else {
			wp_redirect(home_url() . '?error=invalid_credentials');
		}
		exit();
	}
	
	// Successful login - clear all counters
	clear_failed_attempts($user->ID);
	clear_ip_rate_limit();
	
	// Set current user - WordPress will handle the redirect through our hooks
	wp_set_current_user($login_result->ID);
	
	// Redirect to frontend for non-admin users
	if (!current_user_can('manage_options')) {
		wp_redirect(home_url('/bienvenida'));
		exit();
	}
}

add_shortcode( 'logout_user', 'logout_user' );
function logout_user()
{
	wp_logout();
	wp_redirect( home_url() );
	exit();
}

/**
 * Después de cerrar sesión, redirigir al home en lugar del login de WP
 */
add_action('wp_logout', 'sged_redirect_after_logout');
function sged_redirect_after_logout() {
    wp_safe_redirect(home_url('/?logged_out=1'));
    exit();
}