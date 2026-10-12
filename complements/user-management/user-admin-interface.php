<?php
/**
 * User Admin Interface
 * Admin user list enhancements, lockout management, and role filtering
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Admin interface for user lockout management
add_filter('manage_users_columns', 'add_lockout_status_column');
function add_lockout_status_column($columns) {
    $columns['lockout_status'] = 'Security Status';
    return $columns;
}

add_action('manage_users_custom_column', 'show_lockout_status_column', 10, 3);
function show_lockout_status_column($value, $column_name, $user_id) {
    if ($column_name == 'lockout_status') {
        $status = get_user_lockout_status($user_id);
        if (check_account_locked($user_id)) {
            $value = '<span style="color: red; font-weight: bold;">' . $status . '</span>';
        } else {
            $value = '<span style="color: green;">' . $status . '</span>';
        }
    }
    return $value;
}

add_filter('user_row_actions', 'add_unlock_action', 10, 2);
function add_unlock_action($actions, $user) {
    if (check_account_locked($user->ID)) {
        $unlock_url = wp_nonce_url(
            admin_url('users.php?action=unlock_user&user_id=' . $user->ID),
            'unlock_user_' . $user->ID
        );
        $actions['unlock'] = '<a href="' . $unlock_url . '" style="color: #d63638;">Unlock Account</a>';
    }
    return $actions;
}

// Handle unlock action
add_action('admin_init', 'handle_unlock_user_action');
function handle_unlock_user_action() {
    if (isset($_GET['action']) && $_GET['action'] == 'unlock_user' && isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
        $nonce = $_GET['_wpnonce'];
        
        if (wp_verify_nonce($nonce, 'unlock_user_' . $user_id)) {
            unlock_user_account($user_id);
            wp_redirect(admin_url('users.php?unlocked=1'));
            exit();
        }
    }
}

// Add admin notice for unlock success
add_action('admin_notices', 'show_unlock_success_notice');
function show_unlock_success_notice() {
    if (isset($_GET['unlocked']) && $_GET['unlocked'] == '1') {
        echo '<div class="notice notice-success is-dismissible"><p>User account unlocked successfully.</p></div>';
    }
}

// Add bulk action for unlocking multiple users
add_filter('bulk_actions-users', 'add_bulk_unlock_action');
function add_bulk_unlock_action($bulk_actions) {
    $bulk_actions['unlock_users'] = 'Unlock Selected Users';
    return $bulk_actions;
}

add_filter('handle_bulk_actions-users', 'handle_bulk_unlock_action', 10, 3);
function handle_bulk_unlock_action($redirect_to, $doaction, $user_ids) {
    if ($doaction !== 'unlock_users') {
        return $redirect_to;
    }
    
    $unlocked_count = 0;
    foreach ($user_ids as $user_id) {
        if (check_account_locked($user_id)) {
            unlock_user_account($user_id);
            $unlocked_count++;
        }
    }
    
    $redirect_to = add_query_arg('bulk_unlocked', $unlocked_count, $redirect_to);
    return $redirect_to;
}

// Add admin notice for bulk unlock
add_action('admin_notices', 'show_bulk_unlock_success_notice');
function show_bulk_unlock_success_notice() {
    if (isset($_GET['bulk_unlocked'])) {
        $count = intval($_GET['bulk_unlocked']);
        echo '<div class="notice notice-success is-dismissible"><p>' . $count . ' user accounts unlocked successfully.</p></div>';
    }
}

// Add filter to view only locked users
add_action('restrict_manage_users', 'add_lockout_filter');
function add_lockout_filter() {
    $selected = isset($_GET['lockout_filter']) ? $_GET['lockout_filter'] : '';
    echo '<select name="lockout_filter">';
    echo '<option value="">All Users</option>';
    echo '<option value="locked"' . selected($selected, 'locked', false) . '>Locked Users Only</option>';
    echo '<option value="active"' . selected($selected, 'active', false) . '>Active Users Only</option>';
    echo '</select>';
}

// Filter users based on lockout status
add_action('pre_get_users', 'filter_users_by_lockout_status');
function filter_users_by_lockout_status($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if (isset($_GET['lockout_filter']) && $_GET['lockout_filter'] != '') {
        $filter = $_GET['lockout_filter'];
        
        if ($filter == 'locked') {
            // Get all locked user IDs
            $locked_users = get_users([
                'meta_key' => 'account_locked',
                'meta_value' => '1',
                'fields' => 'ID'
            ]);
            
            if (!empty($locked_users)) {
                $query->set('include', $locked_users);
            } else {
                $query->set('include', [0]); // No users found
            }
        } elseif ($filter == 'active') {
            // Get all non-locked user IDs
            $all_users = get_users(['fields' => 'ID']);
            $locked_users = get_users([
                'meta_key' => 'account_locked',
                'meta_value' => '1',
                'fields' => 'ID'
            ]);
            
            $active_users = array_diff($all_users, $locked_users);
            
            if (!empty($active_users)) {
                $query->set('include', $active_users);
            } else {
                $query->set('include', [0]); // No users found
            }
        }
    }
}

// Test user creation function for admin testing
add_shortcode('create_test_user', 'create_test_user_shortcode');
function create_test_user_shortcode() {
    // Only allow admins to create test users
    if (!current_user_can('manage_options')) {
        return 'Access denied. Admin privileges required.';
    }
    
    $username = isset($_GET['username']) ? sanitize_user($_GET['username']) : 'testuser';
    $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : 'test@example.com';
    $password = isset($_GET['password']) ? $_GET['password'] : 'testpass123';
    $role = isset($_GET['role']) ? sanitize_text_field($_GET['role']) : 'faculty';
    
    $user_id = create_test_user_with_role($username, $email, $password, $role);
    
    if ($user_id) {
        return "Test user created successfully!<br>
                Username: {$username}<br>
                Email: {$email}<br>
                Password: {$password}<br>
                Role: {$role}<br>
                <a href='?simulate_attempts={$user_id}'>Simulate 3 failed attempts</a>";
    } else {
        return 'Failed to create test user.';
    }
}

// Simulate failed attempts for testing
add_shortcode('simulate_attempts', 'simulate_attempts_shortcode');
function simulate_attempts_shortcode() {
    if (!current_user_can('manage_options')) {
        return 'Access denied. Admin privileges required.';
    }
    
    $user_id = isset($_GET['simulate_attempts']) ? intval($_GET['simulate_attempts']) : 0;
    
    if ($user_id > 0) {
        simulate_failed_attempts($user_id, 3);
        return "Simulated 3 failed attempts for user ID: {$user_id}. Account should now be locked.";
    }
    
    return 'No user ID provided.';
}

// Add role-based user filtering
add_action('restrict_manage_users', 'add_university_role_filter');
function add_university_role_filter() {
    $selected_role = isset($_GET['university_role']) ? $_GET['university_role'] : '';
    echo '<select name="university_role">';
    echo '<option value="">All University Roles</option>';
    echo '<option value="system_admin"' . selected($selected_role, 'system_admin', false) . '>System Administrators</option>';
    echo '<option value="evaluador_academico"' . selected($selected_role, 'evaluador_academico', false) . '>Evaluadores Académicos</option>';
    echo '<option value="docente"' . selected($selected_role, 'docente', false) . '>Docentes</option>';
    echo '<option value="comite_escalafon"' . selected($selected_role, 'comite_escalafon', false) . '>Comité de Escalafón</option>';
    echo '<option value="auditor"' . selected($selected_role, 'auditor', false) . '>Auditores</option>';
    echo '<option value="guest_faculty"' . selected($selected_role, 'guest_faculty', false) . '>Guest Faculty</option>';
    echo '<option value="subscriber"' . selected($selected_role, 'subscriber', false) . '>Students/Visitors</option>';
    echo '</select>';
}

// Filter users by university role
add_action('pre_get_users', 'filter_users_by_university_role');
function filter_users_by_university_role($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if (isset($_GET['university_role']) && $_GET['university_role'] != '') {
        $role = $_GET['university_role'];
        $query->set('role', $role);
    }
}

// Add university role column to users list
add_filter('manage_users_columns', 'add_university_role_column');
function add_university_role_column($columns) {
    $columns['university_role'] = 'University Role';
    return $columns;
}

add_action('manage_users_custom_column', 'show_university_role_column', 10, 3);
function show_university_role_column($value, $column_name, $user_id) {
    if ($column_name == 'university_role') {
        $user = get_userdata($user_id);
        $roles = $user->roles;
        
        $role_names = [
            'system_admin'         => 'System Administrator',
            'evaluador_academico'  => 'Evaluador Académico',
            'docente'              => 'Docente',
            'comite_escalafon'     => 'Comité de Escalafón',
            'auditor'              => 'Auditor',
            'guest_faculty'        => 'Guest Faculty',
            'administrator'        => 'System Administrator',
            'editor'               => 'Evaluador Académico',
            'author'               => 'Docente',
            'contributor'          => 'Guest Faculty',
            'subscriber'           => 'Student/Visitor'
        ];
        
        $display_roles = [];
        foreach ($roles as $role) {
            if (isset($role_names[$role])) {
                $display_roles[] = $role_names[$role];
            }
        }
        
        $value = implode(', ', $display_roles);
    }
    return $value;
}

// Add role assignment helper for admins
add_shortcode('assign_university_role', 'assign_university_role_shortcode');
function assign_university_role_shortcode() {
    if (!current_user_can('manage_options')) {
        return 'Access denied. Admin privileges required.';
    }
    
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $new_role = isset($_GET['new_role']) ? sanitize_text_field($_GET['new_role']) : '';
    
    if ($user_id > 0 && $new_role) {
        $user = new WP_User($user_id);
        $user->set_role($new_role);
        return "User role updated to: {$new_role}";
    }
    
    return 'Usage: ?user_id=123&new_role=faculty';
}
