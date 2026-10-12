<?php
/**
 * Theme setup and initialization functions
 * Handles automatic creation of required pages and theme configuration
 */

// Define all required pages
function get_required_pages() {
    return array(
        'bienvenida' => array(
            'title' => 'Bienvenida',
            'content' => '<div class="bienvenida-content">
                <h2>Bienvenido al Sistema</h2>
                <p>Este es el sistema de gestión de escalafón de Bellas Artes Institución Universitaria del Valle.</p>
                <p>Desde aquí podrá acceder a todas las funcionalidades del sistema.</p>
            </div>'
        ),
        'perfil' => array(
            'title' => 'Mi Perfil',
            'content' => '[profile_update_form]'
        ),
     /*   'documentos' => array(
            'title' => 'Mis Documentos',
            'content' => '[user_documents]'
        ), */
        'crear_documento' => array(
            'title' => 'Crear Documento',
            'content' => '[create_document_form]'
        ),
        'guardar_documento' => array(
            'title' => 'Guardar Documento',
            'content' => '[save_new_document]'
        ),
        'ver_documento' => array(
            'title' => 'Ver Documento',
            'content' => '[view_document]'
        ),
        'eliminar_documento' => array(
            'title' => 'Eliminar Documento',
            'content' => '[remove_document]'
        ),
        'profesores' => array(
            'title' => 'Profesores',
            'content' => '[list_user_with_documents]'
        ),
        'ver_profesor' => array(
            'title' => 'Ver Profesor',
            'content' => '[show_user_info]'
        ),
        'validar_documento' => array(
            'title' => 'Validar Documento',
            'content' => '[set_document_valid]'
        ),
        'actualizar_perfil' => array(
            'title' => 'Actualizar Perfil',
            'content' => '[update_profile]'
        )
    );
}

// Check for missing required pages
function check_missing_required_pages() {
    $required_pages = get_required_pages();
    $missing_pages = array();
    
    foreach ($required_pages as $slug => $page_data) {
        $page = get_page_by_path($slug);
        if (!$page) {
            $missing_pages[] = array(
                'slug' => $slug,
                'title' => $page_data['title'],
                'content' => $page_data['content']
            );
        }
    }
    
    return $missing_pages;
}

// Create all required pages
function create_all_required_pages() {
    $required_pages = get_required_pages();
    
    foreach ($required_pages as $slug => $page_data) {
        $page = get_page_by_path($slug);
        
        if (!$page) {
            $page_data_array = array(
                'post_title'    => $page_data['title'],
                'post_content'  => $page_data['content'],
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_name'     => $slug,
                'post_author'   => 1,
            );
            
            wp_insert_post($page_data_array);
        }
    }
}

// Auto-create required pages when theme is activated
add_action('after_switch_theme', 'create_required_pages_on_theme_activation');
function create_required_pages_on_theme_activation() {
    create_all_required_pages();
}

// Admin dashboard health check widget
add_action('wp_dashboard_setup', 'add_theme_health_dashboard_widget');
function add_theme_health_dashboard_widget() {
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget(
            'theme_health_check',
            'Theme Health Check',
            'theme_health_dashboard_widget_content'
        );
    }
}

function theme_health_dashboard_widget_content() {
    $missing_pages = check_missing_required_pages();
    
    if (empty($missing_pages)) {
        echo '<p style="color: green;"><strong>✓ All required pages are present.</strong></p>';
        echo '<p><small>Theme is healthy and all required pages exist.</small></p>';
    } else {
        echo '<p style="color: red;"><strong>⚠ Missing ' . count($missing_pages) . ' required pages:</strong></p>';
        echo '<ul>';
        foreach ($missing_pages as $page) {
            echo '<li><strong>' . esc_html($page['title']) . '</strong> (' . esc_html($page['slug']) . ')</li>';
        }
        echo '</ul>';
        echo '<p><a href="' . wp_nonce_url(admin_url('?create_missing_pages=1'), 'create_missing_pages') . '" class="button button-primary">Create Missing Pages</a></p>';
    }
    
    // Show additional theme info
    $total_pages = count(get_required_pages());
    $existing_pages = $total_pages - count($missing_pages);
    echo '<p><small>Pages: ' . $existing_pages . '/' . $total_pages . ' present</small></p>';
}

// Handle the create missing pages action
add_action('admin_init', 'handle_create_missing_pages');
function handle_create_missing_pages() {
    if (isset($_GET['create_missing_pages']) && current_user_can('manage_options')) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'create_missing_pages')) {
            create_all_required_pages();
            wp_redirect(admin_url('?pages_created=1'));
            exit();
        }
    }
}

// Show success notice
add_action('admin_notices', 'show_pages_created_notice');
function show_pages_created_notice() {
    if (isset($_GET['pages_created'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Missing pages created successfully!</p></div>';
    }
}

// Optional: Clean up pages when theme is deactivated
add_action('switch_theme', 'cleanup_theme_pages');
function cleanup_theme_pages() {
    $pages_to_remove = array_keys(get_required_pages());
    
    foreach ($pages_to_remove as $slug) {
        $page = get_page_by_path($slug);
        if ($page) {
            wp_delete_post($page->ID, true);
        }
    }
}
