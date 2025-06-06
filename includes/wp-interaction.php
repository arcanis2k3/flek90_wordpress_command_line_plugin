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
 * Handles 'core version' command using native WordPress functions.
 * @param array $args Associative array of arguments (e.g., for --extra, though not implemented via get_bloginfo).
 * @return string WordPress version string.
 */
function php_handle_core_version(array $args) {
    // The basic WordPress version. get_bloginfo('version') doesn't support --extra like WP-CLI.
    // If --extra functionality (DB version, etc.) is needed, this would require more specific calls
    // or sticking to WP-CLI for this specific flag. For now, just the version.
    if (isset($args['extra'])) {
        // Simulate WP-CLI --extra roughly: version, db_version, locale, site_url, home_url
        // This is a simplified example. WP-CLI does more.
        global $wp_db_version;
        $output_lines = [];
        $output_lines[] = "WordPress version: " . get_bloginfo('version');
        $output_lines[] = "Database revision: " . $wp_db_version;
        $output_lines[] = "Site language: " . get_locale();
        $output_lines[] = "Site URL: " . site_url();
        $output_lines[] = "Home URL: " . home_url();
        return implode("\n", $output_lines);
    }
    return get_bloginfo('version');
}


/**
 * Handles 'user list' command using native WordPress functions.
 * @param array $args Associative array of arguments for WP_User_Query.
 * @return string Formatted list of users or message.
 */
function php_handle_user_list(array $args) {
    $query_args = ['count_total' => false]; // Don't need total count for simple list display

    if (isset($args['role'])) {
        $query_args['role'] = $args['role'];
    }
    if (isset($args['number']) && is_numeric($args['number'])) {
        $query_args['number'] = intval($args['number']);
    } else {
        $query_args['number'] = 10; // Default to 10 users
    }
    if (isset($args['orderby'])) {
        $allowed_orderby = ['ID', 'login', 'nicename', 'email', 'url', 'registered', 'display_name', 'post_count', 'meta_value'];
        if (in_array($args['orderby'], $allowed_orderby)) {
            $query_args['orderby'] = $args['orderby'];
        }
    }
    if (isset($args['order']) && in_array(strtoupper($args['order']), ['ASC', 'DESC'])) {
        $query_args['order'] = strtoupper($args['order']);
    }
    if (isset($args['search'])) {
        // Allows searching for users by matching a term in their user_login, user_email, user_url, user_nicename, or display_name.
        $query_args['search'] = '*' . trim($args['search'], '*') . '*'; // Add wildcards for partial match
        $query_args['search_columns'] = ['user_login', 'user_email', 'user_url', 'user_nicename', 'display_name'];
    }
    // Add more supported args like 'fields', 'offset', 'paged' as needed.

    $users = get_users($query_args);
    $output_lines = [];

    if (empty($users)) {
        return "No users found matching criteria.";
    }

    foreach ($users as $user) {
        $output_lines[] = "ID: " . $user->ID;
        $output_lines[] = "Login: " . esc_html($user->user_login);
        $output_lines[] = "Display Name: " . esc_html($user->display_name);
        $output_lines[] = "Email: " . esc_html($user->user_email);
        $output_lines[] = "Roles: " . (!empty($user->roles) ? esc_html(implode(', ', $user->roles)) : '(none)');
        $output_lines[] = "---";
    }

    return implode("\n", $output_lines);
}

/**
 * Handles 'user get' command using native WordPress functions.
 * @param array $args Array containing the user identifier (ID, login, slug, or email).
 * @return string Formatted user details or error message.
 */
