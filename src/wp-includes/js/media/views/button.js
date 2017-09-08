/**
 * @file Defines the wp.media.view.Button class.
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
	 * Creates a new button view.
	 *
	 * @constructs wp.media.view.Button
	 *
	 * @augments wp.media.View
	 *
	 * @param {Object}   options          The options to initialize this view's model with.
	 * @param {string}   options.text     The text content of this button.
	 * @param {string}   options.style    If present the class `button-{style}` will be added to this button.
	 * @param {string}   options.size     If present the class `button-{size}` will be added to this button.
	 * @param {boolean}  options.disabled If true the disabled attribute is added to this button.
	 * @param {function} options.click    The callback to execute on a click event.
	 */
	initialize: function() {
		/**
		 * Represents the state of this button.
		 * @type     {Backbone.Model}
		 * @property {string} text      The text content of this button.
		 * @property {string} style     If present the class `button-{style}` will be added to this button.
		 * @property {string} size      If present the class `button-{size}` will be added to this button.
		 * @property {boolean} disabled If true the disabled attribute is added to this button.
		 */
		this.model = new Backbone.Model( this.defaults );

		/*
		 * If any of the `options` have a key from `defaults`, apply its
		 * value to the `model` and remove it from the `options object.
		 */
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
	 * Renders the button, adding classes as passed to the constructor.
	 * Will always add the `button` class.
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
	 * Executes the click callback passed to the constructor.
	 *
	 * @param {Object} event
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
