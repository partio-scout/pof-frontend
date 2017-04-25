<?php
/**
REDIS CACHE CONFIG
*/

// ENABLE / DISABLE REDIS CACHE
$USE_REDIS_CACHE = true;

// DEVELOPMENT
$local_redis = true;
$redis_read_host = '127.0.0.1';
$redis_read_database = 0;

if ( $_SERVER['SERVER_NAME'] == 'partio-ohjelma.fi' || $_SERVER['SERVER_NAME'] == 'admin.partio-ohjelma.fi' ) {
    // PRODUCTION
    $local_redis = false;
    $redis_read_host = '%%REDIS_READ_HOST%%';
    $redis_read_pass = '%%REDIS_READ_PASS%%';
    $redis_read_database = '%%REDIS_READ_DB%%';
} else if ( $_SERVER['SERVER_NAME'] == 'partio.jackie.geniem.com' ) {
    // STAGING
    $local_redis = false;
    $redis_read_host = '%%REDIS_READ_HOST%%';
    $redis_read_pass = '%%REDIS_READ_PASS%%';
    $redis_read_database = '%%REDIS_READ_DB%%';
}
