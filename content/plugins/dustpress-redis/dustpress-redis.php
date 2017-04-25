<?php
/*
Plugin Name: DustPress Redis Cache
Plugin URI: http://www.geniem.com
Description: Provides redis cacheing for WordPress-sites powered with DustPress
Version: 0.1
Author: Miika Arponen & Ville Siltala
*/

class DustPress_Redis {

    private $cache;
    private $start;
    private $local_redis;
    private $redis;
    private $redis_params;
    private $redis_read_host;
    private $redis_read_pass;
    private $redis_read_database;

    public function __construct() {
        if( ! defined('CONFIG_PATH') ) return;

        add_action( 'init', array( $this, 'init_plugin' ) );
    }

    public function init_plugin() {
        include( CONFIG_PATH . "redis-config.php" );

        $this->local_redis          = $local_redis;
        $this->redis_read_host      = $redis_read_host;
        $this->redis_read_pass      = $redis_read_pass;
        $this->redis_read_database  = $redis_read_database;

        /*ob_start();
        var_dump($this->redis_read_host);
        var_dump($this->redis_read_pass);
        var_dump($this->redis_read_database);        
        error_log(ob_get_clean());*/

        // from wp
        define( 'WP_USE_THEMES', true );

        if ( $USE_REDIS_CACHE && !is_user_logged_in() ) {
            $this->init_redis();
            add_action( 'dustpress/output', array( $this, 'handle_output'), (PHP_INT_MAX - 100), 2 );
        }  
    }

    // getter for the redis instace
    public function get_redis_instance() {
        return $this->redis;
    }

    // init predis
    private function init_redis() { 
        //include_once( CONFIG_PATH . 'predis.php');

        if ( $this->local_redis ) {
            $this->redis_params = array(
                'host'      => $this->redis_read_host,
                'port'      => 6379,
                'password'  => $this->redis_read_pass,
                'database'  => $this->redis_read_database
            );    
        } else {
            $this->redis_params = array(
                'host'      => $this->redis_read_host,
                'port'      => 6379,
                'password'  => $this->redis_read_pass,
                'database'  => $this->redis_read_database
            );    
        }

        $this->redis = new Predis\Client( $this->redis_params );

    }

    // save the output to redis cache
    public function handle_output( $output, $main ) {

        if ( $main ) {
            // init vars
            $domain = $_SERVER['HTTP_HOST'];
            $url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
            $url = str_replace('?r=y', '', $url);
            $url = str_replace('?c=y', '', $url);
            $dkey = md5($domain);
            $ukey = md5($url);

            // Store to cache only if the page exist and is not a search result.
            if (!is_404() && !is_search()) {
                // store html contents to redis cache
                $this->redis->hset($dkey, $ukey, $output);
            }
        }

        return $output;
    }
}

$dustpress_redis = new DustPress_Redis();