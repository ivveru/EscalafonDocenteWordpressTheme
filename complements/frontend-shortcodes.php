<?php
/**
 * Shortcodes para Convocatorias - VERSIÓN CORREGIDA
 */

// DEPURACIÓN: Verificar post types registrados
add_action('init', function() {
    if (post_type_exists('documento_convocatoria')) {
        error_log('✅ documento_convocatoria SÍ existe');
    } else {
        error_log('❌ documento_convocatoria NO existe');
    }
}, 1);

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// =============================================
// REGISTRAR POST TYPE CONV_DOC (nombre corto)
// =============================================
add_action('init', 'registrar_conv_doc_temporal', 0);
function registrar_conv_doc_temporal() {
    register_post_type('conv_doc',
        array(
            'labels' => array(
                'name' => 'Documentos de Convocatoria',
                'singular_name' => 'Documento de Convocatoria'
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => array('title', 'author'),
        )
    );
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
                            'post_type' => 'conv_doc',
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
// SHORTCODE PARA PROFESORES: [convocatorias_activas] - CON FILTRO DE ASIGNACIÓN
// -----------------------------------------------------------------
add_shortcode('convocatorias_activas', 'sged_convocatorias_activas_shortcode');
function sged_convocatorias_activas_shortcode() {
    
    $current_user = wp_get_current_user();
    
    // Solo profesores (o admins)
    if (!current_user_can('edit_posts') && !current_user_can('manage_options')) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">Solo profesores pueden ver convocatorias.</div>';
    }
    
    // Si es administrador, ve todas las convocatorias activas (sin filtro)
    if (current_user_can('manage_options')) {
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
    } 
    // Para profesores: filtrar por asignación de revisor
    else {
        global $wpdb;
        $tabla_asignaciones = $wpdb->prefix . 'asignaciones_revision';
        $profesor_id = $current_user->ID;
        
        // Obtener IDs de convocatorias donde el profesor tiene revisor asignado
        $convocatorias_asignadas = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT convocatoria_id FROM $tabla_asignaciones WHERE profesor_id = %d",
            $profesor_id
        ));
        
        // Si no tiene ninguna convocatoria asignada, mostrar mensaje
        if (empty($convocatorias_asignadas)) {
            return '<div style="background: #fff3cd; color: #856404; padding: 30px; text-align: center; border-radius: 8px; margin: 20px 0;">
                <h3>📭 No tienes convocatorias disponibles</h3>
                <p>Actualmente no tienes ningún revisor asignado para las convocatorias activas.</p>
                <p style="font-size: 14px; margin-top: 20px;">Si crees que esto es un error, contacta al administrador del sistema.</p>
            </div>';
        }
        
        // Obtener convocatorias activas que están en la lista de asignadas
        $args = array(
            'post_type' => 'convocatoria',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__in' => $convocatorias_asignadas,
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
        
        // Si no hay convocatorias activas entre las asignadas
        if (empty($convocatorias)) {
            return '<div style="background: #fff3cd; color: #856404; padding: 30px; text-align: center; border-radius: 8px; margin: 20px 0;">
                <h3>📭 No hay convocatorias activas</h3>
                <p>Tienes revisores asignados, pero ninguna convocatoria está en período de carga actualmente.</p>
                <p style="font-size: 14px; margin-top: 20px;">Vuelve a intentar más tarde.</p>
            </div>';
        }
    }
    
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
                    <a href="<?php echo home_url('/carga-nueva/?conv=' . $conv->ID); ?>" class="button">
                        📄 Cargar Documentos a esta Convocatoria
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php
    return ob_get_clean();
}









// ============ SHORTCODE CON VISTA PREVIA (VERSIÓN SIMPLIFICADA) ============
add_shortcode('formulario_carga_convocatoria', 'sged_formulario_carga_convocatoria');

function sged_formulario_carga_convocatoria() {
    
    // Verificar que sea profesor
    if (!current_user_can('edit_posts') && !current_user_can('manage_options')) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">Acceso denegado. Solo profesores.</div>';
    }
    
    // Obtener ID de convocatoria
    $convocatoria_id = isset($_GET['conv']) ? intval($_GET['conv']) : 0;
    
    if (!$convocatoria_id) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">No se especificó una convocatoria válida.</div>';
    }
    
    // Verificar convocatoria
    $convocatoria = get_post($convocatoria_id);
    if (!$convocatoria || $convocatoria->post_type != 'convocatoria') {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">La convocatoria no existe.</div>';
    }
    
    // Verificar fechas
    $fecha_ini = get_post_meta($convocatoria_id, 'convocatoria_fecha_ini_carga', true);
    $fecha_fin = get_post_meta($convocatoria_id, 'convocatoria_fecha_fin_carga', true);
    
    // VERIFICAR SI YA TIENE DOCUMENTO
    $user_id = get_current_user_id();
    $documento_existente = get_posts(array(
        'post_type' => 'conv_doc',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'documento_convocatoria_id',
                'value' => $convocatoria_id,
                'compare' => '='
            ),
            array(
                'key' => 'documento_user_id',
                'value' => $user_id,
                'compare' => '='
            )
        ),
        'posts_per_page' => 1
    ));

    if (!empty($documento_existente)) {
        wp_redirect(home_url('/mis-archivos-cargados/?conv=' . $convocatoria_id));
        exit;
    }

    $ahora = current_time('mysql');
    
    if ($ahora < $fecha_ini) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">Esta convocatoria aún no inicia.</div>';
    }
    
    if ($ahora > $fecha_fin) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">Esta convocatoria ya terminó.</div>';
    }
    
    // Mostrar errores si existen
    if (isset($_GET['error'])) {
        $error_mensaje = '';
        switch ($_GET['error']) {
            case 'formato':
                $error_mensaje = '❌ Solo se permiten archivos PDF.';
                break;
            case 'tamano':
                $error_mensaje = '❌ El archivo excede el tamaño máximo (10MB).';
                break;
            case 'archivo':
                $error_mensaje = '❌ Error al subir el archivo.';
                break;
        }
        if ($error_mensaje) {
            echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px;">' . $error_mensaje . '</div>';
        }
    }
    
    // Información de la convocatoria
    $html = '<div style="background: #f0f8ff; padding: 15px; margin-bottom: 20px; border-left: 4px solid #dc0c1d;">';
    $html .= '<h3>📌 ' . esc_html($convocatoria->post_title) . '</h3>';
    $html .= '<p>' . nl2br(esc_html($convocatoria->post_content)) . '</p>';
    $html .= '<p><strong>Fecha límite:</strong> ' . date('d/m/Y H:i', strtotime($fecha_fin)) . '</p>';
    $html .= '</div>';
    
    // Generar el formulario con nuestra nueva función
    $html .= get_form_convocatoria_con_vista_previa($convocatoria_id);
    
    return $html;
}




