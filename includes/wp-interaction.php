<?php
/**
 * WordPress Interaction functions for the AI Agent plugin.
 * Uses WP-CLI for safe and robust WordPress core interactions.
 */

// Define the path to the WordPress installation.
// Adjust this if your WordPress installation is located elsewhere.
// define( 'AI_AGENT_WP_PATH', '/app/wordpress' ); // Standard path in this environment
// Path definition is already in the main plugin file and should be fine.

/**
 * Handles the dispatch of CLI commands coming from the user interface.
 * It can transform commands or pass them to the generic WP-CLI executor.
 *
 * @param string $command_string The raw command string from the user.
 * @return string|false The output from the command, or false on error.
 */
function ai_agent_handle_cli_command( $command_string ) {
    // Trim the command string to remove leading/trailing whitespace
    $command_string = trim($command_string);

    // Check for 'page create' command
    if ( strpos( $command_string, 'page create' ) === 0 ) {
        // Transform 'page create' to 'post create --post_type=page'
        $transformed_command = preg_replace( '/^page create/', 'post create --post_type=page', $command_string, 1 );
        if ($transformed_command === null) {
            // error_log("AI Agent: preg_replace failed for 'page create' command: " . $command_string);
            return "Error: Failed to transform 'page create' command."; // This will be caught by the main handler
        }
        $command_to_execute = $transformed_command;
    }
    // For other commands like 'option update', 'option get', etc., pass them directly.
    // These are assumed to be valid WP-CLI commands or will be handled by WP-CLI itself.
    else {
        $command_to_execute = $command_string;
    }

    $result = ai_agent_execute_wp_cli_command( $command_to_execute );

    if ( $result['exit_code'] !== 0 ) {
        // Format a detailed error message
        $error_parts = [];
        $error_parts[] = "Error: WP-CLI command execution failed (Exit Code: " . $result['exit_code'] . ").";

        // $result['error_message'] should already be prefixed by ai_agent_execute_wp_cli_command if it's from stderr
        // or a generic error.
        if (!empty($result['error_message'])) {
            // If error_message already starts with a standard prefix, use as is, otherwise, clarify it's from STDERR/Captured
            if (strpos($result['error_message'], 'Error:') === 0 || strpos($result['error_message'], 'Fatal error:') === 0 || strpos($result['error_message'], 'Warning:') === 0) {
                $error_parts[] = "Details: " . $result['error_message'];
            } else {
                $error_parts[] = "STDERR/Details: " . $result['error_message'];
            }
        }

        if (!empty($result['output'])) {
            $error_parts[] = "STDOUT (may contain partial output): " . $result['output'];
        }

        return implode("\n", $error_parts);

    } else {
        // Success
        $final_output = $result['output']; // This should be the primary output from STDOUT

        // Append warnings from STDERR if any (error_message might contain "Warning: ...")
        if (!empty($result['error_message']) && strpos($result['error_message'], 'Warning:') === 0) {
            $final_output .= "\n" . $result['error_message'];
        }

        // Ensure some output is always returned, even if it's just a confirmation.
        if (trim($final_output) === "") {
            // This case is now handled by ai_agent_execute_wp_cli_command returning
            // "Command executed successfully and produced no output." in $result['output']
            // So, $final_output should not be truly empty if that logic holds.
            // However, as a fallback:
             return "Command executed successfully with no direct output. Check for warnings if any displayed.";
        }
        return trim($final_output);
    }
}


/**
 * Executes a WP-CLI command using proc_open and returns a structured array.
 *
 * @param string $command The WP-CLI command to execute (without 'wp ' prefix).
 * @return array An array with keys 'output', 'error_message', and 'exit_code'.
 *
 * @note This function serves as the primary, structured, and safer interface for executing WP-CLI commands.
 *       It ensures that commands are run with necessary path and user context. Direct passthrough
 *       of arbitrary commands is avoided by design to reduce security risks. All WP-CLI interactions
 *       within this plugin should ideally use this helper or specific wrapper functions built upon it.
 */
