<?php
/**
 * REST API: WP_REST_Meta_Fields class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.7.0
 */

/**
 * Core class to manage meta values for an object via the REST API.
 *
 * @since 4.7.0
 */
abstract class WP_REST_Meta_Fields {

	/**
	 * Retrieves the object meta type.
	 *
	 * @since 4.7.0
	 * @access protected
	 *
	 * @return string One of 'post', 'comment', 'term', 'user', or anything
	 *                else supported by `_get_meta_table()`.
	 */
	abstract protected function get_meta_type();

	/**
	 * Retrieves the object type for register_rest_field().
	 *
	 * @since 4.7.0
	 * @access protected
	 *
	 * @return string The REST field type, such as post type name, taxonomy name, 'comment', or `user`.
	 */
	abstract protected function get_rest_field_type();

	/**
	 * Registers the meta field.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @see register_rest_field()
	 */
	public function register_field() {
		register_rest_field( $this->get_rest_field_type(), 'meta', array(
			'get_callback'    => array( $this, 'get_value' ),
			'update_callback' => array( $this, 'update_value' ),
			'schema'          => $this->get_field_schema(),
		));
	}

	/**
	 * Retrieves the meta field value.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param int             $object_id Object ID to fetch meta for.
	 * @param WP_REST_Request $request   Full details about the request.
	 * @return WP_Error|object Object containing the meta values by name, otherwise WP_Error object.
	 */
	public function get_value( $object_id, $request ) {
		$fields   = $this->get_registered_fields();
		$response = array();

		foreach ( $fields as $name => $args ) {
			$all_values = get_metadata( $this->get_meta_type(), $object_id, $name, false );
			if ( $args['single'] ) {
				if ( empty( $all_values ) ) {
					$value = $args['schema']['default'];
				} else {
					$value = $all_values[0];
				}
				$value = $this->prepare_value_for_response( $value, $request, $args );
			} else {
				$value = array();
				foreach ( $all_values as $row ) {
					$value[] = $this->prepare_value_for_response( $row, $request, $args );
				}
			}

			$response[ $name ] = $value;
		}

		return (object) $response;
	}

	/**
	 * Prepares a meta value for a response.
	 *
	 * This is required because some native types cannot be stored correctly
	 * in the database, such as booleans. We need to cast back to the relevant
	 * type before passing back to JSON.
	 *
	 * @since 4.7.0
	 * @access protected
	 *
	 * @param mixed           $value   Meta value to prepare.
	 * @param WP_REST_Request $request Current request object.
	 * @param array           $args    Options for the field.
	 * @return mixed Prepared value.
	 */
	protected function prepare_value_for_response( $value, $request, $args ) {
		if ( ! empty( $args['prepare_callback'] ) ) {
			$value = call_user_func( $args['prepare_callback'], $value, $request, $args );
		}

		return $value;
	}

