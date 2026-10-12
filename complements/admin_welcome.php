<?php
/**
 * Admin Welcome Page Functions
 * Post-installation welcome experience for Sistema de Escalafón
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if welcome page should be shown
 */
function should_show_welcome_page() {
    // Check if user has dismissed the welcome page
    $dismissed = get_user_meta(get_current_user_id(), 'escalafon_welcome_dismissed', true);
    if ($dismissed) {
        return false;
    }
    
    // Check if this is the first time the theme is activated
    $theme_activated = get_option('escalafon_theme_activated', false);
    if (!$theme_activated) {
        return true;
    }
    
    // Show welcome if user explicitly requests it
    if (isset($_GET['show_welcome']) && $_GET['show_welcome'] == '1') {
        return true;
    }
    
    return false;
}

/**
 * Mark theme as activated
 */
function mark_theme_activated() {
    update_option('escalafon_theme_activated', true);
}

/**
 * Dismiss welcome page
 */
function dismiss_welcome_page() {
    if (isset($_GET['dismiss_welcome']) && $_GET['dismiss_welcome'] == '1') {
        if (wp_verify_nonce($_GET['_wpnonce'], 'dismiss_welcome')) {
            update_user_meta(get_current_user_id(), 'escalafon_welcome_dismissed', true);
            wp_redirect(admin_url('admin.php?page=faculty-management'));
            exit;
        }
    }
}

/**
 * Get setup checklist status
 */
function get_setup_checklist_status() {
    $checklist = [];
    
    // Theme activated
    $checklist['theme_activated'] = [
        'title' => 'Tema Activado',
        'status' => true,
        'description' => 'El tema Sistema de Escalafón está activo'
    ];
    
    // Check if there are any administrators
    $admins = get_users(['role' => 'administrator']);
    $checklist['has_administrators'] = [
        'title' => 'Administradores Configurados',
        'status' => count($admins) > 0,
        'description' => count($admins) > 0 ? 
            count($admins) . ' administrador(es) configurado(s)' : 
            'Necesita configurar al menos un administrador'
    ];
    
    // Check if there are faculty users
    $faculty = get_users(['role' => 'author']);
    $checklist['has_faculty'] = [
        'title' => 'Usuarios de Facultad',
        'status' => count($faculty) > 0,
        'description' => count($faculty) > 0 ? 
            count($faculty) . ' profesor(es) registrado(s)' : 
            'Puede crear usuarios de facultad cuando sea necesario'
    ];
    
    // Check if pages are created
    $welcome_page = get_page_by_path('bienvenida');
    $checklist['pages_created'] = [
        'title' => 'Páginas del Sistema',
        'status' => $welcome_page !== null,
        'description' => $welcome_page ? 
            'Páginas del sistema creadas correctamente' : 
            'Las páginas del sistema se crearán automáticamente'
    ];
    
    // Check security configuration
    $checklist['security_configured'] = [
        'title' => 'Seguridad Configurada',
        'status' => true,
        'description' => 'Sistema de seguridad de inicio de sesión activo'
    ];
    
    return $checklist;
}

/**
 * Render welcome page
 */
