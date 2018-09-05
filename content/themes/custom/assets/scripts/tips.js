import List from 'list.js';

const $ = jQuery;

class Tips {

    /**
     * Init class and add docready listener
     */
    constructor() {
        $( () => this.init() );
    }

    /**
     * Cache relevant elements
     */
    cache() {
        this.$section    = $( '#tips__section' );
        this.$tipsForm   = $( '#tips__form_data' );
        this.$loader     = $( '#tips__form_loader' );
        this.$container  = $( '#tips__form-container' );
        this.$sorters    = this.$section.find( '.sort-filter' );
        this.$errors     = this.$section.find( '.tips__error' );
        this.$generalErr = this.$section.find( '#tips__general_errors' );
    }

    /**
     * Ran on docready
     */
    init() {
        this.cache();
        this.initListJS();

        // event listeners
        this.$sorters.on( 'click', ( e ) => {
            e.preventDefault();
            this.sortTips( e );
        });
        this.$tipsForm.submit( () => this.saveTip() );
    }

    /**
     * Initialize the tip list
     */
    initListJS() {
        const options = {
            valueNames: [ 'tip__date' ],
            sortClass: 'sort-filter'
        };

        this.tipsList = new List( 'tips__list_container', options );
    }

    /**
     * Sort the tips using list.js
     *
     * @param {object} e Click event.
     */
    sortTips( e ) {
        const elem     = e.target;
        const { sort } = elem.dataset;

        elem.classList.toggle( 'active' );
        if ( elem.classList.contains( 'active' ) ) {
            this.tipsList.sort( sort, { order: 'asc' });
        } else {
            this.tipsList.sort( sort, { order: 'desc' });
        }
    }

    /**
     * Run when the tip form is submit
     *
     * @return false
     */
    saveTip() {
        this.$errors.hide();
        this.$generalErr.html( '' );
        this.$loader.show();
        const data = new FormData( this.$tipsForm[0]);
        $.ajax({
            url: pof.tips_url,
            type: 'POST',
            data,
            success: ( data ) => {
                if ( typeof data === 'string' ) {
                    data = JSON.parse( data );
                }

                if ( data.status === 'error' ) {
                    this.handleError( data.message );
                } else {
                    this.handleSuccess( data.message );
                }
                this.$loader.hide();
            },
            cache: false,
            contentType: false,
            processData: false
        });

        return false;
    }

    /**
     * Handle tip save error
     *
     * @param  {string} msg Message to display.
     */
    handleError( msg ) {
        this.$generalErr.append( '<p>' + msg + '</p>' ).show();
    }

    /**
     * Handle tip save success
     *
     * @param  {string} msg Message to display.
     */
    handleSuccess( msg ) {
        this.$container.html( '<div data-alert class="alert-box info radius">' + msg + '</div>' );
    }
}

export default new Tips();
