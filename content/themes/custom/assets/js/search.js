window.Search = ( function( window, document, $ ){

    var app = {};

    app.cache = function() {
        app.queryString             = '';
        app.$loadMoreButton         = $("#search-results-loadmore");
        app.$resultsContainer       = $("#search-results-container");
        app.$searchInput            = $("#search");
        app.$searchForm             = $("#hero-search");
        app.$resultMessageContainer = $("#results-message");
        app.$maxPages               = app.$loadMoreButton.data('maxpages');
        app.$page                   = app.$loadMoreButton.data('page');
        
    };

    app.init = function(){
        // crawl the DOM
        app.cache();

        // event listeners
        app.$loadMoreButton.on('click', function(e) {
            app.stop(e);
            app.loadMore(e);
        });
    };

    /**
     * Fires on load more click on the search-results page.
     * Fetches more search-results results for the initial search query.
     *
     */
    app.loadMore = function(){
        app.$loadMoreButton.disabled = true;
        app.$loadMoreButton.addClass('loading');
        dp('Search/Results', {
            partial: 'search-results-list',
            success: function(data) {
                app.loadSuccess( data );
            },
            error: function(error) {
                app.$resultsContainer.innerHTML += '<h2>' + error + '</h2>';
            },
        });
    };

    /**
     * Executed upon DustPress.JS success
     * @param data
     */
    app.loadSuccess = function( data ) {
        var newPage = app.$page++;
        app.$loadMoreButton.data('page', newPage);
        // Add returned HTML to container
        var newHTML = app.$resultsContainer.html() + data;
        app.$resultsContainer.html(newHTML);
        // After we load more stuff, add notification for screen reader.
        if ( app.$page > 1) {
            app.$resultMessageContainer.html('Ladattiin lisää tapahtumia');
        }
        console.log('sivulla:', app.$page, 'maxpages:', app.$maxPages);
        // If max pages has not been reached reset the load more button
        if (app.$page < app.$maxPages ) {
            console.log('MENTIINKÖ TÄNNE');
            app.$loadMoreButton.disabled = false;
            app.$loadMoreButton.html('Lataa lisää');
            app.$loadMoreButton.removeClass('loading');
        } else {
            app.$loadMoreButton.hide();
        }
        // Check if history is supported in browser.
        if (window.history) {

            // The url contains a page
            if (/sivu/.test(location.pathname)) {

                // Replace the current location with the new state.
                var path = location.pathname.replace(/sivu\/(\d+)/, 'sivu/' + app.$page);

                // Push and change the full location path.
                window.history.pushState({}, 'Sivu', path);
            } else {

                // Push the state to the end of the current location.
                var url = 'sivu/' + app.$page + '/';
                window.history.pushState({}, 'Sivu', url );
            }
        }
    };

    app.stop = function(e) {
        e.stopPropagation();
        e.preventDefault();
    };

    $(document).ready( app.init );

    return app;

})( window, document, jQuery );
