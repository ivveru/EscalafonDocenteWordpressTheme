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

// ============================================================
// MÓDULO DE RECLAMOS
// Permite al profesor presentar reclamo cuando un documento es rechazado
// y al coordinador que lo calificó originalmente responder (una sola vez).
// ============================================================

/**
 * Verifica si un documento puede recibir un reclamo del profesor.
 * Solo si está rechazado, no tiene reclamo previo y el usuario es el dueño.
 *
 * NOTA: Si admin borró el archivo después de un reclamo previo, el reclamo
 * se mantiene como metadato. Esto bloquea automáticamente un segundo reclamo
 * (el profesor ya usó su única oportunidad).
 *
 * @param int $doc_conv_id ID del conv_doc
 * @return bool|string true si puede, o string con motivo de bloqueo
 */
function sged_puede_presentar_reclamo($doc_conv_id) {
    $user_id = get_current_user_id();
    if (!$user_id) return 'no_logueado';
    
    $owner_id = (int) get_post_meta($doc_conv_id, 'documento_user_id', true);
    if ($owner_id !== $user_id) return 'no_es_dueno';
    
    $estado = get_post_meta($doc_conv_id, 'documento_estado_revision', true);
    if ($estado !== 'rechazado') return 'no_rechazado';
    
    $reclamo_previo = get_post_meta($doc_conv_id, 'reclamo_texto', true);
    if (!empty($reclamo_previo)) return 'ya_reclamado';
    
    return true;
}

/**
 * Verifica si un documento tiene escalafón asignado.
 * Se usa para bloquear el borrado por parte del admin.
 *
 * @param int $doc_conv_id ID del conv_doc
 * @return bool true si tiene escalafón asignado
 */
function sged_documento_tiene_escalafon($doc_conv_id) {
    global $wpdb;
    
    $profesor_id     = (int) get_post_meta($doc_conv_id, 'documento_user_id', true);
    $convocatoria_id = (int) get_post_meta($doc_conv_id, 'documento_convocatoria_id', true);
    
    if (!$profesor_id || !$convocatoria_id) return false;
    
    $tabla_escalafon = $wpdb->prefix . 'escalafon';
    $existe = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $tabla_escalafon WHERE profesor_id = %d AND convocatoria_id = %d",
        $profesor_id, $convocatoria_id
    ));
    
    return !empty($existe);
}

/**
 * Verifica si un coordinador puede responder el reclamo de un documento.
 * Solo el coordinador que calificó originalmente y solo una vez.
 *
 * @param int $doc_conv_id ID del conv_doc
 * @return bool|string true si puede, o string con motivo de bloqueo
 */
function sged_puede_responder_reclamo($doc_conv_id) {
    $user_id = get_current_user_id();
    if (!$user_id) return 'no_logueado';
    
    $reclamo_texto = get_post_meta($doc_conv_id, 'reclamo_texto', true);
    if (empty($reclamo_texto)) return 'no_hay_reclamo';
    
    $revisor_id = (int) get_post_meta($doc_conv_id, 'documento_revisor_id', true);
    if ($revisor_id !== $user_id) return 'no_es_revisor_original';
    
    $respuesta_previa = get_post_meta($doc_conv_id, 'reclamo_respuesta', true);
    if (!empty($respuesta_previa)) return 'ya_respondido';
    
    return true;
}

/**
 * Procesa el envío de un reclamo por parte del profesor.
 * Hook: se ejecuta antes de renderizar el shortcode mis_archivos_cargados.
 */
function sged_procesar_reclamo_profesor() {
    if (!isset($_POST['sged_accion']) || $_POST['sged_accion'] !== 'enviar_reclamo') return;
    
    // Verificar nonce
    if (!isset($_POST['reclamo_nonce']) || !wp_verify_nonce($_POST['reclamo_nonce'], 'enviar_reclamo')) {
        wp_die('Error de seguridad. Por favor recarga la página e intenta nuevamente.', 'Error', ['back_link' => true]);
    }
    
    $doc_conv_id = isset($_POST['doc_conv_id']) ? intval($_POST['doc_conv_id']) : 0;
    $texto = isset($_POST['reclamo_texto']) ? sanitize_textarea_field($_POST['reclamo_texto']) : '';
    
    if (empty($doc_conv_id) || empty($texto)) {
        wp_redirect(home_url('/mis-archivos-cargados/?reclamo_error=datos'));
        exit;
    }
    
    // Validar longitud (mínimo 20, máximo 2000)
    if (strlen($texto) < 20) {
        wp_redirect(home_url('/mis-archivos-cargados/?reclamo_error=corto'));
        exit;
    }
    if (strlen($texto) > 2000) {
        $texto = substr($texto, 0, 2000);
    }
    
    // Validar permisos
    $puede = sged_puede_presentar_reclamo($doc_conv_id);
    if ($puede !== true) {
        wp_redirect(home_url('/mis-archivos-cargados/?reclamo_error=' . urlencode($puede)));
        exit;
    }
    
    // Guardar el reclamo
    update_post_meta($doc_conv_id, 'reclamo_texto', $texto);
    update_post_meta($doc_conv_id, 'reclamo_fecha', current_time('mysql'));
    update_post_meta($doc_conv_id, 'reclamo_estado', 'pendiente_respuesta');
    
    // Hook para notificaciones futuras (cuando SMTP esté listo)
    do_action('sged_reclamo_presentado', $doc_conv_id, get_current_user_id());
    
    wp_redirect(home_url('/mis-archivos-cargados/?reclamo_ok=1'));
    exit;
}
add_action('init', 'sged_procesar_reclamo_profesor');

/**
 * Procesa la respuesta del coordinador al reclamo.
 * Permite mantener el estado o cambiar a aprobado.
 */
function sged_procesar_respuesta_reclamo() {
    if (!isset($_POST['sged_accion']) || $_POST['sged_accion'] !== 'responder_reclamo') return;
    
    // Verificar nonce
    if (!isset($_POST['respuesta_nonce']) || !wp_verify_nonce($_POST['respuesta_nonce'], 'responder_reclamo')) {
        wp_die('Error de seguridad. Por favor recarga la página e intenta nuevamente.', 'Error', ['back_link' => true]);
    }
    
    $doc_conv_id = isset($_POST['doc_conv_id']) ? intval($_POST['doc_conv_id']) : 0;
    $respuesta   = isset($_POST['respuesta_texto']) ? sanitize_textarea_field($_POST['respuesta_texto']) : '';
    $resolucion  = isset($_POST['resolucion']) ? sanitize_text_field($_POST['resolucion']) : '';
    $profesor_id = isset($_POST['profesor_id']) ? intval($_POST['profesor_id']) : 0;
    $convocatoria_id = isset($_POST['convocatoria_id']) ? intval($_POST['convocatoria_id']) : 0;
    
    // Validaciones básicas
    if (empty($doc_conv_id) || empty($respuesta) || empty($resolucion)) {
        wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&reclamo_error=datos'));
        exit;
    }
    
    if (!in_array($resolucion, ['mantiene_rechazo', 'cambia_a_aprobado'], true)) {
        wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&reclamo_error=resolucion'));
        exit;
    }
    
    if (strlen($respuesta) < 20) {
        wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&reclamo_error=corto'));
        exit;
    }
    if (strlen($respuesta) > 2000) {
        $respuesta = substr($respuesta, 0, 2000);
    }
    
    // Validar permisos: solo el coordinador que calificó originalmente
    $puede = sged_puede_responder_reclamo($doc_conv_id);
    if ($puede !== true) {
        wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&reclamo_error=' . urlencode($puede)));
        exit;
    }
    
    // Si decide cambiar a aprobado, verificar que Decanatura NO haya asignado escalafón aún
    // (porque cambiar el estado después del escalafón rompe la coherencia)
    if ($resolucion === 'cambia_a_aprobado') {
        global $wpdb;
        $tabla_escalafon = $wpdb->prefix . 'escalafon';
        $escalafon_asignado = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $tabla_escalafon WHERE profesor_id = %d AND convocatoria_id = %d",
            $profesor_id, $convocatoria_id
        ));
        if ($escalafon_asignado) {
            wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&reclamo_error=escalafon_asignado'));
            exit;
        }
    }
    
    // Guardar la respuesta
    update_post_meta($doc_conv_id, 'reclamo_respuesta', $respuesta);
    update_post_meta($doc_conv_id, 'reclamo_respuesta_fecha', current_time('mysql'));
    update_post_meta($doc_conv_id, 'reclamo_respondido_por', get_current_user_id());
    update_post_meta($doc_conv_id, 'reclamo_resolucion', $resolucion);
    update_post_meta($doc_conv_id, 'reclamo_estado', 'respondido');
    
    // Si la resolución cambia el estado del documento, aplicarlo
    if ($resolucion === 'cambia_a_aprobado') {
        update_post_meta($doc_conv_id, 'documento_estado_revision', 'aprobado');
    }
    
    // Hook para notificaciones futuras
    do_action('sged_reclamo_respondido', $doc_conv_id, get_current_user_id(), $resolucion);
    
    wp_redirect(home_url('/revisar-profesor/?id=' . $profesor_id . '&conv=' . $convocatoria_id . '&reclamo_respondido=1'));
    exit;
}
add_action('init', 'sged_procesar_respuesta_reclamo');

/**
 * Renderiza el bloque visual del reclamo para el profesor (en mis-archivos-cargados).
 * Incluye: ver reclamo previo, formulario para reclamar, o respuesta del coordinador.
 *
 * @param int $doc_conv_id
 * @return string HTML
 */
