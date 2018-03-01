/* jshint loopfunc: true */
// use jQuery and hoverIntent if loaded
if ( typeof(jQuery) != 'undefined' ) {
	if ( typeof(jQuery.fn.hoverIntent) == 'undefined' ) {
		/* jshint ignore:start */
		// hoverIntent v1.8.1 - Copy of wp-includes/js/hoverIntent.min.js
		!function(a){a.fn.hoverIntent=function(b,c,d){var e={interval:100,sensitivity:6,timeout:0};e="object"==typeof b?a.extend(e,b):a.isFunction(c)?a.extend(e,{over:b,out:c,selector:d}):a.extend(e,{over:b,out:b,selector:c});var f,g,h,i,j=function(a){f=a.pageX,g=a.pageY},k=function(b,c){return c.hoverIntent_t=clearTimeout(c.hoverIntent_t),Math.sqrt((h-f)*(h-f)+(i-g)*(i-g))<e.sensitivity?(a(c).off("mousemove.hoverIntent",j),c.hoverIntent_s=!0,e.over.apply(c,[b])):(h=f,i=g,c.hoverIntent_t=setTimeout(function(){k(b,c)},e.interval),void 0)},l=function(a,b){return b.hoverIntent_t=clearTimeout(b.hoverIntent_t),b.hoverIntent_s=!1,e.out.apply(b,[a])},m=function(b){var c=a.extend({},b),d=this;d.hoverIntent_t&&(d.hoverIntent_t=clearTimeout(d.hoverIntent_t)),"mouseenter"===b.type?(h=c.pageX,i=c.pageY,a(d).on("mousemove.hoverIntent",j),d.hoverIntent_s||(d.hoverIntent_t=setTimeout(function(){k(c,d)},e.interval))):(a(d).off("mousemove.hoverIntent",j),d.hoverIntent_s&&(d.hoverIntent_t=setTimeout(function(){l(c,d)},e.timeout)))};return this.on({"mouseenter.hoverIntent":m,"mouseleave.hoverIntent":m},e.selector)}}(jQuery);
		/* jshint ignore:end */
	}
	jQuery(document).ready(function($){
		var adminbar = $('#wpadminbar'), refresh, touchOpen, touchClose, disableHoverIntent = false;

		/**
		 * @summary Forces the browser to refresh the tabbing index.
		 *
		 * @since 3.5.0
		 *
		 * @param  {integer} i The index of the element.
		 * @param {HTMLelement} el  The HTML element to remove the tab index from.
		 * @returns {void}
		 */
		refresh = function(i, el){ // force the browser to refresh the tabbing index
			var node = $(el), tab = node.attr('tabindex');
			if ( tab )
				node.attr('tabindex', '0').attr('tabindex', tab);
		};

		/**
		 * @summary Adds or removes the hover class on touch.
		 *
		 * @since 3.5.0
		 *
		 * @param {bool} unbind if true removes the wp-mobile-hover class.
		 *
		 * @returns {void}
		 */
		touchOpen = function(unbind) {
			adminbar.find('li.menupop').on('click.wp-mobile-hover', function(e) {
				var el = $(this);

				if ( el.parent().is('#wp-admin-bar-root-default') && !el.hasClass('hover') ) {
					e.preventDefault();
					adminbar.find('li.menupop.hover').removeClass('hover');
					el.addClass('hover');
				} else if ( !el.hasClass('hover') ) {
					e.stopPropagation();
					e.preventDefault();
					el.addClass('hover');
				} else if ( ! $( e.target ).closest( 'div' ).hasClass( 'ab-sub-wrapper' ) ) {
					// We're dealing with an already-touch-opened menu genericon (we know el.hasClass('hover')),
					// so close it on a second tap and prevent propag and defaults. See #29906
					e.stopPropagation();
					e.preventDefault();
					el.removeClass('hover');
				}

				if ( unbind ) {
					$('li.menupop').off('click.wp-mobile-hover');
					disableHoverIntent = false;
				}
			});
		};

		/**
		 * @summary Removes the hover class if clicked or touched outside the adminbar.
		 *
		 * @since 3.5.0
		 *
		 * @returns {void}
		 */
		touchClose = function() {
			var mobileEvent = /Mobile\/.+Safari/.test(navigator.userAgent) ? 'touchstart' : 'click';
			// close any open drop-downs when the click/touch is not on the toolbar
			$(document.body).on( mobileEvent+'.wp-mobile-hover', function(e) {
				if ( !$(e.target).closest('#wpadminbar').length )
					adminbar.find('li.menupop.hover').removeClass('hover');
			});
		};

		adminbar.removeClass('nojq').removeClass('nojs');

		// If clicked on the adminbar add the hoverclass, if clicked outside it remove it.
		if ( 'ontouchstart' in window ) {
			adminbar.on('touchstart', function(){
				touchOpen(true);
				disableHoverIntent = true;
			});
			touchClose();
		} else if ( /IEMobile\/[1-9]/.test(navigator.userAgent) ) {
			touchOpen();
			touchClose();
		}

		//Adds or remove the hover class based on the hover intent.
		adminbar.find('li.menupop').hoverIntent({
			over: function() {
				if ( disableHoverIntent )
					return;

				$(this).addClass('hover');
			},
			out: function() {
				if ( disableHoverIntent )
					return;

				$(this).removeClass('hover');
			},
			timeout: 180,
			sensitivity: 7,
			interval: 100
		});

		//Prevents the toolbar from covering up content when a hash is present in the URL.
		if ( window.location.hash )
			window.scrollBy( 0, -32 );

		/**
		 * @summary Adds selected class to shortlink in admin bar.
		 *
		 * Removes the selected class from the parents of the selected shortlink-input.
		 *
		 * @since 3.5.0
		 *
		 * @param {event} e The click event.
		 *
		 * @returns {void}
		 **/
		$('#wp-admin-bar-get-shortlink').click(function(e){
			e.preventDefault();
			$(this).addClass('selected').children('.shortlink-input').blur(function(){
				$(this).parents('#wp-admin-bar-get-shortlink').removeClass('selected');
			}).focus().select();
		});

		/**
		 * @summary It removes the hoverclass if the enter key is pressed.
		 *
		 * Makes sure the tab index is refreshed by refreshing each ab-item
		 * and its children.
		 *
		 * @since 3.5.0
		 *
		 * @param {event} e The keydown event.
		 *
		 * @returns {void}
		 */
		$('#wpadminbar li.menupop > .ab-item').bind('keydown.adminbar', function(e){
			// key code 13 is enter.
			if ( e.which != 13 )
				return;

			var target = $(e.target),
				wrap = target.closest('.ab-sub-wrapper'),
				parentHasHover = target.parent().hasClass('hover');

			e.stopPropagation();
			e.preventDefault();

			if ( !wrap.length )
				wrap = $('#wpadminbar .quicklinks');

			wrap.find('.menupop').removeClass('hover');

			if ( ! parentHasHover ) {
				target.parent().toggleClass('hover');
			}

			target.siblings('.ab-sub-wrapper').find('.ab-item').each(refresh);
		}).each(refresh);

		/**
		 * @summary Removes the hover class when escape is pressed.
		 *
		 * Makes sure the tab index is refreshed by refreshing each ab-item
		 * and its children.
		 *
		 * @since 3.5.0
		 *
		 * @param {event} e The keydown event.
		 *
		 * @returns {void}
		 */
		$('#wpadminbar .ab-item').bind('keydown.adminbar', function(e){
			// key code 27 is escape.
			if ( e.which != 27 )
				return;

			var target = $(e.target);

			e.stopPropagation();
			e.preventDefault();

			target.closest('.hover').removeClass('hover').children('.ab-item').focus();
			target.siblings('.ab-sub-wrapper').find('.ab-item').each(refresh);
		});

		/**
		 * @summary Scrolls to top of page by clicking the adminbar.
		 *
		 * @since 4.3.0
		 *
		 * @param {event} e The click event.
		 *
		 * @returns {void}
		 */
		adminbar.click( function(e) {
			if ( e.target.id != 'wpadminbar' && e.target.id != 'wp-admin-bar-top-secondary' ) {
				return;
			}

			adminbar.find( 'li.menupop.hover' ).removeClass( 'hover' );
			$( 'html, body' ).animate( { scrollTop: 0 }, 'fast' );
			e.preventDefault();
		});

		/**
		 * @summary Sets the focus on an element with a href attribute.
		 *
		 * The timeout is used to fix a focus bug in WebKit.
		 *
		 * @since 3.5.0
		 *
		 * @param {event} e The keydown event.
		 *
		 * @returns {void}
		 */
		$('.screen-reader-shortcut').keydown( function(e) {
			var id, ua;

			if ( 13 != e.which )
				return;

			id = $( this ).attr( 'href' );

			ua = navigator.userAgent.toLowerCase();

			if ( ua.indexOf('applewebkit') != -1 && id && id.charAt(0) == '#' ) {
				setTimeout(function () {
					$(id).focus();
				}, 100);
			}
		});

		$( '#adminbar-search' ).on({
			/**
			 * @summary Adds the adminbar-focused class on focus.
			 *
			 * @since
			 *
			 * @returns {void}
			 */
			focus: function() {
				$( '#adminbarsearch' ).addClass( 'adminbar-focused' );
			/**
			 * @summary Remove the adminbar-focused class on blur.
			 *
			 * @since
			 *
			 * @returns {void}
			 */
			}, blur: function() {
				$( '#adminbarsearch' ).removeClass( 'adminbar-focused' );
			}
		} );

		if ( 'sessionStorage' in window ) {
            /**
			 * @summary Empties sessionStorage on logging out
			 *
			 * @since
			 *
			 * @returns {void}
             */
			$('#wp-admin-bar-logout a').click( function() {
				try {
					for ( var key in sessionStorage ) {
						if ( key.indexOf('wp-autosave-') != -1 )
							sessionStorage.removeItem(key);
					}
				} catch(e) {}
			});
		}

		if ( navigator.userAgent && document.body.className.indexOf( 'no-font-face' ) === -1 &&
			/Android (1.0|1.1|1.5|1.6|2.0|2.1)|Nokia|Opera Mini|w(eb)?OSBrowser|webOS|UCWEB|Windows Phone OS 7|XBLWP7|ZuneWP7|MSIE 7/.test( navigator.userAgent ) ) {

			document.body.className += ' no-font-face';
		}
	});
} else {
    /**
	 * @summary Wrapper function for the adminbar.
	 * Used if jQuery isn't available.
	 *
	 * @since 3.5.0
	 *
	 * @param {object} d The document object.
	 * @param {object} w The window object.
	 *
	 * @returns {void}
     */
	(function(d, w) {
        /**
		 * @summary Adds an event listener to an object.
		 *
		 * @since 3.5.0
		 *
         * @param {ojbect} obj The object to add the event listener to.
         * @param {string} type The type of event
         * @param {function} fn The function to bind to the event listener.
		 *
		 * @returns {void}
         */
		var addEvent = function( obj, type, fn ) {
			if ( obj.addEventListener )
				obj.addEventListener(type, fn, false);
			else if ( obj.attachEvent )
				obj.attachEvent('on' + type, function() { return fn.call(obj, window.event);});
		},

		aB, hc = new RegExp('\\bhover\\b', 'g'), q = [],
		rselected = new RegExp('\\bselected\\b', 'g'),

	/**
		 * @summary Gets the timeout ID of the given element
		 *
		 * @since 3.5.0
		 *
		 * @param {HTMLelement} el the element
		 * @returns {*}
		 */
		getTOID = function(el) {
			var i = q.length;
			while ( i-- ) {
				if ( q[i] && el == q[i][1] )
					return q[i][0];
			}
			return false;
		},

		/**
		 * @summary Adds the hoverclass to menu items.
		 *
		 * @since 3.5.0
		 *
		 * @param {HTMLelement} t the element
		 * @returns {void}
		 */
		addHoverClass = function(t) {
			var i, id, inA, hovering, ul, li,
				ancestors = [],
				ancestorLength = 0;

			// aB is adminbar. d is document.
			while ( t && t != aB && t != d ) {
				if ( 'LI' == t.nodeName.toUpperCase() ) {
					ancestors[ ancestors.length ] = t;
					id = getTOID(t);
					if ( id )
						clearTimeout( id );
					t.className = t.className ? ( t.className.replace(hc, '') + ' hover' ) : 'hover';
					hovering = t;
				}
				t = t.parentNode;
			}

			// Removes any selected classes.
			if ( hovering && hovering.parentNode ) {
				ul = hovering.parentNode;
				if ( ul && 'UL' == ul.nodeName.toUpperCase() ) {
					i = ul.childNodes.length;
					while ( i-- ) {
						li = ul.childNodes[i];
						if ( li != hovering )
							li.className = li.className ? li.className.replace( rselected, '' ) : '';
					}
				}
			}

			// Removes the hover class for any objects not in the immediate element's ancestry.
			i = q.length;
			while ( i-- ) {
				inA = false;
				ancestorLength = ancestors.length;
				while( ancestorLength-- ) {
					if ( ancestors[ ancestorLength ] == q[i][1] )
						inA = true;
				}

				if ( ! inA )
					q[i][1].className = q[i][1].className ? q[i][1].className.replace(hc, '') : '';
			}
		},

			/**
			 * @summary Removes the hoverclass from menu items.
			 *
			 * @since 3.5.0
			 *
			 * @param {HTMLelement} t the element
			 * @returns {void}
			 */
		removeHoverClass = function(t) {
			while ( t && t != aB && t != d ) {
				if ( 'LI' == t.nodeName.toUpperCase() ) {
					(function(t) {
						var to = setTimeout(function() {
							t.className = t.className ? t.className.replace(hc, '') : '';
						}, 500);
						q[q.length] = [to, t];
					})(t);
				}
				t = t.parentNode;
			}
		},

		/**
		 * @summary Handles the click on a shortlink in the adminbar.
		 *
		 * @since 3.5.0
		 *
		 * @param {HTMLelement} t the element
		 * @returns {boolean}
		 */
		clickShortlink = function(e) {
			var i, l, node,
				t = e.target || e.srcElement;

			// Make t the shortlink menu item, or return.
			while ( true ) {
				// Check if we've gone past the shortlink node,
				// or if the user is clicking on the input.
				if ( ! t || t == d || t == aB )
					return;
				// Check if we've found the shortlink node.
				if ( t.id && t.id == 'wp-admin-bar-get-shortlink' )
					break;
				t = t.parentNode;
			}

			// IE doesn't support preventDefault, and does support returnValue
			if ( e.preventDefault )
				e.preventDefault();
			e.returnValue = false;

			if ( -1 == t.className.indexOf('selected') )
				t.className += ' selected';

			for ( i = 0, l = t.childNodes.length; i < l; i++ ) {
				node = t.childNodes[i];
				if ( node.className && -1 != node.className.indexOf('shortlink-input') ) {
					node.focus();
					node.select();
					node.onblur = function() {
						t.className = t.className ? t.className.replace( rselected, '' ) : '';
					};
					break;
				}
			}
			return false;
		},

		/**
		 * @summary Scrolls to the top of the page.
		 *
		 * @since 3.5.0
		 *
		 * @param {HTMLelement} t the element
		 * @returns {void}
		 */
		scrollToTop = function(t) {
			var distance, speed, step, steps, timer, speed_step;

			// Ensure that the #wpadminbar was the target of the click.
			if ( t.id != 'wpadminbar' && t.id != 'wp-admin-bar-top-secondary' )
				return;

			distance    = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;

			if ( distance < 1 )
				return;

			speed_step = distance > 800 ? 130 : 100;
			speed     = Math.min( 12, Math.round( distance / speed_step ) );
			step      = distance > 800 ? Math.round( distance / 30  ) : Math.round( distance / 20  );
			steps     = [];
			timer     = 0;

			// Animate scrolling to the top of the page by generating steps to
			// the top of the page and shifting to each step at a set interval.
			while ( distance ) {
				distance -= step;
				if ( distance < 0 )
					distance = 0;
				steps.push( distance );

				setTimeout( function() {
					window.scrollTo( 0, steps.shift() );
				}, timer * speed );

				timer++;
			}
		};

		addEvent(w, 'load', function() {
			aB = d.getElementById('wpadminbar');

			if ( d.body && aB ) {
				d.body.appendChild( aB );

				if ( aB.className )
					aB.className = aB.className.replace(/nojs/, '');

				addEvent(aB, 'mouseover', function(e) {
					addHoverClass( e.target || e.srcElement );
				});

				addEvent(aB, 'mouseout', function(e) {
					removeHoverClass( e.target || e.srcElement );
				});

				addEvent(aB, 'click', clickShortlink );

				addEvent(aB, 'click', function(e) {
					scrollToTop( e.target || e.srcElement );
				});

				addEvent( document.getElementById('wp-admin-bar-logout'), 'click', function() {
					if ( 'sessionStorage' in window ) {
						try {
							for ( var key in sessionStorage ) {
								if ( key.indexOf('wp-autosave-') != -1 )
									sessionStorage.removeItem(key);
							}
						} catch(e) {}
					}
				});
			}

			if ( w.location.hash )
				w.scrollBy(0,-32);

			if ( navigator.userAgent && document.body.className.indexOf( 'no-font-face' ) === -1 &&
				/Android (1.0|1.1|1.5|1.6|2.0|2.1)|Nokia|Opera Mini|w(eb)?OSBrowser|webOS|UCWEB|Windows Phone OS 7|XBLWP7|ZuneWP7|MSIE 7/.test( navigator.userAgent ) ) {

				document.body.className += ' no-font-face';
			}
		});
	})(document, window);

}
