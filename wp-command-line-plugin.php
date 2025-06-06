<?php
/**
 * Plugin Name:       WP Command Line Interface
 * Plugin URI:        https://example.com/plugins/wp-command-line-interface/
 * Description:       Provides a command-line interface to execute WP-CLI commands from the WordPress admin.
 * Version:           1.0.0
 * Author:            Your Name or Company
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-command-line-interface
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define the path to the WordPress installation for WP-CLI.
if ( ! defined( 'AI_AGENT_WP_PATH' ) ) {
    define( 'AI_AGENT_WP_PATH', '/app/wordpress' ); // Standard path in this environment
}

// Include the WP Interaction functions.
require_once plugin_dir_path( __FILE__ ) . 'includes/wp-interaction.php';

// Include admin settings page
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-settings-page.php';

add_action( 'admin_menu', 'wpcli_plugin_add_admin_menu' );
add_action( 'admin_init', 'wpcli_plugin_register_settings' );
add_action( 'wp_ajax_wpcli_command_handler', 'wpcli_command_handler_callback' );

function wpcli_command_handler_callback() {
    // Verify nonce. The nonce name 'wpcli_nonce' should match what's sent from JS.
    check_ajax_referer( 'wpcli_commands_nonce', 'wpcli_nonce' );

    // Check user capabilities.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( ['message' => 'Error: You do not have permission to execute commands.'], 403 );
        return;
    }

    // Get the command from the POST request.
    $command = isset( $_POST['command'] ) ? sanitize_text_field( wp_unslash( $_POST['command'] ) ) : '';

    if ( empty( $command ) ) {
        wp_send_json_error( ['message' => 'Error: Command cannot be empty.'], 400 );
        return;
    }

    // **Security: Whitelist allowed commands.**
    // For now, only allow specific commands.
    // We check if the *start* of the submitted command matches one of the allowed bases.
    $allowed_commands_base = [
        'plugin list',
        'plugin get',
        'option get',
        'user list',
        'user get',
        'theme list',
        'theme get',
        'core version',
        'site list',
        'post list',
        'comment list',
        // Add more safe, read-only commands as needed
    ];

    $command_is_allowed = false;
    foreach ( $allowed_commands_base as $allowed_base ) {
        if ( strpos( $command, $allowed_base ) === 0 ) {
            // Further validation could be added here, e.g., checking for potentially harmful flags.
            // For now, if the base matches, we allow it.
            $command_is_allowed = true;
            break;
        }
    }

    if ( ! $command_is_allowed ) {
        wp_send_json_error( ['message' => 'Error: This command is not allowed.'], 403 );
        return;
    }

    // Execute the command.
    // The ai_agent_execute_wp_cli_command function is in 'includes/wp-interaction.php'.
    $output = ai_agent_execute_wp_cli_command( $command );

    if ( $output === false ) {
        wp_send_json_error( ['message' => 'Error: Failed to execute command or command returned no output.'], 500 );
    } else {
        // The JS expects 'output' field for success, not 'reply'
        wp_send_json_success( ['output' => $output] );
    }
}

// Enqueue scripts and styles for the admin area
add_action( 'admin_enqueue_scripts', 'wpcli_plugin_enqueue_admin_assets' );

function wpcli_plugin_enqueue_admin_assets() {
    // Check if the current user is an admin and if the toggle is enabled in settings.
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $is_enabled = get_option( 'wpcli_plugin_enable_toggle', true );
    if ( ! $is_enabled ) {
        return;
    }

    // Enqueue Plugin Styles
    wp_enqueue_style(
        'wp-command-line-interface-styles', // Handle
        plugin_dir_url( __FILE__ ) . 'assets/css/command-line.css', // Source
        [], // Dependencies
        '1.0.0' // Version
    );

    // Enqueue Plugin JavaScript
    wp_enqueue_script(
        'wp-command-line-interface-js', // Handle
        plugin_dir_url( __FILE__ ) . 'assets/js/command-line.js', // Source
        ['jquery'], // Dependencies
        '1.0.0', // Version
        true // Load in footer
    );

    // Localize script with data for AJAX requests
    // The object name 'wpcliCmdAjax' must match its usage in command-line.js
    // The nonce handle 'wpcli_commands_nonce' must match what check_ajax_referer() expects.
    wp_localize_script(
        'wp-command-line-interface-js',
        'wpcliCmdAjax',
        [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wpcli_commands_nonce' ),
            // We can also pass the initial list of allowed commands if JS needs it,
            // but for now, it's mainly for the AJAX call.
        ]
    );
}

// Action to add the toggle button and CLI window HTML to the admin footer.
add_action( 'admin_footer', 'wpcli_add_interface_to_footer' );

function wpcli_add_interface_to_footer() {
    // Check if the feature is enabled (we'll add a setting for this later).
    // For now, assume it's enabled if 'true' or if the option doesn't exist yet.
    $is_enabled = get_option( 'wpcli_plugin_enable_toggle', true );

    if ( ! $is_enabled ) {
        return;
    }

    // Output the toggle icon.
    // The ID 'wpcli-icon' should match what command-line.js expects.
    // Basic inline styles are used here; more advanced styling can be in command-line.css.
    echo '<div id="wpcli-icon">CLI</div>';

    // Output the command line window HTML by including the template.
    // Ensure the path is correct.
    $template_path = plugin_dir_path( __FILE__ ) . 'templates/chat-window.html';
    if ( file_exists( $template_path ) ) {
        include $template_path;
    } else {
        echo '<!-- WP CLI Template not found -->';
    }
}
