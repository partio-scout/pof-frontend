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
        this.$searchIcon             = $( '.search-icon' );
        this.$searchForm             = $( '.search-box__form' );
        this.$searchInput            = this.$searchForm.find( 'input[name="s"]' );
        this.$advSearchLink          = this.$searchForm.find( '.advanced-search-link' );
        this.$resultMessageContainer = $( '#results-message' );
        this.$resultsCount           = $( '#results-count' );
        this.$loadmoreContainer      = $( '.loadmore-container' );
        this.$maxPages               = this.$loadMoreButton.data( 'maxpages' );
        this.$page                   = this.$loadMoreButton.data( 'page' );
        this.$filterForm             = $( '.search-filter' );
        this.$filterBtn              = this.$searchForm.find( '.filter-icon' );
        this.$filterMoreBtn          = this.$filterForm.find( '.toggle-global-filters' );
        this.$filterInputs           = this.$filterForm.find( 'input[name]:not([type="text"])' );
        this.lastSearch              = this.$searchInput.val();
        this.$langMenu               = $( '#second-lang-menu' );
    };

    toggleSelfActive( e ) {
        e.preventDefault();
        $( e.currentTarget ).toggleClass( 'active' );
    }

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
            this.$filterForm.on( 'submit', ( e ) => this.doSearch( e ) );
            this.$filterBtn.on( 'click', ( e ) => this.toggleSelfActive( e ) );
            this.$filterMoreBtn.on( 'click', ( e ) => this.toggleSelfActive( e ) );
            this.$filterInputs.on( 'change', ( e ) => this.filterInputChange( e ) );
            this.$advSearchLink.on( 'click', ( e ) => this.highLightFilter( e ) );
        }
    };

    /**
     * Handle change event on filter inputs
     *
     * @param {object} e Change event.
     */
    filterInputChange( e ) {
        const $input = $( e.currentTarget );
        const $children = $input.parent().find( 'input[type="checkbox"][name].checkbox' );
        $children.prop( 'checked', $input.is( ':checked' ) );

        this.doSearch( e );
    }

    /**
     * Hightlight the filterform and move focus to it
     *
     * @param  {object} e Event that triggered this
     */
    highLightFilter( e = null ) {
        if ( e ) {
            e.preventDefault();
        }

        // If we are on mobile hide the menu and show the filter form
        if ( this.$filterBtn.is( ':visible' ) ) {

            // Hide menu
            window.Partio.$menu.removeClass( 'move-right' );
            window.Partio.$content.removeClass( 'move-right' );

            // Show filterform
            this.$filterBtn.addClass( 'active' );
        }

        // Focus the filterform input
        this.$filterForm.find( 'input[name="s"]' ).focus();

        // Clear any current timeouts
        if ( this.filterAnimation ) {
            clearTimeout( this.filterAnimation );
            this.$filterForm.removeClass( 'highlight' );
        }

        // Hightlight the form and add a timeout to clear the highlight class
        this.$filterForm.addClass( 'highlight' );
        this.filterAnimation = setTimeout( () => {
            this.$filterForm.removeClass( 'highlight' );
        }, 2 * 1000 );
    }

    getArgs( searchForm = null ) {
        const filter = ( searchForm || this.$filterForm || this.$searchForm ).filter( ':visible' ).serialize();

        const args = {
            filter
        };

        return args;
    }

    /**
     * Do an ajax search
     *
     * @param {object} e Event that initialized this function call.
     */
    doSearch( e ) {
        if ( e.type !== 'change' ) {
            this.stop( e );
        }

        const $form   = $( e.currentTarget ).closest( 'form' );
        const $SInput = $form.find( 'input[name="s"]' );

        // Duplicate search value across both forms
        if ( $SInput.val() ) {
            this.$searchInput.val( $SInput.val() );
        } else {
            $SInput.val( this.$searchInput.val() );
        }

        // Collect args from the form that was submitted either via click or submit event
        const args = this.getArgs( $form );

        // Abort any existing calls
        if ( this.xhr ) {
            this.xhr.abort();
        }
        this.xhr = dp( 'Search/Results', {
            url: window.location,
            args,
            partial: 'search-results-list',
            data: true,
            success: ( html, data ) => {
                this.doSearchSuccess( html, data );
            },
            error: ( error ) => {
                console.log( 'error', error );
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

            const args = this.getArgs();
            args.page = this.$page + 1;

            // Abort any existing calls
            if ( this.xhr ) {
                this.xhr.abort();
            }
            this.xhr = dp( 'Search/Results', {
                url: window.location,
                args,
                partial: 'search-results-list',
                success: ( data ) => {
                    this.loadMoreSuccess( data );
                },
                error: ( error ) => {
                    console.log( 'error', error );
                }
            });
        }
    };

    /**
     * Handle succesful search call
     *
     * @param {string} html Rendered html result of query.
     * @param {object} data Data that was used to render the html.
     */
    doSearchSuccess( html, data ) {

        // Get new response data or set defaults if no response
        this.handleMetadata( data );

        this.$resultsContainer.html( html );

        // Change url and add the query to the history
        if ( window.history && typeof pof_lang !== 'undefined' ) {
            const searchTerm = _.get( data, 'Search.Results.params.search_term', this.$searchInput.val() );

            const newUrl = location.toString().replace( new RegExp( encodeURIComponent( pof_lang.search_base ) + '\/.+', 'g' ), pof_lang.search_base + '/' + searchTerm );
            if ( newUrl.includes( searchTerm ) ) {
                window.history.pushState({}, 'Haku', newUrl );
            } else {
                window.history.pushState({}, 'Haku', newUrl + searchTerm );
            }

            this.$langMenu.find( 'a' ).each( ( i, el ) => {
                el.href = el.href.replace( this.lastSearch, searchTerm );
            });

            this.lastSearch = searchTerm;
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
            this.$loadMoreButton.removeClass( 'loading' );
        } else {
            this.$loadMoreButton.hide();
        }

        // Check if history is supported in browser.
        if ( window.history && typeof pof_lang !== 'undefined' ) {
            let newUrl;
            if ( location.toString().includes( pof_lang.pagination_base ) ) {
                newUrl = location.toString().replace( new RegExp( pof_lang.pagination_base + '\/.+', 'g' ), pof_lang.pagination_base + '/' + newPage );
            }
            else {
                newUrl = location.toString().replace( /\/$/, '' ) + '/' + pof_lang.pagination_base + '/' + newPage;
            }

            // Push and change the full location path.
            window.history.pushState( {}, 'Sivu', newUrl );
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
