<?php
/**
 * ============================================================
 * Sistema de Notificaciones por Correo - SGED Bellas Artes
 * ============================================================
 *
 * Envía notificaciones automáticas en 3 momentos clave:
 *   1. Cuando un profesor carga un PDF en una convocatoria
 *   2. Cuando un coordinador califica el documento
 *   3. Cuando Decanatura asigna el escalafón
 *
 * Requiere: WP Mail SMTP u otro plugin SMTP configurado.
 *
 * @package SGED_BellasArtes
 * @since   1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================
// CONFIGURACIÓN GLOBAL DEL MÓDULO
// ============================================================

/**
 * Activa o desactiva globalmente todas las notificaciones.
 * Útil en entornos de desarrollo o si hay problemas con el webhook.
 */
if (!defined('SGED_NOTIFICACIONES_ACTIVAS')) {
    define('SGED_NOTIFICACIONES_ACTIVAS', true);
}

/**
 * URL del webhook de Google Apps Script.
 * Lo obtuviste al publicar el Web App en script.google.com.
 *
 * Ejemplo: https://script.google.com/macros/s/AKfyc.../exec
 */
if (!defined('SGED_WEBHOOK_URL')) {
    define('SGED_WEBHOOK_URL', 'https://script.google.com/macros/s/AKfycbwUhAypDTPRjIhPaG4rZMLfxQAV13lYBSQKXeAeeMGhu0Q4H51LMQBa4yUR0yRAeubg/exec');
}

/**
 * Token secreto compartido con el Apps Script.
 * Debe ser EXACTAMENTE el mismo valor que pusiste en el archivo .gs
 * en la variable TOKEN_SECRETO.
 */
if (!defined('SGED_WEBHOOK_TOKEN')) {
    define('SGED_WEBHOOK_TOKEN', '1a4d4e69-317d-483c-aa59-bc32567cf44b');
}

/**
 * Email visible como remitente en los correos.
 * IMPORTANTE: con Apps Script este valor es solo informativo —
 * el remitente real lo decide Google (la cuenta del Apps Script).
 */
if (!defined('SGED_EMAIL_FROM')) {
    define('SGED_EMAIL_FROM', 'andresgomz9404@gmail.com');
}
if (!defined('SGED_EMAIL_FROM_NAME')) {
    define('SGED_EMAIL_FROM_NAME', 'Sistema de Escalafón - Bellas Artes IUV');
}


// ============================================================
// HELPERS CORE: ENVÍO VÍA WEBHOOK Y PLANTILLAS
// ============================================================

/**
 * Envía una notificación usando la plantilla institucional vía webhook
 * de Google Apps Script (en lugar de wp_mail).
 *
 * @param string $destinatario Email del destinatario
 * @param string $asunto       Asunto del correo
 * @param string $titulo       Título principal que verá el usuario
 * @param string $contenido    HTML del cuerpo del mensaje
 * @param string $cta_texto    Texto del botón de acción (ej: "Ver mis documentos")
 * @param string $cta_url      URL del botón de acción
 * @param string $tipo         Tipo de notificación: carga|calificacion|escalafon|reclamo|test
 * @return bool true si se envió, false si falló
 */
function sged_enviar_notificacion($destinatario, $asunto, $titulo, $contenido, $cta_texto = '', $cta_url = '', $tipo = 'general') {

    // Respeta el flag global
    if (!SGED_NOTIFICACIONES_ACTIVAS) {
        return false;
    }

    // Validaciones básicas
    if (empty($destinatario) || !is_email($destinatario)) {
        error_log('[SGED] Email inválido: ' . print_r($destinatario, true));
        return false;
    }
    
    // Verificar que el webhook esté configurado correctamente
    // (verifica que sea una URL válida del Apps Script de Google)
    if (empty(SGED_WEBHOOK_URL) || strpos(SGED_WEBHOOK_URL, 'script.google.com') === false) {
        error_log('[SGED] Webhook URL no configurada o inválida. Debe ser una URL de script.google.com');
        return false;
    }
    // Verificar que el token tenga al menos 16 caracteres (formato UUID o similar)
    if (empty(SGED_WEBHOOK_TOKEN) || strlen(SGED_WEBHOOK_TOKEN) < 16) {
        error_log('[SGED] Webhook TOKEN inválido o demasiado corto.');
        return false;
    }

    $html = sged_template_email_html($titulo, $contenido, $cta_texto, $cta_url);

    // Enviar al webhook de Google Apps Script
    $response = wp_remote_post(SGED_WEBHOOK_URL, [
        'method'      => 'POST',
        'timeout'     => 15,
        'redirection' => 5,
        'headers'     => [
            'Content-Type' => 'application/json',
        ],
        'body'        => json_encode([
            'token'          => SGED_WEBHOOK_TOKEN,
            'destinatario'   => $destinatario,
            'asunto'         => $asunto,
            'contenido_html' => $html,
            'tipo'           => $tipo,
        ]),
        'data_format' => 'body',
    ]);
    
    // Verificar respuesta
    $enviado = false;
    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SGED] Error webhook: ' . $response->get_error_message());
        }
    } else {
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 200 && isset($data['success']) && $data['success']) {
            $enviado = true;
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SGED] Webhook respondió ' . $code . ': ' . $body);
            }
        }
    }

    // Log para auditoría (solo si WP_DEBUG está activo)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            '[SGED] Notificación %s → %s | Asunto: %s | Tipo: %s',
            $enviado ? 'ENVIADA' : 'FALLIDA',
            $destinatario,
            $asunto,
            $tipo
        ));
    }

    return $enviado;
}


