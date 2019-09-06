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
}