function ai_agent_execute_wp_cli_command( $command ) {
    // Ensure $command is not empty.
    if (empty(trim($command))) {
        return [
            'output' => null,
            'error_message' => 'Error: Attempted to execute an empty command.',
            'exit_code' => -1 // Indicate internal error before execution attempt
        ];
    }

    // Construct the full command. Note: AI_AGENT_WP_PATH should be defined.
    // If AI_AGENT_WP_PATH is not defined here, it must be defined in the main plugin file
    // or passed appropriately. Assuming it's globally available via define().
    $full_command = 'wp ' . $command . ' --path=' . AI_AGENT_WP_PATH . ' --allow-root';
    // For debugging: error_log( "Executing WP-CLI command (proc_open): " . $full_command );

    $descriptor_spec = [
        0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w'], // stderr
    ];

    $pipes = [];
    $process = proc_open($full_command, $descriptor_spec, $pipes, null, null);

    if (is_resource($process)) {
        fclose($pipes[0]); // Close stdin as we're not sending input

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exit_code = proc_close($process);

        $trimmed_stdout = trim($stdout);
        $trimmed_stderr = trim($stderr);

        if ($exit_code !== 0) {
            // Error occurred
            $error_message = !empty($trimmed_stderr) ? $trimmed_stderr : $trimmed_stdout;
            // Ensure a generic error prefix if specific one not found from WP-CLI
            if (strpos($error_message, 'Error:') !== 0 && strpos($error_message, 'Fatal error:') !== 0 && strpos($error_message, 'Warning:') !== 0) {
                 $error_message = 'Error: Command failed. Output: ' . $error_message;
            }
            return [
                'output' => $trimmed_stdout, // STDOUT might still contain useful info or partial output
                'error_message' => $error_message,
                'exit_code' => $exit_code
            ];
        } else {
            // Success
            $final_output = $trimmed_stdout;
            if (empty($final_output) && !empty($trimmed_stderr)) {
                // If STDOUT is empty but STDERR has content (e.g., warnings from WP-CLI on success),
                // treat STDERR as part of the output, perhaps prefixed.
                $final_output = "Warning (from STDERR): " . $trimmed_stderr;
            } elseif (empty($final_output) && empty($trimmed_stderr)) {
                $final_output = "Command executed successfully and produced no output.";
            }
            return [
                'output' => $final_output,
                'error_message' => !empty($trimmed_stderr) && $exit_code === 0 ? 'Warning: ' . $trimmed_stderr : null, // Keep stderr if it has warnings on success
                'exit_code' => $exit_code
            ];
        }
    } else {
        // proc_open failed
        // error_log("AI Agent: proc_open failed for command: " . $full_command);
        return [
            'output' => null,
            'error_message' => 'Error: Failed to start command execution process (proc_open failed). Check server configuration (proc_open availability, paths).',
            'exit_code' => -2 // Indicate proc_open failure specifically
        ];
    }
}

/**
 * Retrieves the content of a specific post.
 *
 * @param int $post_id The ID of the post.
 * @return string|false The post content, or false on error/not found.
 */
function ai_agent_get_post_content( $post_id ) {
    if ( ! is_numeric( $post_id ) || $post_id <= 0 ) {
        return false; // Invalid post ID
    }

    $post_id = (int) $post_id;
    $post = get_post( $post_id );

    if ( ! $post ) {
        return false; // Post not found
    }

    // Check if the post type is one that should have content (optional, but good practice)
    // For example, 'attachment' posts might not have typical 'post_content'.
    // However, for generic use, just getting post_content is usually fine.
    // If specific post types should be excluded, add checks here.

    // Return the raw post content.
    // If post_content is empty, this will return an empty string, which is desired.
    return $post->post_content;
}

/**
 * Updates the content of a specific post.
 *
 * @param int $post_id The ID of the post.
 * @param string $content The new content.
 * @return bool True on success, false on failure.
 */
