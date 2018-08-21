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
     * Get short locale
     *
     * @return string First part of locale.
     */
    public static function get_locale() {
        $locale = explode( '_', get_locale() )[0];
        return $locale;
    }

    /**
     * Get search terms and translations from api
     */
    public static function ApiSearchTerms() {
        // Get local data
        $haku_json    = get_field( 'haku-json', 'option' );
        $kaannos_json = get_field( 'kaannos-json', 'option' );
        $locale       = static::get_locale();

        // Get remote data
        $search_terms = \POF\Api::get( $haku_json, true );
        $translations = \POF\Api::get( $kaannos_json, true );
        $age_groups   = get_age_groups();

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

            if ( ! empty( $field_data['fields'] ) ) {
                $field_data['fields'] = array_map(function( $item ) {
                    // If there were no translations just transform this into a dust friendly format
                    if ( ! is_object( $item ) ) {
                        $item = (object) [
                            'key'   => $item,
                            'value' => $item,
                        ];
                    }

                    return $item;
                }, $field_data['fields']);
            }
        }

        $result = (object) [
            'search_terms' => $search_terms,
            'age_groups'   => $age_groups,
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

        $results            = $this->get_results( $params );
        $results->params    = $params;
        $results->lang_base = pll_current_language();

        return $results;
    }

    /**
     * Get post list
     *
     * @param  stdClass $params Search params.
     * @return array            An array of posts or post ids.
     */
    protected function get_post_list( stdClass $params ) {
        $posts_start = microtime( true );
        $cache_key   = 'search/' . esc_sql( $params->search_term ) . '/' . get_locale();
        $result      = wp_cache_get( $cache_key );
        if ( empty( $result ) ) {
            $args = [
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

            // Allow searching without a search term
            if ( ! empty( $params->search_term ) ) {
                $args['s'] = $params->search_term;
            }
            else {
                $args['fields'] = 'ids';
            }

            // Get all posts with query
            $query   = new WP_Query( $args );
            $matches = [];

            // Only use relevanssi if there is a search term
            if ( ! empty( $params->search_term ) ) {

                // Add filter to get which part was matched in post
                add_filter( 'relevanssi_match', function( $match ) use ( &$matches ) {
                    if ( ! empty( $match->customfield_detail ) ) {
                        // Check fields for matches but ignore certain non human readable fields
                        $fields    = array_keys( unserialize( $match->customfield_detail ) );
                        $blacklist = [
                            'api_path',
                        ];

                        foreach ( $fields as $field ) {
                            if ( ! in_array( $field, $blacklist, true ) ) {
                                $matches[ $match->doc ] = $field;
                                break;
                            }
                        }
                    }
                    return $match;
                });
                $posts = relevanssi_do_query( $query );
            }
            else {
                $posts = $query->posts;
            }

            $result = (object) [
                'posts'   => $posts,
                'matches' => $matches,
            ];

            wp_cache_set( $cache_key, $result, null, HOUR_IN_SECONDS );
        }
        header( 'x-posts-time: ' . round( microtime( true ) - $posts_start, 4 ) );

        return $result;
    }

    /**
     * Get results from params
     *
     * @param  stdClass $params Params to use for searching.
     * @return stdClass         Object containing metadata for request and post list.
     */
    protected function get_results( stdClass $params ) {

        // Get initial post list
        $result = $this->get_post_list( $params );

        // Pagination metadata
        $count         = 0;
        $max_num_pages = 0;
        $page          = $params->page;
        $posts         = [];

        if ( ! empty( $result->posts ) ) {
            // Get extra post data for each post
            $posts = $this->get_post_data( $result, $params );

            // Filter the posts
            if ( ! empty( $params->ajax_args ) ) {
                $posts = $this->filter_posts( $posts, $params->ajax_args );
            }

            // Do pagination in php since we do the filtering in php as well
            $count         = count( $posts );
            $max_num_pages = ceil( $count / $params->per_page );
            $posts         = $this->do_pagination( $posts, $params );
        }

        // Construct object containing metadata for request and post list.
        $data = (object) [
            'posts'         => $posts,
            'count'         => $count,
            'max_num_pages' => $max_num_pages,
            'page'          => $page,
        ];

        return $data;
    }

    /**
     * Do post pagination in php
     *
     * @param  array    $posts  Post list.
     * @param  stdClass $params Params to do pagination by.
     * @return array            Sliced post list.
     */
    protected function do_pagination( array $posts, stdClass $params ) {
        $pagination_start = microtime( true );

        $offset = $params->page > 1 ? $params->page * $params->per_page : 0;
        $posts  = array_slice( $posts, $offset, $params->per_page );

        header( 'x-pagination-time: ' . round( microtime( true ) - $pagination_start, 4 ) );

        return $posts;
    }

    /**
     * Get single acf post by post id
     *
     * @param  mixed $post_id Post id.
     * @return \WP_Post
     */
    protected function get_single_acf_post( $post_id ) {
        $cache_key = 'acfpost/' . $post_id;
        $post      = wp_cache_get( $cache_key );
        if ( empty( $post ) ) {
            $post = \DustPress\Query::get_acf_post( $post_id );

            wp_cache_set( $cache_key, $post, null, HOUR_IN_SECONDS );
        }

        return $post;
    }

    /**
     * Get each post data
     *
     * @param  stdClass $result Get posts result.
     * @param  stdClass $params Search params.
     * @return array            Modified $result->posts.
     */
    protected function get_post_data( stdClass $result, stdClass $params ) {

        $post_data_start = microtime( true );
        foreach ( $result->posts as &$post ) {
            if ( is_object( $post ) ) {
                $id = $post->ID;

                // Don't set the normal highlight if match was in custom field
                if ( ! array_key_exists( $id, $result->matches ) ) {
                    $excerpt = $post->post_excerpt;
                }
            }
            else {
                $id = $post;
            }
            $post = $this->get_single_acf_post( $id );

            if ( ! empty( $excerpt ) ) {
                // Replace $post but keep the excerpt
                $post->post_excerpt = $excerpt;
            }
            elseif ( array_key_exists( $id, $result->matches ) ) {
                // Generate custom highlighted excerpt from custom field
                $post->post_excerpt = force_balance_tags( // Fix any broken tags
                    html_entity_decode( // Decode previously encoded html tags
                        wp_trim_words( // Trim the string into an acceptable length
                            htmlentities( // Encode tags so wp_trim_words doesn't remove them
                                relevanssi_highlight_terms( // Highlight matches
                                    $post->fields[ $result->matches[ $id ] ],
                                    $params->search_term,
                                    true
                                )
                            ),
                            30 // Number of max words
                        )
                    )
                );
            }

            if ( $post->post_type === 'pof_tip' ) {

                // Get tip parent link & title
                $tip_cache_key = 'tipdata/' . $id;
                $extra_data    = wp_cache_get( $tip_cache_key );
                if ( empty( $extra_data ) ) {
                    // Get post parent
                    $parent_id   = get_post_meta( $id, 'pof_tip_parent', true );
                    $parent_post = $this->get_single_acf_post( $parent_id );

                    // Store relevant data
                    $extra_data = (object) [
                        'permalink'  => $parent_post->permalink . '#' . $post->post_name,
                        'post_title' => __( 'Tip in task ', 'pof' ) . '<i>' . $parent_post->post_title . '</i>',
                    ];

                    wp_cache_set( $tip_cache_key, $extra_data, null, HOUR_IN_SECONDS );
                }

                // Overwrite tip link and title with parents
                $post->permalink  = $extra_data->permalink;
                $post->post_title = $extra_data->post_title;
            }
            else {

                // Get api images
                $post->parents = ! empty( $post->fields['api_path'] ) ? map_api_parents( json_decode_pof( $post->fields['api_path'] ) ?? [] ) : null;
                map_api_images( $post->fields['api_images'] );
                if ( is_array( $post->fields['api_images'] ) ) {
                    $post->image = $post->fields['api_images'][0]['logo'];
                }
            }

        }
        header( 'x-post_data-time: ' . round( microtime( true ) - $post_data_start, 4 ) );

        return $result->posts;
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

                // First check post relation
                if ( ! empty( $filters['post_guids'] ) ) {
                    $post_relation_result = $model->post_relation_filter( $post, $filters );
                    if ( ! $post_relation_result ) {
                        return false;
                    }
                }

                // Now check task filters
                if ( ! empty( $filters['global']['enabled'] ) ) {
                    $result = $model->task_filter( $post, $filters['global'] );
                    if ( ! $result ) {
                        return false;
                    }
                }

                return true;
            });
            header( 'x-filter-time: ' . round( microtime( true ) - $filter_start, 4 ) );
        }

        return $posts;
    }

    /**
     * Check for api guid matches
     *
     * @param  mixed $post    Post to check.
     * @param  mixed $filters Filters to check.
     * @return bool           True or false depending on if post filter was succesful.
     */
    protected function post_relation_filter( $post, $filters ) {
        $relation = $filters['post_relation'] ?? 'AND';

        // Collect all post guids into a single array for comparison
        $post_guids = array_merge(
            wp_list_pluck( $post->parents, 'guid' ),
            [
                $post->fields['api_guid'],
            ]
        );
        $collisions = array_intersect( $post_guids, $filters['post_guids'] );

        if ( $relation === 'AND' ) {
            // If we are in AND mode then all filter guids should be matched
            $success = ( count( $collisions ) === count( $filters['post_guids'] ) );
        }
        else {
            // If we are in OR mode then succeed with any matches
            $success = ! empty( $collisions );
        }

        return $success;
    }

    /**
     * Check a set of filters against a post to filter it
     *
     * @param  mixed $post    Post to check.
     * @param  mixed $filters Filters to check.
     * @return bool           True or false depending on if post filter was succesful.
     */
    protected function task_filter( $post, $filters ) {

        foreach ( $filters['enabled'] as $field_key ) {
            // Get relation, if no relation is found that means it is in false state = "AND"
            $relation = $filters['and_or'][ $field_key ] ?? 'AND';
            $filter   = $filters['filters'][ $field_key ];
            $success  = ( $relation === 'AND' ) ? 0 : false;

            if ( ! empty( $post->fields['tags'] ) ) {
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
