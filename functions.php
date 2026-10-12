<?php
/**
 * Functions.php - Bootstrap File
 * Carga todos los componentes modulares del sistema SGED Bellas Artes
 *
 * @package SGED_BellasArtes
 * @version 2.0.0
 */

// Core error handling and configuration
require_once("complements/core/error-handling.php");

// Theme setup and compatibility
require_once("complements/theme_setup.php");
if (version_compare($GLOBALS['wp_version'], '4.7-alpha', '<')) {
    require get_template_directory() . '/inc/back-compat.php';
    return;
}

// User management system
require_once("complements/user-management/user-roles.php");
require_once("complements/user-management/authentication.php");
require_once("complements/user-management/user-profiles.php");
require_once("complements/user-management/user-admin-interface.php");

// Existing complement files
require_once("complements/user_documents.php");
require_once("complements/user_security.php");

// Admin interface
require_once("complements/admin-interface/admin-menu.php");
require_once("complements/admin-interface/dashboard-widgets.php");
require_once("complements/admin-interface/ajax-handlers.php");

// Admin dashboard functionality
require_once("complements/admin_dashboard.php");
require_once("complements/admin_welcome.php");

// UI customization
require_once("complements/ui-customization/admin-bar.php");

// Asset management
require_once("complements/core/asset-management.php");

// Sistema de Convocatorias
require_once("complements/convocatorias/convocatoria-metaboxes.php");
require_once("complements/convocatorias/frontend-shortcodes.php");

 // Sistema de notificaciones por correo (vía Google Apps Script Webhook)  ← NUEVO
   require_once("complements/notifications/email-notifications.php"); 
// Disable block editor
add_filter('use_block_editor_for_post', '__return_false', 10);