function php_handle_user_get(array $args) {
    if (empty($args[0]) || !is_string($args[0]) || trim($args[0]) === '') {
        return "Error: User ID, login, slug, or email required for 'user get'.";
    }
    $identifier = trim($args[0]);
    $user_data = null;

    if (is_numeric($identifier)) {
        $user_data = get_user_by('ID', intval($identifier));
    }
    if (!$user_data) {
        $user_data = get_user_by('login', $identifier);
    }
    if (!$user_data) {
        $user_data = get_user_by('slug', $identifier); // User slug is user_nicename
    }
    if (!$user_data) {
        $user_data = get_user_by('email', $identifier);
    }

    if (!$user_data) {
        return "Error: User '" . esc_html($identifier) . "' not found.";
    }

    $output_lines = [];
    $output_lines[] = "ID: " . $user_data->ID;
    $output_lines[] = "Login: " . esc_html($user_data->user_login);
    $output_lines[] = "Display Name: " . esc_html($user_data->display_name);
    $output_lines[] = "Nice Name (Slug): " . esc_html($user_data->user_nicename);
    $output_lines[] = "Email: " . esc_html($user_data->user_email);
    $output_lines[] = "Registered Date: " . esc_html($user_data->user_registered);
    $output_lines[] = "Roles: " . (!empty($user_data->roles) ? esc_html(implode(', ', $user_data->roles)) : '(none)');
    $output_lines[] = "Website: " . esc_html($user_data->user_url);
    // Add more fields as desired

    return implode("\n", $output_lines);
}


/**
 * Handles 'theme list' command using native WordPress functions.
 * @param array $args Associative array of arguments, e.g., ['status' => 'active'].
 * @return string Formatted list of themes or message.
 */
function php_handle_theme_list(array $args) {
    $all_themes = wp_get_themes(['allowed' => null]); // Get all themes
    $output_lines = [];
    $requested_status = isset($args['status']) ? strtolower(trim($args['status'])) : null;
    $current_theme_stylesheet = get_stylesheet();

    if (empty($all_themes)) {
        return "No themes installed.";
    }

    foreach ($all_themes as $theme_slug => $theme_obj) {
        $status_str = ($theme_slug === $current_theme_stylesheet) ? 'Active' : 'Inactive';

        if ($requested_status) {
            if ($requested_status === 'active' && $status_str !== 'Active') {
                continue;
            }
            if ($requested_status === 'inactive' && $status_str !== 'Inactive') {
                continue;
            }
        }

        $output_lines[] = "Name: " . $theme_obj->get('Name');
        $output_lines[] = "Slug: " . $theme_slug; // Stylesheet slug
        $output_lines[] = "Status: " . $status_str;
        $output_lines[] = "Version: " . $theme_obj->get('Version');
        $output_lines[] = "---";
    }

    if (empty($output_lines)) {
        return "No themes found matching criteria" . ($requested_status ? " (status: " . esc_html($requested_status) . ")" : "") . ".";
    }

    return implode("\n", $output_lines);
}

/**
 * Handles 'theme activate' command using native WordPress functions.
 * @param array $args Array containing the theme's stylesheet slug.
 * @return string Success, notice, or error message.
 */
function php_handle_theme_activate(array $args) {
    if (empty($args[0]) || !is_string($args[0]) || trim($args[0]) === '') {
        return "Error: Theme stylesheet (slug) required for activation.";
    }
    $theme_to_activate_slug = trim($args[0]);

    $theme = wp_get_theme($theme_to_activate_slug);
    if (!$theme->exists()) {
        return "Error: Theme '" . esc_html($theme_to_activate_slug) . "' does not exist.";
    }
    if (!$theme->is_allowed()) {
        // This checks for network enabled themes in multisite, or broken themes.
        return "Error: Theme '" . esc_html($theme_to_activate_slug) . "' is not allowed for activation (possibly broken or not network-enabled).";
    }

    if (get_stylesheet() === $theme_to_activate_slug) {
        return "Notice: Theme '" . esc_html($theme_to_activate_slug) . "' is already active.";
    }

    try {
        switch_theme($theme_to_activate_slug);
        // The 'after_switch_theme' action is hooked by WordPress core after switch_theme completes.
        // Explicitly calling it here is generally not necessary unless specific re-initialization is needed
        // outside of the standard theme switch hooks. For most cases, switch_theme() is sufficient.
        // do_action('after_switch_theme', $theme->get_stylesheet(), $theme);

    } catch (Exception $e) {
        return "Error: Failed to activate theme '" . esc_html($theme_to_activate_slug) . "'. Exception: " . $e->getMessage();
    }

    // Verify activation
    if (get_stylesheet() === $theme_to_activate_slug) {
        return "Success: Theme '" . esc_html($theme_to_activate_slug) . "' activated.";
    } else {
        // This state might indicate a problem if switch_theme didn't throw an error but also didn't switch.
        // Or if the theme switch caused a fatal error that broke the current request flow before this check.
        return "Error: Failed to activate theme '" . esc_html($theme_to_activate_slug) . "'. Current active theme is still '" . esc_html(get_stylesheet()) . "'. Check for errors during theme switching.";
    }
}


