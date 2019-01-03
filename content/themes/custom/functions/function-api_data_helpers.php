<?php
// Replacement for native json_decode function to get valid unicode chars
function json_decode_pof($data) {
    $convert = str_replace(
        array('u00e4', 'u00e5', 'u00f6', 'u00c4', 'u00c5', 'u00d6'),
        array('\u00e4', '\u00e5', '\u00f6', '\u00c4', '\u00c5', '\u00d6'),
        $data
    );
    return json_decode($convert);
}
// binds images into a more dust-friendly array
function map_api_images( &$images ) {
    if ( is_array( $images ) ) {
        foreach ( $images as &$img ) {
            if ( is_array( $img ) && array_key_exists( 'key', $img ) ) {
                $img = [ $img['key'] => json_decode_pof( $img['object'] ) ];
            }
        }
    }
}

// binds images into a more dust-friendly array
function map_api_attachments( &$attachments ) {
    $attach_arr = [];
    if ( is_array( $attachments ) ) {
        foreach ($attachments as $attachment) {
            $data = json_decode_pof( $attachment['object'] );
            if ($attachment['type'] == 'files') {
                $data->icon = get_template_directory_uri() . '/assets/img/file_'.substr($data->url, -3).'.png';
            }
            $attach_arr[$attachment['type']][] = $data;
        }
    }
    $attachments = $attach_arr;
}

// binds tags into a dust-friendly array
function map_api_tags( &$repeater ) {
    $bound = [];
    if ( is_array( $repeater ) ) {
        foreach ($repeater as $tags) {
            foreach ($tags as $group) {
                if ( is_array( $group ) ) {
                    $bound[$group[0]['group_key']]['key']   = isset( $group[0]['group_key'] )   ? $group[0]['group_key'] : null;
                    $bound[$group[0]['group_key']]['icon']  = isset( $group[0]['group_icon'] )  ? $group[0]['group_icon'] : null;
                    foreach ($group as $tag) {
                        $bound[$tag['group_key']]['values'][] = $tag;
                    }
                }
            }
        }
        $repeater = $bound;
    }
}

// loads all children of a page in a tree format
function get_child_page_tree( $post_id, $helper, $grandchildren = true  ) {

    $args = [
        'posts_per_page'    => -1,
        'post_type'         => 'page',
        'post_parent'       => $post_id,
        'post_status'       => 'publish',
        'orderby'           => 'level',
        'order'             => 'ASC'
    ];
    $children = \DustPress\Query::get_acf_posts( $args );

    if ( is_array( $children ) ) {
        $order = array();
        foreach ( $children as $key => &$c ) {

            // format images and tags
            map_api_images( $c->fields['api_images'] );

            // format tags for tasks
            if ( $c->fields['api_type'] === 'task' ) {

                // remove level data if not level set, make sort array otherwise
                if ((int)$c->fields['level'] <= 0) {
                    unset ($c->fields['level']);
                } else {
                    $order[$key] = (int)$c->fields['level'] - 1;
                }
                map_api_tags( $c->fields['tags'] );
            }

            if ( $grandchildren ) {
                // get grandchildren
                $gc = get_child_page_tree( $c->ID, $helper );

                if ( is_array( $gc ) ) {
                    $c->children = $gc;
                }
            }
        }

        // sort level -tasks by level
        if (!empty($order)) {
            array_multisort( $order, SORT_ASC, $children );
        }

        // return tree if something found
        return $children;
    }
}

// get correct taskgroup term
function get_taskgroup_term( $post ) {
    $terms = array(
        'subtaskgroup_term' => json_decode_pof($post->fields['subtaskgroup_term']),
        'taskgroup_term'    => json_decode_pof($post->fields['taskgroup_term']),
        'subtask_term'      => json_decode_pof($post->fields['subtask_term'])
    );

    if ($terms['subtask_term']->name != 'null') {
        $term = $terms['subtask_term']->plural;
    } else if ($terms['subtaskgroup_term']->name != 'null') {
        $term = $terms['subtaskgroup_term']->plural;
    } else if ($terms['taskgroup_term']->name != 'null') {
        $term = $terms['taskgroup_term']->plural;
    } else {
        $term = null;
    }

    return $term;
}

// shortcode handler for api internal links
function pofapilink_shortcode( $atts  ) {
    $guid = $atts['guid'];
    $link = '';
    if ($guid) {
        $lang = isset($atts['lang']) ? $atts['lang'] : 'FI';
        $args = [
            'posts_per_page'    => -1,
            'post_type'         => 'page',
            'post_status'       => 'publish',
            'meta_query'        => array(
                                    array('key' => 'api_guid', 'value' => $guid),
                                    array('key' => 'api_lang', 'value' => $lang)
                                )
        ];
        $pages  = \DustPress\Query::get_acf_posts( $args );

        if (count($pages) === 1) {
            if (isset($atts['teksti'])) {
                $link = '<a href="'.$pages[0]->permalink.'">'.$atts['teksti'].'</a>';
            }

            if (isset($atts['kuva'])) {
                $link = '<a href="'.$pages[0]->permalink.'"><img src="'.$atts['kuva'].'"></a>';
            }
        }
    }

    return $link;
}
add_shortcode( 'pofapilink', 'pofapilink_shortcode' );

