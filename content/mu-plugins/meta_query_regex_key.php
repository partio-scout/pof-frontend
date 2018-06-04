<?php
/**
 *  Plugin Name:   Extended Meta Key Search In WP_Query
 *  Description:   Custom '_key_compare' argument as REGEXP, RLIKE or LIKE
 *  Plugin URI:    http://wordpress.stackexchange.com/a/193841/26350
 *  Plugin Author: Birgir Erlendsson (birgire)
 *  Version:       0.0.3
 */

// https://wordpress.stackexchange.com/questions/193791/use-regexp-in-wp-query-meta-query-key#answer-193841
add_action( 'pre_get_posts', function( $q )
{
    // Check the meta query:
    $mq = $q->get( 'meta_query' );

    if( empty( $mq ) )
        return;

    // Init:
    $marker = '___tmp_marker___'; 
    $rx     = [];

    // Collect all the sub meta queries, that use REGEXP, RLIKE or LIKE:
    foreach( $mq as $k => $m )                                    
    {
        if(    isset( $m['_key_compare'] )
            && in_array( strtoupper( $m['_key_compare'] ), [ 'REGEXP', 'RLIKE', 'LIKE' ] )
            && isset( $m['key'] )
        ) {
            // Mark the key with a unique string to secure the later replacements:
            $m['key'] .= $marker . $k; // Make the appended tmp marker unique

            // Modify the corresponding original query variable:
            $q->query_vars['meta_query'][$k]['key'] = $m['key'];

            // Collect it:
            $rx[$k] = $m;
        }
    }

    // Nothing to do:
    if( empty( $rx ) )
        return;

    // Get access the generated SQL of the meta query:
    add_filter( 'get_meta_sql', function( $sql ) use ( $rx, $marker )
    {
        // Only run once:
        static $nr = 0;         
        if( 0 != $nr++ )
            return $sql;

        // Modify WHERE part where we replace the temporary markers:
        foreach( $rx as $k => $r )
        {
            $sql['where'] = str_replace(
                sprintf(
                    ".meta_key = '%s' ",
                    $r['key']
                ),
                sprintf(
                    ".meta_key %s '%s' ",
                    $r['_key_compare'],
                    str_replace(
                        $marker . $k,
                        '',
                        $r['key']
                    )
                ),
                $sql['where']
            );
        }
        return $sql;
    });

});