/**
 * Plantilla HTML institucional reutilizable para todos los correos.
 * Renderiza un email con logo, título, contenido y botón CTA opcional.
 *
 * @param string $titulo    Título principal
 * @param string $contenido HTML del cuerpo
 * @param string $cta_texto Texto del botón (vacío = no se muestra)
 * @param string $cta_url   URL del botón
 * @return string HTML completo listo para enviar
 */
function sged_template_email_html($titulo, $contenido, $cta_texto = '', $cta_url = '') {

    $logo_url      = get_template_directory_uri() . '/assets/images/ba_logo_blanco.png';
    $sitio_url     = home_url();
    $sitio_nombre  = get_bloginfo('name');
    $anio_actual   = date('Y');

    // Botón CTA (solo si se pasan ambos parámetros)
    $boton_cta = '';
    if (!empty($cta_texto) && !empty($cta_url)) {
        $boton_cta = '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 25px auto;">
            <tr>
                <td style="background-color: #dc0c1d; border-radius: 6px;" align="center">
                    <a href="' . esc_url($cta_url) . '"
                       style="display: inline-block; padding: 14px 32px; color: #ffffff;
                              text-decoration: none; font-weight: 600; font-size: 15px;
                              font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
                        ' . esc_html($cta_texto) . '
                    </a>
                </td>
            </tr>
        </table>';
    }

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($titulo) . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5;
             font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
           style="background-color: #f5f5f5; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600"
                       style="max-width: 600px; background-color: #ffffff; border-radius: 8px;
                              box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden;">

                    <!-- HEADER con logo -->
                    <tr>
                        <td style="background-color: #dc0c1d; padding: 25px 30px; text-align: center;">
                            <img src="' . esc_url($logo_url) . '"
                                 alt="Bellas Artes IUV"
                                 style="max-height: 50px; width: auto;" />
                        </td>
                    </tr>

                    <!-- TÍTULO -->
                    <tr>
                        <td style="padding: 30px 30px 10px 30px;">
                            <h1 style="margin: 0; color: #222; font-size: 22px; font-weight: 600;">
                                ' . esc_html($titulo) . '
                            </h1>
                        </td>
                    </tr>

                    <!-- CONTENIDO -->
                    <tr>
                        <td style="padding: 10px 30px 20px 30px; color: #444; font-size: 15px; line-height: 1.6;">
                            ' . $contenido . '
                        </td>
                    </tr>

                    <!-- BOTÓN CTA -->
                    <tr>
                        <td style="padding: 0 30px 20px 30px;">
                            ' . $boton_cta . '
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background-color: #f9f9f9; padding: 20px 30px;
                                   border-top: 1px solid #eee; text-align: center;
                                   color: #888; font-size: 12px; line-height: 1.5;">
                            <strong>Sistema de Gestión de Escalafón</strong><br>
                            Bellas Artes - Institución Universitaria del Valle<br><br>
                            Este es un mensaje automático, por favor no responda a este correo.<br>
                            Si tiene dudas, contacte con el administrador del sistema.<br><br>
                            <a href="' . esc_url($sitio_url) . '" style="color: #dc0c1d; text-decoration: none;">
                                ' . esc_html($sitio_nombre) . '
                            </a>
                            &nbsp;·&nbsp; © ' . $anio_actual . '
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>';

    return $html;
}


// ============================================================
// EVENTO 1: PROFESOR CARGA UN PDF
// ============================================================

/**
 * Hook: Se dispara cuando un docente carga un PDF.
 * Usa un hook custom 'sged_documento_cargado' que se dispara
 * desde frontend-shortcodes.php tanto en cargas nuevas como
 * cuando un docente sube un PDF tras borrado por admin.
 */
add_action('sged_documento_cargado', 'sged_enviar_notificacion_carga', 10, 1);

// Mantener también el hook original como fallback (por si alguna ruta no usa el custom)
add_action('wp_insert_post', 'sged_notificar_carga_documento_legacy', 20, 3);

function sged_notificar_carga_documento_legacy($post_id, $post, $update) {

    // Solo en creación (no en actualizaciones), y solo para conv_doc
    if ($update) return;
    if ($post->post_type !== 'conv_doc') return;
    
    // Si este conv_doc ya tiene 'documento_borrado_por' significa que es
    // una re-carga sobre un conv_doc reutilizado → el hook custom ya lo manejará.
    // En este caso (creación nueva), también disparamos.
    
    // Anti-duplicado: verificar que no se haya notificado ya para este doc
    $ya_notificado = get_post_meta($post_id, 'notificacion_carga_enviada', true);
    if ($ya_notificado === '1') return;
    
    // Esperar a que se guarden todos los metadatos
    add_action('shutdown', function() use ($post_id) {
        sged_enviar_notificacion_carga($post_id);
    });
}

/**
 * Construye y envía el correo de confirmación de carga.
 */