/**
 * Ensures plugin functions are available.
 */
function ensure_plugin_functions_loaded() {
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
}

/**
 * Handles 'plugin list' command using native WordPress functions.
 * @param array $args Associative array of arguments, e.g., ['status' => 'active'].
 * @return string Formatted list of plugins or message.
 */
function php_handle_plugin_list(array $args) {
    ensure_plugin_functions_loaded();
    $all_plugins = get_plugins();
    $output_lines = [];
    $requested_status = isset($args['status']) ? strtolower(trim($args['status'])) : null;

    if (empty($all_plugins)) {
        return "No plugins installed.";
    }

    foreach ($all_plugins as $plugin_file => $plugin_data) {
        $status_str = 'Inactive';
        $is_active = is_plugin_active($plugin_file);
        if ($is_active) {
            $status_str = 'Active';
        }
        // TODO: Add more detailed status like 'must-use', 'dropins' if necessary.

        if ($requested_status) {
            if ($requested_status === 'active' && !$is_active) {
                continue;
            }
            if ($requested_status === 'inactive' && $is_active) {
                continue;
            }
            // Add more status filters if needed
        }

        $output_lines[] = "Name: " . $plugin_data['Name'];
        $output_lines[] = "Status: " . $status_str;
        $output_lines[] = "Version: " . $plugin_data['Version'];
        $output_lines[] = "Plugin File: " . $plugin_file; // Useful for activate/deactivate
        $output_lines[] = "---";
    }

    if (empty($output_lines)) {
        return "No plugins found matching criteria" . ($requested_status ? " (status: " . esc_html($requested_status) . ")" : "") . ".";
    }

    return implode("\n", $output_lines);
}

/**
 * Handles 'plugin activate' command using native WordPress functions.
 * @param array $args Array containing the plugin slug/file.
 * @return string Success, notice, or error message.
 */
function php_handle_plugin_activate(array $args) {
    ensure_plugin_functions_loaded();
    if (empty($args[0]) || !is_string($args[0]) || trim($args[0]) === '') {
        return "Error: Plugin slug/file (e.g., 'akismet/akismet.php') required for activation.";
    }
    $plugin_to_activate = trim($args[0]);

    if (is_plugin_active($plugin_to_activate)) {
        return "Notice: Plugin '" . esc_html($plugin_to_activate) . "' is already active.";
    }

    // The activate_plugin function expects the plugin path relative to the plugins directory.
    // Example: 'akismet/akismet.php'
    // The third parameter `true` makes it silent (no redirects or output).
    $result = activate_plugin($plugin_to_activate, '', false, true);

    if (is_wp_error($result)) {
        return "Error: Failed to activate plugin '" . esc_html($plugin_to_activate) . "'. " . $result->get_error_message();
    }
    // activate_plugin returns null on success.
    if ($result === null) {
         if (is_plugin_active($plugin_to_activate)) { // Double check
            return "Success: Plugin '" . esc_html($plugin_to_activate) . "' activated.";
        } else {
            // This case might happen if the plugin had a fatal error on activation that activate_plugin didn't catch as WP_Error
            return "Error: Failed to activate plugin '" . esc_html($plugin_to_activate) . "'. The plugin did not activate as expected.";
        }
    }
    // If $result is false, it means it was already active, but we checked this already.
    // However, keeping a fallback.
    return "Notice: Plugin '" . esc_html($plugin_to_activate) . "' might already be active or an unknown issue occurred.";
}

