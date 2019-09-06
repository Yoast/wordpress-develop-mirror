<?php

namespace WP\Helper\Post;

class AttachmentHelper{


    /**
     * Retrieve attached file path based on attachment ID.
     */
    public static function getAttachedFile( $attachment_id, $unfiltered = false  ) {
        $file = get_post_meta( $attachment_id, '_wp_attached_file', true );

        // If the file is relative, prepend upload dir.
        if ( $file && 0 !== strpos( $file, '/' ) && ! preg_match( '|^.:\\\|', $file ) ) {
            $uploads = wp_get_upload_dir();
            if ( false === $uploads['error'] ) {
                $file = $uploads['basedir'] . "/$file";
            }
        }

        if ( $unfiltered ) {
            return $file;
        }

        /**
         * Filters the attached file based on the given ID.
         *
         * @since 2.1.0
         *
         * @param string $file          Path to attached file.
         * @param int    $attachment_id Attachment ID.
         */
        return apply_filters( 'get_attached_file', $file, $attachment_id );
    }


    /**
     * Update attachment file path based on attachment ID.
     */
    public static function updateAttachedFile( $attachment_id, $file ) {
        if ( ! get_post( $attachment_id ) ) {
            return false;
        }

        /**
         * Filters the path to the attached file to update.
         *
         * @since 2.1.0
         *
         * @param string $file          Path to the attached file to update.
         * @param int    $attachment_id Attachment ID.
         */
        $file = apply_filters( 'update_attached_file', $file, $attachment_id );

        $file = _wp_relative_upload_path( $file );
        if ( $file ) {
            return update_post_meta( $attachment_id, '_wp_attached_file', $file );
        } else {
            return delete_post_meta( $attachment_id, '_wp_attached_file' );
        }
    }

    /**
     * Return the mime-type
     */
    public static function getMimeType() {
        $post = get_post( $post );

        if ( is_object( $post ) ) {
            return $post->post_mime_type;
        }

        return false;
    }

    /**
     * Return relative path to an uploaded file.
     */
    public static function relativeUploadPath( $path ) {
        $new_path = $path;

        $uploads = wp_get_upload_dir();
        if ( 0 === strpos( $new_path, $uploads['basedir'] ) ) {
                $new_path = str_replace( $uploads['basedir'], '', $new_path );
                $new_path = ltrim( $new_path, '/' );
        }

        /**
         * Filters the relative path to an uploaded file.
         *
         * @since 2.9.0
         *
         * @param string $new_path Relative path to the file.
         * @param string $path     Full path to the file.
         */
        return apply_filters( '_wp_relative_upload_path', $new_path, $path );
    }

    /**
     * Get all mime types
     */
    public static function getMimeTypes() {
        $post_mime_types = array(   //	array( adj, noun )
            'image'       => array(
                __( 'Images' ),
                __( 'Manage Images' ),
                /* translators: %s: Number of images. */
                _n_noop(
                    'Image <span class="count">(%s)</span>',
                    'Images <span class="count">(%s)</span>'
                ),
            ),
            'audio'       => array(
                __( 'Audio' ),
                __( 'Manage Audio' ),
                /* translators: %s: Number of audio files. */
                _n_noop(
                    'Audio <span class="count">(%s)</span>',
                    'Audio <span class="count">(%s)</span>'
                ),
            ),
            'video'       => array(
                __( 'Video' ),
                __( 'Manage Video' ),
                /* translators: %s: Number of video files. */
                _n_noop(
                    'Video <span class="count">(%s)</span>',
                    'Video <span class="count">(%s)</span>'
                ),
            ),
            'document'    => array(
                __( 'Documents' ),
                __( 'Manage Documents' ),
                /* translators: %s: Number of documents. */
                _n_noop(
                    'Document <span class="count">(%s)</span>',
                    'Documents <span class="count">(%s)</span>'
                ),
            ),
            'spreadsheet' => array(
                __( 'Spreadsheets' ),
                __( 'Manage Spreadsheets' ),
                /* translators: %s: Number of spreadsheets. */
                _n_noop(
                    'Spreadsheet <span class="count">(%s)</span>',
                    'Spreadsheets <span class="count">(%s)</span>'
                ),
            ),
            'archive'     => array(
                __( 'Archives' ),
                __( 'Manage Archives' ),
                /* translators: %s: Number of archives. */
                _n_noop(
                    'Archive <span class="count">(%s)</span>',
                    'Archives <span class="count">(%s)</span>'
                ),
            ),
        );

        $ext_types  = wp_get_ext_types();
        $mime_types = wp_get_mime_types();

        foreach ( $post_mime_types as $group => $labels ) {
            if ( in_array( $group, array( 'image', 'audio', 'video' ) ) ) {
                continue;
            }

            if ( ! isset( $ext_types[ $group ] ) ) {
                unset( $post_mime_types[ $group ] );
                continue;
            }

            $group_mime_types = array();
            foreach ( $ext_types[ $group ] as $extension ) {
                foreach ( $mime_types as $exts => $mime ) {
                    if ( preg_match( '!^(' . $exts . ')$!i', $extension ) ) {
                        $group_mime_types[] = $mime;
                        break;
                    }
                }
            }
            $group_mime_types = implode( ',', array_unique( $group_mime_types ) );

            $post_mime_types[ $group_mime_types ] = $labels;
            unset( $post_mime_types[ $group ] );
        }

        /**
         * Filters the default list of post mime types.
         *
         * @since 2.5.0
         *
         * @param array $post_mime_types Default list of post mime types.
         */
        return apply_filters( 'post_mime_types', $post_mime_types );
    }

