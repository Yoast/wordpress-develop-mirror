<?php

namespace WP\Helper;

class LabelHelper{

    /**
     * Return labels from a custom object
     */
    public static function getCustomObjectLabels( $object, $nohier_vs_hier_defaults ) {
        $object->labels = (array) $object->labels;

        if ( isset( $object->label ) && empty( $object->labels['name'] ) ) {
            $object->labels['name'] = $object->label;
        }

        if ( ! isset( $object->labels['singular_name'] ) && isset( $object->labels['name'] ) ) {
            $object->labels['singular_name'] = $object->labels['name'];
        }

        if ( ! isset( $object->labels['name_admin_bar'] ) ) {
            $object->labels['name_admin_bar'] = isset( $object->labels['singular_name'] ) ? $object->labels['singular_name'] : $object->name;
        }

        if ( ! isset( $object->labels['menu_name'] ) && isset( $object->labels['name'] ) ) {
            $object->labels['menu_name'] = $object->labels['name'];
        }

        if ( ! isset( $object->labels['all_items'] ) && isset( $object->labels['menu_name'] ) ) {
            $object->labels['all_items'] = $object->labels['menu_name'];
        }

        if ( ! isset( $object->labels['archives'] ) && isset( $object->labels['all_items'] ) ) {
            $object->labels['archives'] = $object->labels['all_items'];
        }

        $defaults = array();
        foreach ( $nohier_vs_hier_defaults as $key => $value ) {
            $defaults[ $key ] = $object->hierarchical ? $value[1] : $value[0];
        }
        $labels         = array_merge( $defaults, $object->labels );
        $object->labels = (object) $object->labels;

        return (object) $labels;
    }
}