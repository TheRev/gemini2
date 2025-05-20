jQuery(document).ready(function($) {
    $('#gemini-trigger').on('click', function() {
        var $button = $(this);
        var $statusMessage = $('#gemini-status-message'); // Span for messages

        // Check if post_id is available using the new localized object name
        if (!geminiAdminAjax.post_id || geminiAdminAjax.post_id === 0) {
            $statusMessage.text('Error: Post ID not available. Please save the post and try again.').css('color', 'red');
            console.error("Gemini AJAX Error: Post ID is missing or zero.", geminiAdminAjax);
             // Clear status message after a few seconds
            setTimeout(function() {
                $statusMessage.text('').css('color', '');
            }, 7000);
            return; // Stop execution if no post_id
        }

        $button.text('Generating...').prop('disabled', true);
        $statusMessage.text('Processing...').css('color', ''); // Clear previous status color

        $.ajax({
            url: geminiAdminAjax.ajax_url, // Use new localized object name
            type: 'POST',
            data: {
                action: 'gemini2_generate_description', // Updated AJAX action
                nonce: geminiAdminAjax.nonce,        // Use new localized object name (nonce value comes from here)
                post_id: geminiAdminAjax.post_id     // Use new localized object name
            },
            success: function(response) {
                if (response.success && response.data.html_content) {
                    var htmlToInsert = response.data.html_content;
                    var inserted = false;

                    // Try Gutenberg first
                    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.select('core/editor')) {
                        try {
                            const { getBlocks, resetBlocks, insertBlocks } = wp.data.dispatch('core/editor');
                            const { getBlocks: getCurrentBlocks } = wp.data.select('core/editor');
                            
                            const currentBlocks = getCurrentBlocks();
                            // Ensure wp.blocks.rawHandler is available and HTML is valid
                            const newBlocks = wp.blocks.rawHandler({ HTML: htmlToInsert });
                            
                            if (newBlocks && newBlocks.length > 0) {
                                if (currentBlocks.length === 0 || 
                                    (currentBlocks.length === 1 && currentBlocks[0].name === 'core/paragraph' && currentBlocks[0].attributes.content === '')) {
                                    resetBlocks(newBlocks);
                                } else {
                                    insertBlocks(newBlocks);
                                }
                                inserted = true;
                            } else {
                                console.warn("Gemini: rawHandler produced no blocks from HTML. This might be an issue with the HTML structure or wp.blocks.rawHandler.");
                            }
                        } catch (e) {
                            console.error("Error inserting into Gutenberg: ", e);
                        }
                    }
                    
                    // Try TinyMCE (Classic Editor) if not inserted
                    if (!inserted && typeof tinymce !== 'undefined' && tinymce.get('content')) {
                        try {
                            tinymce.get('content').execCommand('mceInsertContent', false, htmlToInsert);
                            inserted = true;
                        } catch (e) {
                             console.error("Error inserting into TinyMCE: ", e);
                        }
                    }
                    
                    // Fallback to appending to the raw #content textarea if still not inserted
                    if (!inserted) {
                        var $classicEditorTextarea = $('#content'); 
                        if ($classicEditorTextarea.length && $classicEditorTextarea.is('textarea')) { // Ensure it's a textarea
                            var currentVal = $classicEditorTextarea.val();
                            $classicEditorTextarea.val(currentVal + "\n\n" + htmlToInsert); 
                            inserted = true;
                           $statusMessage.text('Appended to text editor. Review formatting.').css('color', 'orange');
                        }
                    }

                    if (inserted) {
                        // Avoid overwriting specific "appended to text editor" message
                        if ($statusMessage.text() === 'Processing...' || $statusMessage.text() === '' || $statusMessage.css('color') === 'rgb(0, 128, 0)') { // green
                           $statusMessage.text(response.data.message || 'Content inserted!').css('color', 'green');
                        }
                        // Suggests a page reload to see the updated meta box content (Last Raw AI Output etc.)
                        // You could also try to update these fields dynamically via JS if preferred.
                        // For simplicity, a full save/reload by the user is often acceptable here.
                        // Example: $statusMessage.append(' <a href="#" onclick="window.location.reload(); return false;">Reload to see updated meta?</a>');
                    } else {
                        $statusMessage.text('Could not insert content into any editor. HTML was generated. Check console.').css('color', 'red');
                         console.error("Gemini: HTML content was available but not inserted into any known editor.", htmlToInsert);
                    }

                } else {
                    // Improved error message display from AJAX success but logical error
                    var errorMsg = 'Error: ';
                    if (response.data && typeof response.data === 'string') {
                        errorMsg += response.data; // Typically when wp_send_json_error sends a simple string
                    } else if (response.data && response.data.message) { // For object like { message: "...", details: "..." }
                         errorMsg += response.data.message;
                         if(response.data.details) console.error("Gemini AJAX Error Details:", response.data.details);
                    } else if (typeof response.data === 'object' && response.data !== null) {
                        errorMsg += JSON.stringify(response.data);
                    } else if (!response.success && response.data === null ) { // Explicit null might mean a different server state
                        errorMsg += 'Received empty error data from server.';
                    }
                    else {
                        errorMsg += 'Unknown error generating content. Check console.';
                    }
                    $statusMessage.text(errorMsg).css('color', 'red');
                    console.error("Gemini AJAX Success with Error (response.success=false or missing data):", response);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // AJAX communication error
                var errorMessage = 'AJAX error: ' + textStatus;
                if (errorThrown) errorMessage += ' - ' + errorThrown;

                if (jqXHR.responseText) {
                    try {
                        var responseParsed = JSON.parse(jqXHR.responseText);
                        if(responseParsed && responseParsed.data && typeof responseParsed.data === 'string'){
                            errorMessage += '<br>Server: ' + responseParsed.data;
                        } else if (responseParsed && responseParsed.data && responseParsed.data.message) {
                             errorMessage += '<br>Server: ' + responseParsed.data.message;
                        } else if (responseParsed && responseParsed.data) { // Catch all for data object
                             errorMessage += '<br>Server: ' + JSON.stringify(responseParsed.data);
                        }
                    } catch (e) {
                        // Not JSON or no data field, append raw response text if it's short and might be useful
                        if (jqXHR.responseText.length < 500 && jqXHR.responseText.length > 0) { 
                           // errorMessage += "<br>Raw Response: " + $('<div>').text(jqXHR.responseText).html(); // Basic escaping
                        }
                        console.warn("Gemini AJAX error: Could not parse JSON response or responseText was too long/empty.", jqXHR.responseText);
                    }
                }
                $statusMessage.html(errorMessage).css('color', 'red'); // Use .html() if error messages contain <br>
                console.error("Gemini AJAX Error Full Details:", { status: jqXHR.status, responseText: jqXHR.responseText, textStatus: textStatus, errorThrown: errorThrown });
            },
            complete: function() {
                $button.text('Generate Business Description with AI').prop('disabled', false);
                // Clear status message after a delay, but not if it's an error or important warning
                setTimeout(function() {
                    var currentColor = $statusMessage.css('color');
                    if (currentColor === 'rgb(0, 128, 0)' || currentColor === 'green') { // Only clear green (success) messages
                        $statusMessage.text('').css('color', '');
                    }
                }, 7000); // Clear after 7 seconds
            }
        });
    });
});
