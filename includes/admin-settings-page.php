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
        'wpcli_plugin_active_commands', // Changed option name
        [
            'type'              => 'array',
            'sanitize_callback' => 'wpcli_sanitize_active_commands', // Custom sanitize callback
            'default'           => [], // Default to empty, handler will enable all if not set
        ]
    );

    add_settings_section(
        'wpcli_plugin_general_section',
        __( 'Command Activation Management', 'wp-command-line-interface' ), // Changed title
        null,
        'wp_command_line_plugin_general'
    );

    add_settings_field(
        'wpcli_plugin_active_commands_field', // Changed ID
        __( 'Activate/Deactivate Commands', 'wp-command-line-interface' ), // Changed title
        'wpcli_plugin_active_commands_field_html', // Changed callback
        'wp_command_line_plugin_general',
        'wpcli_plugin_general_section'
    );

    // Keyboard Shortcut Tab Settings
    register_setting(
        'wpcli_plugin_shortcut_settings_group', // Option group
        'wpcli_plugin_keyboard_shortcut',       // Option name
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'ctrl+i',
        ]
    );

    add_settings_section(
        'wpcli_plugin_shortcut_section',         // ID
        __( 'Keyboard Shortcut Configuration', 'wp-command-line-interface' ), // Title
        null,                                   // Callback (optional)
        'wp_command_line_plugin_shortcut'       // Page slug for this section
    );

    add_settings_field(
        'wpcli_plugin_keyboard_shortcut_field', // ID
        __( 'CLI Toggle Shortcut', 'wp-command-line-interface' ), // Title
        'wpcli_plugin_keyboard_shortcut_field_html', // Callback function to render the field
        'wp_command_line_plugin_shortcut',        // Page slug
        'wpcli_plugin_shortcut_section'         // Section ID
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
 * Sanitize callback for the 'wpcli_plugin_active_commands' option.
 *
 * @param array $input The input array from the settings page.
 * @return array Sanitized array of active commands.
 */
function wpcli_sanitize_active_commands( $input ) {
    $sanitized_input = [];
    $all_manageable_commands = function_exists('wpcli_get_all_manageable_commands') ? wpcli_get_all_manageable_commands() : [];

    if ( ! is_array( $input ) ) {
        // If input is not an array, it might mean all checkboxes were unchecked.
        // In this case, all known commands should be marked as false (inactive).
        foreach ( $all_manageable_commands as $command_base ) {
            $sanitized_input[ $command_base ] = false;
        }
        return $sanitized_input;
    }

    foreach ( $all_manageable_commands as $command_base ) {
        // A command is active if its key exists in the input array and the value is '1' (from checkbox).
        // Otherwise, it's considered inactive (false).
        $sanitized_input[ $command_base ] = isset( $input[ $command_base ] ) && $input[ $command_base ] == '1';
    }

    return $sanitized_input;
}

/**
 * Render the checkboxes for 'Activate/Deactivate Commands'.
 */
function wpcli_plugin_active_commands_field_html() {
    // Get all manageable commands (defined in the main plugin file)
    $all_manageable_commands = function_exists('wpcli_get_all_manageable_commands') ? wpcli_get_all_manageable_commands() : [];

    // Get the currently saved active commands
    $active_commands_option = get_option( 'wpcli_plugin_active_commands' );

    // If the option is not set yet (false), default all manageable commands to true (active).
    // This ensures that on first view, all commands appear enabled.
    if ( false === $active_commands_option ) {
        $active_commands = array_fill_keys( $all_manageable_commands, true );
    } else {
        $active_commands = (array) $active_commands_option;
    }

    if ( empty( $all_manageable_commands ) ) {
        echo '<p>' . esc_html__( 'No manageable commands found. Ensure `wpcli_get_all_manageable_commands()` is defined and returns commands.', 'wp-command-line-interface' ) . '</p>';
        return;
    }
    ?>
    <p><?php esc_html_e( 'Select the commands that should be active and usable in the Command Line Interface.', 'wp-command-line-interface' ); ?></p>
    <fieldset>
        <legend class="screen-reader-text"><span><?php esc_html_e( 'Commands Activation', 'wp-command-line-interface' ); ?></span></legend>
        <?php foreach ( $all_manageable_commands as $command_base ) : ?>
            <?php
            // Ensure every command from the main list has an entry in $active_commands for the checkbox
            $is_active = isset( $active_commands[ $command_base ] ) ? (bool) $active_commands[ $command_base ] : true; // Default to true if new command not in option yet
            $field_id = 'wpcli_active_cmd_' . sanitize_key( $command_base );
            ?>
            <label for="<?php echo esc_attr( $field_id ); ?>" style="display: block; margin-bottom: 5px;">
                <input type="checkbox"
                       id="<?php echo esc_attr( $field_id ); ?>"
                       name="wpcli_plugin_active_commands[<?php echo esc_attr( $command_base ); ?>]"
                       value="1"
                       <?php checked( $is_active, true ); ?>>
                <code><?php echo esc_html( $command_base ); ?></code>
            </label>
        <?php endforeach; ?>
    </fieldset>
    <?php
}


/**
 * Render the text input field for 'CLI Toggle Shortcut'.
 */
function wpcli_plugin_keyboard_shortcut_field_html() {
    $option = get_option( 'wpcli_plugin_keyboard_shortcut', 'ctrl+i' );
    ?>
    <input type="text" id="wpcli_plugin_keyboard_shortcut" name="wpcli_plugin_keyboard_shortcut" value="<?php echo esc_attr( $option ); ?>" class="regular-text">
    <p class="description">
        <?php esc_html_e( 'Define the keyboard shortcut to toggle the Command Line Interface.', 'wp-command-line-interface' ); ?>
        <?php esc_html_e( 'Use combinations like "ctrl+i", "alt+m", "ctrl+shift+k". Supported modifiers: ctrl, alt, shift.', 'wp-command-line-interface' ); ?>
        <?php esc_html_e( 'Use lowercase letters for keys (e.g., "i" not "I").', 'wp-command-line-interface' ); ?>
    </p>
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
            <a href="?page=wp_command_line_plugin&tab=shortcut" class="nav-tab <?php echo $active_tab == 'shortcut' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Keyboard Shortcut', 'wp-command-line-interface' ); ?>
            </a>
        </h2>

        <form action="options.php" method="post">
            <?php
            if ( $active_tab == 'styling' ) {
                settings_fields( 'wpcli_plugin_styling_settings_group' );
                do_settings_sections( 'wp_command_line_plugin_styling' );
            } elseif ( $active_tab == 'general' ) {
                settings_fields( 'wpcli_plugin_general_settings_group' );
                do_settings_sections( 'wp_command_line_plugin_general' );
            } elseif ( $active_tab == 'shortcut' ) {
                settings_fields( 'wpcli_plugin_shortcut_settings_group' );
                do_settings_sections( 'wp_command_line_plugin_shortcut' );
            }

            // Submit button for tabs that have savable options
            if ( $active_tab == 'styling' || $active_tab == 'shortcut' || $active_tab == 'general' ) {
                 submit_button( __( 'Save Settings', 'wp-command-line-interface' ) );
            }
            ?>
        </form>
    </div>
    <?php
}