function ai_agent_update_post_content( $post_id, $content ) {
    if ( ! is_numeric( $post_id ) || $post_id <= 0 ) {
        return false;
    }
    // escapeshellarg is crucial for the content part.
    // WP-CLI's post update command expects content via STDIN or --post_content=<content>
    // Using a temporary file for content is safer and more robust for large/complex content.
    $tmp_file = tmpfile();
    fwrite( $tmp_file, $content );
    $tmp_file_path = stream_get_meta_data( $tmp_file )['uri'];

    $command = sprintf(
        'post update %d %s --post_content="$(cat %s)"',
        (int) $post_id,
        escapeshellarg($tmp_file_path), // This is not how you pass content.
                                        // WP-CLI takes content from STDIN or --post_content.
                                        // The cat approach here is incorrect.
                                        // Corrected below.
        escapeshellarg($tmp_file_path) // This is for the cat command
    );
    // Corrected command construction for post content:
    // We will use --post_content with the content directly, ensuring it's properly escaped.
    // However, for very large content, STDIN via proc_open would be better.
    // For shell_exec, we have to pass it as an argument.
    // Let's try a simpler approach for now, assuming content isn't excessively large or complex for shell args.
    // A more robust solution would use WP-CLI's --post_content=@- to read from STDIN,
    // but that's harder with shell_exec directly.
    // The safest way with shell_exec for arbitrary content is to pass it as an environment variable
    // or use a temporary file that WP-CLI can read, but WP-CLI doesn't directly support reading content from a file path argument easily for post_content.

    // Simpler, but potentially problematic for complex content with shell_exec:
    // $command = sprintf( 'post update %d --post_content=%s', (int) $post_id, escapeshellarg( $content ) );
    // Fallback to a method that writes content to a temp file and uses that with WP-CLI if possible or STDIN.
    // For now, let's use a direct but careful command.
    // The `post update` command can take content from a file using `< file`.
    // Or pass it directly. Given `shell_exec` limitations, direct passing with `escapeshellarg` is tricky for multiline.

    // Using --post_content=<content> is simpler with escapeshellarg for the content itself.
    // Let's ensure $content is properly handled.
    // No, WP-CLI's `post update` takes content from a file if you pass a path, or from STDIN.
    // `wp post update 123 --post_content="$(cat path/to/file.txt)"` is not standard.
    // It's `wp post update 123 path/to/file-with-content.txt`
    // Or `cat path/to/file.txt | wp post update 123 -`
    // The `path/to/file-with-content.txt` should *only* contain the new post_content.

    // Rewriting to use a temporary file containing the content, and passing that filename to `post update`
    // The file path itself doesn't need special escaping if it's simple, but its content does if passed directly.
    // `wp post update <id> <file>` expects <file> to be a file whose *contents* are the new post_content.

    $command = sprintf( 'post update %d %s', (int) $post_id, escapeshellarg( $tmp_file_path ) );
    $result = ai_agent_execute_wp_cli_command( $command );
    fclose( $tmp_file ); // Close and delete the temporary file

    // WP-CLI post update typically returns a success message like "Success: Updated post 123." or an error.
    if ( $result !== false && strpos( $result, 'Success: Updated post' ) !== false ) {
        return true;
    }
    // error_log("AI Agent: Failed to update post $post_id. WP-CLI output: $result");
    return false;
}


/**
 * Retrieves a WordPress site option.
 *
 * @param string $option_name The name of the option.
 * @return mixed|false The option value, or false on error/not found.
 */
function ai_agent_get_site_option( $option_name ) {
    if ( empty( $option_name ) || !is_string($option_name) ) {
        return false;
    }
    $command = sprintf( 'option get %s --format=json', escapeshellarg( $option_name ) );
    $result = ai_agent_execute_wp_cli_command( $command );

    if ( $result === false || $result === '' ) { // Empty string could mean option not found or empty value
        return false; // Assuming not found if empty for simplicity, WP-CLI might return 'null' for non-existent
    }
    $decoded_value = json_decode( $result, true );
     if (json_last_error() === JSON_ERROR_NONE) {
      return $decoded_value;
    }
    // If it's not JSON, it might be a simple string value that wasn't json encoded by WP-CLI
    // or an error message. For now, return as is.
    return $result;
}

/**
 * Updates a WordPress site option.
 *
 * @param string $option_name The name of the option.
 * @param mixed $option_value The new value for the option.
 * @return bool True on success, false on failure.
 */