function sged_enviar_notificacion_carga($doc_conv_id) {

    // Recuperar metadatos
    $profesor_id     = get_post_meta($doc_conv_id, 'documento_user_id', true);
    $convocatoria_id = get_post_meta($doc_conv_id, 'documento_convocatoria_id', true);
    $user_doc_id     = get_post_meta($doc_conv_id, 'documento_user_document_id', true);
    $fecha_subida    = get_post_meta($doc_conv_id, 'documento_fecha_subida', true);

    if (!$profesor_id || !$convocatoria_id) {
        return;
    }

    // Datos del profesor
    $profesor = get_userdata($profesor_id);
    if (!$profesor || empty($profesor->user_email)) {
        return;
    }

    // Datos del documento
    $nombre_documento = $user_doc_id ? get_post_meta($user_doc_id, 'user_document_name', true) : '';
    $tipo_documento   = $user_doc_id ? get_post_meta($user_doc_id, 'user_document_type', true) : '';
    
    // ===== Obtener URL directa del PDF cargado =====
    $url_pdf = '';
    if ($user_doc_id) {
        $attachment_id = get_post_meta($user_doc_id, 'user_document_location', true);
        if ($attachment_id) {
            $url_pdf = wp_get_attachment_url($attachment_id);
        }
    }

    // Datos de la convocatoria
    $convocatoria        = get_post($convocatoria_id);
    $nombre_convocatoria = $convocatoria ? $convocatoria->post_title : 'No disponible';

    // Formatear fecha
    $fecha_formateada = $fecha_subida
        ? date('d/m/Y H:i', strtotime($fecha_subida))
        : date('d/m/Y H:i');

    // Nombre mostrado (first_name + last_name, o display_name)
    $nombre_profesor = trim(
        get_user_meta($profesor_id, 'first_name', true) . ' ' .
        get_user_meta($profesor_id, 'last_name', true)
    );
    if (empty($nombre_profesor)) {
        $nombre_profesor = $profesor->display_name;
    }

    // Construir contenido del correo
    $contenido = '
        <p>Hola <strong>' . esc_html($nombre_profesor) . '</strong>,</p>

        <p>Hemos recibido correctamente tu documento en el Sistema de Gestión de Escalafón.
        A continuación los detalles de la carga:</p>

        <table role="presentation" cellspacing="0" cellpadding="10" border="0" width="100%"
               style="background-color: #f9f9f9; border-radius: 6px; margin: 15px 0;">
            <tr>
                <td style="padding: 8px 15px;"><strong>📌 Convocatoria:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($nombre_convocatoria) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px;"><strong>📄 Documento:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($nombre_documento ?: 'Sin nombre') . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px;"><strong>📅 Fecha de carga:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($fecha_formateada) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px;"><strong>📊 Estado actual:</strong></td>
                <td style="padding: 8px 15px;">
                    <span style="background:#fff3cd; color:#856404; padding:4px 10px; border-radius:4px; font-weight:600;">
                        ⏳ Pendiente de revisión
                    </span>
                </td>
            </tr>
        </table>

        <p>Tu documento será revisado por el coordinador asignado.
        Recibirás una nueva notificación cuando haya novedades sobre la revisión.</p>

        <p>Puedes consultar el archivo que cargaste haciendo clic en el botón inferior.</p>
    ';

    // Si hay URL del PDF, ese es el CTA; si no, redirige al sistema
    if (!empty($url_pdf)) {
        $cta_texto = '📄 Ver documento cargado';
        $cta_url   = $url_pdf;
    } else {
        $cta_texto = '📁 Ver mis documentos';
        $cta_url   = home_url('/mis-archivos-cargados/?conv=' . $convocatoria_id);
    }

    $enviado = sged_enviar_notificacion(
        $profesor->user_email,
        '✅ Documento recibido - ' . $nombre_convocatoria,
        'Documento cargado correctamente',
        $contenido,
        $cta_texto,
        $cta_url,
        'carga'
    );
    
    // Marcar como notificado para evitar duplicados si el hook se llama varias veces
    // Se borra cuando se reutiliza el conv_doc tras borrado por admin (ver frontend-shortcodes)
    if ($enviado) {
        update_post_meta($doc_conv_id, 'notificacion_carga_enviada', '1');
        update_post_meta($doc_conv_id, 'notificacion_carga_fecha',   current_time('mysql'));
    }
}


// ============================================================
// EVENTO 2: COORDINADOR CALIFICA EL DOCUMENTO
// ============================================================

/**
 * Hook: Se dispara cuando se actualiza el meta 'documento_estado_revision'.
 * Notifica al profesor del resultado de la revisión.
 */
add_action('updated_post_meta', 'sged_notificar_calificacion', 20, 4);
add_action('added_post_meta',   'sged_notificar_calificacion', 20, 4);

function sged_notificar_calificacion($meta_id, $doc_conv_id, $meta_key, $meta_value) {

    // Solo nos interesa el meta de estado de revisión
    if ($meta_key !== 'documento_estado_revision') return;

    // Solo cuando se establece un estado definitivo (no "pendiente")
    if (!in_array($meta_value, ['aprobado', 'rechazado'], true)) return;

    // Verificar que sea un conv_doc
    $post = get_post($doc_conv_id);
    if (!$post || $post->post_type !== 'conv_doc') return;

    // Prevenir envíos duplicados: marcar como "ya notificado"
    // (así si por alguna razón el coordinador cambia la calificación, no se re-envía)
    $ya_notificado = get_post_meta($doc_conv_id, 'notificacion_calificacion_enviada', true);
    if ($ya_notificado === '1') return;

    // Enviar en shutdown para asegurar que todos los metadatos estén guardados
    add_action('shutdown', function() use ($doc_conv_id) {
        sged_enviar_notificacion_calificacion($doc_conv_id);
    });
}

/**
 * Construye y envía el correo de calificación al profesor.
 */
