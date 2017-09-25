<?php
/**
 * WORDPRESS CONFIGURATIONS
 */

// Run setup on admin side.
if ( ! defined( 'SETUP_DONE' ) ) {
    require_once( dirname( __FILE__ ) . '/setup.php' );
}

// =================
// DATABASE SETTINGS
// =================
define( 'DB_NAME', getenv( 'DB_NAME' ) );
define( 'DB_USER', getenv( 'DB_USER' ) );
define( 'DB_PASSWORD', getenv( 'DB_PASSWORD' ) );
define( 'DB_HOST', getenv( 'DB_HOST' ) );

// ===============
// SERVER SETTINGS
// ===============
switch ( $_SERVER[ 'SERVER_NAME' ] ) {
    case 'partio-ohjelma.fi': // REAL LIVE DOMAIN
        define( 'WP_HOME', 'http://partio-ohjelma.fi' );
        define( 'WP_SITEURL', 'http://partio-ohjelma.fi/wp/' );
        define( 'WP_PUB_SITEURL', 'http://partio-ohjelma.fi' );
        define( 'WP_CONTENT_BASE_URL', 'http://static.partio-ohjelma.fi' );
        define( 'WP_ADMIN_URL', 'http://admin.partio-ohjelma.fi' );
        break;
    case 'admin.partio-ohjelma.fi': // REAL ADMIN DOMAIN
        define( 'WP_HOME', 'http://admin.partio-ohjelma.fi' );
        define( 'WP_SITEURL', 'http://admin.partio-ohjelma.fi/wp/' );
        define( 'WP_PUB_SITEURL', 'http://partio-ohjelma.fi' );
        define( 'WP_CONTENT_BASE_URL', 'http://static.partio-ohjelma.fi' );
        define( 'WP_ADMIN_URL', 'http://admin.partio-ohjelma.fi' );
        break;
    case 'admin.partio.geniem.com': // ADMIN DOMAIN
        define( 'WP_HOME', 'http://admin.partio.geniem.com' );
        define( 'WP_SITEURL', 'http://admin.partio.geniem.com/wp/' );
        define( 'WP_PUB_SITEURL', 'http://partio.geniem.com' );
        define( 'WP_CONTENT_BASE_URL', 'http://partio.cdn.geniem.com' );
        define( 'WP_ADMIN_URL', 'http://admin.partio.geniem.com' );
        break;
    case 'partio.geniem.com': // LIVE DOMAIN
        define( 'WP_HOME', 'http://partio.geniem.com' );
        define( 'WP_SITEURL', 'http://partio.geniem.com/wp/' );
        define( 'WP_PUB_SITEURL', 'http://partio.geniem.com' );
        define( 'WP_CONTENT_BASE_URL', 'http://partio.cdn.geniem.com' );
        define( 'WP_ADMIN_URL', 'http://admin.partio.geniem.com' );
        break;
    case 'partio-ohjelma.dev': // LOCAL DEV DOMAIN
        define( 'WP_HOME', 'http://partio-ohjelma.dev' );
        define( 'WP_SITEURL', 'http://partio-ohjelma.dev/wp/' );
        define( 'WP_PUB_SITEURL', 'http://partio-ohjelma.dev' );
        define( 'WP_CONTENT_BASE_URL', 'http://partio-ohjelma.dev' );
        define( 'WP_ADMIN_URL', 'http://partio-ohjelma.dev' );
        break;
    case 'partio.dev': // LOCAL DEV DOMAIN
        define( 'WP_HOME', 'http://partio.dev' );
        define( 'WP_SITEURL', 'http://partio.dev/wp/' );
        define( 'WP_PUB_SITEURL', 'http://partio.dev' );
        define( 'WP_CONTENT_BASE_URL', 'http://partio.dev' );
        define( 'WP_ADMIN_URL', 'http://partio.dev' );
        break;
    case 'partio.jackie.geniem.com': // STAGING DOMAIN
        define( 'WP_HOME', 'http://partio.jackie.geniem.com' );
        define( 'WP_SITEURL', 'http://partio.jackie.geniem.com/wp/' );
        define( 'WP_PUB_SITEURL', 'http://partio.jackie.geniem.com' );
        define( 'WP_CONTENT_BASE_URL', 'http://partio.jackie.geniem.com' );
        define( 'WP_ADMIN_URL', 'http://partio.jackie.geniem.com' );
        break;
}

define( 'COOKIEPATH', preg_replace( '|http?://[^/]+|i', '', '/' ) );
define( 'EMPTY_TRASH_DAYS', 5 );
define( 'WP_POST_REVISIONS', 2 );

// ========================
// Custom Content Directory
// ========================
define( 'WP_CONTENT_DIR', dirname( __FILE__ ) . '/content' );
define( 'WP_CONTENT_URL', 'http://' . $_SERVER[ 'HTTP_HOST' ] . '/content' );

// ==================================
// REMOVE POLYLANG HOME URL CACHE
// Removes problems with redirect loop
// ===================================
define( 'PLL_CACHE_HOME_URL', false );

// ================================================
// You almost certainly do not want to change these
// ================================================
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// ==============================================================
// Salts, for security
// Grab these from: https://api.wordpress.org/secret-key/1.1/salt
// ==============================================================
define( 'AUTH_KEY', getenv( 'AUTH_KEY' ) );
define( 'SECURE_AUTH_KEY', getenv( 'SECURE_AUTH_KEY' ) );
define( 'LOGGED_IN_KEY', getenv( 'LOGGED_IN_KEY' ) );
define( 'NONCE_KEY', getenv( 'NONCE_KEY' ) );
define( 'AUTH_SALT', getenv( 'AUTH_SALT' ) );
define( 'SECURE_AUTH_SALT', getenv( 'SECURE_AUTH_SALT' ) );
define( 'LOGGED_IN_SALT', getenv( 'LOGGED_IN_SALT' ) );
define( 'NONCE_SALT', getenv( 'NONCE_SALT' ) );

// ==============================================================
// Table prefix
// Change this if you have multiple installs in the same database
// ==============================================================
$table_prefix = 'wp_';

// ================================
// Language
// Leave blank for American English
// ================================
define( 'WPLANG', '' );

// ===========
// Hide errors
// ===========
ini_set( 'display_errors', 0 );
define( 'WP_DEBUG_DISPLAY', false );

// =====================
// Settings for importer
// =====================
ini_set( 'max_execution_time', 1200 );
define( 'WP_MAX_MEMORY_LIMIT', '1024M' );

// ===============
// Disable WP Cron
// ===============
define( 'DISABLE_WP_CRON', true );

// =================================================================
// Debug mode
// Debugging? Enable these. Can also enable them in local-config.php
// =================================================================
// define( 'SAVEQUERIES', true );
// define( 'WP_DEBUG', true );

// ===========================================================================================
// This can be used to programatically set the stage when deploying (e.g. production, staging)
// ===========================================================================================
define( 'WP_STAGE', '%%WP_STAGE%%' );
define( 'STAGING_DOMAIN', '%%WP_STAGING_DOMAIN%%' ); // Does magic in WP Stack to handle staging domain rewriting

// ====================
// Composer vendor path
// ====================
define( 'VENDOR_PATH', dirname( __FILE__ ) . '/vendor/' );

// ===================
// Bootstrap WordPress
// ===================
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __FILE__ ) . '/wp/' );
}
require_once( ABSPATH . 'wp-settings.php' );
