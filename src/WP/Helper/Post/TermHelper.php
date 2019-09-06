<?php

namespace WP\Helper\Post;

use WP\Helper\Taxonomy\TermHelper as Term;

class TermHelper{

    /**
     * Get all categories, tied to a post
     */
    public static function getCategories( $post_id = 0, $args = array() ) {
        $post_id = (int) $post_id;

        $defaults = array( 'fields' => 'ids' );
        $args     = wp_parse_args( $args, $defaults );

        $cats = Term::getForObject( $post_id, 'category', $args );
        return $cats;
    }

    /**
     * Get all tags tied to a post
     */
    public static function getTags( $post_id = 0, $args = array() ) {
        return static::getTerms( $post_id, 'post_tag', $args );        
    }

    /**
     * Get Terms:
     */
    public static function getTerms( $post_id = 0, $taxonomy = 'post_tag', $args = array() ) {
        $post_id = (int) $post_id;

        $defaults = array( 'fields' => 'all' );
        $args     = wp_parse_args( $args, $defaults );

        $tags = Term::getForObject( $post_id, $taxonomy, $args );

        return $tags;    
    }

    /**
     * Add tags to a post
     */
    public static function addTags( $post_id = 0, $tags = '' ) {
        return static::setTags( $post_id, $tags, true );
    }

    /**
     * Add tags to a post:
     */
    public static function setTags( $post_id = 0, $tags = '', $taxonomy = 'post_tag', $append = false ) {
        return static::setTerms( $post_id, $tags, 'post_tag', $append );
    }

    /**
     * Set categories:
     *
     * @return void
     */
    public static function setCategories() {
        $post_ID     = (int) $post_ID;
        $post_type   = get_post_type( $post_ID );
        $post_status = get_post_status( $post_ID );
        // If $post_categories isn't already an array, make it one:
        $post_categories = (array) $post_categories;
        if ( empty( $post_categories ) ) {
            if ( 'post' == $post_type && 'auto-draft' != $post_status ) {
                $post_categories = array( get_option( 'default_category' ) );
                $append          = false;
            } else {
                $post_categories = array();
            }
        } elseif ( 1 == count( $post_categories ) && '' == reset( $post_categories ) ) {
            return true;
        }

        return static::setTerms( $post_ID, $post_categories, 'category', $append );
    }

    /**
     * Set terms
     */
    public static function setTerms( $post_id = 0, $tags = '', $taxonomy = 'post_tag', $append = false ) {
        $post_id = (int) $post_id;

        if ( ! $post_id ) {
            return false;
        }

        if ( empty( $tags ) ) {
            $tags = array();
        }

        if ( ! is_array( $tags ) ) {
            $comma = _x( ',', 'tag delimiter' );
            if ( ',' !== $comma ) {
                $tags = str_replace( $comma, ',', $tags );
            }
            $tags = explode( ',', trim( $tags, " \n\t\r\0\x0B," ) );
        }

        /*
        * Hierarchical taxonomies must always pass IDs rather than names so that
        * children with the same names but different parents aren't confused.
        */
        if ( is_taxonomy_hierarchical( $taxonomy ) ) {
            $tags = array_unique( array_map( 'intval', $tags ) );
        }

        return Term::setForObject( $post_id, $tags, $taxonomy, $append );
    }
}