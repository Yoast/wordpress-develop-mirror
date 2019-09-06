<?php

namespace WP\Helper\Post;

class PostHelper{


    /**
     * Retrieves post data given a post ID or post object.
     */
    public static function getPost( $post = null, $output = OBJECT, $filter = 'raw' ) {
        if ( empty( $post ) && isset( $GLOBALS['post'] ) ) {
            $post = $GLOBALS['post'];
        }

        if ( $post instanceof WP_Post ) {
            $_post = $post;
        } elseif ( is_object( $post ) ) {
            if ( empty( $post->filter ) ) {
                $_post = sanitize_post( $post, 'raw' );
                $_post = new WP_Post( $_post );
            } elseif ( 'raw' == $post->filter ) {
                $_post = new WP_Post( $post );
            } else {
                $_post = WP_Post::get_instance( $post->ID );
            }
        } else {
            $_post = WP_Post::get_instance( $post );
        }

        if ( ! $_post ) {
            return null;
        }

        $_post = $_post->filter( $filter );

        if ( $output == ARRAY_A ) {
            return $_post->to_array();
        } elseif ( $output == ARRAY_N ) {
            return array_values( $_post->to_array() );
        }

        return $_post;
    }


    /**
     * Retrieve ancestors of a post.
     */
    public static function getAncestors( $post ) {
        $post = get_post( $post );

        if ( ! $post || empty( $post->post_parent ) || $post->post_parent == $post->ID ) {
            return array();
        }

        $ancestors = array();

        $id          = $post->post_parent;
        $ancestors[] = $id;

        while ( $ancestor = get_post( $id ) ) {
            // Loop detection: If the ancestor has been seen before, break.
            if ( empty( $ancestor->post_parent ) || ( $ancestor->post_parent == $post->ID ) || in_array( $ancestor->post_parent, $ancestors ) ) {
                break;
            }

            $id          = $ancestor->post_parent;
            $ancestors[] = $id;
        }

        return $ancestors;
    }


    /**
     * Retrieve all children of the post parent ID.
     */
    public static function getChildren( $args = '', $output = OBJECT ) {
        $kids = array();
        if ( empty( $args ) ) {
            if ( isset( $GLOBALS['post'] ) ) {
                $args = array( 'post_parent' => (int) $GLOBALS['post']->post_parent );
            } else {
                return $kids;
            }
        } elseif ( is_object( $args ) ) {
            $args = array( 'post_parent' => (int) $args->post_parent );
        } elseif ( is_numeric( $args ) ) {
            $args = array( 'post_parent' => (int) $args );
        }

        $defaults = array(
            'numberposts' => -1,
            'post_type'   => 'any',
            'post_status' => 'any',
            'post_parent' => 0,
        );

        $parsed_args = wp_parse_args( $args, $defaults );

        $children = get_posts( $parsed_args );

        if ( ! $children ) {
            return $kids;
        }

        if ( ! empty( $parsed_args['fields'] ) ) {
            return $children;
        }

        update_post_cache( $children );

        foreach ( $children as $key => $child ) {
            $kids[ $child->ID ] = $children[ $key ];
        }

        if ( $output == OBJECT ) {
            return $kids;
        } elseif ( $output == ARRAY_A ) {
            $weeuns = array();
            foreach ( (array) $kids as $kid ) {
                $weeuns[ $kid->ID ] = get_object_vars( $kids[ $kid->ID ] );
            }
            return $weeuns;
        } elseif ( $output == ARRAY_N ) {
            $babes = array();
            foreach ( (array) $kids as $kid ) {
                $babes[ $kid->ID ] = array_values( get_object_vars( $kids[ $kid->ID ] ) );
            }
            return $babes;
        } else {
            return $kids;
        }
    }

    /**
     * Retrieves the post type of the current post or of a given post.
     */
    public static function getPostType( $post = null ) {
        $post = get_post( $post );
        if ( $post ) {
            return $post->post_type;
        }

        return false;
    }


    /**
     * Retrieve data from a post field based on Post ID.
     */
    public function getField( $field, $post = null, $context = 'display' )
    {
        $post = get_post( $post );

        if ( ! $post ) {
            return '';
        }

        if ( ! isset( $post->$field ) ) {
            return '';
        }

        return sanitize_post_field( $field, $post->$field, $post->ID, $context );
    }

    

    /**
     * Get extended entry info (<!--more-->).
     */
    public static function getExtended( $post ) {
        //Match the new style more links.
        if ( preg_match( '/<!--more(.*?)?-->/', $post, $matches ) ) {
            list($main, $extended) = explode( $matches[0], $post, 2 );
            $more_text             = $matches[1];
        } else {
            $main      = $post;
            $extended  = '';
            $more_text = '';
        }

        //  leading and trailing whitespace.
        $main      = preg_replace( '/^[\s]*(.*)[\s]*$/', '\\1', $main );
        $extended  = preg_replace( '/^[\s]*(.*)[\s]*$/', '\\1', $extended );
        $more_text = preg_replace( '/^[\s]*(.*)[\s]*$/', '\\1', $more_text );

        return array(
            'main'      => $main,
            'extended'  => $extended,
            'more_text' => $more_text,
        );
    }
    

}