function sged_enviar_notificacion_calificacion($doc_conv_id) {

    // Recuperar metadatos actualizados
    $profesor_id     = get_post_meta($doc_conv_id, 'documento_user_id', true);
    $convocatoria_id = get_post_meta($doc_conv_id, 'documento_convocatoria_id', true);
    $user_doc_id     = get_post_meta($doc_conv_id, 'documento_user_document_id', true);
    $estado          = get_post_meta($doc_conv_id, 'documento_estado_revision', true);
    $observaciones   = get_post_meta($doc_conv_id, 'documento_observaciones', true);
    $revisor_id      = get_post_meta($doc_conv_id, 'documento_revisor_id', true);
    $fecha_revision  = get_post_meta($doc_conv_id, 'documento_fecha_revision', true);

    if (!$profesor_id || !$convocatoria_id || !$estado) {
        return;
    }

    $profesor = get_userdata($profesor_id);
    if (!$profesor || empty($profesor->user_email)) {
        return;
    }

    // Datos complementarios
    $nombre_documento    = $user_doc_id ? get_post_meta($user_doc_id, 'user_document_name', true) : '';
    $convocatoria        = get_post($convocatoria_id);
    $nombre_convocatoria = $convocatoria ? $convocatoria->post_title : 'No disponible';
    $revisor             = $revisor_id ? get_userdata($revisor_id) : null;
    $nombre_revisor      = $revisor ? $revisor->display_name : 'Coordinador';

    $fecha_formateada = $fecha_revision
        ? date('d/m/Y H:i', strtotime($fecha_revision))
        : date('d/m/Y H:i');

    $nombre_profesor = trim(
        get_user_meta($profesor_id, 'first_name', true) . ' ' .
        get_user_meta($profesor_id, 'last_name', true)
    );
    if (empty($nombre_profesor)) {
        $nombre_profesor = $profesor->display_name;
    }

    // Personalización según resultado
    if ($estado === 'aprobado') {
        $emoji          = '✅';
        $titulo         = 'Tu documento fue aprobado';
        $asunto         = '✅ Documento aprobado - ' . $nombre_convocatoria;
        $color_fondo    = '#d4edda';
        $color_texto    = '#155724';
        $estado_texto   = '✅ Cumple con los requisitos';
        $mensaje_extra  = 'Felicitaciones, tu documento cumple con los requisitos establecidos para la convocatoria.';
    } else { // rechazado
        $emoji          = '❌';
        $titulo         = 'Tu documento no fue aprobado';
        $asunto         = '❌ Documento no aprobado - ' . $nombre_convocatoria;
        $color_fondo    = '#f8d7da';
        $color_texto    = '#721c24';
        $estado_texto   = '❌ No cumple con los requisitos';
        $mensaje_extra  = 'Tu documento no cumple con los requisitos establecidos. Revisa las observaciones del coordinador.';
    }

    // Observaciones (solo si existen)
    $html_observaciones = '';
    if (!empty($observaciones)) {
        $html_observaciones = '
            <div style="background:#fff3cd; border-left:4px solid #ffc107; padding:15px; margin:15px 0; border-radius:4px;">
                <strong>📝 Observaciones del coordinador:</strong><br>
                <em>' . nl2br(esc_html($observaciones)) . '</em>
            </div>';
    }

    // Construir contenido
    $contenido = '
        <p>Hola <strong>' . esc_html($nombre_profesor) . '</strong>,</p>

        <p>Tu documento presentado en la convocatoria <strong>' . esc_html($nombre_convocatoria) . '</strong>
        ha sido revisado.</p>

        <table role="presentation" cellspacing="0" cellpadding="10" border="0" width="100%"
               style="background-color: #f9f9f9; border-radius: 6px; margin: 15px 0;">
            <tr>
                <td style="padding: 8px 15px;"><strong>📄 Documento:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($nombre_documento ?: 'Sin nombre') . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px;"><strong>👤 Revisado por:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($nombre_revisor) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px;"><strong>📅 Fecha de revisión:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($fecha_formateada) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px;"><strong>📊 Resultado:</strong></td>
                <td style="padding: 8px 15px;">
                    <span style="background:' . $color_fondo . '; color:' . $color_texto . ';
                                  padding:4px 10px; border-radius:4px; font-weight:600;">
                        ' . esc_html($estado_texto) . '
                    </span>
                </td>
            </tr>
        </table>

        ' . $html_observaciones . '

        <p>' . esc_html($mensaje_extra) . '</p>
    ';

    $url_ver = home_url('/mis-archivos-cargados/?conv=' . $convocatoria_id);

    $enviado = sged_enviar_notificacion(
        $profesor->user_email,
        $asunto,
        $titulo,
        $contenido,
        '📁 Ver mis documentos',
        $url_ver,
        'calificacion'
    );

    // Marcar como notificado para evitar duplicados
    if ($enviado) {
        update_post_meta($doc_conv_id, 'notificacion_calificacion_enviada', '1');
        update_post_meta($doc_conv_id, 'notificacion_calificacion_fecha',   current_time('mysql'));
    }
}


// ============================================================
// EVENTO 3: DOCENTE PRESENTA UN RECLAMO
// Hook custom: do_action('sged_reclamo_presentado', $doc_conv_id);
// Disparado desde frontend-shortcodes.php cuando se procesa un reclamo.
// Destinatarios: TODOS los coordinadores del grupo asignados al docente.
// ============================================================

add_action('sged_reclamo_presentado', 'sged_notificar_reclamo_presentado', 10, 1);

