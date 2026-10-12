<?php
/**
 * Admin Menu Structure
 * Custom admin menu for Sistema de Escalafón
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Custom admin menu for Sistema de Escalafón
add_action('admin_menu', 'add_escalafon_admin_menu');
function add_escalafon_admin_menu() {
    if (current_user_can('manage_options')) {
        add_menu_page(
            'Sistema de Escalafón',
            'Sistema de Escalafón',
            'manage_options',
            'faculty-management',
            'render_system_overview_page',
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'faculty-management',
            'Dashboard del Sistema',
            'Dashboard del Sistema',
            'manage_options',
            'faculty-management',
            'render_system_overview_page'
        );
        
        add_submenu_page(
            'faculty-management',
            'Gestión de Usuarios',
            'Gestión de Usuarios',
            'manage_options',
            'escalafon-user-management',
            'render_user_management_page'
        );
        
        add_submenu_page(
            'faculty-management',
            'Documentos Pendientes',
            'Documentos Pendientes',
            'manage_options',
            'escalafon-pending-documents',
            'render_pending_documents_page'
        );
        
        add_submenu_page(
            'faculty-management',
            'Ayuda y Configuración',
            'Ayuda y Configuración',
            'manage_options',
            'escalafon-help-config',
            'render_help_configuration_page'
        );
    }
}

// Welcome page functionality
add_action('admin_menu', 'add_welcome_page_to_menu');
add_action('admin_notices', 'show_welcome_notice');

// Handle welcome page dismissal
add_action('admin_init', 'dismiss_welcome_page');

// Mark theme as activated on theme activation
add_action('after_switch_theme', 'mark_theme_activated');
