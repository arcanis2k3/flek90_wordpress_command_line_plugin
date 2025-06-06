# WP Command Line Interface

Provides a command-line interface to execute WP-CLI commands directly from the WordPress admin area.

## Overview

The WP Command Line Interface plugin integrates a terminal-like window into your WordPress admin dashboard, allowing you to run WP-CLI commands without needing SSH access to your server. This can be incredibly useful for quick checks, managing options, plugins, themes, users, and performing other WP-CLI operations.

## Key Features

*   **Toggleable CLI Window:** A clean, simple interface that can be opened or closed easily.
*   **Keyboard Shortcut:** Quickly access the CLI window using a configurable keyboard shortcut (defaults to `Ctrl+I`).
*   **WP-CLI Command Execution:** Run a variety of common WP-CLI commands. Examples include:
    *   `plugin list --status=active`
    *   `option get blogname`
    *   `page create --title="My New Page"`
    *   `theme list`
    *   `user get currentuser`
*   **Command Management:** Administrators can activate or deactivate specific commands via the plugin's settings page for enhanced security and control.
*   **Secure:** Nonce verification for AJAX requests and user capability checks (`manage_options`) are in place.

## Basic Usage Instructions

1.  **Opening the CLI:**
    *   **Toggle Button:** Click the "CLI" button located at the bottom-center of admin pages.
    *   **Keyboard Shortcut:** Press `Ctrl+I` (or your configured shortcut) to toggle the CLI window.

2.  **Using Commands:**
    *   Type your WP-CLI command into the input field at the bottom of the CLI window and press Enter or click the "Send" button.
    *   For a detailed list of available commands and examples, please refer to [commands.md](commands.md).
    *   Ensure that the command you wish to use is activated in the plugin's settings.

3.  **Configuration:**
    *   Navigate to the "WP Command Line" menu in your WordPress admin sidebar.
    *   Here you can:
        *   Enable or disable the main toggle button ("Styling" tab).
        *   Configure the keyboard shortcut ("Keyboard Shortcut" tab).
        *   Activate or deactivate specific commands ("General Settings" tab).

## Author

*   **Author:** flek90
*   **Author URI:** https://flek90.aureusz.com

## Installation

1.  Download the plugin ZIP file.
2.  Go to your WordPress admin dashboard -> Plugins -> Add New.
3.  Click "Upload Plugin" and select the ZIP file.
4.  Activate the plugin after installation.
Alternatively, you can manually upload the plugin folder to your `wp-content/plugins/` directory.

## Security Considerations

While this plugin aims to provide a convenient way to run WP-CLI commands, always be mindful of the commands you are executing, especially those that modify data or settings (`option update`, `page create`, etc.). The command activation feature allows administrators to limit the available commands to only those deemed safe and necessary for their workflow.
