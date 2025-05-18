jQuery(document).ready(function($) {
    const { __, sprintf } = wp.i18n;
    const $button = $('#gemini-trigger');
    const $statusMessage = $('#gemini-status-message');

    // Function to update button state
    function updateButtonState(hasContent) {
        if (hasContent) {
            $button.text(geminiAjax.i18n.updateText || __( 'Update AI Content', 'gemini2-business-lookup' ))
                   .removeClass('button-primary gemini-generate-ai')
                   .addClass('button-secondary gemini-update-ai');
        } else {
            $button.text(geminiAjax.i18n.generateText || __( 'Generate AI Content', 'gemini2-business-lookup' ))
                   .removeClass('button-secondary gemini-update-ai')
                   .addClass('button-primary gemini-generate-ai');
        }
    }

    // Set initial button state on page load
    updateButtonState(geminiAjax.hasAiContentMeta);

    $button.on('click', function() {
        if (!geminiAjax.post_id || geminiAjax.post_id === 0) {
            $statusMessage.text(geminiAjax.i18n.errorPostId || __( 'Error: Post ID not available. Please save the post and try again.', 'gemini2-business-lookup' )).css('color', 'red');
            console.error("Gemini AJAX Error: Post ID is missing or zero.", geminiAjax);
            setTimeout(function() {
                $statusMessage.text('').css('color', '');
            }, 7000);
            return;
        }

        const originalButtonText = $button.text(); // Store current text (Generate or Update)
        $button.text(geminiAjax.i18n.generatingText || __( 'Generating...', 'gemini2-business-lookup' )).prop('disabled', true);
        $statusMessage.text(geminiAjax.i18n.processingText || __( 'Processing...', 'gemini2-business-lookup' )).css('color', '');

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

                    // Try Gutenberg
                    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.select('core/editor')) {
                        try {
                            const { getBlocks, resetBlocks, insertBlocks } = wp.data.dispatch('core/editor');
                            const { getBlocks: getCurrentBlocks } = wp.data.select('core/editor');
                            const currentBlocks = getCurrentBlocks();
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
                                console.warn("Gemini: rawHandler produced no blocks from HTML.");
                            }
                        } catch (e) {
                            console.error("Error inserting into Gutenberg: ", e);
                        }
                    }
                    
                    // Try TinyMCE
                    if (!inserted && typeof tinymce !== 'undefined' && tinymce.get('content')) {
                        try {
                            tinymce.get('content').execCommand('mceInsertContent', false, htmlToInsert);
                            inserted = true;
                        } catch (e) {
                             console.error("Error inserting into TinyMCE: ", e);
                        }
                    }
                    
                    // Try Classic Editor Textarea
                    if (!inserted) {
                        var $classicEditorTextarea = $('#content'); 
                        if ($classicEditorTextarea.length) {
                            var currentVal = $classicEditorTextarea.val();
                            $classicEditorTextarea.val(currentVal + "\n\n" + htmlToInsert);
                            inserted = true;
                           $statusMessage.text(geminiAjax.i18n.appendSuccess || __( 'Appended to text editor. Review formatting.', 'gemini2-business-lookup' )).css('color', 'orange');
                        }
                    }

                    if (inserted) {
                        // Use a general success message, or the one from PHP if provided and more specific.
                        let serverMsg = response.data.message; // Already translated by PHP
                        let finalMsg = serverMsg ? (serverMsg + " " + __( 'Content inserted/updated.', 'gemini2-business-lookup' )) : (geminiAjax.i18n.successMessage || __( 'AI Content Generated and Inserted!', 'gemini2-business-lookup' ));
                        
                        if ($statusMessage.text() === (geminiAjax.i18n.processingText || __( 'Processing...', 'gemini2-business-lookup' )) || $statusMessage.text() === '') {
                           $statusMessage.text(finalMsg).css('color', 'green');
                        }
                        updateButtonState(true); // Update button to "Update AI Content" state
                    } else {
                        $statusMessage.text(geminiAjax.i18n.insertFail || __( 'Could not insert content. See console for details.', 'gemini2-business-lookup' )).css('color', 'red');
                        console.error("Gemini: HTML content was available but not inserted into any known editor.", htmlToInsert);
                        updateButtonState(geminiAjax.hasAiContentMeta); // Revert to original state before click
                    }

                } else { // AJAX success but logical error from PHP
                    var errorMsg = __( 'Error:', 'gemini2-business-lookup' ) + ' ';
                    if (response.data && typeof response.data === 'string') { // Error string from wp_send_json_error
                        errorMsg += response.data; 
                    } else if (response.data && response.data.message) { // Error object with message from wp_send_json_error
                         errorMsg += response.data.message;
                    } else {
                        errorMsg += geminiAjax.i18n.genericError || __( 'Unknown error generating content. Check console.', 'gemini2-business-lookup' );
                    }
                    $statusMessage.text(errorMsg).css('color', 'red');
                    console.error("Gemini AJAX Success with Error:", response);
                    // Decide button state: if content existed before, revert to "Update", else "Generate"
                    updateButtonState(geminiAjax.hasAiContentMeta); 
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var errorMessage = __( 'AJAX error:', 'gemini2-business-lookup' ) + ' ' + textStatus;
                if (errorThrown) errorMessage += ' - ' + errorThrown;

                if (jqXHR.responseText) {
                    try {
                        var responseParsed = JSON.parse(jqXHR.responseText);
                        if(responseParsed && responseParsed.data && typeof responseParsed.data === 'string'){
                            errorMessage += '\n' + __( 'Server:', 'gemini2-business-lookup' ) + ' ' + responseParsed.data;
                        } else if (responseParsed && responseParsed.data && responseParsed.data.message) {
                             errorMessage += '\n' + __( 'Server:', 'gemini2-business-lookup' ) + ' ' + responseParsed.data.message;
                        }
                    } catch (e) {
                        console.warn("Gemini AJAX error: Could not parse JSON response.", jqXHR.responseText);
                    }
                }
                $statusMessage.html(errorMessage.replace(/\n/g, '<br>')).css('color', 'red');
                console.error("Gemini AJAX Error Full Details:", jqXHR.status, jqXHR.responseText, textStatus, errorThrown);
                // Decide button state on error
                updateButtonState(geminiAjax.hasAiContentMeta);
            },
            complete: function(jqXHR, textStatus) {
                // Button text and state are now handled in success/error via updateButtonState()
                // Only re-enable the button here.
                $button.prop('disabled', false);
                
                // Update the geminiAjax.hasAiContentMeta flag if content was successfully generated
                if (textStatus === 'success' && jqXHR.responseJSON && jqXHR.responseJSON.success) {
                    geminiAjax.hasAiContentMeta = true; 
                }

                setTimeout(function() {
                    if ($statusMessage.css('color') !== 'red' && $statusMessage.css('color') !== 'orange') {
                        $statusMessage.text('').css('color', '');
                    }
                }, 7000);
            }
        });
    });
});
