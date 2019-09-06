<?php

namespace WP\Helper\Post;

use WP\Helper\Meta\Metadata;

class MetaHelper{

    /**
     * Add post meta
     */
    public function add( $post_id, $meta_key, $meta_value, $unique = false )
    {
        // Make sure meta is added to the post, not a revision.
        $the_post = wp_is_post_revision( $post_id );
        if ( $the_post ) {
            $post_id = $the_post;
        }

        return Metadata::add( 'post', $post_id, $meta_key, $meta_value, $unique );
    }

    /**
     * Delete post meta
     */
    public static function delete( $post_id, $meta_key, $meta_value = '' ) {
        // Make sure meta is added to the post, not a revision.
        $the_post = wp_is_post_revision( $post_id );
        if ( $the_post ) {
            $post_id = $the_post;
        }

        return Metadata::delete( 'post', $post_id, $meta_key, $meta_value );
    }

    /**
     * Get a piece of postmeta
     */
    public static function get( $post_id, $key = '', $single = false  ) {
        return Metadata::get( 'post', $post_id, $key, $single );
    }

    /**
     * Update post meta
     */
    public static function update( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
        // Make sure meta is added to the post, not a revision.
        $the_post = wp_is_post_revision( $post_id );
        if ( $the_post ) {
            $post_id = $the_post;
        }

        return Metadata::update( 'post', $post_id, $meta_key, $meta_value, $prev_value );
    }

    /**
     * Delete all post meta, by key
     */
    public static function deleteByKey( $post_meta_key ) {
        return Metadata::delete( 'post', null, $post_meta_key, '', true );
    }

    /**
     * Register post meta
     */
    public static function register( $post_type, $meta_key, array $args ) {
        $args['object_subtype'] = $post_type;
	    return Metadata::register( 'post', $meta_key, $args );
    }

    /**
     * Unregister a meta key
     */
    public static function unregister( $post_type, $meta_key ) {
        return Metadata::unregisterKey( 'post', $meta_key, $post_type );
    }

    /**
     * Return custom post meta:
     */
    public static function getCustom( $post_id = 0 )
    {
        $post_id = absint( $post_id );
        if ( ! $post_id ) {
            $post_id = get_the_ID();
        }

        return get_post_meta( $post_id );
    }

    /**
     * Get custom keys
     */
    public static function getCustomKeys( $post_id ) {
        $custom = static::getCustom( $post_id );

        if ( ! is_array( $custom ) ) {
            return;
        }

        $keys = array_keys( $custom );
        if ( $keys ) {
            return $keys;
        }
    }

    /**
     * Get custom values
     */
    public static function getCustomValues( $key = '', $post_id = 0 ) {
        if ( ! $key ) {
            return null;
        }

        $custom = static::getCustom( $post_id );

        return isset( $custom[ $key ] ) ? $custom[ $key ] : null;
    }
}