// -----------------------------------------------------------------
// SHORTCODE: [mis_archivos_cargados] - VERSIÓN CORREGIDA
// Muestra los documentos con el nombre de la convocatoria
// -----------------------------------------------------------------
add_shortcode('mis_archivos_cargados', 'sged_mis_archivos_cargados');
function sged_mis_archivos_cargados() {
    
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">Acceso denegado.</div>';
    }
    
    $user_id = get_current_user_id();
    $convocatoria_id_filtro = isset($_GET['conv']) ? intval($_GET['conv']) : 0;
    
    // Obtener documentos del profesor (post_type 'conv_doc')
    $args = array(
        'post_type' => 'conv_doc',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'documento_user_id',
                'value' => $user_id,
                'compare' => '='
            )
        ),
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    // Si hay convocatoria específica, filtrar
    if ($convocatoria_id_filtro) {
        $args['meta_query'][] = array(
            'key' => 'documento_convocatoria_id',
            'value' => $convocatoria_id_filtro,
            'compare' => '='
        );
    }
    
    $documentos = get_posts($args);
    
    if (empty($documentos)) {
        return '<div style="background: #fff3cd; color: #856404; padding: 20px; text-align: center;">
            <h3>📭 No has cargado documentos en ninguna convocatoria</h3>
            <p><a href="' . home_url('/convocatorias-para-profesores') . '">Ver convocatorias activas</a></p>
        </div>';
    }
    
    ob_start();
    ?>
    <style>
        .mis-archivos-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .mis-archivos-table th {
            background: #dc0c1d;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 500;
        }
        .mis-archivos-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        .mis-archivos-table tr:hover {
            background: #f5f5f5;
        }
        .estado-pendiente {
            background: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }
        .estado-aprobado {
            background: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }
        .estado-rechazado {
            background: #f8d7da;
            color: #721c24;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }
        .btn-preview {
            background: #dc0c1d;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            font-size: 14px;
        }
        .btn-preview:hover {
            background: #005a87;
            color: white;
        }
        .observaciones {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
            max-width: 250px;
        }
        .convocatoria-badge {
            background: #e7f5ff;
            color: #dc0c1d;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
            display: inline-block;
            font-weight: 500;
        }
    </style>
    
    <h2>📁 Mis archivos cargados en convocatorias</h2>
    
    <table class="mis-archivos-table">
        <thead>
            <tr>
                <th>Convocatoria</th>
                <th>Tipo de Documento</th>
                <th>Nombre</th>
                <th>Año</th>
                <th>Fecha de carga</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($documentos as $doc): 
            
            // ===== OBTENER ID DE CONVOCATORIA DESDE LOS METADATOS =====
            $conv_id = get_post_meta($doc->ID, 'documento_convocatoria_id', true);
            
            // ===== OBTENER NOMBRE REAL DE LA CONVOCATORIA =====
            $nombre_convocatoria = 'No disponible';
            if ($conv_id) {
                $convocatoria = get_post($conv_id);
                if ($convocatoria && $convocatoria->post_type == 'convocatoria') {
                    $nombre_convocatoria = $convocatoria->post_title;
                }
            }
            
            // ===== OBTENER ID DEL DOCUMENTO ORIGINAL =====
            $user_doc_id = get_post_meta($doc->ID, 'documento_user_document_id', true);
            
            // ===== OBTENER METADATOS DEL DOCUMENTO ORIGINAL =====
            $tipo_documento = get_post_meta($user_doc_id, 'user_document_type', true);
            $nombre_documento = get_post_meta($user_doc_id, 'user_document_name', true);
            $anio_documento = get_post_meta($user_doc_id, 'user_document_year', true);
            $archivo_id = get_post_meta($user_doc_id, 'user_document_location', true);
            $url_archivo = wp_get_attachment_url($archivo_id);
            
            // ===== ESTADO DE REVISIÓN =====
            $estado = get_post_meta($doc->ID, 'documento_estado_revision', true);
            $observaciones = get_post_meta($doc->ID, 'documento_observaciones', true);
            
            // ===== NUEVO: OBTENER REVISOR =====
            $revisor_id = get_post_meta($doc->ID, 'documento_revisor_id', true);
            $revisor_nombre = '';
            if ($revisor_id) {
                $revisor = get_userdata($revisor_id);
                $revisor_nombre = $revisor ? $revisor->display_name : '';
            }
            
            // ===== TRADUCIR TIPO DE DOCUMENTO =====
            $tipos = [
                'pregrado' => 'Pre-Grado',
                'posgrado' => 'Post-Grado',
                'publicacion' => 'Publicación',
                'actualizacion' => 'Actualización',
                'creacion' => 'Creación Artística',
                'asistencia' => 'Asistencia'
            ];
            $tipo_mostrar = isset($tipos[$tipo_documento]) ? $tipos[$tipo_documento] : $tipo_documento;
            
            // ===== ESTADO CON COLORES =====
            $estado_class = 'pendiente';
            $estado_texto = 'Pendiente';
            if ($estado == 'aprobado') {
                $estado_class = 'aprobado';
                $estado_texto = '✅ Aprobado';
            } elseif ($estado == 'rechazado') {
                $estado_class = 'rechazado';
                $estado_texto = '❌ Rechazado';
            }
        ?>
            <!-- FILA PRINCIPAL DEL DOCUMENTO -->
            <tr>
                <td>
                    <span class="convocatoria-badge">
                        <?php echo esc_html($nombre_convocatoria); ?>
                    </span>
                </td>
                <td><?php echo esc_html($tipo_mostrar); ?></td>
                <td><?php echo esc_html($nombre_documento); ?></td>
                <td><?php echo esc_html($anio_documento); ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($doc->post_date)); ?></td>
                <td><span class="estado-<?php echo $estado_class; ?>"><?php echo $estado_texto; ?></span></td>
                <td>
                    <?php if ($url_archivo): ?>
                        <a href="<?php echo $url_archivo; ?>" target="_blank" class="btn-preview">🔍 Ver PDF</a>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- ===== FILA DE OBSERVACIONES (si existen) - COLOCAR AQUÍ ===== -->
            <?php if (!empty($observaciones) || $revisor_id): ?>
            <tr class="observaciones-fila">
                <td colspan="7" style="padding: 10px 20px; background: #f9f9f9; border-top: 1px dashed #ccc;">
                    <div style="display: flex; align-items: center; gap: 15px; font-size: 13px;">
                        <?php if ($revisor_id): ?>
                        <span style="background: #e7f5ff; padding: 4px 12px; border-radius: 20px;">
                            👤 <strong>Revisor:</strong> <?php echo esc_html($revisor_nombre); ?>
                        </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($observaciones)): ?>
                        <span style="background: #fff3cd; padding: 4px 12px; border-radius: 20px; flex: 1;">
                            📝 <strong>Observación:</strong> <?php echo esc_html($observaciones); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <!-- ===== FIN FILA DE OBSERVACIONES ===== -->

        <?php endforeach; ?>




        </tbody>
    </table>
    
    <p style="margin-top: 20px;">
        <a href="<?php echo home_url('/convocatorias-para-profesores'); ?>" class="button">← Volver a convocatorias activas</a>
    </p>
    
    <?php
    return ob_get_clean();
}





// =============================================
// FUNCIONES AUXILIARES PARA ESTADOS (AGREGAR AL FINAL)
// =============================================

function convocatoria_calcular_estado($post_id) {
    $fecha_ini_carga = get_post_meta($post_id, 'convocatoria_fecha_ini_carga', true);
    $fecha_fin_carga = get_post_meta($post_id, 'convocatoria_fecha_fin_carga', true);
    $fecha_ini_revi = get_post_meta($post_id, 'convocatoria_fecha_ini_revi', true);
    $fecha_fin_revi = get_post_meta($post_id, 'convocatoria_fecha_fin_revi', true);
    
    $ahora = current_time('mysql');
    
    if (empty($fecha_ini_carga)) {
        return 'sin_fechas';
    }
    
    if ($ahora < $fecha_ini_carga) {
        return 'proximamente';
    } elseif ($ahora >= $fecha_ini_carga && $ahora <= $fecha_fin_carga) {
        return 'carga_abierta';
    } elseif ($ahora > $fecha_fin_carga && $ahora < $fecha_ini_revi) {
        return 'entre_periodos';
    } elseif ($ahora >= $fecha_ini_revi && $ahora <= $fecha_fin_revi) {
        return 'revision_abierta';
    } elseif ($ahora > $fecha_fin_revi) {
        return 'finalizada';
    }
    
    return 'indeterminado';
}

