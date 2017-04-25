window.POFComments = ( function( window, document, $ ){

    var app = {};

    app.cache = function() {        
    };

    app.init = function(){
        // init comments
        app.comments = window.DustPressComments;
        // add listener for comments
        app.comments.addListener(app.initListJS);
        
        // init listjs for comments
        app.initListJS();
    };

    app.initListJS = function() {
        var options = {            
            page: comments.comments_per_page,
            listClass: 'comments__list',
            valueNames: ['comment__date','comment__heading'],
            plugins: [
                ListPagination({})
            ]
        };

        app.commentsList = new List('comments__list_container', options);

        // list updated
        app.commentsList.on('updated', app.comments.init);
    };

    $(document).ready( app.init );

    return app;

})( window, document, jQuery );

