var Attachment = wp.media.view.Attachment,
	l10n = wp.media.view.l10n,
	$ = jQuery,
	Details;

Details = Attachment.extend(/** @lends wp.media.view.Attachment.Details.prototype */{
	tagName:   'div',
	className: 'attachment-details',
	template:  wp.template('attachment-details'),

	/*
	 * Reset all the attributes inherited from Attachment including role=checkbox,
	 * tabindex, etc., as they are inappropriate for this view. See #47458 and [30483] / #30390.
	 */
	attributes: {},

	events: {
		'change [data-setting]':          'updateSetting',
		'change [data-setting] input':    'updateSetting',
		'change [data-setting] select':   'updateSetting',
		'change [data-setting] textarea': 'updateSetting',
		'click .delete-attachment':       'deleteAttachment',
		'click .trash-attachment':        'trashAttachment',
		'click .untrash-attachment':      'untrashAttachment',
		'click .edit-attachment':         'editAttachment',
		'keydown':                        'toggleSelectionHandler'
	},

	/**
	 * Shows the details of an attachment.
	 *
	 * @since 4.2.0
	 *
	 * @constructs wp.media.view.Attachment.Details
	 * @augments wp.media.view.Attachment
	 *
	 * @returns {void}
	 */
	initialize: function() {
		this.options = _.defaults( this.options, {
			rerenderOnModelChange: false
		});

		// Call 'initialize' directly on the parent class.
		Attachment.prototype.initialize.apply( this, arguments );
	},

	/**
	 * Gets the focusable elements to move focus to.
	 *
	 * @since 5.3.0
	 */
	getFocusableElements: function() {
		var editedAttachment = $( 'li[data-id="' + this.model.id + '"]' );

		this.previousAttachment = editedAttachment.prev();
		this.nextAttachment = editedAttachment.next();
	},

	/**
	 * Moves focus to the previous or next attachment in the grid.
	 * Fallbacks to the upload button or media frame when there are no attachments.
	 *
	 * @since 5.3.0
	 */
	moveFocus: function() {
		if ( this.previousAttachment.length ) {
			this.previousAttachment.focus();
			return;
		}

		if ( this.nextAttachment.length ) {
			this.nextAttachment.focus();
			return;
		}

		// Fallback: move focus to the "Select Files" button in the media modal.
		if ( this.controller.uploader && this.controller.uploader.$browser ) {
			this.controller.uploader.$browser.focus();
			return;
		}

		// Last fallback.
		this.moveFocusToLastFallback();
	},

	/**
	 * Moves focus to the media frame as last fallback.
	 *
	 * @since 5.3.0
	 */
	moveFocusToLastFallback: function() {
		// Last fallback: make the frame focusable and move focus to it.
		$( '.media-frame' )
			.attr( 'tabindex', '-1' )
			.focus();
	},

	/**
	 * Deletes an attachment.
	 *
	 * Deletes an attachment after asking for confirmation. After deletion,
	 * keeps focus in the modal.
	 *
	 * @since 4.2.0
	 *
	 * @param {MouseEvent} event A click event.
	 *
	 * @returns {void}
	 */
	deleteAttachment: function( event ) {
		event.preventDefault();

		this.getFocusableElements();

		if ( window.confirm( l10n.warnDelete ) ) {
			this.model.destroy();
			this.moveFocus();
		}
	},
	/**
	 * Sets the trash state on an attachment, or destroys the model itself.
	 *
	 * If the mediaTrash setting is set to true, trashes the attachment.
	 * Otherwise, the model itself is destroyed.
	 *
	 * @since 4.2.0
	 *
	 * @param {MouseEvent} event A click event.
	 *
	 * @returns {void}
	 */
	trashAttachment: function( event ) {
		var library = this.controller.library,
			self = this;
		event.preventDefault();

		this.getFocusableElements();

		// When in the Media Library and the Media trash is enabled.
		if ( wp.media.view.settings.mediaTrash &&
			'edit-metadata' === this.controller.content.mode() ) {

			this.model.set( 'status', 'trash' );
			this.model.save().done( function() {
				library._requery( true );
				/*
				 * @todo: We need to move focus back to the previous, next, or first
				 * attachment but the library gets re-queried and refreshed. Thus,
				 * the references to the previous attachments are lost. We need an
				 * alternate method.
				 */
				self.moveFocusToLastFallback();
			} );
		} else {
			this.model.destroy();
			this.moveFocus();
		}
	},
	/**
	 * Untrashes an attachment.
	 *
	 * @since 4.2.0
	 *
	 * @param {MouseEvent} event A click event.
	 *
	 * @returns {void}
	 */
	untrashAttachment: function( event ) {
		var library = this.controller.library;
		event.preventDefault();

		this.model.set( 'status', 'inherit' );
		this.model.save().done( function() {
			library._requery( true );
		} );
	},
	/**
	 * Opens the edit page for a specific attachment.
	 *
	 * @since 4.2.0
	 *
	 * @param {MouseEvent} event A click event.
	 *
	 * @returns {void}
	 */
	editAttachment: function( event ) {
		var editState = this.controller.states.get( 'edit-image' );
		if ( window.imageEdit && editState ) {
			event.preventDefault();

			editState.set( 'image', this.model );
			this.controller.setState( 'edit-image' );
		} else {
			this.$el.addClass('needs-refresh');
		}
	},

	/**
	 * When reverse tabbing (shift+tab) out of the right details panel,
	 * move focus to the item that was being edited in the attachments list.
	 *
	 * @since 4.2.0
	 *
	 * @fires wp.media.controller.MediaLibrary#attachment:details:shift-tab
	 * @fires wp.media.controller.MediaLibrary#attachment:keydown:arrow
	 *
	 * @param {KeyboardEvent} event A keyboard event.
	 *
	 * @returns {boolean|void} Returns false or undefined.
	 */
	toggleSelectionHandler: function( event ) {
		if ( 'keydown' === event.type && 9 === event.keyCode && event.shiftKey && event.target === this.$( ':tabbable' ).get( 0 ) ) {
			this.controller.trigger( 'attachment:details:shift-tab', event );
			return false;
		}
	}
});

module.exports = Details;