function convocatoria_texto_estado($estado) {
    $textos = [
        'sin_fechas' => '<span style="color: #999;">⏳ Sin fechas</span>',
        'proximamente' => '<span style="color: #856404;">📅 Próximamente</span>',
        'carga_abierta' => '<span style="color: #46b450; font-weight: bold;">📤 CARGA ABIERTA</span>',
        'entre_periodos' => '<span style="color: #999;">⏸️ Entre períodos</span>',
        'revision_abierta' => '<span style="color: #dc0c1d; font-weight: bold;">🔍 REVISIÓN ABIERTA</span>',
        'finalizada' => '<span style="color: #999;">✅ Finalizada</span>',
        'indeterminado' => '<span style="color: #999;">❓ Indeterminado</span>'
    ];
    
    return isset($textos[$estado]) ? $textos[$estado] : $textos['indeterminado'];
}




// -----------------------------------------------------------------
// PROCESAR DOCUMENTO DE CONVOCATORIA (VERSIÓN CORREGIDA)
// -----------------------------------------------------------------
add_action('init', 'guardar_documento_convocatoria_endpoint');
function guardar_documento_convocatoria_endpoint() {
    if (isset($_POST['convocatoria_id']) && isset($_POST['user_document_name'])) {
        procesar_documento_convocatoria();
    }
}

function procesar_documento_convocatoria() {


   // ===== AGREGAR DEBUG AQUÍ =====
    echo '<h3>DEBUG COMPLETO</h3>';
    echo '<h4>POST:</h4>';
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';

    echo '<h4>FILES:</h4>';
    echo '<pre>';
    print_r($_FILES);
    echo '</pre>';

    echo '<h4>SERVER:</h4>';
    echo '<pre>';
    echo 'REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD'] . '<br>';
    echo 'CONTENT_TYPE: ' . ($_SERVER['CONTENT_TYPE'] ?? 'no definido') . '<br>';
    echo '</pre>';
    // ===== FIN DEBUG =====
    
    // Verificar nonce
    if (!isset($_POST['convocatoria_nonce']) || !wp_verify_nonce($_POST['convocatoria_nonce'], 'carga_convocatoria_nonce')) {
        die('Error: Nonce inválido');
    }
    
    // Verificar usuario
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        die('Error: No tienes permisos');
    }
    
    $current_user = wp_get_current_user();
    $convocatoria_id = intval($_POST['convocatoria_id']);
    
    echo "<h2>DEBUG - Inicio del proceso</h2>";
    echo "Usuario: " . $current_user->ID . "<br>";
    echo "Convocatoria: " . $convocatoria_id . "<br>";
    
    // 1. Crear el documento base
    $user_document_name = $current_user->user_login . " | " . $_POST["user_document_name"];
    $post_id = wp_insert_post(array(
        'post_status' => 'publish',
        'post_type' => 'user_document',
        'post_title' => $user_document_name,
        'post_author' => $current_user->ID
    ));
    
    echo "Post ID creado: " . $post_id . "<br>";
    
    // 2. Verificar el archivo
    echo "<h3>Información del archivo:</h3>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    // 3. Intentar guardar el archivo
    $document_result = save_user_document_file("user_document_location", $current_user->user_login, $_POST["user_document_type"]);
    
    echo "<h3>Resultado de save_user_document_file:</h3>";
    echo "<pre>";
    print_r($document_result);
    echo "</pre>";
    
    if (is_array($document_result) && isset($document_result["error"])) {
        echo "<p style='color:red; font-weight:bold;'>ERROR: " . $document_result["error"] . "</p>";
        wp_delete_post($post_id, true);
        exit;
    }
    
    $attachment_id = $document_result;
    echo "Attachment ID: " . $attachment_id . "<br>";
    
    // 4. Guardar metadatos
    update_post_meta($post_id, 'user_document_type', sanitize_text_field($_POST['user_document_type']));
    update_post_meta($post_id, 'user_document_name', sanitize_text_field($_POST['user_document_name']));
    update_post_meta($post_id, 'user_document_year', intval($_POST['user_document_year']));
    update_post_meta($post_id, 'user_document_location', $attachment_id);
    update_post_meta($post_id, 'user_document_user_id', $current_user->ID);
    
    // 5. Crear documento de convocatoria
    $doc_conv_id = wp_insert_post(array(
        'post_type' => 'conv_doc',
        'post_title' => $user_document_name . ' - Convocatoria ' . $convocatoria_id,
        'post_status' => 'publish',
        'post_author' => $current_user->ID
    ));
    
    // ===== DEBUG =====
echo "Documento convocatoria ID: " . $doc_conv_id . "<br>";
if (!$doc_conv_id) {
    echo "<p style='color:red;'>ERROR: No se pudo crear el documento de convocatoria</p>";
    echo "<pre>";
    global $wpdb;
    echo "Último error SQL: " . $wpdb->last_error;
    echo "</pre>";
} else {
    echo "<p style='color:green;'>✅ Documento de convocatoria creado con ID: " . $doc_conv_id . "</p>";
}
// ===== FIN DEBUG =====
    
    update_post_meta($doc_conv_id, 'documento_convocatoria_id', $convocatoria_id);
    update_post_meta($doc_conv_id, 'documento_user_document_id', $post_id);
    update_post_meta($doc_conv_id, 'documento_estado_revision', 'pendiente');
    update_post_meta($doc_conv_id, 'documento_user_id', $current_user->ID);
    update_post_meta($doc_conv_id, 'documento_fecha_subida', current_time('mysql'));
    
    echo "<h2 style='color:green;'>✅ PROCESO COMPLETADO CON ÉXITO</h2>";
    echo "<p><a href='" . home_url('/mis-archivos-cargados/?conv=' . $convocatoria_id) . "'>Ver mis archivos</a></p>";
    
    exit;
}





