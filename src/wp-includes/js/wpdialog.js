/**
 * Wrap the jQuery UI Dialog open function to fix the TinyMCE focus.
 */
( function($) {
	$.widget('wp.wpdialog', $.ui.dialog, {
		open: function() {
			// Add beforeOpen event.
			if ( this.isOpen() || false === this._trigger('beforeOpen') ) {
				return;
			}

			// Open the dialog.
			this._super();
			// WebKit leaves focus in the TinyMCE editor unless we shift focus.
			this.element.focus();
			// Add refresh event.
			this._trigger('refresh');
		}
	});

	$.wp.wpdialog.prototype.options.closeOnEscape = false;

})(jQuery);