/**
 * Handles 'plugin deactivate' command using native WordPress functions.
 * @param array $args Array containing the plugin slug/file.
 * @return string Success, notice, or error message.
 */
function php_handle_plugin_deactivate(array $args) {
    ensure_plugin_functions_loaded();
    if (empty($args[0]) || !is_string($args[0]) || trim($args[0]) === '') {
        return "Error: Plugin slug/file (e.g., 'akismet/akismet.php') required for deactivation.";
    }
    $plugin_to_deactivate = trim($args[0]);

    if (!is_plugin_active($plugin_to_deactivate)) {
        return "Notice: Plugin '" . esc_html($plugin_to_deactivate) . "' was already inactive.";
    }

    // The second parameter `true` makes it silent.
    deactivate_plugins($plugin_to_deactivate, true, null);

    // Verify deactivation
    if (!is_plugin_active($plugin_to_deactivate)) {
        return "Success: Plugin '" . esc_html($plugin_to_deactivate) . "' deactivated.";
    } else {
        return "Error: Failed to deactivate plugin '" . esc_html($plugin_to_deactivate) . "'. The plugin might still be active.";
    }
}


/**
 * Parses a string of CLI-like arguments into an associative array.
 * Handles --key=value, --key="quoted value", and --flag.
 *
 * @param string $arg_string The string of arguments.
 * @return array Associative array of arguments.
 */
function parse_cli_like_args($arg_string) {
    $args = [];
    // Regex to capture --key=value, --key="value with spaces", or --flag
    // --(\w[-\w]*)             : Captures the key (e.g., title, post-status)
    // (?:                       : Start of non-capturing group for the value part
    //   =                       : Equals sign
    //   (?:"([^"]*)"|([^"\s]*)) : Either a double-quoted string (capture content in group 3)
    //                           : OR an unquoted string without spaces (capture in group 4)
    // )?                        : The entire value part is optional (for flags)
    preg_match_all('/--(\w[-\w]*)(?:=(?:"([^"]*)"|([^"\s]*)))?/', $arg_string, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $key = $match[1];
        // Value is in $match[2] (quoted) or $match[3] (unquoted). If neither, it's a flag (true).
        if (isset($match[2]) && $match[2] !== '') { // Quoted value
            $args[$key] = $match[2];
        } elseif (isset($match[3]) && $match[3] !== '') { // Unquoted value
            $args[$key] = $match[3];
        } else { // Flag
            $args[$key] = true;
        }
    }
    return $args;
}

/**
 * Handles 'page create' command using native WordPress functions.
 * @param array $args Associative array of page arguments (e.g., from parse_cli_like_args).
 * @return string Success or error message.
 */
