<?php
/**
 * Admin Dashboard Functions
 * Enhanced admin interface for Sistema de Escalafón
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get comprehensive system statistics
 */
function get_system_statistics() {
    $stats = [];
    
    // User statistics
    $total_users = count_users();
    $stats['total_users'] = $total_users['total_users'];
    
    // Users by role
    $role_stats = [];
    $roles = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];
    foreach ($roles as $role) {
        $role_stats[$role] = count(get_users(['role' => $role]));
    }
    $stats['users_by_role'] = $role_stats;
    
    // Locked accounts
    $locked_users = get_users([
        'meta_key' => 'account_locked',
        'meta_value' => '1',
        'fields' => 'ID'
    ]);
    $stats['locked_accounts'] = count($locked_users);
    
    // Pending documents
    $pending_docs = get_posts([
        'post_type' => 'user_document',
        'meta_query' => [
            [
                'key' => 'user_document_valid',
                'compare' => 'NOT EXISTS'
            ]
        ],
        'fields' => 'ids'
    ]);
    $stats['pending_documents'] = count($pending_docs);
    
    // Recent activity (last 24 hours)
    $stats['recent_activity'] = get_recent_activity();
    
    return $stats;
}

/**
 * Get user distribution by role
 */
function get_user_distribution() {
    $distribution = [];
    $roles = [
        'administrator' => 'System Administrator',
        'editor' => 'Academic Coordinator',
        'author' => 'Faculty/Professor',
        'contributor' => 'Guest Faculty',
        'subscriber' => 'Student/Visitor'
    ];
    
    foreach ($roles as $role => $display_name) {
        $count = count(get_users(['role' => $role]));
        if ($count > 0) {
            $distribution[$display_name] = $count;
        }
    }
    
    return $distribution;
}

/**
 * Get pending documents count
 */
function get_pending_documents_count() {
    return get_posts([
        'post_type' => 'user_document',
        'meta_query' => [
            [
                'key' => 'user_document_valid',
                'compare' => 'NOT EXISTS'
            ]
        ],
        'fields' => 'ids'
    ]);
}

/**
 * Get recent activity (last 24 hours)
 */
function get_recent_activity() {
    $activities = [];
    
    // Recent document uploads
    $recent_docs = get_posts([
        'post_type' => 'user_document',
        'date_query' => [
            [
                'after' => '24 hours ago'
            ]
        ],
        'posts_per_page' => 5
    ]);
    
    foreach ($recent_docs as $doc) {
        $user = get_userdata(get_post_meta($doc->ID, 'user_document_user_id', true));
        $doc_type = get_post_meta($doc->ID, 'user_document_type', true);
        $activities[] = [
            'type' => 'document_upload',
            'message' => ($user ? $user->display_name : 'Usuario') . ' cargó un documento de ' . $doc_type,
            'time' => $doc->post_date,
            'user' => $user ? $user->display_name : 'Desconocido'
        ];
    }
    
    // Recent user registrations
    $recent_users = get_users([
        'date_query' => [
            [
                'after' => '24 hours ago'
            ]
        ],
        'number' => 5
    ]);
    
    foreach ($recent_users as $user) {
        $activities[] = [
            'type' => 'user_registration',
            'message' => 'Nuevo usuario: ' . $user->display_name,
            'time' => $user->user_registered,
            'user' => $user->display_name
        ];
    }
    
    // Sort by time (most recent first)
    usort($activities, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    return array_slice($activities, 0, 10); // Return last 10 activities
}

/**
 * Get system health status
 */
function get_system_health_status() {
    $health = [
        'status' => 'healthy',
        'issues' => [],
        'recommendations' => []
    ];
    
    // Check for locked accounts
    $locked_count = count(get_users([
        'meta_key' => 'account_locked',
        'meta_value' => '1',
        'fields' => 'ID'
    ]));
    
    if ($locked_count > 5) {
        $health['status'] = 'warning';
        $health['issues'][] = "Muchas cuentas bloqueadas ({$locked_count})";
        $health['recommendations'][] = "Revisar intentos de inicio de sesión y desbloquear cuentas legítimas";
    }
    
    // Check for pending documents
    $pending_count = count(get_pending_documents_count());
    if ($pending_count > 20) {
        $health['status'] = 'warning';
        $health['issues'][] = "Muchos documentos pendientes ({$pending_count})";
        $health['recommendations'][] = "Revisar y validar documentos pendientes";
    }
    
    // Check for system errors
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $health['recommendations'][] = "WP_DEBUG está activado - desactivar en producción";
    }
    
    return $health;
}

