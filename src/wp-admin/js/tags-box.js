/* jshint curly: false, eqeqeq: false */
/* global ajaxurl */

var tagBox, array_unique_noempty;

( function( $ ) {

	var tagDelimiter = ( window.tagsSuggestL10n && window.tagsSuggestL10n.tagDelimiter ) || ',';

	/**
	 * Filters unique items and returns a new array.
	 *
	 * Filters all items from an array into a new array containing only the unique items.
	 * This also excludes whitespace or empty values.
	 *
	 * @since 4.2.0
	 *
	 * @global
	 *
	 * @param {Array} array The array to filter through.
	 *
	 * @return {Array} A new array containing only the unique items.
	 */
	array_unique_noempty = function( array ) {
		var out = [];

		$.each( array,
			// Trim the values and ensure they are unique.
			function( key, val ) {
				val = $.trim( val );

				if ( val && $.inArray( val, out ) === -1 ) {
					out.push( val );
				}
			}
		);

		return out;
	};

	/**
	 * The TagBox object.
	 *
	 * Contains functions to create and manage tags that can be associated with a post.
	 *
	 * @since 4.2.0
	 * @global
	 */
	tagBox = {

		/**
		 * Cleans up tags by removing redundant characters.
		 *
		 * Converting the tag delimiter to commas and by removing extra spaces and commas.
		 *
		 * @since 4.2.0
		 * @memberOf tagBox
		 *
		 * @param {string} tags The tags that need to be cleaned up.
		 *
		 * @return {string} The cleaned up tags.
		 */
		clean : function( tags ) {
			if ( ',' !== tagDelimiter ) {
				tags = tags.replace( new RegExp( tagDelimiter, 'g' ), ',' );
			}

			tags = tags.replace(/\s*,\s*/g, ',').replace(/,+/g, ',').replace(/[,\s]+$/, '').replace(/^[,\s]+/, '');

			if ( ',' !== tagDelimiter ) {
				tags = tags.replace( /,/g, tagDelimiter );
			}

			return tags;
		},

		/**
		 * Parses tags and makes them editable.
		 *
		 * @since 4.2.0
		 * @memberOf tagBox
		 *
		 * @param {Object} el The tag element to retrieve the ID from.
		 *
		 * @return {boolean} Always returns false.
		 */
		parseTags : function(el) {
			var id = el.id,
				num = id.split('-check-num-')[1],
				taxbox = $(el).closest('.tagsdiv'),
				thetags = taxbox.find('.the-tags'),
				current_tags = thetags.val().split( tagDelimiter ),
				new_tags = [];

			delete current_tags[num];

			// Sanitize the current tags and push them as if they're new tags.
			$.each( current_tags, function( key, val ) {
				val = $.trim( val );
				if ( val ) {
					new_tags.push( val );
				}
			} );

			thetags.val( this.clean( new_tags.join( tagDelimiter ) ) );

			this.quickClicks( taxbox );
			return false;
		},

		/**
		 * Creates clickable links, buttons and fields for adding or editing tags.
		 *
		 * @since 4.2.0
		 * @memberOf tagBox
		 *
		 * @param {Object} el The container HTML element.
		 *
		 * @return {void}
		 */
		quickClicks : function( el ) {
			var thetags = $('.the-tags', el),
				tagchecklist = $('.tagchecklist', el),
				id = $(el).attr('id'),
				current_tags, disabled;

			if ( ! thetags.length )
				return;

			disabled = thetags.prop('disabled');

			current_tags = thetags.val().split( tagDelimiter );
			tagchecklist.empty();

			/**
			 * Creates a delete button if tag editing is enabled, before adding it to the tag list.
			 *
			 * @since 4.2.0
			 * @memberOf tagBox
			 *
			 * @param {string} key The key value of the current tag.
			 * @param {string} val The value of the current tag.
			 *
			 * @return {void}
			 */
			$.each( current_tags, function( key, val ) {
				var listItem, xbutton;

				val = $.trim( val );

				if ( ! val )
					return;

				// Create a new list item, and ensure the text is properly escaped.
				listItem = $( '<li />' ).text( val );

				// If tags editing isn't disabled, create the X button.
				if ( ! disabled ) {
					/*
					 * Build the X buttons, hide the X icon with aria-hidden and
					 * use visually hidden text for screen readers.
					 */
					xbutton = $( '<button type="button" id="' + id + '-check-num-' + key + '" class="ntdelbutton">' +
						'<span class="remove-tag-icon" aria-hidden="true"></span>' +
						'<span class="screen-reader-text">' + window.tagsSuggestL10n.removeTerm + ' ' + listItem.html() + '</span>' +
						'</button>' );

					/**
					 * Handles the click and keypress event of a tag.
					 *
					 * Handles the click and keypress event when an event is called on a tag and re-parses the tags
					 * in the tag box.
					 * Sets the focus on the new tag field when the enter key is pressed.
					 *
					 * @since 4.2.0
					 *
					 * @param {Event} e The window event to handle.
					 *
					 * @return {void}
					 */
					xbutton.on( 'click keypress', function( e ) {
						// On click or when using the Enter/Spacebar keys.
						if ( 'click' === e.type || 13 === e.keyCode || 32 === e.keyCode ) {
							/*
							 * When using the keyboard, move focus back to the
							 * add new tag field. Note: when releasing the pressed
							 * key this will fire the `keyup` event on the input.
							 */
							if ( 13 === e.keyCode || 32 === e.keyCode ) {
 								$( this ).closest( '.tagsdiv' ).find( 'input.newtag' ).focus();
 							}

							tagBox.userAction = 'remove';
							tagBox.parseTags( this );
						}
					});

					listItem.prepend( '&nbsp;' ).prepend( xbutton );
				}

				// Append the list item to the tag list.
				tagchecklist.append( listItem );
			});

			// The buttons list is built now, give feedback to screen reader users.
			tagBox.screenReadersMessage();
		},

		/**
		 * Flushes tags on save and on delete.
		 *
		 * Flushes the tags to a hidden field as a comma separated list on save.
		 * Also ensures that the quick links are properly generated.
		 *
		 * @since 4.2.0
		 * @memberOf tagBox
		 *
		 * @param {Object} el The container HTML element.
		 * @param {Object|boolean} a Is either a link from the tag cloud or a hard set boolean value.
		 * @param {Number|boolean} f Determines whether or not focus should be applied to the input field.
		 *
		 * @return {boolean} Always returns false.
		 */
		flushTags : function( el, a, f ) {
			var tagsval, newtags, text,
				tags = $( '.the-tags', el ),
				newtag = $( 'input.newtag', el );

			a = a || false;

			text = a ? $(a).text() : newtag.val();

			/*
			 * Return if there's no new tag or if the input field is empty.
			 * Note: when using the keyboard to add tags, focus is moved back to
			 * the input field and the `keyup` event attached on this field will
			 * fire when releasing the pressed key. Checking also for the field
			 * emptiness avoids to set the tags and call quickClicks() again.
			 */
			if ( 'undefined' == typeof( text ) || '' === text ) {
				return false;
			}

			tagsval = tags.val();
			newtags = tagsval ? tagsval + tagDelimiter + text : text;

			newtags = this.clean( newtags );
			newtags = array_unique_noempty( newtags.split( tagDelimiter ) ).join( tagDelimiter );
			tags.val( newtags );
			this.quickClicks( el );

			if ( ! a )
				newtag.val('');
			if ( 'undefined' == typeof( f ) )
				newtag.focus();

			return false;
		},

		/**
		 * Retrieves the available tags and creates a tagcloud.
		 *
		 * Retrieves the available tags from the database and creates an interactive tagcloud.
		 * If a tag is clicked, the tagbox is flushed for the current taxonomy.
		 *
		 * @since 4.2.0
		 * @memberOf tagBox
		 *
		 * @param {string} id The ID to extract the indice from.
		 *
		 * @return {void}
		 */
		get : function( id ) {
			var tax = id.substr( id.indexOf('-') + 1 );

			/**
			 * Creates a tagcloud based on the AJAX request.
			 *
			 * Handles the AJAX request and creates a tagcloud.
			 * Also handles the flushing of the tagbox whenever an anchor or a tagcloud is clicked.
			 *
			 * @since 4.2.0
			 *
			 * @param {number|string} r The response message from the AJAX call. Can be numeric or a string.
			 * @param {string} stat The status code of the response.
			 *
			 * @return {void}
			 */
			$.post( ajaxurl, { 'action': 'get-tagcloud', 'tax': tax }, function( r, stat ) {
				if ( 0 === r || 'success' != stat ) {
					return;
				}

				r = $( '<div id="tagcloud-' + tax + '" class="the-tagcloud">' + r + '</div>' );

				/**
				 * Flushes the tagbox when an anchor is clicked.
				 *
				 * @since 4.2.0
				 *
				 * @return {boolean} Always returns false.
				 */
				$( 'a', r ).click( function() {
					tagBox.userAction = 'add';
					tagBox.flushTags( $( '#' + tax ), this );
					return false;
				});

				$( '#' + id ).after( r );
			});
		},

		/**
		 * Track the user's last action.
		 *
		 * @since 4.7.0
		 */
		userAction: '',

		/**
		 * Dispatches an audible message to screen readers.
		 *
		 * @since 4.7.0
		 *
		 * @return {void}
		 */
		screenReadersMessage: function() {
			var message;

			switch ( this.userAction ) {
				case 'remove':
					message = window.tagsSuggestL10n.termRemoved;
					break;

				case 'add':
					message = window.tagsSuggestL10n.termAdded;
					break;

				default:
					return;
			}

			window.wp.a11y.speak( message, 'assertive' );
		},

		/**
		 * Initializes the tags box by setting up the links, buttons. Also adds event handling.
		 *
		 * This includes handling of pressing the enter key in the input field and the retrieval of tag suggestions.
		 *
		 * @since 4.2.0
		 * @memberOf tagBox
		 *
		 * @return {void}
		 */
		init : function() {
			var ajaxtag = $('div.ajaxtag');

			$('.tagsdiv').each( function() {
				tagBox.quickClicks( this );
			});

			$( '.tagadd', ajaxtag ).click( function() {
				tagBox.userAction = 'add';
				tagBox.flushTags( $( this ).closest( '.tagsdiv' ) );
			});

			/**
			 * Handles the flushing of the tagbox when adding a new tag.
			 *
			 * Handles the flushing of the tagbox whenever the enter key is pressed in the new tag field.
			 *
			 * @since 4.2.0
			 *
			 * @param {Event} event The window event to handle.
			 *
			 * @return {void}
			 */
			$( 'input.newtag', ajaxtag ).keypress( function( event ) {
				if ( 13 == event.which ) {
					tagBox.userAction = 'add';
					tagBox.flushTags( $( this ).closest( '.tagsdiv' ) );
					event.preventDefault();
					event.stopPropagation();
				}
			}).keypress( function( event ) {
				if ( 13 == event.which ) {
					event.preventDefault();
					event.stopPropagation();
				}
			}).each( function( i, element ) {
				$( element ).wpTagsSuggest();
			});

			/**
			 * Flushes tags whenever the post is saved.
			 *
			 * @since 4.2.0
			 *
			 * @return {void}
			 */
			$('#post').submit(function(){
				$('div.tagsdiv').each( function() {
					tagBox.flushTags(this, false, 1);
				});
			});

			/**
			 * Unbinds the click event and toggles siblings.
			 *
			 * Unbinds the click event and toggles all sibling tagcloud elements when the tagcloud-link is clicked.
			 *
			 * @since 4.2.0
			 *
			 * @return {void}
			 */
			$('.tagcloud-link').click(function() {
				// On the first click, fetch the tag cloud and insert it in the DOM.
				tagBox.get( $( this ).attr( 'id' ) );
				// Update button state, remove previous click event and attach a new one to toggle the cloud.
				$( this )
					.attr( 'aria-expanded', 'true' )
					.unbind()
					.click( function() {
						$( this )
							.attr( 'aria-expanded', 'false' === $( this ).attr( 'aria-expanded' ) ? 'true' : 'false' )
							.siblings( '.the-tagcloud' ).toggle();
					});
			});
		}
	};
}( jQuery ));