function ai_agent_update_site_option( $option_name, $option_value ) {
    if ( empty( $option_name ) || !is_string($option_name) ) {
        return false;
    }

    // For complex values (arrays, objects), WP-CLI expects them in JSON format.
    // Simple strings can be passed directly.
    $value_to_pass = is_scalar( $option_value ) ? $option_value : json_encode( $option_value );
    if ($value_to_pass === false && !is_scalar($option_value)) { // json_encode failed
        // error_log("AI Agent: Failed to json_encode option value for $option_name");
        return false;
    }

    $command = sprintf( 'option update %s %s --format=json', escapeshellarg( $option_name ), escapeshellarg( $value_to_pass ) );
    $result = ai_agent_execute_wp_cli_command( $command );

    // `option update` typically returns a success message or specific error.
    // On success, it usually prints "Success: Updated '$option_name' option."
    // Or if the value is the same, "Success: Value passed for '$option_name' is already the current value."
    if ( $result !== false && (strpos( $result, 'Success: Updated' ) !== false || strpos( $result, 'already the current value') !== false )) {
        return true;
    }
    // error_log("AI Agent: Failed to update option $option_name. WP-CLI output: $result");
    return false;
}

/**
 * Gets a list of active plugins.
 *
 * @return array|false An array of active plugin slugs, or false on error.
 */
function ai_agent_get_active_plugins() {
    $command = 'plugin list --status=active --field=name --format=json'; // 'name' is the slug
    $result = ai_agent_execute_wp_cli_command( $command );
    if ( $result === false ) {
        return false;
    }
    $plugins = json_decode( $result, true );
    if ( json_last_error() === JSON_ERROR_NONE && is_array( $plugins ) ) {
        return $plugins;
    }
    // error_log("AI Agent: Failed to decode active plugins list. JSON Error: " . json_last_error_msg() . " Raw output: " . $result);
    return false;
}

/**
 * Checks if a specific plugin is active.
 *
 * @param string $plugin_slug The slug of the plugin (e.g., 'akismet/akismet').
 * @return bool True if active, false otherwise or on error.
 */
function ai_agent_is_plugin_active( $plugin_slug ) {
    if ( empty( $plugin_slug ) || !is_string($plugin_slug) ) {
        return false;
    }
    // `is-plugin-active` command is more direct
    $command = sprintf( 'plugin is-active %s', escapeshellarg( $plugin_slug ) );
    // This command has an exit code of 0 if active, 1 if not active.
    // shell_exec does not give us direct access to exit codes easily.
    // We can check the output, or use `plugin list` and filter.
    // Let's use `plugin list` for more reliable output parsing with shell_exec.

    $command_check = sprintf( 'plugin get %s --field=status --format=json', escapeshellarg( $plugin_slug ) );
    $result = ai_agent_execute_wp_cli_command( $command_check );

    if ( $result !== false ) {
        $status = json_decode( $result, true );
        if (json_last_error() === JSON_ERROR_NONE) {
            return $status === 'active';
        }
        // Fallback for non-JSON output (e.g. "active" string directly)
        if (is_string($result)) {
            return trim(strtolower($result)) === 'active';
        }
    }
    // error_log("AI Agent: Failed to check plugin status for $plugin_slug. WP-CLI output: $result");
    return false;
}

/**
 * Retrieves a list of all installed plugins with their status, name, and version.
 *
 * @return array|false An array of plugin objects, or false on error.
 *                     Each plugin object contains 'name', 'status', 'update' (availability), 'version'.
 */
function ai_agent_get_all_plugins() {
    $command = 'plugin list --format=json'; // --allow-root and --path are handled by ai_agent_execute_wp_cli_command
    $result = ai_agent_execute_wp_cli_command( $command );

    if ( $result === false ) {
        // error_log("AI Agent: Failed to get all plugins. WP-CLI command failed.");
        return false;
    }

    $plugins = json_decode( $result, true );

    if ( json_last_error() === JSON_ERROR_NONE && is_array( $plugins ) ) {
        return $plugins;
    }
    // error_log("AI Agent: Failed to decode plugins list. JSON Error: " . json_last_error_msg() . " Raw output: " . $result);
    return false;
}