function sged_render_bloque_reclamo_profesor($doc_conv_id) {
    $estado          = get_post_meta($doc_conv_id, 'documento_estado_revision', true);
    $reclamo_texto   = get_post_meta($doc_conv_id, 'reclamo_texto', true);
    $reclamo_fecha   = get_post_meta($doc_conv_id, 'reclamo_fecha', true);
    $respuesta       = get_post_meta($doc_conv_id, 'reclamo_respuesta', true);
    $respuesta_fecha = get_post_meta($doc_conv_id, 'reclamo_respuesta_fecha', true);
    $respondido_por  = get_post_meta($doc_conv_id, 'reclamo_respondido_por', true);
    $resolucion      = get_post_meta($doc_conv_id, 'reclamo_resolucion', true);
    
    // CASO 1: documento no rechazado y sin reclamo → no mostrar nada
    if ($estado !== 'rechazado' && empty($reclamo_texto)) {
        return '';
    }
    
    ob_start();
    ?>
    <div class="sged-reclamo-bloque" style="background:#fff; border:1px solid #e0e0e0; border-left:4px solid #dc0c1d; padding:15px 20px; margin:10px 0; border-radius:4px;">
        
        <?php if (empty($reclamo_texto)): ?>
            <!-- ESTADO A: documento rechazado, sin reclamo aún → mostrar botón -->
            <div style="display:flex; align-items:center; justify-content:space-between; gap:15px; flex-wrap:wrap;">
                <div>
                    <strong style="color:#721c24;">⚖️ ¿No estás de acuerdo con esta calificación?</strong>
                    <p style="margin:5px 0 0; color:#666; font-size:13px;">Tienes derecho a presentar un reclamo formal sobre este documento. Solo puedes hacerlo una vez.</p>
                </div>
                <button type="button"
                        onclick="document.getElementById('sged-form-reclamo-<?php echo $doc_conv_id; ?>').style.display='block'; this.style.display='none';"
                        style="background:#dc0c1d; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:600; white-space:nowrap;">
                    📝 Presentar reclamo
                </button>
            </div>
            
            <!-- Formulario (oculto inicialmente) -->
            <form id="sged-form-reclamo-<?php echo $doc_conv_id; ?>" method="post" style="display:none; margin-top:15px; padding-top:15px; border-top:1px solid #eee;">
                <?php wp_nonce_field('enviar_reclamo', 'reclamo_nonce'); ?>
                <input type="hidden" name="sged_accion" value="enviar_reclamo">
                <input type="hidden" name="doc_conv_id" value="<?php echo $doc_conv_id; ?>">
                
                <label style="display:block; margin-bottom:8px; font-weight:600;">
                    Argumenta tu reclamo:
                </label>
                <textarea name="reclamo_texto"
                          rows="5"
                          minlength="20"
                          maxlength="2000"
                          required
                          placeholder="Explica detalladamente por qué consideras que tu documento sí cumple con los requisitos. Mínimo 20 caracteres, máximo 2000."
                          style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-family:inherit; resize:vertical;"></textarea>
                <p style="font-size:12px; color:#888; margin:5px 0 10px;">
                    ⚠️ Una vez enviado, no podrás modificar el reclamo. Asegúrate de incluir todos los argumentos.
                </p>
                
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button"
                            onclick="this.closest('form').style.display='none'; this.closest('.sged-reclamo-bloque').querySelector('button[onclick*=\'display=\\\'block\\\'\']').style.display='inline-block';"
                            style="background:#f5f5f5; color:#333; border:1px solid #ccc; padding:10px 20px; border-radius:4px; cursor:pointer;">
                        Cancelar
                    </button>
                    <button type="submit"
                            onclick="return confirm('¿Estás seguro de enviar este reclamo? No podrás modificarlo después.');"
                            style="background:#dc0c1d; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:600;">
                        Enviar reclamo →
                    </button>
                </div>
            </form>
            
        <?php else: ?>
            <!-- ESTADO B: reclamo ya presentado → mostrar el reclamo -->
            <div style="margin-bottom:12px;">
                <strong style="color:#721c24;">📝 Tu reclamo</strong>
                <span style="color:#888; font-size:12px; margin-left:10px;">
                    Presentado el <?php echo date('d/m/Y H:i', strtotime($reclamo_fecha)); ?>
                </span>
            </div>
            <div style="background:#f9f9f9; padding:12px 15px; border-radius:4px; color:#444; line-height:1.5; white-space:pre-wrap;"><?php echo esc_html($reclamo_texto); ?></div>
            
            <?php if (empty($respuesta)): ?>
                <!-- Esperando respuesta -->
                <div style="margin-top:12px; background:#fff3cd; color:#856404; padding:10px 15px; border-radius:4px; font-size:13px;">
                    ⏳ <strong>Esperando respuesta del coordinador que calificó tu documento.</strong>
                </div>
            <?php else: ?>
                <!-- Respuesta recibida -->
                <?php
                $respondedor = $respondido_por ? get_userdata($respondido_por) : null;
                $nombre_respondedor = $respondedor ? $respondedor->display_name : 'Coordinador';
                ?>
                <div style="margin-top:15px; padding-top:15px; border-top:1px solid #eee;">
                    <strong style="color:#155724;">✅ Respuesta del coordinador</strong>
                    <span style="color:#888; font-size:12px; margin-left:10px;">
                        Por <?php echo esc_html($nombre_respondedor); ?> el <?php echo date('d/m/Y H:i', strtotime($respuesta_fecha)); ?>
                    </span>
                </div>
                <div style="background:#f0f8f0; padding:12px 15px; border-radius:4px; color:#444; line-height:1.5; margin-top:8px; white-space:pre-wrap;"><?php echo esc_html($respuesta); ?></div>
                
                <div style="margin-top:12px; padding:10px 15px; border-radius:4px; font-size:13px; <?php echo $resolucion === 'cambia_a_aprobado' ? 'background:#d4edda; color:#155724;' : 'background:#f8d7da; color:#721c24;'; ?>">
                    ⚖️ <strong>Resolución final:</strong>
                    <?php if ($resolucion === 'cambia_a_aprobado'): ?>
                        El reclamo fue <strong>aceptado</strong>. Tu documento ahora figura como <strong>✅ Cumple</strong>.
                    <?php else: ?>
                        El reclamo fue <strong>analizado</strong> pero se mantiene la calificación de <strong>❌ No Cumple</strong>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Renderiza el bloque visual del reclamo para el coordinador (en revisar-profesor).
 * Solo aparece si hay reclamo y este coordinador es el revisor original.
 *
 * @param int $doc_conv_id
 * @param int $profesor_id
 * @param int $convocatoria_id
 * @return string HTML
 */
function sged_render_bloque_reclamo_coordinador($doc_conv_id, $profesor_id, $convocatoria_id) {
    $reclamo_texto = get_post_meta($doc_conv_id, 'reclamo_texto', true);
    
    // Sin reclamo → no mostrar nada
    if (empty($reclamo_texto)) return '';
    
    $reclamo_fecha   = get_post_meta($doc_conv_id, 'reclamo_fecha', true);
    $respuesta       = get_post_meta($doc_conv_id, 'reclamo_respuesta', true);
    $respuesta_fecha = get_post_meta($doc_conv_id, 'reclamo_respuesta_fecha', true);
    $resolucion      = get_post_meta($doc_conv_id, 'reclamo_resolucion', true);
    $revisor_id      = (int) get_post_meta($doc_conv_id, 'documento_revisor_id', true);
    $current_user_id = get_current_user_id();
    
    // Obtener datos del profesor (para mostrar)
    $profesor = get_userdata($profesor_id);
    $nombre_profesor = $profesor ? $profesor->display_name : 'Profesor';
    
    ob_start();
    ?>
    <div class="sged-reclamo-coord" style="background:#fff; border:2px solid #dc0c1d; border-radius:6px; padding:20px; margin:15px 0; box-shadow:0 2px 4px rgba(220,12,29,0.1);">
        
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px;">
            <span style="background:#dc0c1d; color:white; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600;">
                🔴 RECLAMO DEL PROFESOR
            </span>
            <span style="color:#888; font-size:12px;">
                Presentado el <?php echo date('d/m/Y H:i', strtotime($reclamo_fecha)); ?>
            </span>
        </div>
        
        <div style="background:#f9f9f9; padding:15px; border-radius:4px; color:#333; line-height:1.6; white-space:pre-wrap; margin-bottom:15px;"><?php echo esc_html($reclamo_texto); ?></div>
        
        <?php if (!empty($respuesta)): ?>
            <!-- Ya respondió: mostrar respuesta en read-only -->
            <div style="border-top:1px dashed #ccc; padding-top:15px;">
                <strong style="color:#155724;">✅ Tu respuesta (definitiva)</strong>
                <span style="color:#888; font-size:12px; margin-left:10px;">
                    <?php echo date('d/m/Y H:i', strtotime($respuesta_fecha)); ?>
                </span>
                <div style="background:#f0f8f0; padding:12px 15px; border-radius:4px; color:#444; line-height:1.5; margin-top:8px; white-space:pre-wrap;"><?php echo esc_html($respuesta); ?></div>
                
                <div style="margin-top:10px; padding:8px 12px; border-radius:4px; font-size:13px; <?php echo $resolucion === 'cambia_a_aprobado' ? 'background:#d4edda; color:#155724;' : 'background:#f8d7da; color:#721c24;'; ?>">
                    ⚖️ Resolución:
                    <?php echo $resolucion === 'cambia_a_aprobado'
                        ? '<strong>Aceptaste el reclamo</strong>. El documento se marcó como Cumple.'
                        : '<strong>Mantuviste la calificación</strong> de No Cumple.'; ?>
                </div>
            </div>
            
        <?php elseif ($revisor_id === $current_user_id): ?>
            <!-- Sin respuesta y soy el revisor original: mostrar formulario -->
            <form method="post" style="border-top:1px dashed #ccc; padding-top:15px;">
                <?php wp_nonce_field('responder_reclamo', 'respuesta_nonce'); ?>
                <input type="hidden" name="sged_accion" value="responder_reclamo">
                <input type="hidden" name="doc_conv_id" value="<?php echo $doc_conv_id; ?>">
                <input type="hidden" name="profesor_id" value="<?php echo $profesor_id; ?>">
                <input type="hidden" name="convocatoria_id" value="<?php echo $convocatoria_id; ?>">
                
                <label style="display:block; margin-bottom:8px; font-weight:600;">
                    Tu respuesta al reclamo (única y definitiva):
                </label>
                <textarea name="respuesta_texto"
                          rows="5"
                          minlength="20"
                          maxlength="2000"
                          required
                          placeholder="Explica detalladamente tu decisión sobre el reclamo del profesor. Mínimo 20 caracteres, máximo 2000."
                          style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-family:inherit; resize:vertical;"></textarea>
                
                <div style="margin:15px 0 10px; padding:12px 15px; background:#f9f9f9; border-radius:4px;">
                    <strong style="display:block; margin-bottom:8px;">Decisión final:</strong>
                    <label style="display:block; margin-bottom:6px; cursor:pointer;">
                        <input type="radio" name="resolucion" value="mantiene_rechazo" required>
                        <strong>Mantener "❌ No Cumple"</strong> — El reclamo se analizó pero la calificación no cambia.
                    </label>
                    <label style="display:block; cursor:pointer;">
                        <input type="radio" name="resolucion" value="cambia_a_aprobado" required>
                        <strong>Cambiar a "✅ Cumple"</strong> — Aceptas el reclamo y el documento queda aprobado.
                    </label>
                </div>
                
                <p style="font-size:12px; color:#888; margin:5px 0 12px;">
                    ⚠️ Solo puedes responder una vez. La respuesta y la resolución serán definitivas.
                </p>
                
                <button type="submit"
                        onclick="return confirm('¿Confirmas tu respuesta? No podrás modificarla después.');"
                        style="background:#dc0c1d; color:white; border:none; padding:10px 24px; border-radius:4px; cursor:pointer; font-weight:600;">
                    Responder reclamo →
                </button>
            </form>
            
        <?php else: ?>
            <!-- Reclamo pendiente pero no soy el revisor original -->
            <div style="border-top:1px dashed #ccc; padding-top:15px; background:#fff3cd; color:#856404; padding:10px 15px; border-radius:4px; font-size:13px;">
                ⏳ Este reclamo debe ser respondido por el coordinador que calificó originalmente el documento.
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================
// FIN MÓDULO DE RECLAMOS
// ============================================================

// ============================================================
// MÓDULO: BORRAR DOCUMENTO (Admin)
// Permite al admin borrar archivo + calificación de un documento,
// manteniendo el historial del reclamo intacto.
// ============================================================

/**
 * AJAX: Borra el archivo PDF y la calificación de un documento.
 * Conserva el reclamo y su respuesta como historial.
 *
 * Requiere: manage_options
 * Bloqueado si: ya hay escalafón asignado en esa convocatoria
 */
add_action('wp_ajax_sged_borrar_documento', 'sged_ajax_borrar_documento');
function sged_ajax_borrar_documento() {
    
    // Permisos: solo admin
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['msg' => 'No tienes permisos para esta acción']);
    }
    
    // Nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sged_borrar_documento')) {
        wp_send_json_error(['msg' => 'Error de seguridad. Recarga la página.']);
    }
    
    $doc_conv_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : 0;
    $confirmacion = isset($_POST['confirmacion']) ? sanitize_text_field($_POST['confirmacion']) : '';
    $motivo = isset($_POST['motivo']) ? sanitize_textarea_field($_POST['motivo']) : '';
    
    // Validar confirmación literal
    if (strtoupper($confirmacion) !== 'ELIMINAR') {
        wp_send_json_error(['msg' => 'Debes escribir ELIMINAR para confirmar la acción']);
    }
    
    if (!$doc_conv_id) {
        wp_send_json_error(['msg' => 'ID de documento inválido']);
    }
    
    // Verificar que el documento exista y sea conv_doc
    $post = get_post($doc_conv_id);
    if (!$post || $post->post_type !== 'conv_doc') {
        wp_send_json_error(['msg' => 'Documento no encontrado']);
    }
    
    // BLOQUEO: si ya hay escalafón asignado, no se puede borrar
    if (sged_documento_tiene_escalafon($doc_conv_id)) {
        wp_send_json_error([
            'msg' => 'No se puede borrar este documento: Decanatura ya asignó escalafón al profesor en esta convocatoria. Para revertirlo, primero debe eliminarse el escalafón.'
        ]);
    }
    
    // ===== EJECUTAR EL BORRADO =====
    
    // 1. Obtener el ID del archivo (attachment)
    $user_doc_id = get_post_meta($doc_conv_id, 'documento_user_document_id', true);
    $attachment_id = $user_doc_id ? get_post_meta($user_doc_id, 'user_document_location', true) : 0;
    
    // 2. Borrar el archivo físico del servidor (attachment)
    if ($attachment_id) {
        wp_delete_attachment($attachment_id, true); // true = forzar borrado definitivo
    }
    
    // 3. Borrar el user_document asociado (el wrapper del archivo)
    if ($user_doc_id) {
        wp_delete_post($user_doc_id, true);
    }
    
    // 4. Limpiar metadatos del conv_doc relacionados al archivo y calificación
    // (PERO conservamos los metadatos del reclamo)
    delete_post_meta($doc_conv_id, 'documento_user_document_id');
    update_post_meta($doc_conv_id, 'documento_estado_revision', 'pendiente');
    delete_post_meta($doc_conv_id, 'documento_observaciones');
    delete_post_meta($doc_conv_id, 'documento_revisor_id');
    delete_post_meta($doc_conv_id, 'documento_fecha_revision');
    
    // 5. Registrar bitácora del borrado
    update_post_meta($doc_conv_id, 'documento_borrado_por',    get_current_user_id());
    update_post_meta($doc_conv_id, 'documento_borrado_fecha',  current_time('mysql'));
    update_post_meta($doc_conv_id, 'documento_borrado_motivo', $motivo);
    
    // Hook para notificaciones futuras (cuando SMTP esté listo)
    do_action('sged_documento_borrado_por_admin', $doc_conv_id, get_current_user_id(), $motivo);
    
    wp_send_json_success([
        'msg' => 'Documento eliminado correctamente. El profesor podrá cargar uno nuevo.'
    ]);
}

/**
 * Cuenta cuántos reclamos pendientes de respuesta tiene un coordinador
 * sobre un profesor específico en una convocatoria.
 *
 * "Pendiente" = reclamo presentado por el profesor + sin respuesta aún
 *               + este coordinador fue quien calificó originalmente.
 *
 * @param int $profesor_id     ID del profesor dueño del documento
 * @param int $convocatoria_id ID de la convocatoria
 * @param int $coordinador_id  ID del coordinador que debe responder
 * @return int Número de reclamos pendientes
 */