// =============================================
// FUNCIÓN PARA GENERAR FORMULARIO CON VISTA PREVIA (basada en la original)
// =============================================
function get_form_convocatoria_con_vista_previa( $convocatoria_id ) {
    
    ob_start();
    ?>
    
    <style>
        .pdf-preview-container {
            display: none;
            margin: 20px 0;
            padding: 20px;
            border: 2px solid #dc0c1d;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .pdf-preview {
            width: 100%;
            min-height: 500px;
            border: 1px solid #ddd;
            margin: 10px 0;
        }
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .preview-title {
            font-size: 18px;
            font-weight: bold;
            color: #dc0c1d;
        }
        .close-preview {
            background: #dc3232;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .close-preview:hover {
            background: #a00;
        }
        .confirm-save {
            background: #46b450;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin-top: 15px;
            width: 100%;
        }
        .confirm-save:hover {
            background: #3a9c44;
        }
        .file-input-wrapper {
            margin: 20px 0;
        }
        .file-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .file-input {
            width: 100%;
            padding: 10px;
            border: 2px dashed #dc0c1d;
            border-radius: 4px;
            background: #f9f9f9;
            cursor: pointer;
        }
        .form-field {
            margin: 15px 0;
        }
        .form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-field input, .form-field select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>

    <!-- INCLUIR PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>

    <div class="formulario-convocatoria">
        <!-- MISMO FORMULARIO QUE EL ORIGINAL PERO CON ACTION DIFERENTE -->
        <form id="form-carga" method="POST" enctype="multipart/form-data" action="<?php echo home_url('/guardar-documento-convocatoria'); ?>">
            
            <?php wp_nonce_field('carga_convocatoria_nonce', 'convocatoria_nonce'); ?>
            <input type="hidden" name="convocatoria_id" value="<?php echo $convocatoria_id; ?>">
            
            <!-- CAMPOS COPIADOS DE get_form_user_document_form() -->
            <div class="input left">
                <label for="user_document_type">Tipo de publicación</label>
                <select name="user_document_type" id="user_document_type" class="form-control" required>
                    <option value="pregrado">Estudio Pre-Grado</option>
                    <option value="posgrado">Estudio Post-Grado</option>
                    <option value="publicacion">Publicación Académica</option>
                    <option value="actualizacion">Curso de Actualización</option>
                    <option value="creacion">Creación Artística</option>
                    <option value="asistencia">Asistencia a Evento</option>
                </select>
            </div>
            
            <div class="input left">
                <label for="user_document_name">Nombre del documento</label>
                <input type="text" name="user_document_name" id="user_document_name" class="form-control" required>
            </div>
            
            <div class="input left">
                <label for="user_document_year">Año</label>
                <input type="number" name="user_document_year" id="user_document_year" class="form-control" min="1900" max="<?php echo date('Y'); ?>" required>
            </div>
            
            <div class="input left file-field-group" id="user_document_location_container">
                <label for="user_document_location">Ubicación del archivo (PDF)</label>
                <input type="file" name="user_document_location" id="pdf-file" class="form-control" accept=".pdf,application/pdf" required>
                <div class="file-info" id="file-info">Solo PDF, máximo 10MB. Esta será su única oportunidad de cargar este archivo.</div>
            </div>
            
            <!-- VISTA PREVIA -->
            <div id="pdfPreviewContainer" class="pdf-preview-container">
                <div class="preview-header">
                    <span class="preview-title">Vista previa del PDF</span>
                    <button type="button" class="close-preview" id="closePreviewBtn">✕ Cerrar vista previa</button>
                </div>
                <div id="pdfInfo" style="margin: 10px 0;"></div>
                <canvas id="pdfPreview" class="pdf-preview"></canvas>
                <div style="text-align: center; margin: 10px 0;">
                    <button type="button" id="prevPage" class="button">◀ Anterior</button>
                    <span id="pageNum">1</span> / <span id="pageCount">0</span>
                    <button type="button" id="nextPage" class="button">Siguiente ▶</button>
                </div>
                <button type="submit" class="confirm-save" id="confirmSaveBtn">✅ CONFIRMAR Y SUBIR DOCUMENTO</button>
            </div>
            
        </form>
    </div>

    <script>
        // Cargar jQuery si no existe
        if (typeof jQuery === 'undefined') {
            var script = document.createElement('script');
            script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
            script.onload = function() {
                jQuery(document).ready(function($) {
                    iniciarVistaPrevia($);
                });
            };
            document.head.appendChild(script);
        } else {
            jQuery(document).ready(function($) {
                iniciarVistaPrevia($);
            });
        }

        function iniciarVistaPrevia($) {
            let pdfDoc = null;
            let pageNum = 1;
            let canvas = document.getElementById('pdfPreview');
            if (!canvas) return;
            let ctx = canvas.getContext('2d');
            
            const fileInput = $('#pdf-file');
            const fileInfo = $('#file-info');
            const previewContainer = $('#pdfPreviewContainer');
            const confirmBtn = $('#confirmSaveBtn');
            const closeBtn = $('#closePreviewBtn');
            
            fileInput.on('change', function() {
                const file = this.files[0];
                if (!file) return;
                
                // ALERTA DE CONFIRMACIÓN
                const confirmar = confirm('⚠️ IMPORTANTE: Esta será la ÚNICA oportunidad de cargar este archivo.\n\n¿Está seguro que desea continuar?');
                
                if (!confirmar) {
                    fileInput.val('');
                    fileInfo.html('Solo PDF, máximo 10MB. Esta será su única oportunidad de cargar este archivo.').css('color', '#666');
                    return;
                }
                
                // Validar tipo
                if (file.type !== 'application/pdf') {
                    alert('❌ Solo se permiten archivos PDF');
                    fileInput.val('');
                    return;
                }
                
                // Validar tamaño
                const maxSize = 10 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('❌ El archivo excede los 10MB');
                    fileInput.val('');
                    return;
                }
                
                fileInfo.html('✅ Archivo válido: ' + file.name + ' (' + (file.size/1024/1024).toFixed(2) + 'MB) - Cargando vista previa...').css('color', '#46b450');
                
                // Cargar PDF
                const fileReader = new FileReader();
                fileReader.onload = function() {
                    const typedarray = new Uint8Array(this.result);
                    
                    pdfjsLib.getDocument(typedarray).promise.then(function(pdf) {
                        pdfDoc = pdf;
                        $('#pageCount').text(pdf.numPages);
                        
                        $('#pdfInfo').html(`
                            <strong>Páginas:</strong> ${pdf.numPages}<br>
                            <strong>Archivo:</strong> ${file.name}<br>
                            <strong>Tamaño:</strong> ${(file.size/1024/1024).toFixed(2)} MB
                        `);
                        
                        renderPage(1);
                        previewContainer.show();
                        ///fileInput.prop('disabled', true);
                        fileInput.hide(); // ← NUEVO: ocultar pero no deshabilitar
                        fileInfo.hide();  // Opcional: ocultar también la info
                        
                    }).catch(function(error) {
                        alert('❌ Error al cargar el PDF. Asegúrese de que sea un archivo válido.');
                        fileInput.val('');
                        fileInfo.html('Solo PDF, máximo 10MB. Esta será su única oportunidad de cargar este archivo.').css('color', '#666');
                    });
                };
                fileReader.readAsArrayBuffer(file);
            });
            
            function renderPage(num) {
                pdfDoc.getPage(num).then(function(page) {
                    const viewport = page.getViewport({ scale: 1.5 });
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    page.render({
                        canvasContext: ctx,
                        viewport: viewport
                    });
                    $('#pageNum').text(num);
                });
            }
            
            $('#prevPage').on('click', function() {
                if (pageNum <= 1) return;
                pageNum--;
                renderPage(pageNum);
            });
            
            $('#nextPage').on('click', function() {
                if (pageNum >= pdfDoc.numPages) return;
                pageNum++;
                renderPage(pageNum);
            });
            
            closeBtn.on('click', function() {
                if (confirm('¿Está seguro que desea cerrar la vista previa? Podrá seleccionar otro archivo.')) {
                    previewContainer.hide();
                    fileInput.show(); // ← MOSTRAR DE NUEVO
                    fileInfo.show();  // ← MOSTRAR DE NUEVO
                    fileInput.val('');
                    fileInfo.html('Solo PDF, máximo 10MB. Esta será su única oportunidad de cargar este archivo.').css('color', '#666');
                    pdfDoc = null;
                }
            });
            
            confirmBtn.on('click', function(e) {
                if (!confirm('⚠️ ÚLTIMA CONFIRMACIÓN: Una vez subido, NO podrá modificar ni reemplazar este documento.\n\n¿Continuar?')) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    </script>
    
    <?php
    return ob_get_clean();
}



// =============================================
// SHORTCODE PARA CARGAR ASIGNACIONES POR EMAIL (CON CONVOCATORIA)
// =============================================
add_shortcode('cargar_asignaciones_csv', 'cargar_asignaciones_csv_shortcode');
function cargar_asignaciones_csv_shortcode() {
    
    if (!current_user_can('manage_options')) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 20px;">Acceso denegado. Solo administradores.</div>';
    }
    
    if (isset($_POST['procesar_csv']) && isset($_FILES['archivo_csv'])) {
        return procesar_asignaciones_por_email_con_convocatoria();
    }
    
    ob_start();
    ?>
    <style>
        .csv-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .csv-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .csv-header h2 {
            color: #0073aa;
        }
        .csv-info {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #0073aa;
        }
        .csv-info pre {
            background: white;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .file-input-wrapper {
            margin: 20px 0;
            padding: 30px;
            border: 2px dashed #0073aa;
            border-radius: 8px;
            text-align: center;
            background: #f9f9f9;
        }
        .btn-procesar {
            background: #0073aa;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        .btn-procesar:hover {
            background: #005a87;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
    
    <div class="csv-container">
        <div class="csv-header">
            <h2>📋 Cargar Asignaciones por Convocatoria</h2>
            <p>Suba un archivo CSV con el nombre de la convocatoria y los correos</p>
        </div>
        
        <div class="csv-info">
            <h3>📌 Formato del CSV:</h3>
            <pre>convocatoria_nombre,coordinador_email,profesor_email
                Convocatoria Docente 2025-1,coordinador1@ejemplo.com,profesor1@ejemplo.com
                Convocatoria Docente 2025-1,coordinador1@ejemplo.com,profesor2@ejemplo.com
                Convocatoria de Investigación 2025,coordinador2@ejemplo.com,profesor3@ejemplo.com</pre>
            <p><strong>Importante:</strong></p>
            <ul>
                <li>El nombre de la convocatoria debe coincidir exactamente con el título en WordPress</li>
                <li>Los correos deben coincidir exactamente con los registrados</li>
                <li>Los coordinadores deben tener rol "Academic Coordinator"</li>
                <li>Los profesores deben tener rol "Faculty"</li>
            </ul>
        </div>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('asignaciones_csv_completo', 'csv_nonce'); ?>
            
            <div class="file-input-wrapper">
                <h3>📎 Seleccionar archivo CSV</h3>
                <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required>
                <div id="file-info" style="color: #666; margin-top: 10px;"></div>
            </div>
            
            <div style="text-align: center;">
                <button type="submit" name="procesar_csv" class="btn-procesar" id="btn-procesar">
                    Procesar CSV
                </button>
            </div>
        </form>
    </div>
    
    <script>
    document.getElementById('archivo_csv').addEventListener('change', function(e) {
        var file = e.target.files[0];
        var info = document.getElementById('file-info');
        if (file) {
            info.innerHTML = '✅ Archivo seleccionado: ' + file.name;
        }
    });
    </script>
    
    <?php
    return ob_get_clean();
}


function procesar_asignaciones_por_email_con_convocatoria() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'asignaciones_revision';
    
    if (!isset($_POST['csv_nonce']) || !wp_verify_nonce($_POST['csv_nonce'], 'asignaciones_csv_completo')) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 20px;">Error de seguridad. Intente nuevamente.</div>';
    }
    
    if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 20px;">Error al subir el archivo.</div>';
    }
    

// ===== LECTURA ROBUSTA DEL CSV =====
$archivo = $_FILES['archivo_csv']['tmp_name'];

// Leer todo el contenido del archivo
$contenido = file_get_contents($archivo);

// Eliminar BOM si existe (los primeros 3 bytes)
if (substr($contenido, 0, 3) == "\xEF\xBB\xBF") {
    $contenido = substr($contenido, 3);
}

// Dividir en líneas
$lineas = explode("\n", trim($contenido));

if (empty($lineas)) {
    return '<div style="background: #f8d7da; color: #721c24; padding: 20px;">El archivo CSV está vacío.</div>';
}

// Procesar cada línea como CSV
$filas = [];
foreach ($lineas as $linea) {
    $linea = trim($linea);
    if (!empty($linea)) {
        $filas[] = str_getcsv($linea);
    }
}

if (count($filas) < 2) { // Necesitamos al menos cabecera + 1 fila
    return '<div style="background: #f8d7da; color: #721c24; padding: 20px;">El archivo CSV no tiene datos suficientes.</div>';
}

$cabecera = array_shift($filas);
$cabecera = array_map('trim', $cabecera); // Limpiar espacios

// Limpiar caracteres raros de la cabecera
$cabecera = array_map(function($item) {
    return preg_replace('/[^\x20-\x7E]/', '', $item); // Eliminar caracteres no imprimibles
}, $cabecera);
// ===== FIN LECTURA =====



    
    // Validar cabecera
    $cabecera_esperada = ['convocatoria_nombre', 'coordinador_email', 'profesor_email'];
    $cabecera_limpia = array_map('trim', $cabecera);
    
    if ($cabecera_limpia !== $cabecera_esperada) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 20px;">
                    <h3>❌ Error en el formato del CSV</h3>
                    <p>Las columnas deben ser: convocatoria_nombre, coordinador_email, profesor_email</p>
                    <p>Columnas encontradas: ' . implode(', ', $cabecera) . '</p>
                </div>';
    }
    
    $resultados = [
        'total' => count($filas),
        'exitosos' => 0,
        'errores' => [],
        'detalles' => []
    ];
    
    foreach ($filas as $index => $fila) {
        $convocatoria_nombre = trim($fila[0]);
        $coordinador_email = trim($fila[1]);
        $profesor_email = trim($fila[2]);
        $fila_num = $index + 2;
        
        // ===== BUSCAR CONVOCATORIA =====
        $convocatorias = get_posts([
            'post_type' => 'convocatoria',
            'title' => $convocatoria_nombre,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ]);
        
        if (empty($convocatorias)) {
            $resultados['errores'][] = "Fila {$fila_num}: Convocatoria '{$convocatoria_nombre}' no encontrada";
            continue;
        }
        $convocatoria = $convocatorias[0];
        $convocatoria_id = $convocatoria->ID;
        
        // ===== BUSCAR COORDINADOR =====
        $coordinador = get_user_by('email', $coordinador_email);
        if (!$coordinador) {
            $resultados['errores'][] = "Fila {$fila_num}: Coordinador {$coordinador_email} no encontrado";
            continue;
        }
        
        if (!user_can($coordinador->ID, 'edit_others_posts')) {
            $resultados['errores'][] = "Fila {$fila_num}: {$coordinador_email} no tiene rol de coordinador";
            continue;
        }
        
        // ===== BUSCAR PROFESOR =====
        $profesor = get_user_by('email', $profesor_email);
        if (!$profesor) {
            $resultados['errores'][] = "Fila {$fila_num}: Profesor {$profesor_email} no encontrado";
            continue;
        }
        
        if (!user_can($profesor->ID, 'edit_posts')) {
            $resultados['errores'][] = "Fila {$fila_num}: {$profesor_email} no tiene rol de profesor";
            continue;
        }
        
        // ===== INSERTAR ASIGNACIÓN =====
        $wpdb->replace($tabla, [
            'coordinador_id' => $coordinador->ID,
            'profesor_id' => $profesor->ID,
            'convocatoria_id' => $convocatoria_id
        ]);
        
        $resultados['exitosos']++;
        $resultados['detalles'][] = [
            'fila' => $fila_num,
            'convocatoria' => $convocatoria_nombre,
            'coordinador' => $coordinador->display_name . ' (' . $coordinador_email . ')',
            'profesor' => $profesor->display_name . ' (' . $profesor_email . ')'
        ];
    }
    
    return mostrar_resultados_asignaciones_completo($resultados);
}




