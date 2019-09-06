<?php

namespace WP\Helper\Taxonomy;

class TermHelper{

    /**
     * Add a term
     */
    public static function add( $term, $taxonomy, $args = array() ) {
        global $wpdb;

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
        }
        /**
         * Filters a term before it is sanitized and inserted into the database.
         *
         * @since 3.0.0
         *
         * @param string $term     The term to add or update.
         * @param string $taxonomy Taxonomy slug.
         */
        $term = apply_filters( 'pre_insert_term', $term, $taxonomy );
        if ( is_wp_error( $term ) ) {
            return $term;
        }
        if ( is_int( $term ) && 0 === $term ) {
            return new WP_Error( 'invalid_term_id', __( 'Invalid term ID.' ) );
        }
        if ( '' === trim( $term ) ) {
            return new WP_Error( 'empty_term_name', __( 'A name is required for this term.' ) );
        }
        $defaults = array(
            'alias_of'    => '',
            'description' => '',
            'parent'      => 0,
            'slug'        => '',
        );
        $args     = wp_parse_args( $args, $defaults );

        if ( $args['parent'] > 0 && ! term_exists( (int) $args['parent'] ) ) {
            return new WP_Error( 'missing_parent', __( 'Parent term does not exist.' ) );
        }

        $args['name']     = $term;
        $args['taxonomy'] = $taxonomy;

        // Coerce null description to strings, to avoid database errors.
        $args['description'] = (string) $args['description'];

        $args = sanitize_term( $args, $taxonomy, 'db' );

        // expected_slashed ($name)
        $name        = wp_unslash( $args['name'] );
        $description = wp_unslash( $args['description'] );
        $parent      = (int) $args['parent'];

        $slug_provided = ! empty( $args['slug'] );
        if ( ! $slug_provided ) {
            $slug = sanitize_title( $name );
        } else {
            $slug = $args['slug'];
        }

        $term_group = 0;
        if ( $args['alias_of'] ) {
            $alias = get_term_by( 'slug', $args['alias_of'], $taxonomy );
            if ( ! empty( $alias->term_group ) ) {
                // The alias we want is already in a group, so let's use that one.
                $term_group = $alias->term_group;
            } elseif ( ! empty( $alias->term_id ) ) {
                /*
                * The alias is not in a group, so we create a new one
                * and add the alias to it.
                */
                $term_group = $wpdb->get_var( "SELECT MAX(term_group) FROM $wpdb->terms" ) + 1;

                wp_update_term(
                    $alias->term_id,
                    $taxonomy,
                    array(
                        'term_group' => $term_group,
                    )
                );
            }
        }

        /*
        * Prevent the creation of terms with duplicate names at the same level of a taxonomy hierarchy,
        * unless a unique slug has been explicitly provided.
        */
        $name_matches = get_terms(
            array(
                'taxonomy'               => $taxonomy,
                'name'                   => $name,
                'hide_empty'             => false,
                'parent'                 => $args['parent'],
                'update_term_meta_cache' => false,
            )
        );

        /*
        * The `name` match in `get_terms()` doesn't differentiate accented characters,
        * so we do a stricter comparison here.
        */
        $name_match = null;
        if ( $name_matches ) {
            foreach ( $name_matches as $_match ) {
                if ( strtolower( $name ) === strtolower( $_match->name ) ) {
                    $name_match = $_match;
                    break;
                }
            }
        }

