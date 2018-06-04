import _ from 'lodash';

const $ = jQuery;

class Search {

    /**
     * Used to run class init function on document ready
     */
    constructor() {
        $( () => this.init() );
    }

    /**
     * Cache relevant dom elements
     */
    cache() {
        this.$loadMoreButton         = $( '#search-results-loadmore' );
        this.$resultsContainer       = $( '#search-results-container' );
        this.$searchInput            = $( '#search' );
        this.$searchIcon             = $( '.search-icon' );
        this.$searchForm             = $( '.search-box__form' );
        this.$searchInput            = this.$searchForm.find( 'input[name="s"]' );
        this.$resultMessageContainer = $( '#results-message' );
        this.$resultsCount           = $( '#results-count' );
        this.$loadmoreContainer      = $( '.loadmore-container' );
        this.$maxPages               = this.$loadMoreButton.data( 'maxpages' );
        this.$page                   = this.$loadMoreButton.data( 'page' );
    };

    /**
     * Handle search metadata
     *
     * @param {object} data A dp.js data result
     */
    handleMetadata( data ) {

        // Get new response data or set defaults if no response
        const metadata = _.get( data, 'Search.Results', {
            'max_num_pages': 0,
            'count': 0,
            'page': 1
        });

        this.$maxPages = metadata.max_num_pages;
        this.$page = metadata.page;

        this.$loadMoreButton
            .data( 'page', metadata.page )
            .data( 'maxpages', metadata.max_num_pages )
            .data( 'postcount', metadata.count );

        this.$resultsCount.text( metadata.count );

        if ( metadata.max_num_pages > 1 ) {
            this.$loadmoreContainer.removeClass( 'hidden' );
            this.$loadMoreButton.show();
        } else {
            this.$loadMoreButton.hide();
        }
    }

    /**
     * Called after document ready to initialize the class
     */
    init() {

        // Only execute this script on the search page
        if ( document.body.classList.contains( 'search' ) ) {

            // crawl the DOM
            this.cache();

            if ( this.$maxPages > 1 ) {
                this.$loadmoreContainer.removeClass( 'hidden' );
            }

            // event listeners
            this.$loadMoreButton.on( 'click', ( e ) => this.loadMore( e ) );
            this.$searchForm.on( 'submit', ( e ) => this.doSearch( e ) );
            this.$searchIcon.on( 'click', ( e ) => this.doSearch( e ) );

            $( '.search-filter' ).on( 'submit', ( e ) => this.filter( e ) );
        }
    };

    /**
     * Do a new search query with filters
     *
     * @param  {object} e Submit event.
     */
    filter( e ) {
        e.preventDefault();
        const args = $( e.currentTarget ).serializeJSON();
        console.log( 'args', args );
    }

    /**
     * Do an ajax search
     *
     * @param {object} e Event that initialized this function call.
     */
    doSearch( e ) {
        this.stop( e );

        // Collect args from the form that was submitted either via click or submit event
        const args = e.type === 'submit' ? $( e.currentTarget ).serializeJSON() : $( e.currentTarget ).closest( 'form' ).serializeJSON();

        // Duplicate search value across both forms
        this.$searchInput.val( args.s );

        dp( 'Search/Results', {
            args,
            partial: 'search-results-list',
            data: true,
            success: ( html, data ) => {
                this.doSearchSuccess( html, data, args );
            },
            error: ( error ) => {
                const newHTML = this.$resultsContainer.html() + '<h2>' + error + '</h2>';
                this.$resultsContainer.html( newHTML );
            }
        });
    };

    /**
     * Load more results from initial query
     *
     * @param  {object} e Click event.
     */
    loadMore( e ) {
        this.stop( e );
        if ( ! this.$loadMoreButton.disabled ) {
            this.$loadMoreButton.disabled = true;
            this.$loadMoreButton.addClass( 'loading' );
            dp( 'Search/Results', {
                args: {
                    'load_more': true,
                    's': this.$searchInput.val()
                },
                partial: 'search-results-list',
                success: ( data ) => {
                    this.loadMoreSuccess( data );
                },
                error: ( error ) => {
                    const newHTML = this.$resultsContainer.html() + '<h2>' + error + '</h2>';
                    this.$resultsContainer.html( newHTML );
                }
            });
        }
    };

    /**
     * Handle succesful search call
     *
     * @param {string} html Rendered html result of query.
     * @param {object} data Data that was used to render the html.
     * @param {obejct} args Data that was used to make the ajax call.
     */
    doSearchSuccess( html, data, args ) {

        // Get new response data or set defaults if no response
        this.handleMetadata( data );

        this.$resultsContainer.html( html );

        // Change url and add the query to the history
        if ( window.history ) {
            window.history.pushState({}, 'Haku', location.origin + '/haku/' + args.s );
        }
    };

    /**
     * Handle succesful load more call
     *
     * @param {string} data Rendered html result of query.
     */
    loadMoreSuccess( data ) {
        const newPage = this.$page++;
        this.$loadMoreButton.data( 'page', newPage );

        // Add returned HTML to container
        const newHTML = this.$resultsContainer.html() + data;
        this.$resultsContainer.html( newHTML );

        // After we load more stuff, add notification for screen reader.
        if ( this.$page > 1 ) {
            this.$resultMessageContainer.html( 'Ladattiin lisää tapahtumia' );
        }

        // If max pages has not been reached reset the load more button
        if ( this.$page < this.$maxPages ) {
            this.$loadMoreButton.disabled = false;
            this.$loadMoreButton.html( 'Lataa lisää' );
            this.$loadMoreButton.removeClass( 'loading' );
        } else {
            this.$loadMoreButton.hide();
        }

        // Check if history is supported in browser.
        if ( window.history ) {

            // The url contains a page
            if ( /sivu/.test( location.pathname ) ) {

                // Replace the current location with the new state.
                const path = location.pathname.replace( /sivu\/(\d+)/, 'sivu/' + this.$page );

                // Push and change the full location path.
                window.history.pushState({}, 'Sivu', path );
            } else {

                // Push the state to the end of the current location.
                const url = 'sivu/' + this.$page + '/';
                window.history.pushState({}, 'Sivu', url );
            }
        }
    };

    /**
     * Stop an event from executing its default functionality
     *
     * @param {object} e Event object.
     */
    stop( e ) {
        e.stopPropagation();
        e.preventDefault();
    };
}

export default new Search();
