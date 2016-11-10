/* global ajaxurl, attachMediaBoxL10n, _wpMediaGridSettings, showNotice */

/**
 * @summary Creates a dialog containing posts that can have a particular media attached to it.
 *
 * Creates a dialog and retrieves all the posts that a particular media item can be attached to.
 *
 * @since 2.7
 *
 * @global
 * @namespace
 *
 * @requires jQuery
 */
var findPosts;

( function( $ ){
	findPosts = {
		/**
		 * @summary Opens a dialog to attach media to a post.
		 *
		 * Adds an overlay prior to retrieving a list of posts to attach the media to.
		 *
		 * @since 2.7
		 *
		 * @memberOf findPosts
		 *
		 * @param {string} af_name The name of the affected element.
		 * @param {string} af_val The value of the affected post element.
		 * @returns {boolean} Always returns false.
		 */
		open: function( af_name, af_val ) {
			var overlay = $( '.ui-find-overlay' );

			if ( overlay.length === 0 ) {
				$( 'body' ).append( '<div class="ui-find-overlay"></div>' );
				findPosts.overlay();
			}

			overlay.show();

			if ( af_name && af_val ) {
				// #affected is a hidden input field in the dialog that keeps track of which media should be attached.
				$( '#affected' ).attr( 'name', af_name ).val( af_val );
			}

			$( '#find-posts' ).show();

			/**
			 * Close the dialog when the escape key is pressed.
			 */
			$('#find-posts-input').focus().keyup( function( event ){
				if ( event.which == 27 ) {
					findPosts.close();
				}
			});

			// Show list of applicable posts for media attachment.
			findPosts.send();

			return false;
		},

		/**
		 * @summary Clears the found posts lists before hiding the attach media dialog.
		 *
		 * @since 2.7
		 *
		 * @memberOf findPosts
		 *
		 * @returns {void}
		 */
		close: function() {
			$('#find-posts-response').empty();
			$('#find-posts').hide();
			$( '.ui-find-overlay' ).hide();
		},

		/**
		 * @summary Binds a click event listener to the overlay which closes the attach media dialog.
		 *
		 * @since 3.5
		 *
		 * @memberOf findPosts
		 *
		 * @returns {void}
		 */
		overlay: function() {
			$( '.ui-find-overlay' ).on( 'click', function () {
				findPosts.close();
			});
		},

		/**
		 * @summary Retrieves and displays posts based on the search term.
		 *
		 * Sends a post request to the admin_ajax.php requesting posts based on the search term provided by the user.
		 * Defaults to all posts if no search term is provided.
		 *
		 * @since 2.7
		 *
		 * @memberOf findPosts
		 *
		 * @returns {void}
		 */
		send: function() {
			var post = {
					ps: $( '#find-posts-input' ).val(),
					action: 'find_posts',
					_ajax_nonce: $('#_ajax_nonce').val()
				},
				spinner = $( '.find-box-search .spinner' );

			spinner.addClass( 'is-active' );

			/**
			 * Send a POST request to admin_ajax.php, hide the spinner and replace the list of posts with the response data.
			 * If an error occurs, display it.
			 */
			$.ajax( ajaxurl, {
				type: 'POST',
				data: post,
				dataType: 'json'
			}).always( function() {
				spinner.removeClass( 'is-active' );
			}).done( function( x ) {
				if ( ! x.success ) {
					$( '#find-posts-response' ).text( attachMediaBoxL10n.error );
				}

				$( '#find-posts-response' ).html( x.data );
			}).fail( function() {
				$( '#find-posts-response' ).text( attachMediaBoxL10n.error );
			});
		}
	};

	/**
	 * @summary Initializes the file once the DOM is fully loaded and attaches events to the various form elements.
	 *
	 * @returns {void}
	 */
	$( document ).ready( function() {
		var settings, $mediaGridWrap = $( '#wp-media-grid' );

		// Open up a manage media frame into the grid.
		if ( $mediaGridWrap.length && window.wp && window.wp.media ) {
			settings = _wpMediaGridSettings;

			window.wp.media({
				frame: 'manage',
				container: $mediaGridWrap,
				library: settings.queryVars
			}).open();
		}

		// Prevent form submission if no post has been selected.
		$( '#find-posts-submit' ).click( function( event ) {
			if ( ! $( '#find-posts-response input[type="radio"]:checked' ).length )
				event.preventDefault();
		});

		// When hitting the enter key in the search input, submit the search query.
		$( '#find-posts .find-box-search :input' ).keypress( function( event ) {
			if ( 13 == event.which ) {
				findPosts.send();
				return false;
			}
		});

		// Bind the search button on click event.
		$( '#find-posts-search' ).click( findPosts.send );

		// Bind the close dialog click event.
		$( '#find-posts-close' ).click( findPosts.close );

		// Bind the bulk action events to the submit buttons.
		$( '#doaction, #doaction2' ).click( function( event ) {

			// Get all select elements for bulk actions that have a name starting with `action` and handle its action based on its value.
			$( 'select[name^="action"]' ).each( function() {
				var optionValue = $( this ).val();

				if ( 'attach' === optionValue ) {
					event.preventDefault();
					findPosts.open();
				} else if ( 'delete' === optionValue ) {
					if ( ! showNotice.warn() ) {
						event.preventDefault();
					}
				}
			});
		});

		/**
		 * @summary Enables a whole row to be clickable inside the post finder.
		 *
		 * @returns {void}
		 */
		$( '.find-box-inside' ).on( 'click', 'tr', function() {
			$( this ).find( '.found-radio input' ).prop( 'checked', true );
		});
	});
})( jQuery );
