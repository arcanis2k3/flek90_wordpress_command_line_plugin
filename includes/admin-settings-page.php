<?php
// wp-command-line-plugin/includes/admin-settings-page.php

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Add the admin menu page.
 */
function wpcli_plugin_add_admin_menu() {
    add_menu_page(
        __( 'WP Command Line', 'wp-command-line-interface' ), // Page Title
        __( 'WP Command Line', 'wp-command-line-interface' ), // Menu Title
        'manage_options',                         // Capability
        'wp_command_line_plugin',                 // Menu Slug
        'wpcli_plugin_settings_page_html',        // Callback function to render the page
        'dashicons-terminal',                     // Icon URL
        75                                         // Position
    );
}

/**
 * Register plugin settings.
 */
function wpcli_plugin_register_settings() {
    // Styling Tab Settings
    register_setting(
        'wpcli_plugin_styling_settings_group', // Option group
        'wpcli_plugin_enable_toggle',          // Option name
        [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean', // Or custom sanitize
            'default'           => true,
        ]
    );

    add_settings_section(
        'wpcli_plugin_styling_section',        // ID
        __( 'Toggle Button Settings', 'wp-command-line-interface' ), // Title
        null,                                  // Callback (optional)
        'wp_command_line_plugin_styling'       // Page slug for this section
    );

    add_settings_field(
        'wpcli_plugin_enable_toggle_field',    // ID
        __( 'Enable Command Line Toggle', 'wp-command-line-interface' ), // Title
        'wpcli_plugin_enable_toggle_field_html',// Callback function to render the field
        'wp_command_line_plugin_styling',       // Page slug
        'wpcli_plugin_styling_section'        // Section ID
    );

    // General Tab Settings (Placeholder)
    register_setting(
        'wpcli_plugin_general_settings_group',
        'wpcli_plugin_allowed_commands', // Example, not fully implemented for user input yet
         [
            'type'              => 'string', // Or array
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => "plugin list
option get
user list", // Default example
        ]
    );
     add_settings_section(
        'wpcli_plugin_general_section',
        __( 'Command Management', 'wp-command-line-interface' ),
        null,
        'wp_command_line_plugin_general'
    );
    add_settings_field(
        'wpcli_plugin_allowed_commands_field',
        __( 'Allowed Commands (Informational)', 'wp-command-line-interface' ),
        'wpcli_plugin_allowed_commands_field_html',
        'wp_command_line_plugin_general',
        'wpcli_plugin_general_section'
    );
}

/**
 * Render the checkbox field for 'Enable Command Line Toggle'.
 */
function wpcli_plugin_enable_toggle_field_html() {
    $option = get_option( 'wpcli_plugin_enable_toggle', true );
    ?>
    <label for="wpcli_plugin_enable_toggle">
        <input type="checkbox" id="wpcli_plugin_enable_toggle" name="wpcli_plugin_enable_toggle" value="1" <?php checked( $option, true ); ?>>
        <?php esc_html_e( 'Show the command line toggle button on all admin pages.', 'wp-command-line-interface' ); ?>
    </label>
    <?php
}

 /**
 * Render the text area for 'Allowed Commands (Informational)'.
 */
function wpcli_plugin_allowed_commands_field_html() {
    $allowed_commands_base = [
         'plugin list', 'plugin get', 'option get', 'user list', 'user get',
         'theme list', 'theme get', 'core version', 'site list', 'post list', 'comment list',
    ]; // These are the currently hardcoded ones from the handler
    ?>
    <p><?php esc_html_e( 'The following command bases are currently permitted for execution. This list is managed internally for security.', 'wp-command-line-interface' ); ?></p>
    <ul style="list-style-type: disc; margin-left: 20px;">
        <?php foreach ( $allowed_commands_base as $cmd ) : ?>
            <li><code><?php echo esc_html( $cmd ); ?></code></li>
        <?php endforeach; ?>
    </ul>
    <p><em><?php esc_html_e( 'More complex commands or making this list user-configurable requires careful security consideration.', 'wp-command-line-interface' ); ?></em></p>
    <?php
}

/**
 * Render the HTML for the settings page.
 */
function wpcli_plugin_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle active tab
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'styling';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'WP Command Line Plugin Settings', 'wp-command-line-interface' ); ?></h1>

        <h2 class="nav-tab-wrapper">
            <a href="?page=wp_command_line_plugin&tab=styling" class="nav-tab <?php echo $active_tab == 'styling' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Styling', 'wp-command-line-interface' ); ?>
            </a>
            <a href="?page=wp_command_line_plugin&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'General Settings', 'wp-command-line-interface' ); ?>
            </a>
        </h2>

        <form action="options.php" method="post">
            <?php
            if ( $active_tab == 'styling' ) {
                settings_fields( 'wpcli_plugin_styling_settings_group' ); // Nonce, action, option_page fields
                do_settings_sections( 'wp_command_line_plugin_styling' ); // Output sections and fields for this tab
            } elseif ( $active_tab == 'general' ) {
                settings_fields( 'wpcli_plugin_general_settings_group' );
                do_settings_sections( 'wp_command_line_plugin_general' );
            }

            // Submit button only if there are settings to save on the current tab
            if ($active_tab == 'styling') { // Only styling tab has a savable option for now
                 submit_button( __( 'Save Settings', 'wp-command-line-interface' ) );
            }
            ?>
        </form>
    </div>
    <?php
}
