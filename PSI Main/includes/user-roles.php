<?php
// user-roles.php

class User_Roles_Manager {
    public function __construct() {
        // Initialize user roles and capabilities
        add_action('init', array($this, 'init_user_roles'));

        // Custom query vars
        add_filter('query_vars', array($this, 'custom_query_vars'));

        // Custom rewrite rules
        add_action('init', array($this, 'custom_rewrite_rules'));

        // Modify attachments query so non admins can only access media files that they upload
        add_filter('ajax_query_attachments_args', array($this, 'show_current_user_attachments'));

        // Prevent Non Admins from entering the backend of WordPress
        add_filter('login_redirect', array($this, 'redirect_non_admin_users'), 10, 3);

        // Hide admin bar for non-administrator users
        if (!current_user_can('administrator')) {
            add_filter('show_admin_bar', '__return_false');
        }
    }

    public function init_user_roles() {
        $this->add_staff_roles();
    }

    public function add_staff_roles() {
        remove_role('staff_member');

        add_role(
            'staff_member',
            __('Staff Member'),
            array(
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
                'publish_posts' => true,
                'upload_files' => true,
                'read_private_posts' => true,
                'edit_private_posts' => true,
                'delete_private_posts' => true,
                'publish_private_posts' => true,
                'read_published_posts' => true,
                'edit_published_posts' => true,
                'delete_published_posts' => true,
            )
        );

        add_role(
            'staff_member_editor',
            __('Staff Member Editor'),
            get_role('staff_member')->capabilities
        );
    }

    public function show_current_user_attachments($query) {
        $user_id = get_current_user_id();
    
        // Check if the user is an administrator
        if (current_user_can('activate_plugins')) {
            return $query; // Admins can see all media, no modification needed
        }
    
        if ($user_id) {
            $user = get_user_by('ID', $user_id);
            $allowed_roles = ['staff_member', 'staff_member_editor'];
    
            if (array_intersect($allowed_roles, (array)$user->roles)) {
                // Allow staff members and staff member editors to edit their own media attachments
                $query['author'] = $user_id;
                $query['can_edit_attachments'] = true;
            }
        }
    
        return $query;
    }

    public function redirect_non_admin_users($redirect_to, $request, $user) {
        // Is there a user?
        if (isset($user->roles) && is_array($user->roles)) {
            // Is the user not an administrator?
            if (!in_array('administrator', $user->roles)) {
                // Redirect to the front end
                return home_url();
            }
        }
    
        // If user is an administrator or there is no user role information, proceed with the default redirect
        return $redirect_to;
    }

    public function custom_rewrite_rules() {
        // Rewrite rule for user-profile/user_nicename
        add_rewrite_rule('^staff/profile/([^/]+)/?$', 'index.php?pagename=user-profile&user_nicename=$matches[1]', 'top');
    
        // Rewrite rules for other sections
        $sections = array(
            'professional-history',
            'honors-and-awards'
        );
    
        foreach ($sections as $section) {
            add_rewrite_rule("staff/profile/{$section}/([^/]+)/?$", 'index.php?pagename=' . $section . '&user_nicename=$matches[1]', 'top');
        }
    }

    public function custom_query_vars($query_vars) {
        $query_vars[] = 'user_nicename';
        return $query_vars;
    }
}

// Instantiate the user roles class
$psi_user_roles = new User_Roles_Manager();
