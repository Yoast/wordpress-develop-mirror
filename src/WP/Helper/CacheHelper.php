<?php

namespace WP\Helper;

class CacheHelper {

    /**
     * get the count post cache key
     */
    public static function countPostCacheKey( $type = 'post', $perm = '' ) {
        $cache_key = 'posts-' . $type;
        if ( 'readable' == $perm && is_user_logged_in() ) {
            $post_type_object = get_post_type_object( $type );
            if ( $post_type_object && ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
                $cache_key .= '_' . $perm . '_' . get_current_user_id();
            }
        }
        return $cache_key;
    }
}
