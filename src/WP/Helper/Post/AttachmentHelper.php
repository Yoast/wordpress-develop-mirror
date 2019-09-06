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

}