// Get hero args
function get_hero_args() {
    $args = [
        'post_type'      => 'partio-ylakuvat',
        'posts_per_page' => 1,
        'orderby'        => 'rand',
        'post_status'    => 'publish'
    ];

    return $args;
}

function sort_by_mandatory( $posts ) {
    // bind tags/taskgroups into a dust-friendly array
    // and split the tasks
    $tasks = [];
    foreach ( $posts as &$task ) {
        if ((int)$task->fields['level'] <= 0) {
            unset($task->fields['level']);
        }
        map_api_tags( $task->fields['tags'] );
        // split between mandatory and voluntary
        if ( isset( $task->fields['tags']['pakollisuus'] ) ) {
            foreach ( $task->fields['tags']['pakollisuus']['values'] as $tag ) {
                if ( $tag['slug'] == 'mandatory' ) {
                    $tasks['mandatory'][] = $task;
                } else {
                    $tasks['voluntary'][] = $task;
                }
            }
        } else { // all tasks are voluntary
            $tasks['voluntary'][] = $task;
        }
    }

    return $tasks;
}

function get_api_media( $id, $rendered = true ) {
    // Make sure the id is a number
    $id = absint( $id );

    // Attempt to get the image from cache
    $cache_key = 'apimedia/' . $id;
    $media_data = wp_cache_get( $cache_key );
    if ( empty( $media_data ) ) {

        // Get the image from api
        $media_url = get_field( 'media-url', 'option' );
        if ( ! empty( $media_url ) ) {
            $media_data = \POF_Importer::init()->fetch_data( $media_url . $id );

            // Save image to cache
            wp_cache_set( $cache_key, $media_data, null, HOUR_IN_SECONDS );
        }
    }

    // Remove width&height params from prerendered image tag
    if ( ! empty( $media_data ) ) {
        $regex = '/width="[0-9]+" height="[0-9]+"/';
        $media_data['description']['rendered'] = preg_replace( $regex, '', $media_data['description']['rendered'] );
    }

    if ( $rendered ) {
        // Get the prerendered image tag
        $rendered = $media_data['description']['rendered'];

        return $rendered;
    }

    return $media_data;
}

/**
 * Sort taskgroups and tasks in age groups result
 *
 * @param mixed $data Data to sort.
 */
function sort_results( &$data ) {
    if ( is_array( $data ) ) {
        if ( array_key_exists( 'taskgroups', $data ) && ! empty( $data['taskgroups'] ) ) {
            usort( $data['taskgroups'], 'sort_by_order' );
            foreach ( $data['taskgroups'] as &$sub_array ) {
                sort_results( $sub_array );
            }
        }
        elseif ( array_key_exists( 'tasks', $data ) && ! empty( $data['tasks'] ) ) {
            // Use elseif here because if we have taskgroups we don't show tasks
            usort( $data['tasks'], 'sort_by_order' );
        }
    }
}

/**
 * Sort array by item order parameter
 *
 * @param  array $a Item to compare.
 * @param  array $b Item to compare.
 * @return int
 */
function sort_by_order( $a, $b ) {
    return $a['order'] - $b['order'];
}

/**
 * Get age groups from the api
 *
 * @return array
 */
function get_age_groups() {
    $ohjelma_json = get_field( 'ohjelma-json', 'option' );
    $program      = \POF\Api::get( $ohjelma_json, true );
    $age_groups   = $program['program'][0]['agegroups'];

    usort( $age_groups, 'sort_by_order' );
    sort_results( $age_groups );

    return $age_groups;
}

/**
 * Flatten api program tree into a single array
 *
 * @return array Flattened tree.
 */
function get_flat_program_tree() {
    // Retrieve the program tree from the api
    $ohjelma_json = get_field( 'ohjelma-json', 'option' );
    $program      = \POF\Api::get( $ohjelma_json, true );
    $tree         = $program['program'][0];

    $flattened = [];
    /**
     * Recursively add api item to flattened array
     *
     * @param array  $item      Item to add.
     * @param array  $flattened Array to gather items to.
     * @param string $parent    Parent guid.
     */
    function add_to_flattened( $item, &$flattened, $parent = null ) {
        $item['parent']  = $parent;
        $items_to_search = [
            'taskgroups',
            'tasks',
            'agegroups',
        ];
        foreach ( $items_to_search as $key ) {
            if ( array_key_exists( $key, $item ) ) {
                foreach ( $item[ $key ] as $new_item ) {
                    add_to_flattened( $new_item, $flattened, $item['guid'] );
                }

                // Collapse sub items to just their guid's
                $item[ $key ] = array_map(function( $item ) {
                    return $item['guid'];
                }, $item[ $key ]);
            }
        }
        $flattened[ $item['guid'] ] = $item;
    }
    add_to_flattened( $tree, $flattened );

    return $flattened;
}
