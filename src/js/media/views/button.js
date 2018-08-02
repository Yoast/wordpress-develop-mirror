/**
 * wp.media.view.Button
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 *
 * @param {Object}	[attributes]
 * @param {string}	[attribute.tagName=button]
 * @param {string}	[attributes.className=media-button]
 * @param {Object}	[attributes.attributes=type:button]
 * @param {Object}	[attributes.events=click:click]
 *
 * @memberOf wp.media.view
 */
var Button = wp.media.View.extend(/** @lends wp.media.view.Button.prototype */{
	tagName:    'button',
	className:  'media-button',
	attributes: { type: 'button' },

	events: {
		'click': 'click'
	},

	defaults: {
		text:     '',
		style:    '',
		size:     'large',
		disabled: false
	},

	/**
	 * Create a model with the provided `defaults`.
	 *
	 * @member {Backbone.Model}
	 *
	 * @returns {void}
	 */
	initialize: function() {
		this.model = new Backbone.Model( this.defaults );

		// If any of the `options` have a key from `defaults`, apply its
		// value to the `model` and remove it from the `options object.
		_.each( this.defaults, function( def, key ) {
			var value = this.options[ key ];
			if ( _.isUndefined( value ) ) {
				return;
			}

			this.model.set( key, value );
			delete this.options[ key ];
		}, this );

		this.listenTo( this.model, 'change', this.render );
	},
	/**
	 * Renders the button
	 *
	 * @returns {wp.media.view.Button} Returns itself to allow chaining
	 */
	render: function() {
		var classes = [ 'button', this.className ],
			model = this.model.toJSON();

		if ( model.style ) {
			classes.push( 'button-' + model.style );
		}

		if ( model.size ) {
			classes.push( 'button-' + model.size );
		}

		classes = _.uniq( classes.concat( this.options.classes ) );
		this.el.className = classes.join(' ');

		this.$el.attr( 'disabled', model.disabled );
		this.$el.text( this.model.get('text') );

		return this;
	},
	/**
	 * Applies any arguments to the options as long as the button is not disabled.
	 *
	 * @param {Object} event The click event
	 *
	 * @returns {void}
	 */
	click: function( event ) {
		if ( '#' === this.attributes.href ) {
			event.preventDefault();
		}

		if ( this.options.click && ! this.model.get('disabled') ) {
			this.options.click.apply( this, arguments );
		}
	}
});

module.exports = Button;
