<?php
/**
 * Registro simple de Convocatorias
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------
// REGISTRAR POST TYPE CONVOCATORIA (con prioridad alta)
// -----------------------------------------------------------------
add_action('init', 'registrar_convocatoria_simple', 0);
function registrar_convocatoria_simple() {
    
    $args = array(
        'labels' => array(
            'name' => 'Convocatorias',
            'singular_name' => 'Convocatoria',
            'add_new' => 'Agregar Nueva',
            'add_new_item' => 'Agregar Nueva Convocatoria',
            'edit_item' => 'Editar Convocatoria',
            'new_item' => 'Nueva Convocatoria',
            'view_item' => 'Ver Convocatoria',
            'search_items' => 'Buscar Convocatorias',
            'not_found' => 'No se encontraron convocatorias',
            'not_found_in_trash' => 'No hay convocatorias en la papelera',
        ),
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true, // CAMBIADO: aparece como menú independiente
        'menu_position' => 25,
        'menu_icon' => 'dashicons-megaphone',
        'supports' => array('title', 'editor'),
        'has_archive' => true,
        'rewrite' => array('slug' => 'convocatoria'),
    );
    
    register_post_type('convocatoria', $args);
}

// -----------------------------------------------------------------
// METABOX DE FECHAS
// -----------------------------------------------------------------
add_action('add_meta_boxes', 'agregar_metabox_fechas_simple');
function agregar_metabox_fechas_simple() {
    add_meta_box(
        'fechas_convocatoria',
        'Fechas de la Convocatoria',
        'render_metabox_fechas_simple',
        'convocatoria',
        'normal',
        'high'
    );
}

function render_metabox_fechas_simple($post) {
    // Nonce
    wp_nonce_field('guardar_fechas', 'fechas_nonce');
    
    // Obtener valores
    $ini_carga = get_post_meta($post->ID, 'convocatoria_fecha_ini_carga', true);
    $fin_carga = get_post_meta($post->ID, 'convocatoria_fecha_fin_carga', true);
    $ini_revi = get_post_meta($post->ID, 'convocatoria_fecha_ini_revi', true);
    $fin_revi = get_post_meta($post->ID, 'convocatoria_fecha_fin_revi', true);
    ?>
    
    <p>
        <label><strong>Fecha inicio carga:</strong></label><br>
        <input type="datetime-local" name="fecha_ini_carga" value="<?php echo esc_attr($ini_carga); ?>" style="width: 100%;">
    </p>
    <p>
        <label><strong>Fecha fin carga:</strong></label><br>
        <input type="datetime-local" name="fecha_fin_carga" value="<?php echo esc_attr($fin_carga); ?>" style="width: 100%;">
    </p>
    <p>
        <label><strong>Fecha inicio revisión:</strong></label><br>
        <input type="datetime-local" name="fecha_ini_revi" value="<?php echo esc_attr($ini_revi); ?>" style="width: 100%;">
    </p>
    <p>
        <label><strong>Fecha fin revisión:</strong></label><br>
        <input type="datetime-local" name="fecha_fin_revi" value="<?php echo esc_attr($fin_revi); ?>" style="width: 100%;">
    </p>
    
    <?php
}

add_action('save_post_convocatoria', 'guardar_fechas_simple');
function guardar_fechas_simple($post_id) {
    
    if (!isset($_POST['fechas_nonce']) || !wp_verify_nonce($_POST['fechas_nonce'], 'guardar_fechas')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    $campos = ['fecha_ini_carga', 'fecha_fin_carga', 'fecha_ini_revi', 'fecha_fin_revi'];
    
    foreach ($campos as $campo) {
        if (isset($_POST[$campo])) {
            update_post_meta($post_id, 'convocatoria_' . $campo, sanitize_text_field($_POST[$campo]));
        }
    }
}