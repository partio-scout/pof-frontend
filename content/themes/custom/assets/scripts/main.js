// jQuery polyfill for foundation-sites
jQuery.fn.load = function( cb ) {
	$( window ).trigger( 'load', cb );
};

// External libraries
import 'babel-polyfill';
import Modernizr from 'modernizr';
import 'normalize-css';
import 'slick-carousel';
import 'foundation-sites/js/foundation';
import 'foundation-sites/js/foundation/foundation.equalizer';
import 'foundation-sites/js/foundation/foundation.reveal';
import 'enquire.js';
import 'dotdotdot';

// Local scripts
import './app';
import './search';
import './tips';

// Styles
import '../styles/app.scss';
import 'slick-carousel/slick/slick.scss';
import 'slick-carousel/slick/slick-theme.scss';

// Make the jQuery instance global for dustpress-js
window.jQuery = jQuery;