function mostrar_resultados_asignaciones_completo($resultados) {
    
    $output = '<div style="background: #f0f8ff; padding: 30px; margin: 20px 0; border-radius: 8px;">';
    $output .= '<h2 style="color: #0073aa;">📊 Resultado de Asignaciones por Convocatoria</h2>';
    
    // Resumen general
    $output .= '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">';
    
    $output .= '<div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    $output .= '<div style="font-size: 36px; font-weight: bold; color: #0073aa;">' . $resultados['total'] . '</div>';
    $output .= '<div>Total de registros</div>';
    $output .= '</div>';
    
    $output .= '<div style="background: #d4edda; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    $output .= '<div style="font-size: 36px; font-weight: bold; color: #155724;">' . $resultados['exitosos'] . '</div>';
    $output .= '<div>Asignaciones exitosas</div>';
    $output .= '</div>';
    
    $output .= '<div style="background: #f8d7da; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    $output .= '<div style="font-size: 36px; font-weight: bold; color: #721c24;">' . count($resultados['errores']) . '</div>';
    $output .= '<div>Errores</div>';
    $output .= '</div>';
    
    $output .= '</div>';
    
    // Errores si existen
    if (!empty($resultados['errores'])) {
        $output .= '<div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 4px; margin: 20px 0;">';
        $output .= '<h3 style="margin-top: 0;">❌ Errores encontrados:</h3>';
        $output .= '<ul style="margin-bottom: 0; max-height: 300px; overflow-y: auto;">';
        foreach ($resultados['errores'] as $error) {
            $output .= "<li>{$error}</li>";
        }
        $output .= '</ul>';
        $output .= '</div>';
    }
    
    // Detalles de asignaciones exitosas
    if (!empty($resultados['detalles'])) {
        $output .= '<h3>✅ Asignaciones realizadas:</h3>';
        $output .= '<table style="width:100%; border-collapse: collapse; margin-top: 20px; background: white;">';
        $output .= '<thead><tr style="background: #0073aa; color: white;">';
        $output .= '<th style="padding: 10px;">Convocatoria</th>';
        $output .= '<th style="padding: 10px;">Coordinador</th>';
        $output .= '<th style="padding: 10px;">Profesor</th>';
        $output .= '</tr></thead><tbody>';
        
        foreach ($resultados['detalles'] as $detalle) {
            $output .= "<tr style='border-bottom: 1px solid #ddd;'>";
            $output .= "<td style='padding: 10px;'><strong>{$detalle['convocatoria']}</strong></td>";
            $output .= "<td style='padding: 10px;'>{$detalle['coordinador']}</td>";
            $output .= "<td style='padding: 10px;'>{$detalle['profesor']}</td>";
            $output .= '</tr>';
        }
        
        $output .= '</tbody></table>';
    }
    
    $output .= '<p style="margin-top: 30px; text-align: center;">';
    $output .= '<a href="' . home_url('/cargar-asignaciones') . '" class="button button-primary">← Cargar otro CSV</a> ';
    $output .= '<a href="' . home_url('/mis-profesores') . '" class="button">Ver asignaciones</a>';
    $output .= '</p>';
    
    $output .= '</div>';
    
    return $output;
}




