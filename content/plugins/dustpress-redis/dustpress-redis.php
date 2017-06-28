<?php
/**
 * Plugin Name: DustPress Redis Cache
 * Plugin URI: http://www.geniem.com
 * Description: Provides redis cacheing for WordPress-sites powered with DustPress
 * Version: 0.2.0
 * Author: Miika Arponen & Ville Siltala
 */

/**
 * Class DustPress_Redis
 */
class DustPress_Redis {

    /**
     * The Predis instance.
     *
     * @var object
     */
    private $redis;

    /**
     * DustPress_Redis constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init_plugin' ) );
    }

    /**
     * Initializes the plugin if we are using the Redis cache for current request.
     */
    public function init_plugin() {
        // We are not using Redis or the user is logged in.
        if ( WP_REDIS_DISABLE === 1 || is_user_logged_in() ) {
            return;
        }

        if ( ! defined( 'WP_USE_THEMES' ) ) {
            define( 'WP_USE_THEMES', true );
        }

        $this->init_redis();
        add_action( 'dustpress/output', [ $this, 'handle_output' ], ( PHP_INT_MAX - 100 ), 2 );
    }

    /**
     * Getter for the redis instace.
     *
     * @return object
     */
    public function get_redis_instance() {
        return $this->redis;
    }

    /**
     * Initialize the Predis client.
     */
    private function init_redis() {

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
        $this->redis = new Predis\Client( $redis_params );
    }

    /**
     * Save the output to Redis cache.
     *
     * @param string  $output The output html.
     * @param boolean $main   Is this the main run of DustPress.
     *
     * @return mixed
     */
    public function handle_output( $output, $main ) {

        if ( $main ) {
            // init vars
            $domain = $_SERVER[ 'HTTP_HOST' ];
            $url    = 'http://' . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ];
            $url    = str_replace( '?r=y', '', $url );
            $url    = str_replace( '?c=y', '', $url );
            $dkey   = md5( $domain );
            $ukey   = md5( $url );

            // Store to cache only if the page exist and is not a search result.
            if ( ! is_404() && ! is_search() ) {
                // store html contents to redis cache
                $this->redis->hset( $dkey, $ukey, $output );
            }
        }

        return $output;
    }
}

$dustpress_redis = new DustPress_Redis();
