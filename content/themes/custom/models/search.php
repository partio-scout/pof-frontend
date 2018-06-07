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
     * Get search terms and translations from api
     */
    public static function ApiSearchTerms() {
        // Get local data
        $haku_json    = get_field( 'haku-json', 'option' );
        $kaannos_json = get_field( 'kaannos-json', 'option' );
        $ohjelma_json = get_field( 'ohjelma-json', 'option' );
        $locale       = get_locale();

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

        // Combine the search terms and translations
        foreach ( $search_terms as $field_name => &$field_data ) {

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
        if ( wp_doing_ajax() ) {
            $ajax_args = $this->get_args();
        }

        $per_page   = get_option( 'posts_per_page' );
        $page       = (int) get_query_var( 'paged' );
        $page       = $page ? $page : 1; // Force page value
        $displaying = $per_page * $page;
        $search_term = wp_doing_ajax() ? $ajax_args->search->s : get_query_var( 's' );
        // Do not execute if no search term - relevanssi doesn't like it.
        if ( empty( $search_term ) ) {
            return false;
        }
        else {
            // Remove - from search_term. Else the word after - will be excluded from query.
            $search_term = str_replace( '-', ' ', $search_term );

            // Args for search.
            $filter_query = [];

            // This technically works as is but must be changed into a raw sql query
            // as it generates a far too slow of a query (over 2 minutes with only 1 parameter).
            // So it is disabled for now.
            if ( wp_doing_ajax() ) {

                // First handle global filters
                $global_args    = $ajax_args->filter->global;
                $global_filters = $this->handle_filters( $global_args );
                $filter_query  += $global_filters;

                // Now handle each invidual age group filter
                foreach ( $ajax_args->filter->enabled_age_groups as $guid ) {
                    $agegroup_args    = $ajax_args->filter->agegroups->{ $guid };
                    $agegroup_filters = $this->handle_filters( $agegroup_args );
                    $filter_query[]   = [
                        'relation' => 'AND',
                        [
                            'key'     => 'api_path',
                            'compare' => 'LIKE',
                            'value'   => $guid,
                        ],
                        [
                            $agegroup_filters,
                        ],
                    ];
                }

                // Only add relation param if more than 1 filter
                if ( count( $filter_query ) > 1 ) {
                    $filter_query['relation'] = $ajax_args->filter->base_relation;
                }
            }

            $api_type_query = [
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
            ];

            if ( ! empty( $filter_query ) ) {
                $meta_query = [
                    'relation' => 'AND',
                    $api_type_query,
                    $filter_query,
                ];
            }
            else {
                $meta_query = $api_type_query;
            }

            $args = [
                's'           => $search_term,
                'post_type'   => [ 'page', 'pof_tip' ],
                'post_status' => 'publish',
                'meta_query'  => $meta_query,
            ];

            // Modify meta key query
            add_filter('posts_where', function( $where ) {
                $where = str_replace( "meta_key = 'tags_\$_group_\$_", "meta_key LIKE 'tags_%_group_%_", $where );
                return $where;
            });

            // Check if executed with ajax and set offset if true.
            if ( wp_doing_ajax() ) {
                $args['posts_per_page'] = $per_page;
                $args['offset']         = $ajax_args->load_more ? $displaying : 0;
            }
            // Else show posts without offset.
            else {
                $args['posts_per_page'] = $displaying;
            }

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
            $data->count         = $query->found_posts;
            $data->max_num_pages = $query->max_num_pages;
            $data->page          = $page;
            // Modify every post
            foreach ( $data->posts as &$post ) {
                // Get details
                $acf_post = \DustPress\Query::get_acf_post( $post->ID );
                $post->ingress = $post->post_excerpt;

                if( $post->post_type === 'pof_tip') {
                    $parent_id = get_post_meta( $post->ID, 'pof_tip_parent', true );
                    $parent_link = get_permalink( $parent_id );
                    $guid = get_post_meta( $post->ID, 'pof_tip_guid', true );
                    $post->url = $parent_link . '#' . $guid;
                    $post->search_type = $post->post_type;
                    $parent_title = get_the_title( $parent_id );
                    $post->post_title =  __( 'Tip in task ', 'pof' ) . '<i>' . $parent_title . '</i>';

                } else {
                    $post->search_type = $acf_post->fields['api_type'];
                    map_api_images( $acf_post->fields['api_images'] );
                    if ( is_array( $acf_post->fields['api_images'] ) ) {
                        $post->image = $acf_post->fields['api_images'][0]['logo'];
                    }
                    $post->parents = map_api_parents( json_decode_pof( $acf_post->fields['api_path'] ) );
                    $post->url = get_permalink( $post->ID );
                }

            } // End foreach().
            unset( $post );
            return $data;
        } // End if().
    }

    /**
     * Transform filter args into a part of a meta query
     *
     * @param  stdClass $filters Filters to handle.
     * @return array             Transformed filter args.
     */
    protected function handle_filters( $filters ) {
        $result = [];
        foreach ( $filters->enabled as $field_key ) {
            $field_value = $filters->filters->{ $field_key };
            if ( is_string( $field_value ) ) {
                // Select/Radio field
                $field_query = [
                    'relation' => 'AND',
                    [
                        'key'     => 'tags_$_group_$_group_key',
                        'compare' => '=',
                        'value'   => $field_key,
                    ],
                    [
                        'key'     => 'tags_$_group_$_slug',
                        'compare' => '=',
                        'value'   => $field_value,
                    ],
                ];
            }
            elseif ( is_array( $field_value ) ) {
                // Checkbox field
                $field_query = [];
                foreach ( $field_value as $value ) {
                    $field_query[] = [
                        'relation' => 'AND',
                        [
                            'key'     => 'tags_$_group_$_group_key',
                            'compare' => '=',
                            'value'   => $field_key,
                        ],
                        [
                            'key'     => 'tags_$_group_$_slug',
                            'compare' => '=',
                            'value'   => $value,
                        ],
                    ];
                }

                if ( count( $field_query ) > 1 ) {
                    $field_query['relation'] = $filters->and_or->{ $field_key };
                }
            }
            elseif ( is_object( $field_value ) ) {
                // Min max field
                $field_query = [
                    'relation' => 'AND',
                    [
                        'key'     => 'tags_$_group_$_group_key',
                        'compare' => '=',
                        'value'   => $field_key,
                    ],
                    [
                        'key'     => 'tags_$_group_$_slug',
                        'compare' => '>=',
                        'value'   => $field_value->min,
                    ],
                    [
                        'key'     => 'tags_$_group_$_slug',
                        'compare' => '<=',
                        'value'   => $field_value->max,
                    ],
                ];
            }

            $result[] = $field_query;
        }

        return $result;
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