function sged_contar_reclamos_pendientes($profesor_id, $convocatoria_id, $coordinador_id) {
    global $wpdb;
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(p.ID)
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_user     ON p.ID = pm_user.post_id     AND pm_user.meta_key     = 'documento_user_id'
         INNER JOIN {$wpdb->postmeta} pm_conv     ON p.ID = pm_conv.post_id     AND pm_conv.meta_key     = 'documento_convocatoria_id'
         INNER JOIN {$wpdb->postmeta} pm_revisor  ON p.ID = pm_revisor.post_id  AND pm_revisor.meta_key  = 'documento_revisor_id'
         INNER JOIN {$wpdb->postmeta} pm_reclamo  ON p.ID = pm_reclamo.post_id  AND pm_reclamo.meta_key  = 'reclamo_texto'
         LEFT  JOIN {$wpdb->postmeta} pm_resp     ON p.ID = pm_resp.post_id     AND pm_resp.meta_key     = 'reclamo_respuesta'
         WHERE p.post_type = 'conv_doc'
           AND pm_user.meta_value    = %d
           AND pm_conv.meta_value    = %d
           AND pm_revisor.meta_value = %d
           AND pm_reclamo.meta_value != ''
           AND (pm_resp.meta_value IS NULL OR pm_resp.meta_value = '')",
        $profesor_id, $convocatoria_id, $coordinador_id
    ));
    
    return (int) $count;
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
    
    // Solo profesores, admins o auditores pueden ver
    if (!current_user_can('edit_posts') && !current_user_can('manage_options') && !current_user_can('view_convocatorias')) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 10px;">No tienes permisos para ver convocatorias.</div>';
    }
    
    // Detectar auditor (solo lectura)
    $es_auditor = current_user_can('view_convocatorias') && !current_user_can('manage_options') && !current_user_can('edit_posts');
    
    // Si es administrador o auditor, ve todas las convocatorias activas (sin filtro)
    if (current_user_can('manage_options') || $es_auditor) {
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
        <?php if ($es_auditor): ?>
            <div style="background:#e7f5ff; color:#0c5460; padding:10px 15px; border-radius:4px; margin-bottom:15px; font-size:13px;">
                👁️ <strong>Modo Auditor:</strong> Estás visualizando las convocatorias en modo solo lectura.
            </div>
        <?php endif; ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(<?php echo $es_auditor ? '380px' : '300px'; ?>, 1fr)); gap: 20px;">
            <?php foreach ($convocatorias as $conv): 
                $fecha_ini_carga = get_post_meta($conv->ID, 'convocatoria_fecha_ini_carga', true);
                $fecha_fin_carga = get_post_meta($conv->ID, 'convocatoria_fecha_fin_carga', true);
                $fecha_ini_revi  = get_post_meta($conv->ID, 'convocatoria_fecha_ini_revi', true);
                $fecha_fin_revi  = get_post_meta($conv->ID, 'convocatoria_fecha_fin_revi', true);
                
                // Determinar estado actual de la convocatoria (para auditor)
                $ahora = current_time('mysql');
                $estado_actual = '';
                $estado_color  = '';
                if ($fecha_ini_carga && $ahora < $fecha_ini_carga) {
                    $estado_actual = '⏳ Próxima a abrir';
                    $estado_color  = 'background:#fff3cd; color:#856404;';
                } elseif ($fecha_fin_carga && $ahora >= $fecha_ini_carga && $ahora <= $fecha_fin_carga) {
                    $estado_actual = '📤 En periodo de carga';
                    $estado_color  = 'background:#d4edda; color:#155724;';
                } elseif ($fecha_ini_revi && $fecha_fin_revi && $ahora >= $fecha_ini_revi && $ahora <= $fecha_fin_revi) {
                    $estado_actual = '🔍 En periodo de revisión';
                    $estado_color  = 'background:#cfe2ff; color:#084298;';
                } elseif ($fecha_fin_revi && $ahora > $fecha_fin_revi) {
                    $estado_actual = '✅ Convocatoria cerrada';
                    $estado_color  = 'background:#e2e3e5; color:#41464b;';
                } else {
                    $estado_actual = '🕒 Entre periodos';
                    $estado_color  = 'background:#f8f9fa; color:#6c757d;';
                }
                
                // Función helper para formatear fechas
                $fmt = function($f) {
                    return $f ? date('d/m/Y', strtotime($f)) : '<em style="color:#999;">No definida</em>';
                };
            ?>
                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 18px; background:#fff;">
                    <h3 style="margin-top:0; color:#dc0c1d;"><?php echo esc_html($conv->post_title); ?></h3>
                    
                    <?php if ($es_auditor): ?>
                        <div style="margin: 8px 0 12px; padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; <?php echo $estado_color; ?>">
                            <?php echo $estado_actual; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($conv->post_content)): ?>
                        <p style="color:#555; font-size:13px;"><?php echo wp_trim_words($conv->post_content, 20); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($es_auditor): ?>
                        <!-- Vista detallada de fechas para Auditor -->
                        <div style="background:#f9f9f9; border-radius:6px; padding:12px; margin: 12px 0; font-size:13px;">
                            <div style="font-weight:600; color:#dc0c1d; margin-bottom:8px; border-bottom:1px solid #ddd; padding-bottom:5px;">
                                📤 Periodo de carga de documentos
                            </div>
                            <div style="display:grid; grid-template-columns:auto 1fr; gap:4px 12px; margin-bottom:12px;">
                                <span style="color:#666;">📅 Apertura:</span>
                                <strong><?php echo $fmt($fecha_ini_carga); ?></strong>
                                <span style="color:#666;">🔒 Cierre:</span>
                                <strong><?php echo $fmt($fecha_fin_carga); ?></strong>
                            </div>
                            
                            <div style="font-weight:600; color:#dc0c1d; margin-bottom:8px; border-bottom:1px solid #ddd; padding-bottom:5px;">
                                🔍 Periodo de revisión
                            </div>
                            <div style="display:grid; grid-template-columns:auto 1fr; gap:4px 12px;">
                                <span style="color:#666;">📅 Apertura:</span>
                                <strong><?php echo $fmt($fecha_ini_revi); ?></strong>
                                <span style="color:#666;">🔒 Cierre:</span>
                                <strong><?php echo $fmt($fecha_fin_revi); ?></strong>
                            </div>
                        </div>
                        <span style="display:inline-block; background:#f0f0f0; color:#666; padding:8px 16px; border-radius:4px; font-size:13px;">
                            👁️ Solo lectura
                        </span>
                    <?php else: ?>
                        <!-- Vista simple para Profesor (sin cambios) -->
                        <p><strong>Cierre:</strong> <?php echo $fmt($fecha_fin_carga); ?></p>
                        <a href="<?php echo home_url('/carga-nueva/?conv=' . $conv->ID); ?>" class="button">
                            📄 Cargar Documentos a esta Convocatoria
                        </a>
                    <?php endif; ?>
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
        // Verificar si el documento sigue teniendo archivo
        // Si admin lo borró, permitimos re-cargar (en el mismo conv_doc para conservar reclamo)
        $doc_existente_id = $documento_existente[0]->ID;
        $tiene_archivo = !empty(get_post_meta($doc_existente_id, 'documento_user_document_id', true));
        $fue_borrado_admin = !empty(get_post_meta($doc_existente_id, 'documento_borrado_por', true));
        
        if ($tiene_archivo || !$fue_borrado_admin) {
            // Tiene archivo o nunca fue borrado por admin → no permitir nueva carga
            wp_redirect(home_url('/mis-archivos-cargados/?conv=' . $convocatoria_id));
            exit;
        }
        // Si llegamos aquí, el admin borró el archivo y el profesor puede re-cargar
        // (la lógica de procesar_documento_convocatoria detectará este caso)
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
    
    // Mostrar mensaje de éxito si viene de una carga reciente
    if (isset($_GET['success']) && $_GET['success'] == '1') {
        echo '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #28a745;">
                ✅ <strong>Archivo cargado correctamente.</strong> Tu documento está pendiente de revisión.
              </div>';
    }
    
    // Mensajes del flujo de reclamos
    if (isset($_GET['reclamo_ok']) && $_GET['reclamo_ok'] == '1') {
        echo '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #28a745;">
                ✅ <strong>Reclamo enviado correctamente.</strong> Recibirás una respuesta del coordinador.
              </div>';
    }
    if (isset($_GET['reclamo_error'])) {
        $errores = [
            'datos'           => 'Faltan datos en el reclamo.',
            'corto'           => 'El reclamo debe tener al menos 20 caracteres.',
            'no_logueado'     => 'Debes iniciar sesión para presentar un reclamo.',
            'no_es_dueno'     => 'No puedes presentar reclamos sobre documentos de otro usuario.',
            'no_rechazado'    => 'Solo puedes presentar reclamos sobre documentos rechazados.',
            'ya_reclamado'    => 'Ya presentaste un reclamo sobre este documento. Solo se permite uno.',
        ];
        $err = sanitize_text_field($_GET['reclamo_error']);
        $msg = isset($errores[$err]) ? $errores[$err] : 'Ocurrió un error al procesar el reclamo.';
        echo '<div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #dc3545;">
                ❌ <strong>' . esc_html($msg) . '</strong>
              </div>';
    }
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
            background: #a10613;
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
            
            // ===== DETECTAR SI EL DOCUMENTO FUE BORRADO POR ADMIN =====
            $borrado_por_admin = get_post_meta($doc->ID, 'documento_borrado_por', true);
            $fecha_borrado     = get_post_meta($doc->ID, 'documento_borrado_fecha', true);
            $motivo_borrado    = get_post_meta($doc->ID, 'documento_borrado_motivo', true);
            $fue_borrado       = !empty($borrado_por_admin) && empty($user_doc_id);
            
            // ===== OBTENER METADATOS DEL DOCUMENTO ORIGINAL =====
            $tipo_documento   = $user_doc_id ? get_post_meta($user_doc_id, 'user_document_type', true) : '';
            $nombre_documento = $user_doc_id ? get_post_meta($user_doc_id, 'user_document_name', true) : '';
            $anio_documento   = $user_doc_id ? get_post_meta($user_doc_id, 'user_document_year', true) : '';
            $archivo_id       = $user_doc_id ? get_post_meta($user_doc_id, 'user_document_location', true) : 0;
            $url_archivo      = $archivo_id ? wp_get_attachment_url($archivo_id) : '';
            
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
                'resolucion' => 'Resolución'
            ];
            $tipo_mostrar = isset($tipos[$tipo_documento]) ? $tipos[$tipo_documento] : $tipo_documento;
            
            // ===== ESTADO CON COLORES =====
            $estado_class = 'pendiente';
            $estado_texto = 'Pendiente';
            if ($estado == 'aprobado') {
                $estado_class = 'aprobado';
                $estado_texto = '✅ Cumple';
            } elseif ($estado == 'rechazado') {
                $estado_class = 'rechazado';
                $estado_texto = '❌ No Cumple';
            }
        ?>
            <!-- FILA PRINCIPAL DEL DOCUMENTO -->
            <tr <?php echo $fue_borrado ? 'style="background:#fafafa; opacity:0.7;"' : ''; ?>>
                <td>
                    <span class="convocatoria-badge">
                        <?php echo esc_html($nombre_convocatoria); ?>
                    </span>
                </td>
                <td><?php echo esc_html($tipo_mostrar); ?></td>
                <td><?php echo esc_html($nombre_documento ?: '(documento eliminado)'); ?></td>
                <td><?php echo esc_html($anio_documento); ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($doc->post_date)); ?></td>
                <td>
                    <?php if ($fue_borrado): ?>
                        <span style="background:#f8d7da; color:#721c24; padding:4px 10px; border-radius:4px; font-size:12px;">
                            🗑️ Eliminado por admin
                        </span>
                    <?php else: ?>
                        <span class="estado-<?php echo $estado_class; ?>"><?php echo $estado_texto; ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($url_archivo): ?>
                        <a href="<?php echo $url_archivo; ?>" target="_blank" class="btn-preview">🔍 Ver PDF</a>
                    <?php elseif ($fue_borrado): ?>
                        <span style="color:#888; font-size:12px;">Sin archivo</span>
                    <?php endif; ?>
                </td>
            </tr>
            
            <!-- ===== FILA DE AVISO DE BORRADO (si aplica) ===== -->
            <?php if ($fue_borrado): 
                $admin_user = get_userdata($borrado_por_admin);
                $admin_nombre = $admin_user ? $admin_user->display_name : 'Administrador';
                $url_recarga = home_url('/carga-nueva/?conv=' . $conv_id);
            ?>
            <tr class="borrado-aviso-fila">
                <td colspan="7" style="padding:15px 20px; background:#fff3cd; border-top:2px solid #ffc107;">
                    <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:300px;">
                            <strong style="color:#856404; display:block; margin-bottom:5px;">
                                ⚠️ El administrador eliminó este documento
                            </strong>
                            <span style="color:#666; font-size:13px;">
                                Eliminado el <?php echo esc_html(date('d/m/Y H:i', strtotime($fecha_borrado))); ?>
                                por <strong><?php echo esc_html($admin_nombre); ?></strong>
                                <?php if (!empty($motivo_borrado)): ?>
                                    <br><em>Motivo: <?php echo esc_html($motivo_borrado); ?></em>
                                <?php endif; ?>
                            </span>
                        </div>
                        <a href="<?php echo esc_url($url_recarga); ?>"
                           style="background:#0073aa; color:white; padding:10px 20px; border-radius:4px; text-decoration:none; font-weight:600; white-space:nowrap;">
                            📤 Cargar nuevo documento
                        </a>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <!-- ===== FIN FILA DE AVISO DE BORRADO ===== -->

            <!-- ===== FILA DE OBSERVACIONES (si existen) ===== -->
            <?php if (!$fue_borrado && (!empty($observaciones) || $revisor_id)): ?>
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
            
            <!-- ===== FILA DE RECLAMO (solo si aplica) ===== -->
            <?php $reclamo_html = sged_render_bloque_reclamo_profesor($doc->ID); ?>
            <?php if (!empty($reclamo_html)): ?>
            <tr class="reclamo-fila">
                <td colspan="7" style="padding:0; background:#fff;">
                    <?php echo $reclamo_html; ?>
                </td>
            </tr>
            <?php endif; ?>
            <!-- ===== FIN FILA DE RECLAMO ===== -->

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

    // Verificar nonce
    if (!isset($_POST['convocatoria_nonce']) || !wp_verify_nonce($_POST['convocatoria_nonce'], 'carga_convocatoria_nonce')) {
        wp_redirect(home_url('/convocatorias-para-profesores/?error=nonce'));
        exit;
    }
    
    // Verificar usuario
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_redirect(home_url('/?error=permisos'));
        exit;
    }
    
    $current_user = wp_get_current_user();
    $convocatoria_id = intval($_POST['convocatoria_id']);
    
    // URL base para redirecciones de error (vuelve al formulario de la misma convocatoria)
    $url_error_base = home_url('/cargar-documento-convocatoria/?id=' . $convocatoria_id);
    
    // ===== DETECTAR SI EXISTE UN CONV_DOC PREVIO BORRADO POR ADMIN =====
    // En ese caso lo REUTILIZAMOS para conservar el historial del reclamo
    $conv_doc_previo = get_posts(array(
        'post_type' => 'conv_doc',
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => 'documento_convocatoria_id', 'value' => $convocatoria_id, 'compare' => '='),
            array('key' => 'documento_user_id',         'value' => $current_user->ID,  'compare' => '='),
            array('key' => 'documento_borrado_por',     'compare' => 'EXISTS'),
        ),
        'posts_per_page' => 1
    ));
    
    $reutilizar_conv_doc = !empty($conv_doc_previo) ? $conv_doc_previo[0]->ID : null;
    
    // 1. Crear el documento base
    $user_document_name = $current_user->user_login . " | " . sanitize_text_field($_POST["user_document_name"]);
    $post_id = wp_insert_post(array(
        'post_status' => 'publish',
        'post_type'   => 'user_document',
        'post_title'  => $user_document_name,
        'post_author' => $current_user->ID
    ));
    
    if (!$post_id || is_wp_error($post_id)) {
        wp_redirect($url_error_base . '&error=archivo');
        exit;
    }
    
    // 2. Intentar guardar el archivo
    $document_result = save_user_document_file("user_document_location", $current_user->user_login, $_POST["user_document_type"]);
    
    // Manejo de errores de carga
    if (is_array($document_result) && isset($document_result["error"])) {
        // Limpiar el post creado (rollback)
        wp_delete_post($post_id, true);
        
        // Determinar tipo de error para redirigir con mensaje apropiado
        $error_msg = strtolower($document_result["error"]);
        $tipo_error = 'archivo';
        if (strpos($error_msg, 'pdf') !== false || strpos($error_msg, 'mime') !== false) {
            $tipo_error = 'formato';
        } elseif (strpos($error_msg, 'tamaño') !== false || strpos($error_msg, 'tamano') !== false || strpos($error_msg, 'maximo') !== false) {
            $tipo_error = 'tamano';
        }
        
        wp_redirect($url_error_base . '&error=' . $tipo_error);
        exit;
    }
    
    $attachment_id = $document_result;
    
    // 3. Guardar metadatos del documento base
    update_post_meta($post_id, 'user_document_type',     sanitize_text_field($_POST['user_document_type']));
    update_post_meta($post_id, 'user_document_name',     sanitize_text_field($_POST['user_document_name']));
    update_post_meta($post_id, 'user_document_year',     intval($_POST['user_document_year']));
    update_post_meta($post_id, 'user_document_location', $attachment_id);
    update_post_meta($post_id, 'user_document_user_id',  $current_user->ID);
    
    // 4. Crear o reutilizar documento de convocatoria
    if ($reutilizar_conv_doc) {
        // REUTILIZAR conv_doc previo (conservar reclamo y respuesta)
        $doc_conv_id = $reutilizar_conv_doc;
        
        // Actualizar título
        wp_update_post([
            'ID'         => $doc_conv_id,
            'post_title' => $user_document_name . ' - Convocatoria ' . $convocatoria_id,
        ]);
        
        // Vincular el nuevo archivo
        update_post_meta($doc_conv_id, 'documento_user_document_id',  $post_id);
        update_post_meta($doc_conv_id, 'documento_estado_revision',   'pendiente');
        update_post_meta($doc_conv_id, 'documento_fecha_subida',      current_time('mysql'));
        
        // Reset del flag de notificación (porque es una carga nueva sobre el mismo conv_doc)
        delete_post_meta($doc_conv_id, 'notificacion_carga_enviada');
        delete_post_meta($doc_conv_id, 'notificacion_calificacion_enviada');
        
        // NOTA: NO borramos los metadatos del reclamo (reclamo_*) ni los de bitácora
        // (documento_borrado_*). Eso es intencional para mantener historial.
        
        // Disparar evento para notificación de carga (caso re-subida tras borrado admin)
        do_action('sged_documento_cargado', $doc_conv_id);
    } else {
        // Crear NUEVO conv_doc (caso normal: primera carga)
        $doc_conv_id = wp_insert_post(array(
            'post_type'   => 'conv_doc',
            'post_title'  => $user_document_name . ' - Convocatoria ' . $convocatoria_id,
            'post_status' => 'publish',
            'post_author' => $current_user->ID
        ));
        
        if (!$doc_conv_id || is_wp_error($doc_conv_id)) {
            // Rollback: eliminar el documento base y el archivo subido
            wp_delete_post($post_id, true);
            if ($attachment_id) {
                wp_delete_attachment($attachment_id, true);
            }
            
            // Log silencioso del error real (solo si WP_DEBUG está activo)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                global $wpdb;
                error_log('SGED - Error creando conv_doc: ' . $wpdb->last_error);
            }
            
            wp_redirect($url_error_base . '&error=archivo');
            exit;
        }
        
        // 5. Vincular metadatos del documento de convocatoria (caso nuevo)
        update_post_meta($doc_conv_id, 'documento_convocatoria_id',   $convocatoria_id);
        update_post_meta($doc_conv_id, 'documento_user_document_id',  $post_id);
        update_post_meta($doc_conv_id, 'documento_estado_revision',   'pendiente');
        update_post_meta($doc_conv_id, 'documento_user_id',           $current_user->ID);
        update_post_meta($doc_conv_id, 'documento_fecha_subida',      current_time('mysql'));
        
        // Disparar evento para notificación de carga (caso nuevo)
        do_action('sged_documento_cargado', $doc_conv_id);
    }
    
    // 6. Redirigir limpiamente a "Mis archivos cargados" con mensaje de éxito
    wp_redirect(home_url('/mis-archivos-cargados/?conv=' . $convocatoria_id . '&success=1'));
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
                    <option value="resolucion">Resolución</option>
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
    
    // ===== MANEJAR DESCARGA DE PLANTILLA (DEBE IR PRIMERO) =====
    if (isset($_GET['descargar_plantilla']) && current_user_can('manage_options')) {
        // Limpiar cualquier salida previa
        if (ob_get_level()) ob_end_clean();
        
        // Nombre del archivo
        $filename = 'plantilla_asignaciones_grupos.csv';
        
        // Cabeceras HTTP para forzar descarga
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Crear el contenido del CSV
        $output = fopen('php://output', 'w');
        
        // Agregar BOM para Excel (UTF-8)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, [
            'convocatoria_nombre',
            'profesor_email',
            'coordinador1_email',
            'coordinador2_email',
            'coordinador3_email',
            'lider_coordinador_email',
            'nombre_grupo'
        ]);
        
        // Ejemplo de filas (datos de ejemplo)
        fputcsv($output, [
            'Convocatoria Docente 2025-1',
            'profesor1@ejemplo.com',
            'coordinador1@ejemplo.com',
            'coordinador2@ejemplo.com',
            'coordinador3@ejemplo.com',
            'coordinador1@ejemplo.com',
            'Grupo A'
        ]);
        
        fputcsv($output, [
            'Convocatoria Docente 2025-1',
            'profesor2@ejemplo.com',
            'coordinador1@ejemplo.com',
            'coordinador2@ejemplo.com',
            'coordinador3@ejemplo.com',
            'coordinador1@ejemplo.com',
            'Grupo A'
        ]);
        
        fputcsv($output, [
            'Convocatoria de Investigación 2025',
            'profesor3@ejemplo.com',
            'coordinador4@ejemplo.com',
            'coordinador5@ejemplo.com',
            '',
            'coordinador4@ejemplo.com',
            'Grupo B'
        ]);
        
        fputcsv($output, [
            'Convocatoria de Investigación 2025',
            'profesor4@ejemplo.com',
            'coordinador4@ejemplo.com',
            'coordinador5@ejemplo.com',
            '',
            'coordinador4@ejemplo.com',
            'Grupo B'
        ]);
        
        fclose($output);
        exit; // IMPORTANTE: Detener la ejecución
    }
    // ===== FIN MANEJO DE DESCARGA =====
    
    // Solo administradores
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
            font-size: 12px;
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
        .btn-descargar {
            background: #17a2b8;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-descargar:hover {
            background: #138496;
            color: white;
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
            <h2>📋 Cargar Asignaciones por Convocatoria (Grupos de Coordinadores)</h2>
            <p>Suba un archivo CSV para asignar grupos de coordinadores a los profesores</p>
        </div>
        
        <div class="csv-info">
            <h3>📌 Formato del CSV:</h3>
            <pre>convocatoria_nombre,profesor_email,coordinador1_email,coordinador2_email,coordinador3_email,lider_coordinador_email,nombre_grupo
Convocatoria Docente 2025-1,profesor1@ejemplo.com,coord1@ejemplo.com,coord2@ejemplo.com,coord3@ejemplo.com,lider@ejemplo.com,Grupo A
Convocatoria Docente 2025-1,profesor2@ejemplo.com,coord1@ejemplo.com,coord2@ejemplo.com,coord3@ejemplo.com,lider@ejemplo.com,Grupo A
Convocatoria de Investigación,profesor3@ejemplo.com,coord4@ejemplo.com,coord5@ejemplo.com,,coord4@ejemplo.com,Grupo B</pre>
            
            <p><strong>📌 Explicación de columnas:</strong></p>
            <ul>
                <li><strong>convocatoria_nombre</strong> - Título exacto de la convocatoria en WordPress</li>
                <li><strong>profesor_email</strong> - Email del profesor (rol "Faculty")</li>
                <li><strong>coordinador1_email</strong> - Email del coordinador 1 (rol "Academic Coordinator")</li>
                <li><strong>coordinador2_email</strong> - Email del coordinador 2 (puede quedar vacío)</li>
                <li><strong>coordinador3_email</strong> - Email del coordinador 3 (puede quedar vacío)</li>
                <li><strong>lider_coordinador_email</strong> - Email del coordinador líder (debe estar en la lista de coordinadores)</li>
                <li><strong>nombre_grupo</strong> - Nombre identificador del grupo (ej: "Grupo A", "Comisión 1")</li>
            </ul>
            
            <p><strong>⚠️ Reglas importantes:</strong></p>
            <ul>
                <li>El líder debe estar incluido en la lista de coordinadores del grupo</li>
                <li>Un profesor puede estar en múltiples grupos (para diferentes convocatorias)</li>
                <li>Solo el líder podrá calificar los documentos del profesor</li>
                <li>Los otros coordinadores solo podrán ver los documentos cargados</li>
                <li>Todos los correos deben coincidir exactamente con los registrados en WordPress</li>
                <li>Los coordinadores deben tener rol "Academic Coordinator"</li>
                <li>Los profesores deben tener rol "Faculty"</li>
            </ul>
        </div>
        
        <div class="warning">
            <strong>⚠️ Nota importante:</strong> Al cargar este CSV, se reemplazarán las asignaciones existentes para el mismo profesor, convocatoria y grupo.
        </div>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('asignaciones_csv_completo', 'csv_nonce'); ?>
            
            <div class="file-input-wrapper">
                <h3>📎 Seleccionar archivo CSV</h3>
                <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required>
                <div id="file-info" style="color: #666; margin-top: 10px;"></div>
            </div>
            
            <div style="text-align: center;">
                <a href="?descargar_plantilla=1" class="btn-descargar">
                    📥 Descargar Plantilla CSV
                </a>
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
            info.innerHTML = '✅ Archivo seleccionado: ' + file.name + ' (' + (file.size/1024).toFixed(2) + ' KB)';
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
    $contenido = file_get_contents($archivo);
    
    // Eliminar BOM si existe
    if (substr($contenido, 0, 3) == "\xEF\xBB\xBF") {
        $contenido = substr($contenido, 3);
    }
    
    $lineas = explode("\n", trim($contenido));
    if (empty($lineas)) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 20px;">El archivo CSV está vacío.</div>';
    }
    
    $filas = [];
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if (!empty($linea)) {
            $filas[] = str_getcsv($linea);
        }
    }
    
    if (count($filas) < 2) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 20px;">El archivo CSV no tiene datos suficientes.</div>';
    }
    
    $cabecera = array_shift($filas);
    $cabecera = array_map('trim', $cabecera);
    
    // Validar cabecera (ahora con 7 columnas)
    $cabecera_esperada = ['convocatoria_nombre', 'profesor_email', 'coordinador1_email', 'coordinador2_email', 'coordinador3_email', 'lider_coordinador_email', 'nombre_grupo'];
    $cabecera_limpia = array_map('trim', $cabecera);
    
    if ($cabecera_limpia !== $cabecera_esperada) {
        return '<div style="background: #f8d7da; color: #721c24; padding: 20px;">
                    <h3>❌ Error en el formato del CSV</h3>
                    <p>Las columnas deben ser: ' . implode(', ', $cabecera_esperada) . '</p>
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
        $profesor_email = trim($fila[1]);
        $coordinador1_email = trim($fila[2]);
        $coordinador2_email = trim($fila[3]);
        $coordinador3_email = trim($fila[4]);
        $lider_email = trim($fila[5]);
        $nombre_grupo = trim($fila[6]);
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
        
        // ===== RECOLECTAR COORDINADORES =====
        $coordinadores_emails = [$coordinador1_email, $coordinador2_email, $coordinador3_email];
        $coordinadores_ids = [];
        
        foreach ($coordinadores_emails as $coord_email) {
            if (empty($coord_email)) continue;
            
            $coordinador = get_user_by('email', $coord_email);
            if (!$coordinador) {
                $resultados['errores'][] = "Fila {$fila_num}: Coordinador {$coord_email} no encontrado";
                continue 2;
            }
            
            if (!user_can($coordinador->ID, 'edit_others_posts')) {
                $resultados['errores'][] = "Fila {$fila_num}: {$coord_email} no tiene rol de coordinador";
                continue 2;
            }
            
            $coordinadores_ids[] = $coordinador->ID;
        }
        
        if (empty($coordinadores_ids)) {
            $resultados['errores'][] = "Fila {$fila_num}: No se encontraron coordinadores válidos";
            continue;
        }
        
        // ===== BUSCAR LÍDER =====
        $lider = get_user_by('email', $lider_email);
        if (!$lider) {
            $resultados['errores'][] = "Fila {$fila_num}: Líder {$lider_email} no encontrado";
            continue;
        }
        
        if (!user_can($lider->ID, 'edit_others_posts')) {
            $resultados['errores'][] = "Fila {$fila_num}: {$lider_email} no tiene rol de coordinador";
            continue;
        }
        
        // Verificar que el líder esté en la lista de coordinadores
        if (!in_array($lider->ID, $coordinadores_ids)) {
            $resultados['errores'][] = "Fila {$fila_num}: El líder {$lider_email} no está en la lista de coordinadores del grupo";
            continue;
        }
        
        // ===== INSERTAR ASIGNACIONES =====
        // Primero, eliminar asignaciones anteriores para este profesor + convocatoria + grupo
        $wpdb->delete($tabla, [
            'profesor_id' => $profesor->ID,
            'convocatoria_id' => $convocatoria_id,
            'grupo_nombre' => $nombre_grupo
        ]);

        // Insertar cada coordinador del grupo
        $coordinadores_guardados = 0;
        foreach ($coordinadores_ids as $coord_id) {
            $es_lider = ($coord_id == $lider->ID) ? 1 : 0;
            
            // Verificar si ya existe (por si acaso)
            $existe = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $tabla WHERE coordinador_id = %d AND profesor_id = %d AND convocatoria_id = %d AND grupo_nombre = %s",
                $coord_id, $profesor->ID, $convocatoria_id, $nombre_grupo
            ));
            
            if (!$existe) {
                $insertado = $wpdb->insert($tabla, [
                    'coordinador_id' => $coord_id,
                    'profesor_id' => $profesor->ID,
                    'convocatoria_id' => $convocatoria_id,
                    'grupo_nombre' => $nombre_grupo,
                    'es_lider' => $es_lider,
                    'fecha_asignacion' => current_time('mysql')
                ]);
                
                if ($insertado) {
                    $coordinadores_guardados++;
                }
            } else {
                $coordinadores_guardados++;
            }
        }
        
        if ($coordinadores_guardados > 0) {
            $resultados['exitosos']++;
            $resultados['detalles'][] = [
                'fila' => $fila_num,
                'convocatoria' => $convocatoria_nombre,
                'grupo' => $nombre_grupo,
                'profesor' => $profesor->display_name . ' (' . $profesor_email . ')',
                'coordinadores' => implode(', ', array_map(function($id) {
                    $user = get_userdata($id);
                    return $user ? $user->display_name : $id;
                }, $coordinadores_ids)),
                'lider' => $lider->display_name . ' (' . $lider_email . ')'
            ];
        } else {
            $resultados['errores'][] = "Fila {$fila_num}: No se pudieron guardar las asignaciones";
        }
    }
    
    return mostrar_resultados_asignaciones_completo($resultados);
}




