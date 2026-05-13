jQuery(function($){
    $('.upload-menu-icon').on('click', function(e){
        e.preventDefault();
        let btn = $(this);
        let frame = wp.media({title:'Select Icon', button:{text:'Use image'}, multiple:false});
        frame.on('select', function(){
            let att = frame.state().get('selection').first().toJSON();
            btn.siblings('.menu-icon-id').val(att.id);
            btn.siblings('.menu-icon-preview').html('<img src="'+att.sizes.thumbnail.url+'" />');
        });
        frame.open();
    });

    $('.remove-menu-icon').on('click', function(e){
        e.preventDefault();
        $(this).siblings('.menu-icon-id').val('');
        $(this).siblings('.menu-icon-preview').html('');
    });
});