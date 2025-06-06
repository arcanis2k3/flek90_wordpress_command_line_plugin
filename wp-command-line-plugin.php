<?php
/**
 * Plugin Name:       WP Command Line Interface
 * Plugin URI:        https://example.com/plugins/wp-command-line-interface/
 * Description:       Provides a command-line interface to execute WP-CLI commands from the WordPress admin.
 * Version:           1.1.0
 * Author:            flek90
 * Author URI:        https://flek90.aureusz.com
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

/**
 * Returns the list of all manageable command bases.
 * This function serves as the single source of truth for all commands this plugin can manage.
 *
 * @return array List of command base strings.
 */
function wpcli_get_all_manageable_commands() {
    return [
        'plugin list',
        'plugin get',
        'option get',
        'option update',
        'user list',
        'user get',
        'theme list',
        'theme get',
        'core version',
        'site list',
        'post list',
        'comment list',
        'page create',
        // Future commands can be added here.
    ];
}

add_action( 'admin_menu', 'wpcli_plugin_add_admin_menu' );
add_action( 'admin_init', 'wpcli_plugin_register_settings' );
add_action( 'wp_ajax_wpcli_command_handler', 'wpcli_command_handler_callback' );

function wpcli_command_handler_callback() {
    // Verify nonce. The nonce name 'wpcli_nonce' should match what's sent from JS.
    // Replaced check_ajax_referer to provide a JSON response on failure.
    if ( ! isset( $_POST['wpcli_nonce'] ) || ! wp_verify_nonce( $_POST['wpcli_nonce'], 'wpcli_commands_nonce' ) ) {
        wp_send_json_error( ['message' => 'Error: Nonce verification failed.'], 403 );
        return;
    }

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

    // **Security: Check if the command is active and allowed.**
    // 1. Get the base of the entered command.
    $entered_command_base = '';
    $all_manageable_commands = wpcli_get_all_manageable_commands(); // Get all defined command bases
    foreach ($all_manageable_commands as $manageable_base) {
        if (strpos($command, $manageable_base) === 0) {
            $entered_command_base = $manageable_base;
            break;
        }
    }

    if (empty($entered_command_base)) {
        wp_send_json_error(['message' => 'Error: Unknown command.'], 400);
        return;
    }

    // 2. Get the list of currently active commands from options.
    // Defaults to all manageable commands being active if the option isn't set yet.
    $active_commands_option = get_option('wpcli_plugin_active_commands');
    if (false === $active_commands_option) { // Option not yet saved
        $active_commands = array_fill_keys($all_manageable_commands, true);
    } else {
        $active_commands = (array) $active_commands_option;
    }

    // 3. Check if the entered command's base is in the active list and set to true.
    if (!isset($active_commands[$entered_command_base]) || $active_commands[$entered_command_base] == false) {
        wp_send_json_error(['message' => 'Error: This command is currently deactivated by the site administrator.'], 403);
        return;
    }

    // Execute the command using the new handler.
    // The ai_agent_handle_cli_command function is in 'includes/wp-interaction.php'.
    $output = ai_agent_handle_cli_command( $command );

    // Check if the output string indicates an error.
    // ai_agent_handle_cli_command formats error strings to start with "Error:", "Fatal error:", or "Warning:".
    $is_error = false;
    if (is_string($output)) {
        if (strpos($output, 'Error:') === 0 ||
            strpos($output, 'Fatal error:') === 0 ||
            strpos($output, 'Warning:') === 0) {
            $is_error = true;
        }
    } elseif ($output === false) {
        // Fallback for any unexpected 'false' return, though ai_agent_handle_cli_command should not return false.
        $is_error = true;
        $output = 'Error: Command execution failed with an unspecified error.';
    }

    if ( $is_error ) {
        wp_send_json_error( ['message' => $output], 500 ); // $output already contains the full error message.
    } else {
        // The JS expects 'output' field for success
        wp_send_json_success( ['output' => $output] ); // $output contains success message or command output.
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
            'shortcut'=> get_option( 'wpcli_plugin_keyboard_shortcut', 'ctrl+i' ), // Add shortcut
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