function sged_notificar_reclamo_presentado($doc_conv_id) {
    global $wpdb;
    
    if (!$doc_conv_id) return;
    
    // Recuperar metadatos del reclamo y documento
    $profesor_id     = get_post_meta($doc_conv_id, 'documento_user_id', true);
    $convocatoria_id = get_post_meta($doc_conv_id, 'documento_convocatoria_id', true);
    $user_doc_id     = get_post_meta($doc_conv_id, 'documento_user_document_id', true);
    $reclamo_texto   = get_post_meta($doc_conv_id, 'reclamo_texto', true);
    $reclamo_fecha   = get_post_meta($doc_conv_id, 'reclamo_fecha', true);
    
    if (!$profesor_id || !$convocatoria_id || empty($reclamo_texto)) {
        return;
    }
    
    $profesor = get_userdata($profesor_id);
    if (!$profesor) return;
    
    $convocatoria        = get_post($convocatoria_id);
    $nombre_convocatoria = $convocatoria ? $convocatoria->post_title : 'No disponible';
    $nombre_documento    = $user_doc_id ? get_post_meta($user_doc_id, 'user_document_name', true) : 'Documento';
    
    // ===== Obtener TODOS los coordinadores del grupo del docente =====
    // Usamos la tabla wp_asignaciones_revision para encontrar a los coordinadores
    // asignados al mismo grupo del docente en esta convocatoria.
    $tabla_asignaciones = $wpdb->prefix . 'asignaciones_revision';
    
    // Primero: obtener el grupo del docente
    $grupo_docente = $wpdb->get_var($wpdb->prepare(
        "SELECT grupo_nombre FROM $tabla_asignaciones 
         WHERE profesor_id = %d AND convocatoria_id = %d
         LIMIT 1",
        $profesor_id, $convocatoria_id
    ));
    
    if (empty($grupo_docente)) {
        // No hay grupo asignado, no podemos notificar
        return;
    }
    
    // Ahora: obtener todos los coordinadores asignados a ese grupo
    $coordinadores_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT coordinador_id FROM $tabla_asignaciones 
         WHERE grupo_nombre = %s AND convocatoria_id = %d",
        $grupo_docente, $convocatoria_id
    ));
    
    if (empty($coordinadores_ids)) {
        return;
    }
    
    // Nombre del docente para el correo
    $nombre_profesor = trim(
        get_user_meta($profesor_id, 'first_name', true) . ' ' .
        get_user_meta($profesor_id, 'last_name', true)
    );
    if (empty($nombre_profesor)) {
        $nombre_profesor = $profesor->display_name;
    }
    
    $fecha_formateada = $reclamo_fecha
        ? date('d/m/Y H:i', strtotime($reclamo_fecha))
        : date('d/m/Y H:i');
    
    // URL para que el coordinador vaya a responder
    $url_responder = home_url('/mis-profesores/?conv=' . $convocatoria_id);
    
    // Enviar correo a CADA coordinador
    foreach ($coordinadores_ids as $coord_id) {
        $coord = get_userdata($coord_id);
        if (!$coord || empty($coord->user_email)) continue;
        
        $nombre_coord = trim(
            get_user_meta($coord_id, 'first_name', true) . ' ' .
            get_user_meta($coord_id, 'last_name', true)
        );
        if (empty($nombre_coord)) {
            $nombre_coord = $coord->display_name;
        }
        
        $contenido = '
            <p>Hola <strong>' . esc_html($nombre_coord) . '</strong>,</p>

            <p>El docente <strong>' . esc_html($nombre_profesor) . '</strong> presentó un
            <strong style="color:#dc0c1d;">reclamo</strong> sobre la calificación de un documento
            del grupo <strong>' . esc_html($grupo_docente) . '</strong>.</p>

            <table role="presentation" cellspacing="0" cellpadding="10" border="0" width="100%"
                   style="background-color: #f9f9f9; border-radius: 6px; margin: 15px 0;">
                <tr>
                    <td style="padding: 8px 15px;"><strong>📌 Convocatoria:</strong></td>
                    <td style="padding: 8px 15px;">' . esc_html($nombre_convocatoria) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 15px;"><strong>👥 Grupo:</strong></td>
                    <td style="padding: 8px 15px;">' . esc_html($grupo_docente) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 15px;"><strong>👤 Docente:</strong></td>
                    <td style="padding: 8px 15px;">' . esc_html($nombre_profesor) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 15px;"><strong>📄 Documento:</strong></td>
                    <td style="padding: 8px 15px;">' . esc_html($nombre_documento) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 15px;"><strong>📅 Fecha del reclamo:</strong></td>
                    <td style="padding: 8px 15px;">' . esc_html($fecha_formateada) . '</td>
                </tr>
            </table>

            <div style="background:#fff3cd; border-left:4px solid #dc0c1d; padding:15px; margin:15px 0; border-radius:4px;">
                <strong>📝 Argumento del docente:</strong><br>
                <em>' . nl2br(esc_html($reclamo_texto)) . '</em>
            </div>

            <p>Por favor revisa el reclamo y responde lo antes posible desde el sistema.
            El docente está esperando tu respuesta.</p>
        ';
        
        sged_enviar_notificacion(
            $coord->user_email,
            '🔴 Reclamo presentado - ' . $nombre_convocatoria,
            'Un docente presentó un reclamo',
            $contenido,
            '⚖️ Responder al reclamo',
            $url_responder,
            'reclamo_presentado'
        );
    }
}


// ============================================================
// EVENTO 4: EVALUADOR ACADÉMICO RESPONDE EL RECLAMO
// Hook custom: do_action('sged_reclamo_respondido', $doc_conv_id);
// Disparado desde frontend-shortcodes.php cuando coord responde reclamo.
// Destinatario: el docente que presentó el reclamo.
// ============================================================

add_action('sged_reclamo_respondido', 'sged_notificar_reclamo_respondido', 10, 1);