// =============================================
// SHORTCODE PARA COORDINADORES: VER PROFESORES ASIGNADOS (POR CONVOCATORIA)
// =============================================
add_shortcode('mis_profesores_asignados', 'mis_profesores_asignados_shortcode');
function mis_profesores_asignados_shortcode() {
    
    if (!current_user_can('edit_others_posts')) {
        return '<div style="background: #f8d7da; padding: 20px;">Acceso denegado. Solo coordinadores.</div>';
    }
    
    global $wpdb;
    $tabla = $wpdb->prefix . 'asignaciones_revision';
    $coordinador_id = get_current_user_id();
    
    // Obtener asignaciones del coordinador
    $asignaciones = $wpdb->get_results($wpdb->prepare(
        "SELECT profesor_id, convocatoria_id FROM $tabla WHERE coordinador_id = %d",
        $coordinador_id
    ));
    
    if (empty($asignaciones)) {
        return '<div style="background: #fff3cd; padding: 20px; text-align: center;">
            <h3>No tienes profesores asignados</h3>
            <p>Espera a que el administrador te asigne profesores para revisar.</p>
        </div>';
    }
    
    // Agrupar por convocatoria
    $convocatorias = [];
    foreach ($asignaciones as $asignacion) {
        $conv_id = $asignacion->convocatoria_id;
        if (!isset($convocatorias[$conv_id])) {
            $convocatorias[$conv_id] = [
                'nombre' => get_the_title($conv_id),
                'profesores' => []
            ];
        }
        $convocatorias[$conv_id]['profesores'][] = $asignacion->profesor_id;
    }
    
    ob_start();
    ?>
    <style>
        .convocatoria-section {
            margin-bottom: 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .convocatoria-header {
            background: #0073aa;
            color: white;
            padding: 15px 20px;
            margin: 0;
        }
        .convocatoria-header h3 {
            margin: 0;
            font-size: 1.2em;
        }
        .profesores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
            background: #f9f9f9;
        }
        .profesor-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .profesor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .profesor-card h4 {
            margin-top: 0;
            color: #0073aa;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .btn-revisar {
            display: inline-block;
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 15px;
            width: 100%;
            text-align: center;
            box-sizing: border-box;
        }
        .btn-revisar:hover {
            background: #005a87;
            color: white;
        }
        .pendiente-count {
            background: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            margin-top: 10px;
        }
    </style>
    
    <h2>📋 Profesores Asignados para Revisión</h2>
    
    <?php foreach ($convocatorias as $conv_id => $conv_data): ?>
        <div class="convocatoria-section">
            <div class="convocatoria-header">
                <h3>📌 <?php echo esc_html($conv_data['nombre']); ?></h3>
            </div>
            
            <div class="profesores-grid">
                <?php 
                $profesores_data = get_users(['include' => $conv_data['profesores'], 'orderby' => 'display_name']);
                
                foreach ($profesores_data as $profesor): 
                    $pendientes = contar_documentos_pendientes_profesor_convocatoria($profesor->ID, $conv_id);
                    
                    // ===== VERIFICAR ESTADO DEL PERÍODO =====
                    $fuera_de_periodo = false;
                    $periodo_mensaje = '';
                    $documentos_reales = 0;
                    
                    if ($pendientes === -1) {
                        $fuera_de_periodo = true;
                        // Obtener fechas para el mensaje
                        $fecha_ini_revi = get_post_meta($conv_id, 'convocatoria_fecha_ini_revi', true);
                        $fecha_fin_revi = get_post_meta($conv_id, 'convocatoria_fecha_fin_revi', true);
                        $ahora = current_time('mysql');
                        
                        if ($ahora < $fecha_ini_revi) {
                            $periodo_mensaje = '⏳ La revisión inicia el ' . date('d/m/Y', strtotime($fecha_ini_revi));
                        } elseif ($ahora > $fecha_fin_revi) {
                            $periodo_mensaje = '✅ Período de revisión finalizado el ' . date('d/m/Y', strtotime($fecha_fin_revi));
                        }
                        
                        // Contar documentos reales (aunque no se puedan revisar)
                        $documentos_reales = contar_documentos_reales_profesor_convocatoria($profesor->ID, $conv_id);
                    }
                ?>
                    <div class="profesor-card">
                        <h4><?php echo esc_html($profesor->display_name); ?></h4>
                        <p><strong>Email:</strong> <?php echo esc_html($profesor->user_email); ?></p>
                        
                        <?php if ($fuera_de_periodo): ?>
                            <p><strong>Documentos del profesor:</strong> <?php echo $documentos_reales; ?></p>
                            <div style="background: #e7f5ff; color: #0073aa; padding: 8px; border-radius: 4px; margin: 10px 0; font-size: 12px;">
                                <?php echo $periodo_mensaje; ?>
                            </div>
                            <div style="background: #f0f0f0; color: #666; padding: 8px; border-radius: 4px; text-align: center;">
                                ⏸️ Revisión no disponible
                            </div>
                        <?php else: ?>
                            <p><strong>Documentos pendientes:</strong> <?php echo $pendientes; ?></p>
                            
                            <?php if ($pendientes > 0): ?>
                                <div class="pendiente-count">⏳ <?php echo $pendientes; ?> por revisar</div>
                                <a href="<?php echo home_url('/revisar-profesor/?id=' . $profesor->ID . '&conv=' . $conv_id); ?>" class="btn-revisar">
                                    Revisar Documentos
                                </a>
                            <?php else: ?>
                                <div style="background: #d4edda; color: #155724; padding: 8px; border-radius: 4px; margin-top: 10px;">
                                    ✅ No hay documentos pendientes
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php
    return ob_get_clean();
}





