window.Search = ( function( window, document, $ ){

    var app = {};

    app.cache = function() {
        app.queryString             = '';
        app.$loadMoreButton         = $("#search-results-loadmore");
        app.$resultsContainer       = $("#search-results-container");
        app.$searchInput            = $("#search");
        app.$searchIcon             = $("#search-icon");
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
            if ( ! app.$loadMoreButton.disabled ) {
                app.loadMore(e);
            }
        });
        
        
        app.$searchInput.on('keyup', function(e) {
            if (event.key === "Enter") {
                app.stop(e);
                return false;
                //TODO: Use ajax for this search after you get somehow rid of non-ajax enter event of input field
                //app.doSearch(e);
            }
        });
   
        app.$searchIcon.on('click', function() {
            app.doSearch();
        });
    };

    /**
     * Fires search query
     * fetches search results
     */
    app.doSearch = function() {
        dp('Search/Results', {
            args: {
                's': app.$searchInput.val()
            },
            partial: 'search-results-list',
            success: function(data) {
                app.doSearchSuccess( data );
            },
            error: function(error) {
                var newHTML = app.$resultsContainer.html() + '<h2>' + error + '</h2>';
                app.$resultsContainer.html(newHTML);
            },
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
            args: {
                'load_more': true,
                's': app.$searchInput.val()
            },
            partial: 'search-results-list',
            success: function(data) {
                app.loadMoreSuccess( data );
            },
            error: function(error) {
                var newHTML = app.$resultsContainer.html() + '<h2>' + error + '</h2>';
                app.$resultsContainer.html(newHTML);
            },
        });
    };

    /**
     * 
     */
    app.doSearchSuccess = function( data ) {
        app.$loadMoreButton.data('page', 1);
        app.$resultsContainer.html( data );
        if (window.history) {
            window.history.pushState({}, 'Haku', location.origin + '/haku/' + app.$searchInput.val());
        }
    };

    /**
     * Executed upon DustPress.JS success
     * @param data
     */
    app.loadMoreSuccess = function( data ) {
        var newPage = app.$page++;
        app.$loadMoreButton.data('page', newPage);
        // Add returned HTML to container
        var newHTML = app.$resultsContainer.html() + data;
        app.$resultsContainer.html(newHTML);
        // After we load more stuff, add notification for screen reader.
        if ( app.$page > 1) {
            app.$resultMessageContainer.html('Ladattiin lisää tapahtumia');
        }
        // If max pages has not been reached reset the load more button
        if (app.$page < app.$maxPages ) {
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