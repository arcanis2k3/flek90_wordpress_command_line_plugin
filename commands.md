# Available WP-CLI Commands

This document lists the WP-CLI commands that are currently permitted for execution through the WP Command Line Interface plugin. For security reasons, only a predefined set of commands (primarily read-only) are allowed.

You can use these commands with their standard WP-CLI arguments and flags, as long as the base command matches one of the entries below.

## Allowed Command Bases:

*   `plugin list` - View installed plugins.
    *   Example: `plugin list --status=active`
*   `plugin get <plugin-name>` - View details for a specific plugin.
    *   Example: `plugin get akismet --fields=name,status,version`
*   `option get <option-name>` - Get the value of a site option.
    *   Example: `option get home`
    *   Example: `option get blogname --format=json`
*   `user list` - List users.
    *   Example: `user list --role=administrator`
*   `user get <user-login-or-id>` - Get details for a specific user.
    *   Example: `user get admin --fields=user_login,display_name,roles`
*   `theme list` - View installed themes.
    *   Example: `theme list --status=active`
*   `theme get <theme-name>` - View details for a specific theme.
    *   Example: `theme get twentytwentyone --fields=name,version,status`
*   `core version` - Display the WordPress version.
    *   Example: `core version --extra`
*   `site list` (for multisite) - List sites in a multisite network.
    *   Example: `site list --fields=blog_id,domain,path`
*   `post list` - List posts.
    *   Example: `post list --post_type=page --posts_per_page=5`
*   `comment list` - List comments.
    *   Example: `comment list --post_id=1 --number=10`
*   `page create` - Create a new page.
    *   Example: `page create --title="My Awesome Page"`
    *   Example: `page create --title="Draft Page" --status=draft`
    *   Example: `page create --title="Detailed Page" --status=publish --content="This is the content of my new page."`
*   `option update <option-name> <option-value>` - Update an existing site option.
    *   Example: `option update my_custom_option "New Value"`
    *   Example: `option update some_setting '{"enabled": true, "items": [1,2,3]}' --format=json` (Note: ensure JSON is valid)

## Important Notes:

*   **Security:** The command whitelist is in place to prevent accidental or malicious execution of potentially harmful commands. Additionally, commands can be individually activated or deactivated via the plugin's settings page in the WordPress admin area (under "WP Command Line" -> "General Settings").
*   **Parameters:** You can use most standard parameters and flags associated with these commands (e.g., `--status=active`, `--fields=name,version`, `--format=json`). However, ensure the base command (e.g., `plugin list`) matches exactly one of the enabled commands.
*   **Output:** The output from the command will be displayed directly in the interface. Some commands might produce verbose output.
*   **Updates:** This list may be updated in future versions of the plugin.
