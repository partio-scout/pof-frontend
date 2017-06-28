<?php
/**
 * Environment setup file
 */

// Enable composer autoloading.
require_once( __DIR__ . '/vendor/autoload.php' );

// ===========================
// SETUP ENVIRONEMNT VARIABLES
// ===========================
$required_envs = [
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
    'AUTH_KEY',
    'SECURE_AUTH_KEY',
    'LOGGED_IN_KEY',
    'NONCE_KEY',
    'AUTH_SALT',
    'SECURE_AUTH_SALT',
    'LOGGED_IN_SALT',
    'NONCE_SALT',
    'WP_REDIS_HOST',
    'WP_REDIS_DB',
];
$root_dir = dirname( __FILE__ );

// .env is located in the project root in local environment.
if ( file_exists( $root_dir . '/.env' ) ) {
    define( 'WP_LOCAL_DEV', true );
}
else {
    define( 'WP_LOCAL_DEV', false );
    // Step one level up
    $root_dir = dirname( __FILE__ ) . '/..';
}

// Use Dotenv to set required environment variables and load the .env file.
if ( class_exists( 'Dotenv\Dotenv' ) && file_exists( $root_dir . '/.env' ) ) {
    $dotenv = new Dotenv\Dotenv( $root_dir );
    $dotenv->load();
    try {
        $dotenv->required( $required_envs );
    } catch ( Exception $e ) {
        // @codingStandardsIgnoreStart
        die( $e->getMessage() );
        // @codingStandardsIgnoreEnd
    }
} else {
    die( 'Environment variables not found!' );
}

define( 'SETUP_DONE', true );
