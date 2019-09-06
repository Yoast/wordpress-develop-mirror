<?php

namespace WP\Helper\Meta;

class Metadata{

    /**
     * Add meta data
     */
    public static function add( $meta_type, $object_id, $meta_key, $meta_value, $unique = false ){
        global $wpdb;

        if ( ! $meta_type || ! $meta_key || ! is_numeric( $object_id ) ) {
            return false;
        }

        $object_id = absint( $object_id );
        if ( ! $object_id ) {
            return false;
        }

        $table = _get_meta_table( $meta_type );
        if ( ! $table ) {
            return false;
        }

        $meta_subtype = get_object_subtype( $meta_type, $object_id );

        $column = sanitize_key( $meta_type . '_id' );

        // expected_slashed ($meta_key)
        $meta_key   = wp_unslash( $meta_key );
        $meta_value = wp_unslash( $meta_value );
        $meta_value = sanitize_meta( $meta_key, $meta_value, $meta_type, $meta_subtype );

        /**
         * Filters whether to add metadata of a specific type.
         *
         * The dynamic portion of the hook, `$meta_type`, refers to the meta
         * object type (comment, post, term, or user). Returning a non-null value
         * will effectively short-circuit the function.
         *
         * @since 3.1.0
         *
         * @param null|bool $check      Whether to allow adding metadata for the given type.
         * @param int       $object_id  Object ID.
         * @param string    $meta_key   Meta key.
         * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
         * @param bool      $unique     Whether the specified meta key should be unique
         *                              for the object. Optional. Default false.
         */
        $check = apply_filters( "add_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $unique );
        if ( null !== $check ) {
            return $check;
        }

        if ( $unique && $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE meta_key = %s AND $column = %d",
                $meta_key,
                $object_id
            )
        ) ) {
            return false;
        }

        $_meta_value = $meta_value;
        $meta_value  = maybe_serialize( $meta_value );

        /**
         * Fires immediately before meta of a specific type is added.
         *
         * The dynamic portion of the hook, `$meta_type`, refers to the meta
         * object type (comment, post, term, or user).
         *
         * @since 3.1.0
         *
         * @param int    $object_id   Object ID.
         * @param string $meta_key    Meta key.
         * @param mixed  $_meta_value Meta value.
         */
        do_action( "add_{$meta_type}_meta", $object_id, $meta_key, $_meta_value );

        $result = $wpdb->insert(
            $table,
            array(
                $column      => $object_id,
                'meta_key'   => $meta_key,
                'meta_value' => $meta_value,
            )
        );

        if ( ! $result ) {
            return false;
        }

        $mid = (int) $wpdb->insert_id;

        wp_cache_delete( $object_id, $meta_type . '_meta' );

        /**
         * Fires immediately after meta of a specific type is added.
         *
         * The dynamic portion of the hook, `$meta_type`, refers to the meta
         * object type (comment, post, term, or user).
         *
         * @since 2.9.0
         *
         * @param int    $mid         The meta ID after successful update.
         * @param int    $object_id   Object ID.
         * @param string $meta_key    Meta key.
         * @param mixed  $_meta_value Meta value.
         */
        do_action( "added_{$meta_type}_meta", $mid, $object_id, $meta_key, $_meta_value );

