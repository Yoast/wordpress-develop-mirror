var Attachment = wp.media.view.Attachment,
	l10n = wp.media.view.l10n,
	Details;

Details = Attachment.extend(/** @lends wp.media.view.Attachment.Details.prototype */{
	tagName:   'div',
	className: 'attachment-details',
	template:  wp.template('attachment-details'),

	/**
	 * Overrides the attributes method in the Attachment prototype and returns the attributes.
	 *
	 * @since 4.2.0
	 *
	 * @returns {Object} The tabIndex and the data id.
	 */
	attributes: function() {
		return {
			'tabIndex':     0,
			'data-id':      this.model.get( 'id' )
		};
	},

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

		this.on( 'ready', this.initialFocus );
		// Call 'initialize' directly on the parent class.
		Attachment.prototype.initialize.apply( this, arguments );
	},

	/**
	 * Puts focus on the first text input on non-touch devices.
	 *
	 * @since 4.2.0
	 *
	 * @returns {void}
	 */
	initialFocus: function() {
		if ( ! wp.media.isTouchDevice ) {
			/*
			Previously focused the first ':input' (the readonly URL text field).
			Since the first ':input' is now a button (delete/trash): when pressing
			spacebar on an attachment, Firefox fires deleteAttachment/trashAttachment
			as soon as focus is moved. Explicitly target the first text field for now.
			@todo change initial focus logic, also for accessibility.
			*/
			this.$( 'input[type="text"]' ).eq( 0 ).focus();
		}
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

		if ( window.confirm( l10n.warnDelete ) ) {
			this.model.destroy();
			/* Keep focus inside media modal
			 after image is deleted */
			this.controller.modal.focusManager.focus();
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
		var library = this.controller.library;
		event.preventDefault();

		if ( wp.media.view.settings.mediaTrash &&
			'edit-metadata' === this.controller.content.mode() ) {

			this.model.set( 'status', 'trash' );
			this.model.save().done( function() {
				library._requery( true );
			} );
		}  else {
			this.model.destroy();
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
	 * When reverse tabbing (shift+tab) out of the right details panel, delivers
	 * the focus to the item in the list that was being edited.
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

		if ( 37 === event.keyCode || 38 === event.keyCode || 39 === event.keyCode || 40 === event.keyCode ) {
			this.controller.trigger( 'attachment:keydown:arrow', event );
			return;
		}
	}
});

module.exports = Details;
