window.Tips = ( function( window, document, $ ){

    var app = {};

    app.cache = function() {
        app.$section        = $("#tips__section");
        app.$tipsForm       = $("#tips__form_data");
    	app.$loader         = $("#tips__form_loader");
        app.$container      = $("#tips__form-container");
    	app.$counter        = $("#tip-count");
        app.$saveButton     = $("#tips__save");
        app.$filesContainer = $("#tips__image_input_container");
        app.$addImage       = $("#add_image_input");
        app.$sorters        = app.$section.find(".sort-filter");
        app.$tips           = app.$section.find(".tip");

        app.pageID          = app.$section.data("page");
        app.fileIdx         = 0;
        app.loaded          = false;

        app.refresh();
    };

    app.refresh = function() {
        app.$errors         = app.$section.find(".tips__error");
        app.$generalErr     = app.$section.find("#tips__general_errors");
    };

    app.init = function(){
        // crawl the DOM
        app.cache();

        // init listjs
        app.initListJS();

        // event listeners
        app.$sorters.on('click', function(e) {
            app.stop(e);
            app.sortTips(e);
        });
        app.$tipsForm.submit(app.saveTip);

    };

    app.initListJS = function() {
        var options = {
            valueNames: [ 'tip__date' ],
            sortClass: 'sort-filter'
        };

        app.tipsList = new List('tips__list_container', options);

        // list updated
        app.tipsList.on('updated', app.update);
    };

    app.update = function() {
        $('#tips__section').get(0).scrollIntoView();
    };

    app.saveTip = function() {
        app.$errors.hide();
        app.$generalErr.html('');
        app.$loader.show();
        var formData = new FormData(app.$tipsForm[0]);
        $.ajax({
            url: 'http://pof-backend.partio.fi/lisaa-vinkki/',
            type: 'POST',
            data: formData,
            success: function (data) {
                if (typeof data === "string") {
                    data = JSON.parse(data);
                }

                if ( data.status === 'error' ) {
                    app.handleError( data.message );
                } else {
                    app.handleSuccess( data.message );
                }
                app.$loader.hide();
            },
            cache: false,
            contentType: false,
            processData: false
        });

        return false;
    };

    app.sortTips = function(e) {
        var sorter = e.target.dataset.sorter;
        app.tipsList.sort(sorter, { order: "desc" });
        if ( e.target.className.indexOf("active") === -1 ) {
            app.$sorters.toggleClass("active");
        }
    };

    app.handleError = function( msg ) {
        app.$generalErr.append('<p>' + msg + '</p>').show();
    };

    app.handleSuccess = function( msg ) {
        app.$container.html('<div data-alert class="alert-box info radius">' + msg + '</div>');
    };

    app.stop = function(e) {
        e.stopPropagation();
        e.preventDefault();
    };

    app.forEach = function(arr, cb) {
        for (var i = arr.length - 1; i >= 0; i--) {
            cb(arr[i]);
        }
    };

    $(document).ready( app.init );

    return app;

})( window, document, jQuery );