function mostrar_resultados_asignaciones_completo($resultados) {
    
    $output = '<div style="background: #f0f8ff; padding: 30px; margin: 20px 0; border-radius: 8px;">';
    $output .= '<h2 style="color: #dc0c1d;">📊 Resultado de Asignaciones por Convocatoria</h2>';
    
    // Resumen general
    $output .= '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">';
    
    $output .= '<div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    $output .= '<div style="font-size: 36px; font-weight: bold; color: #dc0c1d;">' . $resultados['total'] . '</div>';
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
        $output .= '<thead><tr style="background: #dc0c1d; color: white;">';
        $output .= '<th style="padding: 10px;">Convocatoria</th>';
        $output .= '<th style="padding: 10px;">Grupo</th>';
        $output .= '<th style="padding: 10px;">Profesor</th>';
        $output .= '<th style="padding: 10px;">Coordinadores</th>';
        $output .= '<th style="padding: 10px;">Líder</th>';
        $output .= '</tr></thead><tbody>';
        
        foreach ($resultados['detalles'] as $detalle) {
            $output .= "<tr style='border-bottom: 1px solid #ddd;'>";
            $output .= "<td style='padding: 10px;'><strong>{$detalle['convocatoria']}</strong></td>";
            $output .= "<td style='padding: 10px;'><span style='background: #e7f5ff; padding: 4px 8px; border-radius: 4px;'>{$detalle['grupo']}</span></td>";
            $output .= "<td style='padding: 10px;'>{$detalle['profesor']}</td>";
            $output .= "<td style='padding: 10px;'>{$detalle['coordinadores']}</td>";
            $output .= "<td style='padding: 10px;'><strong>👑 {$detalle['lider']}</strong></td>";
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
    
    // Obtener asignaciones donde el coordinador es líder
        $asignaciones = $wpdb->get_results($wpdb->prepare(
            "SELECT profesor_id, convocatoria_id, grupo_nombre, es_lider 
            FROM $tabla 
            WHERE coordinador_id = %d",
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
            background: #dc0c1d;
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
            color: #dc0c1d;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .btn-revisar {
            display: inline-block;
            background: #dc0c1d;
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
            background: #a10613;
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
        /* ===== ESTILOS PARA RECLAMOS PENDIENTES ===== */
        .profesor-card.tiene-reclamos {
            border: 2px solid #dc0c1d;
            box-shadow: 0 4px 12px rgba(220, 12, 29, 0.15);
            position: relative;
        }
        .profesor-card.tiene-reclamos::before {
            content: '';
            position: absolute;
            top: -1px;
            right: -1px;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 35px 35px 0;
            border-color: transparent #dc0c1d transparent transparent;
            border-radius: 0 8px 0 0;
        }
        .reclamo-badge {
            background: #dc0c1d;
            color: white;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 12px 0;
            box-shadow: 0 2px 4px rgba(220, 12, 29, 0.2);
            animation: pulse-reclamo 2s ease-in-out infinite;
        }
        .reclamo-badge .badge-icon {
            font-size: 18px;
        }
        .reclamo-badge .badge-count {
            background: white;
            color: #dc0c1d;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 700;
            margin-left: auto;
        }
        @keyframes pulse-reclamo {
            0%, 100% { box-shadow: 0 2px 4px rgba(220, 12, 29, 0.2); }
            50%      { box-shadow: 0 2px 12px rgba(220, 12, 29, 0.45); }
        }
        .btn-revisar.tiene-reclamos {
            background: #dc0c1d;
            font-weight: 600;
        }
        .btn-revisar.tiene-reclamos:hover {
            background: #a10613;
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
                    
                    // ===== CONTAR RECLAMOS PENDIENTES DE ESTE COORDINADOR =====
                    $reclamos_pendientes = sged_contar_reclamos_pendientes($profesor->ID, $conv_id, $coordinador_id);
                    
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
                    <div class="profesor-card <?php echo $reclamos_pendientes > 0 ? 'tiene-reclamos' : ''; ?>">
                        <h4><?php echo esc_html($profesor->display_name); ?></h4>
                        <p><strong>Email:</strong> <?php echo esc_html($profesor->user_email); ?></p>
                        
                        <?php if ($reclamos_pendientes > 0): ?>
                        <div class="reclamo-badge">
                            <span class="badge-icon">🔴</span>
                            <span>Reclamo pendiente por responder</span>
                            <span class="badge-count"><?php echo $reclamos_pendientes; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($fuera_de_periodo): ?>
                            <p><strong>Documentos del profesor:</strong> <?php echo $documentos_reales; ?></p>
                            <div style="background: #e7f5ff; color: #dc0c1d; padding: 8px; border-radius: 4px; margin: 10px 0; font-size: 12px;">
                                <?php echo $periodo_mensaje; ?>
                            </div>
                            <?php if ($reclamos_pendientes > 0): ?>
                                <a href="<?php echo home_url('/revisar-profesor/?id=' . $profesor->ID . '&conv=' . $conv_id); ?>" class="btn-revisar tiene-reclamos">
                                    ⚖️ Responder reclamo
                                </a>
                            <?php else: ?>
                                <div style="background: #f0f0f0; color: #666; padding: 8px; border-radius: 4px; text-align: center;">
                                    ⏸️ Revisión no disponible
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php
                            // Verificar si el coordinador actual es líder para este profesor/convocatoria
                            $es_lider_actual = $wpdb->get_var($wpdb->prepare(
                                "SELECT es_lider FROM $tabla WHERE coordinador_id = %d AND profesor_id = %d AND convocatoria_id = %d LIMIT 1",
                                $coordinador_id, $profesor->ID, $conv_id
                            ));
                            ?>
                            <p><strong>Documentos pendientes:</strong> <?php echo $pendientes; ?></p>
                            
                            <?php if ($pendientes > 0): ?>
                                <div class="pendiente-count">⏳ <?php echo $pendientes; ?> por revisar</div>
                                <a href="<?php echo home_url('/revisar-profesor/?id=' . $profesor->ID . '&conv=' . $conv_id); ?>" class="btn-revisar <?php echo $reclamos_pendientes > 0 ? 'tiene-reclamos' : ''; ?>">
                                    <?php echo $es_lider_actual ? 'Revisar Documentos' : '🔍 Ver Documentos'; ?>
                                </a>
                            <?php else: ?>
                                <?php if ($reclamos_pendientes == 0): ?>
                                <div style="background: #d4edda; color: #155724; padding: 8px; border-radius: 4px; margin-top: 10px;">
                                    ✅ No hay documentos pendientes
                                </div>
                                <?php endif; ?>
                                <?php if ($reclamos_pendientes > 0): ?>
                                    <a href="<?php echo home_url('/revisar-profesor/?id=' . $profesor->ID . '&conv=' . $conv_id); ?>" class="btn-revisar tiene-reclamos">
                                        ⚖️ Responder reclamo
                                    </a>
                                <?php elseif (!$es_lider_actual): ?>
                                    <a href="<?php echo home_url('/revisar-profesor/?id=' . $profesor->ID . '&conv=' . $conv_id); ?>" class="btn-revisar" style="background: #0073aa;">
                                        🔍 Ver Documentos
                                    </a>
                                <?php endif; ?>
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
// SHORTCODE PARA REVISAR DOCUMENTOS DE UN PROFESOR
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
    
    // ===== VERIFICAR ASIGNACIÓN Y ROL (LÍDER O NO) =====
    global $wpdb;
    $tabla = $wpdb->prefix . 'asignaciones_revision';
    $asignacion = $wpdb->get_row($wpdb->prepare(
        "SELECT id, es_lider FROM $tabla WHERE coordinador_id = %d AND profesor_id = %d AND convocatoria_id = %d",
        get_current_user_id(), $profesor_id, $convocatoria_id
    ));
    
    if (!$asignacion) {
        return '<div style="background: #f8d7da; padding: 20px;">No tienes permiso para revisar este profesor en esta convocatoria.</div>';
    }
    
    $es_lider = ($asignacion->es_lider == 1);
    // ===== FIN VERIFICACIÓN =====
    
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
    
    // ===== PROCESAR CALIFICACIÓN SOLO SI ES LÍDER =====
    if ($es_lider && isset($_POST['accion']) && isset($_POST['documento_id'])) {
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
    
    // Mensajes del flujo de respuesta a reclamos
    if (isset($_GET['reclamo_respondido']) && $_GET['reclamo_respondido'] == '1') {
        echo '<div style="background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px;">';
        echo '✅ <strong>Respuesta enviada correctamente.</strong> El profesor podrá verla en su panel.';
        echo '</div>';
    }
    if (isset($_GET['reclamo_error'])) {
        $errores_coord = [
            'datos'                  => 'Faltan datos en la respuesta.',
            'corto'                  => 'La respuesta debe tener al menos 20 caracteres.',
            'resolucion'             => 'Debes elegir una resolución (mantener o cambiar a aprobado).',
            'no_logueado'            => 'Debes iniciar sesión para responder.',
            'no_hay_reclamo'         => 'No existe un reclamo sobre este documento.',
            'no_es_revisor_original' => 'Solo el coordinador que calificó originalmente puede responder este reclamo.',
            'ya_respondido'          => 'Este reclamo ya fue respondido. No se puede modificar.',
            'escalafon_asignado'     => 'No se puede cambiar el estado: Decanatura ya asignó escalafón a este profesor.',
        ];
        $err = sanitize_text_field($_GET['reclamo_error']);
        $msg = isset($errores_coord[$err]) ? $errores_coord[$err] : 'Ocurrió un error al procesar la respuesta.';
        echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border-radius: 4px;">';
        echo '❌ <strong>' . esc_html($msg) . '</strong>';
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
        .info-header .rol-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            margin-top: 10px;
        }
        .rol-badge.lider {
            background: #d4edda;
            color: #155724;
        }
        .rol-badge.lectura {
            background: #e7f5ff;
            color: #0073aa;
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
        .btn-ver-pdf {
            background: #0073aa;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            display: inline-block;
        }
        .btn-ver-pdf:hover {
            background: #005a87;
        }
        .observaciones-text {
            background: #f9f9f9;
            padding: 8px;
            border-radius: 4px;
            max-width: 250px;
            font-size: 13px;
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
        
        <?php if (!$es_lider): ?>
        /* Estilos para modo solo lectura */
        .documentos-table td:last-child {
            background: #f9f9f9;
        }
        .mensaje-lectura {
            background: #e7f5ff;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        <?php endif; ?>
    </style>
    
    <div class="revision-container">
        <div class="info-header">
            <h2>📋 Revisión de Documentos</h2>
            <p><strong>Profesor:</strong> <?php echo esc_html($profesor->display_name); ?> (<?php echo esc_html($profesor->user_email); ?>)</p>
            <p><strong>Convocatoria:</strong> <?php echo esc_html($convocatoria->post_title); ?></p>
            <?php if ($es_lider): ?>
                <div class="rol-badge lider">👑 Eres el líder del grupo - Puedes calificar documentos</div>
            <?php else: ?>
                <div class="rol-badge lectura">🔍 Eres miembro del grupo - Solo puedes ver los documentos</div>
            <?php endif; ?>
            <p style="margin-top: 15px;"><a href="<?php echo home_url('/mis-profesores'); ?>" class="button">← Volver a mis profesores</a></p>
        </div>
        
        <?php if (!$es_lider): ?>
            <div class="mensaje-lectura">
                ℹ️ No tienes permisos para calificar documentos. Solo puedes visualizar los documentos cargados por el profesor.
            </div>
        <?php endif; ?>
        
        <table class="documentos-table">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Tipo</th>
                    <th>Año</th>
                    <th>Fecha carga</th>
                    <th>Estado actual</th>
                    <th>Observaciones</th>
                    <?php if ($es_lider): ?>
                        <th>Acciones</th>
                    <?php endif; ?>
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
                    
                  /*  $tipos = [
                        'pregrado' => 'Pre-Grado',
                        'posgrado' => 'Post-Grado',
                        'publicacion' => 'Publicación',
                        'actualizacion' => 'Actualización',
                        'creacion' => 'Creación Artística',
                        'asistencia' => 'Asistencia'
                    ];*/

                    $tipos = [
                        'resolucion' => 'Resolución'
                    ];
                    $tipo_mostrar = $tipos[$tipo] ?? $tipo;
                    
                    $estado_texto = match($estado) {
                        'aprobado' => '✅ Cumple',
                        'rechazado' => '❌ No Cumple',
                        default => '⏳ Pendiente'
                    };
                ?>
                <tr>
                    <td>
                        <div style="font-weight: bold; margin-bottom: 5px;"><?php echo esc_html($nombre); ?></div>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" class="btn-ver-pdf">🔍 Ver PDF</a>
                    </td>
                    <td><?php echo esc_html($tipo_mostrar); ?></td>
                    <td><?php echo esc_html($anio); ?></td>
                    <td><?php echo get_the_date('d/m/Y H:i', $doc->ID); ?></td>
                    <td>
                        <span class="estado-actual estado-<?php echo $estado ?: 'pendiente'; ?>">
                            <?php echo $estado_texto; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($observaciones): ?>
                            <div class="observaciones-text">📝 <?php echo esc_html($observaciones); ?></div>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    
                    <?php if ($es_lider): ?>
                        <td style="min-width: 200px;">
                            <form method="post" style="margin:0; padding:0;">
                                <?php wp_nonce_field('revision_documentos', 'revision_nonce'); ?>
                                <input type="hidden" name="profesor_id" value="<?php echo $profesor_id; ?>">
                                <input type="hidden" name="convocatoria_id" value="<?php echo $convocatoria_id; ?>">
                                <input type="hidden" name="documento_id" value="<?php echo $doc->ID; ?>">
                                
                                <textarea name="observaciones" class="observaciones-input" rows="2" placeholder="Observaciones..." style="width:100%; margin-bottom:8px; padding:6px; border:1px solid #ddd; border-radius:4px;"><?php echo esc_textarea($observaciones); ?></textarea>
                                
                                <div style="display: flex; gap: 5px;">
                                    <button type="submit" name="accion" value="aprobar" class="btn-aprobar" style="background:#46b450; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; flex:1;">✅ Cumple</button>
                                    <button type="submit" name="accion" value="rechazar" class="btn-rechazar" style="background:#dc3232; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; flex:1;">❌ No Cumple</button>
                                </div>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
                
                <!-- ===== FILA DE RECLAMO (si aplica) ===== -->
                <?php $reclamo_coord_html = sged_render_bloque_reclamo_coordinador($doc->ID, $profesor_id, $convocatoria_id); ?>
                <?php if (!empty($reclamo_coord_html)): ?>
                <tr class="reclamo-coord-fila">
                    <td colspan="<?php echo $es_lider ? 7 : 6; ?>" style="padding:0; background:#fafafa;">
                        <?php echo $reclamo_coord_html; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <!-- ===== FIN FILA DE RECLAMO ===== -->
                
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





// =============================================
// SHORTCODE PARA ADMIN: VER CALIFICACIONES POR CONVOCATORIA (VERSIÓN AJAX)
// =============================================
add_shortcode('admin_calificaciones', 'admin_calificaciones_shortcode');

function admin_calificaciones_shortcode() {
    
     // ===== CARGAR JQUERY MANUALMENTE =====
    echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
    // ===== FIN CARGA MANUAL =====


    // Solo administradores o usuarios con capacidad 'view_calificaciones' (Decanatura)
        if (!current_user_can('manage_options') && !current_user_can('view_calificaciones')) {
            return '<div style="background: #f8d7da; color: #721c24; padding: 20px; text-align: center;">
                        <h3>⛔ Acceso denegado</h3>
                        <p>No tienes permisos para acceder a esta herramienta.</p>
                    </div>';
        }
    
    // Obtener todas las convocatorias
    $convocatorias = get_posts([
        'post_type' => 'convocatoria',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    // Procesar filtros iniciales (para la primera carga)
    $convocatoria_filtro = isset($_GET['convocatoria']) && $_GET['convocatoria'] !== '' ? intval($_GET['convocatoria']) : 0;
    $coordinador_filtro = isset($_GET['coordinador']) && $_GET['coordinador'] !== '' ? intval($_GET['coordinador']) : 0;
    $estado_filtro = isset($_GET['estado']) && $_GET['estado'] !== '' ? sanitize_text_field($_GET['estado']) : '';
    
    // Obtener todos los coordinadores
    $coordinadores = get_users([
        'role__in' => ['academic_coordinator', 'editor'],
        'orderby' => 'display_name'
    ]);
    
    // Obtener calificaciones iniciales
    $calificaciones = obtener_calificaciones_filtradas($convocatoria_filtro, $coordinador_filtro, $estado_filtro);
    
    // Calcular estadísticas
    $total_calificaciones = count($calificaciones);
    $aprobados = 0;
    $rechazados = 0;
    foreach ($calificaciones as $cal) {
        if ($cal->estado == 'aprobado') $aprobados++;
        if ($cal->estado == 'rechazado') $rechazados++;
    }
    
    ob_start();
    ?>
    <style>
        .admin-calificaciones-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .admin-calificaciones-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .admin-calificaciones-header h2 {
            color: #dc0c1d;
        }
        .filtros-container {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #dc0c1d;
        }
        .filtros-form {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filtro-group {
            flex: 1;
            min-width: 250px;
        }
        .filtro-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #dc0c1d;
        }
        .filtro-group select, .filtro-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn-filtrar {
            background: #dc0c1d;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            height: 42px;
        }
        .btn-filtrar:hover {
            background: #a10613;
        }
        .btn-excel {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        .btn-excel:hover {
            background: #218838;
        }
        .tabla-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        .tabla-calificaciones {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .tabla-calificaciones th {
            background: #dc0c1d;
            color: white;
            padding: 12px;
            text-align: left;
            position: sticky;
            top: 0;
        }
        .tabla-calificaciones td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }
        .tabla-calificaciones tr:hover {
            background: #f5f5f5;
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
        .estado-pendiente {
            background: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }
        .badge-convocatoria {
            background: #e7f5ff;
            color: #dc0c1d;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        .observaciones-cell {
            max-width: 300px;
            white-space: normal;
            word-wrap: break-word;
        }
        .resumen-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #dc0c1d;
        }
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        #filtro-loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        #filtro-loading img {
            width: 32px;
            height: 32px;
        }
    </style>
    
    <div class="admin-calificaciones-container">
        <div class="admin-calificaciones-header">
            <h2>📊 Trazabilidad de Calificaciones por Convocatoria</h2>
            <p>Revise todas las calificaciones realizadas por los coordinadores</p>
        </div>
        
        <!-- Filtros con AJAX -->
        <div class="filtros-container">
            <div class="filtros-form">
                
                <div class="filtro-group">
                    <label>📅 Convocatoria</label>
                    <select name="convocatoria" id="filtro-convocatoria">
                        <option value="">Todas las convocatorias</option>
                        <?php foreach ($convocatorias as $conv): ?>
                            <option value="<?php echo $conv->ID; ?>" <?php echo selected($convocatoria_filtro, $conv->ID); ?>>
                                <?php echo esc_html($conv->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-group">
                    <label>👤 Coordinador</label>
                    <select name="coordinador" id="filtro-coordinador">
                        <option value="">Todos los coordinadores</option>
                        <?php foreach ($coordinadores as $coord): ?>
                            <option value="<?php echo $coord->ID; ?>" <?php echo selected($coordinador_filtro, $coord->ID); ?>>
                                <?php echo esc_html($coord->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-group">
                    <label>📊 Estado</label>
                    <select name="estado" id="filtro-estado">
                        <option value="">Todos los estados</option>
                        <option value="aprobado" <?php echo selected($estado_filtro, 'aprobado'); ?>>✅ cumple</option>
                        <option value="rechazado" <?php echo selected($estado_filtro, 'rechazado'); ?>>❌ no cumple</option>
                        <option value="pendiente" <?php echo selected($estado_filtro, 'pendiente'); ?>>⏳ Pendiente</option>
                    </select>
                </div>
                
                <div>
                    <button type="button" class="btn-filtrar" id="btn-filtrar-ajax">Filtrar</button>
                    <button type="button" class="btn-excel" id="btn-excel-ajax">📥 Exportar Excel</button>
                </div>
            </div>
            
            <!-- Indicador de carga -->
            <div id="filtro-loading">
                <img src="<?php echo admin_url('images/spinner.gif'); ?>" alt="Cargando...">
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="resumen-stats" id="stats-container">
            <div class="stat-card">
                <div class="stat-number" id="stat-total"><?php echo $total_calificaciones; ?></div>
                <div class="stat-label">Total calificaciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-aprobados" style="color: #28a745;"><?php echo $aprobados; ?></div>
                <div class="stat-label">✅ cumple</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-rechazados" style="color: #dc3545;"><?php echo $rechazados; ?></div>
                <div class="stat-label">❌ no cumple</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-pendientes"><?php echo $total_calificaciones - $aprobados - $rechazados; ?></div>
                <div class="stat-label">⏳ Pendientes</div>
            </div>
        </div>
        
        <!-- Tabla de calificaciones -->
        <div class="tabla-container" id="tabla-container">
            <?php echo render_tabla_calificaciones($calificaciones); ?>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        
        $('#btn-filtrar-ajax').on('click', function() {
            var convocatoria = $('#filtro-convocatoria').val();
            var coordinador = $('#filtro-coordinador').val();
            var estado = $('#filtro-estado').val();
            
            $('#filtro-loading').show();
            $('#tabla-container').html('');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'filtrar_calificaciones',
                    convocatoria: convocatoria,
                    coordinador: coordinador,
                    estado: estado
                },
                success: function(response) {
                    $('#filtro-loading').hide();
                    if (response.success) {
                        $('#tabla-container').html(response.data.html);
                        
                        // Actualizar estadísticas
                        $('#stat-total').text(response.data.total);
                        $('#stat-aprobados').text(response.data.aprobados);
                        $('#stat-rechazados').text(response.data.rechazados);
                        $('#stat-pendientes').text(response.data.total - response.data.aprobados - response.data.rechazados);
                    }
                },
                error: function() {
                    $('#filtro-loading').hide();
                    alert('Error al aplicar filtros');
                }
            });
        });
        
        // Exportar Excel
        $('#btn-excel-ajax').on('click', function() {
            var convocatoria = $('#filtro-convocatoria').val();
            var coordinador = $('#filtro-coordinador').val();
            var estado = $('#filtro-estado').val();
            
            window.location.href = '<?php echo admin_url('admin-post.php'); ?>?action=exportar_excel&convocatoria=' + convocatoria + '&coordinador=' + coordinador + '&estado=' + estado;
        });
    });
    </script>
    
    <?php
    return ob_get_clean();
}

// =============================================
// FUNCIÓN PARA OBTENER CALIFICACIONES FILTRADAS
// =============================================
function obtener_calificaciones_filtradas($convocatoria_filtro = 0, $coordinador_filtro = 0, $estado_filtro = '') {
    global $wpdb;
    $tabla_conv_doc = $wpdb->prefix . 'posts';
    $tabla_postmeta = $wpdb->prefix . 'postmeta';
    
    $sql = "SELECT 
                p.ID as documento_id,
                p.post_title as titulo_documento,
                p.post_date as fecha_carga,
                pm_conv.meta_value as convocatoria_id,
                pm_user.meta_value as profesor_id,
                pm_estado.meta_value as estado,
                pm_obs.meta_value as observaciones,
                pm_revisor.meta_value as revisor_id,
                pm_fecha.meta_value as fecha_revision
            FROM $tabla_conv_doc p
            LEFT JOIN $tabla_postmeta pm_conv ON p.ID = pm_conv.post_id AND pm_conv.meta_key = 'documento_convocatoria_id'
            LEFT JOIN $tabla_postmeta pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = 'documento_user_id'
            LEFT JOIN $tabla_postmeta pm_estado ON p.ID = pm_estado.post_id AND pm_estado.meta_key = 'documento_estado_revision'
            LEFT JOIN $tabla_postmeta pm_obs ON p.ID = pm_obs.post_id AND pm_obs.meta_key = 'documento_observaciones'
            LEFT JOIN $tabla_postmeta pm_revisor ON p.ID = pm_revisor.post_id AND pm_revisor.meta_key = 'documento_revisor_id'
            LEFT JOIN $tabla_postmeta pm_fecha ON p.ID = pm_fecha.post_id AND pm_fecha.meta_key = 'documento_fecha_revision'
            WHERE p.post_type = 'conv_doc'";
    
    $args = [];
    $where_conditions = [];
    
    if ($convocatoria_filtro) {
        $where_conditions[] = "pm_conv.meta_value = %d";
        $args[] = $convocatoria_filtro;
    }
    if ($coordinador_filtro) {
        $where_conditions[] = "pm_revisor.meta_value = %d";
        $args[] = $coordinador_filtro;
    }
    if ($estado_filtro) {
        $where_conditions[] = "pm_estado.meta_value = %s";
        $args[] = $estado_filtro;
    }
    
    if (!empty($where_conditions)) {
        $sql .= " AND " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY p.post_date DESC";
    
    if (!empty($args)) {
        return $wpdb->get_results($wpdb->prepare($sql, $args));
    } else {
        return $wpdb->get_results($sql);
    }
}

// =============================================
// FUNCIÓN PARA RENDERIZAR LA TABLA DE CALIFICACIONES
// =============================================
function render_tabla_calificaciones($calificaciones) {
    global $wpdb;
    
    // Detectar si el usuario puede asignar escalafón (Comité de Escalafón)
    $usuario_es_decanatura = current_user_can('asignar_escalafon');
    // Detectar si es administrador (puede ver pero NO editar el escalafón)
    $usuario_es_admin = current_user_can('manage_options');
    // Detectar si es auditor (solo lectura, sin acciones de escritura)
    // Tiene view_calificaciones pero NI asigna escalafón NI es admin
    $usuario_es_auditor = current_user_can('view_calificaciones') && !$usuario_es_admin && !$usuario_es_decanatura;
    // ¿La columna "Escalafón" se debe mostrar? Sí, para admin, decanatura y auditor.
    $mostrar_columna_escalafon = $usuario_es_decanatura || $usuario_es_admin || $usuario_es_auditor;
    
    // ===== BLOQUEAR ACCIONES DE ESCRITURA PARA AUDITOR =====
    // El auditor NUNCA debe poder guardar nada aunque manipule el POST
    if ($usuario_es_auditor) {
        // Forzar a false las flags de edición para que ningún form se procese
        $usuario_es_decanatura = false;
    }
    
    // Procesar guardado de escalafón si viene por POST
    if ($usuario_es_decanatura && isset($_POST['guardar_escalafon']) && isset($_POST['profesor_id']) && isset($_POST['convocatoria_id'])) {
        $profesor_id = intval($_POST['profesor_id']);
        $convocatoria_id = intval($_POST['convocatoria_id']);
        $escalafon = sanitize_text_field($_POST['escalafon']);
        $valores_permitidos = ['auxiliar', 'asistente', 'asociado', 'titular'];
        
        if (in_array($escalafon, $valores_permitidos)) {
            // ===== NUEVA VALIDACIÓN: el profesor debe tener al menos un documento calificado =====
            // Busca si existe algún documento (conv_doc) de este profesor en esta convocatoria
            // cuyo estado de revisión NO sea pendiente (es decir, ya haya sido aprobado o rechazado)
            $tiene_calificacion = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(p.ID) 
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id 
                     AND pm_user.meta_key = 'documento_user_id'
                 INNER JOIN {$wpdb->postmeta} pm_conv ON p.ID = pm_conv.post_id 
                     AND pm_conv.meta_key = 'documento_convocatoria_id'
                 INNER JOIN {$wpdb->postmeta} pm_estado ON p.ID = pm_estado.post_id 
                     AND pm_estado.meta_key = 'documento_estado_revision'
                 WHERE p.post_type = 'conv_doc'
                   AND pm_user.meta_value = %d
                   AND pm_conv.meta_value = %d
                   AND pm_estado.meta_value IN ('aprobado', 'rechazado')",
                $profesor_id, $convocatoria_id
            ));
            
            if (!$tiene_calificacion) {
                echo '<div class="notice-warning">⚠️ No se puede asignar escalafón: el profesor aún no ha sido calificado por el coordinador.</div>';
            } else {
                $tabla_escalafon = $wpdb->prefix . 'escalafon';
                $existe = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $tabla_escalafon WHERE profesor_id = %d AND convocatoria_id = %d",
                    $profesor_id, $convocatoria_id
                ));
                
                if (!$existe) {
                    $wpdb->insert($tabla_escalafon, [
                        'profesor_id' => $profesor_id,
                        'convocatoria_id' => $convocatoria_id,
                        'escalafon' => $escalafon,
                        'asignado_por' => get_current_user_id()
                    ]);
                    
                    // Disparar evento para notificación al docente
                    do_action('sged_escalafon_asignado', $profesor_id, $convocatoria_id, $escalafon);
                    
                    echo '<div class="notice-success">✅ Escalafón asignado correctamente.</div>';
                } else {
                    echo '<div class="notice-warning">⚠️ Este profesor ya tiene escalafón asignado para esta convocatoria. No se puede modificar.</div>';
                }
            }
        }
    }
    
    if (empty($calificaciones)) {
        // Tabla vacía: columnas base (11) + Escalafón (si aplica) + Acciones (si admin)
        $colspan = 11 + ($mostrar_columna_escalafon ? 1 : 0) + ($usuario_es_admin ? 1 : 0);
        return '<table class="tabla-calificaciones">
                    <thead>
                        <tr>
                            <th>Convocatoria</th>
                            <th>Grupo</th>
                            <th>Coordinadores del Grupo</th>
                            <th>Líder</th>
                            <th>Profesor</th>
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th>Calificación</th>
                            <th>Reclamos</th>
                            <th>Observaciones</th>
                            <th>Fecha revisión</th>
                            ' . ($mostrar_columna_escalafon ? '<th>Escalafón</th>' : '') . '
                            ' . ($usuario_es_admin ? '<th>Acciones</th>' : '') . '
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="' . $colspan . '" style="text-align: center; padding: 40px; color: #666;">
                                📭 No se encontraron calificaciones con los filtros seleccionados.
                            </td>
                        </tr>
                    </tbody>
                </table>';
    }
    
    ob_start();
    ?>
    <style>
        .notice-success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .notice-warning {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
    </style>
    <table class="tabla-calificaciones">
        <thead>
            <tr>
                <th>Convocatoria</th>
                <th>Grupo</th>
                <th>Coordinadores del Grupo</th>
                <th>Líder</th>
                <th>Profesor</th>
                <th>Documento</th>
                <th>Tipo</th>
                <th>Calificación</th>
                <th>Reclamos</th>
                <th>Observaciones</th>
                <th>Fecha revisión</th>
                <?php if ($mostrar_columna_escalafon): ?>
                    <th>Escalafón</th>
                <?php endif; ?>
                <?php if ($usuario_es_admin): ?>
                    <th>Acciones</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($calificaciones as $cal): 
                $convocatoria = $cal->convocatoria_id ? get_post($cal->convocatoria_id) : null;
                $nombre_convocatoria = $convocatoria ? $convocatoria->post_title : 'No disponible';
                
                $profesor = $cal->profesor_id ? get_userdata($cal->profesor_id) : null;
                $nombre_profesor = $profesor ? $profesor->display_name : 'No disponible';
                
                $coordinador = $cal->revisor_id ? get_userdata($cal->revisor_id) : null;
                $nombre_coordinador = $coordinador ? $coordinador->display_name : 'No disponible';
                
                // Obtener información del grupo
                $grupo_info = $wpdb->get_row($wpdb->prepare(
                    "SELECT grupo_nombre 
                     FROM {$wpdb->prefix}asignaciones_revision 
                     WHERE profesor_id = %d AND convocatoria_id = %d 
                     LIMIT 1",
                    $cal->profesor_id, $cal->convocatoria_id
                ));
                $nombre_grupo = $grupo_info ? $grupo_info->grupo_nombre : 'No asignado';
                
                // Obtener todos los coordinadores del grupo
                $coordinadores_grupo = $wpdb->get_results($wpdb->prepare(
                    "SELECT u.ID, u.display_name, a.es_lider 
                     FROM {$wpdb->prefix}asignaciones_revision a
                     JOIN {$wpdb->users} u ON a.coordinador_id = u.ID
                     WHERE a.profesor_id = %d AND a.convocatoria_id = %d AND a.grupo_nombre = %s",
                    $cal->profesor_id, $cal->convocatoria_id, $nombre_grupo
                ));
                
                $coordinadores_list = [];
                $nombre_lider = '';
                foreach ($coordinadores_grupo as $coord) {
                    $coordinadores_list[] = $coord->display_name . ($coord->es_lider ? ' 👑' : '');
                    if ($coord->es_lider) {
                        $nombre_lider = $coord->display_name;
                    }
                }
                $coordinadores_str = implode(', ', $coordinadores_list);
                
                // Obtener tipo de documento
                $user_doc_id = get_post_meta($cal->documento_id, 'documento_user_document_id', true);
                $tipo = get_post_meta($user_doc_id, 'user_document_type', true);
                $tipos = [
                    'resolucion' => 'Resolución'
                ];
                $tipo_mostrar = isset($tipos[$tipo]) ? $tipos[$tipo] : ($tipo ?: 'No especificado');
                
                $estado_class = 'estado-pendiente';
                $estado_texto = '⏳ Pendiente';
                if ($cal->estado == 'aprobado') {
                    $estado_class = 'estado-aprobado';
                    $estado_texto = '✅ Cumple';
                } elseif ($cal->estado == 'rechazado') {
                    $estado_class = 'estado-rechazado';
                    $estado_texto = '❌ No Cumple';
                }
                
                $fecha_revision = $cal->fecha_revision ? date('d/m/Y H:i', strtotime($cal->fecha_revision)) : '-';
                
                // Obtener escalafón actual si existe
                $escalafon_actual = '';
                $ya_tiene_escalafon = false;
                if ($mostrar_columna_escalafon) {
                    $tabla_escalafon = $wpdb->prefix . 'escalafon';
                    $escalafon_actual = $wpdb->get_var($wpdb->prepare(
                        "SELECT escalafon FROM $tabla_escalafon WHERE profesor_id = %d AND convocatoria_id = %d",
                        $cal->profesor_id, $cal->convocatoria_id
                    ));
                    $ya_tiene_escalafon = !empty($escalafon_actual);
                }
                
                // ===== INFORMACIÓN DEL RECLAMO =====
                $reclamo_texto    = get_post_meta($cal->documento_id, 'reclamo_texto', true);
                $reclamo_fecha    = get_post_meta($cal->documento_id, 'reclamo_fecha', true);
                $reclamo_resp     = get_post_meta($cal->documento_id, 'reclamo_respuesta', true);
                $reclamo_resp_dt  = get_post_meta($cal->documento_id, 'reclamo_respuesta_fecha', true);
                $reclamo_resol    = get_post_meta($cal->documento_id, 'reclamo_resolucion', true);
                $reclamo_respond  = get_post_meta($cal->documento_id, 'reclamo_respondido_por', true);
                $tiene_reclamo    = !empty($reclamo_texto);
            ?>
            <tr>
                <td><span class="badge-convocatoria"><?php echo esc_html($nombre_convocatoria); ?></span></td>
                <td><strong><?php echo esc_html($nombre_grupo); ?></strong></td>
                <td><?php echo esc_html($coordinadores_str); ?></td>
                <td><strong><?php echo esc_html($nombre_lider ?: $nombre_coordinador); ?></strong></td>
                <td><?php echo esc_html($nombre_profesor); ?></td>
                <td><strong><?php echo esc_html($cal->titulo_documento); ?></strong></td>
                <td><?php echo esc_html($tipo_mostrar); ?></td>
                <td><span class="<?php echo $estado_class; ?>"><?php echo $estado_texto; ?></span></td>
                <td class="reclamos-cell" style="text-align:center;">
                    <?php if ($tiene_reclamo): 
                        // Color según estado del reclamo
                        if (empty($reclamo_resp)) {
                            $rec_bg = '#f8d7da'; $rec_color = '#721c24'; $rec_icon = '🔴';
                        } elseif ($reclamo_resol === 'cambia_a_aprobado') {
                            $rec_bg = '#d4edda'; $rec_color = '#155724'; $rec_icon = '✅';
                        } else {
                            $rec_bg = '#f8d7da'; $rec_color = '#721c24'; $rec_icon = '❌';
                        }
                    ?>
                        <button type="button"
                                class="btn-ver-reclamo"
                                data-doc-id="<?php echo intval($cal->documento_id); ?>"
                                style="background:<?php echo $rec_bg; ?>; color:<?php echo $rec_color; ?>; border:1px solid <?php echo $rec_color; ?>33; padding:5px 12px; border-radius:20px; cursor:pointer; font-weight:600; font-size:13px;">
                            <?php echo $rec_icon; ?> 1
                        </button>
                    <?php else: ?>
                        <span style="color:#bbb;">—</span>
                    <?php endif; ?>
                </td>
                <td class="observaciones-cell"><?php echo esc_html($cal->observaciones ?: '-'); ?></td>
                <td><?php echo $fecha_revision; ?></td>
                <?php if ($mostrar_columna_escalafon): ?>
                    <td>
                        <?php if ($ya_tiene_escalafon): ?>
                            <span style="background:#e7f5ff; padding:5px 10px; border-radius:4px;">🎓 <?php echo esc_html(ucfirst($escalafon_actual)); ?></span>
                        <?php elseif (empty($cal->estado) || $cal->estado === 'pendiente'): ?>
                            <span style="color:#856404; background:#fff3cd; padding:5px 10px; border-radius:4px; font-size:12px;">
                                🔒 Pendiente de calificación
                            </span>
                        <?php elseif ($cal->estado === 'rechazado'): ?>
                            <span style="color:#721c24; background:#f8d7da; padding:5px 10px; border-radius:4px; font-size:12px;">
                                🚫 No aplica
                            </span>
                        <?php elseif ($usuario_es_decanatura): ?>
                            <!-- Solo decanatura ve el dropdown editable -->
                            <form method="post" style="display:flex; gap:5px; flex-wrap:wrap;">
                                <input type="hidden" name="profesor_id" value="<?php echo $cal->profesor_id; ?>">
                                <input type="hidden" name="convocatoria_id" value="<?php echo $cal->convocatoria_id; ?>">
                                <select name="escalafon" required>
                                    <option value="">Seleccionar</option>
                                    <option value="auxiliar">Auxiliar</option>
                                    <option value="asistente">Asistente</option>
                                    <option value="asociado">Asociado</option>
                                    <option value="titular">Titular</option>
                                </select>
                                <button type="submit" name="guardar_escalafon" class="button button-small">Guardar</button>
                            </form>
                        <?php else: ?>
                            <!-- Admin (u otros con acceso) ve mensaje informativo -->
                            <span style="color:#856404; background:#fff3cd; padding:5px 10px; border-radius:4px; font-size:12px;">
                                ⏳ Pendiente asignación Decanatura
                            </span>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
                <?php if ($usuario_es_admin): 
                    // Determinar si el botón puede mostrarse
                    $doc_tiene_escalafon = sged_documento_tiene_escalafon($cal->documento_id);
                    $doc_tiene_archivo   = !empty(get_post_meta($cal->documento_id, 'documento_user_document_id', true));
                ?>
                    <td style="text-align:center;">
                        <?php if ($doc_tiene_escalafon): ?>
                            <span title="No se puede borrar: ya tiene escalafón asignado"
                                  style="color:#bbb; cursor:not-allowed; padding:5px 10px;">
                                🔒 Bloqueado
                            </span>
                        <?php elseif (!$doc_tiene_archivo): ?>
                            <span title="Ya está sin archivo"
                                  style="color:#bbb; padding:5px 10px;">
                                —
                            </span>
                        <?php else: ?>
                            <button type="button"
                                    class="btn-borrar-doc"
                                    data-doc-id="<?php echo intval($cal->documento_id); ?>"
                                    data-profesor="<?php echo esc_attr($nombre_profesor); ?>"
                                    data-titulo="<?php echo esc_attr($cal->titulo_documento); ?>"
                                    data-estado="<?php echo esc_attr($estado_texto); ?>"
                                    data-tiene-reclamo="<?php echo $tiene_reclamo ? '1' : '0'; ?>"
                                    style="background:#dc3545; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:600;">
                                🗑️ Eliminar
                            </button>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- ===== MODAL PARA VER DETALLE DEL RECLAMO ===== -->
    <div id="sged-modal-reclamo" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white; max-width:700px; width:90%; max-height:85vh; overflow-y:auto; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
            <div style="background:#dc0c1d; color:white; padding:15px 20px; border-radius:8px 8px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:18px;">⚖️ Detalle del Reclamo</h3>
                <button type="button" id="sged-modal-cerrar" style="background:transparent; border:none; color:white; font-size:24px; cursor:pointer; line-height:1; padding:0; width:30px; height:30px;">×</button>
            </div>
            <div id="sged-modal-contenido" style="padding:20px;">
                <div style="text-align:center; color:#888; padding:30px;">⏳ Cargando...</div>
            </div>
        </div>
    </div>
    
    <?php if ($usuario_es_admin): ?>
    <!-- ===== MODAL PARA BORRAR DOCUMENTO ===== -->
    <div id="sged-modal-borrar" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white; max-width:600px; width:90%; max-height:90vh; overflow-y:auto; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
            <div style="background:#dc3545; color:white; padding:15px 20px; border-radius:8px 8px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:18px;">⚠️ Eliminar documento</h3>
                <button type="button" id="sged-modal-borrar-cerrar" style="background:transparent; border:none; color:white; font-size:24px; cursor:pointer; line-height:1; padding:0; width:30px; height:30px;">×</button>
            </div>
            <div style="padding:20px; font-size:14px; line-height:1.6;">
                
                <div id="sged-borrar-info" style="background:#f9f9f9; padding:12px 15px; border-radius:6px; margin-bottom:15px;">
                    <!-- Info llena con JS -->
                </div>
                
                <div style="background:#fff3cd; border-left:4px solid #ffc107; padding:12px 15px; border-radius:4px; margin-bottom:15px;">
                    <strong style="display:block; margin-bottom:8px;">Esta acción ELIMINARÁ:</strong>
                    <ul style="margin:0; padding-left:20px;">
                        <li>El archivo PDF del servidor</li>
                        <li>La calificación del coordinador</li>
                        <li>Las observaciones</li>
                    </ul>
                </div>
                
                <div style="background:#d1ecf1; border-left:4px solid #17a2b8; padding:12px 15px; border-radius:4px; margin-bottom:15px;">
                    <strong style="display:block; margin-bottom:8px;">Esta acción CONSERVARÁ:</strong>
                    <ul style="margin:0; padding-left:20px;">
                        <li>El reclamo del profesor (como historial)</li>
                        <li>La respuesta del coordinador al reclamo</li>
                    </ul>
                </div>
                
                <p style="margin:15px 0; color:#666; font-size:13px;">
                    El profesor podrá cargar un nuevo PDF que será calificado desde cero por el coordinador.
                    <br>
                    <strong style="color:#856404;">⚠️ El profesor NO podrá presentar otro reclamo</strong> (ya usó su única oportunidad).
                </p>
                
                <label style="display:block; margin-bottom:6px; font-weight:600;">Motivo (opcional):</label>
                <textarea id="sged-borrar-motivo"
                          rows="2"
                          maxlength="500"
                          placeholder="Razón administrativa por la que se elimina el documento..."
                          style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-family:inherit; resize:vertical; margin-bottom:15px;"></textarea>
                
                <label style="display:block; margin-bottom:6px; font-weight:600;">
                    Escribe <span style="background:#fff3cd; padding:2px 8px; border-radius:3px; font-family:monospace;">ELIMINAR</span> para confirmar:
                </label>
                <input type="text"
                       id="sged-borrar-confirmacion"
                       placeholder=""
                       autocomplete="off"
                       style="width:100%; padding:10px; border:2px solid #dc3545; border-radius:4px; font-family:monospace; font-size:14px; text-transform:uppercase;">
                
                <div id="sged-borrar-error" style="display:none; background:#f8d7da; color:#721c24; padding:10px 15px; border-radius:4px; margin-top:12px; font-size:13px;"></div>
                
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                    <button type="button" id="sged-borrar-cancelar"
                            style="background:#6c757d; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:600;">
                        Cancelar
                    </button>
                    <button type="button" id="sged-borrar-confirmar"
                            style="background:#dc3545; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:600;">
                        🗑️ Eliminar definitivamente
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    (function() {
        var modal     = document.getElementById('sged-modal-reclamo');
        var contenido = document.getElementById('sged-modal-contenido');
        var btnCerrar = document.getElementById('sged-modal-cerrar');
        
        // Función para abrir modal y cargar reclamo
        function abrirModalReclamo(docId) {
            contenido.innerHTML = '<div style="text-align:center; color:#888; padding:30px;">⏳ Cargando reclamo...</div>';
            modal.style.display = 'flex';
            
            var formData = new FormData();
            formData.append('action', 'sged_ver_reclamo');
            formData.append('doc_id', docId);
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    contenido.innerHTML = data.data.html;
                } else {
                    contenido.innerHTML = '<div style="color:#721c24; padding:15px;">❌ ' + (data.data || 'Error al cargar el reclamo') + '</div>';
                }
            })
            .catch(function() {
                contenido.innerHTML = '<div style="color:#721c24; padding:15px;">❌ Error de conexión</div>';
            });
        }
        
        // Click en botones "Ver reclamo" (también funciona con elementos cargados via AJAX por delegación)
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.btn-ver-reclamo');
            if (btn) {
                e.preventDefault();
                abrirModalReclamo(btn.getAttribute('data-doc-id'));
            }
        });
        
        // Cerrar modal
        function cerrarModal() { modal.style.display = 'none'; }
        if (btnCerrar) btnCerrar.addEventListener('click', cerrarModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) cerrarModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') cerrarModal();
        });
    })();
    </script>
    
    <?php if ($usuario_es_admin): ?>
    <!-- ===== JS DEL MODAL DE BORRADO ===== -->
    <script>
    (function() {
        var modalBorrar = document.getElementById('sged-modal-borrar');
        if (!modalBorrar) return;
        
        var infoDiv     = document.getElementById('sged-borrar-info');
        var motivoEl    = document.getElementById('sged-borrar-motivo');
        var confirmEl   = document.getElementById('sged-borrar-confirmacion');
        var errorEl     = document.getElementById('sged-borrar-error');
        var btnConfirm  = document.getElementById('sged-borrar-confirmar');
        var btnCancelar = document.getElementById('sged-borrar-cancelar');
        var btnCerrar   = document.getElementById('sged-modal-borrar-cerrar');
        var docIdActual = null;
        
        function abrirModalBorrar(btn) {
            docIdActual = btn.getAttribute('data-doc-id');
            var profesor = btn.getAttribute('data-profesor') || '';
            var titulo   = btn.getAttribute('data-titulo') || '';
            var estado   = btn.getAttribute('data-estado') || '';
            var tieneReclamo = btn.getAttribute('data-tiene-reclamo') === '1';
            
            infoDiv.innerHTML =
                '<div style="margin-bottom:6px;"><strong>👤 Profesor:</strong> ' + profesor + '</div>' +
                '<div style="margin-bottom:6px;"><strong>📄 Documento:</strong> ' + titulo + '</div>' +
                '<div style="margin-bottom:6px;"><strong>📊 Estado:</strong> ' + estado + '</div>' +
                '<div><strong>📝 Reclamo:</strong> ' + (tieneReclamo ? '✅ Sí (se conservará)' : '— No tiene') + '</div>';
            
            motivoEl.value = '';
            confirmEl.value = '';
            errorEl.style.display = 'none';
            modalBorrar.style.display = 'flex';
            setTimeout(function() { confirmEl.focus(); }, 100);
        }
        
        function cerrarModalBorrar() {
            modalBorrar.style.display = 'none';
            docIdActual = null;
        }
        
        function mostrarError(msg) {
            errorEl.textContent = '❌ ' + msg;
            errorEl.style.display = 'block';
        }
        
        function ejecutarBorrado() {
            errorEl.style.display = 'none';
            
            if (confirmEl.value.trim().toUpperCase() !== 'ELIMINAR') {
                mostrarError('Debes escribir ELIMINAR (en mayúsculas) para confirmar');
                confirmEl.focus();
                return;
            }
            
            btnConfirm.disabled = true;
            btnConfirm.textContent = '⏳ Eliminando...';
            
            var formData = new FormData();
            formData.append('action', 'sged_borrar_documento');
            formData.append('doc_id', docIdActual);
            formData.append('confirmacion', confirmEl.value);
            formData.append('motivo', motivoEl.value);
            formData.append('_wpnonce', '<?php echo wp_create_nonce('sged_borrar_documento'); ?>');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('✅ ' + data.data.msg);
                    location.reload(); // Recargar para reflejar cambios
                } else {
                    mostrarError(data.data.msg || 'Error desconocido');
                    btnConfirm.disabled = false;
                    btnConfirm.textContent = '🗑️ Eliminar definitivamente';
                }
            })
            .catch(function() {
                mostrarError('Error de conexión. Intenta de nuevo.');
                btnConfirm.disabled = false;
                btnConfirm.textContent = '🗑️ Eliminar definitivamente';
            });
        }
        
        // Delegación: click en botones .btn-borrar-doc
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.btn-borrar-doc');
            if (btn) {
                e.preventDefault();
                abrirModalBorrar(btn);
            }
        });
        
        // Cierres del modal
        btnCancelar.addEventListener('click', cerrarModalBorrar);
        btnCerrar.addEventListener('click', cerrarModalBorrar);
        btnConfirm.addEventListener('click', ejecutarBorrado);
        modalBorrar.addEventListener('click', function(e) {
            if (e.target === modalBorrar) cerrarModalBorrar();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modalBorrar.style.display === 'flex') cerrarModalBorrar();
        });
        
        // Enter en el input de confirmación dispara borrado
        confirmEl.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                ejecutarBorrado();
            }
        });
    })();
    </script>
    <?php endif; ?>
    <!-- ===== FIN MODAL ===== -->
    
    <?php
    return ob_get_clean();
}

