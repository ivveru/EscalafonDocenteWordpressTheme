<?php
/**
 * Shortcodes para Convocatorias - VERSIÓN CORREGIDA
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------
// SHORTCODE PARA ADMINISTRADORES: [admin_convocatorias]
// -----------------------------------------------------------------
add_shortcode('admin_convocatorias', 'sged_admin_convocatorias_shortcode');
function sged_admin_convocatorias_shortcode() {
    
    // Solo administradores
    if (!current_user_can('manage_options')) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">Acceso denegado</div>';
    }
    
    // Obtener TODAS las convocatorias
    $args = array(
        'post_type' => 'convocatoria',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $convocatorias = get_posts($args);
    
    ob_start(); // Empezar a capturar el output
    ?>
    
    <div class="admin-convocatorias">
        <h2>📋 Gestión de Convocatorias</h2>
        
        <p>
            <a href="<?php echo admin_url('post-new.php?post_type=convocatoria'); ?>" class="button button-primary">
                + Nueva Convocatoria
            </a>
        </p>
        
        <?php if (empty($convocatorias)): ?>
            <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 8px;">
                <h3>No hay convocatorias creadas</h3>
                <p>Comienza creando tu primera convocatoria.</p>
                <a href="<?php echo admin_url('post-new.php?post_type=convocatoria'); ?>" class="button button-primary">
                    + Crear Primera Convocatoria
                </a>
            </div>
        <?php else: ?>
            
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Fechas</th>
                        <th>Estado</th>
                        <th>Documentos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($convocatorias as $conv): 
                        $fecha_ini_carga = get_post_meta($conv->ID, 'convocatoria_fecha_ini_carga', true);
                        $fecha_fin_carga = get_post_meta($conv->ID, 'convocatoria_fecha_fin_carga', true);
                        
                        // Contar documentos asociados
                        $docs_asociados = get_posts(array(
                            'post_type' => 'documento_convocatoria',
                            'meta_key' => 'documento_convocatoria_id',
                            'meta_value' => $conv->ID,
                            'fields' => 'ids',
                            'posts_per_page' => -1
                        ));
                        
                        $total_docs = count($docs_asociados);
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($conv->post_title); ?></strong></td>
                            <td>
                                <?php if ($fecha_ini_carga && $fecha_fin_carga): ?>
                                    <strong>Carga:</strong> <?php echo date('d/m/Y', strtotime($fecha_ini_carga)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin_carga)); ?>
                                <?php else: ?>
                                    <em>Fechas no configuradas</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                // Estado simple
                                $ahora = current_time('mysql');
                                if ($ahora < $fecha_ini_carga) {
                                    echo '<span style="color: #856404;">⏳ Próximamente</span>';
                                } elseif ($ahora >= $fecha_ini_carga && $ahora <= $fecha_fin_carga) {
                                    echo '<span style="color: #46b450; font-weight: bold;">📤 Activa</span>';
                                } else {
                                    echo '<span style="color: #999;">✅ Finalizada</span>';
                                }
                                ?>
                            </td>
                            <td style="text-align: center;"><?php echo $total_docs; ?></td>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $conv->ID . '&action=edit'); ?>" class="button button-small">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
        <?php endif; ?>
    </div>
    
    <?php
    return ob_get_clean(); // Devolver el output capturado
}

// -----------------------------------------------------------------
// SHORTCODE PARA PROFESORES: [convocatorias_activas] (versión simplificada)
// -----------------------------------------------------------------
add_shortcode('convocatorias_activas', 'sged_convocatorias_activas_shortcode');
function sged_convocatorias_activas_shortcode() {
    
    $current_user = wp_get_current_user();
    
    // Solo profesores (o admins)
    if (!current_user_can('edit_posts') && !current_user_can('manage_options')) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">Solo profesores pueden ver convocatorias.</div>';
    }
    
    $args = array(
        'post_type' => 'convocatoria',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'convocatoria_fecha_ini_carga',
                'value' => current_time('mysql'),
                'compare' => '<=',
                'type' => 'DATETIME'
            ),
            array(
                'key' => 'convocatoria_fecha_fin_carga',
                'value' => current_time('mysql'),
                'compare' => '>=',
                'type' => 'DATETIME'
            )
        )
    );
    
    $convocatorias = get_posts($args);
    
    ob_start();
    ?>
    
    <h2>📢 Convocatorias Activas</h2>
    
    <?php if (empty($convocatorias)): ?>
        <div style="background: #fff3cd; color: #856404; padding: 20px; text-align: center;">
            <h3>No hay convocatorias activas</h3>
            <p>Actualmente no hay convocatorias en período de carga.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($convocatorias as $conv): 
                $fecha_fin = get_post_meta($conv->ID, 'convocatoria_fecha_fin_carga', true);
            ?>
                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px;">
                    <h3><?php echo esc_html($conv->post_title); ?></h3>
                    <p><?php echo wp_trim_words($conv->post_content, 20); ?></p>
                    <p><strong>Cierre:</strong> <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></p>
                    <a href="<?php echo home_url('/crear-documento-convocatoria/?conv=' . $conv->ID); ?>" class="button">
    📄 Cargar Documentos a esta Convocatoria
</a>
                </div>
            <?php endforeach; ?>
            </div>
    <?php endif; ?>
    
    <?php
    return ob_get_clean();
}

// ============ NUEVO SHORTCODE - PÉGALO AQUÍ ============
add_shortcode('formulario_carga_convocatoria', 'sged_formulario_carga_convocatoria');

function sged_formulario_carga_convocatoria() {
    
    // Verificar que sea profesor
    if (!current_user_can('edit_posts') && !current_user_can('manage_options')) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">Acceso denegado. Solo profesores.</div>';
    }
    
    // Obtener ID de convocatoria de la URL
    $convocatoria_id = isset($_GET['conv']) ? intval($_GET['conv']) : 0;
    
    if (!$convocatoria_id) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">No se especificó una convocatoria válida.</div>';
    }
    
    // Verificar que la convocatoria existe y está activa
    $convocatoria = get_post($convocatoria_id);
    if (!$convocatoria || $convocatoria->post_type != 'convocatoria') {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">La convocatoria no existe.</div>';
    }
    
    // Verificar que la convocatoria está en período de carga
    $fecha_ini = get_post_meta($convocatoria_id, 'convocatoria_fecha_ini_carga', true);
    $fecha_fin = get_post_meta($convocatoria_id, 'convocatoria_fecha_fin_carga', true);
    $ahora = current_time('mysql');
    
    if ($ahora < $fecha_ini) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">Esta convocatoria aún no inicia.</div>';
    }
    
    if ($ahora > $fecha_fin) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">Esta convocatoria ya terminó.</div>';
    }
    
    // Mostrar información de la convocatoria
    $html = '<div style="background: #f0f8ff; padding: 15px; margin-bottom: 20px; border-left: 4px solid #0073aa;">';
    $html .= '<h3>📌 ' . esc_html($convocatoria->post_title) . '</h3>';
    $html .= '<p>' . nl2br(esc_html($convocatoria->post_content)) . '</p>';
    $html .= '<p><strong>Fecha límite:</strong> ' . date('d/m/Y H:i', strtotime($fecha_fin)) . '</p>';
    $html .= '</div>';
    
    ob_start();
    ?>
    <form method="POST" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('carga_convocatoria_nonce', 'convocatoria_nonce'); ?>
        <input type="hidden" name="action" value="guardar_documento_convocatoria">
        <input type="hidden" name="convocatoria_id" value="<?php echo $convocatoria_id; ?>">
        
        <p>
            <label>Tipo de documento *</label><br>
            <select name="user_document_type" required style="width:100%; padding:8px;">
                <option value="">Seleccione</option>
                <option value="pregrado">Pre-Grado</option>
                <option value="posgrado">Post-Grado</option>
                <option value="publicacion">Publicación</option>
                <option value="actualizacion">Actualización</option>
                <option value="creacion">Creación Artística</option>
                <option value="asistencia">Asistencia</option>
            </select>
        </p>
        
        <p>
            <label>Nombre del documento *</label><br>
            <input type="text" name="user_document_name" required style="width:100%; padding:8px;">
        </p>
        
        <p>
            <label>Año *</label><br>
            <input type="number" name="user_document_year" min="1900" max="<?php echo date('Y'); ?>" required style="width:100%; padding:8px;">
        </p>
        
        <p>
            <label>Archivo (PDF) *</label><br>
            <input type="file" name="user_document_location" accept=".pdf,application/pdf" required style="width:100%; padding:8px;">
        </p>
        
        <p>
            <button type="submit" style="background:#0073aa; color:white; padding:10px 20px; border:none; cursor:pointer;">Subir Documento</button>
        </p>
    </form>
    <?php
    $html .= ob_get_clean();
    return $html;
}
// ============ FIN DEL NUEVO SHORTCODE ============

// -----------------------------------------------------------------
// PROCESAR EL FORMULARIO DE CARGA
// -----------------------------------------------------------------
add_action('admin_post_guardar_documento_convocatoria', 'procesar_carga_documento_convocatoria');
add_action('admin_post_nopriv_guardar_documento_convocatoria', 'procesar_carga_documento_convocatoria');
function procesar_carga_documento_convocatoria() {
    
    // Verificar nonce
    if (!isset($_POST['convocatoria_nonce']) || !wp_verify_nonce($_POST['convocatoria_nonce'], 'carga_convocatoria_nonce')) {
        wp_redirect(home_url('/?error=seguridad'));
        exit;
    }
    
    // Verificar usuario
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_redirect(home_url('/?error=permisos'));
        exit;
    }
    
    $current_user = wp_get_current_user();
    $convocatoria_id = intval($_POST['convocatoria_id']);
    
    // 1. Crear el documento base (igual que en user_documents.php)
    $user_document_name = $current_user->user_login . " | " . $_POST["user_document_name"];
    $post_id = wp_insert_post(array(
        'post_status' => 'publish',
        'post_type' => 'user_document',
        'post_title' => $user_document_name,
        'post_author' => $current_user->ID
    ));
    
    // 2. Guardar el archivo
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    $attachment_id = media_handle_upload('user_document_location', 0);
    
    if (is_wp_error($attachment_id)) {
        wp_delete_post($post_id, true);
        wp_redirect(home_url('/cargar-documento-convocatoria/?conv=' . $convocatoria_id . '&error=archivo'));
        exit;
    }
    
    // 3. Guardar metadatos del documento
    update_post_meta($post_id, 'user_document_type', sanitize_text_field($_POST['user_document_type']));
    update_post_meta($post_id, 'user_document_name', sanitize_text_field($_POST['user_document_name']));
    update_post_meta($post_id, 'user_document_year', intval($_POST['user_document_year']));
    update_post_meta($post_id, 'user_document_location', $attachment_id);
    update_post_meta($post_id, 'user_document_user_id', $current_user->ID);
    
    // 4. CREAR EL DOCUMENTO DE CONVOCATORIA (NUEVO)
    $doc_conv_id = wp_insert_post(array(
        'post_type' => 'documento_convocatoria',
        'post_title' => $user_document_name . ' - Convocatoria ' . $convocatoria_id,
        'post_status' => 'publish',
        'post_author' => $current_user->ID
    ));
    
    update_post_meta($doc_conv_id, 'documento_convocatoria_id', $convocatoria_id);
    update_post_meta($doc_conv_id, 'documento_user_document_id', $post_id);
    update_post_meta($doc_conv_id, 'documento_estado_revision', 'pendiente');
    update_post_meta($doc_conv_id, 'documento_user_id', $current_user->ID);
    update_post_meta($doc_conv_id, 'documento_fecha_subida', current_time('mysql'));
    
    // 5. Redireccionar
    wp_redirect(home_url('/convocatorias-para-profesores/?mensaje=exito'));
    exit;
}