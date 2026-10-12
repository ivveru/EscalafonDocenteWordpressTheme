<?php
/**
 * Error Handling and Debug Configuration
 * Core error reporting and debug settings for Sistema de Escalafón
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Debug configuration
// Check for a specific debug user via query string or if WP_DEBUG is enabled.
if ( ( isset( $_GET['debug'] ) && 'amorales' === $_GET['debug'] ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
    // Only show errors for the debug user or when WP_DEBUG is true.
    ini_set( 'display_errors', 'On' );
    ini_set( 'error_reporting', E_ALL );
} else {
    // Otherwise, respect the default WordPress settings (usually errors are logged but not displayed).
    ini_set( 'display_errors', 'Off' );
}