        if ( $name_match ) {
            $slug_match = get_term_by( 'slug', $slug, $taxonomy );
            if ( ! $slug_provided || $name_match->slug === $slug || $slug_match ) {
                if ( is_taxonomy_hierarchical( $taxonomy ) ) {
                    $siblings = get_terms(
                        array(
                            'taxonomy'               => $taxonomy,
                            'get'                    => 'all',
                            'parent'                 => $parent,
                            'update_term_meta_cache' => false,
                        )
                    );

                    $existing_term = null;
                    if ( ( ! $slug_provided || $name_match->slug === $slug ) && in_array( $name, wp_list_pluck( $siblings, 'name' ) ) ) {
                        $existing_term = $name_match;
                    } elseif ( $slug_match && in_array( $slug, wp_list_pluck( $siblings, 'slug' ) ) ) {
                        $existing_term = $slug_match;
                    }

                    if ( $existing_term ) {
                        return new WP_Error( 'term_exists', __( 'A term with the name provided already exists with this parent.' ), $existing_term->term_id );
                    }
                } else {
                    return new WP_Error( 'term_exists', __( 'A term with the name provided already exists in this taxonomy.' ), $name_match->term_id );
                }
            }
        }

        $slug = wp_unique_term_slug( $slug, (object) $args );

        $data = compact( 'name', 'slug', 'term_group' );

        /**
         * Filters term data before it is inserted into the database.
         *
         * @since 4.7.0
         *
         * @param array  $data     Term data to be inserted.
         * @param string $taxonomy Taxonomy slug.
         * @param array  $args     Arguments passed to wp_insert_term().
         */
        $data = apply_filters( 'wp_insert_term_data', $data, $taxonomy, $args );

        if ( false === $wpdb->insert( $wpdb->terms, $data ) ) {
            return new WP_Error( 'db_insert_error', __( 'Could not insert term into the database.' ), $wpdb->last_error );
        }

        $term_id = (int) $wpdb->insert_id;

        // Seems unreachable, However, Is used in the case that a term name is provided, which sanitizes to an empty string.
        if ( empty( $slug ) ) {
            $slug = sanitize_title( $slug, $term_id );

            /** This action is documented in wp-includes/taxonomy.php */
            do_action( 'edit_terms', $term_id, $taxonomy );
            $wpdb->update( $wpdb->terms, compact( 'slug' ), compact( 'term_id' ) );

            /** This action is documented in wp-includes/taxonomy.php */
            do_action( 'edited_terms', $term_id, $taxonomy );
        }

        $tt_id = $wpdb->get_var( $wpdb->prepare( "SELECT tt.term_taxonomy_id FROM $wpdb->term_taxonomy AS tt INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.term_id = %d", $taxonomy, $term_id ) );

        if ( ! empty( $tt_id ) ) {
            return array(
                'term_id'          => $term_id,
                'term_taxonomy_id' => $tt_id,
            );
        }

        if ( false === $wpdb->insert( $wpdb->term_taxonomy, compact( 'term_id', 'taxonomy', 'description', 'parent' ) + array( 'count' => 0 ) ) ) {
            return new WP_Error( 'db_insert_error', __( 'Could not insert term taxonomy into the database.' ), $wpdb->last_error );
        }

        $tt_id = (int) $wpdb->insert_id;

        /*
        * Sanity check: if we just created a term with the same parent + taxonomy + slug but a higher term_id than
        * an existing term, then we have unwittingly created a duplicate term. Delete the dupe, and use the term_id
        * and term_taxonomy_id of the older term instead. Then return out of the function so that the "create" hooks
        * are not fired.
        */
        $duplicate_term = $wpdb->get_row( $wpdb->prepare( "SELECT t.term_id, t.slug, tt.term_taxonomy_id, tt.taxonomy FROM $wpdb->terms t INNER JOIN $wpdb->term_taxonomy tt ON ( tt.term_id = t.term_id ) WHERE t.slug = %s AND tt.parent = %d AND tt.taxonomy = %s AND t.term_id < %d AND tt.term_taxonomy_id != %d", $slug, $parent, $taxonomy, $term_id, $tt_id ) );