function sged_notificar_reclamo_respondido($doc_conv_id) {
    
    if (!$doc_conv_id) return;
    
    $profesor_id        = get_post_meta($doc_conv_id, 'documento_user_id', true);
    $convocatoria_id    = get_post_meta($doc_conv_id, 'documento_convocatoria_id', true);
    $user_doc_id        = get_post_meta($doc_conv_id, 'documento_user_document_id', true);
    $reclamo_respuesta  = get_post_meta($doc_conv_id, 'reclamo_respuesta', true);
    $reclamo_resolucion = get_post_meta($doc_conv_id, 'reclamo_resolucion', true);
    $reclamo_resp_dt    = get_post_meta($doc_conv_id, 'reclamo_respuesta_fecha', true);
    $reclamo_respond_by = get_post_meta($doc_conv_id, 'reclamo_respondido_por', true);
    
    if (!$profesor_id || empty($reclamo_respuesta)) return;
    
    $profesor = get_userdata($profesor_id);
    if (!$profesor || empty($profesor->user_email)) return;
    
    $convocatoria        = get_post($convocatoria_id);
    $nombre_convocatoria = $convocatoria ? $convocatoria->post_title : 'No disponible';
    $nombre_documento    = $user_doc_id ? get_post_meta($user_doc_id, 'user_document_name', true) : 'Documento';
    
    $coord = $reclamo_respond_by ? get_userdata($reclamo_respond_by) : null;
    $nombre_coord = $coord ? $coord->display_name : 'Evaluador Académico';
    
    $nombre_profesor = trim(
        get_user_meta($profesor_id, 'first_name', true) . ' ' .
        get_user_meta($profesor_id, 'last_name', true)
    );
    if (empty($nombre_profesor)) {
        $nombre_profesor = $profesor->display_name;
    }
    
    $fecha_formateada = $reclamo_resp_dt
        ? date('d/m/Y H:i', strtotime($reclamo_resp_dt))
        : date('d/m/Y H:i');
    
    // Diferenciar visual según resolución
    if ($reclamo_resolucion === 'cambia_a_aprobado') {
        $titulo        = 'Tu reclamo fue ACEPTADO';
        $asunto        = '✅ Reclamo aceptado - ' . $nombre_convocatoria;
        $resol_bg      = '#d4edda';
        $resol_color   = '#155724';
        $resol_texto   = '✅ Reclamo ACEPTADO — Tu documento cambió a "Cumple"';
        $mensaje_extra = 'Felicitaciones, tu argumento fue aceptado y tu documento ahora figura como "Cumple" con los requisitos.';
    } else {
        $titulo        = 'Respuesta a tu reclamo';
        $asunto        = '⚖️ Respuesta a tu reclamo - ' . $nombre_convocatoria;
        $resol_bg      = '#f8d7da';
        $resol_color   = '#721c24';
        $resol_texto   = '❌ Reclamo RECHAZADO — Se mantiene "No Cumple"';
        $mensaje_extra = 'El Evaluador Académico analizó tu argumento pero decidió mantener la calificación original. Esta es la decisión final.';
    }
    
    $contenido = '
        <p>Hola <strong>' . esc_html($nombre_profesor) . '</strong>,</p>

        <p>El Evaluador Académico <strong>' . esc_html($nombre_coord) . '</strong> respondió
        a tu reclamo del documento <strong>' . esc_html($nombre_documento) . '</strong> en la
        convocatoria <strong>' . esc_html($nombre_convocatoria) . '</strong>.</p>

        <table role="presentation" cellspacing="0" cellpadding="10" border="0" width="100%"
               style="background-color: #f9f9f9; border-radius: 6px; margin: 15px 0;">
            <tr>
                <td style="padding: 8px 15px;"><strong>👤 Respondido por:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($nombre_coord) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px;"><strong>📅 Fecha:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($fecha_formateada) . '</td>
            </tr>
        </table>

        <div style="background:#fff3cd; border-left:4px solid #28a745; padding:15px; margin:15px 0; border-radius:4px;">
            <strong>💬 Respuesta del Evaluador Académico:</strong><br>
            <em>' . nl2br(esc_html($reclamo_respuesta)) . '</em>
        </div>

        <div style="background:' . $resol_bg . '; color:' . $resol_color . ';
                    padding:15px; border-radius:6px; text-align:center; font-weight:600;
                    margin:15px 0; font-size:15px;">
            ⚖️ ' . esc_html($resol_texto) . '
        </div>

        <p>' . esc_html($mensaje_extra) . '</p>
    ';
    
    sged_enviar_notificacion(
        $profesor->user_email,
        $asunto,
        $titulo,
        $contenido,
        '📁 Ver mis documentos',
        home_url('/mis-archivos-cargados/?conv=' . $convocatoria_id),
        'reclamo_respondido'
    );
}


// ============================================================
// EVENTO 5: COMITÉ DE ESCALAFÓN ASIGNA ESCALAFÓN
// ============================================================

/**
 * Hook custom: lo disparamos manualmente desde frontend-shortcodes.php
 * justo después de $wpdb->insert en la tabla wp_escalafon.
 *
 * Uso:
 *   do_action('sged_escalafon_asignado', $profesor_id, $convocatoria_id, $escalafon);
 */
add_action('sged_escalafon_asignado', 'sged_notificar_escalafon_asignado', 10, 3);