function php_handle_page_create(array $args) {
    if (empty($args['title']) || !is_string($args['title']) || trim($args['title']) === '') {
        return "Error: --title is required and cannot be empty for page creation.";
    }

    $post_data = ['post_type' => 'page'];
    $post_data['post_title'] = trim($args['title']);

    if (isset($args['content']) && is_string($args['content'])) {
        $post_data['post_content'] = $args['content'];
    }

    $allowed_statuses = ['publish', 'draft', 'pending', 'private'];
    if (isset($args['status']) && is_string($args['status']) && in_array(strtolower($args['status']), $allowed_statuses, true)) {
        $post_data['post_status'] = strtolower($args['status']);
    } else {
        $post_data['post_status'] = 'draft'; // Default status
    }

    if (isset($args['author'])) {
        if (is_numeric($args['author'])) {
            $user = get_user_by('ID', (int)$args['author']);
            if ($user) {
                $post_data['post_author'] = $user->ID;
            } else {
                return "Error: Author ID '" . esc_html($args['author']) . "' not found.";
            }
        } else if (is_string($args['author'])) {
            $user = get_user_by('login', $args['author']) ?: get_user_by('email', $args['author']);
            if ($user) {
                $post_data['post_author'] = $user->ID;
            } else {
                return "Error: Author login/email '" . esc_html($args['author']) . "' not found.";
            }
        }
    } else {
        $post_data['post_author'] = get_current_user_id(); // Default to current user
    }

    if (isset($args['date']) && is_string($args['date'])) {
        $timestamp = strtotime($args['date']);
        if ($timestamp) {
            $post_data['post_date'] = get_date_from_gmt(date('Y-m-d H:i:s', $timestamp), 'Y-m-d H:i:s');
        } else {
            return "Error: Invalid date format for --date. Please use a recognizable date/time string.";
        }
    }

    if (isset($args['slug']) && is_string($args['slug'])) {
        $post_data['post_name'] = sanitize_title($args['slug']);
    }

    // Example for a custom field if needed, e.g. --meta_key=field_name --meta_value=value
    // if (isset($args['meta_key']) && isset($args['meta_value'])) {
    //     $post_data['meta_input'] = [$args['meta_key'] => $args['meta_value']];
    // }

    $post_id = wp_insert_post($post_data, true); // true for WP_Error on failure

    if (is_wp_error($post_id)) {
        return "Error: Failed to create page. " . $post_id->get_error_message();
    } else {
        $page_link = get_permalink($post_id);
        return "Success: Page created with ID {$post_id}. Link: {$page_link}";
    }
}


/**
 * Handles 'option get' command using native WordPress functions.
 * @param array $args Array containing the option name.
 * @return string Formatted option value or error/notice message.
 */
function php_handle_option_get(array $args) {
    if (count($args) !== 1 || empty(trim($args[0]))) {
        return "Error: `option get` requires exactly one non-empty argument: option_name.";
    }
    $option_name = trim($args[0]);

    // Check if option exists
    $default_value_for_check = uniqid('__non_existent_option_check_');
    $value = get_option($option_name, $default_value_for_check);

    if ($value === $default_value_for_check) {
        return "Notice: Option '" . esc_html($option_name) . "' not found.";
    }

    // Format output value
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    } elseif (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } elseif (is_null($value)) {
        return '(null)'; // Or an empty string like ''
    } else {
        return (string)$value;
    }
}

/**
 * Handles 'option update' command using native WordPress functions.
 * @param array $args Array containing option name and value string.
 * @return string Success, notice, or error message.
 */
