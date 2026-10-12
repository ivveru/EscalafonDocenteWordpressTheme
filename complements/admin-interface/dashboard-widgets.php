<?php
/**
 * Dashboard Widgets
 * Admin dashboard widgets and system overview functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Admin dashboard widget for security overview
add_action('wp_dashboard_setup', 'add_security_dashboard_widget');
function add_security_dashboard_widget() {
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget(
            'security_overview',
            'Security Overview',
            'security_dashboard_widget_content'
        );
    }
}

function security_dashboard_widget_content() {
    $locked_users = get_users([
        'meta_key' => 'account_locked',
        'meta_value' => '1',
        'fields' => 'ID'
    ]);
    
    $total_users = count_users();
    $locked_count = count($locked_users);
    
    echo "<p><strong>Total Users:</strong> {$total_users['total_users']}</p>";
    echo "<p><strong>Locked Accounts:</strong> {$locked_count}</p>";
    
    if ($locked_count > 0) {
        echo "<p><a href='" . admin_url('users.php?lockout_filter=locked') . "'>View Locked Users</a></p>";
    }
    
    echo "<p><strong>Test Functions:</strong></p>";
    echo "<p><a href='?create_test_user=1'>Create Test User</a></p>";
}

// Enhanced dashboard widget with comprehensive system overview
add_action('wp_dashboard_setup', 'add_enhanced_dashboard_widget');
function add_enhanced_dashboard_widget() {
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget(
            'escalafon_dashboard_widget',
            '📊 Sistema de Escalafón - Resumen del Sistema',
            'enhanced_dashboard_widget_content'
        );
    }
}

function enhanced_dashboard_widget_content() {
    $stats = get_system_statistics();
    $health = get_system_health_status();
    $distribution = get_user_distribution();
    $activities = get_recent_activity();
    
    ?>
    <div class="escalafon-dashboard-widget">
        <!-- Statistics Overview -->
        <div class="widget-stats">
            <div class="stat-item">
                <span class="stat-number"><?php echo $stats['total_users']; ?></span>
                <span class="stat-label">Total Usuarios</span>
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
        
        <!-- System Health -->
        <div class="widget-health">
            <strong>Estado del Sistema:</strong>
            <span class="health-indicator <?php echo $health['status']; ?>">
                <?php 
                $status_text = [
                    'healthy' => '● Saludable',
                    'warning' => '⚠ Atención',
                    'error' => '❌ Problemas'
                ];
                echo $status_text[$health['status']];
                ?>
            </span>
        </div>
        
        <!-- User Distribution -->
        <div class="widget-distribution">
            <strong>Distribución de Usuarios:</strong>
            <ul>
                <?php foreach ($distribution as $role => $count): ?>
                    <li><?php echo esc_html($role); ?>: <?php echo $count; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <!-- Recent Activity -->
        <div class="widget-activity">
            <strong>Actividad Reciente (24h):</strong>
            <?php if (empty($activities)): ?>
                <p>No hay actividad reciente.</p>
            <?php else: ?>
                <ul>
                    <?php foreach (array_slice($activities, 0, 5) as $activity): ?>
                        <li><?php echo esc_html($activity['message']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions -->
        <div class="widget-actions">
            <a href="<?php echo admin_url('users.php?page=faculty-management'); ?>" class="button button-primary">
                Ver Dashboard Completo
            </a>
            <a href="<?php echo admin_url('users.php?lockout_filter=locked'); ?>" class="button">
                Cuentas Bloqueadas
            </a>
        </div>
    </div>
    
    <style>
        .escalafon-dashboard-widget {
            font-size: 14px;
        }
        .widget-stats {
            display: flex;
            justify-content: space-around;
            margin: 15px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            display: block;
            font-size: 1.5em;
            font-weight: bold;
            color: #0073aa;
        }
        .stat-label {
            display: block;
            font-size: 0.9em;
            color: #666;
        }
        .widget-health, .widget-distribution, .widget-activity {
            margin: 10px 0;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .health-indicator.healthy { color: #46b450; }
        .health-indicator.warning { color: #ffb900; }
        .health-indicator.error { color: #dc3232; }
        .widget-actions {
            margin-top: 15px;
            text-align: center;
        }
        .widget-actions .button {
            margin: 0 5px;
        }
    </style>
    <?php
}
