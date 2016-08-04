/* global wpActiveEditor, tinymce, QTags */

/**
 * Updates the ThickBox anchor href and the ThickBox's own properties in order to set the size and position on every resize event.
 * Also adds a function to send HTML or text to the currently active editor.
 *
 * @summary Makes sure the ThickBox dimensions are properly set and adds functionality to pass HTML to the editor.
 *
 * @since 2.3.3
 *
 * @requires jQuery
 */


var wpActiveEditor, send_to_editor;

/**
 * @summary Sends the HTML passed in the parameters to TinyMCE.
 *
 * @since 2.3.3
 *
 * @global {tinymce} tinymce An instance of the TinyMCE editor.
 * @global {QTags} QTags An instance of QTags.
 *
 * @param {string} html The HTML/text to be sent to the editor.
 * @return {boolean} Returns false when both TinyMCE and QTags instances are unavailable. This means that the HTML was not sent to the editor.
 */
send_to_editor = function( html ) {
	var editor,
		hasTinymce = typeof tinymce !== 'undefined',
		hasQuicktags = typeof QTags !== 'undefined';

	// If no active editor is set, try to set it.
	if ( ! wpActiveEditor ) {
		if ( hasTinymce && tinymce.activeEditor ) {
			editor = tinymce.activeEditor;
			wpActiveEditor = editor.id;
		} else if ( ! hasQuicktags ) {
			return false;
		}
	} else if ( hasTinymce ) {
		editor = tinymce.get( wpActiveEditor );
	}

	// If the editor is set and not hidden, insert the HTML into the content of the editor.
	if ( editor && ! editor.isHidden() ) {
		editor.execCommand( 'mceInsertContent', false, html );
	} else if ( hasQuicktags ) {
		QTags.insertContent( html );
	} else {
		document.getElementById( wpActiveEditor ).value += html;
	}

	// If the old thickbox remove function exists, call it.
	if ( window.tb_remove ) {
		try { window.tb_remove(); } catch( e ) {}
	}
};

// ThickBox positioning.
var tb_position;
(function($) {
	/**
	 * @summary Recalculates and applies the new ThickBox position based on the current window size.
	 *
	 * @since 2.6.0
	 *
	 * @return {HTMLElements[]} Array containing all the found ThickBox anchors.
	 */
	tb_position = function() {
		var tbWindow = $('#TB_window'),
			width = $(window).width(),
			H = $(window).height(),
			W = ( 833 < width ) ? 833 : width,
			adminbar_height = 0;

		if ( $('#wpadminbar').length ) {
			adminbar_height = parseInt( $('#wpadminbar').css('height'), 10 );
		}

		if ( tbWindow.length ) {
			tbWindow.width( W - 50 ).height( H - 45 - adminbar_height );
			$('#TB_iframeContent').width( W - 50 ).height( H - 75 - adminbar_height );
			tbWindow.css({'margin-left': '-' + parseInt( ( ( W - 50 ) / 2 ), 10 ) + 'px'});
			if ( typeof document.body.style.maxWidth !== 'undefined' )
				tbWindow.css({'top': 20 + adminbar_height + 'px', 'margin-top': '0'});
		}

		/**
		 * @summary Removes any width and height parameters from the href of any anchor with the ThickBox class and recalculates the new height and width values.
		 *
		 * @since 2.6.0
		 */
		return $('a.thickbox').each( function() {
			var href = $(this).attr('href');
			if ( ! href ) return;
			href = href.replace(/&width=[0-9]+/g, '');
			href = href.replace(/&height=[0-9]+/g, '');
			$(this).attr( 'href', href + '&width=' + ( W - 80 ) + '&height=' + ( H - 85 - adminbar_height ) );
		});
	};

	/**
	 * Recalculates the ThickBox position on every window resize.
	 */
	$(window).resize(function(){ tb_position(); });

})(jQuery);
