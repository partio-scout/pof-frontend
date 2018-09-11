<?php
/**
 * Theme custom menu file
 */

namespace POF;

/**
 * Menu Class
 */
class Menu {

    /**
     * Custom menu slug
     *
     * @var string
     */
    public static $slug = 'main-menu';

    /**
     * Initialize the class and add necessary hooks
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_nav_menu' ] );
        add_filter( 'wp_get_nav_menu_items', [ __CLASS__, 'wp_get_nav_menu_items' ], 2, 20 );
    }

    /**
     * Register the custom menu
     */
    public static function register_nav_menu() {
        register_nav_menu( static::$slug, __( 'Main Menu' ) );
    }

    /**
     * Modify nav menu items as necessary to add post children automatically.
     *
     * @param  array    $items Menu items.
     * @param  \WP_Term $menu  Menu that is being modified.
     * @return array           Modified $items.
     */
    public static function wp_get_nav_menu_items( array $items, \WP_Term $menu ) {

        // only add items to the main menu and its translations
        if (
            ! is_admin() &&
            substr( $menu->slug, 0, strlen( static::$slug ) ) === static::$slug
        ) {
            $cache_key = 'custom_menu/' . $menu->slug;
            $new_items = wp_cache_get( $cache_key );
            if ( empty( $new_items ) ) {

                // Add the children
                $items = static::add_children( $items );

                wp_cache_set( $cache_key, $items, null, HOUR_IN_SECONDS );
            }
            else {
                $items = $new_items;
            }

            // Mark current active tree
            $id = get_the_ID();
            if (
                array_key_exists( $id, $items ) &&
                property_exists( $items[ $id ], 'menu_item_parent' ) &&
                array_key_exists( $items[ $id ]->menu_item_parent, $items ) &&
                property_exists( $items[ $items[ $id ]->menu_item_parent ], 'ID' )
            ) {
                $parent_menu_id = $items[ $items[ $id ]->menu_item_parent ]->ID;
                $items          = static::mark_active( $items, $parent_menu_id );
            }
        }

        return $items;
    }

    /**
     * Recursively add post children to posts
     *
     * @param  array $items Items to seek children for.
     * @return array        Modified $items.
     */
    private static function add_children( $items ) {

        // Modify array so the id is the array key
        $items = array_combine( wp_list_pluck( $items, 'ID' ), $items );

        // Add menu item children automatically
        foreach ( $items as &$post ) {
            if ( $post->object === 'page' ) {
                $query = ( new \WP_Query([
                    'post_parent'    => $post->object_id,
                    'post_type'      => 'page',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1, //phpcs:ignore
                    'fields'         => 'ids',
                ]) );
                if ( ! empty( $query->posts ) ) {
                    foreach ( $query->posts as &$child_post ) {
                        $child_post = static::custom_nav_menu_item( $child_post, $post->ID );

                        // Add the child posts immediately so they get checked for children as well
                        $items[ $child_post->ID ] = $child_post;
                    }
                }
            }
        }

        // Add submenu classes
        foreach ( $items as &$post ) {
            $id = $post->menu_item_parent ?: null;
            if (
                array_key_exists( $id, $items ) &&
                ! in_array( 'menu-item-has-children', $items[ $id ]->classes, true )
            ) {
                $items[ $id ]->classes[] = 'menu-item-has-children';
            }
        }

        return $items;
    }

    /**
     * Mark current tree items as active
     *
     * @param array $items Items to search.
     * @param int   $id    Id to search for.
     */
    private static function mark_active( array $items, int $id ) {
        if ( array_key_exists( $id, $items ) ) {
            $item            = $items[ $id ];
            $item->classes[] = 'opened';
            if ( ! empty( $item->menu_item_parent ) ) {
                $items = static::mark_active( $items, $item->menu_item_parent );
            }
        }

        return $items;
    }

    /**
     * Helper function to change post id's into menu items
     *
     * @param  int $post_id Post id.
     * @param  int $parent  Post item parent menu id.
     * @return stdClass     Modified $post.
     */
    private static function custom_nav_menu_item( int $post_id, int $parent = 0 ) {
        $post = (object) [
            'ID'               => $post_id,
            'object_id'        => $post_id,
            'object'           => 'page',
            'type'             => 'post_type',
            'post_type'        => 'nav_menu_item',
            'menu_item_parent' => $parent,
            'menu_order'       => get_post_field( 'menu_order', $post_id ),
            'url'              => get_permalink( $post_id ),
            'title'            => get_the_title( $post_id ),
            'classes'          => [],
        ];

        return $post;
    }
}

// Initialize the class
Menu::init();