/**
 * Render system overview page
 */
function render_system_overview_page() {
    $stats = get_system_statistics();
    $health = get_system_health_status();
    $distribution = get_user_distribution();
    $activities = get_recent_activity();
    
    ?>
    <div class="wrap">
        <h1>📊 Dashboard del Sistema de Escalafón</h1>
        
        <!-- Statistics Cards -->
        <div class="escalafon-stats-cards">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_documents']; ?></div>
                <div class="stat-label">Documentos Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['locked_accounts']; ?></div>
                <div class="stat-label">Cuentas Bloqueadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($activities); ?></div>
                <div class="stat-label">Actividad (24h)</div>
            </div>
        </div>
        
        <!-- System Health -->
        <div class="escalafon-health-status">
            <h2>Estado del Sistema</h2>
            <div class="health-indicator <?php echo $health['status']; ?>">
                <span class="health-dot"></span>
                <?php 
                $status_text = [
                    'healthy' => 'Sistema Saludable',
                    'warning' => 'Atención Requerida',
                    'error' => 'Problemas Detectados'
                ];
                echo $status_text[$health['status']];
                ?>
            </div>
            
            <?php if (!empty($health['issues'])): ?>
                <div class="health-issues">
                    <h3>Problemas Detectados:</h3>
                    <ul>
                        <?php foreach ($health['issues'] as $issue): ?>
                            <li><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($health['recommendations'])): ?>
                <div class="health-recommendations">
                    <h3>Recomendaciones:</h3>
                    <ul>
                        <?php foreach ($health['recommendations'] as $rec): ?>
                            <li><?php echo esc_html($rec); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- User Distribution -->
        <div class="escalafon-user-distribution">
            <h2>Distribución de Usuarios</h2>
            <div class="distribution-chart">
                <?php foreach ($distribution as $role => $count): ?>
                    <div class="distribution-item">
                        <span class="role-name"><?php echo esc_html($role); ?></span>
                        <span class="role-count"><?php echo $count; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="escalafon-recent-activity">
            <h2>Actividad Reciente (24 horas)</h2>
            <div class="activity-feed">
                <?php if (empty($activities)): ?>
                    <p>No hay actividad reciente.</p>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <span class="activity-time"><?php echo human_time_diff(strtotime($activity['time']), current_time('timestamp')); ?> ago</span>
                            <span class="activity-message"><?php echo esc_html($activity['message']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="escalafon-quick-actions">
            <h2>Acciones Rápidas</h2>
            <div class="quick-actions-grid">
                <a href="<?php echo admin_url('users.php?university_role=faculty'); ?>" class="quick-action-btn">
                    👥 Ver Profesores
                </a>
                <a href="<?php echo admin_url('users.php?lockout_filter=locked'); ?>" class="quick-action-btn">
                    🔒 Cuentas Bloqueadas
                </a>
                <a href="<?php echo admin_url('users.php?action=-1&new_role=faculty'); ?>" class="quick-action-btn">
                    ➕ Crear Usuario
                </a>
                <a href="<?php echo home_url('/profesores'); ?>" class="quick-action-btn">
                    📄 Documentos Pendientes
                </a>
            </div>
        </div>
        
        <!-- Documentation Links -->
        <div class="escalafon-documentation">
            <h2>Documentación y Ayuda</h2>
            <div class="doc-links">
                <a href="<?php echo get_template_directory_uri(); ?>/assets/docs/manual.html" target="_blank" class="doc-link">
                    📖 Manual del Sistema
                </a>
                <a href="<?php echo admin_url('users.php?page=faculty-management&tab=help'); ?>" class="doc-link">
                    ❓ Ayuda y Soporte
                </a>
                <a href="<?php echo admin_url('users.php?page=faculty-management&tab=testing'); ?>" class="doc-link">
                    🧪 Funciones de Prueba
                </a>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render user management page
 */
function render_user_management_page() {
    ?>
    <div class="wrap">
        <h1>👥 Gestión de Usuarios del Sistema</h1>
        <p>Gestión avanzada de usuarios con roles universitarios y funciones de seguridad.</p>
        
        <div class="user-management-tools">
            <div class="management-section">
                <h2>Filtros y Búsqueda</h2>
                <p>Use los filtros en la página de <a href="<?php echo admin_url('users.php'); ?>">Usuarios</a> para:</p>
                <ul>
                    <li>Filtrar por rol universitario</li>
                    <li>Ver solo cuentas bloqueadas</li>
                    <li>Buscar usuarios específicos</li>
                </ul>
            </div>
            
            <div class="management-section">
                <h2>Acciones Masivas</h2>
                <p>En la página de usuarios puede:</p>
                <ul>
                    <li>Desbloquear múltiples cuentas</li>
                    <li>Cambiar roles en lote</li>
                    <li>Enviar notificaciones</li>
                </ul>
            </div>
            
            <div class="management-section">
                <h2>Estadísticas de Usuarios</h2>
                <?php
                $stats = get_system_statistics();
                $distribution = get_user_distribution();
                ?>
                <div class="user-stats">
                    <p><strong>Total de Usuarios:</strong> <?php echo $stats['total_users']; ?></p>
                    <p><strong>Cuentas Bloqueadas:</strong> <?php echo $stats['locked_accounts']; ?></p>
                    <div class="role-breakdown">
                        <h3>Distribución por Rol:</h3>
                        <?php foreach ($distribution as $role => $count): ?>
                            <p><?php echo esc_html($role); ?>: <?php echo $count; ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="quick-links">
            <a href="<?php echo admin_url('users.php'); ?>" class="button button-primary">Ir a Gestión de Usuarios</a>
            <a href="<?php echo admin_url('users.php?university_role=faculty'); ?>" class="button">Ver Solo Profesores</a>
            <a href="<?php echo admin_url('users.php?lockout_filter=locked'); ?>" class="button">Ver Cuentas Bloqueadas</a>
        </div>
    </div>
    <?php
}

/**
 * Render pending documents page
 */
function render_pending_documents_page() {
    $pending_docs = get_posts([
        'post_type' => 'user_document',
        'meta_query' => [
            [
                'key' => 'user_document_valid',
                'compare' => 'NOT EXISTS'
            ]
        ],
        'posts_per_page' => 20
    ]);
    
    ?>
    <div class="wrap">
        <h1>📄 Documentos Pendientes de Validación</h1>
        
        <?php if (empty($pending_docs)): ?>
            <div class="no-pending-docs">
                <p>✅ No hay documentos pendientes de validación.</p>
            </div>
        <?php else: ?>
            <div class="pending-documents-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Profesor</th>
                            <th>Tipo de Documento</th>
                            <th>Nombre</th>
                            <th>Año</th>
                            <th>Fecha de Carga</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_docs as $doc): ?>
                            <?php
                            $user_id = get_post_meta($doc->ID, 'user_document_user_id', true);
                            $user = get_userdata($user_id);
                            $doc_type = get_post_meta($doc->ID, 'user_document_type', true);
                            $doc_name = get_post_meta($doc->ID, 'user_document_name', true);
                            $doc_year = get_post_meta($doc->ID, 'user_document_year', true);
                            ?>
                            <tr>
                                <td><?php echo $user ? $user->display_name : 'Usuario no encontrado'; ?></td>
                                <td><?php echo esc_html($doc_type); ?></td>
                                <td><?php echo esc_html($doc_name); ?></td>
                                <td><?php echo esc_html($doc_year); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($doc->post_date)); ?></td>
                                <td>
                                    <a href="<?php echo home_url('/ver_documento?id=' . $doc->ID); ?>" class="button button-small">
                                        Ver Documento
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <div class="pending-docs-actions">
            <a href="<?php echo home_url('/profesores'); ?>" class="button button-primary">Ir a Revisión de Profesores</a>
            <a href="<?php echo admin_url('users.php?page=faculty-management'); ?>" class="button">Volver al Dashboard</a>
        </div>
    </div>
    <?php
}