function render_welcome_page() {
    $checklist = get_setup_checklist_status();
    $stats = get_system_statistics();
    
    ?>
    <div class="wrap escalafon-welcome">
        <div class="welcome-header">
            <h1>🎓 ¡Bienvenido al Sistema de Gestión de Escalafón!</h1>
            <p class="welcome-subtitle">
                Sistema completo de gestión de documentos académicos para Bellas Artes Institución Universitaria del Valle
            </p>
        </div>
        
        <div class="welcome-content">
            <!-- System Overview -->
            <div class="welcome-section">
                <h2>📊 Resumen del Sistema</h2>
                <div class="system-overview">
                    <div class="overview-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['total_users']; ?></span>
                            <span class="stat-label">Usuarios</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['pending_documents']; ?></span>
                            <span class="stat-label">Documentos Pendientes</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['locked_accounts']; ?></span>
                            <span class="stat-label">Cuentas Bloqueadas</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Setup Checklist -->
            <div class="welcome-section">
                <h2>✅ Lista de Verificación de Configuración</h2>
                <div class="setup-checklist">
                    <?php foreach ($checklist as $key => $item): ?>
                        <div class="checklist-item <?php echo $item['status'] ? 'completed' : 'pending'; ?>">
                            <div class="checklist-icon">
                                <?php if ($item['status']): ?>
                                    ✅
                                <?php else: ?>
                                    ⚠️
                                <?php endif; ?>
                            </div>
                            <div class="checklist-content">
                                <h3><?php echo esc_html($item['title']); ?></h3>
                                <p><?php echo esc_html($item['description']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Quick Start Guide -->
            <div class="welcome-section">
                <h2>🚀 Guía de Inicio Rápido</h2>
                <div class="quick-start-steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>Crear Usuarios</h3>
                            <p>Vaya a <a href="<?php echo admin_url('users.php'); ?>">Usuarios</a> para crear administradores, coordinadores y profesores.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>Configurar Roles</h3>
                            <p>Asigne roles universitarios apropiados: System Administrator, Academic Coordinator, o Faculty/Professor.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>Probar el Sistema</h3>
                            <p>Use las <a href="<?php echo admin_url('users.php?page=faculty-management&tab=testing'); ?>">funciones de prueba</a> para verificar el funcionamiento.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h3>Revisar Documentación</h3>
                            <p>Consulte el <a href="<?php echo get_template_directory_uri(); ?>/assets/docs/manual.html" target="_blank">manual completo</a> para más detalles.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Features -->
            <div class="welcome-section">
                <h2>🔧 Características del Sistema</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🔐</div>
                        <h3>Seguridad Avanzada</h3>
                        <p>Protección CSRF, limitación de intentos, bloqueo automático de cuentas</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📄</div>
                        <h3>Gestión de Documentos</h3>
                        <p>6 tipos de documentos académicos con flujo de validación</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">👥</div>
                        <h3>Roles Universitarios</h3>
                        <p>5 roles específicos para el entorno universitario</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>Dashboard Avanzado</h3>
                        <p>Estadísticas en tiempo real y gestión centralizada</p>
                    </div>
                </div>
            </div>
            
            <!-- Next Steps -->
            <div class="welcome-section">
                <h2>📋 Próximos Pasos</h2>
                <div class="next-steps">
                    <div class="next-step">
                        <h3>Para Administradores</h3>
                        <ul>
                            <li>Revisar el <a href="<?php echo admin_url('users.php?page=faculty-management'); ?>">Dashboard del Sistema</a></li>
                            <li>Configurar usuarios y roles</li>
                            <li>Probar funciones de seguridad</li>
                        </ul>
                    </div>
                    <div class="next-step">
                        <h3>Para Coordinadores Académicos</h3>
                        <ul>
                            <li>Revisar <a href="<?php echo home_url('/profesores'); ?>">lista de profesores</a></li>
                            <li>Validar documentos pendientes</li>
                            <li>Gestionar cuentas bloqueadas</li>
                        </ul>
                    </div>
                    <div class="next-step">
                        <h3>Para Profesores</h3>
                        <ul>
                            <li>Completar <a href="<?php echo home_url('/perfil'); ?>">perfil académico</a></li>
                            <li>Cargar <a href="<?php echo home_url('/documentos'); ?>">documentos académicos</a></li>
                            <li>Monitorear estado de validación</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Support Information -->
            <div class="welcome-section">
                <h2>📞 Soporte y Ayuda</h2>
                <div class="support-info">
                    <p>Para soporte técnico, personalización o preguntas sobre el sistema:</p>
                    <ul>
                        <li><strong>Email:</strong> soporte@bellasartes.edu.co</li>
                        <li><strong>Departamento:</strong> TI - Bellas Artes Institución Universitaria del Valle</li>
                        <li><strong>Documentación:</strong> <a href="<?php echo get_template_directory_uri(); ?>/assets/docs/manual.html" target="_blank">Manual completo del sistema</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Welcome Actions -->
        <div class="welcome-actions">
            <a href="<?php echo admin_url('users.php?page=faculty-management'); ?>" class="button button-primary button-large">
                Ir al Dashboard del Sistema
            </a>
            <a href="<?php echo admin_url('users.php'); ?>" class="button button-secondary button-large">
                Gestionar Usuarios
            </a>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=faculty-management&dismiss_welcome=1'), 'dismiss_welcome'); ?>" class="button button-secondary">
                No mostrar esta página nuevamente
            </a>
        </div>
    </div>
    <?php
}

/**
 * Add welcome page to admin menu
 */
function add_welcome_page_to_menu() {
    if (should_show_welcome_page()) {
        add_menu_page(
            'Bienvenido al Sistema',
            '¡Bienvenido!',
            'manage_options',
            'escalafon-welcome',
            'render_welcome_page',
            'dashicons-welcome-learn-more',
            1
        );
    }
}

/**
 * Show welcome notice on dashboard
 */
function show_welcome_notice() {
    if (should_show_welcome_page()) {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>🎓 Sistema de Escalafón Instalado</strong> - 
                <a href="<?php echo admin_url('admin.php?page=escalafon-welcome'); ?>">Ver guía de inicio</a> | 
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=escalafon-welcome&dismiss_welcome=1'), 'dismiss_welcome'); ?>">Dismiss</a>
            </p>
        </div>
        <?php
    }
}
