var View = wp.media.View,
	UploaderStatus = wp.media.view.UploaderStatus,
	l10n = wp.media.view.l10n,
	$ = jQuery,
	Cropper;

Cropper = View.extend(/** @lends wp.media.view.Cropper.prototype */{
	className: 'crop-content',
	template: wp.template('crop-content'),
	/**
	 * Uses the imgAreaSelect plugin to allow a user to crop an image.
	 *
	 * @since 3.9.0
	 * @access public
	 *
	 * @augments wp.media.View
	 * @augments wp.Backbone.View
	 * @augments Backbone.View
	 *
	 * @memberof wp.media.view
	 *
	 * @see   imgAreaSelect plugin
	 * @link  https://github.com/odyniec/imgareaselect
	 * @fires wp.media.view.Cropper#image-loaded
	 */
	initialize: function() {
		_.bindAll(this, 'onImageLoad');
	},
	/**
	 * Listens for image load or a resize on the window to run our onImageLoad function with a limit of once per 250ms.
	 *
	 * @since  3.9.0
	 * @access private
	 */
	ready: function() {
		this.controller.frame.on('content:error:crop', this.onError, this);
		this.$image = this.$el.find('.crop-image');
		this.$image.on('load', this.onImageLoad);
		$(window).on('resize.cropper', _.debounce(this.onImageLoad, 250));
	},
	/**
	 * Remove event listeners and our element.
	 *
	 * @since  3.9.0
	 * @access private
	 */
	remove: function() {
		$(window).off('resize.cropper');
		this.$el.remove();
		this.$el.off();
		View.prototype.remove.apply(this, arguments);
	},
	/**
	 * Prepares the title and url for our image.
	 *
	 * @since  3.9.0
	 * @access private
	 */
	prepare: function() {
		return {
			title: l10n.cropYourImage,
			url: this.options.attachment.get('url')
		};
	},
	/**
	 * Initializes the imgAreaSelect plugin and triggers the 'image-loaded' event.
	 *
	 * @since  3.9.0
	 * @access private
	 */
	onImageLoad: function() {
		var imgOptions = this.controller.get('imgSelectOptions'),
			imgSelect;

		if (typeof imgOptions === 'function') {
			imgOptions = imgOptions(this.options.attachment, this.controller);
		}

		imgOptions = _.extend(imgOptions, {
			parent: this.$el,
			onInit: function() {

				// Store the set ratio.
				var setRatio = imgSelect.getOptions().aspectRatio;

				// On mousedown, if no ratio is set and the Shift key is down, use a 1:1 ratio.
				this.parent.children().on( 'mousedown touchstart', function( e ) {

					// If no ratio is set and the shift key is down, use a 1:1 ratio.
					if ( ! setRatio && e.shiftKey ) {
						imgSelect.setOptions( {
							aspectRatio: '1:1'
						} );
					}
				} );

				this.parent.children().on( 'mouseup touchend', function() {

					// Restore the set ratio.
					imgSelect.setOptions( {
						aspectRatio: setRatio ? setRatio : false
					} );
				} );
			}
		} );
		this.trigger('image-loaded');
		imgSelect = this.controller.imgSelect = this.$image.imgAreaSelect(imgOptions);
	},
	/**
	 * Creates a UploaderStatusError and adds it to the top of the .upload-errors element.
	 *
	 * @since  3.9.0
	 * @access private
	 *
	 * @see  wp.media.view.UploaderStatusError
	 */
	onError: function() {
		var filename = this.options.attachment.get('filename');

		this.views.add( '.upload-errors', new wp.media.view.UploaderStatusError({
			filename: UploaderStatus.prototype.filename(filename),
			message: window._wpMediaViewsL10n.cropError
		}), { at: 0 });
	}
});

module.exports = Cropper;
