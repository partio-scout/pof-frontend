window.ApiEditBlock = ( function( window, document, $ ){

    var app = {};

    app.cache = function() {        
    	app.$textareas  = $('.api_edit_block_wrap').find('textarea');
        app.$inputs     = $('.api_edit_block_wrap').find('input');   
        app.$buttons    = $('.api_edit_block_wrap').find('.acf-repeater-add-row');
        app.$disabled   = $('.api-disabled');   

        app.$title      = $('.api-related').find('input#title')[0];
    };

    app.init = function(){
        app.cache();
        
        app.forEach(app.$textareas, app.disableField);
        app.forEach(app.$inputs, app.disableField);
        app.forEach(app.$disabled, app.disableField);
        app.forEach(app.$buttons, app.remove);        
        app.disableField(app.$title);
    };

    app.disableField = function(field) {
        field.disabled = true;
    };

    app.remove = function(field) {
        $(field).remove();
    }

    app.forEach = function(arr, cb) {
        for (var i = arr.length - 1; i >= 0; i--) {
            cb(arr[i]);
        };
    };

    $(document).ready( app.init );

    return app;

})( window, document, jQuery );