function contar_documentos_pendientes_profesor_convocatoria($profesor_id, $convocatoria_id) {
    
    // Verificar si la convocatoria está en período de revisión
    $fecha_ini_revi = get_post_meta($convocatoria_id, 'convocatoria_fecha_ini_revi', true);
    $fecha_fin_revi = get_post_meta($convocatoria_id, 'convocatoria_fecha_fin_revi', true);
    $ahora = current_time('mysql');
    
    $en_periodo_revision = true;
    $mensaje_periodo = '';
    
    if (!empty($fecha_ini_revi) && !empty($fecha_fin_revi)) {
        if ($ahora < $fecha_ini_revi) {
            $en_periodo_revision = false;
            $mensaje_periodo = '⏳ La revisión iniciará el ' . date('d/m/Y', strtotime($fecha_ini_revi));
        } elseif ($ahora > $fecha_fin_revi) {
            $en_periodo_revision = false;
            $mensaje_periodo = '✅ Período de revisión finalizado el ' . date('d/m/Y', strtotime($fecha_fin_revi));
        }
    }
    
    // Si no está en período de revisión, retornar 0 pero guardar mensaje
    if (!$en_periodo_revision) {
        // Podríamos guardar este mensaje en una variable global o pasarlo de otra forma
        // Por ahora, retornamos un valor especial
        return -1; // -1 indicará que no está en período
    }
    
    // Contar documentos pendientes normalmente
    $documentos = get_posts([
        'post_type' => 'conv_doc',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'documento_user_id',
                'value' => $profesor_id,
                'compare' => '='
            ],
            [
                'key' => 'documento_convocatoria_id',
                'value' => $convocatoria_id,
                'compare' => '='
            ],
            [
                'key' => 'documento_estado_revision',
                'value' => 'pendiente',
                'compare' => '='
            ]
        ],
        'fields' => 'ids'
    ]);
    
    return count($documentos);
}



// =============================================
// FUNCIONES AUXILIARES PARA CONTAR DOCUMENTOS
// =============================================


// ===== NUEVA FUNCIÓN =====
function contar_documentos_reales_profesor_convocatoria($profesor_id, $convocatoria_id) {
    $documentos = get_posts([
        'post_type' => 'conv_doc',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'documento_user_id',
                'value' => $profesor_id,
                'compare' => '='
            ],
            [
                'key' => 'documento_convocatoria_id',
                'value' => $convocatoria_id,
                'compare' => '='
            ]
        ],
        'fields' => 'ids'
    ]);
    
    return count($documentos);
}

// ===== FIN NUEVA FUNCIÓN =====




// =============================================
// SHORTCODE PARA REVISAR DOCUMENTOS DE UN PROFESOR (VERSIÓN CORREGIDA)
// =============================================
add_shortcode('revisar_documentos_profesor', 'revisar_documentos_profesor_shortcode');

function revisar_documentos_profesor_shortcode() {
    
    if (!current_user_can('edit_others_posts')) {
        return '<div style="background: #f8d7da; padding: 20px;">Acceso denegado. Solo coordinadores.</div>';
    }
    
    $profesor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $convocatoria_id = isset($_GET['conv']) ? intval($_GET['conv']) : 0;
    
    if (!$profesor_id || !$convocatoria_id) {
        return '<div style="background: #f8d7da; padding: 20px;">Parámetros inválidos.</div>';
    }
    
    // Verificar que el coordinador tiene asignado este profesor en esta convocatoria
    global $wpdb;
    $tabla = $wpdb->prefix . 'asignaciones_revision';
    $asignado = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $tabla WHERE coordinador_id = %d AND profesor_id = %d AND convocatoria_id = %d",
        get_current_user_id(), $profesor_id, $convocatoria_id
    ));
    
    if (!$asignado) {
        return '<div style="background: #f8d7da; padding: 20px;">No tienes permiso para revisar este profesor en esta convocatoria.</div>';
    }
    

// ===== VALIDAR PERÍODO DE REVISIÓN =====
$fecha_ini_revi = get_post_meta($convocatoria_id, 'convocatoria_fecha_ini_revi', true);
$fecha_fin_revi = get_post_meta($convocatoria_id, 'convocatoria_fecha_fin_revi', true);
$ahora = current_time('mysql');
$error_periodo = '';

if (!empty($fecha_ini_revi) && !empty($fecha_fin_revi)) {
    if ($ahora < $fecha_ini_revi) {
        $error_periodo = '⏳ El período de revisión para esta convocatoria aún no ha iniciado. Inicia el ' . date('d/m/Y H:i', strtotime($fecha_ini_revi));
    } elseif ($ahora > $fecha_fin_revi) {
        $error_periodo = '✅ El período de revisión para esta convocatoria finalizó el ' . date('d/m/Y H:i', strtotime($fecha_fin_revi));
    }
}

