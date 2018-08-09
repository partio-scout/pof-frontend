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

    public function SearchBase() {
        return search_base( pll_current_language() );
    }

    public function PaginationBase() {
        return pagination_base( pll_current_language() );
    }

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
     * Get search terms and translations from api
     */
    public static function ApiSearchTerms() {
        // Get local data
        $haku_json    = get_field( 'haku-json', 'option' );
        $kaannos_json = get_field( 'kaannos-json', 'option' );
        $ohjelma_json = get_field( 'ohjelma-json', 'option' );
        $locale       = explode( '_', get_locale() )[0];

        // Get remote data
        $program      = \POF\Api::get( $ohjelma_json, true );
        $search_terms = \POF\Api::get( $haku_json, true );
        $translations = \POF\Api::get( $kaannos_json, true );

        // Parse age groups
        $age_groups = $program['program'][0]['agegroups'];
        usort( $age_groups, function( $a, $b ) {
            return $a['order'] - $b['order'];
        });

        // Remove invalid field types
        $search_terms = array_filter( $search_terms, function( $field ) {
            return ! empty( $field['type'] );
        });

        // Collect field name translations
        $field_name_translations = $translations['haku'];

        // Combine the search terms and translations
        foreach ( $search_terms as $field_name => &$field_data ) {

            // Get field group translation
            foreach ( $field_name_translations as $name_translations ) {
                if ( $name_translations['lang'] === $locale ) {
                    foreach ( $name_translations['items'] as $name_translation ) {
                        if ( $name_translation['key'] === $field_name ) {
                            $field_data['label'] = $name_translation['value'];
                        }
                    }
                }
            }

            // Get each field translation
            foreach ( $field_data['fields'] as &$field ) {

                // Get translations for correct field
                foreach ( $translations as $translation_field_name => $field_translations ) {
                    if ( $translation_field_name === $field_name ) {

                        // Get correct language translations
                        foreach ( $field_translations as $translation_data ) {
                            if ( $translation_data['lang'] === $locale ) {

                                foreach ( $translation_data['items'] as $translation ) {
                                    if ( $translation['key'] === $field ) {
                                        // Transform the field to an object for better dust handling
                                        $field = (object) $translation;
                                        // Break all the way to the $field_data['fields'] loop
                                        // to handle the next field value
                                        break 3;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $result = (object) [
            'search_terms' => $search_terms,
            'age_groups'   => $age_groups,
            'locale'       => $locale,
        ];

        return $result;
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
        $ajax_args = $this->get_args();

        // Parse serialized ajax args
        if ( $ajax_args ) {
            parse_str( $ajax_args->filter, $ajax_args->filter );
        }

        $per_page    = absint( get_option( 'posts_per_page' ) ?: 10 );
        $page        = absint( ( $ajax_args->page ?? get_query_var( 'paged', 1 ) ) ?: 1 );
        $displaying  = $per_page * $page;
        $search_term = $ajax_args->filter['s'] ?? get_query_var( 's' );
        // Remove - from search_term. Else the word after - will be excluded from query.
        $search_term = str_replace( '-', ' ', $search_term );

        $params = (object) [
            'ajax_args'   => $ajax_args,
            'per_page'    => $per_page,
            'page'        => $page,
            'displaying'  => $displaying,
            'search_term' => $search_term,
        ];

        $results         = $this->get_results( $params );
        $results->params = $params;

        return $results;
    }

    /**
     * Get results from params
     *
     * @param  stdClass $params Params to use for searching.
     * @return stdClass         Object containing metadata for request and post list.
     */
    protected function get_results( stdClass $params ) {
        $posts_start = microtime( true );
        $posts_key   = 'search/' . esc_sql( wp_json_encode( $params ) ) . '/' . get_locale();
        $posts       = wp_cache_get( $posts_key );
        if ( empty( $posts ) ) {
            $args = [
                's'              => $params->search_term,
                'post_type'      => [ 'page', 'pof_tip' ],
                'post_status'    => 'publish',
                'posts_per_page' => -1, //phpcs:ignore
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => 'api_type',
                        'value'   => 'task',
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'api_type',
                        'value'   => 'taskgroup',
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'api_type',
                        'value'   => 'pof_tip',
                        'compare' => '=',
                    ],
                ],
            ];

            // Get all posts with query
            $query = new WP_Query( $args );
            $posts = relevanssi_do_query( $query );

            wp_cache_set( $posts_key, $posts, null, HOUR_IN_SECONDS );
        }
        header( 'x-posts-time: ' . round( microtime( true ) - $posts_start, 4 ) );

        // Get extra post data for each post
        $posts = $this->get_post_data( $posts );

        // Filter the posts
        if ( ! empty( $params->ajax_args ) ) {
            $posts = $this->filter_posts( $posts, $params->ajax_args );
        }

        // Do pagination in php since we do the filtering in php as well
        $pagination_start = microtime( true );
        $offset = $params->page > 1 ? $params->page * $params->per_page : 0;
        $result = array_slice( $posts, $offset, $params->per_page );
        header( 'x-pagination-time: ' . round( microtime( true ) - $pagination_start, 4 ) );

        $data = (object) [
            'posts'         => $result,
            'count'         => count( $posts ),
            'max_num_pages' => ceil( count( $posts ) / $params->per_page ),
            'page'          => $params->page,
        ];

        return $data;
    }

    /**
     * Get each post data
     *
     * @param  array $posts An array of \WP_Post objects.
     * @return array        Modified $posts.
     */
    protected function get_post_data( array $posts ) {
        $post_data_start = microtime( true );
        foreach ( $posts as &$post ) {
            $post_key = 'postdata/' . $post->ID;
            $new_post = wp_cache_get( $post_key );
            if ( empty( $new_post ) ) {
                // Add fields
                $new_post = \DustPress\Query::get_acf_post( $post->ID );

                // Add other custom data
                $new_post->ingress = $new_post->post_excerpt;
                if ( $new_post->post_type === 'pof_tip' ) {
                    $new_post->search_type = $new_post->post_type;
                    $parent_id             = get_post_meta( $new_post->ID, 'pof_tip_parent', true );
                    $guid                  = get_post_meta( $new_post->ID, 'pof_tip_guid', true );
                    $parent_link           = get_permalink( $parent_id );
                    $parent_title          = get_the_title( $parent_id );
                    $new_post->url         = $parent_link . '#' . $guid;
                    $new_post->post_title  = __( 'Tip in task ', 'pof' ) . '<i>' . $parent_title . '</i>';
                }
                else {
                    $new_post->search_type = $new_post->fields['api_type'];
                    $new_post->url         = get_permalink( $new_post->ID );
                    $new_post->parents     = map_api_parents( json_decode_pof( $new_post->fields['api_path'] ) );
                    map_api_images( $new_post->fields['api_images'] );
                    if ( is_array( $new_post->fields['api_images'] ) ) {
                        $new_post->image = $new_post->fields['api_images'][0]['logo'];
                    }
                }

                wp_cache_set( $post_key, $new_post, null, HOUR_IN_SECONDS );
            }

            // Replace $post but keep the excerpt
            $excerpt            = $post->post_excerpt;
            $post               = $new_post;
            $post->post_excerpt = $excerpt;
        }
        header( 'x-post_data-time: ' . round( microtime( true ) - $post_data_start, 4 ) );

        return $posts;
    }

    /**
     * Filter the posts
     *
     * @param  array    $posts Posts to filter.
     * @param  stdClass $args  Args to filter by.
     * @return array           Filtered $posts.
     */
    protected function filter_posts( array $posts, stdClass $args ) {
        // Filter inside php as this is way faster than the query generated by WP_Query
        if ( ! empty( $args->filter ) ) {
            $filter_start = microtime( true );

            $filters = $args->filter;
            $model   = $this;
            $posts   = array_filter( $posts, function( $post ) use ( $filters, $model ) {
                $base_relation = $filters['base_relation'];

                // Collect all items to check into single array
                $filters_to_check = [
                    'global' => $filters['global'],
                ];
                foreach ( $filters['enabled_age_groups'] as $age_guid ) {

                    $added = false;
                    foreach ( $filters['enabled_task_groups'] as $task_guid ) {
                        if ( array_key_exists( $task_guid, $filters['agegroups'][ $age_guid ] ) ) {
                            $filters_to_check[ $task_guid ] = $filters['agegroups'][ $age_guid ][ $task_guid ];
                            $added                          = true;
                        }
                    }

                    // If not added then the agegroup was enabled but none of its taskgroups were
                    // So add the agegroup to the the list of filters to check instead of taskgroup
                    if ( ! $added ) {
                        $filters_to_check[ $age_guid ] = null;
                    }
                }

                // Check all filters
                $success = 0;
                foreach ( $filters_to_check as $guid => $filter ) {
                    $result = $model->single_group_filter( $post, $filter, $guid );

                    // If base relation is in OR mode succeed with only one success
                    if ( $base_relation === 'OR' && $result ) {
                        return true;
                    }
                    // Otherwise check all
                    elseif ( $result ) {
                        $success++;
                    }
                    // Otherwise fail immediately
                    else {
                        return false;
                    }

                }

                // If we are in AND mode and all were succesful
                if ( $success === count( $filters_to_check ) ) {
                    return true;
                }

                // Finally if we are in AND more and not all were successful fail
                return false;
            });
            header( 'x-filter-time: ' . round( microtime( true ) - $filter_start, 4 ) );
        }

        return $posts;
    }

    /**
     * Check a set of filters against a post to filter it
     *
     * @param  mixed  $post    Post to check.
     * @param  mixed  $filters Filters to check.
     * @param  string $guid    Taskgroup guid or 'global'.
     * @return bool            True or false depending on if post filter was succesful.
     */
    protected function single_group_filter( $post, $filters, string $guid ) {

        // First of all check taskgroups
        if ( $guid !== 'global' ) {
            if ( empty( $post->parents ) ) {
                return false;
            }

            $parent_success = false;
            foreach ( $post->parents as $post_parent ) {
                if ( $post_parent['guid'] === $guid ) {
                    $parent_success = true;
                    break;
                }
            }

            if ( ! $parent_success ) {
                return false;
            }
        }

        // Now check filters
        if ( ! empty( $filters['enabled'] ) ) {
            foreach ( $filters['enabled'] as $field_key ) {
                $relation = $filters['and_or'][ $field_key ] ?? 'AND'; // If no relation is found that means it is in false state = "AND"
                $filter   = $filters['filters'][ $field_key ];
                $success  = ( $relation === 'AND' ) ? 0 : false;

                foreach ( $post->fields['tags'] as $tag ) {
                    foreach ( $tag['group'] as $group ) {
                        if ( $group['group_key'] === $field_key ) {
                            // MinMax
                            if ( is_object( $filter ) ) {
                                if (
                                    (
                                        $filter->max &&
                                        $filter->max <= absint( $group['slug'] )
                                    ) ||
                                    (
                                        $filter->min &&
                                        $filter->min >= absint( $group['slug'] )
                                    )
                                ) {
                                    $success = true;
                                    continue 2;
                                }
                            }
                            // AND/OR
                            elseif ( is_array( $filter ) ) {
                                if ( $relation === 'AND' ) {
                                    if ( in_array( $group['slug'], $filter, true ) ) {
                                        $success++;
                                    }
                                }
                                else {
                                    if ( in_array( $group['slug'], $filter, true ) ) {
                                        $success = true;
                                        continue 2;
                                    }
                                }
                            }
                            // Single selection
                            elseif ( is_string( $filter ) ) {
                                if ( $filter === $group['slug'] ) {
                                    $success = true;
                                    continue 2;
                                }
                            }
                        }
                    }
                }

                if (
                    // Success was set to false
                    ! $success ||
                    (
                        // Not all AND relations were met
                        is_int( $success ) &&
                        $success !== count( $filter )
                    )
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Bind translated strings.
     */
    public function S() {
        $s = [
            'aktiviteettiryhma' => __( 'Task group', 'pof' ),
        ];
        return $s;
    }
}
