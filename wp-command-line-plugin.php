<?php
/**
 * Plugin Name:       WP Command Line Interface
 * Plugin URI:        https://example.com/plugins/wp-command-line-interface/
 * Description:       Provides a command-line interface to execute WP-CLI commands from the WordPress admin.
 * Version:           1.1.1
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

// WP-CLI execution has been removed, so AI_AGENT_WP_PATH is no longer needed.
// if ( ! defined( 'AI_AGENT_WP_PATH' ) ) {
//    define( 'AI_AGENT_WP_PATH', '/app/wordpress' );
// }

// Include the WP Interaction functions.
require_once plugin_dir_path( __FILE__ ) . 'includes/wp-interaction.php';

// Include admin settings page
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-settings-page.php';

/**
 * Returns the list of all manageable command bases.
 * This function serves as the single source of truth for all commands this plugin can manage.
 *
 * @return array Associative array of command_key => description.
 */
function wpcli_get_all_manageable_commands() {
    // These keys MUST match the ones identified by wpcli_identify_command_action()
    // and used by ai_agent_handle_cli_command() for routing.
    return [
        'help'              => 'Shows plugin information and a list of available commands.',
        'core version'      => 'Get WordPress core version information.',
        'option get'        => 'Get a site option value.',
        'option update'     => 'Update a site option.',
        'page create'       => 'Create a new page.',
        'plugin list'       => 'List installed plugins.',
        'plugin activate'   => 'Activate a plugin.',
        'plugin deactivate' => 'Deactivate a plugin.',
        'theme list'        => 'List installed themes.',
        'theme activate'    => 'Activate a theme.',
        'user list'         => 'List users.',
        'user get'          => 'Get details for a specific user.',
        // Add other commands that are implemented via PHP handlers.
        // Commands previously handled by WP-CLI directly and not yet refactored
        // (e.g. 'site list', 'post list', 'comment list', 'plugin get', 'theme get')
        // are effectively removed if not listed here and if the WP-CLI fallback is gone.
        // For this task, we assume all listed here are PHP-handled.
    ];
}

add_action( 'admin_menu', 'wpcli_plugin_add_admin_menu' );
add_action( 'admin_init', 'wpcli_plugin_register_settings' );
add_action( 'wp_ajax_wpcli_command_handler', 'wpcli_command_handler_callback' );

/**
 * Identifies the command action key from a raw command string.
 *
 * @param string $command_string The raw command string.
 * @return string|null The identified command action key (e.g., "option get", "page create") or null if not recognized.
 */
function wpcli_identify_command_action($command_string) {
    $command_string = trim($command_string);

    // Handle 'help' or '/help' specifically
    if (strtolower($command_string) === 'help' || strtolower($command_string) === '/help') {
        return 'help';
    }

    $command_parts = explode(' ', $command_string, 3); // Get first two parts generally

    if (empty($command_parts[0])) {
        return null;
    }

    $group = strtolower($command_parts[0]);
    $action = isset($command_parts[1]) ? strtolower($command_parts[1]) : null;

    switch ($group) {
        case 'option':
            if ($action === 'get' || $action === 'update') {
                return 'option ' . $action;
            }
            break;
        case 'page':
            if ($action === 'create') {
                return 'page create';
            }
            break;
        case 'plugin':
            if ($action === 'list' || $action === 'activate' || $action === 'deactivate') {
                return 'plugin ' . $action;
            }
            break;
        case 'theme':
            if ($action === 'list' || $action === 'activate') {
                return 'theme ' . $action;
            }
            break;
        case 'user':
            if ($action === 'list' || $action === 'get') {
                return 'user ' . $action;
            }
            break;
        case 'core':
            if ($action === 'version') {
                return 'core version';
            }
            break;
    }
    // Fallback for simple commands that might not have a group/action structure
    // but are directly the key in wpcli_get_all_manageable_commands.
    // This is less likely with current structure but good for robustness.
    // For example, if 'core version' was just 'coreversion' as a key.
    // The current keys are like "core version", so the switch-case handles them.
    // If $command_string itself is a key (e.g. a single-word command if we had one)
    if (array_key_exists($command_string, wpcli_get_all_manageable_commands())) {
         return $command_string;
    }

    return null; // Not identified
}


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

    // **Security: Identify and check if the command is active and allowed.**
    $command_action_key = wpcli_identify_command_action($command);

    if ($command_action_key === null) {
        wp_send_json_error(['message' => 'Error: Unknown command structure or not a supported command.'], 400);
        return;
    }

    $all_manageable_commands_map = wpcli_get_all_manageable_commands(); // This now returns key => description

    if (!array_key_exists($command_action_key, $all_manageable_commands_map)) {
        wp_send_json_error(['message' => "Error: Command action '{$command_action_key}' is not recognized as a manageable feature."], 400);
        return;
    }

    $active_commands_option = get_option('wpcli_plugin_active_commands');
    $is_active = true; // Default to active

    if ($active_commands_option === false) {
        // Option not in DB yet, all manageable commands are active by default.
        $is_active = true;
    } else {
        // Option exists, check if the command is explicitly listed and true.
        // If a command is not in the $active_commands_option array (e.g., newly added command to the plugin),
        // it defaults to inactive for safety unless $active_commands_option is empty (meaning nothing saved yet).
        if (is_array($active_commands_option) && !empty($active_commands_option)) { // If settings have been saved at least once
             if (!isset($active_commands_option[$command_action_key]) || $active_commands_option[$command_action_key] == false) {
                $is_active = false;
            }
        } elseif (is_array($active_commands_option) && empty($active_commands_option) && $active_commands_option !== false) {
            // This means the option was saved as an empty array (all commands unchecked by user)
            $is_active = false;
        }
        // If $active_commands_option is specifically an empty array from DB, all are inactive.
        // If $active_commands_option is `false` (not in DB), all are active (covered above).
    }

    if (!$is_active) {
        wp_send_json_error(['message' => "Error: Command '{$command_action_key}' is deactivated by the site administrator."], 403);
        return;
    }

    // Execute the command using the handler in wp-interaction.php
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
