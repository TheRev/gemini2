jQuery(function($){
    $('#bulk-ai-content').on('click', function(){
        var $btn = $(this), $status = $('#bulk-ai-status');
        $btn.prop('disabled', true).text('Running...');
        $status.html('');
        $.post(BulkAI.ajax_url, { action: 'bulk_ai_missing_pages', nonce: BulkAI.nonce }, function(res){
            if(res.success){
                $status.html('<span style="color:green;">' + res.data + '</span>');
                location.reload();
            }else{
                $status.html('<span style="color:red;">' + res.data + '</span>');
                $btn.prop('disabled', false).text('Bulk Add AI Content');
            }
        });
    });
});