function sged_notificar_escalafon_asignado($profesor_id, $convocatoria_id, $escalafon) {

    if (!$profesor_id || !$convocatoria_id || !$escalafon) {
        return;
    }

    $profesor = get_userdata($profesor_id);
    if (!$profesor || empty($profesor->user_email)) {
        return;
    }

    // Datos de la convocatoria
    $convocatoria        = get_post($convocatoria_id);
    $nombre_convocatoria = $convocatoria ? $convocatoria->post_title : 'No disponible';

    // Descripción amigable de cada escalafón
    $descripciones = [
        'auxiliar'  => 'Profesor Auxiliar',
        'asistente' => 'Profesor Asistente',
        'asociado'  => 'Profesor Asociado',
        'titular'   => 'Profesor Titular',
    ];
    $escalafon_lower    = strtolower($escalafon);
    $escalafon_display  = isset($descripciones[$escalafon_lower])
        ? $descripciones[$escalafon_lower]
        : ucfirst($escalafon);

    $nombre_profesor = trim(
        get_user_meta($profesor_id, 'first_name', true) . ' ' .
        get_user_meta($profesor_id, 'last_name', true)
    );
    if (empty($nombre_profesor)) {
        $nombre_profesor = $profesor->display_name;
    }

    $asignado_por_id = get_current_user_id();
    $asignado_por    = $asignado_por_id ? get_userdata($asignado_por_id) : null;
    $nombre_asignador = $asignado_por ? $asignado_por->display_name : 'Decanatura';

    $fecha_asignacion = date('d/m/Y H:i');

    // Contenido del correo
    $contenido = '
        <p>Hola <strong>' . esc_html($nombre_profesor) . '</strong>,</p>

        <p>Te informamos que en la convocatoria
        <strong>' . esc_html($nombre_convocatoria) . '</strong>
        se ha asignado tu categoría de escalafón docente.</p>

        <div style="background: linear-gradient(135deg, #dc0c1d 0%, #a10613 100%);
                    color: white; padding: 25px; border-radius: 8px; text-align: center;
                    margin: 20px 0;">
            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">
                Categoría asignada
            </div>
            <div style="font-size: 28px; font-weight: 700; letter-spacing: 1px;">
                🎓 ' . esc_html($escalafon_display) . '
            </div>
        </div>

        <table role="presentation" cellspacing="0" cellpadding="10" border="0" width="100%"
               style="background-color: #f9f9f9; border-radius: 6px; margin: 15px 0;">
            <tr>
                <td style="padding: 8px 15px;"><strong>📌 Convocatoria:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($nombre_convocatoria) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px;"><strong>👤 Asignado por:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($nombre_asignador) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 15px;"><strong>📅 Fecha de asignación:</strong></td>
                <td style="padding: 8px 15px;">' . esc_html($fecha_asignacion) . '</td>
            </tr>
        </table>

        <p>Esta asignación es oficial y queda registrada en el sistema.
        Puedes consultar el detalle completo ingresando con tu usuario.</p>

        <p>Si tienes alguna pregunta sobre tu escalafón, por favor contacta
        directamente con la Decanatura.</p>
    ';

    sged_enviar_notificacion(
        $profesor->user_email,
        '🎓 Escalafón asignado - ' . $nombre_convocatoria,
        'Se ha asignado tu categoría de escalafón',
        $contenido,
        '📁 Ver mis documentos',
        home_url('/mis-archivos-cargados/?conv=' . $convocatoria_id),
        'escalafon'
    );
}


// ============================================================
// FUNCIÓN DE PRUEBA (solo para administradores)
// ============================================================

/**
 * Shortcode [sged_test_email] para que un admin pueda probar el envío.
 * Uso: crear una página temporal con [sged_test_email] y visitarla.
 */
add_shortcode('sged_test_email', 'sged_test_email_shortcode');

function sged_test_email_shortcode() {

    if (!current_user_can('manage_options')) {
        return '<p style="color:red;">Solo administradores pueden usar esta herramienta.</p>';
    }

    $current_user = wp_get_current_user();
    $email        = $current_user->user_email;

    $contenido = '
        <p>Hola <strong>' . esc_html($current_user->display_name) . '</strong>,</p>
        <p>Este es un correo de prueba del Sistema de Notificaciones de Bellas Artes.</p>
        <p>Si recibes este mensaje, la configuración SMTP está funcionando correctamente. ✅</p>
        <p><em>Fecha de envío: ' . date('d/m/Y H:i:s') . '</em></p>
    ';

    $enviado = sged_enviar_notificacion(
        $email,
        '🧪 Prueba de notificaciones - SGED',
        'Prueba de configuración del webhook',
        $contenido,
        '🌐 Ir al sistema',
        home_url(),
        'test'
    );

    if ($enviado) {
        return '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:6px;">
                    ✅ <strong>Correo enviado correctamente</strong> a <code>' . esc_html($email) . '</code>.
                    Revisa tu bandeja de entrada (y spam por si acaso).
                </div>';
    } else {
        return '<div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:6px;">
                    ❌ <strong>Fallo al enviar el correo.</strong><br>
                    Verifica que:<br>
                    • La URL del webhook (SGED_WEBHOOK_URL) esté configurada<br>
                    • El TOKEN (SGED_WEBHOOK_TOKEN) coincida con el del Apps Script<br>
                    • El Web App de Google Apps Script esté publicado<br>
                    Revisa el error_log de WordPress para más detalles.
                </div>';
    }
}


// ============================================================
// SHORTCODE DE DIAGNÓSTICO: simular el evento de carga
// Solo admin. Sirve para validar el flujo paso a paso.
// ============================================================

add_shortcode('sged_test_hook_carga', 'sged_test_hook_carga_shortcode');