function php_handle_option_update(array $args) {
    if (count($args) < 1 || empty(trim($args[0]))) {
        return "Error: `option update` requires at least an option_name.";
    }
    if (count($args) < 2) {
        // Assuming an empty string value is intentional if only option_name is provided by parser
        // This case might be hit if parser sends [option_name, ""]
        // But if parser sends only [option_name], it's an arg count error for value.
        // The current parser logic in ai_agent_handle_cli_command will ensure 2 args,
        // where the second can be an empty string.
        // So, this specific check might be redundant if parser guarantees two elements.
        // For safety:
        return "Error: `option update` requires option_name and option_value. To set an empty string, provide it explicitly (e.g., option update my_option \"\").";
    }

    $option_name = trim($args[0]);
    $option_value_string = $args[1]; // Value string is the second element

    $option_value;
    $trimmed_val_str = trim($option_value_string);

    if (strtolower($trimmed_val_str) === 'true') {
        $option_value = true;
    } elseif (strtolower($trimmed_val_str) === 'false') {
        $option_value = false;
    } elseif (strtolower($trimmed_val_str) === 'null') {
        $option_value = null; // WordPress will often convert this to "" when saving if option is expected to be string.
    } elseif (strval(intval($trimmed_val_str)) === $trimmed_val_str && is_numeric($trimmed_val_str)) { // Check if it's an integer string
        $option_value = intval($trimmed_val_str);
    } elseif (strval(floatval($trimmed_val_str)) === $trimmed_val_str && is_numeric($trimmed_val_str)) { // Check if it's a float string
        $option_value = floatval($trimmed_val_str);
    } elseif ( (strpos($trimmed_val_str, '[') === 0 || strpos($trimmed_val_str, '{') === 0) &&
               ($decoded = json_decode($option_value_string, true)) !== null &&
               json_last_error() === JSON_ERROR_NONE ) {
        // Only attempt JSON decode if it starts with [ or { and is valid JSON
        $option_value = $decoded;
    } else {
        $option_value = $option_value_string; // Treat as a plain string (wp_unslash might be needed if WP adds slashes)
                                             // update_option handles sanitization and slashing.
    }

    // Note: WordPress's update_option() function handles the data serialization (e.g., for arrays/objects) itself.
    // It also handles sanitization for core options, but custom options are saved as is.
    $current_value = get_option($option_name); // Get current value to compare for "no change" scenario

    if (get_option($option_name, $default_value_for_check = uniqid()) === $default_value_for_check && $current_value === $option_value) {
        // This means the option does not exist, and we are trying to set it to its default value (e.g. false for non-existent option)
        // or trying to set a new option to a value that get_option would return for a non-existent option (e.g. null if default isn't specified)
        // update_option will return true if it "adds" the option, even if value is same as default for non-existent.
        // Let's just rely on update_option's return.
    }

    $updated = update_option($option_name, $option_value);

    if ($updated) {
        return "Success: Option '" . esc_html($option_name) . "' updated.";
    } else {
        // Check if the value was the same. update_option returns false if value is not changed.
        // Need to be careful with type comparisons (e.g. "0" vs 0 vs false)
        $newly_retrieved_value = get_option($option_name); // get it again to be sure of type post-save attempt
        if ($newly_retrieved_value == $option_value) { // Use loose comparison to handle type juggling by WP
             // Consider strict comparison if types are critical and known.
             // For example, '0' == 0 is true. If $option_value was int 0 and saved as string '0'.
             // A more robust check might involve serializing both and comparing if they are arrays/objects.
             // For scalar values, this loose comparison is often what WP does.
            return "Notice: Option '" . esc_html($option_name) . "' value unchanged. It was already set to the provided value.";
        }
        return "Error: Option '" . esc_html($option_name) . "' not updated. An error may have occurred, or the option is protected.";
    }
}


/**
 * Handles the dispatch of CLI commands coming from the user interface.
 * It can transform commands or pass them to the generic WP-CLI executor.
 *
 * @param string $command_string The raw command string from the user.
 * @return string The output from the command, or an error/notice string.
 */
