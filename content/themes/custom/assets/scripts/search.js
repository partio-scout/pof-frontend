window.Search = ( function( window, document, $ ){

    var app = {};

    app.cache = function(){
    	app.$kestoslider = $('#kestoslider');
        app.$kestolabel = $('#kestolabel');
        app.$ryhmaslider = $('#ryhmaslider');
        app.$ryhmalabel = $('#ryhmalabel');
    };

    app.init = function(){

        app.cache();
        if ( app.$kestoslider.length ) {
            app.$kestoslider.slider({
                range: true,
                min: 0,
                max: 10,
                values: [ 0, 10 ],
                slide: function( event, ui ) {
                    app.$kestolabel.val( ui.values[ 0 ] + " - " + ui.values[ 1 ] );
                }
            });

            app.$kestolabel.val( app.$kestoslider.slider( "values", 0 ) + " - " + app.$kestoslider.slider( "values", 1 ) );

            app.$ryhmaslider.slider({
                range: true,
                min: 0,
                max: 10,
                values: [ 0, 10 ],
                slide: function( event, ui ) {
                    app.$ryhmalabel.val( ui.values[ 0 ] + " - " + ui.values[ 1 ] );
                }
            });

            app.$ryhmalabel.val( app.$ryhmaslider.slider( "values", 0 ) + " - " + app.$ryhmaslider.slider( "values", 1 ) );
        }
    };

    $(document).ready( app.init );

    return app;

})( window, document, jQuery );