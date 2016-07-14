/**
 * @namespace wp.utils
 */
( function() {
	/**
	 * Word counting object
	 * 
	 * @namespace wp.utils.wordcounter
	 * @memberof wp.utils
	 *
	 * @static
	 *
	 * @constructs
	 * 
	 * @param {object} [settings] - Key-value object containing overrides for settings.
	 * @param {RegExp} [settings.HTMLRegExp] - Find HTML elements.
	 * @param {RegExp} [settings.HTMLcommentRegExp] - Find HTML comments.
	 * @param {RegExp} [settings.spaceRegExp] - Find non-breaking space.
	 * @param {RegExp} [settings.HTMLEntityRegExp] - Find ampersant HTML code.
	 * @param {RegExp} [settings.connectorRegExp] - Double dash or 'em-dash'
	 * @param {RegExp} [settings.removeRegExp] - Remove unwanted characters to reduce false-positives.
	 * @param {RegExp} [settings.astralRegExp] - Remove astral planes.
	 * @param {RegExp} [settings.wordsRegExp] - Find words by spaces.
	 * @param {RegExp} [settings.characters_excluding_spacesRegExp] - Find non-spaces.
	 * @param {RegExp} [settings.characters_including_spacesRegExp] - Find characters including spaces.
	 * @param {object} [settings.l10n] - Localization object containing specific configuration for the current localization.
	 * @param {string} [settings.l10n.type] - Method of finding words to count.
	 * @param {array}  [settings.l10n.shortcodes] - Array of shortcodes that contain text to be counted.
	 */
	function WordCounter( settings ) {
		var key,
			shortcodes;

		// Apply provided settings to object settings.
		if ( settings ) {
			for ( key in settings ) {
				// Only apply valid settings.
				if ( settings.hasOwnProperty( key ) ) {
					this.settings[ key ] = settings[ key ];
				}
			}
		}

		shortcodes = this.settings.l10n.shortcodes;

		// If there are any localization shortcodes add this as type in the settings.
		if ( shortcodes && shortcodes.length ) {
			this.settings.shortcodesRegExp = new RegExp( '\\[\\/?(?:' + shortcodes.join( '|' ) + ')[^\\]]*?\\]', 'g' );
		}
	}

	/**
	 * @memberof wp.utils.wordcounter
	 * @type {{HTMLRegExp: RegExp, HTMLcommentRegExp: RegExp, spaceRegExp: RegExp, HTMLEntityRegExp: RegExp, connectorRegExp: RegExp, removeRegExp: RegExp, astralRegExp: RegExp, wordsRegExp: RegExp, characters_excluding_spacesRegExp: RegExp, characters_including_spacesRegExp: RegExp, l10n: (*|{})}}
	 */
	WordCounter.prototype.settings = {
		HTMLRegExp: /<\/?[a-z][^>]*?>/gi,
		HTMLcommentRegExp: /<!--[\s\S]*?-->/g,
		spaceRegExp: /&nbsp;|&#160;/gi,
		HTMLEntityRegExp: /&\S+?;/g,
		connectorRegExp: /--|\u2014/g, // \u2014 = em-dash.
		removeRegExp: new RegExp( [
			'[',
				// Basic Latin (extract)
				'\u0021-\u0040\u005B-\u0060\u007B-\u007E',
				// Latin-1 Supplement (extract)
				'\u0080-\u00BF\u00D7\u00F7',
				// General Punctuation
				// Superscripts and Subscripts
				// Currency Symbols
				// Combining Diacritical Marks for Symbols
				// Letterlike Symbols
				// Number Forms
				// Arrows
				// Mathematical Operators
				// Miscellaneous Technical
				// Control Pictures
				// Optical Character Recognition
				// Enclosed Alphanumerics
				// Box Drawing
				// Block Elements
				// Geometric Shapes
				// Miscellaneous Symbols
				// Dingbats
				// Miscellaneous Mathematical Symbols-A
				// Supplemental Arrows-A
				// Braille Patterns
				// Supplemental Arrows-B
				// Miscellaneous Mathematical Symbols-B
				// Supplemental Mathematical Operators
				// Miscellaneous Symbols and Arrows
				'\u2000-\u2BFF',
				// Supplemental Punctuation
				'\u2E00-\u2E7F',
			']'
		].join( '' ), 'g' ),
		astralRegExp: /[\uD800-\uDBFF][\uDC00-\uDFFF]/g,
		wordsRegExp: /\S\s+/g,
		characters_excluding_spacesRegExp: /\S/g,
		characters_including_spacesRegExp: /[^\f\n\r\t\v\u00AD\u2028\u2029]/g,
		l10n: window.wordCountL10n || {}
	};

	/**
	 * Count words
	 *
	 * @param {string} text - Text to count words in.
	 * @param {string} [type] - Override type to use.
	 * @returns {number}
	 *
	 * @memberof wp.utils.wordcounter
	 */
	WordCounter.prototype.count = function( text, type ) {
		var count = 0;

		type = type || this.settings.l10n.type;

		if ( type !== 'characters_excluding_spaces' && type !== 'characters_including_spaces' ) {
			type = 'words';
		}

		if ( text ) {
			text = text + '\n';

			text = text.replace( this.settings.HTMLRegExp, '\n' );
			text = text.replace( this.settings.HTMLcommentRegExp, '' );

			if ( this.settings.shortcodesRegExp ) {
				text = text.replace( this.settings.shortcodesRegExp, '\n' );
			}

			text = text.replace( this.settings.spaceRegExp, ' ' );

			if ( type === 'words' ) {
				text = text.replace( this.settings.HTMLEntityRegExp, '' );
				text = text.replace( this.settings.connectorRegExp, ' ' );
				text = text.replace( this.settings.removeRegExp, '' );
			} else {
				text = text.replace( this.settings.HTMLEntityRegExp, 'a' );
				text = text.replace( this.settings.astralRegExp, 'a' );
			}

			text = text.match( this.settings[ type + 'RegExp' ] );

			if ( text ) {
				count = text.length;
			}
		}

		return count;
	};

	window.wp = window.wp || {};
	window.wp.utils = window.wp.utils || {};
	window.wp.utils.WordCounter = WordCounter;
} )();