        return $mid;
    }

    /**
     * Update metadata
     */
    public static function update( $meta_type, $object_id, $meta_key, $meta_value, $prev_value = '' ) {
        global $wpdb;

        if ( ! $meta_type || ! $meta_key || ! is_numeric( $object_id ) ) {
            return false;
        }

        $object_id = absint( $object_id );
        if ( ! $object_id ) {
            return false;
        }

        $table = _get_meta_table( $meta_type );
        if ( ! $table ) {
            return false;
        }

        $meta_subtype = get_object_subtype( $meta_type, $object_id );

        $column    = sanitize_key( $meta_type . '_id' );
        $id_column = 'user' == $meta_type ? 'umeta_id' : 'meta_id';

        // expected_slashed ($meta_key)
        $raw_meta_key = $meta_key;
        $meta_key     = wp_unslash( $meta_key );
        $passed_value = $meta_value;
        $meta_value   = wp_unslash( $meta_value );
        $meta_value   = sanitize_meta( $meta_key, $meta_value, $meta_type, $meta_subtype );

        /**
         * Filters whether to update metadata of a specific type.
         *
         * The dynamic portion of the hook, `$meta_type`, refers to the meta
         * object type (comment, post, term, or user). Returning a non-null value
         * will effectively short-circuit the function.
         *
         * @since 3.1.0
         *
         * @param null|bool $check      Whether to allow updating metadata for the given type.
         * @param int       $object_id  Object ID.
         * @param string    $meta_key   Meta key.
         * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
         * @param mixed     $prev_value Optional. If specified, only update existing
         *                              metadata entries with the specified value.
         *                              Otherwise, update all entries.
         */
        $check = apply_filters( "update_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $prev_value );
        if ( null !== $check ) {
            return (bool) $check;
        }

        // Compare existing value to new value if no prev value given and the key exists only once.
        if ( empty( $prev_value ) ) {
            $old_value = static::get( $meta_type, $object_id, $meta_key );
            if ( count( $old_value ) == 1 ) {
                if ( $old_value[0] === $meta_value ) {
                    return false;
                }
            }
        }

        $meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $id_column FROM $table WHERE meta_key = %s AND $column = %d", $meta_key, $object_id ) );
        if ( empty( $meta_ids ) ) {
            return static::add( $meta_type, $object_id, $raw_meta_key, $passed_value );
        }

        $_meta_value = $meta_value;
        $meta_value  = maybe_serialize( $meta_value );

        $data  = compact( 'meta_value' );
        $where = array(
            $column    => $object_id,
            'meta_key' => $meta_key,
        );

        if ( ! empty( $prev_value ) ) {
            $prev_value          = maybe_serialize( $prev_value );
            $where['meta_value'] = $prev_value;
        }

        foreach ( $meta_ids as $meta_id ) {
            /**
             * Fires immediately before updating metadata of a specific type.
             *
             * The dynamic portion of the hook, `$meta_type`, refers to the meta
             * object type (comment, post, term, or user).
             *
             * @since 2.9.0
             *
             * @param int    $meta_id     ID of the metadata entry to update.
             * @param int    $object_id   Object ID.
             * @param string $meta_key    Meta key.
             * @param mixed  $_meta_value Meta value.
             */
            do_action( "update_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );

            if ( 'post' == $meta_type ) {
                /**
                 * Fires immediately before updating a post's metadata.
                 *
                 * @since 2.9.0
                 *
                 * @param int    $meta_id    ID of metadata entry to update.
                 * @param int    $object_id  Post ID.
                 * @param string $meta_key   Meta key.
                 * @param mixed  $meta_value Meta value. This will be a PHP-serialized string representation of the value if
                 *                           the value is an array, an object, or itself a PHP-serialized string.
                 */
                do_action( 'update_postmeta', $meta_id, $object_id, $meta_key, $meta_value );
            }
        }

        $result = $wpdb->update( $table, $data, $where );
        if ( ! $result ) {
            return false;
        }

        wp_cache_delete( $object_id, $meta_type . '_meta' );

        foreach ( $meta_ids as $meta_id ) {
            /**
             * Fires immediately after updating metadata of a specific type.
             *
             * The dynamic portion of the hook, `$meta_type`, refers to the meta
             * object type (comment, post, term, or user).
             *
             * @since 2.9.0
             *
             * @param int    $meta_id     ID of updated metadata entry.
             * @param int    $object_id   Object ID.
             * @param string $meta_key    Meta key.
             * @param mixed  $_meta_value Meta value.
             */
            do_action( "updated_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );

            if ( 'post' == $meta_type ) {
                /**
                 * Fires immediately after updating a post's metadata.
                 *
                 * @since 2.9.0
                 *
                 * @param int    $meta_id    ID of updated metadata entry.
                 * @param int    $object_id  Post ID.
                 * @param string $meta_key   Meta key.
                 * @param mixed  $meta_value Meta value. This will be a PHP-serialized string representation of the value if
                 *                           the value is an array, an object, or itself a PHP-serialized string.
                 */
                do_action( 'updated_postmeta', $meta_id, $object_id, $meta_key, $meta_value );
            }
        }

        return true;
    }

    /**
     * Delete meta
     */
    public static function delete( $meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false ) {
        global $wpdb;

        if ( ! $meta_type || ! $meta_key || ! is_numeric( $object_id ) && ! $delete_all ) {
            return false;
        }

        $object_id = absint( $object_id );
        if ( ! $object_id && ! $delete_all ) {
            return false;
        }

        $table = _get_meta_table( $meta_type );
        if ( ! $table ) {
            return false;
        }

        $type_column = sanitize_key( $meta_type . '_id' );
        $id_column   = 'user' == $meta_type ? 'umeta_id' : 'meta_id';
        // expected_slashed ($meta_key)
        $meta_key   = wp_unslash( $meta_key );
        $meta_value = wp_unslash( $meta_value );

        /**
         * Filters whether to delete metadata of a specific type.
         *
         * The dynamic portion of the hook, `$meta_type`, refers to the meta
         * object type (comment, post, term, or user). Returning a non-null value
         * will effectively short-circuit the function.
         *
         * @since 3.1.0
         *
         * @param null|bool $delete     Whether to allow metadata deletion of the given type.
         * @param int       $object_id  Object ID.
         * @param string    $meta_key   Meta key.
         * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
         * @param bool      $delete_all Whether to delete the matching metadata entries
         *                              for all objects, ignoring the specified $object_id.
         *                              Default false.
         */
        $check = apply_filters( "delete_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $delete_all );
        if ( null !== $check ) {
            return (bool) $check;
        }

        $_meta_value = $meta_value;
        $meta_value  = maybe_serialize( $meta_value );

        $query = $wpdb->prepare( "SELECT $id_column FROM $table WHERE meta_key = %s", $meta_key );

        if ( ! $delete_all ) {
            $query .= $wpdb->prepare( " AND $type_column = %d", $object_id );
        }

        if ( '' !== $meta_value && null !== $meta_value && false !== $meta_value ) {
            $query .= $wpdb->prepare( ' AND meta_value = %s', $meta_value );
        }

        $meta_ids = $wpdb->get_col( $query );
        if ( ! count( $meta_ids ) ) {
            return false;
        }

        if ( $delete_all ) {
            if ( '' !== $meta_value && null !== $meta_value && false !== $meta_value ) {
                $object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $type_column FROM $table WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value ) );
            } else {
                $object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $type_column FROM $table WHERE meta_key = %s", $meta_key ) );
            }
        }

        /**
         * Fires immediately before deleting metadata of a specific type.
         *
         * The dynamic portion of the hook, `$meta_type`, refers to the meta
         * object type (comment, post, term, or user).
         *
         * @since 3.1.0
         *
         * @param array  $meta_ids    An array of metadata entry IDs to delete.
         * @param int    $object_id   Object ID.
         * @param string $meta_key    Meta key.
         * @param mixed  $_meta_value Meta value.
         */
        do_action( "delete_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value );

        // Old-style action.
        if ( 'post' == $meta_type ) {
            /**
             * Fires immediately before deleting metadata for a post.
             *
             * @since 2.9.0
             *
             * @param array $meta_ids An array of post metadata entry IDs to delete.
             */
            do_action( 'delete_postmeta', $meta_ids );
        }

        $query = "DELETE FROM $table WHERE $id_column IN( " . implode( ',', $meta_ids ) . ' )';

        $count = $wpdb->query( $query );

        if ( ! $count ) {
            return false;
        }

        if ( $delete_all ) {
            foreach ( (array) $object_ids as $o_id ) {
                wp_cache_delete( $o_id, $meta_type . '_meta' );
            }
        } else {
            wp_cache_delete( $object_id, $meta_type . '_meta' );
        }

        /**
         * Fires immediately after deleting metadata of a specific type.
         *
         * The dynamic portion of the hook name, `$meta_type`, refers to the meta
         * object type (comment, post, term, or user).
         *
         * @since 2.9.0
         *
         * @param array  $meta_ids    An array of deleted metadata entry IDs.
         * @param int    $object_id   Object ID.
         * @param string $meta_key    Meta key.
         * @param mixed  $_meta_value Meta value.
         */
        do_action( "deleted_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value );

        // Old-style action.
        if ( 'post' == $meta_type ) {
            /**
             * Fires immediately after deleting metadata for a post.
             *
             * @since 2.9.0
             *
             * @param array $meta_ids An array of deleted post metadata entry IDs.
             */
            do_action( 'deleted_postmeta', $meta_ids );
        }

        return true;
    }

    /**
     * Get metadata
     */
    public static function get( $meta_type, $object_id, $meta_key = '', $single = false ) {
        if ( ! $meta_type || ! is_numeric( $object_id ) ) {
            return false;
        }

        $object_id = absint( $object_id );
        if ( ! $object_id ) {
            return false;
        }

        /**
         * Filters whether to retrieve metadata of a specific type.
         *
         * The dynamic portion of the hook, `$meta_type`, refers to the meta
         * object type (comment, post, term, or user). Returning a non-null value
         * will effectively short-circuit the function.
         *
         * @since 3.1.0
         *
         * @param null|array|string $value     The value get_metadata() should return - a single metadata value,
         *                                     or an array of values.
         * @param int               $object_id Object ID.
         * @param string            $meta_key  Meta key.
         * @param bool              $single    Whether to return only the first value of the specified $meta_key.
         */
        $check = apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, $single );
        if ( null !== $check ) {
            if ( $single && is_array( $check ) ) {
                return $check[0];
            } else {
                return $check;
            }
        }

        $meta_cache = wp_cache_get( $object_id, $meta_type . '_meta' );

        if ( ! $meta_cache ) {
            $meta_cache = update_meta_cache( $meta_type, array( $object_id ) );
            if ( isset( $meta_cache[ $object_id ] ) ) {
                $meta_cache = $meta_cache[ $object_id ];
            } else {
                $meta_cache = null;
            }
        }

        if ( ! $meta_key ) {
            return $meta_cache;
        }

        if ( isset( $meta_cache[ $meta_key ] ) ) {
            if ( $single ) {
                return maybe_unserialize( $meta_cache[ $meta_key ][0] );
            } else {
                return array_map( 'maybe_unserialize', $meta_cache[ $meta_key ] );
            }
        }

        if ( $single ) {
            return '';
        } else {
            return array();
        }
    }

    /**
     * Register meta data:
     */
    public static function register( $object_type, $meta_key, $args, $deprecated = null ) {
        global $wp_meta_keys;

        if ( ! is_array( $wp_meta_keys ) ) {
            $wp_meta_keys = array();
        }

        $defaults = array(
            'object_subtype'    => '',
            'type'              => 'string',
            'description'       => '',
            'single'            => false,
            'sanitize_callback' => null,
            'auth_callback'     => null,
            'show_in_rest'      => false,
        );

        // There used to be individual args for sanitize and auth callbacks
        $has_old_sanitize_cb = false;
        $has_old_auth_cb     = false;

        if ( is_callable( $args ) ) {
            $args = array(
                'sanitize_callback' => $args,
            );

            $has_old_sanitize_cb = true;
        } else {
            $args = (array) $args;
        }

        if ( is_callable( $deprecated ) ) {
            $args['auth_callback'] = $deprecated;
            $has_old_auth_cb       = true;
        }

        /**
         * Filters the registration arguments when registering meta.
         *
         * @since 4.6.0
         *
         * @param array  $args        Array of meta registration arguments.
         * @param array  $defaults    Array of default arguments.
         * @param string $object_type Object type.
         * @param string $meta_key    Meta key.
         */
        $args = apply_filters( 'register_meta_args', $args, $defaults, $object_type, $meta_key );
        $args = wp_parse_args( $args, $defaults );

        $object_subtype = ! empty( $args['object_subtype'] ) ? $args['object_subtype'] : '';

        // If `auth_callback` is not provided, fall back to `is_protected_meta()`.
        if ( empty( $args['auth_callback'] ) ) {
            if ( is_protected_meta( $meta_key, $object_type ) ) {
                $args['auth_callback'] = '__return_false';
            } else {
                $args['auth_callback'] = '__return_true';
            }
        }

        // Back-compat: old sanitize and auth callbacks are applied to all of an object type.
        if ( is_callable( $args['sanitize_callback'] ) ) {
            if ( ! empty( $object_subtype ) ) {
                add_filter( "sanitize_{$object_type}_meta_{$meta_key}_for_{$object_subtype}", $args['sanitize_callback'], 10, 4 );
            } else {
                add_filter( "sanitize_{$object_type}_meta_{$meta_key}", $args['sanitize_callback'], 10, 3 );
            }
        }

        if ( is_callable( $args['auth_callback'] ) ) {
            if ( ! empty( $object_subtype ) ) {
                add_filter( "auth_{$object_type}_meta_{$meta_key}_for_{$object_subtype}", $args['auth_callback'], 10, 6 );
            } else {
                add_filter( "auth_{$object_type}_meta_{$meta_key}", $args['auth_callback'], 10, 6 );
            }
        }

        // Global registry only contains meta keys registered with the array of arguments added in 4.6.0.
        if ( ! $has_old_auth_cb && ! $has_old_sanitize_cb ) {
            unset( $args['object_subtype'] );

            $wp_meta_keys[ $object_type ][ $object_subtype ][ $meta_key ] = $args;

            return true;
        }

        return false;
    }

    /**
     * Unregister a piece of metadata
     */
    public static function unregisterKey( $object_type, $meta_key, $object_subtype = '' ) {
        global $wp_meta_keys;

        if ( ! registered_meta_key_exists( $object_type, $meta_key, $object_subtype ) ) {
            return false;
        }

        $args = $wp_meta_keys[ $object_type ][ $object_subtype ][ $meta_key ];

        if ( isset( $args['sanitize_callback'] ) && is_callable( $args['sanitize_callback'] ) ) {
            if ( ! empty( $object_subtype ) ) {
                remove_filter( "sanitize_{$object_type}_meta_{$meta_key}_for_{$object_subtype}", $args['sanitize_callback'] );
            } else {
                remove_filter( "sanitize_{$object_type}_meta_{$meta_key}", $args['sanitize_callback'] );
            }
        }

        if ( isset( $args['auth_callback'] ) && is_callable( $args['auth_callback'] ) ) {
            if ( ! empty( $object_subtype ) ) {
                remove_filter( "auth_{$object_type}_meta_{$meta_key}_for_{$object_subtype}", $args['auth_callback'] );
            } else {
                remove_filter( "auth_{$object_type}_meta_{$meta_key}", $args['auth_callback'] );
            }
        }

        unset( $wp_meta_keys[ $object_type ][ $object_subtype ][ $meta_key ] );

        // Do some clean up
        if ( empty( $wp_meta_keys[ $object_type ][ $object_subtype ] ) ) {
            unset( $wp_meta_keys[ $object_type ][ $object_subtype ] );
        }
        if ( empty( $wp_meta_keys[ $object_type ] ) ) {
            unset( $wp_meta_keys[ $object_type ] );
        }

        return true;
    }
        
}