// =============================================
// AJAX HANDLER PARA VER DETALLE DEL RECLAMO (modal)
// =============================================
add_action('wp_ajax_sged_ver_reclamo', 'sged_ajax_ver_reclamo');
function sged_ajax_ver_reclamo() {
    
    // Solo usuarios con acceso a admin-calificaciones
    if (!current_user_can('manage_options') && !current_user_can('view_calificaciones')) {
        wp_send_json_error('Sin permisos');
    }
    
    $doc_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : 0;
    if (!$doc_id) {
        wp_send_json_error('ID de documento inválido');
    }
    
    // Verificar que sea un conv_doc real
    $post = get_post($doc_id);
    if (!$post || $post->post_type !== 'conv_doc') {
        wp_send_json_error('Documento no encontrado');
    }
    
    $reclamo_texto    = get_post_meta($doc_id, 'reclamo_texto', true);
    if (empty($reclamo_texto)) {
        wp_send_json_error('Este documento no tiene reclamo presentado');
    }
    
    $reclamo_fecha    = get_post_meta($doc_id, 'reclamo_fecha', true);
    $reclamo_resp     = get_post_meta($doc_id, 'reclamo_respuesta', true);
    $reclamo_resp_dt  = get_post_meta($doc_id, 'reclamo_respuesta_fecha', true);
    $reclamo_resol    = get_post_meta($doc_id, 'reclamo_resolucion', true);
    $reclamo_respond  = get_post_meta($doc_id, 'reclamo_respondido_por', true);
    $profesor_id      = get_post_meta($doc_id, 'documento_user_id', true);
    $revisor_id       = get_post_meta($doc_id, 'documento_revisor_id', true);
    $estado           = get_post_meta($doc_id, 'documento_estado_revision', true);
    
    $profesor = $profesor_id ? get_userdata($profesor_id) : null;
    $revisor  = $revisor_id ? get_userdata($revisor_id) : null;
    $respondedor = $reclamo_respond ? get_userdata($reclamo_respond) : null;
    
    // Construir HTML del modal
    ob_start();
    ?>
    <div style="font-size:14px; line-height:1.6;">
        
        <!-- Información del documento -->
        <div style="background:#f9f9f9; padding:12px 15px; border-radius:6px; margin-bottom:15px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div><strong>📄 Documento:</strong> <?php echo esc_html($post->post_title); ?></div>
                <div><strong>👤 Profesor:</strong> <?php echo esc_html($profesor ? $profesor->display_name : '—'); ?></div>
                <div><strong>👨‍🏫 Revisor original:</strong> <?php echo esc_html($revisor ? $revisor->display_name : '—'); ?></div>
                <div><strong>📊 Estado actual:</strong>
                    <?php
                    if ($estado === 'aprobado') echo '<span style="color:#155724;">✅ Cumple</span>';
                    elseif ($estado === 'rechazado') echo '<span style="color:#721c24;">❌ No Cumple</span>';
                    else echo '<span style="color:#856404;">⏳ Pendiente</span>';
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Reclamo del profesor -->
        <div style="border-left:4px solid #dc0c1d; padding-left:15px; margin-bottom:20px;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <span style="background:#dc0c1d; color:white; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600;">
                    🔴 RECLAMO DEL PROFESOR
                </span>
                <span style="color:#888; font-size:12px;">
                    <?php echo esc_html(date('d/m/Y H:i', strtotime($reclamo_fecha))); ?>
                </span>
            </div>
            <div style="background:#fafafa; padding:12px 15px; border-radius:4px; white-space:pre-wrap; color:#333;"><?php echo esc_html($reclamo_texto); ?></div>
        </div>
        
        <!-- Respuesta del coordinador (si existe) -->
        <?php if (!empty($reclamo_resp)): ?>
            <?php
            $resol_aceptado = ($reclamo_resol === 'cambia_a_aprobado');
            $resol_bg       = $resol_aceptado ? '#d4edda' : '#f8d7da';
            $resol_color    = $resol_aceptado ? '#155724' : '#721c24';
            $resol_texto    = $resol_aceptado ? '✅ Reclamo ACEPTADO — Cambió a Cumple' : '❌ Reclamo RECHAZADO — Se mantiene No Cumple';
            ?>
            <div style="border-left:4px solid #28a745; padding-left:15px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <span style="background:#28a745; color:white; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600;">
                        ✅ RESPUESTA DEL COORDINADOR
                    </span>
                    <span style="color:#888; font-size:12px;">
                        Por <?php echo esc_html($respondedor ? $respondedor->display_name : '—'); ?> el <?php echo esc_html(date('d/m/Y H:i', strtotime($reclamo_resp_dt))); ?>
                    </span>
                </div>
                <div style="background:#fafafa; padding:12px 15px; border-radius:4px; white-space:pre-wrap; color:#333; margin-bottom:10px;"><?php echo esc_html($reclamo_resp); ?></div>
                
                <div style="background:<?php echo $resol_bg; ?>; color:<?php echo $resol_color; ?>; padding:10px 15px; border-radius:4px; font-weight:600; font-size:13px;">
                    ⚖️ Resolución final: <?php echo esc_html($resol_texto); ?>
                </div>
            </div>
        <?php else: ?>
            <div style="background:#fff3cd; color:#856404; padding:12px 15px; border-radius:4px; font-size:13px;">
                ⏳ <strong>Pendiente de respuesta.</strong>
                El coordinador (<?php echo esc_html($revisor ? $revisor->display_name : '—'); ?>) aún no ha respondido este reclamo.
            </div>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}