	/**
	 * Updates meta values.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param WP_REST_Request $request   Full details about the request.
	 * @param int             $object_id Object ID to fetch meta for.
	 * @return WP_Error|null WP_Error if one occurs, null on success.
	 */
	public function update_value( $request, $object_id ) {
		$fields = $this->get_registered_fields();
		foreach ( $fields as $name => $args ) {
			if ( ! array_key_exists( $name, $request ) ) {
				continue;
			}

			/*
			 * A null value means reset the field, which is essentially deleting it
			 * from the database and then relying on the default value.
			 */
			if ( is_null( $request[ $name ] ) ) {
				$result = $this->delete_meta_value( $object_id, $name );
			} elseif ( $args['single'] ) {
				$result = $this->update_meta_value( $object_id, $name, $request[ $name ] );
			} else {
				$result = $this->update_multi_meta_value( $object_id, $name, $request[ $name ] );
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * Deletes a meta value for an object.
	 *
	 * @since 4.7.0
	 * @access protected
	 *
	 * @param int    $object_id Object ID the field belongs to.
	 * @param string $name      Key for the field.
	 * @return bool|WP_Error True if meta field is deleted, WP_Error otherwise.
	 */
	protected function delete_meta_value( $object_id, $name ) {
		$meta_type = $this->get_meta_type();
		if ( ! current_user_can( "delete_{$meta_type}_meta", $object_id, $name ) ) {
			return new WP_Error(
				'rest_cannot_delete',
				sprintf( __( 'You do not have permission to edit the %s custom field.' ), $name ),
				array( 'key' => $name, 'status' => rest_authorization_required_code() )
			);
		}

		if ( ! delete_metadata( $meta_type, $object_id, wp_slash( $name ) ) ) {
			return new WP_Error(
				'rest_meta_database_error',
				__( 'Could not delete meta value from database.' ),
				array( 'key' => $name, 'status' => WP_Http::INTERNAL_SERVER_ERROR )
			);
		}

		return true;
	}

	/**
	 * Updates multiple meta values for an object.
	 *
	 * Alters the list of values in the database to match the list of provided values.
	 *
	 * @since 4.7.0
	 * @access protected
	 *
	 * @param int    $object_id Object ID to update.
	 * @param string $name      Key for the custom field.
	 * @param array  $values    List of values to update to.
	 * @return bool|WP_Error True if meta fields are updated, WP_Error otherwise.
	 */
	protected function update_multi_meta_value( $object_id, $name, $values ) {
		$meta_type = $this->get_meta_type();
		if ( ! current_user_can( "edit_{$meta_type}_meta", $object_id, $name ) ) {
			return new WP_Error(
				'rest_cannot_update',
				sprintf( __( 'You do not have permission to edit the %s custom field.' ), $name ),
				array( 'key' => $name, 'status' => rest_authorization_required_code() )
			);
		}

		$current = get_metadata( $meta_type, $object_id, $name, false );

		$to_remove = $current;
		$to_add    = $values;

		foreach ( $to_add as $add_key => $value ) {
			$remove_keys = array_keys( $to_remove, $value, true );

			if ( empty( $remove_keys ) ) {
				continue;
			}

			if ( count( $remove_keys ) > 1 ) {
				// To remove, we need to remove first, then add, so don't touch.
				continue;
			}

			$remove_key = $remove_keys[0];

			unset( $to_remove[ $remove_key ] );
			unset( $to_add[ $add_key ] );
		}

		// `delete_metadata` removes _all_ instances of the value, so only call once.
		$to_remove = array_unique( $to_remove );

		foreach ( $to_remove as $value ) {
			if ( ! delete_metadata( $meta_type, $object_id, wp_slash( $name ), wp_slash( $value ) ) ) {
				return new WP_Error(
					'rest_meta_database_error',
					__( 'Could not update meta value in database.' ),
					array( 'key' => $name, 'status' => WP_Http::INTERNAL_SERVER_ERROR )
				);
			}
		}

		foreach ( $to_add as $value ) {
			if ( ! add_metadata( $meta_type, $object_id, wp_slash( $name ), wp_slash( $value ) ) ) {
				return new WP_Error(
					'rest_meta_database_error',
					__( 'Could not update meta value in database.' ),
					array( 'key' => $name, 'status' => WP_Http::INTERNAL_SERVER_ERROR )
				);
			}
		}

		return true;
	}

	/**
	 * Updates a meta value for an object.
	 *
	 * @since 4.7.0
	 * @access protected
	 *
	 * @param int    $object_id Object ID to update.
	 * @param string $name      Key for the custom field.
	 * @param mixed  $value     Updated value.
	 * @return bool|WP_Error True if the meta field was updated, WP_Error otherwise.
	 */
	protected function update_meta_value( $object_id, $name, $value ) {
		$meta_type = $this->get_meta_type();
		if ( ! current_user_can(  "edit_{$meta_type}_meta", $object_id, $name ) ) {
			return new WP_Error(
				'rest_cannot_update',
				sprintf( __( 'You do not have permission to edit the %s custom field.' ), $name ),
				array( 'key' => $name, 'status' => rest_authorization_required_code() )
			);
		}

		$meta_key   = wp_slash( $name );
		$meta_value = wp_slash( $value );

		// Do the exact same check for a duplicate value as in update_metadata() to avoid update_metadata() returning false.
		$old_value = get_metadata( $meta_type, $object_id, $meta_key );

		if ( 1 === count( $old_value ) ) {
			if ( $old_value[0] === $meta_value ) {
				return true;
			}
		}

		if ( ! update_metadata( $meta_type, $object_id, $meta_key, $meta_value ) ) {
			return new WP_Error(
				'rest_meta_database_error',
				__( 'Could not update meta value in database.' ),
				array( 'key' => $name, 'status' => WP_Http::INTERNAL_SERVER_ERROR )
			);
		}

		return true;
	}

	/**
	 * Retrieves all the registered meta fields.
	 *
	 * @since 4.7.0
	 * @access protected
	 *
	 * @return array Registered fields.
	 */
	protected function get_registered_fields() {
		$registered = array();

		foreach ( get_registered_meta_keys( $this->get_meta_type() ) as $name => $args ) {
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}

			$rest_args = array();

			if ( is_array( $args['show_in_rest'] ) ) {
				$rest_args = $args['show_in_rest'];
			}

			$default_args = array(
				'name'             => $name,
				'single'           => $args['single'],
				'schema'           => array(),
				'prepare_callback' => array( $this, 'prepare_value' ),
			);

			$default_schema = array(
				'type'        => null,
				'description' => empty( $args['description'] ) ? '' : $args['description'],
				'default'     => isset( $args['default'] ) ? $args['default'] : null,
			);

			$rest_args = array_merge( $default_args, $rest_args );
			$rest_args['schema'] = array_merge( $default_schema, $rest_args['schema'] );

			if ( empty( $rest_args['schema']['type'] ) ) {
				// Skip over meta fields that don't have a defined type.
				if ( empty( $args['type'] ) ) {
					continue;
				}

				if ( $rest_args['single'] ) {
					$rest_args['schema']['type'] = $args['type'];
				} else {
					$rest_args['schema']['type'] = 'array';
					$rest_args['schema']['items'] = array(
						'type' => $args['type'],
					);
				}
			}

			$registered[ $rest_args['name'] ] = $rest_args;
		}

		return $registered;
	}

	/**
	 * Retrieves the object's meta schema, conforming to JSON Schema.
	 *
	 * @since 4.7.0
	 * @access protected
	 *
	 * @return array Field schema data.
	 */
	public function get_field_schema() {
		$fields = $this->get_registered_fields();

		$schema = array(
			'description' => __( 'Meta fields.' ),
			'type'        => 'object',
			'context'     => array( 'view', 'edit' ),
			'properties'  => array(),
		);

		foreach ( $fields as $key => $args ) {
			$schema['properties'][ $key ] = $args['schema'];
		}

		return $schema;
	}

	/**
	 * Prepares a meta value for output.
	 *
	 * Default preparation for meta fields. Override by passing the
	 * `prepare_callback` in your `show_in_rest` options.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param mixed           $value   Meta value from the database.
	 * @param WP_REST_Request $request Request object.
	 * @param array           $args    REST-specific options for the meta key.
	 * @return mixed Value prepared for output. If a non-JsonSerializable object, null.
	 */
	public static function prepare_value( $value, $request, $args ) {
		$type = $args['schema']['type'];

		// For multi-value fields, check the item type instead.
		if ( 'array' === $type && ! empty( $args['schema']['items']['type'] ) ) {
			$type = $args['schema']['items']['type'];
		}

		switch ( $type ) {
			case 'string':
				$value = (string) $value;
				break;
			case 'integer':
				$value = (int) $value;
				break;
			case 'number':
				$value = (float) $value;
				break;
			case 'boolean':
				$value = (bool) $value;
				break;
		}

		// Don't allow objects to be output.
		if ( is_object( $value ) && ! ( $value instanceof JsonSerializable ) ) {
			return null;
		}

		return $value;
	}
}
