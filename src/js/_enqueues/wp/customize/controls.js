/**
 * @output wp-admin/js/customize-controls.js
 */

/* global _wpCustomizeHeader, _wpCustomizeBackground, _wpMediaViewsL10n, MediaElementPlayer, console, confirm */
(function( exports, $ ){
	var Container, focus, normalizedTransitionendEventName, api = wp.customize;

	/**
	 * The OverlayNotification object.
	 */
	api.OverlayNotification = api.Notification.extend(/** @lends wp.customize.OverlayNotification.prototype */{

		/**
		 * Whether the notification should show a loading spinner.
		 *
		 * @since 4.9.0
		 * @var {boolean}
		 */
		loading: false,

		/**
		 * Initializes a notification that is displayed in a full-screen overlay.
		 *
		 * @since 4.9.0
		 *
		 * @constructs wp.customize.OverlayNotification
		 * @augments   wp.customize.Notification
		 * @memberOf   wp.customize
		 *
		 * @param {string} code   The notification code.
		 * @param {Object} params Parameters to pass on to the parent class.
		 */
		initialize: function( code, params ) {
			var notification = this;
			api.Notification.prototype.initialize.call( notification, code, params );
			notification.containerClasses += ' notification-overlay';
			if ( notification.loading ) {
				notification.containerClasses += ' notification-loading';
			}
		},

		/**
		 * Renders the notification.
		 *
		 * @since 4.9.0
		 *
		 * @return {jQuery} The Notification container.
		 */
		render: function() {
			var li = api.Notification.prototype.render.call( this );
			li.on( 'keydown', _.bind( this.handleEscape, this ) );
			return li;
		},

		/**
		 * Stops event propagation on escape key presses, but also dismisses notification if it is dismissible.
		 *
		 * @since 4.9.0
		 *
		 * @param {jQuery.Event} event The event to check against.
		 *
		 * @return {void}
		 */
		handleEscape: function( event ) {
			var notification = this;
			if ( 27 === event.which ) {
				event.stopPropagation();
				if ( notification.dismissible && notification.parent ) {
					notification.parent.remove( notification.code );
				}
			}
		}
	});

	/**
	 * Extends the Notifications class to set default parameters.
	 */
	api.Notifications = api.Values.extend(/** @lends wp.customize.Notifications.prototype */{

		/**
		 * Whether the alternative style should be used.
		 *
		 * @since 4.9.0
		 * @type {boolean}
		 */
		alt: false,

		/**
		 * The default constructor for items of the collection.
		 *
		 * @since 4.9.0
		 * @type {Object}
		 */
		defaultConstructor: api.Notification,

		/**
		 * Initializes the collection of observable notifications.
		 *
		 * @since 4.9.0
		 *
		 * @constructs wp.customize.Notifications
		 * @augments   wp.customize.Values
		 * @memberOf   wp.customize
		 *
		 * @param {Object}  options             The options to pass to the parent class.
		 * @param {jQuery}  [options.container] Container element for notifications. This can be injected later.
		 * @param {boolean} [options.alt]       Whether alternative style should be used when rendering notifications.
		 */
		initialize: function( options ) {
			var collection = this;

			api.Values.prototype.initialize.call( collection, options );

			_.bindAll( collection, 'constrainFocus' );

			// Keep track of the order in which the notifications were added for sorting purposes.
			collection._addedIncrement = 0;
			collection._addedOrder = {};

			// Trigger change event when notification is added or removed.
			collection.bind( 'add', function( notification ) {
				collection.trigger( 'change', notification );
			});
			collection.bind( 'removed', function( notification ) {
				collection.trigger( 'change', notification );
			});
		},

		/**
		 * Gets the number of notifications added.
		 *
		 * @since 4.9.0
		 *
		 * @return {number} Amount of added notifications.
		 */
		count: function() {
			return _.size( this._value );
		},

		/**
		 * Adds a notification to the collection.
		 *
		 * Alternatively, you can supply the notification parameter with a string, in which case the notificationObject
		 * parameter is required.
		 *
		 * @since 4.9.0
		 *
		 * @param {string|wp.customize.Notification} notification         Notification object or code to add.
		 * @param {wp.customize.Notification}        [notificationObject] Notification to add when the first argument is a code string.
		 *
		 * @return {wp.customize.Notification} Added notification or existing instance if it was already added.
		 */
		add: function( notification, notificationObject ) {
			var collection = this, code, instance;
			if ( 'string' === typeof notification ) {
				code = notification;
				instance = notificationObject;
			} else {
				code = notification.code;
				instance = notification;
			}
			if ( ! collection.has( code ) ) {
				collection._addedIncrement += 1;
				collection._addedOrder[ code ] = collection._addedIncrement;
			}
			return api.Values.prototype.add.call( collection, code, instance );
		},

		/**
		 * Removes a notification from the collection.
		 *
		 * @since 4.9.0
		 *
		 * @param {string} code The notification code used to find the notification that needs to be removed.
		 */
		remove: function( code ) {
			var collection = this;
			delete collection._addedOrder[ code ];
			return api.Values.prototype.remove.call( this, code );
		},

		/**
		 * Gets the list of notifications.
		 *
		 * Notifications can be sorted by type followed by how recently they were added.
		 * By default no sorting is applied.
		 *
		 * @since 4.9.0
		 *
		 * @param {Object}  args              The arguments to pass along to the sorting algorithm.
		 * @param {boolean} [args.sort=false] Whether to return the notifications sorted.
		 *
		 * @return {Array.<wp.customize.Notification>} List containing the notifications.
		 */
		get: function( args ) {
			var collection = this, notifications, errorTypePriorities, params;
			notifications = _.values( collection._value );

			params = _.extend(
				{ sort: false },
				args
			);

			if ( params.sort ) {
				errorTypePriorities = { error: 4, warning: 3, success: 2, info: 1 };
				notifications.sort( function( a, b ) {
					var aPriority = 0, bPriority = 0;
					if ( ! _.isUndefined( errorTypePriorities[ a.type ] ) ) {
						aPriority = errorTypePriorities[ a.type ];
					}
					if ( ! _.isUndefined( errorTypePriorities[ b.type ] ) ) {
						bPriority = errorTypePriorities[ b.type ];
					}
					if ( aPriority !== bPriority ) {
						return bPriority - aPriority; // Show errors first.
					}
					return collection._addedOrder[ b.code ] - collection._addedOrder[ a.code ]; // Show newer notifications higher.
				});
			}

			return notifications;
		},

		/**
		 * Renders notifications area.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		render: function() {
			var collection = this,
				notifications, hadOverlayNotification = false, hasOverlayNotification, overlayNotifications = [],
				previousNotificationsByCode = {},
				listElement, focusableElements;

			// Short-circuit if there is no container to render into.
			if ( ! collection.container || ! collection.container.length ) {
				return;
			}

			notifications = collection.get( { sort: true } );
			collection.container.toggle( 0 !== notifications.length );

			// Short-circuit if there are no changes to the notifications.
			if ( collection.container.is( collection.previousContainer ) && _.isEqual( notifications, collection.previousNotifications ) ) {
				return;
			}

			// Make sure the list is part of the container.
			listElement = collection.container.children( 'ul' ).first();
			if ( ! listElement.length ) {
				listElement = $( '<ul></ul>' );
				collection.container.append( listElement );
			}

			// Remove all notifications prior to re-rendering.
			listElement.find( '> [data-code]' ).remove();

			_.each( collection.previousNotifications, function( notification ) {
				previousNotificationsByCode[ notification.code ] = notification;
			});

			// Add all notifications in their sorted order.
			_.each( notifications, function( notification ) {
				var notificationContainer;

				// Determine whether the notification code is different from the previous one so screen readers don't announce the same code twice.
				if ( wp.a11y && ( ! previousNotificationsByCode[ notification.code ] || ! _.isEqual( notification.message, previousNotificationsByCode[ notification.code ].message ) ) ) {
					wp.a11y.speak( notification.message, 'assertive' );
				}
				notificationContainer = $( notification.render() );
				notification.container = notificationContainer;
				listElement.append( notificationContainer ); // @todo Consider slideDown() as enhancement.

				// If the notification extends an OverlayNotifcation, add it to the proper collection.
				if ( notification.extended( api.OverlayNotification ) ) {
					overlayNotifications.push( notification );
				}
			});
			hasOverlayNotification = Boolean( overlayNotifications.length );

			// If notifications are already present in the collection, check whether they extend an OverlayNotification.
			if ( collection.previousNotifications ) {
				hadOverlayNotification = Boolean( _.find( collection.previousNotifications, function( notification ) {
					return notification.extended( api.OverlayNotification );
				} ) );
			}

			// Check if the current OverlayNotifications differ from the previous ones.
			if ( hasOverlayNotification !== hadOverlayNotification ) {
				$( document.body ).toggleClass( 'customize-loading', hasOverlayNotification );
				collection.container.toggleClass( 'has-overlay-notifications', hasOverlayNotification );

				/*
				* If there are OverlayNotifcations present, set the previousActiveElement and bind the constrainFocus function.
				*
				* If not, unbind the constrainFocus function.
				*/
				if ( hasOverlayNotification ) {
					collection.previousActiveElement = document.activeElement;
					$( document ).on( 'keydown', collection.constrainFocus );
				} else {
					$( document ).off( 'keydown', collection.constrainFocus );
				}
			}

			/*
			 * If there are OverlayNotifications, attempt to find a focusable element. If none are present, set focus to the container.
			 *
			 * If there are no OverlayNotifications, reset focus to the previousActiveElement.
			 */
			if ( hasOverlayNotification ) {
				collection.focusContainer = overlayNotifications[ overlayNotifications.length - 1 ].container;
				collection.focusContainer.prop( 'tabIndex', -1 );
				focusableElements = collection.focusContainer.find( ':focusable' );
				if ( focusableElements.length ) {
					focusableElements.first().focus();
				} else {
					collection.focusContainer.focus();
				}
			} else if ( collection.previousActiveElement ) {
				$( collection.previousActiveElement ).focus();
				collection.previousActiveElement = null;
			}

			collection.previousNotifications = notifications;
			collection.previousContainer = collection.container;
			collection.trigger( 'rendered' );
		},

		/**
		 * Constrains focus on the focus container.
		 *
		 * @since 4.9.0
		 *
		 * @param {jQuery.Event} event The event to check against.
		 *
		 * @return {void}
		 */
		constrainFocus: function constrainFocus( event ) {
			var collection = this, focusableElements;

			// Prevent keys from escaping.
			event.stopPropagation();

			// Return if any other key than the tab key is pressed.
			if ( 9 !== event.which ) {
				return;
			}

			focusableElements = collection.focusContainer.find( ':focusable' );
			if ( 0 === focusableElements.length ) {
				focusableElements = collection.focusContainer;
			}

			/*
			 * Check whether the target of the tabbing event exists within the focusContainer. If not, set focus on the first element within the focusableElements.
			 * If the user is currently on the last focusable element and doesn't hold shift whilst tabbing, jump back to the first focusable element.
			 * If the user is on the first focusable element and uses shift and tab, jump to the last focusable element.
			 */
			if ( ! $.contains( collection.focusContainer[0], event.target ) || ! $.contains( collection.focusContainer[0], document.activeElement ) ) {
				event.preventDefault();
				focusableElements.first().focus();
			} else if ( focusableElements.last().is( event.target ) && ! event.shiftKey ) {
				event.preventDefault();
				focusableElements.first().focus();
			} else if ( focusableElements.first().is( event.target ) && event.shiftKey ) {
				event.preventDefault();
				focusableElements.last().focus();
			}
		}
	});

	/**
	 * The API Setting object.
	 */
	api.Setting = api.Value.extend(/** @lends wp.customize.Setting.prototype */{

		/**
		 * Default parameters.
		 *
		 * @since 4.9.0
		 *
		 * @var {Object}
		 */
		defaults: {
			transport: 'refresh',
			dirty: false
		},

		/**
		 * Initializes a Customizer Setting.
		 *
		 * A setting is WordPress data (theme mod, option, menu, etc.) that the user can
		 * draft changes to in the Customizer.
		 *
		 * @since 3.4.0
		 *
		 * @constructs wp.customize.Setting
		 * @augments   wp.customize.Value
		 * @memberOf   wp.customize
		 *
		 * @see PHP class WP_Customize_Setting.
		 *
		 * @param {string}  id                          The setting ID.
		 * @param {*}       value                       The initial value of the setting.
		 * @param {Object}  [options={}]                The options to use within the setting.
		 * @param {string}  [options.transport=refresh] The transport method to use for previewing. Supports 'refresh' and 'postMessage'.
		 * @param {boolean} [options.dirty=false]       Whether the setting should initially be considered dirty.
		 * @param {Object}  [options.previewer]         The Previewer instance to sync with. Defaults to wp.customize.previewer.
		 */
		initialize: function( id, value, options ) {
			var setting = this, params;
			params = _.extend(
				{ previewer: api.previewer },
				setting.defaults,
				options || {}
			);

			api.Value.prototype.initialize.call( setting, value, params );

			setting.id = id;
			setting._dirty = params.dirty;
			setting.notifications = new api.Notifications();

			// Whenever the setting's value changes, refresh the preview.
			setting.bind( setting.preview );
		},

		/**
		 * Refreshes the preview, respective of the setting's refresh policy.
		 *
		 * If the preview hasn't sent a keep-alive message and is likely
		 * disconnected by having navigated to a non-allowed URL, then the
		 * refresh transport will be forced when postMessage is the transport method.
		 * Note that postMessage does not throw an error when the recipient window
		 * fails to match the origin window, so using try/catch around the
		 * previewer.send() call to then fallback to refresh will not work.
		 *
		 * @since 3.4.0
		 * @access public
		 *
		 * @return {void}
		 */
		preview: function() {
			var setting = this, transport;
			transport = setting.transport;

			if ( 'postMessage' === transport && ! api.state( 'previewerAlive' ).get() ) {
				transport = 'refresh';
			}

			if ( 'postMessage' === transport ) {
				setting.previewer.send( 'setting', [ setting.id, setting() ] );
			} else if ( 'refresh' === transport ) {
				setting.previewer.refresh();
			}
		},

		/**
		 * Finds controls associated with this setting.
		 *
		 * @since 4.6.0
		 *
		 * @return {wp.customize.Control[]} Controls associated with the setting.
		 */
		findControls: function() {
			var setting = this, controls = [];
			api.control.each( function( control ) {
				_.each( control.settings, function( controlSetting ) {
					if ( controlSetting.id === setting.id ) {
						controls.push( control );
					}
				} );
			} );
			return controls;
		}
	});

	/**
	 * Current change count.
	 *
	 * @alias wp.customize._latestRevision
	 *
	 * @since 4.7.0
	 *
	 * @type {number}
	 * @protected
	 */
	api._latestRevision = 0;

	/**
	 * Last revision that was saved.
	 *
	 * @alias wp.customize._lastSavedRevision
	 *
	 * @since 4.7.0
	 *
	 * @type {number}
	 * @protected
	 */
	api._lastSavedRevision = 0;

	/**
	 * Latest revisions associated with the updated setting.
	 *
	 * @alias wp.customize._latestSettingRevisions
	 *
	 * @since 4.7.0
	 *
	 * @type {Object}
	 * @protected
	 */
	api._latestSettingRevisions = {};

	/*
	 * Keep track of the revision associated with each updated setting so that
	 * requestChangesetUpdate knows which dirty settings to include. Also, once
	 * ready is triggered and all initial settings have been added, increment
	 * revision for each newly-created initially-dirty setting so that it will
	 * also be included in changeset update requests.
	 */
	api.bind( 'change', function incrementChangedSettingRevision( setting ) {
		api._latestRevision += 1;
		api._latestSettingRevisions[ setting.id ] = api._latestRevision;
	} );
	api.bind( 'ready', function() {
		api.bind( 'add', function incrementCreatedSettingRevision( setting ) {
			if ( setting._dirty ) {
				api._latestRevision += 1;
				api._latestSettingRevisions[ setting.id ] = api._latestRevision;
			}
		} );
	} );

	/**
	 * Gets the dirty setting values.
	 *
	 * @alias wp.customize.dirtyValues
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param {Object}  [options]               The options to use within the setting.
	 * @param {boolean} [options.unsaved=false] Whether only values that aren't saved yet into a changeset will be returned (differential changes).
	 *
	 * @return {Object} The dirty setting values.
	 */
	api.dirtyValues = function dirtyValues( options ) {
		var values = {};
		api.each( function( setting ) {
			var settingRevision;

			if ( ! setting._dirty ) {
				return;
			}

			settingRevision = api._latestSettingRevisions[ setting.id ];

			// Skip including settings that have already been included in the changeset, if only requesting unsaved values.
			if ( api.state( 'changesetStatus' ).get() && ( options && options.unsaved ) && ( _.isUndefined( settingRevision ) || settingRevision <= api._lastSavedRevision ) ) {
				return;
			}

			values[ setting.id ] = setting.get();
		} );
		return values;
	};

	/**
	 * Sends a request to update the changeset.
	 *
	 * @alias wp.customize.requestChangesetUpdate
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param {Object}  [changes]             Mapping of setting IDs to setting params which normally include a value property, or mapping to null.
	 *                                        If not provided, then the changes will still be obtained from unsaved dirty settings.
	 * @param {Object}  [args]                Additional options for the save request.
	 * @param {boolean} [args.autosave=false] Whether changes will be stored in autosave revision if the changeset has been promoted from an auto-draft.
	 * @param {boolean} [args.force=false]    Force sending a request to update even when there are no changes to submit. This can be used to request the latest status of the changeset on the server.
	 * @param {string}  [args.title]          Title to update in the changeset.
	 * @param {string}  [args.date]           Date to update in the changeset.
	 *
	 * @return {jQuery.Promise}              Promise resolving with the response data.
	 */
	api.requestChangesetUpdate = function requestChangesetUpdate( changes, args ) {
		var deferred, request, submittedChanges = {}, data, submittedArgs;
		deferred = new $.Deferred();

		// Prevent attempting changeset update while request is being made.
		if ( 0 !== api.state( 'processing' ).get() ) {
			deferred.reject( 'already_processing' );
			return deferred.promise();
		}

		submittedArgs = _.extend( {
			title: null,
			date: null,
			autosave: false,
			force: false
		}, args );

		if ( changes ) {
			_.extend( submittedChanges, changes );
		}

		// Ensure all revised settings (changes pending save) are also included, but not if marked for deletion in changes.
		_.each( api.dirtyValues( { unsaved: true } ), function( dirtyValue, settingId ) {
			if ( ! changes || null !== changes[ settingId ] ) {
				submittedChanges[ settingId ] = _.extend(
					{},
					submittedChanges[ settingId ] || {},
					{ value: dirtyValue }
				);
			}
		} );

		// Allow plugins to attach additional params to the settings.
		api.trigger( 'changeset-save', submittedChanges, submittedArgs );

		// Short-circuit when there are no pending changes.
		if ( ! submittedArgs.force && _.isEmpty( submittedChanges ) && null === submittedArgs.title && null === submittedArgs.date ) {
			deferred.resolve( {} );
			return deferred.promise();
		}

		// A status would cause a revision to be made, and for this wp.customize.previewer.save() should be used. Status is also disallowed for revisions regardless.
		if ( submittedArgs.status ) {
			return deferred.reject( { code: 'illegal_status_in_changeset_update' } ).promise();
		}

		// Dates not being allowed for revisions is a technical limitation of post revisions.
		if ( submittedArgs.date && submittedArgs.autosave ) {
			return deferred.reject( { code: 'illegal_autosave_with_date_gmt' } ).promise();
		}

		// Make sure that publishing a changeset waits for all changeset update requests to complete.
		api.state( 'processing' ).set( api.state( 'processing' ).get() + 1 );
		deferred.always( function() {
			api.state( 'processing' ).set( api.state( 'processing' ).get() - 1 );
		} );

		// Ensure that additional data is included if any plugin adds data to save requests (by extending query()).
		data = api.previewer.query( { excludeCustomizedSaved: true } );
		delete data.customized; // This is being sent in customize_changeset_data instead.
		_.extend( data, {
			nonce: api.settings.nonce.save,
			customize_theme: api.settings.theme.stylesheet,
			customize_changeset_data: JSON.stringify( submittedChanges )
		} );
		if ( null !== submittedArgs.title ) {
			data.customize_changeset_title = submittedArgs.title;
		}
		if ( null !== submittedArgs.date ) {
			data.customize_changeset_date = submittedArgs.date;
		}
		if ( false !== submittedArgs.autosave ) {
			data.customize_changeset_autosave = 'true';
		}

		// Allow plugins to modify the params included with the save request.
		api.trigger( 'save-request-params', data );

		request = wp.ajax.post( 'customize_save', data );

		request.done( function requestChangesetUpdateDone( data ) {
			var savedChangesetValues = {};

			// Ensure that all settings updated subsequently will be included in the next changeset update request.
			api._lastSavedRevision = Math.max( api._latestRevision, api._lastSavedRevision );

			api.state( 'changesetStatus' ).set( data.changeset_status );

			if ( data.changeset_date ) {
				api.state( 'changesetDate' ).set( data.changeset_date );
			}

			deferred.resolve( data );
			api.trigger( 'changeset-saved', data );

			if ( data.setting_validities ) {
				_.each( data.setting_validities, function( validity, settingId ) {
					if ( true === validity && _.isObject( submittedChanges[ settingId ] ) && ! _.isUndefined( submittedChanges[ settingId ].value ) ) {
						savedChangesetValues[ settingId ] = submittedChanges[ settingId ].value;
					}
				} );
			}

			api.previewer.send( 'changeset-saved', _.extend( {}, data, { saved_changeset_values: savedChangesetValues } ) );
		} );
		request.fail( function requestChangesetUpdateFail( data ) {
			deferred.reject( data );
			api.trigger( 'changeset-error', data );
		} );
		request.always( function( data ) {
			if ( data.setting_validities ) {
				api._handleSettingValidities( {
					settingValidities: data.setting_validities
				} );
			}
		} );

		return deferred.promise();
	};

	/**
	 * Watches all changes to Value properties, and bubbles changes to parent Values instances.
	 *
	 * @alias wp.customize.utils.bubbleChildValueChanges
	 *
	 * @since 4.1.0
	 *
	 * @param {wp.customize.Class} instance    The customize class instance.
	 * @param {Array}              properties  The names of the Value instances to watch.
	 *
	 * @return {void}
	 */
	api.utils.bubbleChildValueChanges = function ( instance, properties ) {
		$.each( properties, function ( i, key ) {
			instance[ key ].bind( function ( to, from ) {
				if ( instance.parent && to !== from ) {
					instance.parent.trigger( 'change', instance );
				}
			} );
		} );
	};

	/**
	 * Expands a panel, section, or control and focusses on the first focusable element.
	 *
	 * @alias wp.customize~focus
	 *
	 * @since 4.1.0
	 *
	 * @param {Object}   [params]                   Object containing the parameters using this function.
	 * @param {Function} [params.completeCallback]  Function that gets called once the expansion is complete.
	 *
	 * @return {void}
	 */
	focus = function ( params ) {
		var construct, completeCallback, focus, focusElement;
		construct = this;
		params = params || {};
		focus = function () {
			var focusContainer;
			if ( ( construct.extended( api.Panel ) || construct.extended( api.Section ) ) && construct.expanded && construct.expanded() ) {
				focusContainer = construct.contentContainer;
			} else {
				focusContainer = construct.container;
			}

			focusElement = focusContainer.find( '.control-focus:first' );
			if ( 0 === focusElement.length ) {
				// Note that we can't use :focusable due to a jQuery UI issue. See: https://github.com/jquery/jquery-ui/pull/1583
				focusElement = focusContainer.find( 'input, select, textarea, button, object, a[href], [tabindex]' ).filter( ':visible' ).first();
			}
			focusElement.focus();
		};
		if ( params.completeCallback ) {
			completeCallback = params.completeCallback;
			params.completeCallback = function () {
				focus();
				completeCallback();
			};
		} else {
			params.completeCallback = focus;
		}

		api.state( 'paneVisible' ).set( true );
		if ( construct.expand ) {
			construct.expand( params );
		} else {
			params.completeCallback();
		}
	};

	/**
	 * Stable sorts Panels, Sections, and Controls.
	 *
	 * If a.priority() === b.priority(), then it sorts by their respective params.instanceNumber.
	 *
	 * @alias wp.customize.utils.prioritySort
	 *
	 * @since 4.1.0
	 *
	 * @param {wp.customize.Panel|wp.customize.Section|wp.customize.Control} a The element to compare parameter b with.
	 * @param {wp.customize.Panel|wp.customize.Section|wp.customize.Control} b The element to compare parameter a with.
	 *
	 * @return {number} The sorting order.
	 */
	api.utils.prioritySort = function ( a, b ) {
		if ( a.priority() === b.priority() && typeof a.params.instanceNumber === 'number' && typeof b.params.instanceNumber === 'number' ) {
			return a.params.instanceNumber - b.params.instanceNumber;
		} else {
			return a.priority() - b.priority();
		}
	};

	/**
	 * Returns whether the supplied Event object is for a keydown event and is not the Enter key.
	 *
	 * @alias wp.customize.utils.isKeydownButNotEnterEvent
	 *
	 * @since 4.1.0
	 *
	 * @param {jQuery.Event} event  The page event to check against.
	 *
	 * @return {boolean} Whether or not a key was pressed that isn't the Enter key.
	 */
	api.utils.isKeydownButNotEnterEvent = function ( event ) {
		return ( 'keydown' === event.type && 13 !== event.which );
	};

	/**
	 * Returns whether the two lists of elements are the same and are in the same order.
	 *
	 * @alias wp.customize.utils.areElementListsEqual
	 *
	 * @since 4.1.0
	 *
	 * @param {Array|jQuery} listA  List of elements that need to be compared with listB.
	 * @param {Array|jQuery} listB  List of elements that need to be compared with listA.
	 *
	 * @return {boolean} Whether or not both lists are equal.
	 */
	api.utils.areElementListsEqual = function ( listA, listB ) {
		var equal = (
			listA.length === listB.length && // If the lists have different lengths, then naturally they are not equal.
			-1 === _.indexOf( _.map( // Determine if there are any false values in the list returned by map.
				_.zip( listA, listB ), // Pair up each element between the two lists.
				function ( pair ) {
					return $( pair[0] ).is( pair[1] ); // Compare to see if each pair is equal.
				}
			), false ) // Check for presence of false in map's return value.
		);
		return equal;
	};

	/**
	 * Highlights the existence of a button.
	 *
	 * This function reminds the user of a button represented by the specified
	 * UI element, after an optional delay. If the user focuses the element
	 * before the delay passes, the reminder is canceled.
	 *
	 * @alias wp.customize.utils.highlightButton
	 *
	 * @since 4.9.0
	 *
	 * @param {jQuery} button                  The element to highlight.
	 * @param {Object} [options]               The options to use when highlighting a button.
	 * @param {number} [options.delay=0]       The amount of time in milliseconds to wait before highlighting.
	 * @param {jQuery} [options.focusTarget]   The target to focus on that defaults to the highlighted element.
	 *                                         If the user focuses the target before the delay passes, the reminder
	 *                                         is canceled. This option exists to accommodate compound buttons
	 *                                         containing auxiliary UI, such as the Publish button augmented with a
	 *                                         Settings button.
	 *
	 * @return {Function} A function that cancels the reminder.
	 */
	api.utils.highlightButton = function highlightButton( button, options ) {
		var animationClass = 'button-see-me',
			canceled = false,
			params;

		params = _.extend(
			{
				delay: 0,
				focusTarget: button
			},
			options
		);

		function cancelReminder() {
			canceled = true;
		}

		params.focusTarget.on( 'focusin', cancelReminder );
		setTimeout( function() {
			params.focusTarget.off( 'focusin', cancelReminder );

			if ( ! canceled ) {
				button.addClass( animationClass );
				button.one( 'animationend', function() {
					/*
					 * Remove animation class to avoid situations in Customizer where
					 * DOM nodes are moved (re-inserted) and the animation repeats.
					 */
					button.removeClass( animationClass );
				} );
			}
		}, params.delay );

		return cancelReminder;
	};

	/**
	 * Gets the current timestamp adjusted for the server clock time.
	 *
	 * Same functionality as the `current_time( 'mysql', false )` function in PHP.
	 *
	 * @alias wp.customize.utils.getCurrentTimestamp
	 *
	 * @since 4.9.0
	 *
	 * @return {number} The current timestamp.
	 */
	api.utils.getCurrentTimestamp = function getCurrentTimestamp() {
		var currentDate, currentClientTimestamp, timestampDifferential;
		currentClientTimestamp = _.now();
		currentDate = new Date( api.settings.initialServerDate.replace( /-/g, '/' ) );
		timestampDifferential = currentClientTimestamp - api.settings.initialClientTimestamp;
		timestampDifferential += api.settings.initialClientTimestamp - api.settings.initialServerTimestamp;
		currentDate.setTime( currentDate.getTime() + timestampDifferential );
		return currentDate.getTime();
	};

	/**
	 * Calculates the remaining time based on the passed datetime.
	 *
	 * @alias wp.customize.utils.getRemainingTime
	 *
	 * @since 4.9.0
	 *
	 * @param {string|number|Date} datetime The datetime or timestamp of the future date.
	 *
	 * @return {number} The remaining time in milliseconds.
	 */
	api.utils.getRemainingTime = function getRemainingTime( datetime ) {
		var millisecondsDivider = 1000, remainingTime, timestamp;
		if ( datetime instanceof Date ) {
			timestamp = datetime.getTime();
		} else if ( 'string' === typeof datetime ) {
			timestamp = ( new Date( datetime.replace( /-/g, '/' ) ) ).getTime();
		} else {
			timestamp = datetime;
		}

		remainingTime = timestamp - api.utils.getCurrentTimestamp();
		remainingTime = Math.ceil( remainingTime / millisecondsDivider );
		return remainingTime;
	};

	/**
	 * Returns the browser supported `transitionend` event name.
	 *
	 * @since 4.7.0
	 *
	 * @ignore
	 *
	 * @return {string|null} Normalized `transitionend` event name or null if CSS transitions are not supported.
	 */
	normalizedTransitionendEventName = (function () {
		var el, transitions, prop;
		el = document.createElement( 'div' );
		transitions = {
			'transition'      : 'transitionend',
			'OTransition'     : 'oTransitionEnd',
			'MozTransition'   : 'transitionend',
			'WebkitTransition': 'webkitTransitionEnd'
		};
		prop = _.find( _.keys( transitions ), function( prop ) {
			return ! _.isUndefined( el.style[ prop ] );
		} );
		if ( prop ) {
			return transitions[ prop ];
		} else {
			return null;
		}
	})();

	/**
	 * The Container object.
	 */
	Container = api.Class.extend(/** @lends wp.customize~Container.prototype */{
		defaultActiveArguments: { duration: 'fast', completeCallback: $.noop },
		defaultExpandedArguments: { duration: 'fast', completeCallback: $.noop },
		containerType: 'container',
		defaults: {
			title: '',
			description: '',
			priority: 100,
			type: 'default',
			content: null,
			active: true,
			instanceNumber: null
		},

		/**
		 * Initializes the base class for Panels and Sections.
		 *
		 * @since 4.1.0
		 *
		 * @constructs wp.customize~Container
		 * @augments   wp.customize.Class
		 * @memberOf   wp.customize
		 *
		 * @borrows wp.customize~focus as focus
		 *
		 * @param {string}    id                       The ID for the container.
		 * @param {Object}    options                  The options to use within the Container object.
		 * @param {string}    options.title            Title shown when panel is collapsed and expanded.
		 * @param {string}    [options.description]    Description shown at the top of the panel.
		 * @param {number}    [options.priority=100]   The sort priority for the panel.
		 * @param {string}    [options.templateId]     Template selector for the container.
		 * @param {string}    [options.type=default]   The type of panel. See wp.customize.panelConstructor.
		 * @param {string}    [options.content]        The markup to be used for the panel container. If empty, a JS template is used.
		 * @param {boolean}   [options.active=true]    Whether the panel is active or not.
		 * @param {Object}    [options.params]         Deprecated wrapper for the above properties.
		 */
		initialize: function ( id, options ) {
			var container = this;
			container.id = id;

			if ( ! Container.instanceCounter ) {
				Container.instanceCounter = 0;
			}
			Container.instanceCounter++;

			$.extend( container, {
				params: _.defaults(
					options.params || options, // Passing the params is deprecated.
					container.defaults
				)
			} );
			if ( ! container.params.instanceNumber ) {
				container.params.instanceNumber = Container.instanceCounter;
			}
			container.notifications = new api.Notifications();
			container.templateSelector = container.params.templateId || 'customize-' + container.containerType + '-' + container.params.type;
			container.container = $( container.params.content );
			if ( 0 === container.container.length ) {
				container.container = $( container.getContainer() );
			}
			container.headContainer = container.container;
			container.contentContainer = container.getContent();
			container.container = container.container.add( container.contentContainer );

			container.deferred = {
				embedded: new $.Deferred()
			};
			container.priority = new api.Value();
			container.active = new api.Value();
			container.activeArgumentsQueue = [];
			container.expanded = new api.Value();
			container.expandedArgumentsQueue = [];

			container.active.bind( function ( active ) {
				var args = container.activeArgumentsQueue.shift();
				args = $.extend( {}, container.defaultActiveArguments, args );
				active = ( active && container.isContextuallyActive() );
				container.onChangeActive( active, args );
			});
			container.expanded.bind( function ( expanded ) {
				var args = container.expandedArgumentsQueue.shift();
				args = $.extend( {}, container.defaultExpandedArguments, args );
				container.onChangeExpanded( expanded, args );
			});

			container.deferred.embedded.done( function () {
				container.setupNotifications();
				container.attachEvents();
			});

			api.utils.bubbleChildValueChanges( container, [ 'priority', 'active' ] );

			container.priority.set( container.params.priority );
			container.active.set( container.params.active );
			container.expanded.set( false );
		},

		/**
		 * Gets the element that will contain the notifications.
		 *
		 * @since 4.9.0
		 *
		 * @return {jQuery} Notification container element.
		 */
		getNotificationsContainerElement: function() {
			var container = this;
			return container.contentContainer.find( '.customize-control-notifications-container:first' );
		},

		/**
		 * Sets up the notifications.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		setupNotifications: function() {
			var container = this, renderNotifications;
			container.notifications.container = container.getNotificationsContainerElement();

			// Render the notifications when they change and when the construct is expanded.
			renderNotifications = function() {
				if ( container.expanded.get() ) {
					container.notifications.render();
				}
			};
			container.expanded.bind( renderNotifications );
			renderNotifications();
			container.notifications.bind( 'change', _.debounce( renderNotifications ) );
		},

		/**
		 * Fires when the class is considered DOM-ready.
		 *
		 * This function gets called when the view is rendered and added to the DOM. Can be used to bind input events.
		 *
		 * @since 4.1.0
		 *
		 * @abstract
		 *
		 * @return {void}
		 */
		ready: function() {},

		/**
		 * Gets the child models associated with this parent and sorts them by their priority Value.
		 *
		 * @since 4.1.0
		 *
		 * @param {string} parentType The parent type to look for within the separate childTypes.
		 * @param {string} childType  The childType to loop through to retrieve the model from.
		 *
		 * @return {Array} The child models.
		 */
		_children: function ( parentType, childType ) {
			var parent = this,
				children = [];
			api[ childType ].each( function ( child ) {
				if ( child[ parentType ].get() === parent.id ) {
					children.push( child );
				}
			} );
			children.sort( api.utils.prioritySort );
			return children;
		},

		/**
		 * Determines whether the Container is contextually active.
		 *
		 * Must be overridden by subclass, to return whether the container has active children.
		 *
		 * @since 4.1.0
		 *
		 * @abstract
		 *
		 * @return {void}
		 */
		isContextuallyActive: function () {
			throw new Error( 'Container.isContextuallyActive() must be overridden in a subclass.' );
		},

		/**
		 * Shows the Container if it is active, hides it if not.
		 *
		 * Must be overridden by subclass. Update the container's UI to reflect the provided active state.
		 *
		 * @since 4.1.0
		 *
		 * @param {boolean}  active						The active state to transition to.
		 * @param {Object}   [args]						The arguments to use within the transition.
		 * @param {Object}   [args.duration]			The duration for the slideUp/slideDown animation.
		 * @param {boolean}  [args.unchanged]			Whether the state remains unchanged.
		 * @param {Function} [args.completeCallback]	Function to call when the slideUp/slideDown is completed.
		 *
		 * @return {void}
		 */
		onChangeActive: function( active, args ) {
			var construct = this,
				headContainer = construct.headContainer,
				duration, expandedOtherPanel;

			if ( args.unchanged ) {
				if ( args.completeCallback ) {
					args.completeCallback();
				}
				return;
			}

			duration = ( 'resolved' === api.previewer.deferred.active.state() ? args.duration : 0 );

			if ( construct.extended( api.Panel ) ) {
				// If this is a panel and is not currently expanded, but another panel is, do not animate.
				api.panel.each(function ( panel ) {
					if ( panel !== construct && panel.expanded() ) {
						expandedOtherPanel = panel;
						duration = 0;
					}
				});

				// Collapse any expanded sections inside of this panel first before deactivating.
				if ( ! active ) {
					_.each( construct.sections(), function( section ) {
						section.collapse( { duration: 0 } );
					} );
				}
			}

			if ( ! $.contains( document, headContainer.get( 0 ) ) ) {
				// If the element is not in the DOM, then jQuery.fn.slideUp() does nothing. In this case, a hard toggle is required instead.
				headContainer.toggle( active );
				if ( args.completeCallback ) {
					args.completeCallback();
				}
			} else if ( active ) {
				headContainer.slideDown( duration, args.completeCallback );
			} else {
				if ( construct.expanded() ) {
					construct.collapse({
						duration: duration,
						completeCallback: function() {
							headContainer.slideUp( duration, args.completeCallback );
						}
					});
				} else {
					headContainer.slideUp( duration, args.completeCallback );
				}
			}
		},

		/**
		 * Toggles the active state if the current state is different than the wanted state.
		 *
		 * @since 4.1.0
		 *
		 * @param {boolean} active		The state to toggle to.
		 * @param {Object}  [params]	Optional parameters that can be passed along to either onChangeActive
		 * 								or the activeArgumentsQueue.
		 *
		 * @return {boolean} True if the toggling of state was executed. Otherwise false.
		 */
		_toggleActive: function ( active, params ) {
			var self = this;
			params = params || {};
			if ( ( active && this.active.get() ) || ( ! active && ! this.active.get() ) ) {
				params.unchanged = true;
				self.onChangeActive( self.active.get(), params );
				return false;
			} else {
				params.unchanged = false;
				this.activeArgumentsQueue.push( params );
				this.active.set( active );
				return true;
			}
		},

		/**
		 * Activates the Container.
		 *
		 * @param {Object} [params]	The parameters to pass along to the toggleActive function.
		 *
		 * @return {boolean} True if the toggling of state was executed. Otherwise false.
		 */
		activate: function ( params ) {
			return this._toggleActive( true, params );
		},

		/**
		 * Deactivates the Container.
		 *
		 * @param {Object} [params]	The parameters to pass along to the toggleActive function.
		 *
		 * @return {boolean} True if the toggling of state was executed. Otherwise false.
		 */
		deactivate: function ( params ) {
			return this._toggleActive( false, params );
		},

		/**
		 * Is called when there's been a change in the Container's expanded state.
		 *
		 * Must be overridden by subclass. Update the Container's UI to reflect the provided active state.
		 *
		 * @abstract
		 *
		 * @return {void}
		 */
		onChangeExpanded: function () {
			throw new Error( 'Must override with subclass.' );
		},

		/**
		 * Handles the toggle logic for expanding and collapsing the Container.
		 *
		 * @param {boolean}  expanded 					The new expanded / collapsed state to apply.
		 * @param {Object}   [params] 					Object containing options for expansion/collapse.
		 * @param {Function} [params.completeCallback]  Function to call when expansion/collapse is complete.
		 *
		 * @return {boolean} False if state already applied or active state is false
		 */
		_toggleExpanded: function( expanded, params ) {
			var instance = this, previousCompleteCallback;
			params = params || {};
			previousCompleteCallback = params.completeCallback;

			// Short-circuit expand() if the instance is not active.
			if ( expanded && ! instance.active() ) {
				return false;
			}

			api.state( 'paneVisible' ).set( true );
			params.completeCallback = function() {
				if ( previousCompleteCallback ) {
					previousCompleteCallback.apply( instance, arguments );
				}
				if ( expanded ) {
					instance.container.trigger( 'expanded' );
				} else {
					instance.container.trigger( 'collapsed' );
				}
			};
			if ( ( expanded && instance.expanded.get() ) || ( ! expanded && ! instance.expanded.get() ) ) {
				params.unchanged = true;
				instance.onChangeExpanded( instance.expanded.get(), params );
				return false;
			} else {
				params.unchanged = false;
				instance.expandedArgumentsQueue.push( params );
				instance.expanded.set( expanded );
				return true;
			}
		},

		/**
		 * Expands the Container.
		 *
		 * @param {Object} [params] Object containing options for expansion/collapse.
		 *
		 * @return {boolean} False if already expanded or if inactive.
		 */
		expand: function ( params ) {
			return this._toggleExpanded( true, params );
		},

		/**
		 * Collapses the Container.
		 *
		 * @param {Object} [params] Object containing options for expansion/collapse.
		 *
		 * @return {boolean} False if already collapsed.
		 */
		collapse: function ( params ) {
			return this._toggleExpanded( false, params );
		},

		/**
		 * Animates the container state change if transitions are supported by the browser.
		 *
		 * @since 4.7.0
		 * @private
		 *
		 * @param {Function} completeCallback Function to be called after transition has completed.
		 *
		 * @return {void}
		 */
		_animateChangeExpanded: function( completeCallback ) {
			// Return if CSS transitions are not supported.
			if ( ! normalizedTransitionendEventName ) {
				if ( completeCallback ) {
					completeCallback();
				}
				return;
			}

			var construct = this,
				content = construct.contentContainer,
				overlay = content.closest( '.wp-full-overlay' ),
				elements, transitionEndCallback, transitionParentPane;

			// Determine set of elements that are affected by the animation.
			elements = overlay.add( content );

			if ( ! construct.panel || '' === construct.panel() ) {
				transitionParentPane = true;
			} else if ( api.panel( construct.panel() ).contentContainer.hasClass( 'skip-transition' ) ) {
				transitionParentPane = true;
			} else {
				transitionParentPane = false;
			}
			if ( transitionParentPane ) {
				elements = elements.add( '#customize-info, .customize-pane-parent' );
			}

			// Handle `transitionEnd` event.
			transitionEndCallback = function( e ) {
				if ( 2 !== e.eventPhase || ! $( e.target ).is( content ) ) {
					return;
				}
				content.off( normalizedTransitionendEventName, transitionEndCallback );
				elements.removeClass( 'busy' );
				if ( completeCallback ) {
					completeCallback();
				}
			};
			content.on( normalizedTransitionendEventName, transitionEndCallback );
			elements.addClass( 'busy' );

			// Prevent screen flicker when pane has been scrolled before expanding.
			_.defer( function() {
				var container = content.closest( '.wp-full-overlay-sidebar-content' ),
					currentScrollTop = container.scrollTop(),
					previousScrollTop = content.data( 'previous-scrollTop' ) || 0,
					expanded = construct.expanded();

				if ( expanded && 0 < currentScrollTop ) {
					content.css( 'top', currentScrollTop + 'px' );
					content.data( 'previous-scrollTop', currentScrollTop );
				} else if ( ! expanded && 0 < currentScrollTop + previousScrollTop ) {
					content.css( 'top', previousScrollTop - currentScrollTop + 'px' );
					container.scrollTop( previousScrollTop );
				}
			} );
		},

		/*
		 * Is documented using @borrows in the constructor.
		 */
		focus: focus,

		/**
		 * Returns the container HTML, generated from its JS template, if it exists.
		 *
		 * @since 4.3.0
		 *
		 * @return {string} The container's template or an empty set of <li>'s.
		 */
		getContainer: function () {
			var template,
				container = this;

			if ( 0 !== $( '#tmpl-' + container.templateSelector ).length ) {
				template = wp.template( container.templateSelector );
			} else {
				template = wp.template( 'customize-' + container.containerType + '-default' );
			}
			if ( template && container.container ) {
				return $.trim( template( _.extend(
					{ id: container.id },
					container.params
				) ) );
			}

			return '<li></li>';
		},

		/**
		 * Finds the content element which is displayed when the section is expanded.
		 *
		 * After a construct is initialized, the return value will be available via the `contentContainer` property.
		 * By default the element will relate it to the parent container with `aria-owns` and then be detached.
		 *
		 * Custom panels and sections without a sliding pane, should just return the content element .
		 * Such non-sliding pane custom sections also need to override the `onChangeExpanded` method to handle animating
		 * the panel/section into and out of view.
		 *
		 * @since 4.7.0
		 *
		 * @return {jQuery} The detached content element.
		 */
		getContent: function() {
			var construct = this,
				container = construct.container,
				content = container.find( '.accordion-section-content, .control-panel-content' ).first(),
				contentId = 'sub-' + container.attr( 'id' ),
				ownedElements = contentId,
				alreadyOwnedElements = container.attr( 'aria-owns' );

			if ( alreadyOwnedElements ) {
				ownedElements = ownedElements + ' ' + alreadyOwnedElements;
			}
			container.attr( 'aria-owns', ownedElements );

			return content.detach().attr( {
				'id': contentId,
				'class': 'customize-pane-child ' + content.attr( 'class' ) + ' ' + container.attr( 'class' )
			} );
		}
	});

	/**
	 * The Section object.
	 */
	api.Section = Container.extend(/** @lends wp.customize.Section.prototype */{
		containerType: 'section',
		containerParent: '#customize-theme-controls',
		containerPaneParent: '.customize-pane-parent',
		defaults: {
			title: '',
			description: '',
			priority: 100,
			type: 'default',
			content: null,
			active: true,
			instanceNumber: null,
			panel: null,
			customizeAction: ''
		},

		/**
		 * Initializes a Section object.
		 *
		 * @since 4.1.0
		 *
		 * @constructs wp.customize.Section
		 * @augments   wp.customize~Container
		 *
		 * @memberOf   wp.customize
		 *
		 * @param {string} 	id 							The ID for the section.
		 * @param {Object} 	options 					The options to use within the Section object.
		 * @param {string} 	options.title 				Title shown when section is collapsed and expanded.
		 * @param {string} 	[options.description] 		Description shown at the top of the section.
		 * @param {number} 	[options.priority=100] 		The sort priority for the section.
		 * @param {string} 	[options.type=default] 		The type of the section. See wp.customize.sectionConstructor.
		 * @param {string} 	[options.content] 			The markup to be used for the section container. If empty, a JS template is used.
		 * @param {boolean}	[options.active=true] 		Whether the section is active or not.
		 * @param {string}  options.panel 				The ID for the panel this section is associated with.
		 * @param {string}  [options.customizeAction] 	Additional context information shown before the section title when expanded.
		 * @param {Object}  [options.params] 			Deprecated wrapper for the above properties.
		 */
		initialize: function ( id, options ) {
			var section = this, params;
			params = options.params || options;

			// Look up the type if one was not supplied.
			if ( ! params.type ) {
				_.find( api.sectionConstructor, function( Constructor, type ) {
					if ( Constructor === section.constructor ) {
						params.type = type;
						return true;
					}
					return false;
				} );
			}

			Container.prototype.initialize.call( section, id, params );

			section.id = id;
			section.panel = new api.Value();
			section.panel.bind( function ( id ) {
				$( section.headContainer ).toggleClass( 'control-subsection', !! id );
			});
			section.panel.set( section.params.panel || '' );
			api.utils.bubbleChildValueChanges( section, [ 'panel' ] );

			section.embed();
			section.deferred.embedded.done( function () {
				section.ready();
			});
		},

		/**
		 * Embeds the container in the DOM when any parent panel is ready.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		embed: function () {
			var inject,
				section = this;

			section.containerParent = api.ensure( section.containerParent );

			// Watch for changes to the panel state.
			inject = function ( panelId ) {
				var parentContainer;
				if ( panelId ) {
					// The panel has been supplied, so wait until the panel object is registered.
					api.panel( panelId, function ( panel ) {
						// The panel has been registered, wait for it to become ready/initialized.
						panel.deferred.embedded.done( function () {
							parentContainer = panel.contentContainer;
							if ( ! section.headContainer.parent().is( parentContainer ) ) {
								parentContainer.append( section.headContainer );
							}
							if ( ! section.contentContainer.parent().is( section.headContainer ) ) {
								section.containerParent.append( section.contentContainer );
							}
							section.deferred.embedded.resolve();
						});
					} );
				} else {
					// There is no panel, so embed the section in the root of the customizer
					parentContainer = api.ensure( section.containerPaneParent );
					if ( ! section.headContainer.parent().is( parentContainer ) ) {
						parentContainer.append( section.headContainer );
					}
					if ( ! section.contentContainer.parent().is( section.headContainer ) ) {
						section.containerParent.append( section.contentContainer );
					}
					section.deferred.embedded.resolve();
				}
			};
			section.panel.bind( inject );
			inject( section.panel.get() ); // Since a section may never get a panel, assume that it won't ever get one.
		},

		/**
		 * Adds behaviors for the accordion section to deal with expanding and collapsing.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		attachEvents: function () {
			var meta, content, section = this;

			if ( section.container.hasClass( 'cannot-expand' ) ) {
				return;
			}

			// Expand/collapse accordion sections on click.
			section.container.find( '.accordion-section-title, .customize-section-back' ).on( 'click keydown', function( event ) {
				if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
					return;
				}
				event.preventDefault(); // Keep this AFTER the key filter above

				if ( section.expanded() ) {
					section.collapse();
				} else {
					section.expand();
				}
			});

			// Handle click events on the help toggle.
			section.container.find( '.customize-section-title .customize-help-toggle' ).on( 'click', function() {

				meta = section.container.find( '.section-meta' );
				if ( meta.hasClass( 'cannot-expand' ) ) {
					return;
				}
				content = meta.find( '.customize-section-description:first' );
				content.toggleClass( 'open' );
				content.slideToggle( section.defaultExpandedArguments.duration, function() {
					content.trigger( 'toggled' );
				} );
				$( this ).attr( 'aria-expanded', function( i, attr ) {
					return 'true' === attr ? 'false' : 'true';
				});
			});
		},

		/**
		 * Returns whether this section has any active controls.
		 *
		 * @since 4.1.0
		 *
		 * @return {boolean} True if the section has active controls.
		 */
		isContextuallyActive: function () {
			var section = this,
				controls = section.controls(),
				activeCount = 0;
			_( controls ).each( function ( control ) {
				if ( control.active() ) {
					activeCount += 1;
				}
			} );
			return ( activeCount !== 0 );
		},

		/**
		 * Gets the controls that are associated with this section, sorted by their priority value.
		 *
		 * @since 4.1.0
		 *
		 * @return {Array} A list of controls associated with this section.
		 */
		controls: function () {
			return this._children( 'section', 'control' );
		},

		/**
		 * Updates the UI to reflect the expanded state.
		 *
		 * @since 4.1.0
		 *
		 * @param {boolean} expanded 	The current state of the Panel object.
		 * @param {Object}  args	 	The arguments to use while updating the panel.
		 *
		 * @return {void}
		 */
		onChangeExpanded: function ( expanded, args ) {
			var section = this,
				container = section.headContainer.closest( '.wp-full-overlay-sidebar-content' ),
				content = section.contentContainer,
				overlay = section.headContainer.closest( '.wp-full-overlay' ),
				backBtn = content.find( '.customize-section-back' ),
				sectionTitle = section.headContainer.find( '.accordion-section-title' ).first(),
				expand, panel;

			if ( expanded && ! content.hasClass( 'open' ) ) {

				if ( args.unchanged ) {
					expand = args.completeCallback;
				} else {
					expand = $.proxy( function() {
						// Animate the expansion of the panel.
						section._animateChangeExpanded( function() {
							sectionTitle.attr( 'tabindex', '-1' );
							backBtn.attr( 'tabindex', '0' );

							backBtn.focus();
							content.css( 'top', '' );
							container.scrollTop( 0 );

							if ( args.completeCallback ) {
								args.completeCallback();
							}
						} );

						content.addClass( 'open' );
						overlay.addClass( 'section-open' );
						api.state( 'expandedSection' ).set( section );
					}, this );
				}

				// Collapse other panels if allowMultiple is false.
				if ( ! args.allowMultiple ) {
					api.section.each( function ( otherSection ) {
						if ( otherSection !== section ) {
							otherSection.collapse( { duration: args.duration } );
						}
					});
				}

				if ( section.panel() ) {
					// Expand the panel.
					api.panel( section.panel() ).expand({
						duration: args.duration,
						completeCallback: expand
					});
				} else {
					// Collapse other panels if allowMultiple is false.
					if ( ! args.allowMultiple ) {
						api.panel.each( function( panel ) {
							panel.collapse();
						});
					}
					expand();
				}

			} else if ( ! expanded && content.hasClass( 'open' ) ) {
				if ( section.panel() ) {
					panel = api.panel( section.panel() );
					if ( panel.contentContainer.hasClass( 'skip-transition' ) ) {
						panel.collapse();
					}
				}

				// Animate the collapsing of the panel.
				section._animateChangeExpanded( function() {
					backBtn.attr( 'tabindex', '-1' );
					sectionTitle.attr( 'tabindex', '0' );

					sectionTitle.focus();
					content.css( 'top', '' );

					if ( args.completeCallback ) {
						args.completeCallback();
					}
				} );

				content.removeClass( 'open' );
				overlay.removeClass( 'section-open' );
				if ( section === api.state( 'expandedSection' ).get() ) {
					api.state( 'expandedSection' ).set( false );
				}

			} else {
				if ( args.completeCallback ) {
					args.completeCallback();
				}
			}
		}
	});

	/**
	 * The ThemesSection object.
	 */
	api.ThemesSection = api.Section.extend(/** @lends wp.customize.ThemesSection.prototype */{
		currentTheme: '',
		overlay: '',
		template: '',
		screenshotQueue: null,
		$window: null,
		$body: null,
		loaded: 0,
		loading: false,
		fullyLoaded: false,
		term: '',
		tags: '',
		nextTerm: '',
		nextTags: '',
		filtersHeight: 0,
		headerContainer: null,
		updateCountDebounced: null,

		/**
		 * Initializes the base class for the ThemesSection.
		 *
		 * Loads themes by category and also handles the theme-details view rendering and navigation.
		 *
		 * @since 4.9.0
		 *
		 * @constructs wp.customize.ThemesSection
		 * @augments   wp.customize.Section
		 *
		 * @memberOf   wp.customize
		 *
		 * @param {string} id       The ID for the section.
		 * @param {Object} options  The options to use within the ThemesSection object.
		 */
		initialize: function( id, options ) {
			var section = this;
			section.headerContainer = $();
			section.$window = $( window );
			section.$body = $( document.body );
			api.Section.prototype.initialize.call( section, id, options );
			section.updateCountDebounced = _.debounce( section.updateCount, 500 );
		},

		/**
		 * Embeds the section in the DOM when the themes panel is ready.
		 *
		 * Inserts the section before the themes container and assumes that a themes section is within a panel,
		 * but not necessarily the themes panel.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		embed: function() {
			var inject,
				section = this;

			// Watch for changes to the panel state.
			inject = function( panelId ) {
				var parentContainer;
				api.panel( panelId, function( panel ) {

					// The panel has been registered, wait for it to become ready/initialized.
					panel.deferred.embedded.done( function() {
						parentContainer = panel.contentContainer;
						if ( ! section.headContainer.parent().is( parentContainer ) ) {
							parentContainer.find( '.customize-themes-full-container-container' ).before( section.headContainer );
						}
						if ( ! section.contentContainer.parent().is( section.headContainer ) ) {
							section.containerParent.append( section.contentContainer );
						}
						section.deferred.embedded.resolve();
					});
				} );
			};
			section.panel.bind( inject );

			// Since a section may never get a panel, assume that it won't ever get one.
			inject( section.panel.get() );
		},

		/**
		 * Fires when the ThemesSection is considered DOM-ready.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		ready: function() {
			var section = this;
			section.overlay = section.container.find( '.theme-overlay' );
			section.template = wp.template( 'customize-themes-details-view' );

			// Bind global keyboard events.
			section.container.on( 'keydown', function( event ) {
				if ( ! section.overlay.find( '.theme-wrap' ).is( ':visible' ) ) {
					return;
				}

				// Pressing the right arrow key fires a theme:next event.
				if ( 39 === event.keyCode ) {
					section.nextTheme();
				}

				// Pressing the left arrow key fires a theme:previous event.
				if ( 37 === event.keyCode ) {
					section.previousTheme();
				}

				// Pressing the escape key fires a theme:collapse event.
				if ( 27 === event.keyCode ) {
					if ( section.$body.hasClass( 'modal-open' ) ) {

						// Escape from the details modal.
						section.closeDetails();
					} else {

						// Escape from the infinite scroll list.
						section.headerContainer.find( '.customize-themes-section-title' ).focus();
					}

					// Prevent section from being collapsed.
					event.stopPropagation();
				}
			});

			section.renderScreenshots = _.throttle( section.renderScreenshots, 100 );

			_.bindAll( section, 'renderScreenshots', 'loadMore', 'checkTerm', 'filtersChecked' );
		},

		/**
		 * Checks if the ThemesSection is contextually active.
		 *
		 * Overrides the Section.isContextuallyActive method.
		 **
		 * Ignores the active states of the contained theme controls and just
		 * use the section's own active state instead. This prevents empty search
		 * results for theme sections from causing the section to become inactive.
		 *
		 * @since 4.2.0
		 *
		 * @return {boolean} Whether or not the ThemesSection is considered contextually active.
		 */
		isContextuallyActive: function () {
			return this.active();
		},

		/**
		 * Attaches events to the ThemesSection.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		attachEvents: function () {
			var section = this, debounced;

			// Expand/collapse accordion sections on click.
			section.container.find( '.customize-section-back' ).on( 'click keydown', function( event ) {
				if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
					return;
				}

				// Keep this AFTER the key filter above
				event.preventDefault();
				section.collapse();
			});

			section.headerContainer = $( '#accordion-section-' + section.id );

			// Expand section/panel. Only collapse when opening another section.
			section.headerContainer.on( 'click', '.customize-themes-section-title', function() {

				// Toggle accordion filters under section headers.
				if ( section.headerContainer.find( '.filter-details' ).length ) {
					section.headerContainer.find( '.customize-themes-section-title' )
						.toggleClass( 'details-open' )
						.attr( 'aria-expanded', function( i, attr ) {
							return 'true' === attr ? 'false' : 'true';
						});
					section.headerContainer.find( '.filter-details' ).slideToggle( 180 );
				}

				// Open the section.
				if ( ! section.expanded() ) {
					section.expand();
				}
			});

			// Preview installed themes.
			section.container.on( 'click', '.theme-actions .preview-theme', function() {
				api.panel( 'themes' ).loadThemePreview( $( this ).data( 'slug' ) );
			});

			// Theme navigation in details view.
			section.container.on( 'click', '.left', function() {
				section.previousTheme();
			});

			section.container.on( 'click', '.right', function() {
				section.nextTheme();
			});

			section.container.on( 'click', '.theme-backdrop, .close', function() {
				section.closeDetails();
			});

			if ( 'local' === section.params.filter_type ) {

				// Filter-search all theme objects loaded in the section.
				section.container.on( 'input', '.wp-filter-search-themes', function( event ) {
					section.filterSearch( event.currentTarget.value );
				});

			} else if ( 'remote' === section.params.filter_type ) {

				// Event listeners for remote queries with user-entered terms.
				// Wait until there is no input for 500 milliseconds to initiate a search.
				debounced = _.debounce( section.checkTerm, 500 );
				section.contentContainer.on( 'input', '.wp-filter-search', function() {
					if ( ! api.panel( 'themes' ).expanded() ) {
						return;
					}
					debounced( section );
					if ( ! section.expanded() ) {
						section.expand();
					}
				});

				// Feature filters.
				section.contentContainer.on( 'click', '.filter-group input', function() {
					section.filtersChecked();
					section.checkTerm( section );
				});
			}

			// Toggle feature filters.
			section.contentContainer.on( 'click', '.feature-filter-toggle', function( e ) {
				var $themeContainer = $( '.customize-themes-full-container' ),
					$filterToggle = $( e.currentTarget );
				section.filtersHeight = $filterToggle.parent().next( '.filter-drawer' ).height();

				if ( 0 < $themeContainer.scrollTop() ) {
					$themeContainer.animate( { scrollTop: 0 }, 400 );

					if ( $filterToggle.hasClass( 'open' ) ) {
						return;
					}
				}

				$filterToggle
					.toggleClass( 'open' )
					.attr( 'aria-expanded', function( i, attr ) {
						return 'true' === attr ? 'false' : 'true';
					})
					.parent().next( '.filter-drawer' ).slideToggle( 180, 'linear' );

				if ( $filterToggle.hasClass( 'open' ) ) {
					var marginOffset = 1018 < window.innerWidth ? 50 : 76;

					section.contentContainer.find( '.themes' ).css( 'margin-top', section.filtersHeight + marginOffset );
				} else {
					section.contentContainer.find( '.themes' ).css( 'margin-top', 0 );
				}
			});

			// Setup section cross-linking.
			section.contentContainer.on( 'click', '.no-themes-local .search-dotorg-themes', function() {
				api.section( 'wporg_themes' ).focus();
			});

			function updateSelectedState() {
				var el = section.headerContainer.find( '.customize-themes-section-title' );
				el.toggleClass( 'selected', section.expanded() );
				el.attr( 'aria-expanded', section.expanded() ? 'true' : 'false' );
				if ( ! section.expanded() ) {
					el.removeClass( 'details-open' );
				}
			}
			section.expanded.bind( updateSelectedState );
			updateSelectedState();

			// Move section controls to the themes area.
			api.bind( 'ready', function () {
				section.contentContainer = section.container.find( '.customize-themes-section' );
				section.contentContainer.appendTo( $( '.customize-themes-full-container' ) );
				section.container.add( section.headerContainer );
			});
		},

		/**
		 * Updates the UI to reflect the expanded state.
		 *
		 * @since 4.2.0
		 *
		 * @param {boolean}  expanded               Whether or not the section was expanded.
		 * @param {Object}   args                   The arguments to use within the updating of the UI.
		 * @param {boolean}  args.unchanged         Whether the state remains unchanged.
		 * @param {Function} args.completeCallback  Function to call when the updating of the UI is completed.
		 *
		 * @return {void}
		 */
		onChangeExpanded: function ( expanded, args ) {

			// Note: there is a second argument 'args' passed
			var section = this,
				container = section.contentContainer.closest( '.customize-themes-full-container' );

			// Immediately call the complete callback if there were no changes.
			if ( args.unchanged ) {
				if ( args.completeCallback ) {
					args.completeCallback();
				}
				return;
			}

			function expand() {

				// Try to load controls if none are loaded yet.
				if ( 0 === section.loaded ) {
					section.loadThemes();
				}

				// Collapse any sibling sections/panels.
				api.section.each( function ( otherSection ) {
					var searchTerm;

					if ( otherSection !== section ) {

						// Try to sync the current search term to the new section.
						if ( 'themes' === otherSection.params.type ) {
							searchTerm = otherSection.contentContainer.find( '.wp-filter-search' ).val();
							section.contentContainer.find( '.wp-filter-search' ).val( searchTerm );

							// Directly initialize an empty remote search to avoid a race condition.
							if ( '' === searchTerm && '' !== section.term && 'local' !== section.params.filter_type ) {
								section.term = '';
								section.initializeNewQuery( section.term, section.tags );
							} else {
								if ( 'remote' === section.params.filter_type ) {
									section.checkTerm( section );
								} else if ( 'local' === section.params.filter_type ) {
									section.filterSearch( searchTerm );
								}
							}
							otherSection.collapse( { duration: args.duration } );
						}
					}
				});

				section.contentContainer.addClass( 'current-section' );
				container.scrollTop();

				container.on( 'scroll', _.throttle( section.renderScreenshots, 300 ) );
				container.on( 'scroll', _.throttle( section.loadMore, 300 ) );

				if ( args.completeCallback ) {
					args.completeCallback();
				}

				// Show this section's count.
				section.updateCount();
			}

			if ( expanded ) {
				if ( section.panel() && api.panel.has( section.panel() ) ) {
					api.panel( section.panel() ).expand({
						duration: args.duration,
						completeCallback: expand
					});
				} else {
					expand();
				}
			} else {
				section.contentContainer.removeClass( 'current-section' );

				// Always hide, even if they don't exist or are already hidden.
				section.headerContainer.find( '.filter-details' ).slideUp( 180 );

				container.off( 'scroll' );

				if ( args.completeCallback ) {
					args.completeCallback();
				}
			}
		},

		/**
		 * Returns the section's content element without detaching from the parent.
		 *
		 * @since 4.9.0
		 *
		 * @return {jQuery}
		 */
		getContent: function() {
			return this.container.find( '.control-section-content' );
		},

		/**
		 * Loads theme data via AJAX and add themes to the section as controls.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		loadThemes: function() {
			var section = this, params, page, request;

			if ( section.loading ) {
				// We're already loading a batch of themes.
				return;
			}

			// Parameters for every API query. Additional params are set in PHP.
			page = Math.ceil( section.loaded / 100 ) + 1;
			params = {
				'nonce': api.settings.nonce.switch_themes,
				'wp_customize': 'on',
				'theme_action': section.params.action,
				'customized_theme': api.settings.theme.stylesheet,
				'page': page
			};

			// Add fields for remote filtering.
			if ( 'remote' === section.params.filter_type ) {
				params.search = section.term;
				params.tags = section.tags;
			}

			// Load themes.
			section.headContainer.closest( '.wp-full-overlay' ).addClass( 'loading' );
			section.loading = true;
			section.container.find( '.no-themes' ).hide();
			request = wp.ajax.post( 'customize_load_themes', params );
			request.done(function( data ) {
				var themes = data.themes;

				// Stop and try again if the term changed while loading.
				if ( '' !== section.nextTerm || '' !== section.nextTags ) {
					if ( section.nextTerm ) {
						section.term = section.nextTerm;
					}
					if ( section.nextTags ) {
						section.tags = section.nextTags;
					}
					section.nextTerm = '';
					section.nextTags = '';
					section.loading = false;
					section.loadThemes();
					return;
				}

				if ( 0 !== themes.length ) {

					section.loadControls( themes, page );

					if ( 1 === page ) {

						// Pre-load the first 3 theme screenshots.
						_.each( section.controls().slice( 0, 3 ), function( control ) {
							var img, src = control.params.theme.screenshot[0];
							if ( src ) {
								img = new Image();
								img.src = src;
							}
						});
						if ( 'local' !== section.params.filter_type ) {
							wp.a11y.speak( api.settings.l10n.themeSearchResults.replace( '%d', data.info.results ) );
						}
					}

					// Wait for the controls to become visible.
					_.delay( section.renderScreenshots, 100 );

					// If we have less than the requested 100 themes, it's the end of the list.
					if ( 'local' === section.params.filter_type || 100 > themes.length ) {
						section.fullyLoaded = true;
					}
				} else {
					if ( 0 === section.loaded ) {
						section.container.find( '.no-themes' ).show();
						wp.a11y.speak( section.container.find( '.no-themes' ).text() );
					} else {
						section.fullyLoaded = true;
					}
				}
				if ( 'local' === section.params.filter_type ) {
					// Count of visible theme controls.
					section.updateCount();
				} else {
					// Total number of results including pages not yet loaded.
					section.updateCount( data.info.results );
				}
				// Hide error notice in case it was previously shown.
				section.container.find( '.unexpected-error' ).hide();

				/**
				 * This cannot run on request.always, as section.loading may turn false before
				 * the new controls load in the success case.
				 */
				section.headContainer.closest( '.wp-full-overlay' ).removeClass( 'loading' );
				section.loading = false;
			});
			request.fail(function( data ) {
				if ( 'undefined' === typeof data ) {
					section.container.find( '.unexpected-error' ).show();
					wp.a11y.speak( section.container.find( '.unexpected-error' ).text() );
				} else if ( 'undefined' !== typeof console && console.error ) {
					console.error( data );
				}

				/**
				 * This cannot run on request.always, as section.loading may turn false before
				 * the new controls load in the success case.
				 */
				section.headContainer.closest( '.wp-full-overlay' ).removeClass( 'loading' );
				section.loading = false;
			});
		},

		/**
		 * Loads controls into the section from data received from loadThemes().
		 *
		 * @since 4.9.0
		 *
		 * @param {Array}  themes Array of theme data to create controls with.
		 * @param {number} page   Page of results being loaded.
		 *
		 * @return {void}
		 */
		loadControls: function( themes, page ) {
			var newThemeControls = [],
				section = this;

			// Add controls for each theme.
			_.each( themes, function( theme ) {
				var themeControl = new api.controlConstructor.theme( section.params.action + '_theme_' + theme.id, {
					type: 'theme',
					section: section.params.id,
					theme: theme,
					priority: section.loaded + 1
				} );

				api.control.add( themeControl );
				newThemeControls.push( themeControl );
				section.loaded = section.loaded + 1;
			});

			if ( 1 !== page ) {
				// Add new themes to the screenshot queue.
				Array.prototype.push.apply( section.screenshotQueue, newThemeControls );
			}
		},

		/**
		 * Determines whether more themes should be loaded, and loads them.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		loadMore: function() {
			var section = this, container, bottom, threshold;
			if ( ! section.fullyLoaded && ! section.loading ) {
				container = section.container.closest( '.customize-themes-full-container' );

				bottom = container.scrollTop() + container.height();

				/**
				 * Use a fixed distance to the bottom of loaded results to avoid unnecessarily loading results sooner
				 * when using a percentage of scroll distance.
				 */
				threshold = container.prop( 'scrollHeight' ) - 3000;

				if ( bottom > threshold ) {
					section.loadThemes();
				}
			}
		},

		/**
		 * Handles events for search input that filters visible controls.
		 *
		 * @since 4.9.0
		 *
		 * @param {string} term The raw search input value.
		 *
		 * @return {void}
		 */
		filterSearch: function( term ) {
			var count = 0,
				visible = false,
				section = this,
				noFilter = ( api.section.has( 'wporg_themes' ) && 'remote' !== section.params.filter_type ) ? '.no-themes-local' : '.no-themes',
				controls = section.controls(),
				terms;

			if ( section.loading ) {
				return;
			}

			// Standardize search term format and split into an array of individual words.
			terms = term.toLowerCase().trim().replace( /-/g, ' ' ).split( ' ' );

			_.each( controls, function( control ) {
				// Shows/hides and sorts control based on the applicability of the search term.
				visible = control.filter( terms );
				if ( visible ) {
					count = count + 1;
				}
			});

			if ( 0 === count ) {
				section.container.find( noFilter ).show();
				wp.a11y.speak( section.container.find( noFilter ).text() );
			} else {
				section.container.find( noFilter ).hide();
			}

			section.renderScreenshots();
			api.reflowPaneContents();

			// Update theme count.
			section.updateCountDebounced( count );
		},

		/**
		 * Handles events for search input that determines if the terms have changed and loads new controls as needed.
		 *
		 * @since 4.9.0
		 *
		 * @param {wp.customize.ThemesSection} section The current theme section, passed through the debouncer.
		 *
		 * @return {void}
		 */
		checkTerm: function( section ) {
			var newTerm;
			if ( 'remote' === section.params.filter_type ) {
				newTerm = section.contentContainer.find( '.wp-filter-search' ).val();
				if ( section.term !== newTerm.trim() ) {
					section.initializeNewQuery( newTerm, section.tags );
				}
			}
		},

		/**
		 * Checks for filters checked in the feature filter list and initialize a new query.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		filtersChecked: function() {
			var section = this,
				items = section.container.find( '.filter-group' ).find( ':checkbox' ),
				tags = [];

			_.each( items.filter( ':checked' ), function( item ) {
				tags.push( $( item ).prop( 'value' ) );
			});

			// When no filters are checked, restore initial state. Update filter count.
			if ( 0 === tags.length ) {
				tags = '';
				section.contentContainer.find( '.feature-filter-toggle .filter-count-0' ).show();
				section.contentContainer.find( '.feature-filter-toggle .filter-count-filters' ).hide();
			} else {
				section.contentContainer.find( '.feature-filter-toggle .theme-filter-count' ).text( tags.length );
				section.contentContainer.find( '.feature-filter-toggle .filter-count-0' ).hide();
				section.contentContainer.find( '.feature-filter-toggle .filter-count-filters' ).show();
			}

			// Check whether tags have changed, and either load or queue them.
			if ( ! _.isEqual( section.tags, tags ) ) {
				if ( section.loading ) {
					section.nextTags = tags;
				} else {
					if ( 'remote' === section.params.filter_type ) {
						section.initializeNewQuery( section.term, tags );
					} else if ( 'local' === section.params.filter_type ) {
						section.filterSearch( tags.join( ' ' ) );
					}
				}
			}
		},

		/**
		 * Resets the current query and load new results.
		 *
		 * @since 4.9.0
		 *
		 * @param {string} newTerm The new term to load.
		 * @param {Array}  newTags The new tags to load.
		 *
		 * @return {void}
		 */
		initializeNewQuery: function( newTerm, newTags ) {
			var section = this;

			// Clear the controls in the section.
			_.each( section.controls(), function( control ) {
				control.container.remove();
				api.control.remove( control.id );
			});
			section.loaded = 0;
			section.fullyLoaded = false;
			section.screenshotQueue = null;

			// Run a new query, with loadThemes handling paging, etc.
			if ( ! section.loading ) {
				section.term = newTerm;
				section.tags = newTags;
				section.loadThemes();
			} else {
				// This will reload from loadThemes() with the newest term once the current batch is loaded.
				section.nextTerm = newTerm;

				// This will reload from loadThemes() with the newest tags once the current batch is loaded.
				section.nextTags = newTags;
			}
			if ( ! section.expanded() ) {
				// Expand the section if it isn't expanded.
				section.expand();
			}
		},

		/**
		 * Renders the control's screenshot if the control comes into view.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		renderScreenshots: function() {
			var section = this;

			// Fill queue initially, or check for more if empty.
			if ( null === section.screenshotQueue || 0 === section.screenshotQueue.length ) {

				// Add controls that haven't had their screenshots rendered.
				section.screenshotQueue = _.filter( section.controls(), function( control ) {
					return ! control.screenshotRendered;
				});
			}

			// Are all screenshots rendered (for now)?
			if ( ! section.screenshotQueue.length ) {
				return;
			}

			section.screenshotQueue = _.filter( section.screenshotQueue, function( control ) {
				var $imageWrapper = control.container.find( '.theme-screenshot' ),
					$image = $imageWrapper.find( 'img' );

				if ( ! $image.length ) {
					return false;
				}

				if ( $image.is( ':hidden' ) ) {
					return true;
				}

				// Based on unveil.js.
				var wt = section.$window.scrollTop(),
					wb = wt + section.$window.height(),
					et = $image.offset().top,
					ih = $imageWrapper.height(),
					eb = et + ih,
					threshold = ih * 3,
					inView = eb >= wt - threshold && et <= wb + threshold;

				if ( inView ) {
					control.container.trigger( 'render-screenshot' );
				}

				// If the image is in view return false so it's cleared from the queue.
				return ! inView;
			} );
		},

		/**
		 * Counts the amount of visible controls.
		 *
		 * @since 4.9.0
		 *
		 * @return {number} The amount of visible controls.
		 */
		getVisibleCount: function() {
			return this.contentContainer.find( 'li.customize-control:visible' ).length;
		},

		/**
		 * Updates the number of themes in the section.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		updateCount: function( count ) {
			var section = this, countEl, displayed;

			if ( ! count && 0 !== count ) {
				count = section.getVisibleCount();
			}

			displayed = section.contentContainer.find( '.themes-displayed' );
			countEl = section.contentContainer.find( '.theme-count' );

			if ( 0 === count ) {
				countEl.text( '0' );
			} else {

				// Animate the count change for emphasis.
				displayed.fadeOut( 180, function() {
					countEl.text( count );
					displayed.fadeIn( 180 );
				} );
				wp.a11y.speak( api.settings.l10n.announceThemeCount.replace( '%d', count ) );
			}
		},

		/**
		 * Advances the modal to the next theme.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		nextTheme: function () {
			var section = this;
			if ( section.getNextTheme() ) {
				section.showDetails( section.getNextTheme(), function() {
					section.overlay.find( '.right' ).focus();
				} );
			}
		},

		/**
		 * Gets the next theme model.
		 *
		 * @since 4.2.0
		 *
		 * @return {wp.customize.ThemeControl|boolean} The next theme. Returns false if there is no next theme.
		 */
		getNextTheme: function () {
			var section = this, control, nextControl, sectionControls, i;
			control = api.control( section.params.action + '_theme_' + section.currentTheme );
			sectionControls = section.controls();
			i = _.indexOf( sectionControls, control );
			if ( -1 === i ) {
				return false;
			}

			nextControl = sectionControls[ i + 1 ];
			if ( ! nextControl ) {
				return false;
			}
			return nextControl.params.theme;
		},

		/**
		 * Advances the modal to the previous theme.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		previousTheme: function () {
			var section = this;
			if ( section.getPreviousTheme() ) {
				section.showDetails( section.getPreviousTheme(), function() {
					section.overlay.find( '.left' ).focus();
				} );
			}
		},

		/**
		 * Gets the previous theme model.
		 *
		 * @since 4.2.0
		 *
		 * @return {wp.customize.ThemeControl|boolean} The previous theme. Returns false if there is no previous theme.
		 */
		getPreviousTheme: function () {
			var section = this, control, nextControl, sectionControls, i;
			control = api.control( section.params.action + '_theme_' + section.currentTheme );
			sectionControls = section.controls();
			i = _.indexOf( sectionControls, control );
			if ( -1 === i ) {
				return false;
			}

			nextControl = sectionControls[ i - 1 ];
			if ( ! nextControl ) {
				return false;
			}
			return nextControl.params.theme;
		},

		/**
		 * Disables buttons when we're viewing the first or last theme.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		updateLimits: function () {
			if ( ! this.getNextTheme() ) {
				this.overlay.find( '.right' ).addClass( 'disabled' );
			}
			if ( ! this.getPreviousTheme() ) {
				this.overlay.find( '.left' ).addClass( 'disabled' );
			}
		},

		/**
		 * Loads the theme preview.
		 *
		 * @since 4.7.0
		 *
		 * @access public
		 *
		 * @deprecated
		 *
		 * @param {string} themeId The Theme ID to load.
		 *
		 * @return {jQuery.promise} Promise that resolves to the loaded theme.
		 */
		loadThemePreview: function( themeId ) {
			return api.ThemesPanel.prototype.loadThemePreview.call( this, themeId );
		},

		/**
		 * Renders and shows the theme details for a given theme model.
		 *
		 * @since 4.2.0
		 *
		 * @param {Object}   theme      The theme to get the details from.
		 * @param {Function} [callback] Callback that gets called once the details have been shown.
		 *
		 * @return {void}
		 */
		showDetails: function ( theme, callback ) {
			var section = this, panel = api.panel( 'themes' );
			section.currentTheme = theme.id;
			section.overlay.html( section.template( theme ) )
				.fadeIn( 'fast' )
				.focus();

			function disableSwitchButtons() {
				return ! panel.canSwitchTheme( theme.id );
			}

			// Temporary special function since supplying SFTP credentials does not work yet. See #42184.
			function disableInstallButtons() {
				return disableSwitchButtons() || false === api.settings.theme._canInstall || true === api.settings.theme._filesystemCredentialsNeeded;
			}

			section.overlay.find( 'button.preview, button.preview-theme' ).toggleClass( 'disabled', disableSwitchButtons() );
			section.overlay.find( 'button.theme-install' ).toggleClass( 'disabled', disableInstallButtons() );

			section.$body.addClass( 'modal-open' );
			section.containFocus( section.overlay );
			section.updateLimits();
			wp.a11y.speak( api.settings.l10n.announceThemeDetails.replace( '%s', theme.name ) );
			if ( callback ) {
				callback();
			}
		},

		/**
		 * Closes the theme details modal.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		closeDetails: function () {
			var section = this;
			section.$body.removeClass( 'modal-open' );
			section.overlay.fadeOut( 'fast' );
			api.control( section.params.action + '_theme_' + section.currentTheme ).container.find( '.theme' ).focus();
		},

		/**
		 * Keeps tab focus within the theme details modal.
		 *
		 * @since 4.2.0
		 *
		 * @param {jQuery} el The element to keep the focus on.
		 *
		 * @return {void}
		 */
		containFocus: function( el ) {
			var tabbables;

			el.on( 'keydown', function( event ) {

				// Return if it's not the tab key
				// When navigating with prev/next focus is already handled
				if ( 9 !== event.keyCode ) {
					return;
				}

				// uses jQuery UI to get the tabbable elements
				tabbables = $( ':tabbable', el );

				// Keep focus within the overlay
				if ( tabbables.last()[0] === event.target && ! event.shiftKey ) {
					tabbables.first().focus();
					return false;
				} else if ( tabbables.first()[0] === event.target && event.shiftKey ) {
					tabbables.last().focus();
					return false;
				}
			});
		}
	});

	/**
	 * The OuterSection object.
	 */
	api.OuterSection = api.Section.extend(/** @lends wp.customize.OuterSection.prototype */{

		/**
		 * Initializes the base class for the OuterSection.
		 *
		 * Creates section outside of the sidebar. There is no UI to trigger collapse/expand so
		 * it would require custom handling.
		 *
		 * @since 4.9.0
		 *
		 * @constructs wp.customize.OuterSection
		 * @augments   wp.customize.Section
		 *
		 * @memberOf   wp.customize
		 */
		initialize: function() {
			var section = this;
			section.containerParent = '#customize-outer-theme-controls';
			section.containerPaneParent = '.customize-outer-pane-parent';
			api.Section.prototype.initialize.apply( section, arguments );
		},

		/**
		 * Overrides api.Section.prototype.onChangeExpanded to prevent collapse/expand effect
		 * on other sections and panels.
		 *
		 * @since 4.9.0
		 *
		 * @param {boolean}  expanded                The expanded state to transition to.
		 * @param {Object}   [args]                  The arguments to use within the updating of the UI.
		 * @param {boolean}  [args.unchanged]        Whether the state remains unchanged.
		 * @param {Function} [args.completeCallback] Function to call when the slideUp/slideDown has completed.
		 * @param {Object}   [args.duration]         The duration of the animation.
		 *
		 * @return {void}
		 */
		onChangeExpanded: function( expanded, args ) {
			var section = this,
				container = section.headContainer.closest( '.wp-full-overlay-sidebar-content' ),
				content = section.contentContainer,
				backBtn = content.find( '.customize-section-back' ),
				sectionTitle = section.headContainer.find( '.accordion-section-title' ).first(),
				body = $( document.body ),
				expand, panel;

			body.toggleClass( 'outer-section-open', expanded );
			section.container.toggleClass( 'open', expanded );
			section.container.removeClass( 'busy' );
			api.section.each( function( _section ) {
				if ( 'outer' === _section.params.type && _section.id !== section.id ) {
					_section.container.removeClass( 'open' );
				}
			} );

			if ( expanded && ! content.hasClass( 'open' ) ) {

				if ( args.unchanged ) {
					expand = args.completeCallback;
				} else {
					expand = $.proxy( function() {
						section._animateChangeExpanded( function() {
							sectionTitle.attr( 'tabindex', '-1' );
							backBtn.attr( 'tabindex', '0' );

							backBtn.focus();
							content.css( 'top', '' );
							container.scrollTop( 0 );

							if ( args.completeCallback ) {
								args.completeCallback();
							}
						} );

						content.addClass( 'open' );
					}, this );
				}

				if ( section.panel() ) {
					api.panel( section.panel() ).expand({
						duration: args.duration,
						completeCallback: expand
					});
				} else {
					expand();
				}

			} else if ( ! expanded && content.hasClass( 'open' ) ) {
				if ( section.panel() ) {
					panel = api.panel( section.panel() );
					if ( panel.contentContainer.hasClass( 'skip-transition' ) ) {
						panel.collapse();
					}
				}
				section._animateChangeExpanded( function() {
					backBtn.attr( 'tabindex', '-1' );
					sectionTitle.attr( 'tabindex', '0' );

					sectionTitle.focus();
					content.css( 'top', '' );

					if ( args.completeCallback ) {
						args.completeCallback();
					}
				} );

				content.removeClass( 'open' );

			} else {
				if ( args.completeCallback ) {
					args.completeCallback();
				}
			}
		}
	});

	/**
	 * The Panel object.
	 */
	api.Panel = Container.extend(/** @lends wp.customize.Panel.prototype */{
		containerType: 'panel',

		/**
		 * Initializes the base class for the Panel.
		 *
		 * @since 4.1.0
		 *
		 * @constructs wp.customize.Panel
		 * @augments   wp.customize~Container
		 *
		 * @memberOf   wp.customize
		 *
		 * @param {string}  id                     The ID for the panel.
		 * @param {Object}  options                The options to use within the Panel object.
		 * @param {string}  options.title          Title shown when the panel is collapsed and expanded.
		 * @param {string}  [options.description]  Description shown at the top of the panel.
		 * @param {number}  [options.priority=100] The sort priority for the panel.
		 * @param {string}  [options.type=default] The type of the panel. See wp.customize.panelConstructor.
		 * @param {string}  [options.content]      The markup to be used for the panel container. If empty, a JS template is used.
		 * @param {boolean} [options.active=true]  Whether the panel is active or not.
		 * @param {Object}  [options.params]       Deprecated wrapper for the above properties.
		 */
		initialize: function ( id, options ) {
			var panel = this, params;
			params = options.params || options;

			// Look up the type if one was not supplied.
			if ( ! params.type ) {
				_.find( api.panelConstructor, function( Constructor, type ) {
					if ( Constructor === panel.constructor ) {
						params.type = type;
						return true;
					}
					return false;
				} );
			}

			Container.prototype.initialize.call( panel, id, params );

			panel.embed();
			panel.deferred.embedded.done( function () {
				panel.ready();
			});
		},

		/**
		 * Embeds the container in the DOM when any parent panel is ready.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		embed: function () {
			var panel = this,
				container = $( '#customize-theme-controls' ),
				parentContainer = $( '.customize-pane-parent' ); // @todo This should be defined elsewhere, and to be configurable

			if ( ! panel.headContainer.parent().is( parentContainer ) ) {
				parentContainer.append( panel.headContainer );
			}
			if ( ! panel.contentContainer.parent().is( panel.headContainer ) ) {
				container.append( panel.contentContainer );
			}
			panel.renderContent();

			panel.deferred.embedded.resolve();
		},

		/**
		 * Attaches events to the Panel.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		attachEvents: function () {
			var meta, panel = this;

			// Expand/Collapse accordion sections on click.
			panel.headContainer.find( '.accordion-section-title' ).on( 'click keydown', function( event ) {
				if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
					return;
				}

				// Keep this AFTER the key filter above.
				event.preventDefault();

				if ( ! panel.expanded() ) {
					panel.expand();
				}
			});

			// Close panel.
			panel.container.find( '.customize-panel-back' ).on( 'click keydown', function( event ) {
				if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
					return;
				}

				// Keep this AFTER the key filter above.
				event.preventDefault();

				if ( panel.expanded() ) {
					panel.collapse();
				}
			});

			meta = panel.container.find( '.panel-meta:first' );

			meta.find( '> .accordion-section-title .customize-help-toggle' ).on( 'click', function() {
				if ( meta.hasClass( 'cannot-expand' ) ) {
					return;
				}

				var content = meta.find( '.customize-panel-description:first' );
				if ( meta.hasClass( 'open' ) ) {
					meta.toggleClass( 'open' );
					content.slideUp( panel.defaultExpandedArguments.duration, function() {
						content.trigger( 'toggled' );
					} );
					$( this ).attr( 'aria-expanded', false );
				} else {
					content.slideDown( panel.defaultExpandedArguments.duration, function() {
						content.trigger( 'toggled' );
					} );
					meta.toggleClass( 'open' );
					$( this ).attr( 'aria-expanded', true );
				}
			});

		},

		/**
		 * Gets the sections that are associated with this panel, sorted by their priority value.
		 *
		 * @since 4.1.0
		 *
		 * @return {Array} The sections associated with the panel.
		 */
		sections: function () {
			return this._children( 'panel', 'section' );
		},

		/**
		 * Returns whether this panel has any active sections.
		 *
		 * @since 4.1.0
		 *
		 * @return {boolean} Whether there are any contextually active sections.
		 */
		isContextuallyActive: function () {
			var panel = this,
				sections = panel.sections(),
				activeCount = 0;
			_( sections ).each( function ( section ) {
				if ( section.active() && section.isContextuallyActive() ) {
					activeCount += 1;
				}
			} );
			return ( activeCount !== 0 );
		},

		/**
		 * Updates the UI to reflect the expanded state.
		 *
		 * @since 4.1.0
		 *
		 * @param {boolean}  expanded               Whether or not the section was expanded.
		 * @param {Object}   args                   The arguments to use within the updating of the UI.
		 * @param {boolean}  args.unchanged         Whether the state remains unchanged.
		 * @param {Function} args.completeCallback  Function to call when the updating of the UI is completed.
		 *
		 * @return {void}
		 */
		onChangeExpanded: function ( expanded, args ) {

			// Immediately call the complete callback if there were no changes.
			if ( args.unchanged ) {
				if ( args.completeCallback ) {
					args.completeCallback();
				}
				return;
			}

			// Note: there is a second argument 'args' passed.
			var panel = this,
				accordionSection = panel.contentContainer,
				overlay = accordionSection.closest( '.wp-full-overlay' ),
				container = accordionSection.closest( '.wp-full-overlay-sidebar-content' ),
				topPanel = panel.headContainer.find( '.accordion-section-title' ),
				backBtn = accordionSection.find( '.customize-panel-back' ),
				childSections = panel.sections(),
				skipTransition;

			if ( expanded && ! accordionSection.hasClass( 'current-panel' ) ) {
				// Collapse any sibling sections/panels.
				api.section.each( function ( section ) {
					if ( panel.id !== section.panel() ) {
						section.collapse( { duration: 0 } );
					}
				});
				api.panel.each( function ( otherPanel ) {
					if ( panel !== otherPanel ) {
						otherPanel.collapse( { duration: 0 } );
					}
				});

				if ( panel.params.autoExpandSoleSection && 1 === childSections.length && childSections[0].active.get() ) {
					accordionSection.addClass( 'current-panel skip-transition' );
					overlay.addClass( 'in-sub-panel' );

					childSections[0].expand( {
						completeCallback: args.completeCallback
					} );
				} else {
					panel._animateChangeExpanded( function() {
						topPanel.attr( 'tabindex', '-1' );
						backBtn.attr( 'tabindex', '0' );

						backBtn.focus();
						accordionSection.css( 'top', '' );
						container.scrollTop( 0 );

						if ( args.completeCallback ) {
							args.completeCallback();
						}
					} );

					accordionSection.addClass( 'current-panel' );
					overlay.addClass( 'in-sub-panel' );
				}

				api.state( 'expandedPanel' ).set( panel );

			} else if ( ! expanded && accordionSection.hasClass( 'current-panel' ) ) {
				skipTransition = accordionSection.hasClass( 'skip-transition' );
				if ( ! skipTransition ) {
					panel._animateChangeExpanded( function() {
						topPanel.attr( 'tabindex', '0' );
						backBtn.attr( 'tabindex', '-1' );

						topPanel.focus();
						accordionSection.css( 'top', '' );

						if ( args.completeCallback ) {
							args.completeCallback();
						}
					} );
				} else {
					accordionSection.removeClass( 'skip-transition' );
				}

				overlay.removeClass( 'in-sub-panel' );
				accordionSection.removeClass( 'current-panel' );
				if ( panel === api.state( 'expandedPanel' ).get() ) {
					api.state( 'expandedPanel' ).set( false );
				}
			}
		},

		/**
		 * Renders the panel from its JS template, if it exists.
		 *
		 * The panel's container must already exist in the DOM.
		 *
		 * @since 4.3.0
		 *
		 * @return {void}
		 */
		renderContent: function () {
			var template,
				panel = this;

			// Add the content to the container.
			if ( 0 !== $( '#tmpl-' + panel.templateSelector + '-content' ).length ) {
				template = wp.template( panel.templateSelector + '-content' );
			} else {
				template = wp.template( 'customize-panel-default-content' );
			}
			if ( template && panel.headContainer ) {
				panel.contentContainer.html( template( _.extend(
					{ id: panel.id },
					panel.params
				) ) );
			}
		}
	});

	/**
	 * The ThemesPanel object.
	 */
	api.ThemesPanel = api.Panel.extend(/** @lends wp.customize.ThemsPanel.prototype */{

		/**
		 * Initializes the custom section for themes that displays without the customize preview.
		 *
		 * @since 4.9.0
		 *
		 * @constructs wp.customize.ThemesPanel
		 * @augments   wp.customize.Panel
		 *
		 * @memberOf   wp.customize
		 *
		 * @param {string} 	id 		The ID for the panel.
		 * @param {Object} 	options The options to use within the ThemesPanel object.
		 */
		initialize: function( id, options ) {
			var panel = this;
			panel.installingThemes = [];
			api.Panel.prototype.initialize.call( panel, id, options );
		},

		/**
		 * Determines whether a given theme can be switched to, or can be switched in general.
		 *
		 * @since 4.9.0
		 *
		 * @param {string} [slug] The theme slug.
		 *
		 * @return {boolean} Whether the theme can be switched to.
		 */
		canSwitchTheme: function canSwitchTheme( slug ) {
			if ( slug && slug === api.settings.theme.stylesheet ) {
				return true;
			}
			return 'publish' === api.state( 'selectedChangesetStatus' ).get() && ( '' === api.state( 'changesetStatus' ).get() || 'auto-draft' === api.state( 'changesetStatus' ).get() );
		},

		/**
		 * Attaches events to the ThemesPanel.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		attachEvents: function() {
			var panel = this;

			// Attach regular panel events.
			api.Panel.prototype.attachEvents.apply( panel );

			// Temporary since supplying SFTP credentials does not work yet. See #42184.
			if ( api.settings.theme._canInstall && api.settings.theme._filesystemCredentialsNeeded ) {
				panel.notifications.add( new api.Notification( 'theme_install_unavailable', {
					message: api.l10n.themeInstallUnavailable,
					type: 'info',
					dismissible: true
				} ) );
			}

			function toggleDisabledNotifications() {
				if ( panel.canSwitchTheme() ) {
					panel.notifications.remove( 'theme_switch_unavailable' );
				} else {
					panel.notifications.add( new api.Notification( 'theme_switch_unavailable', {
						message: api.l10n.themePreviewUnavailable,
						type: 'warning'
					} ) );
				}
			}
			toggleDisabledNotifications();
			api.state( 'selectedChangesetStatus' ).bind( toggleDisabledNotifications );
			api.state( 'changesetStatus' ).bind( toggleDisabledNotifications );

			// Collapse panel to customize the current theme.
			panel.contentContainer.on( 'click', '.customize-theme', function() {
				panel.collapse();
			});

			// Toggle between filtering and browsing themes on mobile.
			panel.contentContainer.on( 'click', '.customize-themes-section-title, .customize-themes-mobile-back', function() {
				$( '.wp-full-overlay' ).toggleClass( 'showing-themes' );
			});

			// Install (and maybe preview) a theme.
			panel.contentContainer.on( 'click', '.theme-install', function( event ) {
				panel.installTheme( event );
			});

			// Update a theme. Theme cards have the class, the details modal has the id.
			panel.contentContainer.on( 'click', '.update-theme, #update-theme', function( event ) {

				// #update-theme is a link.
				event.preventDefault();
				event.stopPropagation();

				panel.updateTheme( event );
			});

			// Delete a theme.
			panel.contentContainer.on( 'click', '.delete-theme', function( event ) {
				panel.deleteTheme( event );
			});

			_.bindAll( panel, 'installTheme', 'updateTheme' );
		},

		/**
		 * Updates the UI to reflect expanded state.
		 *
		 * @since 4.9.0
		 *
		 * @param {boolean}  expanded               Whether or not the panel was expanded.
		 * @param {Object}   args                   The arguments to use within the updating of the UI.
		 * @param {boolean}  args.unchanged         Whether the state remains unchanged.
		 * @param {Function} args.completeCallback  Function to call when the updating of the UI is completed.
		 *
		 * @return {void}
		 */
		onChangeExpanded: function( expanded, args ) {
			var panel = this, overlay, sections, hasExpandedSection = false;

			// Expand/collapse the panel normally.
			api.Panel.prototype.onChangeExpanded.apply( this, [ expanded, args ] );

			// Immediately call the complete callback if there were no changes.
			if ( args.unchanged ) {
				if ( args.completeCallback ) {
					args.completeCallback();
				}
				return;
			}

			overlay = panel.headContainer.closest( '.wp-full-overlay' );

			if ( expanded ) {
				overlay
					.addClass( 'in-themes-panel' )
					.delay( 200 ).find( '.customize-themes-full-container' ).addClass( 'animate' );

				_.delay( function() {
					overlay.addClass( 'themes-panel-expanded' );
				}, 200 );

				// Automatically open the first section (except on small screens), if one isn't already expanded.
				if ( 600 < window.innerWidth ) {
					sections = panel.sections();
					_.each( sections, function( section ) {
						if ( section.expanded() ) {
							hasExpandedSection = true;
						}
					} );
					if ( ! hasExpandedSection && sections.length > 0 ) {
						sections[0].expand();
					}
				}
			} else {
				overlay
					.removeClass( 'in-themes-panel themes-panel-expanded' )
					.find( '.customize-themes-full-container' ).removeClass( 'animate' );
			}
		},

		/**
		 * Installs a theme via wp.updates.
		 *
		 * @since 4.9.0
		 *
		 * @param {jQuery.Event} event The page event to use in the FileSystemsCredentials request.
		 *
		 * @return {jQuery.promise} Promise that resolves to the installation of the theme.
		 */
		installTheme: function( event ) {
			var panel = this, preview, onInstallSuccess, slug = $( event.target ).data( 'slug' ), deferred = $.Deferred(), request;
			preview = $( event.target ).hasClass( 'preview' );

			// Temporary since supplying SFTP credentials does not work yet. See #42184.
			if ( api.settings.theme._filesystemCredentialsNeeded ) {
				deferred.reject({
					errorCode: 'theme_install_unavailable'
				});
				return deferred.promise();
			}

			// Prevent loading a non-active theme preview when there is a drafted/scheduled changeset.
			if ( ! panel.canSwitchTheme( slug ) ) {
				deferred.reject({
					errorCode: 'theme_switch_unavailable'
				});
				return deferred.promise();
			}

			// Theme is already being installed.
			if ( _.contains( panel.installingThemes, slug ) ) {
				deferred.reject({
					errorCode: 'theme_already_installing'
				});
				return deferred.promise();
			}

			wp.updates.maybeRequestFilesystemCredentials( event );

			onInstallSuccess = function( response ) {
				var theme = false, themeControl;
				if ( preview ) {
					api.notifications.remove( 'theme_installing' );

					panel.loadThemePreview( slug );

				} else {
					api.control.each( function( control ) {
						if ( 'theme' === control.params.type && control.params.theme.id === response.slug ) {
							// Used below to add theme control.
							theme = control.params.theme;
							control.rerenderAsInstalled( true );
						}
					});

					// Don't add the same theme more than once.
					if ( ! theme || api.control.has( 'installed_theme_' + theme.id ) ) {
						deferred.resolve( response );
						return;
					}

					// Add theme control to installed section.
					theme.type = 'installed';
					themeControl = new api.controlConstructor.theme( 'installed_theme_' + theme.id, {
						type: 'theme',
						section: 'installed_themes',
						theme: theme,
						priority: 0 // Add all newly-installed themes to the top.
					} );

					api.control.add( themeControl );
					api.control( themeControl.id ).container.trigger( 'render-screenshot' );

					// Close the details modal if it's open to the installed theme.
					api.section.each( function( section ) {
						if ( 'themes' === section.params.type ) {
							// Don't close the modal if the user has navigated elsewhere.
							if ( theme.id === section.currentTheme ) {
								section.closeDetails();
							}
						}
					});
				}
				deferred.resolve( response );
			};

			// Note: we don't remove elements from installingThemes, since they shouldn't be installed again.
			panel.installingThemes.push( slug );
			request = wp.updates.installTheme( {
				slug: slug
			} );

			// Also preview the theme as the event is triggered on Install & Preview.
			if ( preview ) {
				api.notifications.add( new api.OverlayNotification( 'theme_installing', {
					message: api.l10n.themeDownloading,
					type: 'info',
					loading: true
				} ) );
			}

			request.done( onInstallSuccess );
			request.fail( function() {
				api.notifications.remove( 'theme_installing' );
			} );

			return deferred.promise();
		},

		/**
		 * Loads the theme preview.
		 *
		 * @since 4.9.0
		 *
		 * @param {string} themeId The Theme ID to load.
		 *
		 * @return {jQuery.promise} Promise that resolves to the loaded theme.
		 */
		loadThemePreview: function( themeId ) {
			var panel = this, deferred = $.Deferred(), onceProcessingComplete, urlParser, queryParams;

			// Prevent loading a non-active theme preview when there is a drafted/scheduled changeset.
			if ( ! panel.canSwitchTheme( themeId ) ) {
				deferred.reject({
					errorCode: 'theme_switch_unavailable'
				});
				return deferred.promise();
			}

			urlParser = document.createElement( 'a' );
			urlParser.href = location.href;
			queryParams = _.extend(
				api.utils.parseQueryString( urlParser.search.substr( 1 ) ),
				{
					theme: themeId,
					changeset_uuid: api.settings.changeset.uuid,
					'return': api.settings.url['return']
				}
			);

			// Include autosaved param to load autosave revision without prompting user to restore it.
			if ( ! api.state( 'saved' ).get() ) {
				queryParams.customize_autosaved = 'on';
			}

			urlParser.search = $.param( queryParams );

			// Update loading message. Everything else is handled by reloading the page.
			api.notifications.add( new api.OverlayNotification( 'theme_previewing', {
				message: api.l10n.themePreviewWait,
				type: 'info',
				loading: true
			} ) );

			onceProcessingComplete = function() {
				var request;
				if ( api.state( 'processing' ).get() > 0 ) {
					return;
				}

				api.state( 'processing' ).unbind( onceProcessingComplete );

				request = api.requestChangesetUpdate( {}, { autosave: true } );
				request.done( function() {
					deferred.resolve();
					$( window ).off( 'beforeunload.customize-confirm' );
					location.replace( urlParser.href );
				} );
				request.fail( function() {

					// @todo Show notification regarding failure.
					api.notifications.remove( 'theme_previewing' );

					deferred.reject();
				} );
			};

			if ( 0 === api.state( 'processing' ).get() ) {
				onceProcessingComplete();
			} else {
				api.state( 'processing' ).bind( onceProcessingComplete );
			}

			return deferred.promise();
		},

		/**
		 * Updates a theme via wp.updates.
		 *
		 * @since 4.9.0
		 *
		 * @param {jQuery.Event} event The page event to use in the FileSystemsCredentials request.
		 *
		 * @return {void}
		 */
		updateTheme: function( event ) {
			wp.updates.maybeRequestFilesystemCredentials( event );

			$( document ).one( 'wp-theme-update-success', function( e, response ) {

				// Rerender the control to reflect the update.
				api.control.each( function( control ) {
					if ( 'theme' === control.params.type && control.params.theme.id === response.slug ) {
						control.params.theme.hasUpdate = false;
						control.params.theme.version = response.newVersion;
						setTimeout( function() {
							control.rerenderAsInstalled( true );
						}, 2000 );
					}
				});
			} );

			wp.updates.updateTheme( {
				slug: $( event.target ).closest( '.notice' ).data( 'slug' )
			} );
		},

		/**
		 * Deletes a theme via wp.updates.
		 *
		 * @since 4.9.0
		 *
		 * @param {jQuery.Event} event The page event to use in the FileSystemsCredentials request.
		 *
		 * @return {void}
		 */
		deleteTheme: function( event ) {
			var theme, section;
			theme = $( event.target ).data( 'slug' );
			section = api.section( 'installed_themes' );

			event.preventDefault();

			// Temporary since supplying SFTP credentials does not work yet. See #42184.
			if ( api.settings.theme._filesystemCredentialsNeeded ) {
				return;
			}

			// Confirmation dialog for deleting a theme.
			if ( ! window.confirm( api.settings.l10n.confirmDeleteTheme ) ) {
				return;
			}

			wp.updates.maybeRequestFilesystemCredentials( event );

			$( document ).one( 'wp-theme-delete-success', function() {
				var control = api.control( 'installed_theme_' + theme );

				// Remove theme control.
				control.container.remove();
				api.control.remove( control.id );

				// Update installed count.
				section.loaded = section.loaded - 1;
				section.updateCount();

				// Re-render any other theme controls as uninstalled.
				api.control.each( function( control ) {
					if ( 'theme' === control.params.type && control.params.theme.id === theme ) {
						control.rerenderAsInstalled( false );
					}
				});
			} );

			wp.updates.deleteTheme( {
				slug: theme
			} );

			// Close modal and focus the section.
			section.closeDetails();
			section.focus();
		}
	});

	/**
	 * The Control object.
	 */
	api.Control = api.Class.extend(/** @lends wp.customize.Control.prototype */{
		defaultActiveArguments: { duration: 'fast', completeCallback: $.noop },

		/**
		 * Default params.
		 *
		 * @since 4.9.0
		 * @var {Object}
		 */
		defaults: {
			label: '',
			description: '',
			active: true,
			priority: 10
		},

		/**
		 * A Customizer Control.
		 *
		 * A control provides a UI element that allows a user to modify a Customizer Setting.
		 *
		 * @since 4.1.0
		 *
		 * @constructs wp.customize.Control
		 * @augments   wp.customize.Class
		 *
		 * @memberOf   wp.customize
		 *
		 * @see PHP class WP_Customize_Control.
		 *
		 * @borrows wp.customize~focus as this#focus
		 * @borrows wp.customize~Container#activate as this#activate
		 * @borrows wp.customize~Container#deactivate as this#deactivate
		 * @borrows wp.customize~Container#_toggleActive as this#_toggleActive
		 *
		 *
		 * @param {string} id                       Unique identifier for the Control instance.
		 * @param {Object} options                  Options hash for the Control instance.
		 * @param {Object} options.type             Type of control (e.g. text, radio, dropdown-pages, etc.)
		 * @param {string} [options.content]        The HTML content for the Control (or at least its container). This should normally be left blank in favor of supplying a templateId.
		 * @param {string} [options.templateId]     Template ID for the Control's content.
		 * @param {string} [options.priority=10]    Order of priority to show the Control within the section.
		 * @param {string} [options.active=true]    Whether the control is active.
		 * @param {string} options.section          The ID of the section the Control belongs to.
		 * @param {mixed}  [options.setting]        The ID of the main setting or an instance of this setting.
		 * @param {mixed}  options.settings         An object with keys (e.g. default) that maps to setting IDs or Setting/Value objects, or an array of setting IDs or Setting/Value objects.
		 * @param {mixed}  options.settings.default The ID of the setting the Control relates to.
		 * @param {string} options.settings.data    Settings data to be used within the Control instance. (Seems to no longer be used).
		 * @param {string} options.label            The label for the Control.
		 * @param {string} options.description      The description of the Control.
		 * @param {number} [options.instanceNumber] Order in which this instance was created in relation to other instances.
		 * @param {Object} [options.params]         Deprecated wrapper for the above properties.
		 */
		initialize: function( id, options ) {
			var control = this, deferredSettingIds = [], settings, gatherSettings;

			control.params = _.extend(
				{},
				control.defaults,
				// In case sub-class already defines.
				control.params || {},
				// The options.params property is deprecated, but it is checked first for back-compat.
				options.params || options || {}
			);

			if ( ! api.Control.instanceCounter ) {
				api.Control.instanceCounter = 0;
			}
			api.Control.instanceCounter++;
			if ( ! control.params.instanceNumber ) {
				control.params.instanceNumber = api.Control.instanceCounter;
			}

			// Look up the type if one was not supplied.
			if ( ! control.params.type ) {
				_.find( api.controlConstructor, function( Constructor, type ) {
					if ( Constructor === control.constructor ) {
						control.params.type = type;
						return true;
					}
					return false;
				} );
			}

			if ( ! control.params.content ) {
				control.params.content = $( '<li></li>', {
					id: 'customize-control-' + id.replace( /]/g, '' ).replace( /\[/g, '-' ),
					'class': 'customize-control customize-control-' + control.params.type
				} );
			}

			control.id = id;

			// Deprecated, likely dead code from time before #28709.
			control.selector = '#customize-control-' + id.replace( /\]/g, '' ).replace( /\[/g, '-' );
			if ( control.params.content ) {
				control.container = $( control.params.content );
			} else {
				// Likely dead, per above. See #28709.
				control.container = $( control.selector );
			}

			if ( control.params.templateId ) {
				control.templateSelector = control.params.templateId;
			} else {
				control.templateSelector = 'customize-control-' + control.params.type + '-content';
			}

			control.deferred = _.extend( control.deferred || {}, {
				embedded: new $.Deferred()
			} );
			control.section = new api.Value();
			control.priority = new api.Value();
			control.active = new api.Value();
			control.activeArgumentsQueue = [];
			control.notifications = new api.Notifications({
				alt: control.altNotice
			});

			control.elements = [];

			control.active.bind( function ( active ) {
				var args = control.activeArgumentsQueue.shift();
				args = $.extend( {}, control.defaultActiveArguments, args );
				control.onChangeActive( active, args );
			} );

			control.section.set( control.params.section );
			control.priority.set( isNaN( control.params.priority ) ? 10 : control.params.priority );
			control.active.set( control.params.active );

			api.utils.bubbleChildValueChanges( control, [ 'section', 'priority', 'active' ] );

			control.settings = {};

			settings = {};
			if ( control.params.setting ) {
				settings['default'] = control.params.setting;
			}
			_.extend( settings, control.params.settings );

			// Note: Settings can be an array or an object, with values being either setting IDs or Setting (or Value) objects.
			_.each( settings, function( value, key ) {
				var setting;
				if ( _.isObject( value ) && _.isFunction( value.extended ) && value.extended( api.Value ) ) {
					control.settings[ key ] = value;
				} else if ( _.isString( value ) ) {
					setting = api( value );
					if ( setting ) {
						control.settings[ key ] = setting;
					} else {
						deferredSettingIds.push( value );
					}
				}
			} );

			/**
			 * Gathers settings and maps them properly for later usage.
			 *
			 * @since 4.9.0
			 *
			 * @return {void}
			 */
			gatherSettings = function() {

				// Fill-in all resolved settings.
				_.each( settings, function ( settingId, key ) {
					if ( ! control.settings[ key ] && _.isString( settingId ) ) {
						control.settings[ key ] = api( settingId );
					}
				} );

				// Make sure settings passed as array gets associated with default.
				if ( control.settings[0] && ! control.settings['default'] ) {
					control.settings['default'] = control.settings[0];
				}

				// Identify the main setting.
				control.setting = control.settings['default'] || null;

				// Link initial elements present in server-rendered content.
				control.linkElements();
				control.embed();
			};

			if ( 0 === deferredSettingIds.length ) {
				gatherSettings();
			} else {
				api.apply( api, deferredSettingIds.concat( gatherSettings ) );
			}

			// After the control is embedded on the page, invoke the "ready" method.
			control.deferred.embedded.done( function () {
				// Link any additional elements after template is rendered by renderContent().
				control.linkElements();
				control.setupNotifications();
				control.ready();
			});
		},

		/**
		 * Links elements between settings and inputs.
		 *
		 * @since 4.7.0
		 *
		 * @access public
		 *
		 * @return {void}
		 */
		linkElements: function () {
			var control = this, nodes, radios, element;

			nodes = control.container.find( '[data-customize-setting-link], [data-customize-setting-key-link]' );
			radios = {};

			nodes.each( function () {
				var node = $( this ), name, setting;

				if ( node.data( 'customizeSettingLinked' ) ) {
					return;
				}

				// Prevent re-linking element.
				node.data( 'customizeSettingLinked', true );

				if ( node.is( ':radio' ) ) {
					name = node.prop( 'name' );
					if ( radios[name] ) {
						return;
					}

					radios[name] = true;
					node = nodes.filter( '[name="' + name + '"]' );
				}

				// Let link by default refer to setting ID. If it doesn't exist, fallback to looking up by setting key.
				if ( node.data( 'customizeSettingLink' ) ) {
					setting = api( node.data( 'customizeSettingLink' ) );
				} else if ( node.data( 'customizeSettingKeyLink' ) ) {
					setting = control.settings[ node.data( 'customizeSettingKeyLink' ) ];
				}

				if ( setting ) {
					element = new api.Element( node );
					control.elements.push( element );
					element.sync( setting );
					element.set( setting() );
				}
			} );
		},

		/**
		 * Embeds the control into the page.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		embed: function () {
			var control = this,
				inject;

			/**
			 * Watches for changes to the section state.
			 *
			 * @since 4.1.0
			 *
			 * @param {string} sectionId The section's ID.
			 *
			 * @return {void}
			 */
			inject = function ( sectionId ) {
				var parentContainer;
				if ( ! sectionId ) { // @todo allow a control to be embedded without a section, for instance a control embedded in the front end.
					return;
				}

				// Wait for the section to be registered.
				api.section( sectionId, function ( section ) {
					// Wait for the section to be ready/initialized.
					section.deferred.embedded.done( function () {
						parentContainer = ( section.contentContainer.is( 'ul' ) ) ? section.contentContainer : section.contentContainer.find( 'ul:first' );
						if ( ! control.container.parent().is( parentContainer ) ) {
							parentContainer.append( control.container );
							control.renderContent();
						}
						control.deferred.embedded.resolve();
					});
				});
			};
			control.section.bind( inject );
			inject( control.section.get() );
		},

		/**
		 * Triggers when the control's markup has been injected into the DOM.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		ready: function() {
			var control = this, newItem;
			if ( 'dropdown-pages' === control.params.type && control.params.allow_addition ) {
				newItem = control.container.find( '.new-content-item' );
				// Hide in JS to preserve flex display when showing.
				newItem.hide();
				control.container.on( 'click', '.add-new-toggle', function( e ) {
					$( e.currentTarget ).slideUp( 180 );
					newItem.slideDown( 180 );
					newItem.find( '.create-item-input' ).focus();
				});
				control.container.on( 'click', '.add-content', function() {
					control.addNewPage();
				});
				control.container.on( 'keydown', '.create-item-input', function( e ) {
					// Handle keydown event for the Enter key.
					if ( 13 === e.which ) {
						control.addNewPage();
					}
				});
			}
		},

		/**
		 * Gets the element inside of a control's container that contains the validation error message.
		 *
		 * Control subclasses may override this to return the proper container to render notifications into.
		 * Injects the notification container for existing controls that lack the necessary container,
		 * including special handling for nav menu items and widgets.
		 *
		 * @since 4.6.0
		 *
		 * @return {jQuery} Setting validation message element.
		 */
		getNotificationsContainerElement: function() {
			var control = this, controlTitle, notificationsContainer;

			notificationsContainer = control.container.find( '.customize-control-notifications-container:first' );
			if ( notificationsContainer.length ) {
				return notificationsContainer;
			}

			notificationsContainer = $( '<div class="customize-control-notifications-container"></div>' );

			if ( control.container.hasClass( 'customize-control-nav_menu_item' ) ) {
				control.container.find( '.menu-item-settings:first' ).prepend( notificationsContainer );
			} else if ( control.container.hasClass( 'customize-control-widget_form' ) ) {
				control.container.find( '.widget-inside:first' ).prepend( notificationsContainer );
			} else {
				controlTitle = control.container.find( '.customize-control-title' );
				if ( controlTitle.length ) {
					controlTitle.after( notificationsContainer );
				} else {
					control.container.prepend( notificationsContainer );
				}
			}
			return notificationsContainer;
		},

		/**
		 * Sets up notifications.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		setupNotifications: function() {
			var control = this, renderNotificationsIfVisible, onSectionAssigned;

			// Add setting notifications to the control notification.
			_.each( control.settings, function( setting ) {
				if ( ! setting.notifications ) {
					return;
				}
				setting.notifications.bind( 'add', function( settingNotification ) {
					var params = _.extend(
						{},
						settingNotification,
						{
							setting: setting.id
						}
					);
					control.notifications.add( new api.Notification( setting.id + ':' + settingNotification.code, params ) );
				} );
				setting.notifications.bind( 'remove', function( settingNotification ) {
					control.notifications.remove( setting.id + ':' + settingNotification.code );
				} );
			} );

			/**
			 * Renders the notifications if the particular section is expanded.
			 *
			 * @since 4.9.0
			 *
			 * @return {void}
			 */
			renderNotificationsIfVisible = function() {
				var sectionId = control.section();
				if ( ! sectionId || ( api.section.has( sectionId ) && api.section( sectionId ).expanded() ) ) {
					control.notifications.render();
				}
			};

			control.notifications.bind( 'rendered', function() {
				var notifications = control.notifications.get();
				control.container.toggleClass( 'has-notifications', 0 !== notifications.length );
				control.container.toggleClass( 'has-error', 0 !== _.where( notifications, { type: 'error' } ).length );
			} );

			/**
			 * Handles the binding and unbinding of the renderNotificationsIfVisible method.
			 *
			 * @since 4.9.0
			 *
			 * @param {string} newSectionId The new section ID.
			 * @param {string} oldSectionId The old section ID.
			 *
			 * @return {void}
			 */
			onSectionAssigned = function( newSectionId, oldSectionId ) {
				if ( oldSectionId && api.section.has( oldSectionId ) ) {
					api.section( oldSectionId ).expanded.unbind( renderNotificationsIfVisible );
				}
				if ( newSectionId ) {
					api.section( newSectionId, function( section ) {
						section.expanded.bind( renderNotificationsIfVisible );
						renderNotificationsIfVisible();
					});
				}
			};

			control.section.bind( onSectionAssigned );
			onSectionAssigned( control.section.get() );
			control.notifications.bind( 'change', _.debounce( renderNotificationsIfVisible ) );
		},

		/**
		 * Renders the `control.notifications` into the control's container.
		 *
		 * Control subclasses may override this method to do their own handling of rendering notifications.
		 *
		 * @deprecated in favor of `control.notifications.render()`
		 *
		 * @since 4.6.0
		 *
		 * @this {wp.customize.Control}
		 *
		 * @return {void}
		 */
		renderNotifications: function() {
			var control = this, container, notifications, hasError = false;

			if ( 'undefined' !== typeof console && console.warn ) {
				console.warn( '[DEPRECATED] wp.customize.Control.prototype.renderNotifications() is deprecated in favor of instantating a wp.customize.Notifications and calling its render() method.' );
			}

			container = control.getNotificationsContainerElement();
			if ( ! container || ! container.length ) {
				return;
			}
			notifications = [];
			control.notifications.each( function( notification ) {
				notifications.push( notification );
				if ( 'error' === notification.type ) {
					hasError = true;
				}
			} );

			if ( 0 === notifications.length ) {
				container.stop().slideUp( 'fast' );
			} else {
				container.stop().slideDown( 'fast', null, function() {
					$( this ).css( 'height', 'auto' );
				} );
			}

			if ( ! control.notificationsTemplate ) {
				control.notificationsTemplate = wp.template( 'customize-control-notifications' );
			}

			control.container.toggleClass( 'has-notifications', 0 !== notifications.length );
			control.container.toggleClass( 'has-error', hasError );
			container.empty().append( $.trim(
				control.notificationsTemplate( { notifications: notifications, altNotice: Boolean( control.altNotice ) } )
			) );
		},

		/**
		 * Expands the parent of normal controls.
		 *
		 * @since 4.0.0
		 *
		 * @param {Object} [params] The parameters to use during the expansion.
		 *
		 * @return {void}
		 */
		expand: function ( params ) {
			api.section( this.section() ).expand( params );
		},

		/*
		 * Documented using @borrows in the constructor.
		 */
		focus: focus,

		/**
		 * Updates the UI in response to a change in the control's active state.
		 *
		 * This does not change the active state, it merely handles the behavior
		 * for when it does change.
		 *
		 * @since 4.1.0
		 *
		 * @param {boolean}  active                 Whether the state of the control is active.
		 * @param {Object}   args                   The arguments to pass along to the slideDown or slideUp animation.
		 * @param {number}   args.duration          The duration of the animation.
		 * @param {Function} args.completeCallback  The function to call once the animation is complete.
		 *
		 * @return {void}
		 */
		onChangeActive: function ( active, args ) {
			if ( args.unchanged ) {
				if ( args.completeCallback ) {
					args.completeCallback();
				}
				return;
			}

			if ( ! $.contains( document, this.container[0] ) ) {
				// jQuery.fn.slideUp is not hiding an element if it is not in the DOM.
				this.container.toggle( active );
				if ( args.completeCallback ) {
					args.completeCallback();
				}
			} else if ( active ) {
				this.container.slideDown( args.duration, args.completeCallback );
			} else {
				this.container.slideUp( args.duration, args.completeCallback );
			}
		},

		/**
		 * @deprecated 4.1.0 Use this.onChangeActive() instead.
		 */
		toggle: function ( active ) {
			return this.onChangeActive( active, this.defaultActiveArguments );
		},

		/*
		 * Documented using @borrows in the constructor.
		 */
		activate: Container.prototype.activate,

		/*
		 * Documented using @borrows in the constructor.
		 */
		deactivate: Container.prototype.deactivate,

		/*
		 * Documented using @borrows in the constructor.
		 */
		_toggleActive: Container.prototype._toggleActive,

		/**
		 * Initializes the drop-down element.
		 *
		 * @todo This function appears to be dead code and can be removed.
		 *
		 * @since 3.5.0
		 *
		 * @return {void}
		 */
		dropdownInit: function() {
			var control      = this,
				statuses     = this.container.find('.dropdown-status'),
				params       = this.params,
				toggleFreeze = false,
				update       = function( to ) {
					if ( 'string' === typeof to && params.statuses && params.statuses[ to ] ) {
						statuses.html( params.statuses[ to ] ).show();
					} else {
						statuses.hide();
					}
				};

			// Support the .dropdown class to open/close complex elements.
			this.container.on( 'click keydown', '.dropdown', function( event ) {
				if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
					return;
				}

				event.preventDefault();

				if ( ! toggleFreeze ) {
					control.container.toggleClass( 'open' );
				}

				if ( control.container.hasClass( 'open' ) ) {
					control.container.parent().parent().find( 'li.library-selected' ).focus();
				}

				// Don't want to fire focus and click at same time.
				toggleFreeze = true;
				setTimeout(function () {
					toggleFreeze = false;
				}, 400);
			});

			this.setting.bind( update );
			update( this.setting() );
		},

		/**
		 * Renders the control from its JS template, if it exists.
		 *
		 * The control's container must already exist in the DOM.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		renderContent: function () {
			var control = this, template, standardTypes, templateId, sectionId;

			standardTypes = [
				'button',
				'checkbox',
				'date',
				'datetime-local',
				'email',
				'month',
				'number',
				'password',
				'radio',
				'range',
				'search',
				'select',
				'tel',
				'time',
				'text',
				'textarea',
				'week',
				'url'
			];

			templateId = control.templateSelector;

			// Use default content template when a standard HTML type is used, there isn't a more specific template existing, and the control container is empty.
			if ( templateId === 'customize-control-' + control.params.type + '-content' &&
				_.contains( standardTypes, control.params.type ) &&
				! document.getElementById( 'tmpl-' + templateId ) &&
				0 === control.container.children().length )
			{
				templateId = 'customize-control-default-content';
			}

			// Replace the container element's content with the control.
			if ( document.getElementById( 'tmpl-' + templateId ) ) {
				template = wp.template( templateId );
				if ( template && control.container ) {
					control.container.html( template( control.params ) );
				}
			}

			// Re-render notifications after content has been re-rendered.
			control.notifications.container = control.getNotificationsContainerElement();
			sectionId = control.section();
			if ( ! sectionId || ( api.section.has( sectionId ) && api.section( sectionId ).expanded() ) ) {
				control.notifications.render();
			}
		},

		/**
		 * Adds a new page to a dropdown-pages control reusing menus code for this.
		 *
		 * @since 4.7.0
		 *
		 * @access private
		 *
		 * @return {void}
		 */
		addNewPage: function () {
			var control = this, promise, toggle, container, input, title, select;

			if ( 'dropdown-pages' !== control.params.type || ! control.params.allow_addition || ! api.Menus ) {
				return;
			}

			toggle = control.container.find( '.add-new-toggle' );
			container = control.container.find( '.new-content-item' );
			input = control.container.find( '.create-item-input' );
			title = input.val();
			select = control.container.find( 'select' );

			if ( ! title ) {
				input.addClass( 'invalid' );
				return;
			}

			input.removeClass( 'invalid' );
			input.attr( 'disabled', 'disabled' );

			// The menus functions add the page, publish when appropriate, and also add the new page to the dropdown-pages controls.
			promise = api.Menus.insertAutoDraftPost( {
				post_title: title,
				post_type: 'page'
			} );
			promise.done( function( data ) {
				var availableItem, $content, itemTemplate;

				// Prepare the new page as an available menu item. See api.Menus.submitNew().
				availableItem = new api.Menus.AvailableItemModel( {
					// Used for available menu item Backbone models.
					'id': 'post-' + data.post_id,
					'title': title,
					'type': 'post_type',
					'type_label': api.Menus.data.l10n.page_label,
					'object': 'page',
					'object_id': data.post_id,
					'url': data.url
				} );

				// Add the new item to the list of available menu items.
				api.Menus.availableMenuItemsPanel.collection.add( availableItem );
				$content = $( '#available-menu-items-post_type-page' ).find( '.available-menu-items-list' );
				itemTemplate = wp.template( 'available-menu-item' );
				$content.prepend( itemTemplate( availableItem.attributes ) );

				// Focus the select control.
				select.focus();

				// Trigger a preview refresh and updates the setting.
				control.setting.set( String( data.post_id ) );

				// Reset the create page form.
				container.slideUp( 180 );
				toggle.slideDown( 180 );
			} );
			promise.always( function() {
				input.val( '' ).removeAttr( 'disabled' );
			} );
		}
	});

	/**
	 * A colorpicker control.
	 *
	 * @since 3.5.0
	 *
	 * @constructs wp.customize.ColorControl
	 * @memberOf   wp.customize
	 * @augments   wp.customize.Control
	 * @inheritDoc
	 */
	api.ColorControl = api.Control.extend(/** @lends wp.customize.ColorControl.prototype */{
		/**
		 * Sets up internal event bindings when the control's DOM structure is ready.
		 *
		 * @since 3.5.0
		 *
		 * @return {void}
		 */
		ready: function() {
			var control = this,
				isHueSlider = this.params.mode === 'hue',
				updating = false,
				picker;

			if ( isHueSlider ) {
				picker = this.container.find( '.color-picker-hue' );
				picker.val( control.setting() ).wpColorPicker({
					change: function( event, ui ) {
						updating = true;
						control.setting( ui.color.h() );
						updating = false;
					}
				});
			} else {
				picker = this.container.find( '.color-picker-hex' );
				picker.val( control.setting() ).wpColorPicker({
					change: function() {
						updating = true;
						control.setting.set( picker.wpColorPicker( 'color' ) );
						updating = false;
					},
					clear: function() {
						updating = true;
						control.setting.set( '' );
						updating = false;
					}
				});
			}

			control.setting.bind( function ( value ) {
				// Bail if the update came from the control itself.
				if ( updating ) {
					return;
				}
				picker.val( value );
				picker.wpColorPicker( 'color', value );
			} );

			// Collapse color picker when hitting Esc instead of collapsing the current section.
			control.container.on( 'keydown', function( event ) {
				var pickerContainer;
				if ( 27 !== event.which ) { // Esc.
					return;
				}
				pickerContainer = control.container.find( '.wp-picker-container' );
				if ( pickerContainer.hasClass( 'wp-picker-active' ) ) {
					picker.wpColorPicker( 'close' );
					control.container.find( '.wp-color-result' ).focus();

					// Prevent section from being collapsed.
					event.stopPropagation();
				}
			} );
		}
	});

	/**
	 * A control that implements the media modal.
	 *
	 * @since 4.1.0
	 *
	 * @constructs wp.customize.MediaControl
	 * @augments   wp.customize.Control
	 * @memberOf   wp.customize
	 * @inheritDoc
	 */
	api.MediaControl = api.Control.extend(/** @lends wp.customize.MediaControl.prototype */{
		/**
		 * Sets up internal event bindings when the control's DOM structure is ready.
		 *
		 * @since 3.5.0
		 *
		 * @return {void}
		 */
		ready: function() {
			var control = this;
			// Shortcut so that we don't have to use _.bind every time we add a callback.
			_.bindAll( control, 'restoreDefault', 'removeFile', 'openFrame', 'select', 'pausePlayer' );

			// Bind events, with delegation to facilitate re-rendering.
			control.container.on( 'click keydown', '.upload-button', control.openFrame );
			control.container.on( 'click keydown', '.upload-button', control.pausePlayer );
			control.container.on( 'click keydown', '.thumbnail-image img', control.openFrame );
			control.container.on( 'click keydown', '.default-button', control.restoreDefault );
			control.container.on( 'click keydown', '.remove-button', control.pausePlayer );
			control.container.on( 'click keydown', '.remove-button', control.removeFile );
			control.container.on( 'click keydown', '.remove-button', control.cleanupPlayer );

			// Resize the player controls when the section becomes visible.
			api.section( control.section() ).container
				.on( 'expanded', function() {
					if ( control.player ) {
						control.player.setControlsSize();
					}
				})
				.on( 'collapsed', function() {
					control.pausePlayer();
				});

			/**
			 * Sets attachment data and renders content.
			 *
			 * Note that BackgroundImage.prototype.ready applies this ready method
			 * to itself. Since BackgroundImage is an UploadControl, the value
			 * is the attachment URL instead of the attachment ID. In this case
			 * we skip fetching the attachment data because we have no ID available,
			 * and it is the responsibility of the UploadControl to set the control's
			 * attachmentData before calling the renderContent method.
			 *
			 * @since 4.6.0
			 *
			 * @param {number|string} value The attachment to set.
			 *
			 * @return {void}
			 */
			function setAttachmentDataAndRenderContent( value ) {
				var hasAttachmentData = $.Deferred();

				if ( control.extended( api.UploadControl ) ) {
					hasAttachmentData.resolve();
				} else {
					value = parseInt( value, 10 );
					if ( _.isNaN( value ) || value <= 0 ) {
						delete control.params.attachment;
						hasAttachmentData.resolve();
					} else if ( control.params.attachment && control.params.attachment.id === value ) {
						hasAttachmentData.resolve();
					}
				}

				// Fetch the attachment data.
				if ( 'pending' === hasAttachmentData.state() ) {
					wp.media.attachment( value ).fetch().done( function() {
						control.params.attachment = this.attributes;
						hasAttachmentData.resolve();

						// Send attachment information to the preview for possible use in `postMessage` transport.
						wp.customize.previewer.send( control.setting.id + '-attachment-data', this.attributes );
					} );
				}

				hasAttachmentData.done( function() {
					control.renderContent();
				} );
			}

			// Ensure attachment data is initially set for dynamically-instantiated controls.
			setAttachmentDataAndRenderContent( control.setting() );

			// Update the attachment data and re-render the control when the setting changes.
			control.setting.bind( setAttachmentDataAndRenderContent );
		},

		/**
		 * Pauses the player.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		pausePlayer: function () {
			this.player && this.player.pause();
		},

		/**
		 * Removes the player.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		cleanupPlayer: function () {
			this.player && wp.media.mixin.removePlayer( this.player );
		},

		/**
		 * Opens the media modal.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		openFrame: function( event ) {
			if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
				return;
			}

			event.preventDefault();

			if ( ! this.frame ) {
				this.initFrame();
			}

			this.frame.open();
		},

		/**
		 * Creates a media modal select frame, and stores it so the instance can be reused when needed.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		initFrame: function() {
			this.frame = wp.media({
				button: {
					text: this.params.button_labels.frame_button
				},
				states: [
					new wp.media.controller.Library({
						title:     this.params.button_labels.frame_title,
						library:   wp.media.query({ type: this.params.mime_type }),
						multiple:  false,
						date:      false
					})
				]
			});

			// Run a callback when a file is selected.
			this.frame.on( 'select', this.select );
		},

		/**
		 * Handles the selection of attachments in the media modal.
		 *
		 * If an image is selected, it also gets the additional information and sets it within the control.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		select: function() {
			// Get the attachment from the modal frame.
			var node,
				attachment = this.frame.state().get( 'selection' ).first().toJSON(),
				mejsSettings = window._wpmejsSettings || {};

			this.params.attachment = attachment;

			// Set the Customizer setting; the callback takes care of rendering.
			this.setting( attachment.id );
			node = this.container.find( 'audio, video' ).get(0);

			// Initialize audio/video previews.
			if ( node ) {
				this.player = new MediaElementPlayer( node, mejsSettings );
			} else {
				this.cleanupPlayer();
			}
		},

		/**
		 * Resets the setting to the default value.
		 *
		 * @since 4.1.0
		 *
		 * @return {void}
		 */
		restoreDefault: function( event ) {
			if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
				return;
			}
			event.preventDefault();

			this.params.attachment = this.params.defaultAttachment;
			this.setting( this.params.defaultAttachment.url );
		},

		/**
		 * Removes the selected media file from the control.
		 *
		 * Gets called when the "Remove" link is clicked. Also empties the setting.
		 *
		 * @since 4.1.0
		 *
		 * @param {Object} event jQuery Event object.
		 *
		 * @return {void}
		 */
		removeFile: function( event ) {
			if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
				return;
			}
			event.preventDefault();

			this.params.attachment = {};
			this.setting( '' );

			// Not bound to setting change when emptying.
			this.renderContent();
		}
	});

	/**
	 * An upload control, which utilizes the media modal.
	 *
	 * @since 3.5.0
	 *
	 * @constructs wp.customize.UploadControl
	 * @augments   wp.customize.MediaControl
	 * @memberOf   wp.customize
	 * @inheritDoc
	 */
	api.UploadControl = api.MediaControl.extend(/** @lends wp.customize.UploadControl.prototype */{

		/**
		 * Handles the selection of attachments in the upload modal.
		 *
		 * If an image is selected, it also gets the additional information and sets it within the control.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		select: function() {
			// Get the attachment from the modal frame.
			var node,
				attachment = this.frame.state().get( 'selection' ).first().toJSON(),
				mejsSettings = window._wpmejsSettings || {};

			this.params.attachment = attachment;

			// Set the Customizer setting; the callback takes care of rendering.
			this.setting( attachment.url );
			node = this.container.find( 'audio, video' ).get(0);

			// Initialize audio/video previews.
			if ( node ) {
				this.player = new MediaElementPlayer( node, mejsSettings );
			} else {
				this.cleanupPlayer();
			}
		},

		/**
		 * @deprecated 4.1.0
		 */
		success: function() {},

		/**
		 * @deprecated 4.1.0
		 */
		removerVisibility: function() {}
	});

	/**
	 * A control for uploading images.
	 *
	 * This control no longer needs to do anything more
	 * than what the upload control does in JS.
	 *
	 * @since 3.5.0
	 *
	 * @constructs wp.customize.ImageControl
	 * @augments   wp.customize.UploadControl
	 * @memberOf   wp.customize
	 * @inheritDoc
	 */
	api.ImageControl = api.UploadControl.extend(/** @lends wp.customize.ImageControl.prototype */{
		/**
		 * @deprecated 4.1.0
		 */
		thumbnailSrc: function() {}
	});

	/**
	 * A control for uploading background images.
	 *
	 * @since 3.5.0
	 *
	 * @constructs wp.customize.BackgroundControl
	 * @augments   wp.customize.UploadControl
	 * @memberOf   wp.customize
	 * @inheritDoc
	 */
	api.BackgroundControl = api.UploadControl.extend(/** @lends wp.customize.BackgroundControl.prototype */{

		/**
		 * Sets up internal event bindings when the control's DOM structure is ready.
		 *
		 * @return {void}
		 */
		ready: function() {
			api.UploadControl.prototype.ready.apply( this, arguments );
		},

		/**
		 * Handles the selection of an attachment in the BackgroundControl media modal.
		 *
		 * Does an additional AJAX request for setting the background context.
		 *
		 * @since 3.5.0
		 *
		 * @return {void}
		 */
		select: function() {
			api.UploadControl.prototype.select.apply( this, arguments );

			wp.ajax.post( 'custom-background-add', {
				nonce: _wpCustomizeBackground.nonces.add,
				wp_customize: 'on',
				customize_theme: api.settings.theme.stylesheet,
				attachment_id: this.params.attachment.id
			} );
		}
	});

	/**
	 * A control for positioning a background image.
	 *
	 * @since 4.7.0
	 *
	 * @constructs wp.customize.BackgroundPositionControl
	 * @augments   wp.customize.Control
	 * @memberOf   wp.customize
	 * @inheritDoc
	 */
	api.BackgroundPositionControl = api.Control.extend(/** @lends wp.customize.BackgroundPositionControl.prototype */{

		/**
		 * Sets up the control UI once it's embedded in the DOM and settings are created.
		 *
		 * @since 4.7.0
		 *
		 * @return {void}
		 */
		ready: function() {
			var control = this, updateRadios;

			control.container.on( 'change', 'input[name="background-position"]', function() {
				var position = $( this ).val().split( ' ' );
				control.settings.x( position[0] );
				control.settings.y( position[1] );
			} );

			updateRadios = _.debounce( function() {
				var x, y, radioInput, inputValue;
				x = control.settings.x.get();
				y = control.settings.y.get();
				inputValue = String( x ) + ' ' + String( y );
				radioInput = control.container.find( 'input[name="background-position"][value="' + inputValue + '"]' );
				radioInput.click();
			} );
			control.settings.x.bind( updateRadios );
			control.settings.y.bind( updateRadios );

			// Set initial UI.
			updateRadios();
		}
	} );

	/**
	 * A control for selecting and cropping an image.
	 *
	 * @since 4.3.0
	 *
	 * @construct wp.customize.CroppedImageControl
	 * @augments  wp.customize.MediaControl
	 * @memberOf  wp.customize
	 * @inheritDoc
	 */
	api.CroppedImageControl = api.MediaControl.extend(/** @lends wp.customize.CroppedImageControl.prototype */{

		/**
		 * Opens the media modal to the library state.
		 *
		 * @since 4.3.0
		 *
		 * @return {void}
		 */
		openFrame: function( event ) {
			if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
				return;
			}

			this.initFrame();
			this.frame.setState( 'library' ).open();
		},

		/**
		 * Creates a media modal select frame and stores it so the instance can be reused when needed.
		 *
		 * @since 4.3.0
		 *
		 * @return {void}
		 */
		initFrame: function() {
			var l10n = _wpMediaViewsL10n;

			this.frame = wp.media({
				button: {
					text: l10n.select,
					close: false
				},
				states: [
					new wp.media.controller.Library({
						title: this.params.button_labels.frame_title,
						library: wp.media.query({ type: 'image' }),
						multiple: false,
						date: false,
						priority: 20,
						suggestedWidth: this.params.width,
						suggestedHeight: this.params.height
					}),
					new wp.media.controller.CustomizeImageCropper({
						imgSelectOptions: this.calculateImageSelectOptions,
						control: this
					})
				]
			});

			this.frame.on( 'select', this.onSelect, this );
			this.frame.on( 'cropped', this.onCropped, this );
			this.frame.on( 'skippedcrop', this.onSkippedCrop, this );
		},

		/**
		 * Switches to the cropper state if an image has been selected in the media modal.
		 * This only happens if the image isn't the right size.
		 *
		 * @since 4.3.0
		 *
		 * @return {void}
		 */
		onSelect: function() {
			var attachment = this.frame.state().get( 'selection' ).first().toJSON();

			if ( this.params.width === attachment.width && this.params.height === attachment.height && ! this.params.flex_width && ! this.params.flex_height ) {
				this.setImageFromAttachment( attachment );
				this.frame.close();
			} else {
				this.frame.setState( 'cropper' );
			}
		},

		/**
		 * Applies the cropped image data to the setting once cropping is complete.
		 *
		 * @since 4.3.0
		 *
		 * @param {Object} croppedImage The cropped attachment data.
		 *
		 * @return {void}
		 */
		onCropped: function( croppedImage ) {
			this.setImageFromAttachment( croppedImage );
		},

		/**
		 * Returns a set of options, computed from the attached image data and
		 * control-specific data, to be fed to the imgAreaSelect plugin in
		 * wp.media.view.Cropper.
		 *
		 * @since 4.3.0
		 *
		 * @param {wp.media.model.Attachment}   attachment The attachment image data.
		 * @param {wp.media.controller.Cropper} controller The controller containing the imgAreaSelect plugin.
		 *
		 * @return {Object} The computed set of options.
		 */
		calculateImageSelectOptions: function( attachment, controller ) {
			var control    = controller.get( 'control' ),
				flexWidth  = !! parseInt( control.params.flex_width, 10 ),
				flexHeight = !! parseInt( control.params.flex_height, 10 ),
				realWidth  = attachment.get( 'width' ),
				realHeight = attachment.get( 'height' ),
				xInit = parseInt( control.params.width, 10 ),
				yInit = parseInt( control.params.height, 10 ),
				ratio = xInit / yInit,
				xImg  = xInit,
				yImg  = yInit,
				x1, y1, imgSelectOptions;

			controller.set( 'canSkipCrop', ! control.mustBeCropped( flexWidth, flexHeight, xInit, yInit, realWidth, realHeight ) );

			if ( realWidth / realHeight > ratio ) {
				yInit = realHeight;
				xInit = yInit * ratio;
			} else {
				xInit = realWidth;
				yInit = xInit / ratio;
			}

			x1 = ( realWidth - xInit ) / 2;
			y1 = ( realHeight - yInit ) / 2;

			imgSelectOptions = {
				handles: true,
				keys: true,
				instance: true,
				persistent: true,
				imageWidth: realWidth,
				imageHeight: realHeight,
				minWidth: xImg > xInit ? xInit : xImg,
				minHeight: yImg > yInit ? yInit : yImg,
				x1: x1,
				y1: y1,
				x2: xInit + x1,
				y2: yInit + y1
			};

			if ( flexHeight === false && flexWidth === false ) {
				imgSelectOptions.aspectRatio = xInit + ':' + yInit;
			}

			if ( true === flexHeight ) {
				delete imgSelectOptions.minHeight;
				imgSelectOptions.maxWidth = realWidth;
			}

			if ( true === flexWidth ) {
				delete imgSelectOptions.minWidth;
				imgSelectOptions.maxHeight = realHeight;
			}

			return imgSelectOptions;
		},

		/**
		 * Returns whether the image must be cropped, based on required dimensions.
		 *
		 * @since 4.3.0
		 *
		 * @param {boolean} flexW   Whether or not the width is flexible.
		 * @param {boolean} flexH   Whether or not the height is flexible.
		 * @param {int}     dstW    The destination width.
		 * @param {int}     dstH    The destination height.
		 * @param {int}     imgW    The current image width.
		 * @param {int}     imgH    The current image height.
		 *
		 * @return {boolean} Whether or not the image should be cropped.
		 */
		mustBeCropped: function( flexW, flexH, dstW, dstH, imgW, imgH ) {
			if ( true === flexW && true === flexH ) {
				return false;
			}

			if ( true === flexW && dstH === imgH ) {
				return false;
			}

			if ( true === flexH && dstW === imgW ) {
				return false;
			}

			if ( dstW === imgW && dstH === imgH ) {
				return false;
			}

			if ( imgW <= dstW ) {
				return false;
			}

			return true;
		},

		/**
		 * Applies the image data directly to the setting if cropping was skipped.
		 *
		 * @since 4.3.0
		 *
		 * @return {void}
		 */
		onSkippedCrop: function() {
			var attachment = this.frame.state().get( 'selection' ).first().toJSON();
			this.setImageFromAttachment( attachment );
		},

		/**
		 * Updates the setting and re-renders the control UI based on passed the attachment.
		 *
		 * @since 4.3.0
		 *
		 * @param {Object} attachment The attachment to set the image from.
		 *
		 * @return {void}
		 */
		setImageFromAttachment: function( attachment ) {
			this.params.attachment = attachment;

			// Set the Customizer setting; the callback takes care of rendering.
			this.setting( attachment.id );
		}
	});

	/**
	 * A control for selecting and cropping Site Icons.
	 *
	 * @since 4.3.0
	 *
	 * @constructs wp.customize.SiteIconControl
	 * @augments   wp.customize.CroppedImageControl
	 * @memberOf   wp.customize
	 * @inheritDoc
	 */
	api.SiteIconControl = api.CroppedImageControl.extend(/** @lends wp.customize.SiteIconControl.prototype */{

		/**
		 * Creates a media modal select frame and stores it so the instance can be reused when needed.
		 *
		 * @since 4.3.0
		 *
		 * @return {void}
		 */
		initFrame: function() {
			var l10n = _wpMediaViewsL10n;

			this.frame = wp.media({
				button: {
					text: l10n.select,
					close: false
				},
				states: [
					new wp.media.controller.Library({
						title: this.params.button_labels.frame_title,
						library: wp.media.query({ type: 'image' }),
						multiple: false,
						date: false,
						priority: 20,
						suggestedWidth: this.params.width,
						suggestedHeight: this.params.height
					}),
					new wp.media.controller.SiteIconCropper({
						imgSelectOptions: this.calculateImageSelectOptions,
						control: this
					})
				]
			});

			this.frame.on( 'select', this.onSelect, this );
			this.frame.on( 'cropped', this.onCropped, this );
			this.frame.on( 'skippedcrop', this.onSkippedCrop, this );
		},

		/**
		 * Switches to the cropper state if an image has been selected in the media modal.
		 * This only happens if the image isn't the right size.
		 *
		 * @since 4.3.0
		 *
		 * @return {void}
		 */
		onSelect: function() {
			var attachment = this.frame.state().get( 'selection' ).first().toJSON(),
				controller = this;

			if ( this.params.width === attachment.width && this.params.height === attachment.height && ! this.params.flex_width && ! this.params.flex_height ) {
				wp.ajax.post( 'crop-image', {
					nonce: attachment.nonces.edit,
					id: attachment.id,
					context: 'site-icon',
					cropDetails: {
						x1: 0,
						y1: 0,
						width: this.params.width,
						height: this.params.height,
						dst_width: this.params.width,
						dst_height: this.params.height
					}
				} ).done( function( croppedImage ) {
					controller.setImageFromAttachment( croppedImage );
					controller.frame.close();
				} ).fail( function() {
					controller.frame.trigger('content:error:crop');
				} );
			} else {
				this.frame.setState( 'cropper' );
			}
		},

		/**
		 * Updates the setting and re-renders the control UI based on passed the attachment.
		 *
		 * @since 4.3.0
		 *
		 * @param {Object} attachment The attachment to set the image from.
		 *
		 * @return {void}
		 */
		setImageFromAttachment: function( attachment ) {
			var sizes = [ 'site_icon-32', 'thumbnail', 'full' ], link,
				icon;

			_.each( sizes, function( size ) {
				if ( ! icon && ! _.isUndefined ( attachment.sizes[ size ] ) ) {
					icon = attachment.sizes[ size ];
				}
			} );

			this.params.attachment = attachment;

			// Set the Customizer setting; the callback takes care of rendering.
			this.setting( attachment.id );

			if ( ! icon ) {
				return;
			}

			// Update the icon in-browser.
			link = $( 'link[rel="icon"][sizes="32x32"]' );
			link.attr( 'href', icon.url );
		},

		/**
		 * Empties the setting when the "Remove" link is clicked.
		 *
 		 * @since 4.3.0
		 *
		 * @param {Object} event jQuery Event object
		 *
		 * @return {void}
		 */
		removeFile: function( event ) {
			if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
				return;
			}
			event.preventDefault();

			this.params.attachment = {};
			this.setting( '' );
			this.renderContent(); // Not bound to setting change when emptying.
			$( 'link[rel="icon"][sizes="32x32"]' ).attr( 'href', '/favicon.ico' ); // Set to default.
		}
	});

	/**
	 * A control for selecting and cropping the Site header.
	 *
	 * @since 3.9.0
	 *
	 * @constructs wp.customize.HeaderControl
	 * @augments   wp.customize.Control
	 * @memberOf   wp.customize
	 * @inheritDoc
	 */
	api.HeaderControl = api.Control.extend(/** @lends wp.customize.HeaderControl.prototype */{

		/**
		 * Sets up the control UI once it's embedded in the DOM and settings are created.
		 *
		 * @since 3.9.0
		 *
		 * @return {void}
		 */
		ready: function() {
			this.btnRemove = $('#customize-control-header_image .actions .remove');
			this.btnNew    = $('#customize-control-header_image .actions .new');

			_.bindAll(this, 'openMedia', 'removeImage');

			this.btnNew.on( 'click', this.openMedia );
			this.btnRemove.on( 'click', this.removeImage );

			api.HeaderTool.currentHeader = this.getInitialHeaderImage();

			new api.HeaderTool.CurrentView({
				model: api.HeaderTool.currentHeader,
				el: '#customize-control-header_image .current .container'
			});

			new api.HeaderTool.ChoiceListView({
				collection: api.HeaderTool.UploadsList = new api.HeaderTool.ChoiceList(),
				el: '#customize-control-header_image .choices .uploaded .list'
			});

			new api.HeaderTool.ChoiceListView({
				collection: api.HeaderTool.DefaultsList = new api.HeaderTool.DefaultsList(),
				el: '#customize-control-header_image .choices .default .list'
			});

			api.HeaderTool.combinedList = api.HeaderTool.CombinedList = new api.HeaderTool.CombinedList([
				api.HeaderTool.UploadsList,
				api.HeaderTool.DefaultsList
			]);

			// Ensure custom-header-crop AJAX requests bootstrap the Customizer to activate the previewed theme.
			wp.media.controller.Cropper.prototype.defaults.doCropArgs.wp_customize = 'on';
			wp.media.controller.Cropper.prototype.defaults.doCropArgs.customize_theme = api.settings.theme.stylesheet;
		},

		/**
		 * Returns a new instance of api.HeaderTool.ImageModel based on the currently saved header image (if any).
		 *
		 * @since 4.2.0
		 *
		 * @return {Object} The new ImageModel instance.
		 */
		getInitialHeaderImage: function() {
			if ( ! api.get().header_image || ! api.get().header_image_data || _.contains( [ 'remove-header', 'random-default-image', 'random-uploaded-image' ], api.get().header_image ) ) {
				return new api.HeaderTool.ImageModel();
			}

			// Get the matching uploaded image object.
			var currentHeaderObject = _.find( _wpCustomizeHeader.uploads, function( imageObj ) {
				return ( imageObj.attachment_id === api.get().header_image_data.attachment_id );
			} );
			// Fall back to the raw current header image.
			if ( ! currentHeaderObject ) {
				currentHeaderObject = {
					url: api.get().header_image,
					thumbnail_url: api.get().header_image,
					attachment_id: api.get().header_image_data.attachment_id
				};
			}

			return new api.HeaderTool.ImageModel({
				header: currentHeaderObject,
				choice: currentHeaderObject.url.split( '/' ).pop()
			});
		},

		/**
		 * Calculates the select options for the image to be used in the Cropper object.
		 *
		 * @since 3.9.0
		 *
		 * @param {wp.media.model.Attachment}   attachment The image attachment to calculate the options for.
		 * @param {wp.media.controller.Cropper} controller The Cropper controller to
		 *
		 * @return {Object} The image select options.
		 */
		calculateImageSelectOptions: function(attachment, controller) {
			var xInit = parseInt(_wpCustomizeHeader.data.width, 10),
				yInit = parseInt(_wpCustomizeHeader.data.height, 10),
				flexWidth = !! parseInt(_wpCustomizeHeader.data['flex-width'], 10),
				flexHeight = !! parseInt(_wpCustomizeHeader.data['flex-height'], 10),
				ratio, xImg, yImg, realHeight, realWidth,
				imgSelectOptions;

			realWidth = attachment.get('width');
			realHeight = attachment.get('height');

			this.headerImage = new api.HeaderTool.ImageModel();
			this.headerImage.set({
				themeWidth: xInit,
				themeHeight: yInit,
				themeFlexWidth: flexWidth,
				themeFlexHeight: flexHeight,
				imageWidth: realWidth,
				imageHeight: realHeight
			});

			controller.set( 'canSkipCrop', ! this.headerImage.shouldBeCropped() );

			ratio = xInit / yInit;
			xImg = realWidth;
			yImg = realHeight;

			if ( xImg / yImg > ratio ) {
				yInit = yImg;
				xInit = yInit * ratio;
			} else {
				xInit = xImg;
				yInit = xInit / ratio;
			}

			imgSelectOptions = {
				handles: true,
				keys: true,
				instance: true,
				persistent: true,
				imageWidth: realWidth,
				imageHeight: realHeight,
				x1: 0,
				y1: 0,
				x2: xInit,
				y2: yInit
			};

			if (flexHeight === false && flexWidth === false) {
				imgSelectOptions.aspectRatio = xInit + ':' + yInit;
			}
			if (flexHeight === false ) {
				imgSelectOptions.maxHeight = yInit;
			}
			if (flexWidth === false ) {
				imgSelectOptions.maxWidth = xInit;
			}

			return imgSelectOptions;
		},

		/**
		 * Sets up and opens the Media Manager in order to select an image.
		 *
		 * Depending on both the size of the image and the properties of the
		 * current theme, a cropping step after selection may be required or
		 * skippable.
		 *
		 * @since 3.9.0
		 *
		 * @param {event} event The event that triggered the opening of the Media Manager.
		 *
		 * @return {void}
		 */
		openMedia: function(event) {
			var l10n = _wpMediaViewsL10n;

			event.preventDefault();

			this.frame = wp.media({
				button: {
					text: l10n.selectAndCrop,
					close: false
				},
				states: [
					new wp.media.controller.Library({
						title:     l10n.chooseImage,
						library:   wp.media.query({ type: 'image' }),
						multiple:  false,
						date:      false,
						priority:  20,
						suggestedWidth: _wpCustomizeHeader.data.width,
						suggestedHeight: _wpCustomizeHeader.data.height
					}),
					new wp.media.controller.Cropper({
						imgSelectOptions: this.calculateImageSelectOptions
					})
				]
			});

			this.frame.on('select', this.onSelect, this);
			this.frame.on('cropped', this.onCropped, this);
			this.frame.on('skippedcrop', this.onSkippedCrop, this);

			this.frame.open();
		},

		/**
		 * Switches to the cropper state after an image is selected in the media modal.
		 *
		 * @since 3.9.0
		 *
		 * @return {void}
		 */
		onSelect: function() {
			this.frame.setState('cropper');
		},

		/**
		 * Applies the cropped image data to the setting after the image has been cropped,
		 *
		 * @since 3.9.0
		 *
		 * @param {Object} croppedImage The cropped attachment data.
		 */
		onCropped: function(croppedImage) {
			var url = croppedImage.url,
				attachmentId = croppedImage.attachment_id,
				w = croppedImage.width,
				h = croppedImage.height;
			this.setImageFromURL(url, attachmentId, w, h);
		},

		/**
		 * Applies the image data directly to the setting if cropping was skipped.
		 *
		 * @since 3.9.0
		 *
		 * @param {Object} selection The selected image.
		 */
		onSkippedCrop: function(selection) {
			var url = selection.get('url'),
				w = selection.get('width'),
				h = selection.get('height');
			this.setImageFromURL(url, selection.id, w, h);
		},

		/**
		 * Creates a new wp.customize.HeaderTool.ImageModel from the provided header image data and inserts
		 * it into the user-uploaded headers collection.
		 *
		 * @since 3.9.0
		 *
		 * @param {string} url          The URL of the image.
		 * @param {number} attachmentId The ID of the attachment.
		 * @param {number} width        The width of the image.
		 * @param {number} height       The height of the image.
		 *
		 * @return {void}
		 */
		setImageFromURL: function(url, attachmentId, width, height) {
			var choice, data = {};

			data.url = url;
			data.thumbnail_url = url;
			data.timestamp = _.now();

			if (attachmentId) {
				data.attachment_id = attachmentId;
			}

			if (width) {
				data.width = width;
			}

			if (height) {
				data.height = height;
			}

			choice = new api.HeaderTool.ImageModel({
				header: data,
				choice: url.split('/').pop()
			});
			api.HeaderTool.UploadsList.add(choice);
			api.HeaderTool.currentHeader.set(choice.toJSON());
			choice.save();
			choice.importImage();
		},

		/**
		 * Triggers the necessary events to deselect an image which was set as
		 * the currently selected one.
		 *
		 * @since 3.9.0
		 *
		 * @return {void}
		 */
		removeImage: function() {
			api.HeaderTool.currentHeader.trigger('hide');
			api.HeaderTool.CombinedList.trigger('control:removeImage');
		}

	});

	/**
	 * A control for managing themes.
	 *
	 * @since 3.9.0
	 *
	 * @constructs wp.customize.ThemeControl
	 * @augments   wp.customize.Control
	 * @memberOf   wp.customize
	 * @inheritDoc
	 */
	api.ThemeControl = api.Control.extend(/** @lends wp.customize.ThemeControl.prototype */{

		touchDrag: false,
		screenshotRendered: false,

		/**
		 * Sets up the control UI and binds events.
		 *
		 * @since 4.2.0
		 *
		 * @return {void}
		 */
		ready: function() {
			var control = this, panel = api.panel( 'themes' );

			/**
			 * Determines whether switch buttons should be disabled based on if it's possible to switch themes.
			 *
			 * @since 4.9.0
			 *
			 * @return {boolean} True if the switching themes button should be disabled.
			 */
			function disableSwitchButtons() {
				return ! panel.canSwitchTheme( control.params.theme.id );
			}

			/**
			 * Determines if the install button should be disabled or not.
			 *
			 * This is necessary since supplying SFTP credentials does not work yet. See #42184.
			 *
			 *  @since 4.9.0
			 *
			 * @return {boolean} True if the install button should be disabled.
			 */
			function disableInstallButtons() {
				return disableSwitchButtons() || false === api.settings.theme._canInstall || true === api.settings.theme._filesystemCredentialsNeeded;
			}

			/**
			 * Updates the preview and install buttons disabled state.
			 *
			 * @since 3.9.0
			 *
			 * @return {void}
			 */
			function updateButtons() {
				control.container.find( 'button.preview, button.preview-theme' ).toggleClass( 'disabled', disableSwitchButtons() );
				control.container.find( 'button.theme-install' ).toggleClass( 'disabled', disableInstallButtons() );
			}

			api.state( 'selectedChangesetStatus' ).bind( updateButtons );
			api.state( 'changesetStatus' ).bind( updateButtons );
			updateButtons();

			control.container.on( 'touchmove', '.theme', function() {
				control.touchDrag = true;
			});

			// Bind the details view trigger.
			control.container.on( 'click keydown touchend', '.theme', function( event ) {
				var section;
				if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
					return;
				}

				// Bail if the user scrolled on a touch device.
				if ( control.touchDrag === true ) {
					return control.touchDrag = false;
				}

				// Prevent the modal from showing when the user clicks the action button.
				if ( $( event.target ).is( '.theme-actions .button, .update-theme' ) ) {
					return;
				}

				// Keep this AFTER the key filter above to prevent further default behavior from executing.
				event.preventDefault();
				section = api.section( control.section() );
				section.showDetails( control.params.theme, function() {

					// Temporary special function since supplying SFTP credentials does not work yet. See #42184.
					if ( api.settings.theme._filesystemCredentialsNeeded ) {
						section.overlay.find( '.theme-actions .delete-theme' ).remove();
					}
				} );
			});

			// Set the screenshot once it's been rendered.
			control.container.on( 'render-screenshot', function() {
				var $screenshot = $( this ).find( 'img' ),
					source = $screenshot.data( 'src' );

				if ( source ) {
					$screenshot.attr( 'src', source );
				}
				control.screenshotRendered = true;
			});
		},

		/**
		 * Shows or hides the theme based on the presence of the term in the title, description, tags, and author.
		 *
		 * @since 4.2.0
		 *
		 * @param {Array} terms An array of terms to search for.
		 *
		 * @return {boolean} Whether a theme control was activated or not.
		 */
		filter: function( terms ) {
			var control = this,
				matchCount = 0,
				haystack = control.params.theme.name + ' ' +
					control.params.theme.description + ' ' +
					control.params.theme.tags + ' ' +
					control.params.theme.author + ' ';
			haystack = haystack.toLowerCase().replace( '-', ' ' );

			// Backwards-compatibility for behavior in WordPress 4.2.0 to 4.8.X.
			if ( ! _.isArray( terms ) ) {
				terms = [ terms ];
			}

			// Always give exact name matches highest ranking.
			if ( control.params.theme.name.toLowerCase() === terms.join( ' ' ) ) {
				matchCount = 100;
			} else {

				// Search for and weight (by 10) complete term matches.
				matchCount = matchCount + 10 * ( haystack.split( terms.join( ' ' ) ).length - 1 );

				// Search for each term individually (as whole-word and partial match) and sum weighted match counts.
				_.each( terms, function( term ) {

					// Whole-word, double-weighted.
					matchCount = matchCount + 2 * ( haystack.split( term + ' ' ).length - 1 );

					// Partial word, to minimize empty intermediate searches while typing.
					matchCount = matchCount + haystack.split( term ).length - 1;
				});

				// Upper limit on match ranking.
				if ( matchCount > 99 ) {
					matchCount = 99;
				}
			}

			if ( 0 !== matchCount ) {
				control.activate();
				// Sort results by match count.
				control.params.priority = 101 - matchCount;
				return true;
			} else {
				// Hide control.
				control.deactivate();
				control.params.priority = 101;
				return false;
			}
		},

		/**
		 * Rerenders the theme from its JavaScript template with the installed type.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		rerenderAsInstalled: function( installed ) {
			var control = this, section;
			if ( installed ) {
				control.params.theme.type = 'installed';
			} else {
				section = api.section( control.params.section );
				control.params.theme.type = section.params.action;
			}
			control.renderContent(); // Replaces existing content.
			control.container.trigger( 'render-screenshot' );
		}
	});

	/**
	 * A control for the code editor.
	 *
	 * @since 4.9.0
	 *
	 * @constructs wp.customize.CodeEditorControl
	 * @augments   wp.customize.Control
	 * @memberOf   wp.customize
	 * @inheritDoc
	 */
	api.CodeEditorControl = api.Control.extend(/** @lends wp.customize.CodeEditorControl.prototype */{

		/**
		 * Initialize the control and binds its events.
		 *
		 * @since 4.9.0
		 *
		 * @param {string} id      Unique identifier for the control instance.
		 * @param {Object} options Options hash for the control instance.
		 *
		 * @return {void}
		 */
		initialize: function( id, options ) {
			var control = this;
			control.deferred = _.extend( control.deferred || {}, {
				codemirror: $.Deferred()
			} );
			api.Control.prototype.initialize.call( control, id, options );

			// Rendering is debounced so the props will be used when rendering happens after the add event.
			control.notifications.bind( 'add', function( notification ) {

				// Skip if control notification is not from setting csslint_error notification.
				if ( notification.code !== control.setting.id + ':csslint_error' ) {
					return;
				}

				// Customize the template and behavior of csslint_error notifications.
				notification.templateId = 'customize-code-editor-lint-error-notification';
				notification.render = (function( render ) {
					return function() {
						var li = render.call( this );
						li.find( 'input[type=checkbox]' ).on( 'click', function() {
							control.setting.notifications.remove( 'csslint_error' );
						} );
						return li;
					};
				})( notification.render );
			} );
		},

		/**
		 * Initializes the editor when the containing section is ready and expanded.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		ready: function() {
			var control = this;
			if ( ! control.section() ) {
				control.initEditor();
				return;
			}

			// Wait to initialize the editor until the section is embedded and expanded.
			api.section( control.section(), function( section ) {
				section.deferred.embedded.done( function() {
					var onceExpanded;
					if ( section.expanded() ) {
						control.initEditor();
					} else {
						onceExpanded = function( isExpanded ) {
							if ( isExpanded ) {
								control.initEditor();
								section.expanded.unbind( onceExpanded );
							}
						};
						section.expanded.bind( onceExpanded );
					}
				} );
			} );
		},

		/**
		 * Initializes the editor.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		initEditor: function() {
			var control = this, element, editorSettings = false;

			// Obtain editorSettings for instantiation.
			if ( wp.codeEditor && ( _.isUndefined( control.params.editor_settings ) || false !== control.params.editor_settings ) ) {

				// Obtain default editor settings.
				editorSettings = wp.codeEditor.defaultSettings ? _.clone( wp.codeEditor.defaultSettings ) : {};
				editorSettings.codemirror = _.extend(
					{},
					editorSettings.codemirror,
					{
						indentUnit: 2,
						tabSize: 2
					}
				);

				// Merge editor_settings param on top of defaults.
				if ( _.isObject( control.params.editor_settings ) ) {
					_.each( control.params.editor_settings, function( value, key ) {
						if ( _.isObject( value ) ) {
							editorSettings[ key ] = _.extend(
								{},
								editorSettings[ key ],
								value
							);
						}
					} );
				}
			}

			element = new api.Element( control.container.find( 'textarea' ) );
			control.elements.push( element );
			element.sync( control.setting );
			element.set( control.setting() );

			if ( editorSettings ) {
				control.initSyntaxHighlightingEditor( editorSettings );
			} else {
				control.initPlainTextareaEditor();
			}
		},

		/**
		 * Ensures the editor gets focused when the control is focused.
		 *
		 * @since 4.9.0
		 *
		 * @callback completeCallback
		 * @param {Object}           [params]                   The focus parameters to use.
		 * @param {completeCallback} [params.completeCallback]  Function to call when expansion is complete.
		 *
		 * @return {void}
		 */
		focus: function( params ) {
			var control = this, extendedParams = _.extend( {}, params ), originalCompleteCallback;
			originalCompleteCallback = extendedParams.completeCallback;
			extendedParams.completeCallback = function() {
				if ( originalCompleteCallback ) {
					originalCompleteCallback();
				}
				if ( control.editor ) {
					control.editor.codemirror.focus();
				}
			};
			api.Control.prototype.focus.call( control, extendedParams );
		},

		/**
		 * Initializes the syntax-highlighting editor.
		 *
		 * @since 4.9.0
		 *
		 * @param {Object} codeEditorSettings The code editor settings to use.
		 *
		 * @return {void}
		 */
		initSyntaxHighlightingEditor: function( codeEditorSettings ) {
			var control = this, $textarea = control.container.find( 'textarea' ), settings, suspendEditorUpdate = false;

			settings = _.extend( {}, codeEditorSettings, {
				onTabNext: _.bind( control.onTabNext, control ),
				onTabPrevious: _.bind( control.onTabPrevious, control ),
				onUpdateErrorNotice: _.bind( control.onUpdateErrorNotice, control )
			});

			control.editor = wp.codeEditor.initialize( $textarea, settings );

			// Improve the editor accessibility.
			$( control.editor.codemirror.display.lineDiv )
				.attr({
					role: 'textbox',
					'aria-multiline': 'true',
					'aria-label': control.params.label,
					'aria-describedby': 'editor-keyboard-trap-help-1 editor-keyboard-trap-help-2 editor-keyboard-trap-help-3 editor-keyboard-trap-help-4'
				});

			// Focus the editor when clicking on its label.
			control.container.find( 'label' ).on( 'click', function() {
				control.editor.codemirror.focus();
			});

			/*
			 * When the CodeMirror instance changes, mirror to the textarea,
			 * where we have our "true" change event handler bound.
			 */
			control.editor.codemirror.on( 'change', function( codemirror ) {
				suspendEditorUpdate = true;
				$textarea.val( codemirror.getValue() ).trigger( 'change' );
				suspendEditorUpdate = false;
			});

			// Update CodeMirror when the setting is changed by another plugin.
			control.setting.bind( function( value ) {
				if ( ! suspendEditorUpdate ) {
					control.editor.codemirror.setValue( value );
				}
			});

			// Prevent collapsing section when hitting Esc to tab out of editor.
			control.editor.codemirror.on( 'keydown', function onKeydown( codemirror, event ) {
				var escKeyCode = 27;
				if ( escKeyCode === event.keyCode ) {
					event.stopPropagation();
				}
			});

			control.deferred.codemirror.resolveWith( control, [ control.editor.codemirror ] );
		},

		/**
		 * Handles tabbing to the field after the editor.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		onTabNext: function onTabNext() {
			var control = this, controls, controlIndex, section;
			section = api.section( control.section() );
			controls = section.controls();
			controlIndex = controls.indexOf( control );
			if ( controls.length === controlIndex + 1 ) {
				$( '#customize-footer-actions .collapse-sidebar' ).focus();
			} else {
				controls[ controlIndex + 1 ].container.find( ':focusable:first' ).focus();
			}
		},

		/**
		 * Handles tabbing to the field before the editor.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		onTabPrevious: function onTabPrevious() {
			var control = this, controls, controlIndex, section;
			section = api.section( control.section() );
			controls = section.controls();
			controlIndex = controls.indexOf( control );
			if ( 0 === controlIndex ) {
				section.contentContainer.find( '.customize-section-title .customize-help-toggle, .customize-section-title .customize-section-description.open .section-description-close' ).last().focus();
			} else {
				controls[ controlIndex - 1 ].contentContainer.find( ':focusable:first' ).focus();
			}
		},

		/**
		 * Updates the error notice.
		 *
		 * @since 4.9.0
		 *
		 * @param {Array} errorAnnotations The error annotations.
		 *
		 * @return {void}
		 */
		onUpdateErrorNotice: function onUpdateErrorNotice( errorAnnotations ) {
			var control = this, message;
			control.setting.notifications.remove( 'csslint_error' );

			if ( 0 !== errorAnnotations.length ) {
				if ( 1 === errorAnnotations.length ) {
					message = api.l10n.customCssError.singular.replace( '%d', '1' );
				} else {
					message = api.l10n.customCssError.plural.replace( '%d', String( errorAnnotations.length ) );
				}
				control.setting.notifications.add( new api.Notification( 'csslint_error', {
					message: message,
					type: 'error'
				} ) );
			}
		},

		/**
		 * Initializes the plain-textarea editor when syntax highlighting is disabled.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		initPlainTextareaEditor: function() {
			var control = this, $textarea = control.container.find( 'textarea' ), textarea = $textarea[0];

			$textarea.on( 'blur', function onBlur() {
				$textarea.data( 'next-tab-blurs', false );
			} );

			$textarea.on( 'keydown', function onKeydown( event ) {
				var selectionStart, selectionEnd, value, tabKeyCode = 9, escKeyCode = 27;

				if ( escKeyCode === event.keyCode ) {
					if ( ! $textarea.data( 'next-tab-blurs' ) ) {
						$textarea.data( 'next-tab-blurs', true );

						// Prevent collapsing the section.
						event.stopPropagation();
					}
					return;
				}

				// Short-circuit if tab key is not being pressed or if a modifier key is being pressed.
				if ( tabKeyCode !== event.keyCode || event.ctrlKey || event.altKey || event.shiftKey ) {
					return;
				}

				// Prevent capturing tab characters if escape was pressed.
				if ( $textarea.data( 'next-tab-blurs' ) ) {
					return;
				}

				selectionStart = textarea.selectionStart;
				selectionEnd = textarea.selectionEnd;
				value = textarea.value;

				if ( selectionStart >= 0 ) {
					textarea.value = value.substring( 0, selectionStart ).concat( '\t', value.substring( selectionEnd ) );
					$textarea.selectionStart = textarea.selectionEnd = selectionStart + 1;
				}

				event.stopPropagation();
				event.preventDefault();
			});

			control.deferred.codemirror.rejectWith( control );
		}
	});

	/**
	 * A control for date/time inputs.
	 *
	 * @since 4.9.0
	 *
	 * @constructs wp.customize.DateTimeControl
 	 * @memberOf   wp.customize
	 * @augments   wp.customize.Control
	 * @inheritDoc
	 */
	api.DateTimeControl = api.Control.extend(/** @lends wp.customize.DateTimeControl.prototype */{

		/**
		 * Initializes the control.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		ready: function ready() {
			var control = this;

			control.inputElements = {};
			control.invalidDate = false;

			_.bindAll( control, 'populateSetting', 'updateDaysForMonth', 'populateDateInputs' );

			if ( ! control.setting ) {
				throw new Error( 'Missing setting' );
			}

			control.container.find( '.date-input' ).each( function() {
				var input = $( this ), component, element;
				component = input.data( 'component' );
				element = new api.Element( input );
				control.inputElements[ component ] = element;
				control.elements.push( element );

				// Add invalid date error once user changes (and has blurred the input).
				input.on( 'change', function() {
					if ( control.invalidDate ) {
						control.notifications.add( new api.Notification( 'invalid_date', {
							message: api.l10n.invalidDate
						} ) );
					}
				} );

				// Remove the error immediately after validity change.
				input.on( 'input', _.debounce( function() {
					if ( ! control.invalidDate ) {
						control.notifications.remove( 'invalid_date' );
					}
				} ) );

				// Add zero-padding when blurring field.
				input.on( 'blur', _.debounce( function() {
					if ( ! control.invalidDate ) {
						control.populateDateInputs();
					}
				} ) );
			} );

			control.inputElements.month.bind( control.updateDaysForMonth );
			control.inputElements.year.bind( control.updateDaysForMonth );
			control.populateDateInputs();
			control.setting.bind( control.populateDateInputs );

			// Start populating setting after inputs have been populated.
			_.each( control.inputElements, function( element ) {
				element.bind( control.populateSetting );
			} );
		},

		/**
		 * Parses the passed datetime string.
		 *
		 * @since 4.9.0
		 *
		 * @param {string} datetime The date/time string. Accepts Y-m-d[ H:i[:s]] format.
		 *
		 * @return {Object|null} Returns an object containing date components or null if a parse error occurred.
		 */
		parseDateTime: function parseDateTime( datetime ) {
			var control = this, matches, date, midDayHour = 12;

			if ( datetime ) {
				matches = datetime.match( /^(\d\d\d\d)-(\d\d)-(\d\d)(?: (\d\d):(\d\d)(?::(\d\d))?)?$/ );
			}

			if ( ! matches ) {
				return null;
			}

			matches.shift();

			date = {
				year: matches.shift(),
				month: matches.shift(),
				day: matches.shift(),
				hour: matches.shift() || '00',
				minute: matches.shift() || '00',
				second: matches.shift() || '00'
			};

			if ( control.params.includeTime && control.params.twelveHourFormat ) {
				date.hour = parseInt( date.hour, 10 );
				date.meridian = date.hour >= midDayHour ? 'pm' : 'am';
				date.hour = date.hour % midDayHour ? String( date.hour % midDayHour ) : String( midDayHour );
				delete date.second; // @todo Why only if twelveHourFormat?
			}

			return date;
		},

		/**
		 * Validates if input components have a valid date and time.
		 *
		 * @since 4.9.0
		 *
		 * @return {boolean} Whether the date input fields contain an error.
		 */
		validateInputs: function validateInputs() {
			var control = this, components, validityInput;

			control.invalidDate = false;

			components = [ 'year', 'day' ];
			if ( control.params.includeTime ) {
				components.push( 'hour', 'minute' );
			}

			_.find( components, function( component ) {
				var element, max, min, value;

				element = control.inputElements[ component ];
				validityInput = element.element.get( 0 );
				max = parseInt( element.element.attr( 'max' ), 10 );
				min = parseInt( element.element.attr( 'min' ), 10 );
				value = parseInt( element(), 10 );
				control.invalidDate = isNaN( value ) || value > max || value < min;

				if ( ! control.invalidDate ) {
					validityInput.setCustomValidity( '' );
				}

				return control.invalidDate;
			} );

			if ( control.inputElements.meridian && ! control.invalidDate ) {
				validityInput = control.inputElements.meridian.element.get( 0 );
				if ( 'am' !== control.inputElements.meridian.get() && 'pm' !== control.inputElements.meridian.get() ) {
					control.invalidDate = true;
				} else {
					validityInput.setCustomValidity( '' );
				}
			}

			if ( control.invalidDate ) {
				validityInput.setCustomValidity( api.l10n.invalidValue );
			} else {
				validityInput.setCustomValidity( '' );
			}
			if ( ! control.section() || api.section.has( control.section() ) && api.section( control.section() ).expanded() ) {
				_.result( validityInput, 'reportValidity' );
			}

			return control.invalidDate;
		},

		/**
		 * Updates the number of days according to the selected month and year.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		updateDaysForMonth: function updateDaysForMonth() {
			var control = this, daysInMonth, year, month, day;

			month = parseInt( control.inputElements.month(), 10 );
			year = parseInt( control.inputElements.year(), 10 );
			day = parseInt( control.inputElements.day(), 10 );

			if ( month && year ) {
				daysInMonth = new Date( year, month, 0 ).getDate();
				control.inputElements.day.element.attr( 'max', daysInMonth );

				if ( day > daysInMonth ) {
					control.inputElements.day( String( daysInMonth ) );
				}
			}
		},

		/**
		 * Populates the setting value from the inputs.
		 *
		 * @since 4.9.0
		 *
		 * @return {boolean} Whether the setting was successfully updated.
		 */
		populateSetting: function populateSetting() {
			var control = this, date;

			if ( control.validateInputs() || ! control.params.allowPastDate && ! control.isFutureDate() ) {
				return false;
			}

			date = control.convertInputDateToString();
			control.setting.set( date );
			return true;
		},

		/**
		 * Converts the input values to a string in Y-m-d H:i:s format.
		 *
		 * @since 4.9.0
		 *
		 * @return {string} The date string.
		 */
		convertInputDateToString: function convertInputDateToString() {
			var control = this, date = '', dateFormat, hourInTwentyFourHourFormat,
				getElementValue, pad;

			/**
			 * Applies the passed padding to the passed number.
			 *
	 		 * @since 4.9.0
			 *
			 * @param {number} number   The number to add padding to.
			 * @param {number} padding  The amount of padding to add.
			 *
			 * @return {number} The padded number.
			 */
			pad = function( number, padding ) {
				var zeros;
				if ( String( number ).length < padding ) {
					zeros = padding - String( number ).length;
					number = Math.pow( 10, zeros ).toString().substr( 1 ) + String( number );
				}
				return number;
			};

			/**
			 * Gets the element's value.
			 *
			 * @since 4.9.0
			 *
			 * @param {string} component The component to retrieve.
			 *
			 * @return {number} The value of the component / element.
			 */
			getElementValue = function( component ) {
				var value = parseInt( control.inputElements[ component ].get(), 10 );

				if ( _.contains( [ 'month', 'day', 'hour', 'minute' ], component ) ) {
					value = pad( value, 2 );
				} else if ( 'year' === component ) {
					value = pad( value, 4 );
				}
				return value;
			};

			dateFormat = [ 'year', '-', 'month', '-', 'day' ];
			if ( control.params.includeTime ) {
				hourInTwentyFourHourFormat = control.inputElements.meridian ? control.convertHourToTwentyFourHourFormat( control.inputElements.hour(), control.inputElements.meridian() ) : control.inputElements.hour();
				dateFormat = dateFormat.concat( [ ' ', pad( hourInTwentyFourHourFormat, 2 ), ':', 'minute', ':', '00' ] );
			}

			_.each( dateFormat, function( component ) {
				date += control.inputElements[ component ] ? getElementValue( component ) : component;
			} );

			return date;
		},

		/**
		 * Checks if the date is in the future.
		 *
		 * @since 4.9.0
		 *
		 * @return {boolean} Whether or not the date is in the future.
		 */
		isFutureDate: function isFutureDate() {
			var control = this;
			return 0 < api.utils.getRemainingTime( control.convertInputDateToString() );
		},

		/**
		 * Converts hours that are in a 12-hour format, to a 24-hour format.
		 *
		 * @since 4.9.0
		 *
		 * @param {string} hourInTwelveHourFormat Hour in 12-hour format.
		 * @param {string} meridian               Either 'am' or 'pm'.
		 *
		 * @return {string} Hour in 24-hour format.
		 */
		convertHourToTwentyFourHourFormat: function convertHour( hourInTwelveHourFormat, meridian ) {
			var hourInTwentyFourHourFormat, hour, midDayHour = 12;

			hour = parseInt( hourInTwelveHourFormat, 10 );
			if ( isNaN( hour ) ) {
				return '';
			}

			if ( 'pm' === meridian && hour < midDayHour ) {
				hourInTwentyFourHourFormat = hour + midDayHour;
			} else if ( 'am' === meridian && midDayHour === hour ) {
				hourInTwentyFourHourFormat = hour - midDayHour;
			} else {
				hourInTwentyFourHourFormat = hour;
			}

			return String( hourInTwentyFourHourFormat );
		},

		/**
		 * Populates the date inputs in date fields.
		 *
		 * @since 4.9.0
		 *
		 * @return {boolean} Whether the inputs were populated.
		 */
		populateDateInputs: function populateDateInputs() {
			var control = this, parsed;

			parsed = control.parseDateTime( control.setting.get() );

			if ( ! parsed ) {
				return false;
			}

			_.each( control.inputElements, function( element, component ) {

				// This will be zero-padded string.
				var value = parsed[ component ];

				// Set month and meridian regardless of focused state since they are dropdowns.
				if ( 'month' === component || 'meridian' === component ) {

					// Options in dropdowns are not zero-padded.
					value = value.replace( /^0/, '' );

					element.set( value );
				} else {

					value = parseInt( value, 10 );
					if ( ! element.element.is( document.activeElement ) ) {

						// Populate element with zero-padded value if not focused.
						element.set( parsed[ component ] );
					} else if ( value !== parseInt( element(), 10 ) ) {

						// Forcibly update the value if its underlying value changed, regardless of zero-padding.
						element.set( String( value ) );
					}
				}
			} );

			return true;
		},

		/**
		 * Toggles the future date notification for date control.
		 *
		 * @since 4.9.0
		 *
		 * @param {boolean} notify Add or remove the notification.
		 *
		 * @return {wp.customize.DateTimeControl} The date control.
		 */
		toggleFutureDateNotification: function toggleFutureDateNotification( notify ) {
			var control = this, notificationCode, notification;

			notificationCode = 'not_future_date';

			if ( notify ) {
				notification = new api.Notification( notificationCode, {
					type: 'error',
					message: api.l10n.futureDateError
				} );
				control.notifications.add( notification );
			} else {
				control.notifications.remove( notificationCode );
			}

			return control;
		}
	});

	/**
	 * Class PreviewLinkControl.
	 *
	 * @since 4.9.0
	 *
	 * @constructs wp.customize.PreviewLinkControl
	 * @augments   wp.customize.Control
	 * @memberOf   wp.customize
	 * @inheritDoc
	 */
	api.PreviewLinkControl = api.Control.extend(/** @lends wp.customize.PreviewLinkControl.prototype */{

		defaults: _.extend( {}, api.Control.prototype.defaults, {
			templateId: 'customize-preview-link-control'
		} ),

		/**
		 * Initializes the control.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		ready: function ready() {
			var control = this, element, component, node, url, input, button;

			_.bindAll( control, 'updatePreviewLink' );

			if ( ! control.setting ) {
				control.setting = new api.Value();
			}

			control.previewElements = {};

			control.container.find( '.preview-control-element' ).each( function() {
				node = $( this );
				component = node.data( 'component' );
				element = new api.Element( node );
				control.previewElements[ component ] = element;
				control.elements.push( element );
			} );

			url = control.previewElements.url;
			input = control.previewElements.input;
			button = control.previewElements.button;

			input.link( control.setting );
			url.link( control.setting );

			url.bind( function( value ) {
				url.element.parent().attr( {
					href: value,
					target: api.settings.changeset.uuid
				} );
			} );

			api.bind( 'ready', control.updatePreviewLink );
			api.state( 'saved' ).bind( control.updatePreviewLink );
			api.state( 'changesetStatus' ).bind( control.updatePreviewLink );
			api.state( 'activated' ).bind( control.updatePreviewLink );
			api.previewer.previewUrl.bind( control.updatePreviewLink );

			button.element.on( 'click', function( event ) {
				event.preventDefault();
				if ( control.setting() ) {
					input.element.select();
					document.execCommand( 'copy' );
					button( button.element.data( 'copied-text' ) );
				}
			} );

			url.element.parent().on( 'click', function( event ) {
				if ( $( this ).hasClass( 'disabled' ) ) {
					event.preventDefault();
				}
			} );

			button.element.on( 'mouseenter', function() {
				if ( control.setting() ) {
					button( button.element.data( 'copy-text' ) );
				}
			} );
		},

		/**
		 * Updates the Preview Link control.
		 *
		 * @since 4.9.0
		 *
		 * @return {void}
		 */
		updatePreviewLink: function updatePreviewLink() {
			var control = this, unsavedDirtyValues;

			unsavedDirtyValues = ! api.state( 'saved' ).get() || '' === api.state( 'changesetStatus' ).get() || 'auto-draft' === api.state( 'changesetStatus' ).get();

			control.toggleSaveNotification( unsavedDirtyValues );
			control.previewElements.url.element.parent().toggleClass( 'disabled', unsavedDirtyValues );
			control.previewElements.button.element.prop( 'disabled', unsavedDirtyValues );
			control.setting.set( api.previewer.getFrontendPreviewUrl() );
		},

		/**
		 * Toggles the save notification.
		 *
		 * @since 4.9.0
		 *
		 * @param {boolean} notify Whether to add or remove notification.
		 *
		 * @return {void}
		 */
		toggleSaveNotification: function toggleSaveNotification( notify ) {
			var control = this, notificationCode, notification;

			notificationCode = 'changes_not_saved';

			if ( notify ) {
				notification = new api.Notification( notificationCode, {
					type: 'info',
					message: api.l10n.saveBeforeShare
				} );
				control.notifications.add( notification );
			} else {
				control.notifications.remove( notificationCode );
			}
		}
	});

	/**
	 * Changes objects contained within the main customize object to Settings.
	 *
	 * @alias wp.customize.defaultConstructor
	 */
	api.defaultConstructor = api.Setting;

	/**
	 * Callback for resolved controls.
	 *
	 * @callback wp.customize.deferredControlsCallback
	 * @param {wp.customize.Control[]} controls Resolved controls.
	 */

	/**
	 * Collection of all registered controls.
	 *
	 * @alias wp.customize.control
	 *
	 * @since 3.4.0
	 *
	 * @type {Function}
	 *
	 * @param {...string}                ids        One or more ids for controls to obtain.
	 * @param {deferredControlsCallback} [callback] Function called when all supplied controls exist.
	 *
	 * @return {wp.customize.Control|undefined|jQuery.promise} Control instance or undefined (if function called with one id param), or promise resolving to requested controls.
	 *
	 * @example <caption>Loop over all registered controls.</caption>
	 * wp.customize.control.each( function( control ) { ... } );
	 *
	 * @example <caption>Getting `background_color` control instance.</caption>
	 * control = wp.customize.control( 'background_color' );
	 *
	 * @example <caption>Check if control exists.</caption>
	 * hasControl = wp.customize.control.has( 'background_color' );
	 *
	 * @example <caption>Deferred getting of `background_color` control until it exists, using callback.</caption>
	 * wp.customize.control( 'background_color', function( control ) { ... } );
	 *
	 * @example <caption>Get title and tagline controls when they both exist, using promise (only available when multiple IDs are present).</caption>
	 * promise = wp.customize.control( 'blogname', 'blogdescription' );
	 * promise.done( function( titleControl, taglineControl ) { ... } );
	 *
	 * @example <caption>Get title and tagline controls when they both exist, using callback.</caption>
	 * wp.customize.control( 'blogname', 'blogdescription', function( titleControl, taglineControl ) { ... } );
	 *
	 * @example <caption>Getting setting value for `background_color` control.</caption>
	 * value = wp.customize.control( 'background_color ').setting.get();
	 * value = wp.customize( 'background_color' ).get(); // Same as above, since setting ID and control ID are the same.
	 *
	 * @example <caption>Add new control for site title.</caption>
	 * wp.customize.control.add( new wp.customize.Control( 'other_blogname', {
	 *     setting: 'blogname',
	 *     type: 'text',
	 *     label: 'Site title',
	 *     section: 'other_site_identify'
	 * } ) );
	 *
	 * @example <caption>Remove control.</caption>
	 * wp.customize.control.remove( 'other_blogname' );
	 *
	 * @example <caption>Listen for control being added.</caption>
	 * wp.customize.control.bind( 'add', function( addedControl ) { ... } )
	 *
	 * @example <caption>Listen for control being removed.</caption>
	 * wp.customize.control.bind( 'removed', function( removedControl ) { ... } )
	 */
	api.control = new api.Values({ defaultConstructor: api.Control });

	/**
	 * Callback for resolved sections.
	 *
	 * @callback wp.customize.deferredSectionsCallback
	 * @param {wp.customize.Section[]} sections Resolved sections.
	 */

	/**
	 * Collection of all registered sections.
	 *
	 * @alias wp.customize.section
	 *
	 * @since 3.4.0
	 *
	 * @type {Function}
	 *
	 * @param {...string}                ids        One or more ids for sections to obtain.
	 * @param {deferredSectionsCallback} [callback] Function called when all supplied sections exist.
	 *
	 * @return {wp.customize.Section|undefined|jQuery.promise} Section instance or undefined (if function called with one id param), or promise resolving to requested sections.
	 *
	 * @example <caption>Loop over all registered sections.</caption>
	 * wp.customize.section.each( function( section ) { ... } )
	 *
	 * @example <caption>Getting `title_tagline` section instance.</caption>
	 * section = wp.customize.section( 'title_tagline' )
	 *
	 * @example <caption>Expand dynamically-created section when it exists.</caption>
	 * wp.customize.section( 'dynamically_created', function( section ) {
	 *     section.expand();
	 * } );
	 *
	 * @see {@link wp.customize.control} for further examples of how to interact with {@link wp.customize.Values} instances.
	 */
	api.section = new api.Values({ defaultConstructor: api.Section });

	/**
	 * Callback for resolved panels.
	 *
	 * @callback wp.customize.deferredPanelsCallback
	 * @param {wp.customize.Panel[]} panels Resolved panels.
	 */

	/**
	 * Collection of all registered panels.
	 *
	 * @alias wp.customize.panel
	 *
	 * @since 4.0.0
	 *
	 * @type {Function}
	 *
	 * @param {...string}              ids        One or more ids for panels to obtain.
	 * @param {deferredPanelsCallback} [callback] Function called when all supplied panels exist.
	 *
	 * @return {wp.customize.Panel|undefined|jQuery.promise} Panel instance or undefined (if function called with one id param), or promise resolving to requested panels.
	 *
	 * @example <caption>Loop over all registered panels.</caption>
	 * wp.customize.panel.each( function( panel ) { ... } )
	 *
	 * @example <caption>Getting nav_menus panel instance.</caption>
	 * panel = wp.customize.panel( 'nav_menus' );
	 *
	 * @example <caption>Expand dynamically-created panel when it exists.</caption>
	 * wp.customize.panel( 'dynamically_created', function( panel ) {
	 *     panel.expand();
	 * } );
	 *
	 * @see {@link wp.customize.control} for further examples of how to interact with {@link wp.customize.Values} instances.
	 */
	api.panel = new api.Values({ defaultConstructor: api.Panel });

	/**
	 * Callback for resolved notifications.
	 *
	 * @callback wp.customize.deferredNotificationsCallback
	 * @param {wp.customize.Notification[]} notifications Resolved notifications.
	 */

	/**
	 * Collection of all global notifications.
	 *
	 * @alias wp.customize.notifications
	 *
	 * @since 4.9.0
	 *
	 * @type {Function}
	 *
	 * @param {...string}                     codes      One or more codes for notifications to obtain.
	 * @param {deferredNotificationsCallback} [callback] Function called when all supplied notifications exist.
	 *
	 * @return {wp.customize.Notification|undefined|jQuery.promise} notification instance or undefined (if function called with one code param), or promise resolving to requested notifications.
	 *
	 * @example <caption>Check if existing notification</caption>
	 * exists = wp.customize.notifications.has( 'a_new_day_arrived' );
	 *
	 * @example <caption>Obtain existing notification</caption>
	 * notification = wp.customize.notifications( 'a_new_day_arrived' );
	 *
	 * @example <caption>Obtain notification that may not exist yet.</caption>
	 * wp.customize.notifications( 'a_new_day_arrived', function( notification ) { ... } );
	 *
	 * @example <caption>Add a warning notification.</caption>
	 * wp.customize.notifications.add( new wp.customize.Notification( 'midnight_almost_here', {
	 *     type: 'warning',
	 *     message: 'Midnight has almost arrived!',
	 *     dismissible: true
	 * } ) );
	 *
	 * @example <caption>Remove a notification.</caption>
	 * wp.customize.notifications.remove( 'a_new_day_arrived' );
	 *
	 * @see {@link wp.customize.control} for further examples of how to interact with {@link wp.customize.Values} instances.
	 */
	api.notifications = new api.Notifications();

	/**
	 * The PreviewFrame object.
	 */
	api.PreviewFrame = api.Messenger.extend(/** @lends wp.customize.PreviewFrame.prototype */{
		// Will get set to api.settings.timeouts.previewFrameSensitivity.
		sensitivity: null,

		/**
		 * An object that fetches a preview in the background of the document, which
		 * allows for seamless replacement of an existing preview frame.
		 *
		 * @constructs wp.customize.PreviewFrame
		 * @augments   wp.customize.Messenger
		 * @memberOf   wp.customize
		 *
		 * @since 3.5.0
		 *
		 * @param {Object}   params.container  The container element to load the preview frame into.
		 * @param {Function} params.previewUrl Callback function to get the preview URL.
		 * @param {Object}   params.query      The query.
		 * @param {Object}   options           The options to use within the preview frame.
		 */
		initialize: function( params, options ) {
			var deferred = $.Deferred();

			// Make the instance of the PreviewFrame the promise object so other objects can easily interact with it.
			deferred.promise( this );

			this.container = params.container;

			$.extend( params, { channel: api.PreviewFrame.uuid() });

			api.Messenger.prototype.initialize.call( this, params, options );

			this.add( 'previewUrl', params.previewUrl );

			this.query = $.extend( params.query || {}, { customize_messenger_channel: this.channel() });

			this.run( deferred );
		},

		/**
		 * Runs the preview request.
		 *
		 * @since 3.5.0
		 *
		 * @param {Object} deferred jQuery Deferred object to be resolved with the request.
		 *
		 * @return {void}
		 */
		run: function( deferred ) {
			var previewFrame = this,
				loaded = false,
				ready = false,
				readyData = null,
				hasPendingChangesetUpdate = '{}' !== previewFrame.query.customized,
				urlParser,
				params,
				form;

			if ( previewFrame._ready ) {
				previewFrame.unbind( 'ready', previewFrame._ready );
			}

			previewFrame._ready = function( data ) {
				ready = true;
				readyData = data;
				previewFrame.container.addClass( 'iframe-ready' );
				if ( ! data ) {
					return;
				}

				if ( loaded ) {
					deferred.resolveWith( previewFrame, [ data ] );
				}
			};

			previewFrame.bind( 'ready', previewFrame._ready );

			urlParser = document.createElement( 'a' );
			urlParser.href = previewFrame.previewUrl();

			params = _.extend(
				api.utils.parseQueryString( urlParser.search.substr( 1 ) ),
				{
					customize_changeset_uuid: previewFrame.query.customize_changeset_uuid,
					customize_theme: previewFrame.query.customize_theme,
					customize_messenger_channel: previewFrame.query.customize_messenger_channel
				}
			);
			if ( api.settings.changeset.autosaved || ! api.state( 'saved' ).get() ) {
				params.customize_autosaved = 'on';
			}

			urlParser.search = $.param( params );
			previewFrame.iframe = $( '<iframe />', {
				title: api.l10n.previewIframeTitle,
				name: 'customize-' + previewFrame.channel()
			} );

			// Workaround for Safari bug. See WP Trac #38149.
			previewFrame.iframe.attr( 'onmousewheel', '' );

			if ( ! hasPendingChangesetUpdate ) {
				previewFrame.iframe.attr( 'src', urlParser.href );
			} else {
				// For debugging purposes.
				previewFrame.iframe.attr( 'data-src', urlParser.href );
			}

			previewFrame.iframe.appendTo( previewFrame.container );
			previewFrame.targetWindow( previewFrame.iframe[0].contentWindow );

			/*
			 * Submit customized data in a POST request to the preview frame window, since
			 * there are changes in the settings values that are not yet written to the changeset.
			 */
			if ( hasPendingChangesetUpdate ) {
				form = $( '<form>', {
					action: urlParser.href,
					target: previewFrame.iframe.attr( 'name' ),
					method: 'post',
					hidden: 'hidden'
				} );
				form.append( $( '<input>', {
					type: 'hidden',
					name: '_method',
					value: 'GET'
				} ) );
				_.each( previewFrame.query, function( value, key ) {
					form.append( $( '<input>', {
						type: 'hidden',
						name: key,
						value: value
					} ) );
				} );
				previewFrame.container.append( form );
				form.submit();
				// No need to keep the form around after submission.
				form.remove();
			}

			previewFrame.bind( 'iframe-loading-error', function( error ) {
				previewFrame.iframe.remove();

				// Check if the user is not logged in.
				if ( 0 === error ) {
					previewFrame.login( deferred );
					return;
				}

				// Check for cheaters.
				if ( -1 === error ) {
					deferred.rejectWith( previewFrame, [ 'cheatin' ] );
					return;
				}

				deferred.rejectWith( previewFrame, [ 'request failure' ] );
			} );

			previewFrame.iframe.one( 'load', function() {
				loaded = true;

				if ( ready ) {
					deferred.resolveWith( previewFrame, [ readyData ] );
				} else {
					setTimeout( function() {
						deferred.rejectWith( previewFrame, [ 'ready timeout' ] );
					}, previewFrame.sensitivity );
				}
			});
		},

		/**
		 * Runs the preview request.
		 *
		 * @since 3.5.0
		 *
		 * @param {Object} deferred jQuery Deferred object to be resolved with the request.
		 *
		 * @return {void}
		 */
		login: function( deferred ) {
			var self = this,
				reject;

			reject = function() {
				deferred.rejectWith( self, [ 'logged out' ] );
			};

			if ( this.triedLogin ) {
				return reject();
			}

			// Check if we have an admin cookie.
			$.get( api.settings.url.ajax, {
				action: 'logged-in'
			}).fail( reject ).done( function( response ) {
				var iframe;

				if ( '1' !== response ) {
					reject();
				}

				iframe = $( '<iframe />', { 'src': self.previewUrl(), 'title': api.l10n.previewIframeTitle } ).hide();
				iframe.appendTo( self.container );
				iframe.on( 'load', function() {
					self.triedLogin = true;

					iframe.remove();
					self.run( deferred );
				});
			});
		},

		/**
		 * Destroys the Preview Frame.
		 *
		 * @since 3.5.0
		 *
		 * @return {void}
		 */
		destroy: function() {
			api.Messenger.prototype.destroy.call( this );

			if ( this.iframe ) {
				this.iframe.remove();
			}

			delete this.iframe;
			delete this.targetWindow;
		}
	});

	(function(){
		var id = 0;
		/**
		 * Returns an incremented ID for a preview messenger channel.
		 *
		 * This function is named "uuid" for historical reasons, but it is a
		 * misnomer as it is not an actual UUID, and it is not universally unique.
		 * This is not to be confused with `api.settings.changeset.uuid`.
		 *
 		 * @since 3.5.0
		 *
		 * @return {string} The UUID.
		 */
		api.PreviewFrame.uuid = function() {
			return 'preview-' + String( id++ );
		};
	}());

	/**
	 * Sets the document title of the customizer.
	 *
	 * @alias wp.customize.setDocumentTitle
	 *
	 * @since 4.1.0
	 *
	 * @param {string} The document title to set.
	 *
	 * @return {void}
	 */
	api.setDocumentTitle = function ( documentTitle ) {
		var tmpl, title;
		tmpl = api.settings.documentTitleTmpl;
		title = tmpl.replace( '%s', documentTitle );
		document.title = title;
		api.trigger( 'title', title );
	};

	/**
	 * The Previewer object.
	 */
	api.Previewer = api.Messenger.extend(/** @lends wp.customize.Previewer.prototype */{
		// Will get set to api.settings.timeouts.windowRefresh.
		refreshBuffer: null,

		/**
		 * The Previewer constructor.
		 *
		 * @since 3.5.0
		 *
		 * @constructs wp.customize.Previewer
		 * @augments   wp.customize.Messenger
		 * @memberOf   wp.customize
		 *
		 * @param {array}  params.allowedUrls An array of allowed URLs.
		 * @param {string} params.container   The container element to load the preview frame into.
		 * @param {string} params.previewUrl  The URL to preview.
		 * @param {Object} options            The options to use within the previewer.
		 */
		initialize: function( params, options ) {
			var previewer = this,
				urlParser = document.createElement( 'a' );

			$.extend( previewer, options || {} );
			previewer.deferred = {
				active: $.Deferred()
			};

			// Debounce to prevent hammering server and then wait for any pending update requests.
			previewer.refresh = _.debounce(
				( function( originalRefresh ) {
					return function() {
						var isProcessingComplete, refreshOnceProcessingComplete;
						isProcessingComplete = function() {
							return 0 === api.state( 'processing' ).get();
						};
						if ( isProcessingComplete() ) {
							originalRefresh.call( previewer );
						} else {
							refreshOnceProcessingComplete = function() {
								if ( isProcessingComplete() ) {
									originalRefresh.call( previewer );
									api.state( 'processing' ).unbind( refreshOnceProcessingComplete );
								}
							};
							api.state( 'processing' ).bind( refreshOnceProcessingComplete );
						}
					};
				}( previewer.refresh ) ),
				previewer.refreshBuffer
			);

			previewer.container   = api.ensure( params.container );
			previewer.allowedUrls = params.allowedUrls;

			params.url = window.location.href;

			api.Messenger.prototype.initialize.call( previewer, params );

			urlParser.href = previewer.origin();
			previewer.add( 'scheme', urlParser.protocol.replace( /:$/, '' ) );

			/*
             * Limit the URL to internal, front-end links.
			 *
			 * If the front end and the admin are served from the same domain, load the
			 * preview over ssl if the Customizer is being loaded over ssl. This avoids
			 * insecure content warnings. This is not attempted if the admin and front end
			 * are on different domains to avoid the case where the front end doesn't have ssl certs.
			 */

			previewer.add( 'previewUrl', params.previewUrl ).setter( function( to ) {
				var result = null, urlParser, queryParams, parsedAllowedUrl, parsedCandidateUrls = [];
				urlParser = document.createElement( 'a' );
				urlParser.href = to;

				// Abort if URL is for admin or (static) files in wp-includes or wp-content.
				if ( /\/wp-(admin|includes|content)(\/|$)/.test( urlParser.pathname ) ) {
					return null;
				}

				// Remove state query params.
				if ( urlParser.search.length > 1 ) {
					queryParams = api.utils.parseQueryString( urlParser.search.substr( 1 ) );
					delete queryParams.customize_changeset_uuid;
					delete queryParams.customize_theme;
					delete queryParams.customize_messenger_channel;
					delete queryParams.customize_autosaved;
					if ( _.isEmpty( queryParams ) ) {
						urlParser.search = '';
					} else {
						urlParser.search = $.param( queryParams );
					}
				}

				parsedCandidateUrls.push( urlParser );

				// Prepend list with URL that matches the scheme/protocol of the iframe.
				if ( previewer.scheme.get() + ':' !== urlParser.protocol ) {
					urlParser = document.createElement( 'a' );
					urlParser.href = parsedCandidateUrls[0].href;
					urlParser.protocol = previewer.scheme.get() + ':';
					parsedCandidateUrls.unshift( urlParser );
				}

				// Attempt to match the URL to the control frame's scheme and check if it's allowed. If not, try the original URL.
				parsedAllowedUrl = document.createElement( 'a' );
				_.find( parsedCandidateUrls, function( parsedCandidateUrl ) {
					return ! _.isUndefined( _.find( previewer.allowedUrls, function( allowedUrl ) {
						parsedAllowedUrl.href = allowedUrl;
						if ( urlParser.protocol === parsedAllowedUrl.protocol && urlParser.host === parsedAllowedUrl.host && 0 === urlParser.pathname.indexOf( parsedAllowedUrl.pathname.replace( /\/$/, '' ) ) ) {
							result = parsedCandidateUrl.href;
							return true;
						}
					} ) );
				} );

				return result;
			});

			previewer.bind( 'ready', previewer.ready );

			// Start listening for keep-alive messages when iframe first loads.
			previewer.deferred.active.done( _.bind( previewer.keepPreviewAlive, previewer ) );

			previewer.bind( 'synced', function() {
				previewer.send( 'active' );
			} );

			// Refresh the preview when the URL is changed (but not yet).
			previewer.previewUrl.bind( previewer.refresh );

			previewer.scroll = 0;
			previewer.bind( 'scroll', function( distance ) {
				previewer.scroll = distance;
			});

			// Update the URL when the iframe sends a URL message, resetting scroll position. If URL is unchanged, then refresh.
			previewer.bind( 'url', function( url ) {
				var onUrlChange, urlChanged = false;
				previewer.scroll = 0;
				onUrlChange = function() {
					urlChanged = true;
				};
				previewer.previewUrl.bind( onUrlChange );
				previewer.previewUrl.set( url );
				previewer.previewUrl.unbind( onUrlChange );
				if ( ! urlChanged ) {
					previewer.refresh();
				}
			} );

			// Update the document title when the preview changes.
			previewer.bind( 'documentTitle', function ( title ) {
				api.setDocumentTitle( title );
			} );
		},

		/**
		 * Handles the preview receiving the ready message.
		 *
		 * @since 4.7.0
		 *
		 * @param {Object} data                Data from the preview.
		 * @param {string} data.currentUrl     The current URL.
		 * @param {Object} data.activePanels   Object containing the active panels.
		 * @param {Object} data.activeSections Object containing the active sections.
		 * @param {Object} data.activeControls Object containing the active controls.
		 *
		 * @return {void}
		 */
		ready: function( data ) {
			var previewer = this, synced = {}, constructs;

			synced.settings = api.get();
			synced['settings-modified-while-loading'] = previewer.settingsModifiedWhileLoading;
			if ( 'resolved' !== previewer.deferred.active.state() || previewer.loading ) {
				synced.scroll = previewer.scroll;
			}
			synced['edit-shortcut-visibility'] = api.state( 'editShortcutVisibility' ).get();
			previewer.send( 'sync', synced );

			// Set the previewUrl without causing the URL to set the iframe.
			if ( data.currentUrl ) {
				previewer.previewUrl.unbind( previewer.refresh );
				previewer.previewUrl.set( data.currentUrl );
				previewer.previewUrl.bind( previewer.refresh );
			}

			/*
			 * Walk over all panels, sections and controls and set their
			 * respective active states to true if the preview explicitly indicates as such.
			 */
			constructs = {
				panel: data.activePanels,
				section: data.activeSections,
				control: data.activeControls
			};
			_( constructs ).each( function ( activeConstructs, type ) {
				api[ type ].each( function ( construct, id ) {
					var isDynamicallyCreated = _.isUndefined( api.settings[ type + 's' ][ id ] );

					/*
					 * If the construct was created statically in PHP (not dynamically in JS)
					 * then consider a missing (undefined) value in the activeConstructs to
					 * mean it should be deactivated (since it is gone). But if it is
					 * dynamically created then only toggle activation if the value is defined,
					 * as this means that the construct was also then correspondingly
					 * created statically in PHP and the active callback is available.
					 * Otherwise, dynamically-created constructs should normally have
					 * their active states toggled in JS rather than from PHP.
					 */
					if ( ! isDynamicallyCreated || ! _.isUndefined( activeConstructs[ id ] ) ) {
						if ( activeConstructs[ id ] ) {
							construct.activate();
						} else {
							construct.deactivate();
						}
					}
				} );
			} );

			if ( data.settingValidities ) {
				api._handleSettingValidities( {
					settingValidities: data.settingValidities,
					focusInvalidControl: false
				} );
			}
		},

		/**
		 * Keeps the preview alive by listening for ready and keep-alive messages.
		 *
		 * If a message is not received in the allotted time then the iframe will be set back to the last known valid URL.
		 *
		 * @since 4.7.0
		 *
		 * @return {void}
		 */
		keepPreviewAlive: function keepPreviewAlive() {
			var previewer = this, keepAliveTick, timeoutId, handleMissingKeepAlive, scheduleKeepAliveCheck;

			/**
			 * Schedules a preview keep-alive check.
			 *
			 * Note that if a page load takes longer than keepAliveCheck milliseconds,
			 * the keep-alive messages will still be getting sent from the previous
			 * URL.
			 *
			 * @since 4.7.0
			 *
			 * @return {void}
			 */
			scheduleKeepAliveCheck = function() {
				timeoutId = setTimeout( handleMissingKeepAlive, api.settings.timeouts.keepAliveCheck );
			};

			/**
			 * Sets the previewerAlive state to true when receiving a message from the preview.
			 *
			 * @since 4.7.0
			 *
			 * @return {void}
			 */
			keepAliveTick = function() {
				api.state( 'previewerAlive' ).set( true );
				clearTimeout( timeoutId );
				scheduleKeepAliveCheck();
			};

			/**
			 * Set the previewerAlive state to false if keepAliveCheck milliseconds have transpired without a message.
			 *
			 * This is most likely to happen in the case of a connectivity error, or if the theme causes the browser
			 * to navigate to a non-allowed URL. Setting this state to false will force settings with a postMessage
			 * transport to use refresh instead, causing the preview frame also to be replaced with the current
			 * allowed preview URL.
			 *
			 * @since 4.7.0
			 *
			 * @return {void}
			 */
			handleMissingKeepAlive = function() {
				api.state( 'previewerAlive' ).set( false );
			};
			scheduleKeepAliveCheck();

			previewer.bind( 'ready', keepAliveTick );
			previewer.bind( 'keep-alive', keepAliveTick );
		},

		/**
		 * Query string data sent with each preview request.
		 *
		 * @since 3.5.0
		 *
		 * @abstract
		 */
		query: function() {},

		/**
		 * Aborts the loading of the control.
		 *
 		 * @since 3.5.0
		 *
		 * @return {void}
		 */
		abort: function() {
			if ( this.loading ) {
				this.loading.destroy();
				delete this.loading;
			}
		},

		/**
		 * Refreshes the preview seamlessly.
		 *
		 * @since 3.4.0
		 *
		 * @return {void}
		 */
		refresh: function() {
			var previewer = this, onSettingChange;

			// Display loading indicator.
			previewer.send( 'loading-initiated' );

			previewer.abort();

			previewer.loading = new api.PreviewFrame({
				url:        previewer.url(),
				previewUrl: previewer.previewUrl(),
				query:      previewer.query( { excludeCustomizedSaved: true } ) || {},
				container:  previewer.container
			});

			previewer.settingsModifiedWhileLoading = {};
			onSettingChange = function( setting ) {
				previewer.settingsModifiedWhileLoading[ setting.id ] = true;
			};
			api.bind( 'change', onSettingChange );
			previewer.loading.always( function() {
				api.unbind( 'change', onSettingChange );
			} );

			previewer.loading.done( function( readyData ) {
				var loadingFrame = this, onceSynced;

				previewer.preview = loadingFrame;
				previewer.targetWindow( loadingFrame.targetWindow() );
				previewer.channel( loadingFrame.channel() );

				onceSynced = function() {
					loadingFrame.unbind( 'synced', onceSynced );
					if ( previewer._previousPreview ) {
						previewer._previousPreview.destroy();
					}
					previewer._previousPreview = previewer.preview;
					previewer.deferred.active.resolve();
					delete previewer.loading;
				};
				loadingFrame.bind( 'synced', onceSynced );

				// This event will be received directly by the previewer in normal navigation; this is only needed for seamless refresh.
				previewer.trigger( 'ready', readyData );
			});

			previewer.loading.fail( function( reason ) {
				previewer.send( 'loading-failed' );

				if ( 'logged out' === reason ) {
					if ( previewer.preview ) {
						previewer.preview.destroy();
						delete previewer.preview;
					}

					previewer.login().done( previewer.refresh );
				}

				if ( 'cheatin' === reason ) {
					previewer.cheatin();
				}
			});
		},

		/**
		 * Adds a login and a response handler for the login process..
		 *
		 * @since 3.5.0
		 *
		 * @return {Object} The jQuery Deferred object.
		 */
		login: function() {
			var previewer = this,
				deferred, messenger, iframe;

			if ( this._login ) {
				return this._login;
			}

			deferred = $.Deferred();
			this._login = deferred.promise();

			messenger = new api.Messenger({
				channel: 'login',
				url:     api.settings.url.login
			});

			iframe = $( '<iframe />', { 'src': api.settings.url.login, 'title': api.l10n.loginIframeTitle } ).appendTo( this.container );

			messenger.targetWindow( iframe[0].contentWindow );

			messenger.bind( 'login', function () {
				var refreshNonces = previewer.refreshNonces();

				refreshNonces.always( function() {
					iframe.remove();
					messenger.destroy();
					delete previewer._login;
				});

				refreshNonces.done( function() {
					deferred.resolve();
				});

				refreshNonces.fail( function() {
					previewer.cheatin();
					deferred.reject();
				});
			});

			return this._login;
		},

		/**
		 * Adds a message to the body when someone is cheating.
		 *
		 * @since 3.5.0
		 *
		 * @return {void}
		 */
		cheatin: function() {
			$( document.body ).empty().addClass( 'cheatin' ).append(
				'<h1>' + api.l10n.notAllowedHeading + '</h1>' +
				'<p>' + api.l10n.notAllowed + '</p>'
			);
		},

		/**
		 * Refreshes the nonces.
		 *
		 * @since 4.2.0
		 *
		 * @return {Object} The jQuery Deferred object.
		 */
		refreshNonces: function() {
			var request, deferred = $.Deferred();

			deferred.promise();

			request = wp.ajax.post( 'customize_refresh_nonces', {
				wp_customize: 'on',
				customize_theme: api.settings.theme.stylesheet
			});

			request.done( function( response ) {
				api.trigger( 'nonce-refresh', response );
				deferred.resolve();
			});

			request.fail( function() {
				deferred.reject();
			});

			return deferred;
		}
	});

	api.settingConstructor = {};
	api.controlConstructor = {
		color:               api.ColorControl,
		media:               api.MediaControl,
		upload:              api.UploadControl,
		image:               api.ImageControl,
		cropped_image:       api.CroppedImageControl,
		site_icon:           api.SiteIconControl,
		header:              api.HeaderControl,
		background:          api.BackgroundControl,
		background_position: api.BackgroundPositionControl,
		theme:               api.ThemeControl,
		date_time:           api.DateTimeControl,
		code_editor:         api.CodeEditorControl
	};
	api.panelConstructor = {
		themes: api.ThemesPanel
	};
	api.sectionConstructor = {
		themes: api.ThemesSection,
		outer: api.OuterSection
	};

	/**
	 * Handles setting_validities in an error response for the customize-save request.
	 *
	 * Add notifications to the settings and focus on the first control that has an invalid setting.
	 *
	 * @alias wp.customize._handleSettingValidities
	 *
	 * @since 4.6.0
	 *
	 * @access private
	 *
	 * @param {Object}  args                             The arguments object.
	 * @param {Object}  args.settingValidities           The validation settings that need to be checked.
	 * @param {boolean} [args.focusInvalidControl=false] Whether or not to focus on the first invalid control.
	 *
	 * @return {void}
	 */
	api._handleSettingValidities = function handleSettingValidities( args ) {
		var invalidSettingControls, invalidSettings = [], wasFocused = false;

		// Find the controls that correspond to each invalid setting.
		_.each( args.settingValidities, function( validity, settingId ) {
			var setting = api( settingId );
			if ( setting ) {

				// Add notifications for invalidities.
				if ( _.isObject( validity ) ) {
					_.each( validity, function( params, code ) {
						var notification, existingNotification, needsReplacement = false;
						notification = new api.Notification( code, _.extend( { fromServer: true }, params ) );

						// Remove existing notification if already exists for code but differs in parameters.
						existingNotification = setting.notifications( notification.code );
						if ( existingNotification ) {
							needsReplacement = notification.type !== existingNotification.type || notification.message !== existingNotification.message || ! _.isEqual( notification.data, existingNotification.data );
						}
						if ( needsReplacement ) {
							setting.notifications.remove( code );
						}

						if ( ! setting.notifications.has( notification.code ) ) {
							setting.notifications.add( notification );
						}
						invalidSettings.push( setting.id );
					} );
				}

				// Remove notification errors that are no longer valid.
				setting.notifications.each( function( notification ) {
					if ( notification.fromServer && 'error' === notification.type && ( true === validity || ! validity[ notification.code ] ) ) {
						setting.notifications.remove( notification.code );
					}
				} );
			}
		} );

		if ( args.focusInvalidControl ) {
			invalidSettingControls = api.findControlsForSettings( invalidSettings );

			// Focus on the first control that is inside of an expanded section (one that is visible).
			_( _.values( invalidSettingControls ) ).find( function( controls ) {
				return _( controls ).find( function( control ) {
					var isExpanded = control.section() && api.section.has( control.section() ) && api.section( control.section() ).expanded();
					if ( isExpanded && control.expanded ) {
						isExpanded = control.expanded();
					}
					if ( isExpanded ) {
						control.focus();
						wasFocused = true;
					}
					return wasFocused;
				} );
			} );

			// Focus on the first invalid control.
			if ( ! wasFocused && ! _.isEmpty( invalidSettingControls ) ) {
				_.values( invalidSettingControls )[0][0].focus();
			}
		}
	};

	/**
	 * Finds all controls associated with the given settings.
	 *
	 * @alias wp.customize.findControlsForSettings
	 *
	 * @since 4.6.0
	 *
	 * @param {string} [settingIds] The setting IDs.
	 *
	 * @return {Object} Mappings of the setting ids and arrays of controls.
	 */
	api.findControlsForSettings = function findControlsForSettings( settingIds ) {
		var controls = {}, settingControls;
		_.each( _.unique( settingIds ), function( settingId ) {
			var setting = api( settingId );
			if ( setting ) {
				settingControls = setting.findControls();
				if ( settingControls && settingControls.length > 0 ) {
					controls[ settingId ] = settingControls;
				}
			}
		} );
		return controls;
	};

	/**
	 * Sorts panels, sections and controls by priorities. Hides empty sections and panels.
	 *
	 * @alias wp.customize.reflowPaneContents
	 *
	 * @since 4.1.0
	 *
	 * @return {void}
	 */
	api.reflowPaneContents = _.bind( function () {

		var appendContainer, activeElement, rootHeadContainers, rootNodes = [], wasReflowed = false;

		if ( document.activeElement ) {
			activeElement = $( document.activeElement );
		}

		// Sort the sections within each panel.
		api.panel.each( function ( panel ) {
			if ( 'themes' === panel.id ) {
				// Don't reflow theme sections, as doing so moves them after the themes container.
				return;
			}

			var sections = panel.sections(),
				sectionHeadContainers = _.pluck( sections, 'headContainer' );
			rootNodes.push( panel );
			appendContainer = ( panel.contentContainer.is( 'ul' ) ) ? panel.contentContainer : panel.contentContainer.find( 'ul:first' );
			if ( ! api.utils.areElementListsEqual( sectionHeadContainers, appendContainer.children( '[id]' ) ) ) {
				_( sections ).each( function ( section ) {
					appendContainer.append( section.headContainer );
				} );
				wasReflowed = true;
			}
		} );

		// Sort the controls within each section.
		api.section.each( function ( section ) {
			var controls = section.controls(),
				controlContainers = _.pluck( controls, 'container' );
			if ( ! section.panel() ) {
				rootNodes.push( section );
			}
			appendContainer = ( section.contentContainer.is( 'ul' ) ) ? section.contentContainer : section.contentContainer.find( 'ul:first' );
			if ( ! api.utils.areElementListsEqual( controlContainers, appendContainer.children( '[id]' ) ) ) {
				_( controls ).each( function ( control ) {
					appendContainer.append( control.container );
				} );
				wasReflowed = true;
			}
		} );

		// Sort the root panels and sections.
		rootNodes.sort( api.utils.prioritySort );
		rootHeadContainers = _.pluck( rootNodes, 'headContainer' );
		appendContainer = $( '#customize-theme-controls .customize-pane-parent' ); // @todo This should be defined elsewhere, and to be configurable
		if ( ! api.utils.areElementListsEqual( rootHeadContainers, appendContainer.children() ) ) {
			_( rootNodes ).each( function ( rootNode ) {
				appendContainer.append( rootNode.headContainer );
			} );
			wasReflowed = true;
		}

		// Now re-trigger the active Value callbacks to that the panels and sections can decide whether they can be rendered.
		api.panel.each( function ( panel ) {
			var value = panel.active();
			panel.active.callbacks.fireWith( panel.active, [ value, value ] );
		} );
		api.section.each( function ( section ) {
			var value = section.active();
			section.active.callbacks.fireWith( section.active, [ value, value ] );
		} );

		// Restore focus if there was a reflow and there was an active (focused) element.
		if ( wasReflowed && activeElement ) {
			activeElement.focus();
		}
		api.trigger( 'pane-contents-reflowed' );
	}, api );

	// Define state values.
	api.state = new api.Values();
	_.each( [
		'saved',
		'saving',
		'trashing',
		'activated',
		'processing',
		'paneVisible',
		'expandedPanel',
		'expandedSection',
		'changesetDate',
		'selectedChangesetDate',
		'changesetStatus',
		'selectedChangesetStatus',
		'remainingTimeToPublish',
		'previewerAlive',
		'editShortcutVisibility',
		'changesetLocked',
		'previewedDevice'
	], function( name ) {
		api.state.create( name );
	});

	$( function() {
		api.settings = window._wpCustomizeSettings;
		api.l10n = window._wpCustomizeControlsL10n;

		// Check if we can run the Customizer.
		if ( ! api.settings ) {
			return;
		}

		// Bail if any incompatibilities are found.
		if ( ! $.support.postMessage || ( ! $.support.cors && api.settings.isCrossDomain ) ) {
			return;
		}

		if ( null === api.PreviewFrame.prototype.sensitivity ) {
			api.PreviewFrame.prototype.sensitivity = api.settings.timeouts.previewFrameSensitivity;
		}
		if ( null === api.Previewer.prototype.refreshBuffer ) {
			api.Previewer.prototype.refreshBuffer = api.settings.timeouts.windowRefresh;
		}

		var parent,
			body = $( document.body ),
			overlay = body.children( '.wp-full-overlay' ),
			title = $( '#customize-info .panel-title.site-title' ),
			closeBtn = $( '.customize-controls-close' ),
			saveBtn = $( '#save' ),
			btnWrapper = $( '#customize-save-button-wrapper' ),
			publishSettingsBtn = $( '#publish-settings' ),
			footerActions = $( '#customize-footer-actions' );

		// Add publish settings section in JS instead of PHP since the Customizer depends on it to function.
		api.bind( 'ready', function() {
			api.section.add( new api.OuterSection( 'publish_settings', {
				title: api.l10n.publishSettings,
				priority: 0,
				active: api.settings.theme.active
			} ) );
		} );

		// Set up publish settings section and its controls.
		api.section( 'publish_settings', function( section ) {
			var updateButtonsState, trashControl, updateSectionActive, isSectionActive, statusControl, dateControl, toggleDateControl, publishWhenTime, pollInterval, updateTimeArrivedPoller, cancelScheduleButtonReminder, timeArrivedPollingInterval = 1000;

			trashControl = new api.Control( 'trash_changeset', {
				type: 'button',
				section: section.id,
				priority: 30,
				input_attrs: {
					'class': 'button-link button-link-delete',
					value: api.l10n.discardChanges
				}
			} );
			api.control.add( trashControl );
			trashControl.deferred.embedded.done( function() {
				trashControl.container.find( '.button-link' ).on( 'click', function() {
					if ( confirm( api.l10n.trashConfirm ) ) {
						wp.customize.previewer.trash();
					}
				} );
			} );

			api.control.add( new api.PreviewLinkControl( 'changeset_preview_link', {
				section: section.id,
				priority: 100
			} ) );

			/**
			 * Returns whether the publish settings section should be active.
			 *
			 * @return {boolean} True if the section is active.
			 */
			isSectionActive = function() {
				if ( ! api.state( 'activated' ).get() ) {
					return false;
				}
				if ( api.state( 'trashing' ).get() || 'trash' === api.state( 'changesetStatus' ).get() ) {
					return false;
				}
				if ( '' === api.state( 'changesetStatus' ).get() && api.state( 'saved' ).get() ) {
					return false;
				}
				return true;
			};

			// Make sure publish settings are not available while the theme is not active and the customizer is in a published state.
			section.active.validate = isSectionActive;

			/**
			 * Updates the active section state.
			 *
			 * @returns {void}
			 */
			updateSectionActive = function() {
				section.active.set( isSectionActive() );
			};
			api.state( 'activated' ).bind( updateSectionActive );
			api.state( 'trashing' ).bind( updateSectionActive );
			api.state( 'saved' ).bind( updateSectionActive );
			api.state( 'changesetStatus' ).bind( updateSectionActive );
			updateSectionActive();

			// Bind visibility of the publish settings button to whether the section is active.

			/**
			 * Updates the
			 */
			updateButtonsState = function() {
				publishSettingsBtn.toggle( section.active.get() );
				saveBtn.toggleClass( 'has-next-sibling', section.active.get() );
			};
			updateButtonsState();
			section.active.bind( updateButtonsState );

			function highlightScheduleButton() {
				if ( ! cancelScheduleButtonReminder ) {
					cancelScheduleButtonReminder = api.utils.highlightButton( btnWrapper, {
						delay: 1000,

						// Only abort the reminder when the save button is focused.
						// If the user clicks the settings button to toggle the
						// settings closed, we'll still remind them.
						focusTarget: saveBtn
					} );
				}
			}
			function cancelHighlightScheduleButton() {
				if ( cancelScheduleButtonReminder ) {
					cancelScheduleButtonReminder();
					cancelScheduleButtonReminder = null;
				}
			}
			api.state( 'selectedChangesetStatus' ).bind( cancelHighlightScheduleButton );

			section.contentContainer.find( '.customize-action' ).text( api.l10n.updating );
			section.contentContainer.find( '.customize-section-back' ).removeAttr( 'tabindex' );
			publishSettingsBtn.prop( 'disabled', false );

			publishSettingsBtn.on( 'click', function( event ) {
				event.preventDefault();
				section.expanded.set( ! section.expanded.get() );
			} );

			section.expanded.bind( function( isExpanded ) {
				var defaultChangesetStatus;
				publishSettingsBtn.attr( 'aria-expanded', String( isExpanded ) );
				publishSettingsBtn.toggleClass( 'active', isExpanded );

				if ( isExpanded ) {
					cancelHighlightScheduleButton();
					return;
				}

				defaultChangesetStatus = api.state( 'changesetStatus' ).get();
				if ( '' === defaultChangesetStatus || 'auto-draft' === defaultChangesetStatus ) {
					defaultChangesetStatus = 'publish';
				}

				if ( api.state( 'selectedChangesetStatus' ).get() !== defaultChangesetStatus ) {
					highlightScheduleButton();
				} else if ( 'future' === api.state( 'selectedChangesetStatus' ).get() && api.state( 'selectedChangesetDate' ).get() !== api.state( 'changesetDate' ).get() ) {
					highlightScheduleButton();
				}
			} );

			statusControl = new api.Control( 'changeset_status', {
				priority: 10,
				type: 'radio',
				section: 'publish_settings',
				setting: api.state( 'selectedChangesetStatus' ),
				templateId: 'customize-selected-changeset-status-control',
				label: api.l10n.action,
				choices: api.settings.changeset.statusChoices
			} );
			api.control.add( statusControl );

			dateControl = new api.DateTimeControl( 'changeset_scheduled_date', {
				priority: 20,
				section: 'publish_settings',
				setting: api.state( 'selectedChangesetDate' ),
				minYear: ( new Date() ).getFullYear(),
				allowPastDate: false,
				includeTime: true,
				twelveHourFormat: /a/i.test( api.settings.timeFormat ),
				description: api.l10n.scheduleDescription
			} );
			dateControl.notifications.alt = true;
			api.control.add( dateControl );

			publishWhenTime = function() {
				api.state( 'selectedChangesetStatus' ).set( 'publish' );
				api.previewer.save();
			};

			// Start countdown for when the dateTime arrives, or clear interval when it is .
			updateTimeArrivedPoller = function() {
				var shouldPoll = (
					'future' === api.state( 'changesetStatus' ).get() &&
					'future' === api.state( 'selectedChangesetStatus' ).get() &&
					api.state( 'changesetDate' ).get() &&
					api.state( 'selectedChangesetDate' ).get() === api.state( 'changesetDate' ).get() &&
					api.utils.getRemainingTime( api.state( 'changesetDate' ).get() ) >= 0
				);

				if ( shouldPoll && ! pollInterval ) {
					pollInterval = setInterval( function() {
						var remainingTime = api.utils.getRemainingTime( api.state( 'changesetDate' ).get() );
						api.state( 'remainingTimeToPublish' ).set( remainingTime );
						if ( remainingTime <= 0 ) {
							clearInterval( pollInterval );
							pollInterval = 0;
							publishWhenTime();
						}
					}, timeArrivedPollingInterval );
				} else if ( ! shouldPoll && pollInterval ) {
					clearInterval( pollInterval );
					pollInterval = 0;
				}
			};

			api.state( 'changesetDate' ).bind( updateTimeArrivedPoller );
			api.state( 'selectedChangesetDate' ).bind( updateTimeArrivedPoller );
			api.state( 'changesetStatus' ).bind( updateTimeArrivedPoller );
			api.state( 'selectedChangesetStatus' ).bind( updateTimeArrivedPoller );
			updateTimeArrivedPoller();

			// Ensure dateControl only appears when selected status is future.
			dateControl.active.validate = function() {
				return 'future' === api.state( 'selectedChangesetStatus' ).get();
			};
			toggleDateControl = function( value ) {
				dateControl.active.set( 'future' === value );
			};
			toggleDateControl( api.state( 'selectedChangesetStatus' ).get() );
			api.state( 'selectedChangesetStatus' ).bind( toggleDateControl );

			// Show notification on date control when status is future but it isn't a future date.
			api.state( 'saving' ).bind( function( isSaving ) {
				if ( isSaving && 'future' === api.state( 'selectedChangesetStatus' ).get() ) {
					dateControl.toggleFutureDateNotification( ! dateControl.isFutureDate() );
				}
			} );
		} );

		// Prevent the form from saving when enter is pressed on an input or select element.
		$('#customize-controls').on( 'keydown', function( e ) {
			var isEnter = ( 13 === e.which ),
				$el = $( e.target );

			if ( isEnter && ( $el.is( 'input:not([type=button])' ) || $el.is( 'select' ) ) ) {
				e.preventDefault();
			}
		});

		// Expand/Collapse the main customizer customize info.
		$( '.customize-info' ).find( '> .accordion-section-title .customize-help-toggle' ).on( 'click', function() {
			var section = $( this ).closest( '.accordion-section' ),
				content = section.find( '.customize-panel-description:first' );

			if ( section.hasClass( 'cannot-expand' ) ) {
				return;
			}

			if ( section.hasClass( 'open' ) ) {
				section.toggleClass( 'open' );
				content.slideUp( api.Panel.prototype.defaultExpandedArguments.duration, function() {
					content.trigger( 'toggled' );
				} );
				$( this ).attr( 'aria-expanded', false );
			} else {
				content.slideDown( api.Panel.prototype.defaultExpandedArguments.duration, function() {
					content.trigger( 'toggled' );
				} );
				section.toggleClass( 'open' );
				$( this ).attr( 'aria-expanded', true );
			}
		});

		/**
		 * Initialize Previewer
		 *
		 * @alias wp.customize.previewer
		 */
		api.previewer = new api.Previewer({
			container:   '#customize-preview',
			form:        '#customize-controls',
			previewUrl:  api.settings.url.preview,
			allowedUrls: api.settings.url.allowed
		},/** @lends wp.customize.previewer */{

			nonce: api.settings.nonce,

			/**
			 * Build the query to send along with the Preview request.
			 *
			 * @since 3.4.0
			 * @since 4.7.0 Added options param.
			 * @access public
			 *
			 * @param {Object}  [options] Options.
			 * @param {boolean} [options.excludeCustomizedSaved=false] Exclude saved settings in customized response (values pending writing to changeset).
			 * @return {Object} Query vars.
			 */
			query: function( options ) {
				var queryVars = {
					wp_customize: 'on',
					customize_theme: api.settings.theme.stylesheet,
					nonce: this.nonce.preview,
					customize_changeset_uuid: api.settings.changeset.uuid
				};
				if ( api.settings.changeset.autosaved || ! api.state( 'saved' ).get() ) {
					queryVars.customize_autosaved = 'on';
				}

				/*
				 * Exclude customized data if requested especially for calls to requestChangesetUpdate.
				 * Changeset updates are differential and so it is a performance waste to send all of
				 * the dirty settings with each update.
				 */
				queryVars.customized = JSON.stringify( api.dirtyValues( {
					unsaved: options && options.excludeCustomizedSaved
				} ) );

				return queryVars;
			},

			/**
			 * Save (and publish) the customizer changeset.
			 *
			 * Updates to the changeset are transactional. If any of the settings
			 * are invalid then none of them will be written into the changeset.
			 * A revision will be made for the changeset post if revisions support
			 * has been added to the post type.
			 *
			 * @since 3.4.0
			 * @since 4.7.0 Added args param and return value.
			 *
			 * @param {Object} [args] Args.
			 * @param {string} [args.status=publish] Status.
			 * @param {string} [args.date] Date, in local time in MySQL format.
			 * @param {string} [args.title] Title
			 * @return {jQuery.promise} Promise.
			 */
			save: function( args ) {
				var previewer = this,
					deferred = $.Deferred(),
					changesetStatus = api.state( 'selectedChangesetStatus' ).get(),
					selectedChangesetDate = api.state( 'selectedChangesetDate' ).get(),
					processing = api.state( 'processing' ),
					submitWhenDoneProcessing,
					submit,
					modifiedWhileSaving = {},
					invalidSettings = [],
					invalidControls = [],
					invalidSettingLessControls = [];

				if ( args && args.status ) {
					changesetStatus = args.status;
				}

				if ( api.state( 'saving' ).get() ) {
					deferred.reject( 'already_saving' );
					deferred.promise();
				}

				api.state( 'saving' ).set( true );

				function captureSettingModifiedDuringSave( setting ) {
					modifiedWhileSaving[ setting.id ] = true;
				}

				submit = function () {
					var request, query, settingInvalidities = {}, latestRevision = api._latestRevision, errorCode = 'client_side_error';

					api.bind( 'change', captureSettingModifiedDuringSave );
					api.notifications.remove( errorCode );

					/*
					 * Block saving if there are any settings that are marked as
					 * invalid from the client (not from the server). Focus on
					 * the control.
					 */
					api.each( function( setting ) {
						setting.notifications.each( function( notification ) {
							if ( 'error' === notification.type && ! notification.fromServer ) {
								invalidSettings.push( setting.id );
								if ( ! settingInvalidities[ setting.id ] ) {
									settingInvalidities[ setting.id ] = {};
								}
								settingInvalidities[ setting.id ][ notification.code ] = notification;
							}
						} );
					} );

					// Find all invalid setting less controls with notification type error.
					api.control.each( function( control ) {
						if ( ! control.setting || ! control.setting.id && control.active.get() ) {
							control.notifications.each( function( notification ) {
								if ( 'error' === notification.type ) {
									invalidSettingLessControls.push( [ control ] );
								}
							} );
						}
					} );

					invalidControls = _.union( invalidSettingLessControls, _.values( api.findControlsForSettings( invalidSettings ) ) );
					if ( ! _.isEmpty( invalidControls ) ) {

						invalidControls[0][0].focus();
						api.unbind( 'change', captureSettingModifiedDuringSave );

						if ( invalidSettings.length ) {
							api.notifications.add( new api.Notification( errorCode, {
								message: ( 1 === invalidSettings.length ? api.l10n.saveBlockedError.singular : api.l10n.saveBlockedError.plural ).replace( /%s/g, String( invalidSettings.length ) ),
								type: 'error',
								dismissible: true,
								saveFailure: true
							} ) );
						}

						deferred.rejectWith( previewer, [
							{ setting_invalidities: settingInvalidities }
						] );
						api.state( 'saving' ).set( false );
						return deferred.promise();
					}

					/*
					 * Note that excludeCustomizedSaved is intentionally false so that the entire
					 * set of customized data will be included if bypassed changeset update.
					 */
					query = $.extend( previewer.query( { excludeCustomizedSaved: false } ), {
						nonce: previewer.nonce.save,
						customize_changeset_status: changesetStatus
					} );

					if ( args && args.date ) {
						query.customize_changeset_date = args.date;
					} else if ( 'future' === changesetStatus && selectedChangesetDate ) {
						query.customize_changeset_date = selectedChangesetDate;
					}

					if ( args && args.title ) {
						query.customize_changeset_title = args.title;
					}

					// Allow plugins to modify the params included with the save request.
					api.trigger( 'save-request-params', query );

					/*
					 * Note that the dirty customized values will have already been set in the
					 * changeset and so technically query.customized could be deleted. However,
					 * it is remaining here to make sure that any settings that got updated
					 * quietly which may have not triggered an update request will also get
					 * included in the values that get saved to the changeset. This will ensure
					 * that values that get injected via the saved event will be included in
					 * the changeset. This also ensures that setting values that were invalid
					 * will get re-validated, perhaps in the case of settings that are invalid
					 * due to dependencies on other settings.
					 */
					request = wp.ajax.post( 'customize_save', query );
					api.state( 'processing' ).set( api.state( 'processing' ).get() + 1 );

					api.trigger( 'save', request );

					request.always( function () {
						api.state( 'processing' ).set( api.state( 'processing' ).get() - 1 );
						api.state( 'saving' ).set( false );
						api.unbind( 'change', captureSettingModifiedDuringSave );
					} );

					// Remove notifications that were added due to save failures.
					api.notifications.each( function( notification ) {
						if ( notification.saveFailure ) {
							api.notifications.remove( notification.code );
						}
					});

					request.fail( function ( response ) {
						var notification, notificationArgs;
						notificationArgs = {
							type: 'error',
							dismissible: true,
							fromServer: true,
							saveFailure: true
						};

						if ( '0' === response ) {
							response = 'not_logged_in';
						} else if ( '-1' === response ) {
							// Back-compat in case any other check_ajax_referer() call is dying
							response = 'invalid_nonce';
						}

						if ( 'invalid_nonce' === response ) {
							previewer.cheatin();
						} else if ( 'not_logged_in' === response ) {
							previewer.preview.iframe.hide();
							previewer.login().done( function() {
								previewer.save();
								previewer.preview.iframe.show();
							} );
						} else if ( response.code ) {
							if ( 'not_future_date' === response.code && api.section.has( 'publish_settings' ) && api.section( 'publish_settings' ).active.get() && api.control.has( 'changeset_scheduled_date' ) ) {
								api.control( 'changeset_scheduled_date' ).toggleFutureDateNotification( true ).focus();
							} else if ( 'changeset_locked' !== response.code ) {
								notification = new api.Notification( response.code, _.extend( notificationArgs, {
									message: response.message
								} ) );
							}
						} else {
							notification = new api.Notification( 'unknown_error', _.extend( notificationArgs, {
								message: api.l10n.unknownRequestFail
							} ) );
						}

						if ( notification ) {
							api.notifications.add( notification );
						}

						if ( response.setting_validities ) {
							api._handleSettingValidities( {
								settingValidities: response.setting_validities,
								focusInvalidControl: true
							} );
						}

						deferred.rejectWith( previewer, [ response ] );
						api.trigger( 'error', response );

						// Start a new changeset if the underlying changeset was published.
						if ( 'changeset_already_published' === response.code && response.next_changeset_uuid ) {
							api.settings.changeset.uuid = response.next_changeset_uuid;
							api.state( 'changesetStatus' ).set( '' );
							if ( api.settings.changeset.branching ) {
								parent.send( 'changeset-uuid', api.settings.changeset.uuid );
							}
							api.previewer.send( 'changeset-uuid', api.settings.changeset.uuid );
						}
					} );

					request.done( function( response ) {

						previewer.send( 'saved', response );

						api.state( 'changesetStatus' ).set( response.changeset_status );
						if ( response.changeset_date ) {
							api.state( 'changesetDate' ).set( response.changeset_date );
						}

						if ( 'publish' === response.changeset_status ) {

							// Mark all published as clean if they haven't been modified during the request.
							api.each( function( setting ) {
								/*
								 * Note that the setting revision will be undefined in the case of setting
								 * values that are marked as dirty when the customizer is loaded, such as
								 * when applying starter content. All other dirty settings will have an
								 * associated revision due to their modification triggering a change event.
								 */
								if ( setting._dirty && ( _.isUndefined( api._latestSettingRevisions[ setting.id ] ) || api._latestSettingRevisions[ setting.id ] <= latestRevision ) ) {
									setting._dirty = false;
								}
							} );

							api.state( 'changesetStatus' ).set( '' );
							api.settings.changeset.uuid = response.next_changeset_uuid;
							if ( api.settings.changeset.branching ) {
								parent.send( 'changeset-uuid', api.settings.changeset.uuid );
							}
						}

						// Prevent subsequent requestChangesetUpdate() calls from including the settings that have been saved.
						api._lastSavedRevision = Math.max( latestRevision, api._lastSavedRevision );

						if ( response.setting_validities ) {
							api._handleSettingValidities( {
								settingValidities: response.setting_validities,
								focusInvalidControl: true
							} );
						}

						deferred.resolveWith( previewer, [ response ] );
						api.trigger( 'saved', response );

						// Restore the global dirty state if any settings were modified during save.
						if ( ! _.isEmpty( modifiedWhileSaving ) ) {
							api.state( 'saved' ).set( false );
						}
					} );
				};

				if ( 0 === processing() ) {
					submit();
				} else {
					submitWhenDoneProcessing = function () {
						if ( 0 === processing() ) {
							api.state.unbind( 'change', submitWhenDoneProcessing );
							submit();
						}
					};
					api.state.bind( 'change', submitWhenDoneProcessing );
				}

				return deferred.promise();
			},

			/**
			 * Trash the current changes.
			 *
			 * Revert the Customizer to it's previously-published state.
			 *
			 * @since 4.9.0
			 *
			 * @return {jQuery.promise} Promise.
			 */
			trash: function trash() {
				var request, success, fail;

				api.state( 'trashing' ).set( true );
				api.state( 'processing' ).set( api.state( 'processing' ).get() + 1 );

				request = wp.ajax.post( 'customize_trash', {
					customize_changeset_uuid: api.settings.changeset.uuid,
					nonce: api.settings.nonce.trash
				} );
				api.notifications.add( new api.OverlayNotification( 'changeset_trashing', {
					type: 'info',
					message: api.l10n.revertingChanges,
					loading: true
				} ) );

				success = function() {
					var urlParser = document.createElement( 'a' ), queryParams;

					api.state( 'changesetStatus' ).set( 'trash' );
					api.each( function( setting ) {
						setting._dirty = false;
					} );
					api.state( 'saved' ).set( true );

					// Go back to Customizer without changeset.
					urlParser.href = location.href;
					queryParams = api.utils.parseQueryString( urlParser.search.substr( 1 ) );
					delete queryParams.changeset_uuid;
					queryParams['return'] = api.settings.url['return'];
					urlParser.search = $.param( queryParams );
					location.replace( urlParser.href );
				};

				fail = function( code, message ) {
					var notificationCode = code || 'unknown_error';
					api.state( 'processing' ).set( api.state( 'processing' ).get() - 1 );
					api.state( 'trashing' ).set( false );
					api.notifications.remove( 'changeset_trashing' );
					api.notifications.add( new api.Notification( notificationCode, {
						message: message || api.l10n.unknownError,
						dismissible: true,
						type: 'error'
					} ) );
				};

				request.done( function( response ) {
					success( response.message );
				} );

				request.fail( function( response ) {
					var code = response.code || 'trashing_failed';
					if ( response.success || 'non_existent_changeset' === code || 'changeset_already_trashed' === code ) {
						success( response.message );
					} else {
						fail( code, response.message );
					}
				} );
			},

			/**
			 * Builds the front preview url with the current state of customizer.
			 *
			 * @since 4.9
			 *
			 * @return {string} Preview url.
			 */
			getFrontendPreviewUrl: function() {
				var previewer = this, params, urlParser;
				urlParser = document.createElement( 'a' );
				urlParser.href = previewer.previewUrl.get();
				params = api.utils.parseQueryString( urlParser.search.substr( 1 ) );

				if ( api.state( 'changesetStatus' ).get() && 'publish' !== api.state( 'changesetStatus' ).get() ) {
					params.customize_changeset_uuid = api.settings.changeset.uuid;
				}
				if ( ! api.state( 'activated' ).get() ) {
					params.customize_theme = api.settings.theme.stylesheet;
				}

				urlParser.search = $.param( params );
				return urlParser.href;
			}
		});

		// Ensure preview nonce is included with every customized request, to allow post data to be read.
		$.ajaxPrefilter( function injectPreviewNonce( options ) {
			if ( ! /wp_customize=on/.test( options.data ) ) {
				return;
			}
			options.data += '&' + $.param({
				customize_preview_nonce: api.settings.nonce.preview
			});
		});

		// Refresh the nonces if the preview sends updated nonces over.
		api.previewer.bind( 'nonce', function( nonce ) {
			$.extend( this.nonce, nonce );
		});

		// Refresh the nonces if login sends updated nonces over.
		api.bind( 'nonce-refresh', function( nonce ) {
			$.extend( api.settings.nonce, nonce );
			$.extend( api.previewer.nonce, nonce );
			api.previewer.send( 'nonce-refresh', nonce );
		});

		// Create Settings
		$.each( api.settings.settings, function( id, data ) {
			var Constructor = api.settingConstructor[ data.type ] || api.Setting;
			api.add( new Constructor( id, data.value, {
				transport: data.transport,
				previewer: api.previewer,
				dirty: !! data.dirty
			} ) );
		});

		// Create Panels
		$.each( api.settings.panels, function ( id, data ) {
			var Constructor = api.panelConstructor[ data.type ] || api.Panel, options;
			options = _.extend( { params: data }, data ); // Inclusion of params alias is for back-compat for custom panels that expect to augment this property.
			api.panel.add( new Constructor( id, options ) );
		});

		// Create Sections
		$.each( api.settings.sections, function ( id, data ) {
			var Constructor = api.sectionConstructor[ data.type ] || api.Section, options;
			options = _.extend( { params: data }, data ); // Inclusion of params alias is for back-compat for custom sections that expect to augment this property.
			api.section.add( new Constructor( id, options ) );
		});

		// Create Controls
		$.each( api.settings.controls, function( id, data ) {
			var Constructor = api.controlConstructor[ data.type ] || api.Control, options;
			options = _.extend( { params: data }, data ); // Inclusion of params alias is for back-compat for custom controls that expect to augment this property.
			api.control.add( new Constructor( id, options ) );
		});

		// Focus the autofocused element
		_.each( [ 'panel', 'section', 'control' ], function( type ) {
			var id = api.settings.autofocus[ type ];
			if ( ! id ) {
				return;
			}

			/*
			 * Defer focus until:
			 * 1. The panel, section, or control exists (especially for dynamically-created ones).
			 * 2. The instance is embedded in the document (and so is focusable).
			 * 3. The preview has finished loading so that the active states have been set.
			 */
			api[ type ]( id, function( instance ) {
				instance.deferred.embedded.done( function() {
					api.previewer.deferred.active.done( function() {
						instance.focus();
					});
				});
			});
		});

		api.bind( 'ready', api.reflowPaneContents );
		$( [ api.panel, api.section, api.control ] ).each( function ( i, values ) {
			var debouncedReflowPaneContents = _.debounce( api.reflowPaneContents, api.settings.timeouts.reflowPaneContents );
			values.bind( 'add', debouncedReflowPaneContents );
			values.bind( 'change', debouncedReflowPaneContents );
			values.bind( 'remove', debouncedReflowPaneContents );
		} );

		// Set up global notifications area.
		api.bind( 'ready', function setUpGlobalNotificationsArea() {
			var sidebar, containerHeight, containerInitialTop;
			api.notifications.container = $( '#customize-notifications-area' );

			api.notifications.bind( 'change', _.debounce( function() {
				api.notifications.render();
			} ) );

			sidebar = $( '.wp-full-overlay-sidebar-content' );
			api.notifications.bind( 'rendered', function updateSidebarTop() {
				sidebar.css( 'top', '' );
				if ( 0 !== api.notifications.count() ) {
					containerHeight = api.notifications.container.outerHeight() + 1;
					containerInitialTop = parseInt( sidebar.css( 'top' ), 10 );
					sidebar.css( 'top', containerInitialTop + containerHeight + 'px' );
				}
				api.notifications.trigger( 'sidebarTopUpdated' );
			});

			api.notifications.render();
		});

		// Save and activated states
		(function( state ) {
			var saved = state.instance( 'saved' ),
				saving = state.instance( 'saving' ),
				trashing = state.instance( 'trashing' ),
				activated = state.instance( 'activated' ),
				processing = state.instance( 'processing' ),
				paneVisible = state.instance( 'paneVisible' ),
				expandedPanel = state.instance( 'expandedPanel' ),
				expandedSection = state.instance( 'expandedSection' ),
				changesetStatus = state.instance( 'changesetStatus' ),
				selectedChangesetStatus = state.instance( 'selectedChangesetStatus' ),
				changesetDate = state.instance( 'changesetDate' ),
				selectedChangesetDate = state.instance( 'selectedChangesetDate' ),
				previewerAlive = state.instance( 'previewerAlive' ),
				editShortcutVisibility  = state.instance( 'editShortcutVisibility' ),
				changesetLocked = state.instance( 'changesetLocked' ),
				populateChangesetUuidParam, defaultSelectedChangesetStatus;

			state.bind( 'change', function() {
				var canSave;

				if ( ! activated() ) {
					saveBtn.val( api.l10n.activate );
					closeBtn.find( '.screen-reader-text' ).text( api.l10n.cancel );

				} else if ( '' === changesetStatus.get() && saved() ) {
					if ( api.settings.changeset.currentUserCanPublish ) {
						saveBtn.val( api.l10n.published );
					} else {
						saveBtn.val( api.l10n.saved );
					}
					closeBtn.find( '.screen-reader-text' ).text( api.l10n.close );

				} else {
					if ( 'draft' === selectedChangesetStatus() ) {
						if ( saved() && selectedChangesetStatus() === changesetStatus() ) {
							saveBtn.val( api.l10n.draftSaved );
						} else {
							saveBtn.val( api.l10n.saveDraft );
						}
					} else if ( 'future' === selectedChangesetStatus() ) {
						if ( saved() && selectedChangesetStatus() === changesetStatus() ) {
							if ( changesetDate.get() !== selectedChangesetDate.get() ) {
								saveBtn.val( api.l10n.schedule );
							} else {
								saveBtn.val( api.l10n.scheduled );
							}
						} else {
							saveBtn.val( api.l10n.schedule );
						}
					} else if ( api.settings.changeset.currentUserCanPublish ) {
						saveBtn.val( api.l10n.publish );
					}
					closeBtn.find( '.screen-reader-text' ).text( api.l10n.cancel );
				}

				/*
				 * Save (publish) button should be enabled if saving is not currently happening,
				 * and if the theme is not active or the changeset exists but is not published.
				 */
				canSave = ! saving() && ! trashing() && ! changesetLocked() && ( ! activated() || ! saved() || ( changesetStatus() !== selectedChangesetStatus() && '' !== changesetStatus() ) || ( 'future' === selectedChangesetStatus() && changesetDate.get() !== selectedChangesetDate.get() ) );

				saveBtn.prop( 'disabled', ! canSave );
			});

			selectedChangesetStatus.validate = function( status ) {
				if ( '' === status || 'auto-draft' === status ) {
					return null;
				}
				return status;
			};

			defaultSelectedChangesetStatus = api.settings.changeset.currentUserCanPublish ? 'publish' : 'draft';

			// Set default states.
			changesetStatus( api.settings.changeset.status );
			changesetLocked( Boolean( api.settings.changeset.lockUser ) );
			changesetDate( api.settings.changeset.publishDate );
			selectedChangesetDate( api.settings.changeset.publishDate );
			selectedChangesetStatus( '' === api.settings.changeset.status || 'auto-draft' === api.settings.changeset.status ? defaultSelectedChangesetStatus : api.settings.changeset.status );
			selectedChangesetStatus.link( changesetStatus ); // Ensure that direct updates to status on server via wp.customizer.previewer.save() will update selection.
			saved( true );
			if ( '' === changesetStatus() ) { // Handle case for loading starter content.
				api.each( function( setting ) {
					if ( setting._dirty ) {
						saved( false );
					}
				} );
			}
			saving( false );
			activated( api.settings.theme.active );
			processing( 0 );
			paneVisible( true );
			expandedPanel( false );
			expandedSection( false );
			previewerAlive( true );
			editShortcutVisibility( 'visible' );

			api.bind( 'change', function() {
				if ( state( 'saved' ).get() ) {
					state( 'saved' ).set( false );
				}
			});

			// Populate changeset UUID param when state becomes dirty.
			if ( api.settings.changeset.branching ) {
				saved.bind( function( isSaved ) {
					if ( ! isSaved ) {
						populateChangesetUuidParam( true );
					}
				});
			}

			saving.bind( function( isSaving ) {
				body.toggleClass( 'saving', isSaving );
			} );
			trashing.bind( function( isTrashing ) {
				body.toggleClass( 'trashing', isTrashing );
			} );

			api.bind( 'saved', function( response ) {
				state('saved').set( true );
				if ( 'publish' === response.changeset_status ) {
					state( 'activated' ).set( true );
				}
			});

			activated.bind( function( to ) {
				if ( to ) {
					api.trigger( 'activated' );
				}
			});

			/**
			 * Populate URL with UUID via `history.replaceState()`.
			 *
			 * @since 4.7.0
			 * @access private
			 *
			 * @param {boolean} isIncluded Is UUID included.
			 * @return {void}
			 */
			populateChangesetUuidParam = function( isIncluded ) {
				var urlParser, queryParams;

				// Abort on IE9 which doesn't support history management.
				if ( ! history.replaceState ) {
					return;
				}

				urlParser = document.createElement( 'a' );
				urlParser.href = location.href;
				queryParams = api.utils.parseQueryString( urlParser.search.substr( 1 ) );
				if ( isIncluded ) {
					if ( queryParams.changeset_uuid === api.settings.changeset.uuid ) {
						return;
					}
					queryParams.changeset_uuid = api.settings.changeset.uuid;
				} else {
					if ( ! queryParams.changeset_uuid ) {
						return;
					}
					delete queryParams.changeset_uuid;
				}
				urlParser.search = $.param( queryParams );
				history.replaceState( {}, document.title, urlParser.href );
			};

			// Show changeset UUID in URL when in branching mode and there is a saved changeset.
			if ( api.settings.changeset.branching ) {
				changesetStatus.bind( function( newStatus ) {
					populateChangesetUuidParam( '' !== newStatus && 'publish' !== newStatus && 'trash' !== newStatus );
				} );
			}
		}( api.state ) );

		/**
		 * Handles lock notice and take over request.
		 *
		 * @since 4.9.0
		 */
		( function checkAndDisplayLockNotice() {

			var LockedNotification = api.OverlayNotification.extend(/** @lends wp.customize~LockedNotification.prototype */{

				/**
				 * Template ID.
				 *
				 * @type {string}
				 */
				templateId: 'customize-changeset-locked-notification',

				/**
				 * Lock user.
				 *
				 * @type {Object}
				 */
				lockUser: null,

				/**
				 * A notification that is displayed in a full-screen overlay with information about the locked changeset.
				 *
				 * @since 4.9.0
				 *
				 * @constructs wp.customize~LockedNotification
				 * @augments   wp.customize.OverlayNotification
				 * @memberOf   wp.customize
				 *
				 * @param {string} [code] - Code.
				 * @param {Object} [params] - Params.
				 */
				initialize: function( code, params ) {
					var notification = this, _code, _params;
					_code = code || 'changeset_locked';
					_params = _.extend(
						{
							type: 'warning',
							containerClasses: '',
							lockUser: {}
						},
						params
					);
					_params.containerClasses += ' notification-changeset-locked';
					api.OverlayNotification.prototype.initialize.call( notification, _code, _params );
				},

				/**
				 * Render notification.
				 *
				 * @since 4.9.0
				 *
				 * @return {jQuery} Notification container.
				 */
				render: function() {
					var notification = this, li, data, takeOverButton, request;
					data = _.extend(
						{
							allowOverride: false,
							returnUrl: api.settings.url['return'],
							previewUrl: api.previewer.previewUrl.get(),
							frontendPreviewUrl: api.previewer.getFrontendPreviewUrl()
						},
						this
					);

					li = api.OverlayNotification.prototype.render.call( data );

					// Try to autosave the changeset now.
					api.requestChangesetUpdate( {}, { autosave: true } ).fail( function( response ) {
						if ( ! response.autosaved ) {
							li.find( '.notice-error' ).prop( 'hidden', false ).text( response.message || api.l10n.unknownRequestFail );
						}
					} );

					takeOverButton = li.find( '.customize-notice-take-over-button' );
					takeOverButton.on( 'click', function( event ) {
						event.preventDefault();
						if ( request ) {
							return;
						}

						takeOverButton.addClass( 'disabled' );
						request = wp.ajax.post( 'customize_override_changeset_lock', {
							wp_customize: 'on',
							customize_theme: api.settings.theme.stylesheet,
							customize_changeset_uuid: api.settings.changeset.uuid,
							nonce: api.settings.nonce.override_lock
						} );

						request.done( function() {
							api.notifications.remove( notification.code ); // Remove self.
							api.state( 'changesetLocked' ).set( false );
						} );

						request.fail( function( response ) {
							var message = response.message || api.l10n.unknownRequestFail;
							li.find( '.notice-error' ).prop( 'hidden', false ).text( message );

							request.always( function() {
								takeOverButton.removeClass( 'disabled' );
							} );
						} );

						request.always( function() {
							request = null;
						} );
					} );

					return li;
				}
			});

			/**
			 * Start lock.
			 *
			 * @since 4.9.0
			 *
			 * @param {Object} [args] - Args.
			 * @param {Object} [args.lockUser] - Lock user data.
			 * @param {boolean} [args.allowOverride=false] - Whether override is allowed.
			 * @return {void}
			 */
			function startLock( args ) {
				if ( args && args.lockUser ) {
					api.settings.changeset.lockUser = args.lockUser;
				}
				api.state( 'changesetLocked' ).set( true );
				api.notifications.add( new LockedNotification( 'changeset_locked', {
					lockUser: api.settings.changeset.lockUser,
					allowOverride: Boolean( args && args.allowOverride )
				} ) );
			}

			// Show initial notification.
			if ( api.settings.changeset.lockUser ) {
				startLock( { allowOverride: true } );
			}

			// Check for lock when sending heartbeat requests.
			$( document ).on( 'heartbeat-send.update_lock_notice', function( event, data ) {
				data.check_changeset_lock = true;
				data.changeset_uuid = api.settings.changeset.uuid;
			} );

			// Handle heartbeat ticks.
			$( document ).on( 'heartbeat-tick.update_lock_notice', function( event, data ) {
				var notification, code = 'changeset_locked';
				if ( ! data.customize_changeset_lock_user ) {
					return;
				}

				// Update notification when a different user takes over.
				notification = api.notifications( code );
				if ( notification && notification.lockUser.id !== api.settings.changeset.lockUser.id ) {
					api.notifications.remove( code );
				}

				startLock( {
					lockUser: data.customize_changeset_lock_user
				} );
			} );

			// Handle locking in response to changeset save errors.
			api.bind( 'error', function( response ) {
				if ( 'changeset_locked' === response.code && response.lock_user ) {
					startLock( {
						lockUser: response.lock_user
					} );
				}
			} );
		} )();

		// Set up initial notifications.
		(function() {
			var removedQueryParams = [], autosaveDismissed = false;

			/**
			 * Obtain the URL to restore the autosave.
			 *
			 * @return {string} Customizer URL.
			 */
			function getAutosaveRestorationUrl() {
				var urlParser, queryParams;
				urlParser = document.createElement( 'a' );
				urlParser.href = location.href;
				queryParams = api.utils.parseQueryString( urlParser.search.substr( 1 ) );
				if ( api.settings.changeset.latestAutoDraftUuid ) {
					queryParams.changeset_uuid = api.settings.changeset.latestAutoDraftUuid;
				} else {
					queryParams.customize_autosaved = 'on';
				}
				queryParams['return'] = api.settings.url['return'];
				urlParser.search = $.param( queryParams );
				return urlParser.href;
			}

			/**
			 * Remove parameter from the URL.
			 *
			 * @param {Array} params - Parameter names to remove.
			 * @return {void}
			 */
			function stripParamsFromLocation( params ) {
				var urlParser = document.createElement( 'a' ), queryParams, strippedParams = 0;
				urlParser.href = location.href;
				queryParams = api.utils.parseQueryString( urlParser.search.substr( 1 ) );
				_.each( params, function( param ) {
					if ( 'undefined' !== typeof queryParams[ param ] ) {
						strippedParams += 1;
						delete queryParams[ param ];
					}
				} );
				if ( 0 === strippedParams ) {
					return;
				}

				urlParser.search = $.param( queryParams );
				history.replaceState( {}, document.title, urlParser.href );
			}

			/**
			 * Dismiss autosave.
			 *
			 * @return {void}
			 */
			function dismissAutosave() {
				if ( autosaveDismissed ) {
					return;
				}
				wp.ajax.post( 'customize_dismiss_autosave_or_lock', {
					wp_customize: 'on',
					customize_theme: api.settings.theme.stylesheet,
					customize_changeset_uuid: api.settings.changeset.uuid,
					nonce: api.settings.nonce.dismiss_autosave_or_lock,
					dismiss_autosave: true
				} );
				autosaveDismissed = true;
			}

			/**
			 * Add notification regarding the availability of an autosave to restore.
			 *
			 * @return {void}
			 */
			function addAutosaveRestoreNotification() {
				var code = 'autosave_available', onStateChange;

				// Since there is an autosave revision and the user hasn't loaded with autosaved, add notification to prompt to load autosaved version.
				api.notifications.add( new api.Notification( code, {
					message: api.l10n.autosaveNotice,
					type: 'warning',
					dismissible: true,
					render: function() {
						var li = api.Notification.prototype.render.call( this ), link;

						// Handle clicking on restoration link.
						link = li.find( 'a' );
						link.prop( 'href', getAutosaveRestorationUrl() );
						link.on( 'click', function( event ) {
							event.preventDefault();
							location.replace( getAutosaveRestorationUrl() );
						} );

						// Handle dismissal of notice.
						li.find( '.notice-dismiss' ).on( 'click', dismissAutosave );

						return li;
					}
				} ) );

				// Remove the notification once the user starts making changes.
				onStateChange = function() {
					dismissAutosave();
					api.notifications.remove( code );
					api.unbind( 'change', onStateChange );
					api.state( 'changesetStatus' ).unbind( onStateChange );
				};
				api.bind( 'change', onStateChange );
				api.state( 'changesetStatus' ).bind( onStateChange );
			}

			if ( api.settings.changeset.autosaved ) {
				api.state( 'saved' ).set( false );
				removedQueryParams.push( 'customize_autosaved' );
			}
			if ( ! api.settings.changeset.branching && ( ! api.settings.changeset.status || 'auto-draft' === api.settings.changeset.status ) ) {
				removedQueryParams.push( 'changeset_uuid' ); // Remove UUID when restoring autosave auto-draft.
			}
			if ( removedQueryParams.length > 0 ) {
				stripParamsFromLocation( removedQueryParams );
			}
			if ( api.settings.changeset.latestAutoDraftUuid || api.settings.changeset.hasAutosaveRevision ) {
				addAutosaveRestoreNotification();
			}
		})();

		// Check if preview url is valid and load the preview frame.
		if ( api.previewer.previewUrl() ) {
			api.previewer.refresh();
		} else {
			api.previewer.previewUrl( api.settings.url.home );
		}

		// Button bindings.
		saveBtn.click( function( event ) {
			api.previewer.save();
			event.preventDefault();
		}).keydown( function( event ) {
			if ( 9 === event.which ) { // Tab.
				return;
			}
			if ( 13 === event.which ) { // Enter.
				api.previewer.save();
			}
			event.preventDefault();
		});

		closeBtn.keydown( function( event ) {
			if ( 9 === event.which ) { // Tab.
				return;
			}
			if ( 13 === event.which ) { // Enter.
				this.click();
			}
			event.preventDefault();
		});

		$( '.collapse-sidebar' ).on( 'click', function() {
			api.state( 'paneVisible' ).set( ! api.state( 'paneVisible' ).get() );
		});

		api.state( 'paneVisible' ).bind( function( paneVisible ) {
			overlay.toggleClass( 'preview-only', ! paneVisible );
			overlay.toggleClass( 'expanded', paneVisible );
			overlay.toggleClass( 'collapsed', ! paneVisible );

			if ( ! paneVisible ) {
				$( '.collapse-sidebar' ).attr({ 'aria-expanded': 'false', 'aria-label': api.l10n.expandSidebar });
			} else {
				$( '.collapse-sidebar' ).attr({ 'aria-expanded': 'true', 'aria-label': api.l10n.collapseSidebar });
			}
		});

		// Keyboard shortcuts - esc to exit section/panel.
		body.on( 'keydown', function( event ) {
			var collapsedObject, expandedControls = [], expandedSections = [], expandedPanels = [];

			if ( 27 !== event.which ) { // Esc.
				return;
			}

			/*
			 * Abort if the event target is not the body (the default) and not inside of #customize-controls.
			 * This ensures that ESC meant to collapse a modal dialog or a TinyMCE toolbar won't collapse something else.
			 */
			if ( ! $( event.target ).is( 'body' ) && ! $.contains( $( '#customize-controls' )[0], event.target ) ) {
				return;
			}

			// Check for expanded expandable controls (e.g. widgets and nav menus items), sections, and panels.
			api.control.each( function( control ) {
				if ( control.expanded && control.expanded() && _.isFunction( control.collapse ) ) {
					expandedControls.push( control );
				}
			});
			api.section.each( function( section ) {
				if ( section.expanded() ) {
					expandedSections.push( section );
				}
			});
			api.panel.each( function( panel ) {
				if ( panel.expanded() ) {
					expandedPanels.push( panel );
				}
			});

			// Skip collapsing expanded controls if there are no expanded sections.
			if ( expandedControls.length > 0 && 0 === expandedSections.length ) {
				expandedControls.length = 0;
			}

			// Collapse the most granular expanded object.
			collapsedObject = expandedControls[0] || expandedSections[0] || expandedPanels[0];
			if ( collapsedObject ) {
				if ( 'themes' === collapsedObject.params.type ) {

					// Themes panel or section.
					if ( body.hasClass( 'modal-open' ) ) {
						collapsedObject.closeDetails();
					} else if ( api.panel.has( 'themes' ) ) {

						// If we're collapsing a section, collapse the panel also.
						api.panel( 'themes' ).collapse();
					}
					return;
				}
				collapsedObject.collapse();
				event.preventDefault();
			}
		});

		$( '.customize-controls-preview-toggle' ).on( 'click', function() {
			api.state( 'paneVisible' ).set( ! api.state( 'paneVisible' ).get() );
		});

		/*
		 * Sticky header feature.
		 */
		(function initStickyHeaders() {
			var parentContainer = $( '.wp-full-overlay-sidebar-content' ),
				changeContainer, updateHeaderHeight, releaseStickyHeader, resetStickyHeader, positionStickyHeader,
				activeHeader, lastScrollTop;

			/**
			 * Determine which panel or section is currently expanded.
			 *
			 * @since 4.7.0
			 * @access private
			 *
			 * @param {wp.customize.Panel|wp.customize.Section} container Construct.
			 * @return {void}
			 */
			changeContainer = function( container ) {
				var newInstance = container,
					expandedSection = api.state( 'expandedSection' ).get(),
					expandedPanel = api.state( 'expandedPanel' ).get(),
					headerElement;

				if ( activeHeader && activeHeader.element ) {
					// Release previously active header element.
					releaseStickyHeader( activeHeader.element );

					// Remove event listener in the previous panel or section.
					activeHeader.element.find( '.description' ).off( 'toggled', updateHeaderHeight );
				}

				if ( ! newInstance ) {
					if ( ! expandedSection && expandedPanel && expandedPanel.contentContainer ) {
						newInstance = expandedPanel;
					} else if ( ! expandedPanel && expandedSection && expandedSection.contentContainer ) {
						newInstance = expandedSection;
					} else {
						activeHeader = false;
						return;
					}
				}

				headerElement = newInstance.contentContainer.find( '.customize-section-title, .panel-meta' ).first();
				if ( headerElement.length ) {
					activeHeader = {
						instance: newInstance,
						element:  headerElement,
						parent:   headerElement.closest( '.customize-pane-child' ),
						height:   headerElement.outerHeight()
					};

					// Update header height whenever help text is expanded or collapsed.
					activeHeader.element.find( '.description' ).on( 'toggled', updateHeaderHeight );

					if ( expandedSection ) {
						resetStickyHeader( activeHeader.element, activeHeader.parent );
					}
				} else {
					activeHeader = false;
				}
			};
			api.state( 'expandedSection' ).bind( changeContainer );
			api.state( 'expandedPanel' ).bind( changeContainer );

			// Throttled scroll event handler.
			parentContainer.on( 'scroll', _.throttle( function() {
				if ( ! activeHeader ) {
					return;
				}

				var scrollTop = parentContainer.scrollTop(),
					scrollDirection;

				if ( ! lastScrollTop ) {
					scrollDirection = 1;
				} else {
					if ( scrollTop === lastScrollTop ) {
						scrollDirection = 0;
					} else if ( scrollTop > lastScrollTop ) {
						scrollDirection = 1;
					} else {
						scrollDirection = -1;
					}
				}
				lastScrollTop = scrollTop;
				if ( 0 !== scrollDirection ) {
					positionStickyHeader( activeHeader, scrollTop, scrollDirection );
				}
			}, 8 ) );

			// Update header position on sidebar layout change.
			api.notifications.bind( 'sidebarTopUpdated', function() {
				if ( activeHeader && activeHeader.element.hasClass( 'is-sticky' ) ) {
					activeHeader.element.css( 'top', parentContainer.css( 'top' ) );
				}
			});

			// Release header element if it is sticky.
			releaseStickyHeader = function( headerElement ) {
				if ( ! headerElement.hasClass( 'is-sticky' ) ) {
					return;
				}
				headerElement
					.removeClass( 'is-sticky' )
					.addClass( 'maybe-sticky is-in-view' )
					.css( 'top', parentContainer.scrollTop() + 'px' );
			};

			// Reset position of the sticky header.
			resetStickyHeader = function( headerElement, headerParent ) {
				if ( headerElement.hasClass( 'is-in-view' ) ) {
					headerElement
						.removeClass( 'maybe-sticky is-in-view' )
						.css( {
							width: '',
							top:   ''
						} );
					headerParent.css( 'padding-top', '' );
				}
			};

			/**
			 * Update the active header height.
			 *
			 * @since 4.7.0
			 * @access private
			 *
			 * @return {void}
			 */
			updateHeaderHeight = function() {
				activeHeader.height = activeHeader.element.outerHeight();
			};

			/**
			 * Reposition the header on throttled `scroll` event.
			 *
			 * @since 4.7.0
			 * @access private
			 *
			 * @param {Object} header           The header.
			 * @param {number} scrollTop        The scroll top position.
			 * @param {number} scrollDirection  The scroll direction, negative number being up and positive being down.
			 *
			 * @return {void}
			 */
			positionStickyHeader = function( header, scrollTop, scrollDirection ) {
				var headerElement = header.element,
					headerParent = header.parent,
					headerHeight = header.height,
					headerTop = parseInt( headerElement.css( 'top' ), 10 ),
					maybeSticky = headerElement.hasClass( 'maybe-sticky' ),
					isSticky = headerElement.hasClass( 'is-sticky' ),
					isInView = headerElement.hasClass( 'is-in-view' ),
					isScrollingUp = ( -1 === scrollDirection );

				// When scrolling down, gradually hide sticky header.
				if ( ! isScrollingUp ) {
					if ( isSticky ) {
						headerTop = scrollTop;
						headerElement
							.removeClass( 'is-sticky' )
							.css( {
								top:   headerTop + 'px',
								width: ''
							} );
					}
					if ( isInView && scrollTop > headerTop + headerHeight ) {
						headerElement.removeClass( 'is-in-view' );
						headerParent.css( 'padding-top', '' );
					}
					return;
				}

				// Scrolling up.
				if ( ! maybeSticky && scrollTop >= headerHeight ) {
					maybeSticky = true;
					headerElement.addClass( 'maybe-sticky' );
				} else if ( 0 === scrollTop ) {
					// Reset header in base position.
					headerElement
						.removeClass( 'maybe-sticky is-in-view is-sticky' )
						.css( {
							top:   '',
							width: ''
						} );
					headerParent.css( 'padding-top', '' );
					return;
				}

				if ( isInView && ! isSticky ) {
					// Header is in the view but is not yet sticky.
					if ( headerTop >= scrollTop ) {
						// Header is fully visible.
						headerElement
							.addClass( 'is-sticky' )
							.css( {
								top:   parentContainer.css( 'top' ),
								width: headerParent.outerWidth() + 'px'
							} );
					}
				} else if ( maybeSticky && ! isInView ) {
					// Header is out of the view.
					headerElement
						.addClass( 'is-in-view' )
						.css( 'top', ( scrollTop - headerHeight ) + 'px' );
					headerParent.css( 'padding-top', headerHeight + 'px' );
				}
			};
		}());

		// Previewed device bindings. (The api.previewedDevice property is how this Value was first introduced, but since it has moved to api.state.)
		api.previewedDevice = api.state( 'previewedDevice' );

		// Set the default device.
		api.bind( 'ready', function() {
			_.find( api.settings.previewableDevices, function( value, key ) {
				if ( true === value['default'] ) {
					api.previewedDevice.set( key );
					return true;
				}
			} );
		} );

		// Set the toggled device.
		footerActions.find( '.devices button' ).on( 'click', function( event ) {
			api.previewedDevice.set( $( event.currentTarget ).data( 'device' ) );
		});

		// Bind device changes.
		api.previewedDevice.bind( function( newDevice ) {
			var overlay = $( '.wp-full-overlay' ),
				devices = '';

			footerActions.find( '.devices button' )
				.removeClass( 'active' )
				.attr( 'aria-pressed', false );

			footerActions.find( '.devices .preview-' + newDevice )
				.addClass( 'active' )
				.attr( 'aria-pressed', true );

			$.each( api.settings.previewableDevices, function( device ) {
				devices += ' preview-' + device;
			} );

			overlay
				.removeClass( devices )
				.addClass( 'preview-' + newDevice );
		} );

		// Bind site title display to the corresponding field.
		if ( title.length ) {
			api( 'blogname', function( setting ) {
				var updateTitle = function() {
					title.text( $.trim( setting() ) || api.l10n.untitledBlogName );
				};
				setting.bind( updateTitle );
				updateTitle();
			} );
		}

		/*
		 * Create a postMessage connection with a parent frame,
		 * in case the Customizer frame was opened with the Customize loader.
		 *
		 * @see wp.customize.Loader
		 */
		parent = new api.Messenger({
			url: api.settings.url.parent,
			channel: 'loader'
		});

		// Handle exiting of Customizer.
		(function() {
			var isInsideIframe = false;

			function isCleanState() {
				var defaultChangesetStatus;

				/*
				 * Handle special case of previewing theme switch since some settings (for nav menus and widgets)
				 * are pre-dirty and non-active themes can only ever be auto-drafts.
				 */
				if ( ! api.state( 'activated' ).get() ) {
					return 0 === api._latestRevision;
				}

				// Dirty if the changeset status has been changed but not saved yet.
				defaultChangesetStatus = api.state( 'changesetStatus' ).get();
				if ( '' === defaultChangesetStatus || 'auto-draft' === defaultChangesetStatus ) {
					defaultChangesetStatus = 'publish';
				}
				if ( api.state( 'selectedChangesetStatus' ).get() !== defaultChangesetStatus ) {
					return false;
				}

				// Dirty if scheduled but the changeset date hasn't been saved yet.
				if ( 'future' === api.state( 'selectedChangesetStatus' ).get() && api.state( 'selectedChangesetDate' ).get() !== api.state( 'changesetDate' ).get() ) {
					return false;
				}

				return api.state( 'saved' ).get() && 'auto-draft' !== api.state( 'changesetStatus' ).get();
			}

			/*
			 * If we receive a 'back' event, we're inside an iframe.
			 * Send any clicks to the 'Return' link to the parent page.
			 */
			parent.bind( 'back', function() {
				isInsideIframe = true;
			});

			function startPromptingBeforeUnload() {
				api.unbind( 'change', startPromptingBeforeUnload );
				api.state( 'selectedChangesetStatus' ).unbind( startPromptingBeforeUnload );
				api.state( 'selectedChangesetDate' ).unbind( startPromptingBeforeUnload );

				// Prompt user with AYS dialog if leaving the Customizer with unsaved changes
				$( window ).on( 'beforeunload.customize-confirm', function() {
					if ( ! isCleanState() && ! api.state( 'changesetLocked' ).get() ) {
						setTimeout( function() {
							overlay.removeClass( 'customize-loading' );
						}, 1 );
						return api.l10n.saveAlert;
					}
				});
			}
			api.bind( 'change', startPromptingBeforeUnload );
			api.state( 'selectedChangesetStatus' ).bind( startPromptingBeforeUnload );
			api.state( 'selectedChangesetDate' ).bind( startPromptingBeforeUnload );

			function requestClose() {
				var clearedToClose = $.Deferred(), dismissAutoSave = false, dismissLock = false;

				if ( isCleanState() ) {
					dismissLock = true;
				} else if ( confirm( api.l10n.saveAlert ) ) {

					dismissLock = true;

					// Mark all settings as clean to prevent another call to requestChangesetUpdate.
					api.each( function( setting ) {
						setting._dirty = false;
					});
					$( document ).off( 'visibilitychange.wp-customize-changeset-update' );
					$( window ).off( 'beforeunload.wp-customize-changeset-update' );

					closeBtn.css( 'cursor', 'progress' );
					if ( '' !== api.state( 'changesetStatus' ).get() ) {
						dismissAutoSave = true;
					}
				} else {
					clearedToClose.reject();
				}

				if ( dismissLock || dismissAutoSave ) {
					wp.ajax.send( 'customize_dismiss_autosave_or_lock', {
						timeout: 500, // Don't wait too long.
						data: {
							wp_customize: 'on',
							customize_theme: api.settings.theme.stylesheet,
							customize_changeset_uuid: api.settings.changeset.uuid,
							nonce: api.settings.nonce.dismiss_autosave_or_lock,
							dismiss_autosave: dismissAutoSave,
							dismiss_lock: dismissLock
						}
					} ).always( function() {
						clearedToClose.resolve();
					} );
				}

				return clearedToClose.promise();
			}

			parent.bind( 'confirm-close', function() {
				requestClose().done( function() {
					parent.send( 'confirmed-close', true );
				} ).fail( function() {
					parent.send( 'confirmed-close', false );
				} );
			} );

			closeBtn.on( 'click.customize-controls-close', function( event ) {
				event.preventDefault();
				if ( isInsideIframe ) {
					parent.send( 'close' ); // See confirm-close logic above.
				} else {
					requestClose().done( function() {
						$( window ).off( 'beforeunload.customize-confirm' );
						window.location.href = closeBtn.prop( 'href' );
					} );
				}
			});
		})();

		// Pass events through to the parent.
		$.each( [ 'saved', 'change' ], function ( i, event ) {
			api.bind( event, function() {
				parent.send( event );
			});
		} );

		// Pass titles to the parent
		api.bind( 'title', function( newTitle ) {
			parent.send( 'title', newTitle );
		});

		if ( api.settings.changeset.branching ) {
			parent.send( 'changeset-uuid', api.settings.changeset.uuid );
		}

		// Initialize the connection with the parent frame.
		parent.send( 'ready' );

		// Control visibility for default controls
		$.each({
			'background_image': {
				controls: [ 'background_preset', 'background_position', 'background_size', 'background_repeat', 'background_attachment' ],
				callback: function( to ) { return !! to; }
			},
			'show_on_front': {
				controls: [ 'page_on_front', 'page_for_posts' ],
				callback: function( to ) { return 'page' === to; }
			},
			'header_textcolor': {
				controls: [ 'header_textcolor' ],
				callback: function( to ) { return 'blank' !== to; }
			}
		}, function( settingId, o ) {
			api( settingId, function( setting ) {
				$.each( o.controls, function( i, controlId ) {
					api.control( controlId, function( control ) {
						var visibility = function( to ) {
							control.container.toggle( o.callback( to ) );
						};

						visibility( setting.get() );
						setting.bind( visibility );
					});
				});
			});
		});

		api.control( 'background_preset', function( control ) {
			var visibility, defaultValues, values, toggleVisibility, updateSettings, preset;

			visibility = { // position, size, repeat, attachment
				'default': [ false, false, false, false ],
				'fill': [ true, false, false, false ],
				'fit': [ true, false, true, false ],
				'repeat': [ true, false, false, true ],
				'custom': [ true, true, true, true ]
			};

			defaultValues = [
				_wpCustomizeBackground.defaults['default-position-x'],
				_wpCustomizeBackground.defaults['default-position-y'],
				_wpCustomizeBackground.defaults['default-size'],
				_wpCustomizeBackground.defaults['default-repeat'],
				_wpCustomizeBackground.defaults['default-attachment']
			];

			values = { // position_x, position_y, size, repeat, attachment
				'default': defaultValues,
				'fill': [ 'left', 'top', 'cover', 'no-repeat', 'fixed' ],
				'fit': [ 'left', 'top', 'contain', 'no-repeat', 'fixed' ],
				'repeat': [ 'left', 'top', 'auto', 'repeat', 'scroll' ]
			};

			// @todo These should actually toggle the active state, but without the preview overriding the state in data.activeControls.
			toggleVisibility = function( preset ) {
				_.each( [ 'background_position', 'background_size', 'background_repeat', 'background_attachment' ], function( controlId, i ) {
					var control = api.control( controlId );
					if ( control ) {
						control.container.toggle( visibility[ preset ][ i ] );
					}
				} );
			};

			updateSettings = function( preset ) {
				_.each( [ 'background_position_x', 'background_position_y', 'background_size', 'background_repeat', 'background_attachment' ], function( settingId, i ) {
					var setting = api( settingId );
					if ( setting ) {
						setting.set( values[ preset ][ i ] );
					}
				} );
			};

			preset = control.setting.get();
			toggleVisibility( preset );

			control.setting.bind( 'change', function( preset ) {
				toggleVisibility( preset );
				if ( 'custom' !== preset ) {
					updateSettings( preset );
				}
			} );
		} );

		api.control( 'background_repeat', function( control ) {
			control.elements[0].unsync( api( 'background_repeat' ) );

			control.element = new api.Element( control.container.find( 'input' ) );
			control.element.set( 'no-repeat' !== control.setting() );

			control.element.bind( function( to ) {
				control.setting.set( to ? 'repeat' : 'no-repeat' );
			} );

			control.setting.bind( function( to ) {
				control.element.set( 'no-repeat' !== to );
			} );
		} );

		api.control( 'background_attachment', function( control ) {
			control.elements[0].unsync( api( 'background_attachment' ) );

			control.element = new api.Element( control.container.find( 'input' ) );
			control.element.set( 'fixed' !== control.setting() );

			control.element.bind( function( to ) {
				control.setting.set( to ? 'scroll' : 'fixed' );
			} );

			control.setting.bind( function( to ) {
				control.element.set( 'fixed' !== to );
			} );
		} );

		// Juggle the two controls that use header_textcolor
		api.control( 'display_header_text', function( control ) {
			var last = '';

			control.elements[0].unsync( api( 'header_textcolor' ) );

			control.element = new api.Element( control.container.find('input') );
			control.element.set( 'blank' !== control.setting() );

			control.element.bind( function( to ) {
				if ( ! to ) {
					last = api( 'header_textcolor' ).get();
				}

				control.setting.set( to ? last : 'blank' );
			});

			control.setting.bind( function( to ) {
				control.element.set( 'blank' !== to );
			});
		});

		// Add behaviors to the static front page controls.
		api( 'show_on_front', 'page_on_front', 'page_for_posts', function( showOnFront, pageOnFront, pageForPosts ) {
			var handleChange = function() {
				var setting = this, pageOnFrontId, pageForPostsId, errorCode = 'show_on_front_page_collision';
				pageOnFrontId = parseInt( pageOnFront(), 10 );
				pageForPostsId = parseInt( pageForPosts(), 10 );

				if ( 'page' === showOnFront() ) {

					// Change previewed URL to the homepage when changing the page_on_front.
					if ( setting === pageOnFront && pageOnFrontId > 0 ) {
						api.previewer.previewUrl.set( api.settings.url.home );
					}

					// Change the previewed URL to the selected page when changing the page_for_posts.
					if ( setting === pageForPosts && pageForPostsId > 0 ) {
						api.previewer.previewUrl.set( api.settings.url.home + '?page_id=' + pageForPostsId );
					}
				}

				// Toggle notification when the homepage and posts page are both set and the same.
				if ( 'page' === showOnFront() && pageOnFrontId && pageForPostsId && pageOnFrontId === pageForPostsId ) {
					showOnFront.notifications.add( new api.Notification( errorCode, {
						type: 'error',
						message: api.l10n.pageOnFrontError
					} ) );
				} else {
					showOnFront.notifications.remove( errorCode );
				}
			};
			showOnFront.bind( handleChange );
			pageOnFront.bind( handleChange );
			pageForPosts.bind( handleChange );
			handleChange.call( showOnFront, showOnFront() ); // Make sure initial notification is added after loading existing changeset.

			// Move notifications container to the bottom.
			api.control( 'show_on_front', function( showOnFrontControl ) {
				showOnFrontControl.deferred.embedded.done( function() {
					showOnFrontControl.container.append( showOnFrontControl.getNotificationsContainerElement() );
				});
			});
		});

		// Add code editor for Custom CSS.
		(function() {
			var sectionReady = $.Deferred();

			api.section( 'custom_css', function( section ) {
				section.deferred.embedded.done( function() {
					if ( section.expanded() ) {
						sectionReady.resolve( section );
					} else {
						section.expanded.bind( function( isExpanded ) {
							if ( isExpanded ) {
								sectionReady.resolve( section );
							}
						} );
					}
				});
			});

			// Set up the section description behaviors.
			sectionReady.done( function setupSectionDescription( section ) {
				var control = api.control( 'custom_css' );

				// Hide redundant label for visual users.
				control.container.find( '.customize-control-title:first' ).addClass( 'screen-reader-text' );

				// Close the section description when clicking the close button.
				section.container.find( '.section-description-buttons .section-description-close' ).on( 'click', function() {
					section.container.find( '.section-meta .customize-section-description:first' )
						.removeClass( 'open' )
						.slideUp();

					section.container.find( '.customize-help-toggle' )
						.attr( 'aria-expanded', 'false' )
						.focus(); // Avoid focus loss.
				});

				// Reveal help text if setting is empty.
				if ( control && ! control.setting.get() ) {
					section.container.find( '.section-meta .customize-section-description:first' )
						.addClass( 'open' )
						.show()
						.trigger( 'toggled' );

					section.container.find( '.customize-help-toggle' ).attr( 'aria-expanded', 'true' );
				}
			});
		})();

		// Toggle visibility of Header Video notice when active state change.
		api.control( 'header_video', function( headerVideoControl ) {
			headerVideoControl.deferred.embedded.done( function() {
				var toggleNotice = function() {
					var section = api.section( headerVideoControl.section() ), noticeCode = 'video_header_not_available';
					if ( ! section ) {
						return;
					}
					if ( headerVideoControl.active.get() ) {
						section.notifications.remove( noticeCode );
					} else {
						section.notifications.add( new api.Notification( noticeCode, {
							type: 'info',
							message: api.l10n.videoHeaderNotice
						} ) );
					}
				};
				toggleNotice();
				headerVideoControl.active.bind( toggleNotice );
			} );
		} );

		// Update the setting validities.
		api.previewer.bind( 'selective-refresh-setting-validities', function handleSelectiveRefreshedSettingValidities( settingValidities ) {
			api._handleSettingValidities( {
				settingValidities: settingValidities,
				focusInvalidControl: false
			} );
		} );

		// Focus on the control that is associated with the given setting.
		api.previewer.bind( 'focus-control-for-setting', function( settingId ) {
			var matchedControls = [];
			api.control.each( function( control ) {
				var settingIds = _.pluck( control.settings, 'id' );
				if ( -1 !== _.indexOf( settingIds, settingId ) ) {
					matchedControls.push( control );
				}
			} );

			// Focus on the matched control with the lowest priority (appearing higher).
			if ( matchedControls.length ) {
				matchedControls.sort( function( a, b ) {
					return a.priority() - b.priority();
				} );
				matchedControls[0].focus();
			}
		} );

		// Refresh the preview when it requests.
		api.previewer.bind( 'refresh', function() {
			api.previewer.refresh();
		});

		// Update the edit shortcut visibility state.
		api.state( 'paneVisible' ).bind( function( isPaneVisible ) {
			var isMobileScreen;
			if ( window.matchMedia ) {
				isMobileScreen = window.matchMedia( 'screen and ( max-width: 640px )' ).matches;
			} else {
				isMobileScreen = $( window ).width() <= 640;
			}
			api.state( 'editShortcutVisibility' ).set( isPaneVisible || isMobileScreen ? 'visible' : 'hidden' );
		} );
		if ( window.matchMedia ) {
			window.matchMedia( 'screen and ( max-width: 640px )' ).addListener( function() {
				var state = api.state( 'paneVisible' );
				state.callbacks.fireWith( state, [ state.get(), state.get() ] );
			} );
		}
		api.previewer.bind( 'edit-shortcut-visibility', function( visibility ) {
			api.state( 'editShortcutVisibility' ).set( visibility );
		} );
		api.state( 'editShortcutVisibility' ).bind( function( visibility ) {
			api.previewer.send( 'edit-shortcut-visibility', visibility );
		} );

		/**
		 * Start the auto-saving.
		 *
		 * @return {void}
		 */
		function startAutosaving() {
			var timeoutId, updateChangesetWithReschedule, scheduleChangesetUpdate, updatePending = false;

			api.unbind( 'change', startAutosaving ); // Ensure startAutosaving only fires once.

			function onChangeSaved( isSaved ) {
				if ( ! isSaved && ! api.settings.changeset.autosaved ) {
					api.settings.changeset.autosaved = true; // Once a change is made then autosaving kicks in.
					api.previewer.send( 'autosaving' );
				}
			}
			api.state( 'saved' ).bind( onChangeSaved );
			onChangeSaved( api.state( 'saved' ).get() );

			/**
			 * Request a changeset update and then re-schedule the next changeset update time.
			 *
			 * @since 4.7.0
			 * @private
			 */
			updateChangesetWithReschedule = function() {
				if ( ! updatePending ) {
					updatePending = true;
					api.requestChangesetUpdate( {}, { autosave: true } ).always( function() {
						updatePending = false;
					} );
				}
				scheduleChangesetUpdate();
			};

			/**
			 * Schedule the changeset update.
			 *
			 * @since 4.7.0
			 * @private
			 */
			scheduleChangesetUpdate = function() {
				clearTimeout( timeoutId );
				timeoutId = setTimeout( function() {
					updateChangesetWithReschedule();
				}, api.settings.timeouts.changesetAutoSave );
			};

			// Start auto-save interval for updating changeset.
			scheduleChangesetUpdate();

			// Save changeset when focus removed from window.
			$( document ).on( 'visibilitychange.wp-customize-changeset-update', function() {
				if ( document.hidden ) {
					updateChangesetWithReschedule();
				}
			} );

			// Save changeset before unloading window.
			$( window ).on( 'beforeunload.wp-customize-changeset-update', function() {
				updateChangesetWithReschedule();
			} );
		}
		api.bind( 'change', startAutosaving );

		// Make sure TinyMCE dialogs appear above Customizer UI.
		$( document ).one( 'tinymce-editor-setup', function() {
			if ( window.tinymce.ui.FloatPanel && ( ! window.tinymce.ui.FloatPanel.zIndex || window.tinymce.ui.FloatPanel.zIndex < 500001 ) ) {
				window.tinymce.ui.FloatPanel.zIndex = 500001;
			}
		} );

		body.addClass( 'ready' );
		api.trigger( 'ready' );
	});

})( wp, jQuery );