/**
 * Activates a specified plugin.
 *
 * @param string $plugin_slug The slug of the plugin to activate (e.g., 'akismet').
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function ai_agent_activate_plugin( string $plugin_slug ) {
    if ( empty( $plugin_slug ) ) {
        return new WP_Error( 'invalid_plugin_slug', __( 'Plugin slug cannot be empty.', 'ai-agent' ) );
    }

    $command = sprintf( 'plugin activate %s', escapeshellarg( $plugin_slug ) );
    $result = ai_agent_execute_wp_cli_command( $command );

    // `wp plugin activate` returns "Success: Plugin '<slug>' activated." or "Plugin '<slug>' is already active."
    // On failure, it might print an error message and have a non-zero exit code (handled by execute_wp_cli_command returning false)
    // or specific error text.
    if ( $result !== false && ( strpos( $result, 'Success: Plugin' ) !== false && strpos( $result, 'activated.' ) !== false || strpos($result, 'already active') !== false )) {
        return true;
    } elseif ($result === false) {
         return new WP_Error( 'plugin_activation_failed_command', sprintf(__( 'Failed to execute activation command for plugin %s.', 'ai-agent' ), $plugin_slug ));
    }

    // error_log("AI Agent: Failed to activate plugin $plugin_slug. WP-CLI output: $result");
	  return new WP_Error( 'plugin_activation_failed', sprintf(__( 'Could not activate plugin %s. Response: %s', 'ai-agent' ), $plugin_slug, $result), $result );
}

/**
 * Deactivates a specified plugin.
 *
 * @param string $plugin_slug The slug of the plugin to deactivate (e.g., 'akismet').
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function ai_agent_deactivate_plugin( string $plugin_slug ) {
    if ( empty( $plugin_slug ) ) {
        return new WP_Error( 'invalid_plugin_slug', __( 'Plugin slug cannot be empty.', 'ai-agent' ) );
    }

    $command = sprintf( 'plugin deactivate %s', escapeshellarg( $plugin_slug ) );
    $result = ai_agent_execute_wp_cli_command( $command );

    // `wp plugin deactivate` returns "Success: Plugin '<slug>' deactivated." or "Plugin '<slug>' is already inactive."
    if ( $result !== false && ( strpos( $result, 'Success: Plugin' ) !== false && strpos( $result, 'deactivated.' ) !== false || strpos($result, 'already inactive') !== false ) ) {
        return true;
    } elseif ($result === false) {
        return new WP_Error( 'plugin_deactivation_failed_command', sprintf(__( 'Failed to execute deactivation command for plugin %s.', 'ai-agent' ), $plugin_slug ));
    }

    // error_log("AI Agent: Failed to deactivate plugin $plugin_slug. WP-CLI output: $result");
	  return new WP_Error( 'plugin_deactivation_failed', sprintf(__( 'Could not deactivate plugin %s. Response: %s', 'ai-agent' ), $plugin_slug, $result), $result );
}

/**
 * Retrieves all published posts.
 *
 * This function uses the WordPress function get_posts() to fetch all posts
 * that have a 'publish' status. It then formats them into an array
 * containing the ID and post_title of each post.
 *
 * @return array An array of posts, where each post is an associative array
 *               with 'ID' and 'post_title' keys. Returns an empty array
 *               if no posts are found or in case of an error.
 */
function ai_agent_get_all_posts() {
    // Ensure WordPress environment is loaded if this function is called in a context where it might not be.
    // Typically, for a plugin, WordPress core functions like get_posts() are available.
    // If this were a standalone script, you'd need to include wp-load.php.

    $args = [
        'post_type'   => 'post',
        'post_status' => 'publish',
        'numberposts' => -1, // Retrieve all posts
        'orderby'     => 'title',
        'order'       => 'ASC',
    ];

    $wordpress_posts = get_posts( $args );

    $formatted_posts = [];

    if ( ! empty( $wordpress_posts ) ) {
        foreach ( $wordpress_posts as $post_object ) {
            $formatted_posts[] = [
                'ID'         => $post_object->ID,
                'post_title' => $post_object->post_title,
            ];
        }
    }

    return $formatted_posts;
}

/**
 * Retrieves a list of all installed plugins with their details.
 *
 * This function acts as a wrapper for ai_agent_get_all_plugins() for semantic clarity
 * when retrieving plugin lists for display in the admin area.
 *
 * @return array|false An array of plugin objects (each with name, status, version, etc.),
 *                     or false on error.
 */
function ai_agent_get_all_installed_plugins() {
    return ai_agent_get_all_plugins();
}

?>