/**
 * Render help and configuration page
 */
function render_help_configuration_page() {
    ?>
    <div class="wrap">
        <h1>❓ Ayuda y Configuración del Sistema</h1>
        
        <div class="help-sections">
            <div class="help-section">
                <h2>📖 Documentación del Sistema</h2>
                <p>El sistema incluye documentación completa en español:</p>
                <ul>
                    <li><a href="<?php echo get_template_directory_uri(); ?>/assets/docs/manual.html" target="_blank">Manual Completo del Sistema</a></li>
                    <li><a href="<?php echo home_url('/bienvenida'); ?>">Página de Bienvenida</a></li>
                </ul>
            </div>
            
            <div class="help-section">
                <h2>🧪 Funciones de Prueba</h2>
                <p>Para administradores - funciones de testing:</p>
                <div class="test-functions">
                    <h3>Crear Usuario de Prueba</h3>
                    <p>Shortcode: <code>[create_test_user]</code></p>
                    <p>Parámetros opcionales: username, email, password, role</p>
                    
                    <h3>Simular Bloqueo de Cuenta</h3>
                    <p>Shortcode: <code>[simulate_attempts]</code></p>
                    <p>Simula 3 intentos fallidos para probar el bloqueo</p>
                    
                    <h3>Asignar Rol Universitario</h3>
                    <p>Shortcode: <code>[assign_university_role user_id=123 new_role=faculty]</code></p>
                </div>
            </div>
            
            <div class="help-section">
                <h2>🔧 Configuración del Sistema</h2>
                <div class="system-config">
                    <h3>Roles Universitarios</h3>
                    <ul>
                        <li><strong>System Administrator:</strong> Acceso completo al sistema</li>
                        <li><strong>Academic Coordinator:</strong> Supervisión de facultad y validación</li>
                        <li><strong>Faculty/Professor:</strong> Gestión de documentos personales</li>
                        <li><strong>Guest Faculty:</strong> Acceso limitado temporal</li>
                        <li><strong>Student/Visitor:</strong> Solo lectura</li>
                    </ul>
                    
                    <h3>Configuración de Seguridad</h3>
                    <ul>
                        <li>Bloqueo automático después de 3 intentos fallidos</li>
                        <li>Limitación de 5 intentos por IP cada 15 minutos</li>
                        <li>Protección CSRF en todos los formularios</li>
                        <li>Sin sesiones persistentes (seguro para computadoras públicas)</li>
                    </ul>
                </div>
            </div>
            
            <div class="help-section">
                <h2>📞 Soporte Técnico</h2>
                <p>Para soporte técnico y personalización:</p>
                <ul>
                    <li><strong>Email:</strong> soporte@bellasartes.edu.co</li>
                    <li><strong>Departamento:</strong> TI - Bellas Artes Institución Universitaria del Valle</li>
                    <li><strong>Documentación:</strong> Manual de usuario disponible internamente</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}