function sged_test_hook_carga_shortcode() {
    if (!current_user_can('manage_options')) {
        return '<p style="color:red;">Solo administradores.</p>';
    }
    
    $output = '<div style="background:#f8f9fa; padding:20px; border-radius:8px; font-family:monospace; font-size:13px;">';
    $output .= '<h2 style="margin-top:0;">🔍 Test de Hook de Carga</h2>';
    
    // 1. ¿Está configurado el webhook?
    $output .= '<h3>1. Configuración del webhook</h3>';
    if (defined('SGED_WEBHOOK_URL')) {
        $url_corta = substr(SGED_WEBHOOK_URL, 0, 50) . '...';
        $output .= '<div>✅ SGED_WEBHOOK_URL definida: <code>' . esc_html($url_corta) . '</code></div>';
        if (strpos(SGED_WEBHOOK_URL, 'PEGAR_AQUI') !== false) {
            $output .= '<div style="color:red;">❌ Contiene el placeholder, NO está configurada correctamente</div>';
        }
    } else {
        $output .= '<div style="color:red;">❌ SGED_WEBHOOK_URL NO está definida</div>';
    }
    
    // 2. ¿Está registrado el hook?
    $output .= '<h3>2. Hook sged_documento_cargado</h3>';
    if (has_action('sged_documento_cargado')) {
        $output .= '<div>✅ El hook está registrado (priority: ' . has_action('sged_documento_cargado') . ')</div>';
    } else {
        $output .= '<div style="color:red;">❌ El hook NO está registrado. Verifica que email-notifications.php se está cargando.</div>';
    }
    
    // 3. Buscar el ÚLTIMO conv_doc cargado para simular el envío
    $output .= '<h3>3. Último conv_doc cargado en el sistema</h3>';
    $ultimo = get_posts([
        'post_type' => 'conv_doc',
        'numberposts' => 1,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    if (empty($ultimo)) {
        $output .= '<div style="color:orange;">⚠️ No hay ningún conv_doc en el sistema. Sube un PDF como docente primero.</div>';
    } else {
        $doc = $ultimo[0];
        $profesor_id = get_post_meta($doc->ID, 'documento_user_id', true);
        $profesor = $profesor_id ? get_userdata($profesor_id) : null;
        $convocatoria_id = get_post_meta($doc->ID, 'documento_convocatoria_id', true);
        $notif_enviada = get_post_meta($doc->ID, 'notificacion_carga_enviada', true);
        
        $output .= '<table cellpadding="6" style="background:white; border-collapse:collapse; width:100%;">';
        $output .= '<tr><td style="border:1px solid #ddd;"><strong>ID conv_doc:</strong></td><td style="border:1px solid #ddd;">' . $doc->ID . '</td></tr>';
        $output .= '<tr><td style="border:1px solid #ddd;"><strong>Título:</strong></td><td style="border:1px solid #ddd;">' . esc_html($doc->post_title) . '</td></tr>';
        $output .= '<tr><td style="border:1px solid #ddd;"><strong>Fecha creación:</strong></td><td style="border:1px solid #ddd;">' . $doc->post_date . '</td></tr>';
        $output .= '<tr><td style="border:1px solid #ddd;"><strong>Profesor ID:</strong></td><td style="border:1px solid #ddd;">' . esc_html($profesor_id) . '</td></tr>';
        $output .= '<tr><td style="border:1px solid #ddd;"><strong>Profesor login:</strong></td><td style="border:1px solid #ddd;">' . esc_html($profesor ? $profesor->user_login : '(no encontrado)') . '</td></tr>';
        $output .= '<tr><td style="border:1px solid #ddd;"><strong>Profesor EMAIL:</strong></td><td style="border:1px solid #ddd; ' . ($profesor && is_email($profesor->user_email) ? 'color:green;' : 'color:red;') . '"><strong>' . esc_html($profesor ? $profesor->user_email : '(SIN EMAIL)') . '</strong></td></tr>';
        $output .= '<tr><td style="border:1px solid #ddd;"><strong>Convocatoria ID:</strong></td><td style="border:1px solid #ddd;">' . esc_html($convocatoria_id) . '</td></tr>';
        $output .= '<tr><td style="border:1px solid #ddd;"><strong>¿Notificación ya enviada?</strong></td><td style="border:1px solid #ddd;">' . ($notif_enviada === '1' ? '✅ SÍ' : '❌ NO') . '</td></tr>';
        $output .= '</table>';
        
        // 4. Forzar envío de notificación manualmente
        $output .= '<h3>4. Forzar envío manual</h3>';
        $output .= '<div style="background:#fff3cd; padding:10px; border-radius:4px; margin:10px 0;">⏳ Ejecutando sged_enviar_notificacion_carga(' . $doc->ID . ')...</div>';
        
        // Quitar el flag anti-duplicado temporalmente para forzar el envío
        delete_post_meta($doc->ID, 'notificacion_carga_enviada');
        
        ob_start();
        sged_enviar_notificacion_carga($doc->ID);
        $forzado_output = ob_get_clean();
        
        $notif_post = get_post_meta($doc->ID, 'notificacion_carga_enviada', true);
        if ($notif_post === '1') {
            $output .= '<div style="background:#d4edda; color:#155724; padding:10px; border-radius:4px;">✅ La función se ejecutó correctamente. Revisa el correo del docente: <strong>' . esc_html($profesor->user_email) . '</strong></div>';
        } else {
            $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:4px;">❌ La función NO marcó como enviado. Revisa el debug.log para ver el error específico.</div>';
        }
    }
    
    $output .= '</div>';
    return $output;
}
