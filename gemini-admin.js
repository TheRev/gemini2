/**
 * Gemini 2.0 Admin Integration
 * Version: 1.1
 * Date: 2025-05-14
 */
jQuery(document).ready(function($) {
    console.log('[Gemini] Script loaded at: ' + new Date().toISOString());
    console.log('[Gemini] Checking for button with ID #gemini-trigger');

    // Check if the button exists
    if ($('#gemini-trigger').length) {
        console.log('[Gemini] Button found!');
    } else {
        console.error('[Gemini] Button not found! Check your HTML output.');
        return;
    }
    
    // Check if the localizations are available
    if (typeof geminiAjax === 'undefined') {
        console.error('[Gemini] geminiAjax object not defined! Check wp_localize_script.');
        return;
    }

    // Log the ajax parameters for debugging
    console.log('[Gemini] AJAX URL: ' + geminiAjax.ajax_url);
    console.log('[Gemini] Post ID: ' + geminiAjax.post_id);

    // Bind click event
    $('#gemini-trigger').on('click', function(e) {
        e.preventDefault();
        console.log('[Gemini] Button clicked');
        
        // Show loading message
        $('#gemini-results').show().html('<p><em>Generating business description...</em></p>');

        // Make AJAX request
        $.ajax({
            url: geminiAjax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'gemini_search',
                nonce: geminiAjax.nonce,
                post_id: geminiAjax.post_id
            },
            success: function(response) {
                console.log('[Gemini] AJAX request successful', response);
                
                if (response.success) {
                    // Display results
                    $('#gemini-results').html(
                        '<div class="gemini-content" style="padding: 10px; border: 1px solid #ddd; background: #fff;">' +
                        '<p style="color:green; font-weight:bold;">' + response.data.message + '</p>' +
                        '<div>' + formatText(response.data.content) + '</div>' +
                        '</div>'
                    );
                    
                    // Add button to insert into editor
                    $('#gemini-results').append(
                        '<div style="margin-top:10px;">' +
                        '<button id="gemini-insert" class="button">Insert Into Content</button>' +
                        '</div>'
                    );
                    
                    // Attach click handler for the insert button
                    $('#gemini-insert').on('click', function() {
                        injectIntoEditor(response.data.content);
                        $(this).text('Content Inserted!').attr('disabled', 'disabled');
                    });
                } else {
                    $('#gemini-results').html('<p style="color:red;">Error: ' + response.data + '</p>');
                    console.error('[Gemini] Error in response:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('[Gemini] AJAX error:', status, error);
                console.error('[Gemini] Response:', xhr.responseText);
                $('#gemini-results').html(
                    '<p style="color:red;">AJAX error: ' + error + '</p>' +
                    '<p>Status: ' + status + '</p>' +
                    '<p>Response: ' + xhr.responseText + '</p>'
                );
            }
        });
    });

    // Function to format text with proper HTML
    function formatText(text) {
        if (!text) return '';
        return '<p>' + text.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>';
    }

    // Function to inject content into the editor
    function injectIntoEditor(text) {
        var html = '<p>' + text.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>';
        console.log('[Gemini] Attempting to inject content into editor');
        
        try {
            // If block editor is present
            if (window.wp && wp.data && wp.data.dispatch('core/editor')) {
                console.log('[Gemini] Using Block Editor API');
                var cur = wp.data.select('core/editor').getEditedPostContent();
                wp.data.dispatch('core/editor').editPost({ content: cur + html });
                alert('Content added to the editor!');
                return true;
            } 
            // If classic editor with TinyMCE is present
            else if (window.tinyMCE && tinyMCE.get('content')) {
                console.log('[Gemini] Using TinyMCE API');
                var cur = tinyMCE.get('content').getContent();
                tinyMCE.get('content').setContent(cur + html);
                alert('Content added to the editor!');
                return true;
            } 
            // Fallback for plain textarea
            else if ($('#content').length) {
                console.log('[Gemini] Using plain textarea');
                $('#content').val($('#content').val() + html);
                alert('Content added to the editor!');
                return true;
            }
            else {
                console.warn('[Gemini] Could not find editor');
                alert('Could not find editor. Please copy and paste the text manually.');
                return false;
            }
        } catch (e) {
            console.error('[Gemini] Error injecting content:', e);
            alert('Error adding content to the editor: ' + e.message);
            return false;
        }
    }

    console.log('[Gemini] Initialization complete');
});
