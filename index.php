<?php
/**
 * The client side index.php file
 */

// Setup autoloading and environment variables.
require_once( dirname( __FILE__ ) . '/setup.php' );

define( 'WP_USE_THEMES', true );

// To disable redis caching, set the WP_REDIS_DISABLE env as 1.
if ( WP_REDIS_DISABLE === 1 ) {
    // NO CACHING
    require( './wp/wp-blog-header.php' );
    return;
}

$redis_params = [
    'host'     => WP_REDIS_HOST,
    'port'     => 6379,
    'database' => WP_REDIS_DB,
];
// If a password is set..
if ( WP_REDIS_PASSWORD !== false && ! empty( WP_REDIS_PASSWORD ) ) {
    $redis_params['password'] = WP_REDIS_PASSWORD;
}

// Connect the Redis client.
$redis = new Predis\Client( $redis_params );

// Init cache vars.
$domain = $_SERVER['HTTP_HOST'];
$url    = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$url    = str_replace( '?r=y', '', $url );
$url    = str_replace( '?c=y', '', $url );
$dkey   = md5( $domain );
$ukey   = md5( $url );

// Debugging vars.
$debug                    = 0;  // set to 1 if you wish to see execution time and cache actions
$display_powered_by_redis = 0;  // set to 1 if you want to display a powered by redis message with execution time, see below
$start = microtime();   // start timing page exec

// check if page isn't a comment submission
( isset( $_SERVER['HTTP_CACHE_CONTROL'] ) && $_SERVER['HTTP_CACHE_CONTROL'] == 'max-age=0' ) ? $submit = 1 : $submit = 0;

// check if logged in to wp
$cookie   = var_export( $_COOKIE, true );
$loggedin = preg_match( "/wordpress_logged_in/", $cookie );

// check if a cache of the page exists
if ( $redis->hexists( $dkey, $ukey ) && ! $loggedin && ! $submit && ! strpos( $url, '/feed/' ) ) {
    echo $redis->hget( $dkey, $ukey );
    $cached = 1;
    $msg    = 'this is a cache';
    // if a comment was submitted or clear page cache request was made delete cache of page
} elseif ( $submit || substr( $_SERVER['REQUEST_URI'], - 4 ) == '?r=y' ) {

    // Geniem cdn cache buffer start
    ob_start();
    // Set the variable for cdn urls to be filled from WP settings
    $GLOBALS['gen_cdn_urls'] = array();
    require( './wp/wp-blog-header.php' );

    $redis->hdel( $dkey, $ukey );
    $msg = 'cache of page deleted';

    // Geniem cdn cache buffer end
    $html = ob_get_clean();
    echo geniem_magic( $html );

    // delete entire cache, works only if logged in
} elseif ( $loggedin && substr( $_SERVER['REQUEST_URI'], - 4 ) == '?c=y' ) {
    // Geniem cdn cache buffer start
    ob_start();

    // Set the variable for cdn urls to be filled from WP settings
    $GLOBALS['gen_cdn_urls'] = array();
    require( './wp/wp-blog-header.php' );

    if ( $redis->exists( $dkey ) ) {
        $redis->del( $dkey );
        $msg = 'domain cache flushed';
    } else {
        $msg = 'no cache to flush';
    }

    // Geniem cdn cache buffer end
    $html = ob_get_clean();
    echo geniem_magic( $html );

    // if logged in don't cache anything
} elseif ( $loggedin ) {

    // Geniem cdn cache buffer start
    ob_start();
    // Set the variable for cdn urls to be filled from WP settings
    $GLOBALS['gen_cdn_urls'] = array();
    require( './wp/wp-blog-header.php' );

    // Geniem cdn cache buffer end
    $html = ob_get_clean();
    echo geniem_magic( $html );

    $msg = 'not cached';

    // do nothing (we let DustPress to handle the caching)
} else {
    require( './wp/wp-blog-header.php' );
    $msg = 'cache is set';
}

$end = microtime(); // get end execution time

// show messages if debug is enabled
if ( $debug ) {
    ob_start();
    echo $msg . ': ';
    echo t_exec( $start, $end );
    echo( ob_get_clean() );
}

if ( $cached && $display_powered_by_redis ) {
    // You should move this CSS to your CSS file and change the: float:right;margin:20px 0;
    echo "<style>#redis_powered{float:right;margin:20px 0;background:url(http://images.staticjw.com/jim/3959/redis.png) 10px no-repeat #fff;border:1px solid #D7D8DF;padding:10px;width:190px;}
    #redis_powered div{width:190px;text-align:right;font:10px/11px arial,sans-serif;color:#000;}</style>";
    echo "<a href=\"http://www.jimwestergren.com/wordpress-with-redis-as-a-frontend-cache/\" style=\"text-decoration:none;\"><div id=\"redis_powered\"><div>Page generated in<br/> " . t_exec( $start, $end ) . " sec</div></div></a>";
}

// time diff
function t_exec( $start, $end ) {
    $t = ( getmicrotime( $end ) - getmicrotime( $start ) );
    return round( $t, 5 );
}

// get time
function getmicrotime( $t ) {
    list( $usec, $sec ) = explode( " ", $t );

    return ( (float) $usec + (float) $sec );
}

function geniem_magic( $html ) {
    if ( isset( $GLOBALS['gen_cdn_urls'] ) && is_array( $GLOBALS['gen_cdn_urls'] ) && count( $GLOBALS['gen_cdn_urls'] ) > 0 ) {
        $pattern = "#(" . preg_quote( WP_CONTENT_BASE_URL ) . ")[^\"']*#";
        $html    = preg_replace_callback( $pattern, "gen_cdn_callback", $html );
    }

    return $html;
}

function gen_cdn_callback( $matches ) {
    $int         = intval( substr( md5( $matches[0] ), 0, 8 ), 16 );
    $url_amount  = count( $GLOBALS['gen_cdn_urls'] );
    $path        = str_replace( $matches[1], '', $matches[0] );
    $replace_key = ( $int % $url_amount );

    return $GLOBALS['gen_cdn_urls'][$replace_key] . $path;
}
