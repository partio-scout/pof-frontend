<?php
/**
 * Search page
 */
/**
 * Class Search
 */

class Search extends \DustPress\Model {
    /**
     * Enable these methods for DustPress.js.
     *
     * @var array
     */
    protected $api = [
        'Results'
    ];

    public function Submodules() {
        $this->bind_sub( 'ProgramLangnav', [ 'model' => 'Search' ] );
        $this->bind_sub( 'Attachments' );
        $this->bind_sub( 'Header' );
        $this->bind_sub( 'Footer' );
        $this->bind_sub( 'Breadcrumbs' );
    }
    
    /**
     *  Content section
     */
    public function Content() {
        return true;
    }

    /**
     *  Get search term.
     */
    public function Term() {
        $term = get_query_var( 's' );
        return $term;
    }
    /**
     *  Get search results.
     */
    public function Results() {
        $per_page   = get_option( 'posts_per_page' );
        $page       = (int) get_query_var( 'paged' );
        $page       = $page ? $page : 1; // Force page value
        $displaying = $per_page * $page;
        $search_term = get_query_var( 's' );
        // Do not execute if no search term - relevanssi doesn't like it.
        if ( empty( $search_term ) ) {
            return false;
        }
        else {
            // Remove - from search_term. Else the word after - will be excluded from query.
            $search_term = str_replace( '-', ' ', $search_term );
            // Args for search.
            $args = array(
                's'             => $search_term,
                'post_type'     => array( 'page' ),
                'post_status'   => 'publish',
                'meta_query'    => array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'api_type',
                        'value'   => 'task',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => 'api_type',
                        'value'   => 'taskgroup',
                        'compare' => '=',
                    )
                )
            );
            // Check if executed with ajax and set offset if true.
            if ( wp_doing_ajax() ) {
                $args['posts_per_page'] = $per_page;
                $args['offset']         = $displaying;
            }
            // Else show posts without offset.
            else {
                $args['posts_per_page'] = $displaying;
            }
            $query = new WP_Query( $args );
            if ( function_exists( 'relevanssi_do_query' ) ) {
                // Make relevanssi search.
                $results = relevanssi_do_query( $query );
            }
            else {
                $error = [
                    'message' => 'Relevanssi is not activated!',
                ];
                return $error;
            }
            // Build object to be returned.
            $data                = new stdClass();
            $data->posts         = $results;
            $data->count         = count($results);
            $data->max_num_pages = $query->max_num_pages;
            $data->page          = $page;
            // Modify every post
            foreach ( $data->posts as &$post ) {
                // Get parents
                $acf_post = \DustPress\Query::get_acf_post( $post->ID );
                $post->ingress = $acf_post->fields['api_ingress'];
                $post->search_type = $acf_post->fields['api_type'];
                $post->parents = map_api_parents( json_decode_pof( $acf_post->fields['api_path'] ) );
                // Get permalink
                $post->url = get_permalink( $post->ID );
            } // End foreach().
            return $data;
        } // End if().
    }

    // Bind translated strings.
    public function S() {
        $s = [
            'aktiviteettiryhma'             => __( 'Task group', 'pof' ),
        ];
        return $s;
    }     
    
}