import FastClick from 'fastclick';
import ClipboardJS from 'clipboard';

import 'jquery-ui/themes/base/core.css';
import 'jquery-ui/themes/base/theme.css';
import 'jquery-ui/themes/base/dialog.css';
import 'jquery-ui/ui/widgets/dialog';

window.Partio = ( function( window, document, $ ){

    var app = {};

    app.cache = function(){

        //etusivun slider
    	app.$slider = $('.slider');

        app.currentLang         = $('body').data('lang');

        //menu
        app.$menu               = $('.menu-wrapper');
        app.$content            = $('.content-wrapper');
        app.$programContent     = $('#program-content');
        app.$spinner            = $('.partio-spinner');
        app.$menu_has_children  = $('.menu-item-has-children');
        app.$menu_has_children_toggler = $('.menu-item-has-children > a > .toggler');

        app.$mainmenu           = $('.main_menu-wrapper');
        app.$frontpageContent   = $('.frontpage-content-wrapper')
        
        app.$showmainmenu       = $('.show-main_menu');
        app.$showmenu           = $('.show-menu');
        app.menuheight          = app.$menu.outerHeight();
        app.contentheight       = app.$content.outerHeight();

        // second lang menu
        app.$secondLangMenu     = $('#second-lang-menu');
        app.$dynamicLangLinks   = app.$secondLangMenu.find('.dynamic');

        //vinkit ja kommentit
        app.$tips_heading = $('.tips__heading');
        app.$tip__content = $('.tip__content');

        //taskilistat ja printtinappi
        app.$tasks__filter_heading = $('.tasks__filter-heading');
        app.$printbutton = $( '.print-button' );

        //haku
        app.$search_section__header = $('.search-section__header');
    };

    app.dp_ajax_success = function(parsedSuccess, textStatus, jqXHR) {
        console.log('success', parsedSuccess);
    };

    app.dp_ajax_error = function(errorThrown, textStatus, jqXHR) {
        console.log('error', errorThrown);
    };

    /**
     * Setup clipboard copying
     */
    app.setupClipboard = () => {
        // Initialize clipboard
        const clipboard = new ClipboardJS( '.clipboard' );
        clipboard.on( 'success', ( e ) => {
            // Show msg on copy
            $( e.trigger ).addClass( 'copied' );
            $( e.trigger ).on( 'mouseleave', ( ev ) => {
                $( ev.currentTarget ).removeClass( 'copied' ).off();
            });
        });
        clipboard.on( 'error', ( e ) => {
            // On error just display text prompt with url
            prompt( 'Share', e.text );
        });
    };

    app.init = function(){

        $( "#dialog-f3f4a14fafe40e017215c7ef67286aeb" ).dialog({
            autoOpen: false
          });

        $( "#js-qr-code-f3f4a14fafe40e017215c7ef67286aeb" ).on( "click", function() {
            $( "#dialog-f3f4a14fafe40e017215c7ef67286aeb" ).dialog( "open" );
          });

        app.setupClipboard();

        app.cache();

        // Language switcher
        app.$dynamicLangLinks.on('click', app.switchLang);

        //init foundation
        $(document).foundation();

        //init slick slider
        app.$slider.slick({
			"autoplay": true,
			"autoplaySpeed": app.$slider.data('time'),
			"arrows": false,
			"fade": true,
			"infinite": true,
			"pause": false,
			"speed": 500
		});

        $(function() {
            FastClick.attach(document.body);
        });


        // mainmenu auki
        app.$showmainmenu.on('click',function(){
            app.$showmainmenu.toggleClass('move-right');
            app.$mainmenu.toggleClass('move-right');
            app.$frontpageContent.toggleClass('move-right');
        });


        //menu auki
        app.$showmenu.on('click',function(){
            app.$menu.toggleClass('move-right');
            app.$content.toggleClass('move-right');
        });

        //menun dropdownien avaaminen / sulkeminen
        app.$menu_has_children_toggler.each(function(){

            var self = $(this);
            self.on('click',function(e){
                e.preventDefault();
                e.stopPropagation();

                var parent_li = self.closest('.menu-item-has-children');

                parent_li.siblings().removeClass('opened');
                parent_li.siblings().find('.toggler').removeClass('opened');
                self.toggleClass('opened');
                parent_li.toggleClass('opened');

                //lasketaan contentille uusi korkeus jos menun uusi korkeus korkeampi kuin content
                var menu_tempheight = app.$menu.outerHeight();
                if ( menu_tempheight > app.contentheight) {
                    app.$content.css('min-height',menu_tempheight);
                }

            });
        });

        // vinkkien avaaminen / sulkeminen
        // lisää each
        app.$tips_heading.on('click',function(){
            var toggle = $(this);
            toggle.toggleClass('opened');
            toggle.closest('.tips__header').siblings('.tips__body').toggleClass('opened');
        });

        // taskien avaaminen / sulkeminen
        // tähän sama
        app.$tasks__filter_heading.on('click',function(){
            var toggle = $(this);
            toggle.toggleClass('opened');
            toggle.closest('.tasks__heading-row').next('.tasks__body-row').toggleClass('opened');
        });

        // taskien avaaminen / sulkeminen
        // tähän sama
        app.$search_section__header.on('click',function(){
            var toggle = $(this);
            toggle.toggleClass('opened');
            toggle.next('.search-section__body').toggleClass('opened');


            //lasketaan contentille uusi korkeus jos menun uusi korkeus korkeampi kuin content
            var menu_tempheight = app.$menu.outerHeight();
            if ( menu_tempheight > app.contentheight) {
                app.$content.css('min-height',menu_tempheight);
            }

        });

        // vinkkien pienennys ja niiden avaaminen jos vinkki liian pitkä
        // kesken
        // tähän myös
        app.$tip__content.dotdotdot({

            /*  The text to add as ellipsis. */
            ellipsis : '... ',
            /*  How to cut off the text/html: 'word'/'letter'/'children' */
            wrap : 'letter',
            /*  Wrap-option fallback to 'letter' for long words */
            fallbackToLetter: true,
            /*  jQuery-selector for the element to keep and put after the ellipsis. */
            after : null,
            /*  Whether to update the ellipsis: true/'window' */
            watch : true,
            /*  Optionally set a max-height, if null, the height will be measured. */
            height : null,
            /*  Deviation for the height-option. */
            tolerance : 0,

            /*  Callback function that is fired after the ellipsis is added,
                receives two parameters: isTruncated(boolean), orgContent(string). */
            callback : function( isTruncated, orgContent ) {},

            lastCharacter   : {
                /*  Remove these characters from the end of the truncated text. */
                remove      : [ ' ', ',', ';', '.', '!', '?' ],
                /*  Don't add an ellipsis if this array contains
                    the last character of the truncated text. */
                noEllipsis  : []
            }
        });

        // taskin printtaaminen
        app.$printbutton.each(function(){
            $(this).on( 'click', function(){
                window.print();
            });
        });

        // sivulatauksella katsotaan menun korkeus ja contentille sama jos menu korkeampi
        if ( app.menuheight > app.contentheight) {
            app.$content.css('min-height',app.menuheight);
        }

    };

    app.switchLang = function(e) {
        e.preventDefault();
        e.stopPropagation();
        app.$spinner.show();
        var id      = $(e.target).data('id'),
            lang    = $(e.target).data('lang'),
            model   = $(e.target).data('model');

        var params = {
            args: {
                id: id,
            },
            get: '?lang=' + app.currentLang,
            tidy:   true,
            render: true,
            success: function(data) {
                app.$spinner.hide();
                app.$programContent.html(data);
            },
            error: function(data) {
                console.log(data);
            }
        };
        DustPress.ajax( model + '/translate', params );
    };

    $(document).ready( app.init );

    return app;

})( window, document, jQuery );