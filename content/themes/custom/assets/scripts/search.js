import _ from 'lodash';

window.Search = ( function( window, document, $ ){

    var app = {};

    app.cache = function() {
        app.$loadMoreButton         = $("#search-results-loadmore");
        app.$resultsContainer       = $("#search-results-container");
        app.$searchInput            = $("#search");
        app.$searchIcon             = $(".search-icon");
        app.$searchForm             = $(".search-box__form");
        app.$searchInput            = app.$searchForm.find( 'input[name="s"]');
        app.$resultMessageContainer = $("#results-message");
        app.$resultsCount           = $( '#results-count' );
        app.$loadmoreContainer      = $( '.loadmore-container' );
        app.$maxPages               = app.$loadMoreButton.data('maxpages');
        app.$page                   = app.$loadMoreButton.data('page');        
    };

    app.handleMetadata = ( data ) => {
        // Get new response data or set defaults if no response
        const metadata = _.get( data, 'Search.Results', {
            max_num_pages: 0,
            count: 0,
            page: 1
        });

        app.$maxPages = metadata.max_num_pages;
        app.$page = metadata.page;

        app.$loadMoreButton
            .data( 'page', metadata.page )
            .data( 'maxpages', metadata.max_num_pages )
            .data( 'postcount', metadata.count );

        app.$resultsCount.text( metadata.count );

        if ( metadata.max_num_pages > 1 ) {
            app.$loadmoreContainer.removeClass( 'hidden' );
            app.$loadMoreButton.show();
        }
        else {
            app.$loadMoreButton.hide();
        }
    }

    app.init = function(){
        // Only execute this script on the search page
        if ( document.body.classList.contains( 'search' ) ) {

            // crawl the DOM
            app.cache();

            if ( app.$maxPages > 1 ) {
                app.$loadmoreContainer.removeClass( 'hidden' );
            }

            // event listeners
            app.$loadMoreButton.on( 'click', ( e ) => app.loadMore( e ) );
            app.$searchForm.on( 'submit', ( e ) => app.doSearch( e ) );
            app.$searchIcon.on( 'click', ( e ) => app.doSearch( e ) );
        }

    };

    /**
     * Fires search query
     * fetches search results
     */
    app.doSearch = function( e ) {
        app.stop( e );

        // Collect args from the form that was submitted either via click or submit event
        const args = e.type === 'submit' ? $( e.currentTarget ).serializeJSON() : $( e.currentTarget ).closest( 'form' ).serializeJSON();

        // Duplicate search value across both forms
        app.$searchInput.val( args.s );

        dp('Search/Results', {
            args,
            partial: 'search-results-list',
            data: true,
            success: ( html, data ) => {
                app.doSearchSuccess( html, data, args );
            },
            error: ( error ) => {
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
    app.loadMore = ( e ) => {
        app.stop(e);
        if ( ! app.$loadMoreButton.disabled ) {
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
        }
    };

    /**
     * 
     */
    app.doSearchSuccess = function( html, data, args ) {
        // Get new response data or set defaults if no response
        app.handleMetadata( data );

        app.$resultsContainer.html( html );
        if (window.history) {
            window.history.pushState( {}, 'Haku', location.origin + '/haku/' + args.s );
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