// =============================================
// AJAX HANDLER PARA FILTRAR CALIFICACIONES
// =============================================
add_action('wp_ajax_filtrar_calificaciones', 'ajax_filtrar_calificaciones');
function ajax_filtrar_calificaciones() {
    
    $convocatoria_filtro = isset($_POST['convocatoria']) && $_POST['convocatoria'] !== '' ? intval($_POST['convocatoria']) : 0;
    $coordinador_filtro = isset($_POST['coordinador']) && $_POST['coordinador'] !== '' ? intval($_POST['coordinador']) : 0;
    $estado_filtro = isset($_POST['estado']) && $_POST['estado'] !== '' ? sanitize_text_field($_POST['estado']) : '';
    
    $calificaciones = obtener_calificaciones_filtradas($convocatoria_filtro, $coordinador_filtro, $estado_filtro);
    
    $aprobados = 0;
    $rechazados = 0;
    foreach ($calificaciones as $cal) {
        if ($cal->estado == 'aprobado') $aprobados++;
        if ($cal->estado == 'rechazado') $rechazados++;
    }
    
    $html = render_tabla_calificaciones($calificaciones);
    
    wp_send_json_success([
        'html' => $html,
        'total' => count($calificaciones),
        'aprobados' => $aprobados,
        'rechazados' => $rechazados
    ]);
}








