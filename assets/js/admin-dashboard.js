/**
 * Admin Dashboard JavaScript
 * Interactive functionality for Sistema de Escalafón admin interface
 */

(function($) {
    'use strict';

    // Auto-refresh for live statistics
    function initAutoRefresh() {
        if ($('.escalafon-stats-cards').length > 0) {
            setInterval(function() {
                refreshStatistics();
            }, 30000); // Refresh every 30 seconds
        }
    }

    // Refresh statistics via AJAX
    function refreshStatistics() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'escalafon_refresh_stats',
                nonce: $('#escalafon_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    updateStatisticsDisplay(response.data);
                }
            },
            error: function() {
                console.log('Error refreshing statistics');
            }
        });
    }

    // Update statistics display
    function updateStatisticsDisplay(data) {
        $('.stat-card').each(function() {
            var $card = $(this);
            var label = $card.find('.stat-label').text().toLowerCase();
            
            if (label.includes('usuarios')) {
                $card.find('.stat-number').text(data.total_users);
            } else if (label.includes('documentos')) {
                $card.find('.stat-number').text(data.pending_documents);
            } else if (label.includes('bloqueadas')) {
                $card.find('.stat-number').text(data.locked_accounts);
            } else if (label.includes('actividad')) {
                $card.find('.stat-number').text(data.recent_activity_count);
            }
        });
    }

    // Dismiss welcome page
    function initWelcomeDismiss() {
        $(document).on('click', '.dismiss-welcome', function(e) {
            e.preventDefault();
            
            if (confirm('¿Está seguro de que desea ocultar esta página de bienvenida?')) {
                var url = $(this).attr('href');
                window.location.href = url;
            }
        });
    }

    // Quick action handlers
    function initQuickActions() {
        // Create test user
        $(document).on('click', '.create-test-user', function(e) {
            e.preventDefault();
            
            var role = prompt('Ingrese el rol para el usuario de prueba (faculty, coordinator, admin):', 'faculty');
            if (role) {
                createTestUser(role);
            }
        });

        // Unlock account
        $(document).on('click', '.unlock-account', function(e) {
            e.preventDefault();
            
            var userId = $(this).data('user-id');
            if (userId && confirm('¿Desbloquear esta cuenta?')) {
                unlockUserAccount(userId);
            }
        });

        // View locked users
        $(document).on('click', '.view-locked-users', function(e) {
            e.preventDefault();
            window.location.href = 'users.php?lockout_filter=locked';
        });
    }

    // Create test user via AJAX
    function createTestUser(role) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'escalafon_create_test_user',
                role: role,
                nonce: $('#escalafon_nonce').val()
            },
            beforeSend: function() {
                $('.create-test-user').prop('disabled', true).text('Creando...');
            },
            success: function(response) {
                if (response.success) {
                    alert('Usuario de prueba creado: ' + response.data.username);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error al crear usuario de prueba');
            },
            complete: function() {
                $('.create-test-user').prop('disabled', false).text('Crear Usuario de Prueba');
            }
        });
    }

    // Unlock user account via AJAX
    function unlockUserAccount(userId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'escalafon_unlock_user',
                user_id: userId,
                nonce: $('#escalafon_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('Cuenta desbloqueada exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error al desbloquear cuenta');
            }
        });
    }

    // Initialize dashboard widgets
    function initDashboardWidgets() {
        // Add refresh button to dashboard widget
        if ($('#escalafon_dashboard_widget').length > 0) {
            var refreshBtn = '<button type="button" class="button button-small refresh-stats" style="float: right; margin-top: -5px;">🔄</button>';
            $('#escalafon_dashboard_widget .inside').prepend(refreshBtn);
        }

        // Handle refresh button click
        $(document).on('click', '.refresh-stats', function() {
            refreshStatistics();
            $(this).text('🔄').prop('disabled', true);
            setTimeout(function() {
                $('.refresh-stats').text('🔄').prop('disabled', false);
            }, 2000);
        });
    }

    // Initialize activity feed
    function initActivityFeed() {
        if ($('.activity-feed').length > 0) {
            // Add timestamp formatting
            $('.activity-time').each(function() {
                var time = $(this).text();
                if (time.includes('ago')) {
                    $(this).addClass('time-ago');
                }
            });
        }
    }

    // Initialize health status indicators
    function initHealthIndicators() {
        $('.health-indicator').each(function() {
            var $indicator = $(this);
            var status = $indicator.hasClass('healthy') ? 'healthy' : 
                        $indicator.hasClass('warning') ? 'warning' : 'error';
            
            // Add pulse animation for warning/error states
            if (status !== 'healthy') {
                $indicator.addClass('pulse-animation');
            }
        });
    }

    // Initialize responsive features
    function initResponsiveFeatures() {
        // Handle mobile menu for quick actions
        if ($(window).width() < 768) {
            $('.quick-actions-grid').addClass('mobile-layout');
        }

        // Handle window resize
        $(window).resize(function() {
            if ($(window).width() < 768) {
                $('.quick-actions-grid').addClass('mobile-layout');
            } else {
                $('.quick-actions-grid').removeClass('mobile-layout');
            }
        });
    }

    // Initialize tooltips
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            var $element = $(this);
            var tooltip = $element.data('tooltip');
            
            $element.hover(
                function() {
                    $('<div class="escalafon-tooltip">' + tooltip + '</div>')
                        .appendTo('body')
                        .fadeIn(200);
                },
                function() {
                    $('.escalafon-tooltip').remove();
                }
            );
        });
    }

    // Initialize all features
    function init() {
        initAutoRefresh();
        initWelcomeDismiss();
        initQuickActions();
        initDashboardWidgets();
        initActivityFeed();
        initHealthIndicators();
        initResponsiveFeatures();
        initTooltips();
    }

    // Document ready
    $(document).ready(function() {
        init();
    });

    // Add CSS for tooltips
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .escalafon-tooltip {
                position: absolute;
                background: #333;
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 9999;
                pointer-events: none;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            .escalafon-tooltip:after {
                content: '';
                position: absolute;
                top: 100%;
                left: 50%;
                margin-left: -5px;
                border: 5px solid transparent;
                border-top-color: #333;
            }
            .pulse-animation {
                animation: pulse 2s infinite;
            }
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }
            .mobile-layout .quick-action-btn {
                margin-bottom: 10px;
            }
        `)
        .appendTo('head');

})(jQuery);
