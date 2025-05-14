;( function( $, wp ) {
  function initGemini() {
    $('#gemini-trigger').on('click', function(e) {
      e.preventDefault();
      $('#gemini-results').html('Searching…');

      $.post(geminiAjax.ajax_url, {
        action:  'gemini_search',
        nonce:   geminiAjax.nonce,
        post_id: geminiAjax.post_id
      })
      .done(function(res) {
        if ( res.success ) {
          $('#gemini-results').html('<p style="color:green;">' + res.data.message + '</p>');
          $('#gemini-results').append(
            '<div class="gemini-content">' +
              res.data.content.replace(/\n/g, '<br>') +
            '</div>'
          );
          injectIntoEditor(res.data.content);
        } else {
          $('#gemini-results').html('<p style="color:red;">Error: ' + res.data + '</p>');
        }
      })
      .fail(function(xhr, status, err) {
        $('#gemini-results').html('<p style="color:red;">AJAX error: ' + err + '</p>');
      });
    });
  }

  function injectIntoEditor(text) {
    var html = '<p>' + text.replace(/\n/g, '</p><p>') + '</p>';
    if ( wp && wp.data && wp.data.dispatch ) {
      var cur = wp.data.select('core/editor').getEditedPostContent();
      wp.data.dispatch('core/editor').editPost({ content: cur + html });
    } else if ( window.tinyMCE && tinyMCE.get('content') ) {
      var cur = tinyMCE.get('content').getContent();
      tinyMCE.get('content').setContent( cur + html );
    } else if ( $('#content').length ) {
      $('#content').val( $('#content').val() + html );
    }
  }

  if ( wp && wp.domReady ) {
    wp.domReady(initGemini);
  } else {
    $(initGemini);
  }
})( jQuery, window.wp );