// =============================================
// FUNCIÓN PARA MOSTRAR DOCUMENTOS EN MODO SOLO LECTURA
// =============================================
function mostrar_documentos_solo_lectura($profesor_id, $convocatoria_id) {
    
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
        .info-header .modo-lectura {
            background: #e7f5ff;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            font-size: 13px;
            color: #0073aa;
            margin-top: 10px;
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
        .btn-ver-pdf {
            background: #0073aa;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            display: inline-block;
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
        .observaciones-text {
            background: #f9f9f9;
            padding: 8px;
            border-radius: 4px;
            max-width: 300px;
            font-size: 13px;
        }
    </style>
    
    <div class="revision-container">
        <div class="info-header">
            <h2>📋 Documentos del Profesor</h2>
            <p><strong>Profesor:</strong> <?php echo esc_html($profesor->display_name); ?> (<?php echo esc_html($profesor->user_email); ?>)</p>
            <p><strong>Convocatoria:</strong> <?php echo esc_html($convocatoria->post_title); ?></p>
            <div class="modo-lectura">
                🔍 Modo de solo lectura - No puedes calificar documentos
            </div>
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
                        'resolucion' => 'Resolución'
                    ];
                    $tipo_mostrar = $tipos[$tipo] ?? $tipo;
                    
                    $estado_texto = match($estado) {
                        'aprobado' => '✅ Cumple',
                        'rechazado' => '❌ No Cumple',
                        default => '⏳ Pendiente'
                    };
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($nombre); ?></strong><br>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" class="btn-ver-pdf">🔍 Ver PDF</a>
                    </td>
                    <td><?php echo esc_html($tipo_mostrar); ?></td>
                    <td><?php echo esc_html($anio); ?></td>
                    <td><?php echo get_the_date('d/m/Y H:i', $doc->ID); ?></td>
                    <td>
                        <span class="estado-actual estado-<?php echo $estado ?: 'pendiente'; ?>">
                            <?php echo $estado_texto; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($observaciones): ?>
                            <div class="observaciones-text">📝 <?php echo esc_html($observaciones); ?></div>
                        <?php else: ?>
                            -
                        <?php endif; ?>
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
// EXPORTAR A EXCEL (OPCIONAL)
// =============================================
add_action('admin_post_exportar_excel', 'exportar_calificaciones_excel');
add_action('admin_post_nopriv_exportar_excel', 'exportar_calificaciones_excel');
function exportar_calificaciones_excel() {
    
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para acceder a esta función.');
    }
    
    $convocatoria_filtro = isset($_GET['convocatoria']) && $_GET['convocatoria'] !== '' ? intval($_GET['convocatoria']) : 0;
    $coordinador_filtro = isset($_GET['coordinador']) && $_GET['coordinador'] !== '' ? intval($_GET['coordinador']) : 0;
    $estado_filtro = isset($_GET['estado']) && $_GET['estado'] !== '' ? sanitize_text_field($_GET['estado']) : '';
    
    $calificaciones = obtener_calificaciones_filtradas($convocatoria_filtro, $coordinador_filtro, $estado_filtro);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=calificaciones_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Encabezados
    fputcsv($output, ['Convocatoria', 'Coordinador', 'Profesor', 'Documento', 'Tipo', 'Calificación', 'Observaciones', 'Fecha Revisión']);
    
    foreach ($calificaciones as $cal) {
        $convocatoria = $cal->convocatoria_id ? get_post($cal->convocatoria_id) : null;
        $nombre_convocatoria = $convocatoria ? $convocatoria->post_title : 'No disponible';
        
        $profesor = $cal->profesor_id ? get_userdata($cal->profesor_id) : null;
        $nombre_profesor = $profesor ? $profesor->display_name : 'No disponible';
        
        $coordinador = $cal->revisor_id ? get_userdata($cal->revisor_id) : null;
        $nombre_coordinador = $coordinador ? $coordinador->display_name : 'No disponible';
        
        $user_doc_id = get_post_meta($cal->documento_id, 'documento_user_document_id', true);
        $tipo = get_post_meta($user_doc_id, 'user_document_type', true);
        $tipos = [
            'resolucion' => 'Resolución'
        ];
        $tipo_mostrar = isset($tipos[$tipo]) ? $tipos[$tipo] : ($tipo ?: 'No especificado');
        
        $estado_texto = $cal->estado == 'aprobado' ? 'Cumple' : ($cal->estado == 'rechazado' ? 'No Cumple' : 'Pendiente');
        $fecha_revision = $cal->fecha_revision ? date('d/m/Y H:i', strtotime($cal->fecha_revision)) : '-';
        
        fputcsv($output, [
            $nombre_convocatoria,
            $nombre_coordinador,
            $nombre_profesor,
            $cal->titulo_documento,
            $tipo_mostrar,
            $estado_texto,
            $cal->observaciones,
            $fecha_revision
        ]);
    }
    
    fclose($output);
    exit;
}