function ai_agent_handle_cli_command( $command_string ) {
    $command_string = trim($command_string);

    // Handle 'help' command
    if (strtolower($command_string) === 'help' || strtolower($command_string) === '/help') {
        if (function_exists('wpcli_get_all_manageable_commands')) {
            $commands = wpcli_get_all_manageable_commands();
            $output = "WP Command Line Interface Plugin\n";
            $output .= "---------------------------------\n";
            $output .= "This plugin provides a command-line interface to execute WordPress commands directly from the admin area.\n";
            $output .= "Type a command and hit enter. For commands that take arguments, include them after the command (e.g., 'option get siteurl').\n\n";
            $output .= "Available commands:\n";
            foreach ($commands as $key => $description) {
                $output .= "- " . $key . ": " . $description . "\n";
            }
            $output .= "\nFor more details on command arguments and usage, please refer to the plugin's README.md file.";
            return rtrim($output); // rtrim in case of any accidental trailing newline from loop, though the final string concatenation should be fine.
        } else {
            return "Error: Help information is currently unavailable (cannot access command list).";
        }
    }
    // Try to parse 'option get <name>' or 'option update <name> <value_string>'
    elseif (preg_match('/^option\s+(get|update)\s+([^\s]+)(?:\s*(.*))?$/s', $command_string, $matches)) {
        // ... (existing option handling logic) ...
        $sub_command_action = strtolower($matches[1]);
        $option_name = $matches[2];
        $value_string = isset($matches[3]) ? $matches[3] : null;

        if ($sub_command_action === 'get') {
            if ($value_string !== null && trim($value_string) !== '') {
                return "Error: `option get` received too many arguments. Usage: option get <option_name>";
            }
            return php_handle_option_get([$option_name]);
        } elseif ($sub_command_action === 'update') {
            $value_to_pass = $value_string ?? "";
            return php_handle_option_update([$option_name, $value_to_pass]);
        }
    }
    // Try to parse 'page create --arg1=val1 ...'
    elseif (preg_match('/^page\s+create(?:\s+(.*))?$/s', $command_string, $matches)) {
        // ... (existing page create handling logic) ...
        $arguments_string = $matches[1] ?? '';
        $parsed_args = parse_cli_like_args($arguments_string);
        return php_handle_page_create($parsed_args);
    }
    // Try to parse 'plugin list|activate|deactivate ...'
    elseif (preg_match('/^plugin\s+(list|activate|deactivate)(?:\s+(.*))?$/s', $command_string, $matches)) {
        // ... (existing plugin handling logic) ...
        $action = strtolower($matches[1]);
        $arguments_string = $matches[2] ?? '';

        if ($action === 'list') {
            $parsed_args = parse_cli_like_args($arguments_string);
            return php_handle_plugin_list($parsed_args);
        } elseif ($action === 'activate') {
            $plugin_slug_arg = trim($arguments_string);
            if (empty($plugin_slug_arg)) return "Error: Plugin slug/file required for activate.";
            return php_handle_plugin_activate([$plugin_slug_arg]);
        } elseif ($action === 'deactivate') {
            $plugin_slug_arg = trim($arguments_string);
            if (empty($plugin_slug_arg)) return "Error: Plugin slug/file required for deactivate.";
            return php_handle_plugin_deactivate([$plugin_slug_arg]);
        }
    }
    // Try to parse 'theme list|activate ...'
    elseif (preg_match('/^theme\s+(list|activate)(?:\s+(.*))?$/s', $command_string, $matches)) {
        // ... (existing theme handling logic) ...
        $action = strtolower($matches[1]);
        $arguments_string = $matches[2] ?? '';

        if ($action === 'list') {
            $parsed_args = parse_cli_like_args($arguments_string);
            return php_handle_theme_list($parsed_args);
        } elseif ($action === 'activate') {
            $theme_slug_arg = trim($arguments_string);
            if (empty($theme_slug_arg)) return "Error: Theme stylesheet (slug) required for activate.";
            return php_handle_theme_activate([$theme_slug_arg]);
        }
    }
    // Try to parse 'user list|get ...'
    elseif (preg_match('/^user\s+(list|get)(?:\s+(.*))?$/s', $command_string, $matches)) {
        // ... (existing user handling logic) ...
        $action = strtolower($matches[1]);
        $arguments_string = $matches[2] ?? '';

        if ($action === 'list') {
            $parsed_args = parse_cli_like_args($arguments_string);
            return php_handle_user_list($parsed_args);
        } elseif ($action === 'get') {
            $identifier_arg = trim($arguments_string);
            if (empty($identifier_arg)) return "Error: User identifier required for 'user get'.";
            return php_handle_user_get([$identifier_arg]);
        }
    }
    // Try to parse 'core version ...'
    elseif (preg_match('/^core\s+version(?:\s+(.*))?$/s', $command_string, $matches)) {
        $arguments_string = $matches[1] ?? '';
        $parsed_args = parse_cli_like_args($arguments_string);
        return php_handle_core_version($parsed_args);
    }
    // Fallback for commands not handled by specific PHP functions
    else {
        return "Error: Command '" . esc_html($command_string) . "' is not recognized or not supported by native PHP handlers. Only PHP-native commands are allowed.";
    }
    // Fallback if no conditions met (should ideally not be reached if logic is exhaustive for known command types)
    // This line was commented out in original, keeping it so for now.
    // return "Error: Unhandled command or internal logic error in ai_agent_handle_cli_command.";
    }

// WP-CLI execution via proc_open is being phased out.
// The function ai_agent_execute_wp_cli_command is now removed.
// All commands are expected to be handled by specific php_handle_* functions
// or return an error if not recognized.


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
