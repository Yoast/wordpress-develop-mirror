/* jshint curly: false, eqeqeq: false */
/* global ajaxurl */

var tagBox, array_unique_noempty;

( function( $ ) {

	var tagDelimiter = ( window.tagsSuggestL10n && window.tagsSuggestL10n.tagDelimiter ) || ',';

	/**
	 * Filters all items from an array into a new array containing only the unique items.
	 * This also excludes whitespace or empty values.
	 *
	 * @summary Filters all items from an array into a new array containing only the unique items.
	 *
	 * @since 4.2
	 *
	 * @param {Array} array The array to filter through.
	 * @returns {Array} A new array containing only the unique items.
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
	 * @since 4.2
	 * @namespace
	 * @global
	 */
	tagBox = {

		/**
		 * Cleans up tags by converting the tag delimiter to comma's and by removing extra spaces and comma's.
		 *
		 * @summary Cleans up tags by removing redundant characters.
		 *
		 * @since 4.2
		 *
		 * @memberOf tagBox
		 *
		 * @param {string} tags The tags that need to be cleaned up.
		 * @returns {string} The cleaned up tags.
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
		 * Parses the tags and binds events to them to make them editable.
		 *
		 * @summary Parses tags and makes them editable.
		 *
		 * @since 4.2
		 *
		 * @memberOf tagBox
		 *
		 * @param {Object} el The tag element to retrieve the ID from.
		 * @returns {boolean} Always returns false.
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
		 * Creates all the links, buttons and fields for adding and editing tags.
		 *
		 * @summary Creates clickable links, buttons and fields for adding or editing tags.
		 *
		 * @since 4.2
		 *
		 * @memberOf tagBox
		 *
		 * @param {Object} el The container HTML element.
		 *
		 * @returns {void}
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
			 * Creates a new span for every available tag and creates a delete button if tag editing is enabled before
			 * adding it to the tag list.
			 *
			 * @summary Creates spans for every available tag and optionally adds a delete button.
			 *
	 		 * @since 4.2
			 *
			 * @memberOf tagBox
			 *
			 * @param key The key value of the current tag.
			 * @param val The value of the current tag.
			 *
			 * @returns {void}
			 */
			$.each( current_tags, function( key, val ) {
				var span, xbutton;

				val = $.trim( val );

				if ( ! val )
					return;

				/**
				 * Create a new span and set its text value.
				 * Setting the value via .text() ensures no additional HTML gets parsed into the element.
				 */
				span = $('<span />').text( val );

				// If tags editing isn't disabled, create the X button.
				if ( ! disabled ) {
					/*
					 * Build the X buttons, hide the X icon with aria-hidden and
					 * use visually hidden text for screen readers.
					 */
					xbutton = $( '<button type="button" id="' + id + '-check-num-' + key + '" class="ntdelbutton">' +
						'<span class="remove-tag-icon" aria-hidden="true"></span>' +
						'<span class="screen-reader-text">' + window.tagsSuggestL10n.removeTerm + ' ' + span.html() + '</span>' +
						'</button>' );

					/**
					 * Handles the click and keypress event when an event is called on a tag and reparses the tags
					 * in the tag box.
					 * Also sets the focus on the new tag field is the enter key is pressed.
					 *
					 * @summary Handles the click and keypress event when an event is called on a tag.
					 *
					 * @since 4.2
					 *
					 * @param {Event} e The window event to handle.
					 *
					 * @returns {void}
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
				}

				// Append the span to the tag list.
				tagchecklist.append( span );
			});

			// The buttons list is built now, give feedback to screen reader users.
			tagBox.screenReadersMessage();
		},

		/**
		 * Flushes the tags on save to a hidden field as a comma separated list.
		 * Also ensures that the quick links are properly generated.
		 *
		 * @summary Flushes tags on save and on delete.
		 *
		 * @since 4.2
		 *
		 * @memberOf tagBox
		 *
		 * @param {Object} el The container HTML element.
		 * @param {Object|boolean} a Is either a link from the tag cloud or a hard set boolean value.
		 * @param {*} f Determines whether or not focus should be applied to the input field.
		 * @returns {boolean} Always returns false.
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
		 * Retrieves the available tags from the database and forms a tagcloud.
		 * If a tag is clicked, the tagbox is flushed for the current taxonomy.
		 *
		 * @summary Retrieves the available tags from the database and creates an interactive tagcloud.
		 *
		 * @since 4.2
		 *
		 * @memberOf tagBox
		 *
		 * @param {string} id The ID to extract the indice from.
		 * @returns {void}
		 */
		get : function( id ) {
			var tax = id.substr( id.indexOf('-') + 1 );

			/**
			 * Handles the AJAX request and creates a tagcloud.
			 * Also handles the flushing of the tagbox whenever an anchor or a tagcloud is clicked.
			 *
			 * @summary Handles the AJAX request and creates a tagcloud.
			 *
			 * @since 4.2
			 *
			 * @param {number|string} r The response message from the AJAX call. Can be numeric or a string.
			 * @param {string} stat The status code of the response.
			 * @returns {void}
			 */
			$.post( ajaxurl, { 'action': 'get-tagcloud', 'tax': tax }, function( r, stat ) {
				if ( 0 === r || 'success' != stat ) {
					return;
				}

				r = $( '<p id="tagcloud-' + tax + '" class="the-tagcloud">' + r + '</p>' );

				/**
				 * Flushes the tagbox whenever an anchor or the tagcloud is clicked.
				 *
				 * @summary Flushes the tagbox when clicked.
				 *
				 * @since 4.2
				 *
				 * @returns {bool} Always returns false.
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
		 * Dispatch an audible message to screen readers.
		 *
		 * @since 4.7.0
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
		 * Initializes the tags box by setting up the links, buttons and adds event handling.
		 * This includes handling of pressing the enter key in the input field and the retrieval of tag suggestions.
		 *
		 * @since 4.2
		 *
		 * @memberOf tagBox
		 *
		 * @returns {void}
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
			 * Handles the flushing of the tagbox whenever the enter key is released in the new tag field.
			 *
			 * @summary Handles the flushing of the tagbox when adding a new tag.
			 *
			 * @since 4.2
			 *
			 * @param {Event} e The window event to handle.
			 * @returns {void}
			 */
			$( 'input.newtag', ajaxtag ).keyup( function( event ) {
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
			 * @summary Flushes tags whenever the post is saved.
			 *
			 * @since 4.2
			 *
			 * @returns {void}
			 */
			$('#post').submit(function(){
				$('div.tagsdiv').each( function() {
					tagBox.flushTags(this, false, 1);
				});
			});

			/**
			 * Unbinds the click event and toggles all sibling tagcloud elements when clicking on the tagcloud-link.
			 *
			 * @summary Unbinds the click event and toggles siblings.
			 *
			 * @since 4.2
			 *
			 * @returns {bool} Always returns false.
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
