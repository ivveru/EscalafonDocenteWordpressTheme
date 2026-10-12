<?php
/**
 * User Roles Management - SGED Bellas Artes
 * 
 * Roles del sistema:
 *   - system_admin         → "System Administrator"     (admin total)
 *   - comite_escalafon     → "Comité de Escalafón"     (asigna escalafón)
 *   - evaluador_academico  → "Evaluador Académico"     (califica documentos)
 *   - docente              → "Docente"                 (sube documentos)
 *   - auditor              → "Auditor"                 (solo lectura)
 *   - guest_faculty        → "Guest Faculty"           (acceso limitado)
 *
 * @package SGED_BellasArtes
 * @since   2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================
// REGISTRO DE ROLES PERSONALIZADOS
// ============================================================

add_action('init', 'sged_registrar_roles', 5);
function sged_registrar_roles() {
    
    // System Administrator - Acceso total
    if (!get_role('system_admin')) {
        add_role('system_admin', 'System Administrator', [
            'manage_options'      => true,
            'edit_others_posts'   => true,
            'edit_posts'          => true,
            'read'                => true,
            'unlock_accounts'     => true,
            'create_test_users'   => true,
        ]);
    }
    
    // Evaluador Académico - Califica documentos (antes Academic Coordinator)
    if (!get_role('evaluador_academico')) {
        add_role('evaluador_academico', 'Evaluador Académico', [
            'edit_others_posts'   => true,
            'edit_posts'          => true,
            'read'                => true,
            'unlock_accounts'     => true,
            'calificar_documento' => true,
        ]);
    }
    
    // Docente - Sube documentos (antes Faculty/Professor)
    if (!get_role('docente')) {
        add_role('docente', 'Docente', [
            'edit_posts'          => true,
            'read'                => true,
            'subir_documento'     => true,
        ]);
    }
    
    // Guest Faculty - Acceso limitado (sin cambios)
    if (!get_role('guest_faculty')) {
        add_role('guest_faculty', 'Guest Faculty', [
            'edit_posts'          => true,
            'read'                => true,
        ]);
    }
    
    // Comité de Escalafón - Asigna escalafón (antes Decanatura)
    if (!get_role('comite_escalafon')) {
        add_role('comite_escalafon', 'Comité de Escalafón', [
            'read'                => true,
            'view_calificaciones' => true,
            'asignar_escalafon'   => true,
        ]);
    }
    
    // Auditor - Solo lectura, ve TODO pero no puede editar
    if (!get_role('auditor')) {
        add_role('auditor', 'Auditor', [
            'read'                => true,
            'view_calificaciones' => true,   // Ver tabla admin-calificaciones
            'view_convocatorias'  => true,   // Ver convocatorias (sin editar)
            'view_reclamos'       => true,   // Ver detalle de reclamos
        ]);
    }
}

// ============================================================
// LIMPIEZA: Eliminar roles antiguos
// ============================================================
//
// Como el usuario confirmó que NO hay usuarios reales con los roles
// antiguos (sistema en pruebas), eliminamos los roles obsoletos.
//
// Esta función se ejecuta UNA SOLA VEZ controlada por una option.
// Si necesitas volver a ejecutarla, borra la option 'sged_roles_v2_aplicado'.

add_action('init', 'sged_eliminar_roles_obsoletos', 10);
function sged_eliminar_roles_obsoletos() {
    
    if (get_option('sged_roles_v2_aplicado') === 'yes') return;
    
    $roles_obsoletos = [
        'decanatura',
        'academic_coordinator',
        'faculty',
    ];
    
    foreach ($roles_obsoletos as $rol_obsoleto) {
        if (get_role($rol_obsoleto)) {
            // Quitar el rol a todos los usuarios que lo tengan
            $usuarios = get_users(['role' => $rol_obsoleto]);
            foreach ($usuarios as $user) {
                $user->remove_role($rol_obsoleto);
                // NOTA: Quedan sin rol, el admin debe asignarles uno nuevo manualmente
            }
            
            // Eliminar el rol del sistema
            remove_role($rol_obsoleto);
        }
    }
    
    update_option('sged_roles_v2_aplicado', 'yes');
}

// ============================================================
// PERSONALIZAR NOMBRES DE ROLES NATIVOS DE WORDPRESS
// ============================================================
//
// WordPress trae roles base (administrator, editor, author...) que no se pueden
// renombrar internamente, pero sí se puede cambiar el nombre que se muestra al
// usuario. Aquí los alineamos con la nomenclatura del sistema.

add_filter('wp_roles', 'sged_renombrar_roles_nativos');
function sged_renombrar_roles_nativos($wp_roles) {
    if (isset($wp_roles->roles['administrator'])) {
        $wp_roles->roles['administrator']['name'] = 'System Administrator';
    }
    if (isset($wp_roles->roles['editor'])) {
        $wp_roles->roles['editor']['name'] = 'Evaluador Académico';
    }
    if (isset($wp_roles->roles['author'])) {
        $wp_roles->roles['author']['name'] = 'Docente';
    }
    if (isset($wp_roles->roles['contributor'])) {
        $wp_roles->roles['contributor']['name'] = 'Guest Faculty';
    }
    if (isset($wp_roles->roles['subscriber'])) {
        $wp_roles->roles['subscriber']['name'] = 'Student/Visitor';
    }
    return $wp_roles;
}
