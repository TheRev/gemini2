jQuery(function($){
    $('#bulk-ai-business').on('click', function(){
        var $btn = $(this), $status = $('#bulk-ai-business-status');
        $btn.prop('disabled', true).text('Running...');
        $status.html('');
        $.post(BulkAIBusiness.ajax_url, { action: 'bulk_ai_missing_business', nonce: BulkAIBusiness.nonce }, function(res){
            if(res.success){
                $status.html('<span style="color:green;">' + res.data + '</span>');
                location.reload();
            }else{
                $status.html('<span style="color:red;">' + res.data + '</span>');
                $btn.prop('disabled', false).text('Bulk Add AI Reviews');
            }
        });
    });
});
