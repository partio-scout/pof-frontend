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
            substr( $menu->slug, 0, strlen( static::$slug ) ) === static::$slug &&
            ! is_admin()
        ) {
            $cache_key = 'custom_menu/' . $menu->slug;
            $new_items = wp_cache_get( $cache_key );
            if ( empty( $new_items ) ) {
                // Double the memory limit if we are getting all menu items without cache
                ini_set( 'memory_limit', '512M' );

                // Add the children
                $new_items = static::add_children( $items );

                wp_cache_set( $cache_key, $new_items, null, HOUR_IN_SECONDS );
            }

            // Replace item list with new items
            if ( ! empty( $new_items ) ) {
                $items = $new_items;
            }

            // Mark current active tree
            $parent_menu_id = $items[ $items[ get_the_ID() ]->menu_item_parent ]->ID ?? null;
            if ( $parent_menu_id ) {
                $items = static::mark_active( $items, $parent_menu_id );
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
        $menu_items = [];
        foreach ( $items as &$post ) {
            $menu_items[ $post->ID ] = $post;
        }

        // Add menu item children automatically
        foreach ( $menu_items as &$post ) {
            if ( $post->object === 'page' ) {
                $query = ( new \WP_Query([
                    'post_parent'    => $post->object_id,
                    'post_type'      => $post->object,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1, //phpcs:ignore
                ]) );
                if ( ! empty( $query->posts ) ) {
                    foreach ( $query->posts as &$child_post ) {
                        $child_post = static::custom_nav_menu_item( $child_post, $post->ID );

                        // Add the child posts immediately so they get checked for children as well
                        $menu_items[ $child_post->ID ] = $child_post;
                    }
                }
            }
        }

        // Add submenu classes
        foreach ( $menu_items as &$post ) {
            $id = $post->menu_item_parent ?: null;
            if (
                array_key_exists( $id, $menu_items ) &&
                ! in_array( 'menu-item-has-children', $menu_items[ $id ]->classes, true )
            ) {
                $menu_items[ $id ]->classes[] = 'menu-item-has-children';
            }
        }

        return $menu_items;
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
     * Helper function to change posts into menu items
     *
     * @param  \WP_Post $post   Post item.
     * @param  int      $parent Post item parent menu id.
     * @return \WP_Post         Modified $post.
     */
    private static function custom_nav_menu_item( \WP_Post $post, int $parent = 0 ) {
        $post->object_id        = $post->ID;
        $post->object           = $post->post_type;
        $post->type             = 'post_type';
        $post->post_type        = 'nav_menu_item';
        $post->menu_item_parent = $parent;

        $post = wp_setup_nav_menu_item( $post );

        return $post;
    }
}

// Initialize the class
Menu::init();