        /**
         * Filters the duplicate term check that takes place during term creation.
         *
         * Term parent+taxonomy+slug combinations are meant to be unique, and wp_insert_term()
         * performs a last-minute confirmation of this uniqueness before allowing a new term
         * to be created. Plugins with different uniqueness requirements may use this filter
         * to bypass or modify the duplicate-term check.
         *
         * @since 5.1.0
         *
         * @param object $duplicate_term Duplicate term row from terms table, if found.
         * @param string $term           Term being inserted.
         * @param string $taxonomy       Taxonomy name.
         * @param array  $args           Term arguments passed to the function.
         * @param int    $tt_id          term_taxonomy_id for the newly created term.
         */
        $duplicate_term = apply_filters( 'wp_insert_term_duplicate_term_check', $duplicate_term, $term, $taxonomy, $args, $tt_id );

        if ( $duplicate_term ) {
            $wpdb->delete( $wpdb->terms, array( 'term_id' => $term_id ) );
            $wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $tt_id ) );

            $term_id = (int) $duplicate_term->term_id;
            $tt_id   = (int) $duplicate_term->term_taxonomy_id;

            clean_term_cache( $term_id, $taxonomy );
            return array(
                'term_id'          => $term_id,
                'term_taxonomy_id' => $tt_id,
            );
        }

        /**
         * Fires immediately after a new term is created, before the term cache is cleaned.
         *
         * @since 2.3.0
         *
         * @param int    $term_id  Term ID.
         * @param int    $tt_id    Term taxonomy ID.
         * @param string $taxonomy Taxonomy slug.
         */
        do_action( 'create_term', $term_id, $tt_id, $taxonomy );

        /**
         * Fires after a new term is created for a specific taxonomy.
         *
         * The dynamic portion of the hook name, `$taxonomy`, refers
         * to the slug of the taxonomy the term was created for.
         *
         * @since 2.3.0
         *
         * @param int $term_id Term ID.
         * @param int $tt_id   Term taxonomy ID.
         */
        do_action( "create_{$taxonomy}", $term_id, $tt_id );

        /**
         * Filters the term ID after a new term is created.
         *
         * @since 2.3.0
         *
         * @param int $term_id Term ID.
         * @param int $tt_id   Taxonomy term ID.
         */
        $term_id = apply_filters( 'term_id_filter', $term_id, $tt_id );

        clean_term_cache( $term_id, $taxonomy );

        /**
         * Fires after a new term is created, and after the term cache has been cleaned.
         *
         * @since 2.3.0
         *
         * @param int    $term_id  Term ID.
         * @param int    $tt_id    Term taxonomy ID.
         * @param string $taxonomy Taxonomy slug.
         */
        do_action( 'created_term', $term_id, $tt_id, $taxonomy );

        /**
         * Fires after a new term in a specific taxonomy is created, and after the term
         * cache has been cleaned.
         *
         * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
         *
         * @since 2.3.0
         *
         * @param int $term_id Term ID.
         * @param int $tt_id   Term taxonomy ID.
         */
        do_action( "created_{$taxonomy}", $term_id, $tt_id );

        return array(
            'term_id'          => $term_id,
            'term_taxonomy_id' => $tt_id,
        );
    }


    /**
     * Get terms for an object
     */
    public static function getForObject( $object_ids, $taxonomies, $args = array() ) {

        if ( empty( $object_ids ) || empty( $taxonomies ) ) {
            return array();
        }

        if ( ! is_array( $taxonomies ) ) {
            $taxonomies = array( $taxonomies );
        }

        foreach ( $taxonomies as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                return new \WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
            }
        }

        if ( ! is_array( $object_ids ) ) {
            $object_ids = array( $object_ids );
        }
        $object_ids = array_map( 'intval', $object_ids );

        $args = wp_parse_args( $args );

        /**
         * Filter arguments for retrieving object terms.
         *
         * @since 4.9.0
         *
         * @param array    $args       An array of arguments for retrieving terms for the given object(s).
         *                             See {@see wp_get_object_terms()} for details.
         * @param int[]    $object_ids Array of object IDs.
         * @param string[] $taxonomies Array of taxonomy names to retrieve terms from.
         */
        $args = apply_filters( 'wp_get_object_terms_args', $args, $object_ids, $taxonomies );

        /*
        * When one or more queried taxonomies is registered with an 'args' array,
        * those params override the `$args` passed to this function.
        */
        $terms = array();
        if ( count( $taxonomies ) > 1 ) {
            foreach ( $taxonomies as $index => $taxonomy ) {
                $t = get_taxonomy( $taxonomy );
                if ( isset( $t->args ) && is_array( $t->args ) && $args != array_merge( $args, $t->args ) ) {
                    unset( $taxonomies[ $index ] );
                    $terms = array_merge( $terms, wp_get_object_terms( $object_ids, $taxonomy, array_merge( $args, $t->args ) ) );
                }
            }
        } else {
            $t = get_taxonomy( $taxonomies[0] );
            if ( isset( $t->args ) && is_array( $t->args ) ) {
                $args = array_merge( $args, $t->args );
            }
        }

        $args['taxonomy']   = $taxonomies;
        $args['object_ids'] = $object_ids;

        // Taxonomies registered without an 'args' param are handled here.
        if ( ! empty( $taxonomies ) ) {
            $terms_from_remaining_taxonomies = get_terms( $args );

            // Array keys should be preserved for values of $fields that use term_id for keys.
            if ( ! empty( $args['fields'] ) && 0 === strpos( $args['fields'], 'id=>' ) ) {
                $terms = $terms + $terms_from_remaining_taxonomies;
            } else {
                $terms = array_merge( $terms, $terms_from_remaining_taxonomies );
            }
        }

        /**
         * Filters the terms for a given object or objects.
         *
         * @since 4.2.0
         *
         * @param array    $terms      Array of terms for the given object or objects.
         * @param int[]    $object_ids Array of object IDs for which terms were retrieved.
         * @param string[] $taxonomies Array of taxonomy names from which terms were retrieved.
         * @param array    $args       Array of arguments for retrieving terms for the given
         *                             object(s). See wp_get_object_terms() for details.
         */
        $terms = apply_filters( 'get_object_terms', $terms, $object_ids, $taxonomies, $args );

        $object_ids = implode( ',', $object_ids );
        $taxonomies = "'" . implode( "', '", array_map( 'esc_sql', $taxonomies ) ) . "'";

        /**
         * Filters the terms for a given object or objects.
         *
         * The `$taxonomies` parameter passed to this filter is formatted as a SQL fragment. The
         * {@see 'get_object_terms'} filter is recommended as an alternative.
         *
         * @since 2.8.0
         *
         * @param array    $terms      Array of terms for the given object or objects.
         * @param int[]    $object_ids Array of object IDs for which terms were retrieved.
         * @param string[] $taxonomies Array of taxonomy names from which terms were retrieved.
         * @param array    $args       Array of arguments for retrieving terms for the given
         *                             object(s). See wp_get_object_terms() for details.
         */
        return apply_filters( 'wp_get_object_terms', $terms, $object_ids, $taxonomies, $args );
    }


    /**
     * Set terms for an object
     */
    public static function setForObject( $object_id, $terms, $taxonomy, $append = false ) {
        global $wpdb;

        $object_id = (int) $object_id;

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
        }

        if ( ! is_array( $terms ) ) {
            $terms = array( $terms );
        }

        if ( ! $append ) {
            $old_tt_ids = wp_get_object_terms(
                $object_id,
                $taxonomy,
                array(
                    'fields'                 => 'tt_ids',
                    'orderby'                => 'none',
                    'update_term_meta_cache' => false,
                )
            );
        } else {
            $old_tt_ids = array();
        }

        $tt_ids     = array();
        $term_ids   = array();
        $new_tt_ids = array();

        foreach ( (array) $terms as $term ) {
            if ( '' === trim( $term ) ) {
                continue;
            }

            $term_info = term_exists( $term, $taxonomy );
            if ( ! $term_info ) {
                // Skip if a non-existent term ID is passed.
                if ( is_int( $term ) ) {
                    continue;
                }
                $term_info = wp_insert_term( $term, $taxonomy );
            }
            if ( is_wp_error( $term_info ) ) {
                return $term_info;
            }
            $term_ids[] = $term_info['term_id'];
            $tt_id      = $term_info['term_taxonomy_id'];
            $tt_ids[]   = $tt_id;

            if ( $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id = %d", $object_id, $tt_id ) ) ) {
                continue;
            }

            /**
             * Fires immediately before an object-term relationship is added.
             *
             * @since 2.9.0
             * @since 4.7.0 Added the `$taxonomy` parameter.
             *
             * @param int    $object_id Object ID.
             * @param int    $tt_id     Term taxonomy ID.
             * @param string $taxonomy  Taxonomy slug.
             */
            do_action( 'add_term_relationship', $object_id, $tt_id, $taxonomy );
            $wpdb->insert(
                $wpdb->term_relationships,
                array(
                    'object_id'        => $object_id,
                    'term_taxonomy_id' => $tt_id,
                )
            );

            /**
             * Fires immediately after an object-term relationship is added.
             *
             * @since 2.9.0
             * @since 4.7.0 Added the `$taxonomy` parameter.
             *
             * @param int    $object_id Object ID.
             * @param int    $tt_id     Term taxonomy ID.
             * @param string $taxonomy  Taxonomy slug.
             */
            do_action( 'added_term_relationship', $object_id, $tt_id, $taxonomy );
            $new_tt_ids[] = $tt_id;
        }

        if ( $new_tt_ids ) {
            wp_update_term_count( $new_tt_ids, $taxonomy );
        }

        if ( ! $append ) {
            $delete_tt_ids = array_diff( $old_tt_ids, $tt_ids );

            if ( $delete_tt_ids ) {
                $in_delete_tt_ids = "'" . implode( "', '", $delete_tt_ids ) . "'";
                $delete_term_ids  = $wpdb->get_col( $wpdb->prepare( "SELECT tt.term_id FROM $wpdb->term_taxonomy AS tt WHERE tt.taxonomy = %s AND tt.term_taxonomy_id IN ($in_delete_tt_ids)", $taxonomy ) );
                $delete_term_ids  = array_map( 'intval', $delete_term_ids );

                $remove = wp_remove_object_terms( $object_id, $delete_term_ids, $taxonomy );
                if ( is_wp_error( $remove ) ) {
                    return $remove;
                }
            }
        }

        $t = get_taxonomy( $taxonomy );
        if ( ! $append && isset( $t->sort ) && $t->sort ) {
            $values       = array();
            $term_order   = 0;
            $final_tt_ids = wp_get_object_terms(
                $object_id,
                $taxonomy,
                array(
                    'fields'                 => 'tt_ids',
                    'update_term_meta_cache' => false,
                )
            );
            foreach ( $tt_ids as $tt_id ) {
                if ( in_array( $tt_id, $final_tt_ids ) ) {
                    $values[] = $wpdb->prepare( '(%d, %d, %d)', $object_id, $tt_id, ++$term_order );
                }
            }
            if ( $values ) {
                if ( false === $wpdb->query( "INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id, term_order) VALUES " . join( ',', $values ) . ' ON DUPLICATE KEY UPDATE term_order = VALUES(term_order)' ) ) {
                    return new WP_Error( 'db_insert_error', __( 'Could not insert term relationship into the database.' ), $wpdb->last_error );
                }
            }
        }

        wp_cache_delete( $object_id, $taxonomy . '_relationships' );
        wp_cache_delete( 'last_changed', 'terms' );

        /**
         * Fires after an object's terms have been set.
         *
         * @since 2.8.0
         *
         * @param int    $object_id  Object ID.
         * @param array  $terms      An array of object terms.
         * @param array  $tt_ids     An array of term taxonomy IDs.
         * @param string $taxonomy   Taxonomy slug.
         * @param bool   $append     Whether to append new terms to the old terms.
         * @param array  $old_tt_ids Old array of term taxonomy IDs.
         */
        do_action( 'set_object_terms', $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids );
        return $tt_ids;
    }
}