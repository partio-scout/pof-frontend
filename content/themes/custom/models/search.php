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

    /**
     * Modify page title according to backend translation
     */
    public function ModifyTitle() {

        /**
         * Change the title
         *
         * @param  string $title Existing title.
         * @return string        Modified $title.
         */
        add_filter( 'wp_title', function( string $title ) : string {
            $title = \DustPress\ApiTranslation::get_translation( 'haku.advanced_search' ) ?? $title;
            return $title;
        }, 30);
    }

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

        // Get remote data
        $haku_json    = get_field( 'haku-json', 'option' );
        $search_terms = \POF\Api::get( $haku_json, true );
        $age_groups   = get_age_groups( true ); // Get age groups filtered to current language

        // Create pseudo filtering field for api_type
        $search_terms['api_type'] = [
            'type'   => 'radiobutton',
            'fields' => [
                'task',
                'taskgroup',
                'pof_tip',
            ],
        ];

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
        $search_term = get_query_var( 's', '' );
        $per_page    = absint( get_option( 'posts_per_page' ) ?: 10 );
        $page        = absint( get_query_var( 'paged', 1 ) ?: 1 );
        $post_guids  = get_query_var( 'post_guids', null );
        $post_guids  = ! empty( $post_guids ) ? explode( ',', $post_guids ) : null;

        if ( ! empty( $post_guids ) ) {
            $post_relation = get_query_var( 'post_relation', 'OR' );
            $filters       = [
                'post_guids'    => $post_guids,
                'post_relation' => $post_relation,
            ];
        }
        else {
            $filters = [];
        }

        // Remove - from search_term. Else the word after - will be excluded from query.
        $search_term = str_replace( '-', ' ', $search_term );

        $params = (object) [
            'filters'     => $filters,
            'per_page'    => $per_page,
            'page'        => $page,
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
                'post_type'      => 'any',
                'post_status'    => 'publish',
                'posts_per_page' => -1, //phpcs:ignore
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => 'api_type',
                        'compare' => 'IN',
                        'value'   => [
                            'task',
                            'taskgroup',
                            'pof_tip',
                        ],
                    ],
                ],
                // Manually add language query
                'tax_query' => [
                    [
                        'taxonomy'         => 'language',
                        'field'            => 'slug',
                        'terms'            => get_short_locale(),
                        'include_children' => false,
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
                    if ( ! empty( $match->customfield_detail && is_array( unserialize( $match->customfield_detail ) ) ) ) {
                        // Check fields for matches but ignore certain non human readable fields
                        $fields    = array_keys( unserialize( $match->customfield_detail ) ?: [] );
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
            if ( ! empty( $params->filters ) ) {
                $posts = $this->filter_posts( $posts, $params->filters );
            }

            $posts = $this->remove_duplicate_posts( $posts );

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
     * Remove duplicate posts.
     * If duplicated posts found, keep the post which post type is 'pof_tip' only.
     *
     * @param array $posts Posts array.
     * @return void
     */
    protected function remove_duplicate_posts( $posts ) {
        foreach ( $posts as $post ) {
            // Current post data.
            $current_id    = $post->ID;
            $current_title = $post->post_title;

            foreach ( $posts as $key => $post ) {
                // Do not care current post.
                if ( $current_id !== $post->ID ) {
                    // If post found with same title and it is not pof_tip, delete it.
                    if ( $post->post_title === $current_title && $post->post_type !== 'pof_tip' ) {
                        unset( $posts[ $key ] );
                    }
                }
            }
        }

        return $posts;
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

        // Do different pagination handling if we are doing a loadmore call
        if ( DOING_AJAX ) {
            $offset = $params->page > 1 ? $params->page * $params->per_page : 0;
            $posts  = array_slice( $posts, $offset, $params->per_page );
        }
        else {
            $posts = array_slice( $posts, 0, $params->page * $params->per_page );
        }

        header( 'x-pagination-time: ' . round( microtime( true ) - $pagination_start, 4 ) );

        return $posts;
    }

    /**
     * Get single acf post by post id
     *
     * @param  mixed $post_id Post id.
     * @return \stdClass
     */
    protected function get_single_acf_post( $post_id ) {
        $cache_key = 'acfpost/' . $post_id;
        $post      = wp_cache_get( $cache_key );
        if ( empty( $post ) ) {
            $post_obj = get_post( $post_id );

            // Construct the actual used post object
            $post = (object) [
                'post_title'   => $post_obj->post_title,
                'post_type'    => $post_obj->post_type,
                'permalink'    => get_permalink( $post_id ),
                'post_excerpt' => ! empty( $post_obj->post_excerpt ) ? $post_obj->post_excerpt : $post_obj->post_content,
                'fields'       => [
                    'api_guid'       => get_field( 'api_guid', $post_id ),
                    'api_type'       => get_field( 'api_type', $post_id ),
                    'api_path'       => get_field( 'api_path', $post_id ),
                    'api_images'     => get_field( 'api_images', $post_id ),
                    'tags'           => get_field( 'tags', $post_id ),
                    'pof_tip_parent' => get_field( 'pof_tip_parent', $post_id ),
                ],
            ];

            wp_cache_set( $cache_key, $post, null, HOUR_IN_SECONDS );

            // Save id for guid data retrieval
            $guid_cache_key = 'get_id_by_guid/' . $post->fields['api_guid'] . '/' . get_locale();
            wp_cache_set( $guid_cache_key, $post_id, null, HOUR_IN_SECONDS );
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

        // Get program tree
        $tree = get_flat_program_tree();
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
                $parent_post = $this->get_single_acf_post( $post->fields['pof_tip_parent'] );

                // Overwrite tip link and title with parents
                $post->permalink          = $parent_post->permalink . '#' . $post->post_title;
                $post->post_title         = $parent_post->post_title;
                $post->fields             = $parent_post->fields;
                $post->fields['api_type'] = 'pof_tip';
            }

            // Map item parents
            $api_path = json_decode_pof( $post->fields['api_path'] ?? '[]' ) ?? [];
            if ( $post->post_type === 'pof_tip' ) {
                // If this is a tip then add the task itself to the path as well
                $api_path[] = [
                    'guid'      => $post->fields['api_guid'],
                    'languages' => [
                        [
                            'lang'  => get_short_locale(),
                            'title' => $parent_post->post_title,
                        ],
                    ],
                ];
            }
            $post->parents = array_reduce( $api_path, [ $this, 'map_api_parents' ], [] );

            // Decode images
            map_api_images( $post->fields['api_images'] );
            // Get the main logo
            if (
                is_array( $post->fields['api_images'] ) &&
                ! empty( $post->fields['api_images'] ) &&
                array_key_exists( 'logo', $post->fields['api_images'][0] )
            ) {
                $post->image = $post->fields['api_images'][0]['logo'];
            }

            // Get actual item guid
            $guid = $post->fields['api_guid'];
            if ( array_key_exists( $guid, $tree ) ) {
                // Get post term data
                $post->term = $this->get_post_term( $guid, $tree );
            }
        }
        header( 'x-post_data-time: ' . round( microtime( true ) - $post_data_start, 4 ) );

        return $result->posts;
    }

    /**
     * Set api item parents
     * Should be used via array_reduce
     *
     * @param  array           $parents Complete array.
     * @param  array|\stdClass $parent  Parent object.
     * @return array           Modified $parents.
     */
    public function map_api_parents( array $parents, $parent ) {
        $parent = (object) $parent;

        // Remove programs from parents
        if ( ! $parent->type !== 'program' ) {

            // Add logo data to agegroup parent
            if ( $parent->type === 'agegroup' ) {
                $parent->logo = $this->get_images_by_guid( $parent->guid );
            }

            $parents[] = $parent;
        }

        return $parents;
    }

    /**
     * Get api images by guid
     *
     * @param  string $guid Api guid of post to get images of.
     * @return mixed
     */
    public function get_images_by_guid( string $guid ) {
        $id     = $this->get_id_by_guid( $guid );
        $result = null;
        if ( ! empty( $id ) ) {
            $post   = $this->get_single_acf_post( $id );
            $result = $post->fields['api_images'];
            map_api_images( $result );
        }

        return $result;
    }

    /**
     * Get post id by guid
     *
     * @param  string $guid Post api guid.
     * @return mixed
     */
    public function get_id_by_guid( string $guid ) {
        $cache_key = 'get_id_by_guid/' . $guid . '/' . get_locale();
        $result    = wp_cache_get( $cache_key );
        if ( $result === false ) {
            $args = [
                'posts_per_page' => 1,
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => 'api_guid',
                        'value' => $guid,
                    ],
                ],
            ];

            $query = ( new WP_Query( $args ) )->posts[0] ?? null;

            wp_cache_set( $cache_key, $result, null, HOUR_IN_SECONDS );
        }

        return $result;
    }

    /**
     * Get api item term
     *
     * @param  string $guid Item guid.
     * @param  array  $tree Api item tree.
     * @return mixed        Term or null on no match.
     */
    protected function get_post_term( string $guid, array $tree ) {
        $item            = $tree[ $guid ] ?? [];
        $params_to_check = [
            'task_term',
            'taskgroup_term',
            'subtaskgroup_term',
            'subtask_term',
        ];
        foreach ( $params_to_check as $term ) {
            if ( ! empty( $item[ $term ] ) ) {
                return $item[ $term ];
            }
        }
        if ( ! empty( $item['parent'] ) ) {
            return $this->get_post_term( $item['parent'], $tree );
        }

        return null;
    }

    /**
     * Filter the posts
     *
     * @param  array $posts   Posts to filter.
     * @param  array $filters Args to filter by.
     * @return array          Filtered $posts.
     */
    protected function filter_posts( array $posts, array $filters ) {
        // Filter inside php as this is way faster than the query generated by WP_Query
        if ( ! empty( $filters ) ) {
            $filter_start = microtime( true );

            $model = $this;
            $posts = array_filter( $posts, function( $post ) use ( $filters, $model ) {

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

        foreach ( $filters['post_guids'] as $filter_guids ) {
            $is_match = in_array( $filter_guids, $post_guids, true );

            if (
                $relation === 'AND' ?
                    ! $is_match : // AND mode non match found
                    $is_match // OR mode match found
            ) {
                return $is_match;
            }
        }

        // If we got here then succeed in AND mode and fail in OR mode
        return ( $relation === 'AND' );
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

            if ( $field_key === 'api_type' && $post->fields['api_type'] === $filter ) {
                // Do different filtering for pseudo field
                $success = true;
            }
            else {
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
}