    /**
     * Match mime types
     */
    public static function matchMimeTypes( $wildcard_mime_types, $real_mime_types ) {
        $matches = array();
        if ( is_string( $wildcard_mime_types ) ) {
            $wildcard_mime_types = array_map( 'trim', explode( ',', $wildcard_mime_types ) );
        }
        if ( is_string( $real_mime_types ) ) {
            $real_mime_types = array_map( 'trim', explode( ',', $real_mime_types ) );
        }

        $patternses = array();
        $wild       = '[-._a-z0-9]*';

        foreach ( (array) $wildcard_mime_types as $type ) {
            $mimes = array_map( 'trim', explode( ',', $type ) );
            foreach ( $mimes as $mime ) {
                $regex                 = str_replace( '__wildcard__', $wild, preg_quote( str_replace( '*', '__wildcard__', $mime ) ) );
                $patternses[][ $type ] = "^$regex$";
                if ( false === strpos( $mime, '/' ) ) {
                    $patternses[][ $type ] = "^$regex/";
                    $patternses[][ $type ] = $regex;
                }
            }
        }
        asort( $patternses );

        foreach ( $patternses as $patterns ) {
            foreach ( $patterns as $type => $pattern ) {
                foreach ( (array) $real_mime_types as $real ) {
                    if ( preg_match( "#$pattern#", $real ) && ( empty( $matches[ $type ] ) || false === array_search( $real, $matches[ $type ] ) ) ) {
                        $matches[ $type ][] = $real;
                    }
                }
            }
        }
        return $matches;
    }

    /**
     * Find by mime types
     */
    public static function findByMimeType( $post_mime_types, $table_alias = '') {
        $where     = '';
        $wildcards = array( '', '%', '%/%' );
        if ( is_string( $post_mime_types ) ) {
            $post_mime_types = array_map( 'trim', explode( ',', $post_mime_types ) );
        }

        $wheres = array();

        foreach ( (array) $post_mime_types as $mime_type ) {
            $mime_type = preg_replace( '/\s/', '', $mime_type );
            $slashpos  = strpos( $mime_type, '/' );
            if ( false !== $slashpos ) {
                $mime_group    = preg_replace( '/[^-*.a-zA-Z0-9]/', '', substr( $mime_type, 0, $slashpos ) );
                $mime_subgroup = preg_replace( '/[^-*.+a-zA-Z0-9]/', '', substr( $mime_type, $slashpos + 1 ) );
                if ( empty( $mime_subgroup ) ) {
                    $mime_subgroup = '*';
                } else {
                    $mime_subgroup = str_replace( '/', '', $mime_subgroup );
                }
                $mime_pattern = "$mime_group/$mime_subgroup";
            } else {
                $mime_pattern = preg_replace( '/[^-*.a-zA-Z0-9]/', '', $mime_type );
                if ( false === strpos( $mime_pattern, '*' ) ) {
                    $mime_pattern .= '/*';
                }
            }

            $mime_pattern = preg_replace( '/\*+/', '%', $mime_pattern );

            if ( in_array( $mime_type, $wildcards ) ) {
                return '';
            }

            if ( false !== strpos( $mime_pattern, '%' ) ) {
                $wheres[] = empty( $table_alias ) ? "post_mime_type LIKE '$mime_pattern'" : "$table_alias.post_mime_type LIKE '$mime_pattern'";
            } else {
                $wheres[] = empty( $table_alias ) ? "post_mime_type = '$mime_pattern'" : "$table_alias.post_mime_type = '$mime_pattern'";
            }
        }
        if ( ! empty( $wheres ) ) {
            $where = ' AND (' . join( ' OR ', $wheres ) . ') ';
        }
        return $where;
    }


    /**
     * Count attachments
     */
    public static function count() {
        global $wpdb;

        $and   = wp_post_mime_type_where( $mime_type );
        $count = $wpdb->get_results( "SELECT post_mime_type, COUNT( * ) AS num_posts FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status != 'trash' $and GROUP BY post_mime_type", ARRAY_A );

        $counts = array();
        foreach ( (array) $count as $row ) {
            $counts[ $row['post_mime_type'] ] = $row['num_posts'];
        }
        $counts['trash'] = $wpdb->get_var( "SELECT COUNT( * ) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status = 'trash' $and" );

        /**
         * Modify returned attachment counts by mime type.
         *
         * @since 3.7.0
         *
         * @param object $counts    An object containing the attachment counts by
         *                          mime type.
         * @param string $mime_type The mime type pattern used to filter the attachments
         *                          counted.
         */
        return apply_filters( 'wp_count_attachments', (object) $counts, $mime_type );
    }
}