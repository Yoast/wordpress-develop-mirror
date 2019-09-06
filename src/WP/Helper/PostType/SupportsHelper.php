<?php

namespace WP\Helper\PostType;


class SupportsHelper{

    /**
     * Registers support of certain features for a post type.
     */
    public static function add( $post_type, $feature, ...$args  )
    {
        global $_wp_post_type_features;
        $features = (array) $feature;
        foreach ( $features as $feature ) {
            if ( $args ) {
                $_wp_post_type_features[ $post_type ][ $feature ] = $args;
            } else {
                $_wp_post_type_features[ $post_type ][ $feature ] = true;
            }
        }
    }

    /**
     * Remove support for a feature
     */
    public static function remove( $post_type, $feature ) {
        global $_wp_post_type_features;
	    unset( $_wp_post_type_features[ $post_type ][ $feature ] );
    }

    
    /**
     * Get all supports for a specific post-type
     */
    public static function get( $post_type ) {
        global $_wp_post_type_features;

        if ( isset( $_wp_post_type_features[ $post_type ] ) ) {
            return $_wp_post_type_features[ $post_type ];
        }

        return array();
    }

    /**
     * Check to see if a post-type has support
     */
    public static function hasSupport( $post_type, $feature ) {
        global $_wp_post_type_features;

	    return ( isset( $_wp_post_type_features[ $post_type ][ $feature ] ) );
    }
}