<?php
/**
* Lead Editor Role
*
* @author        Andrew Hateley-Browne @ Afterword
* @version       1.0.0
* @license       GPL-2.0+
*
* Plugin Name:       Lead Editor Role
* Plugin URI:        https://github.com/ahateleybrowne/lead-editor-role
* Description:       A simple WordPress plugin to extend the Redirections plugin. Adds "Lead Editor" role with Editor and Redirection management capabilities.
* Version:           1.0.0
* Author:            Andrew Hateley-Browne @ Afterword
* Author URI:        https://github.com/ahateleybrowne
* Update URI:        https://github.com/ahateleybrowne/lead-editor-role
* Text Domain:       aw
* Domain Path:       /lang
* Requires PHP:      8.1
* License:           GPL-2.0+
*/

/*
* Adds a "Lead Editor" role on plugin activation
*
* Adds new Lead Editor role to database on activation, and WP_Option for performance and version-control.
*
* @since   1.0.0
* @see     https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/
* @return  void
*/
register_activation_hook( __FILE__, 'dml_redirection_role_activate' );
function dml_redirection_role_activate() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Sets flag if Redirection plugin dependency is not installed and activated
    if ( ! is_plugin_active( 'redirection/redirection.php' ) ) {
        set_transient(
            'dml_redirection_role_activation_dependency_missing',
            true,
            30
        );

        return;
    }

    if( !function_exists('get_plugin_data') ) {
      		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    // Gets plugin version
    $plugin_data = get_plugin_data( __FILE__ );
    $version = get_option( 'redirection_lead_editor_role_version' );

    // Sets or resets the Lead Editor role capabilties if those in the database were set from another version of this plugin
    if ( $version !== $plugin_data['Version'] || !wp_roles()->is_role( 'lead_editor' ) ) {
        update_option( 'redirection_lead_editor_role_version', $plugin_data[ 'Version' ] );
        // Removes Lead Editor roles on activation to refresh capabilities
        remove_role( 'lead_editor' );

        // Gets editor capabilities
        $editor_capabilities = get_role( 'editor' )->capabilities;

        // Sets redirection capabilities
        // @see https://redirection.me/docs/developer/capabilities/
        $redirection_capabilities = [
            'redirection_access'
        ];

        // Combines editor and redirection capabilities
        $lead_editor_capabilities = array_merge(
            $editor_capabilities,
            array_fill_keys( $redirection_capabilities, true )
        );

        // Creates Lead Editor role
        add_role( 'lead_editor', 'Lead Editor', $lead_editor_capabilities );
        // Adds combined editor and redirection capabilities to Lead Editor role
        $role = get_role( 'lead_editor' );
        foreach ( $lead_editor_capabilities as $lead_editor_capability ) {
            $role->add_cap( $lead_editor_capability );
        }
    }
}

/*
* Defines redirection access capability for Lead Editor role
*
* If the user has manage_options capability (is "Super Admin" or "Administrator"),
* returns manage_options. Otherwise returns redirection_access
*
* @since   1.0.0
* @param   string   $role              The role to check
* @see     https://redirection.me/docs/developer/capabilities/
* @return  string   The role to use for the current user
*/
add_filter( 'redirection_role', function( $role ) {

    // Returns manage_options if user already has this capability
    if ( current_user_can( 'manage_options' ) ) {
        return 'manage_options';
    }

    // Otherwise, returns redirection_access
    return 'redirection_access';
} );

/*
* Defines permissions for those with Redirection role
*
* Defaults to 'manage_options' if the user already has this capability. Otherwise, returns 'redirection_access'.
*
* @since   1.0.0
* @param   string   $capability        The capability to check
* @param   string   $permission_name   The name of the permission to check
* @see     https://redirection.me/docs/developer/capabilities/
* @return  string   Indicates whether the current user has the specified capability
*/
add_filter( 'redirection_capability_check', function( $capability, $permission_name ) {

    // All redirection capabilities
    // @see     https://redirection.me/docs/developer/capabilities/
    // @note    Comment out permissions to remove from Lead Editor role
    $redirection_capabilities = [
        'redirection_cap_redirect_manage',  // ability to view redirects
        'redirection_cap_redirect_add',     // ability to create redirects
        'redirection_cap_redirect_delete',  // ability to delete redirects
        'redirection_cap_group_manage',     // ability to view groups
        'redirection_cap_group_add',        // ability to create groups
        'redirection_cap_group_delete',     // ability to delete groups
        'redirection_cap_log_manage',       // ability to view logs
        'redirection_cap_log_delete',       // ability to delete logs
        'redirection_cap_404_manage',       // ability to view 404s
        'redirection_cap_404_delete',       // ability to delete 404s
        'redirection_cap_io_manage',        // ability to perform import/export actions
        'redirection_cap_option_manage',    // ability to change options
        'redirection_cap_support_manage',   // ability to perform support actions, including REST API tests
        'redirection_cap_site_manage'       // ability to site actions
    ];

    if ( in_array( $permission_name, $redirection_capabilities ) ) {
        return $capability;
    }

    return 'manage_options';
}, 10, 2 );

/*
* Displays an admin notice if Redirection Plugin dependency is not met
*
* @since   1.0.0
* @see     https://developer.wordpress.org/reference/hooks/admin_notices/
* @return  void
*/
add_action( 'admin_notices', 'dml_redirection_role_activation_notice' );
function dml_redirection_role_activation_notice() {

    // Gets transient flag for missing Redirection plugin dependency
    $missing = get_transient( 'dml_redirection_role_activation_dependency_missing' );
    if ( $missing ) {
        // Delete transient flag
        delete_transient( 'dml_redirection_role_activation_dependency_missing' );

        $class = 'notice notice-error';
        $message = 'The Lead Editor role requires the Redirection plugin to be installed and activated.';

        // Prints admin notice
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }
}

/*
* Removes the "Lead Editor" role from WordPress on uninstallation
*
* Removes role and version option from database,
* and sets current Lead Editors with an Editor role.
*
* @since   1.0.0
* @see     https://developer.wordpress.org/reference/functions/register_uninstall_hook/
* @return  void
*/
register_uninstall_hook( __FILE__,	'dml_redirection_role_uninstall' );
function dml_redirection_role_uninstall() {

    // Gets all User IDs with Lead Editor role
    $lead_editors = get_users( [
        'role'   => 'lead_editor',
        'fields' => 'ID',
    ] );

    // Updates all Lead Editor users to Editor role
    foreach ( $lead_editors as $user_id ) {
        $user = new WP_User( $user_id );
        $user->set_role( 'editor' );
    }

    // Removes Lead Editor role
    remove_role( 'lead_editor' );
    // Removes version option
    delete_option( 'redirection_lead_editor_role_version' );
}
