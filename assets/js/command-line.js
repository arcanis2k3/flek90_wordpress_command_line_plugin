jQuery(document).ready(function($) {
    var $cliIcon = $('#wpcli-icon'); // Changed
    var $cliWindow = $('#wpcli-window'); // Changed
    var $closeBtn = $('#wpcli-close-btn'); // Changed
    var $sendBtn = $('#wpcli-send-btn'); // Changed
    var $cliInput = $('#wpcli-input'); // Changed
    var $cliMessages = $('#wpcli-messages'); // Changed

    // Function to safely escape HTML (simple version)
    function escapeHtml(text) {
        if (typeof text !== 'string') {
            text = String(text); // Ensure text is a string
        }
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Function to convert URLs to clickable links - potentially less relevant for command output
    function linkify(text) {
        if (typeof text !== 'string') {
            text = String(text);
        }
        // Simple URL regex, adjust if necessary for command output
        const urlRegex = /((https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])|(www\.[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
        return text.replace(urlRegex, function(url) {
            let fullUrl = url;
            if (url.toLowerCase().startsWith('www.')) {
                fullUrl = 'http://' + url;
            }
            return '<a href="' + escapeHtml(fullUrl) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(url) + '</a>';
        });
    }

    // Ensure this icon ID matches the one created in PHP if it's not in chat-window.html
    if ($cliIcon.length) {
        $cliIcon.on('click', function() {
            $cliWindow.toggle();
            if ($cliWindow.is(':visible')) {
                $cliInput.focus();
            }
        });
    } else {
        // console.log('WP CLI jQuery: CLI icon #wpcli-icon not found.');
    }

    if ($closeBtn.length) {
        $closeBtn.on('click', function() {
            $cliWindow.hide();
        });
    }

    function addMessageToOutput(message, type) { // Renamed function and parameter
        var messageClass = type === 'user' ? 'wpcli-user-command' : 'wpcli-server-response'; // Renamed classes

        // For command output, we might want to preserve whitespace and newlines faithfully.
        // Using <pre> or CSS white-space: pre-wrap; for the message content might be better.
        // For now, simple escaping, linkify might be optional or adjusted.
        var escapedMessage = escapeHtml(message);
        // var linkedMessage = linkify(escapedMessage); // Optional for command output

        var $messageDiv = $('<div class="wpcli-message"></div>').addClass(messageClass); // General class + specific
        var $messageContent = $('<span></span>').html(escapedMessage); // Using .html for now, consider <pre>

        $messageDiv.append($messageContent);
        $cliMessages.append($messageDiv);
        if ($cliMessages.length) {
            $cliMessages.scrollTop($cliMessages[0].scrollHeight);
        }
    }

    function sendCommand() { // Renamed function
        var commandText = $cliInput.val().trim(); // Renamed variable
        if (commandText === '') {
            return;
        }

        addMessageToOutput(commandText, 'user'); // Use new function name
        $cliInput.val('');
        $sendBtn.prop('disabled', true);

        // "Thinking..." message is removed
        // addMessageToOutput('Executing...', 'server thinking');

        $.ajax({
            url: wpcliCmdAjax.ajaxUrl, // Ensure this object name is localized in PHP
            type: 'POST',
            data: {
                action: 'wpcli_command_handler', // Changed action
                command: commandText, // Changed parameter name
                wpcli_nonce: wpcliCmdAjax.nonce, // Key changed to 'wpcli_nonce'
                // post_id is likely not relevant for a general WP-CLI command interface
            },
            success: function(response) {
                // $cliMessages.find('.thinking').remove(); // Removed

                if (response.success && response.data && typeof response.data.output !== 'undefined') {
                    addMessageToOutput(response.data.output, 'server'); // Use new function name, 'server' type
                } else if (response.data && response.data.message) {
                    addMessageToOutput('Error: ' + response.data.message, 'server');
                }
                // else if (response.data && response.data.reply) { // Old AI chat field
                //    addMessageToOutput('Error: ' + response.data.reply, 'server');
                // }
                else {
                    addMessageToOutput('Error: Received an invalid response from the server.', 'server');
                    // console.log('WP CLI jQuery: Invalid server response', response);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // $cliMessages.find('.thinking').remove(); // Removed
                addMessageToOutput('AJAX Error: ' + textStatus + ' - ' + errorThrown, 'server');
                // console.log('WP CLI jQuery: AJAX error', textStatus, errorThrown);
            },
            complete: function() {
                $sendBtn.prop('disabled', false);
                $cliInput.focus();
            }
        });
    }

    if ($sendBtn.length) {
        $sendBtn.on('click', sendCommand); // Use new function name
    }

    if ($cliInput.length) {
        $cliInput.on('keypress', function(e) {
            if (e.which === 13) { // Enter key pressed
                sendCommand(); // Use new function name
                e.preventDefault();
            }
        });
    }

    // Admin bar toggle logic removed as it's not part of this plugin's scope for now

    // Keyboard shortcut functionality
    function parseShortcut(shortcutString) {
        if (typeof shortcutString !== 'string' || shortcutString.trim() === '') {
            return null;
        }
        const parts = shortcutString.toLowerCase().split('+').map(p => p.trim());
        const shortcut = {
            ctrlKey: false,
            altKey: false,
            shiftKey: false,
            key: ''
        };

        for (const part of parts) {
            if (part === 'ctrl') shortcut.ctrlKey = true;
            else if (part === 'alt') shortcut.altKey = true;
            else if (part === 'shift') shortcut.shiftKey = true;
            else if (part.length === 1) shortcut.key = part;
            else return null; // Invalid part
        }

        if (!shortcut.key) return null; // Key is mandatory
        return shortcut;
    }

    let configuredShortcutString = (typeof wpcliCmdAjax !== 'undefined' && wpcliCmdAjax.shortcut) ? wpcliCmdAjax.shortcut : 'ctrl+i';
    let activeShortcut = parseShortcut(configuredShortcutString);

    if (!activeShortcut) {
        // console.log('WP CLI jQuery: Invalid or empty shortcut configured ("' + configuredShortcutString + '"), defaulting to ctrl+i.');
        activeShortcut = parseShortcut('ctrl+i');
    }

    if (activeShortcut) {
        $(document).on('keydown', function(e) {
            // Normalize event.key for older browsers if necessary, though 'i', 'k', etc. are standard
            const keyPressed = e.key.toLowerCase();

            if (
                e.ctrlKey === activeShortcut.ctrlKey &&
                e.altKey === activeShortcut.altKey &&
                e.shiftKey === activeShortcut.shiftKey &&
                keyPressed === activeShortcut.key
            ) {
                e.preventDefault();
                $cliWindow.toggle();
                if ($cliWindow.is(':visible')) {
                    $cliInput.focus();
                }
            }
        });
    } else {
        // This should ideally not happen if default is correctly parsed
        // console.log('WP CLI jQuery: Failed to set up keyboard shortcut.');
    }
});