if ($error_periodo) {
    return '<div style="background: #fff3cd; color: #856404; padding: 30px; text-align: center; border-radius: 8px; margin: 20px;">
        <h3>📅 ' . $error_periodo . '</h3>
        <p><a href="' . home_url('/mis-profesores') . '" class="button">← Volver a mis profesores</a></p>
    </div>';
}
// ===== FIN VALIDACIÓN =====





    // ===== PROCESAR CALIFICACIÓN SI SE ENVIÓ EL FORMULARIO =====
    if (isset($_POST['accion']) && isset($_POST['documento_id'])) {
        return procesar_calificacion_documento($profesor_id, $convocatoria_id);
    }
    // ===== FIN PROCESAMIENTO =====
    
    // Obtener documentos del profesor en esta convocatoria
    $documentos = get_posts([
        'post_type' => 'conv_doc',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'documento_user_id',
                'value' => $profesor_id,
                'compare' => '='
            ],
            [
                'key' => 'documento_convocatoria_id',
                'value' => $convocatoria_id,
                'compare' => '='
            ]
        ],
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    if (empty($documentos)) {
        return '<div style="background: #fff3cd; padding: 20px; text-align: center;">
            <h3>📭 Este profesor no ha cargado documentos en esta convocatoria</h3>
            <p><a href="' . home_url('/mis-profesores') . '">← Volver a mis profesores</a></p>
        </div>';
    }
    
    // Mostrar mensaje de éxito si existe
    if (isset($_GET['mensaje']) && $_GET['mensaje'] == 'ok') {
        echo '<div style="background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px;">';
        echo '✅ Documento calificado correctamente.';
        echo '</div>';
    }
    
    $profesor = get_userdata($profesor_id);
    $convocatoria = get_post($convocatoria_id);
    
    ob_start();
    ?>
    <style>
        .revision-container {
            max-width: 1200px;
            margin: 20px auto;
        }
        .info-header {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #0073aa;
        }
        .documentos-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .documentos-table th {
            background: #0073aa;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .documentos-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        .documentos-table tr:hover {
            background: #f5f5f5;
        }
        .documento-info {
            font-weight: bold;
            color: #333;
        }
        .documento-meta {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        .btn-ver-pdf {
            background: #0073aa;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            display: inline-block;
            margin-top: 5px;
        }
        .btn-ver-pdf:hover {
            background: #005a87;
            color: white;
        }
        .observaciones-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            resize: vertical;
        }
        .acciones-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-aprobar {
            background: #46b450;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            flex: 1;
        }
        .btn-aprobar:hover {
            background: #3a9c44;
        }
        .btn-rechazar {
            background: #dc3232;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            flex: 1;
        }
        .btn-rechazar:hover {
            background: #a00;
        }
        .estado-actual {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }
        .estado-pendiente { background: #fff3cd; color: #856404; }
        .estado-aprobado { background: #d4edda; color: #155724; }
        .estado-rechazado { background: #f8d7da; color: #721c24; }
    </style>
    
    <div class="revision-container">
        <div class="info-header">
            <h2>📋 Revisión de Documentos</h2>
            <p><strong>Profesor:</strong> <?php echo esc_html($profesor->display_name); ?> (<?php echo esc_html($profesor->user_email); ?>)</p>
            <p><strong>Convocatoria:</strong> <?php echo esc_html($convocatoria->post_title); ?></p>
            <p><a href="<?php echo home_url('/mis-profesores'); ?>" class="button">← Volver a mis profesores</a></p>
        </div>
        
        <table class="documentos-table">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Tipo</th>
                    <th>Año</th>
                    <th>Fecha carga</th>
                    <th>Estado actual</th>
                    <th>Observaciones</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documentos as $doc): 
                    $user_doc_id = get_post_meta($doc->ID, 'documento_user_document_id', true);
                    $tipo = get_post_meta($user_doc_id, 'user_document_type', true);
                    $nombre = get_post_meta($user_doc_id, 'user_document_name', true);
                    $anio = get_post_meta($user_doc_id, 'user_document_year', true);
                    $archivo_id = get_post_meta($user_doc_id, 'user_document_location', true);
                    $url = wp_get_attachment_url($archivo_id);
                    
                    $estado = get_post_meta($doc->ID, 'documento_estado_revision', true);
                    $observaciones = get_post_meta($doc->ID, 'documento_observaciones', true);
                    
                    $tipos = [
                        'pregrado' => 'Pre-Grado',
                        'posgrado' => 'Post-Grado',
                        'publicacion' => 'Publicación',
                        'actualizacion' => 'Actualización',
                        'creacion' => 'Creación Artística',
                        'asistencia' => 'Asistencia'
                    ];
                    $tipo_mostrar = $tipos[$tipo] ?? $tipo;
                    
                    $estado_texto = match($estado) {
                        'aprobado' => '✅ Aprobado',
                        'rechazado' => '❌ Rechazado',
                        default => '⏳ Pendiente'
                    };
                ?>
                <tr>
                    <td>
                        <div class="documento-info"><?php echo esc_html($nombre); ?></div>
                        <div class="documento-meta">ID: <?php echo $doc->ID; ?></div>
                        <?php if ($url): ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" class="btn-ver-pdf">🔍 Ver PDF</a>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($tipo_mostrar); ?></td>
                    <td><?php echo esc_html($anio); ?></td>
                    <td><?php echo get_the_date('d/m/Y H:i', $doc->ID); ?></td>
                    <td>
                        <span class="estado-actual estado-<?php echo $estado ?: 'pendiente'; ?>">
                            <?php echo $estado_texto; ?>
                        </span>
                    </td>
                   
                   <td colspan="2">
                        <form method="post">
                            <?php wp_nonce_field('revision_documentos', 'revision_nonce'); ?>
                            <input type="hidden" name="profesor_id" value="<?php echo $profesor_id; ?>">
                            <input type="hidden" name="convocatoria_id" value="<?php echo $convocatoria_id; ?>">
                            <input type="hidden" name="documento_id" value="<?php echo $doc->ID; ?>">
                            
                            <textarea name="observaciones" class="observaciones-input" rows="2"><?php echo esc_textarea($observaciones); ?></textarea>
                            
                            <button type="submit" name="accion" value="aprobar" class="btn-aprobar">✅ Aprobar</button>
                            <button type="submit" name="accion" value="rechazar" class="btn-rechazar">❌ Rechazar</button>
                        </form>
                    </td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php
    return ob_get_clean();
}

// =============================================
// PROCESAR CALIFICACIÓN DE DOCUMENTO
// =============================================
function procesar_calificacion_documento($profesor_id, $convocatoria_id) {
    
    // Verificar nonce (una sola vez)
    if (!isset($_POST['revision_nonce']) || !wp_verify_nonce($_POST['revision_nonce'], 'revision_documentos')) {
        wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&error=seguridad'));
        exit;
    }
    
    // Verificar permisos
    if (!current_user_can('edit_others_posts')) {
        wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&error=permisos'));
        exit;
    }

    // ===== VALIDAR PERÍODO DE REVISIÓN =====
    $fecha_ini_revi = get_post_meta($convocatoria_id, 'convocatoria_fecha_ini_revi', true);
    $fecha_fin_revi = get_post_meta($convocatoria_id, 'convocatoria_fecha_fin_revi', true);
    $ahora = current_time('mysql');
    
    if (!empty($fecha_ini_revi) && !empty($fecha_fin_revi)) {
        if ($ahora < $fecha_ini_revi) {
            wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&error=no_iniciado'));
            exit;
        } elseif ($ahora > $fecha_fin_revi) {
            wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&error=finalizado'));
            exit;
        }
    }
    // ===== FIN VALIDACIÓN =====

    // Obtener datos del formulario
    $documento_id = isset($_POST['documento_id']) ? intval($_POST['documento_id']) : 0;
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    
    // Verificar que tenemos los datos necesarios
    if (!$documento_id || !$accion) {
        wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&error=faltan_datos'));
        exit;
    }
    
    // Obtener observaciones (puede venir como string directo o como array)
    $observaciones = '';
    if (isset($_POST['observaciones'])) {
        if (is_array($_POST['observaciones'])) {
            $observaciones = isset($_POST['observaciones'][$documento_id]) 
                ? sanitize_textarea_field($_POST['observaciones'][$documento_id]) 
                : '';
        } else {
            $observaciones = sanitize_textarea_field($_POST['observaciones']);
        }
    }
    
    // ===== CONVERTIR LA ACCIÓN AL FORMATO CORRECTO =====
    // Si viene "aprobar" → guardar como "aprobado"
    // Si viene "rechazar" → guardar como "rechazado"
    if ($accion == 'aprobar') {
        $estado_final = 'aprobado';
    } elseif ($accion == 'rechazar') {
        $estado_final = 'rechazado';
    } else {
        // Si por alguna razón viene otro valor, lo usamos tal cual
        $estado_final = $accion;
    }
    // ===== FIN CONVERSIÓN =====
    
    // Actualizar estado (con el valor convertido)
    update_post_meta($documento_id, 'documento_estado_revision', $estado_final);
    update_post_meta($documento_id, 'documento_observaciones', $observaciones);
    update_post_meta($documento_id, 'documento_fecha_revision', current_time('mysql'));
    update_post_meta($documento_id, 'documento_revisor_id', get_current_user_id());
    
    // Redirigir con mensaje de éxito
    wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&mensaje=ok'));
    exit;
}