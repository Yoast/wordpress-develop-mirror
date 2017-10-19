var Controller = wp.media.controller,
	CustomizeImageCropper;

/**
 * wp.media.controller.CustomizeImageCropper
 *
 * @summary Prepares the image to get cropped.
 *
 * Checks whether the image needs to be cropped according to a ratio.
 *
 * @since 4.3
 *
 * @memberOf wp.media.controller
 *
 * @class
 * @augments wp.media.controller.Cropper
 * @augments wp.media.controller.State
 * @augments Backbone.Model
 *
 * @returns ajax post Call to Ajax to crop the image according to the crop details.
 */
CustomizeImageCropper = Controller.Cropper.extend(/** @lends wp.media.controller.CustomizeImageCropper.prototype */{
	doCrop: function( attachment ) {
		var cropDetails = attachment.get( 'cropDetails' ),
			control = this.get( 'control' ),
			ratio = cropDetails.width / cropDetails.height;

		// Use crop measurements when flexible in both directions.
		if ( control.params.flex_width && control.params.flex_height ) {
			cropDetails.dst_width  = cropDetails.width;
			cropDetails.dst_height = cropDetails.height;

		// Constrain flexible side based on image ratio and size of the fixed side.
		} else {
			cropDetails.dst_width  = control.params.flex_width  ? control.params.height * ratio : control.params.width;
			cropDetails.dst_height = control.params.flex_height ? control.params.width  / ratio : control.params.height;
		}

		return wp.ajax.post( 'crop-image', {
			wp_customize: 'on',
			nonce: attachment.get( 'nonces' ).edit,
			id: attachment.get( 'id' ),
			context: control.id,
			cropDetails: cropDetails
		} );
	}
});

module.exports = CustomizeImageCropper;
