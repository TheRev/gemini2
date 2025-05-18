jQuery(document).ready(function($) {
    $('#gemini-trigger').on('click', function() {
        var $button = $(this);
        var $statusMessage = $('#gemini-status-message'); // Span for messages

        // Check if post_id is available
        if (!geminiAjax.post_id || geminiAjax.post_id === 0) {
            $statusMessage.text('Error: Post ID not available. Please save the post and try again.').css('color', 'red');
            console.error("Gemini AJAX Error: Post ID is missing or zero.", geminiAjax);
             // Clear status message after a few seconds
            setTimeout(function() {
                $statusMessage.text('').css('color', '');
            }, 7000);
            return; // Stop execution if no post_id
        }


        $button.text('Generating...').prop('disabled', true);
        $statusMessage.text('Processing...').css('color', ''); // Clear previous status color

        $.ajax({
            url: geminiAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'gemini_search',
                nonce: geminiAjax.nonce,
                post_id: geminiAjax.post_id
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
                            const newBlocks = wp.blocks.rawHandler({ HTML: htmlToInsert });
                            
                            if (newBlocks && newBlocks.length > 0) { // Ensure newBlocks is not empty
                                if (currentBlocks.length === 0 || 
                                    (currentBlocks.length === 1 && currentBlocks[0].name === 'core/paragraph' && currentBlocks[0].attributes.content === '')) {
                                    resetBlocks(newBlocks);
                                } else {
                                    insertBlocks(newBlocks);
                                }
                                inserted = true;
                            } else {
                                console.warn("Gemini: rawHandler produced no blocks from HTML.");
                            }
                        } catch (e) {
                            console.error("Error inserting into Gutenberg: ", e);
                        }
                    }
                    
                    if (!inserted && typeof tinymce !== 'undefined' && tinymce.get('content')) {
                        try {
                            tinymce.get('content').execCommand('mceInsertContent', false, htmlToInsert);
                            inserted = true;
                        } catch (e) {
                             console.error("Error inserting into TinyMCE: ", e);
                        }
                    }
                    
                    if (!inserted) {
                        var $classicEditorTextarea = $('#content'); 
                        if ($classicEditorTextarea.length) {
                            var currentVal = $classicEditorTextarea.val();
                            $classicEditorTextarea.val(currentVal + "\n\n" + htmlToInsert); // Add some space
                            inserted = true;
                           // alert('Content appended to text editor. Please switch to Visual mode to see formatting or clean up HTML tags if in Text mode.');
                           $statusMessage.text('Appended to text editor. Review formatting.').css('color', 'orange');
                        }
                    }

                    if (inserted) {
                        if ($statusMessage.text() === 'Processing...' || $statusMessage.text() === '') { // Avoid overwriting specific append message
                           $statusMessage.text(response.data.message || 'Content inserted!').css('color', 'green');
                        }
                        // Consider if a page refresh or meta box reload is needed to show "Last Raw AI Output" update.
                        // For now, user would save the post.
                    } else {
                        $statusMessage.text('Could not insert content into editor. HTML was generated but not inserted.').css('color', 'red');
                         console.error("Gemini: HTML content was available but not inserted into any known editor.", htmlToInsert);
                    }

                } else {
                    var errorMsg = 'Error: ';
                    if (response.data && typeof response.data === 'string') {
                        errorMsg += response.data;
                    } else if (response.data && response.data.message) { // Check if response.data itself is the message
                         errorMsg += response.data.message;
                    } else if (typeof response.data === 'object' && response.data !== null) { // If data is an object with details
                        errorMsg += JSON.stringify(response.data);
                    } else if (!response.success && typeof response.data === 'string' && response.data.includes("blocked")) { // Specific check for blocked content
                        errorMsg += response.data; // Show the detailed blocked message
                    }
                    else {
                        errorMsg += 'Unknown error generating content. Check console.';
                    }
                    $statusMessage.text(errorMsg).css('color', 'red');
                    console.error("Gemini AJAX Success with Error:", response);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var errorMessage = 'AJAX error: ' + textStatus;
                if (errorThrown) errorMessage += ' - ' + errorThrown;

                if (jqXHR.responseText) {
                    try {
                        var responseParsed = JSON.parse(jqXHR.responseText);
                        if(responseParsed && responseParsed.data && typeof responseParsed.data === 'string'){
                            errorMessage += '\nServer: ' + responseParsed.data;
                        } else if (responseParsed && responseParsed.data && responseParsed.data.message) {
                             errorMessage += '\nServer: ' + responseParsed.data.message;
                        } else if (responseParsed && responseParsed.data) {
                             errorMessage += '\nServer: ' + JSON.stringify(responseParsed.data);
                        }
                    } catch (e) {
                        // Not JSON or no data field, append raw response text if it's useful
                        if (jqXHR.responseText.length < 300) { // Avoid overly long raw text
                           // errorMessage += "\nRaw Response: " + jqXHR.responseText;
                        }
                        console.warn("Gemini AJAX error: Could not parse JSON response.", jqXHR.responseText);
                    }
                }
                $statusMessage.text(errorMessage.replace(/\n/g, '<br>')).css('color', 'red');
                console.error("Gemini AJAX Error Full Details:", jqXHR.status, jqXHR.responseText, textStatus, errorThrown);
            },
            complete: function() {
                $button.text('Generate Business Description with AI').prop('disabled', false);
                setTimeout(function() {
                    if ($statusMessage.css('color') !== 'red' && $statusMessage.css('color') !== 'orange') { // Don't clear errors/warnings too quickly
                        $statusMessage.text('').css('color', '');
                    }
                }, 7000); // Clear after 7 seconds
            }
        });
    });
});
