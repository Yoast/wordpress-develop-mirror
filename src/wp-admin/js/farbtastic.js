/*!
 * Farbtastic: jQuery color picker plug-in v1.3u
 *
 * Licensed under the GPL license:
 *   http://www.gnu.org/licenses/gpl.html
 */
(function($) {

	/**
	 * Extends jQuery by adding Farbtastic.
	 *
	 * @param {Object} options Options to be used by Farbtastic.
	 *
	 * @returns {jQuery} extended jQuery instance.
	 */
	$.fn.farbtastic = function (options) {
	  $.farbtastic(this, options);
	  return this;
	};

	/**
	 * Wraps the passed container element and returns a Farbtastic object.
	 *
	 * @param {jQuery|String} container The placeholder element.
	 * @param {HTMLElement|jQuery|function} callback Links the color picker to the selected elements.
	 * In the case that a function is passed, this function will be called whenever a user picks a new color.
	 *
	 * @returns {jQuery._farbtastic} An instance of the Farbtastic object.
	 */
	$.farbtastic = function (container, callback) {
	  var container = $(container).get(0);
	  return container.farbtastic || (container.farbtastic = new $._farbtastic(container, callback));
	};

	/**
	 * Implements the necessary functions for Farbtastic to function properly.
	 *
	 * @param {jQuery|String} container The placeholder element.
	 * @param {HTMLElement|jQuery|function} callback Links the color picker to the selected elements.
	 * In the case that a function is passed, this function will be called whenever a user picks a new color.
	 * @private
	 */
	$._farbtastic = function (container, callback) {
		// Store a reference to the Farbtastic object.
		var fb = this;

		// Insert the necessary HTML to display the color picker.
		$(container).html('<div class="farbtastic"><div class="color"></div><div class="wheel"></div><div class="overlay"></div><div class="h-marker marker"></div><div class="sl-marker marker"></div></div>');
		var e = $('.farbtastic', container);
		fb.wheel = $('.wheel', container).get(0);
		// Set the dimensions of the color picker.
		fb.radius = 84;
		fb.square = 100;
		fb.width = 194;

		// Fix background PNGs in IE6.
		if (navigator.appVersion.match(/MSIE [0-6]\./)) {
		  $('*', e).each(function () {
			if (this.currentStyle.backgroundImage != 'none') {
			  var image = this.currentStyle.backgroundImage;

			  // Capture the background image URL.
			  image = this.currentStyle.backgroundImage.substring(5, image.length - 2);
			  $(this).css({
				'backgroundImage': 'none',
				'filter': "progid:DXImageTransform.Microsoft.AlphaImageLoader(enabled=true, sizingMethod=crop, src='" + image + "')"
			  });
			}
		  });
		}

		/**
		 * @summary Binds the proper function or property based on the callback type.
		 *
		 * If the callback is an element, it binds the updateValue function to the element.
		 * If the callback is a function, it sets the callback function to the Farbtastic instance.
		 *
		 * @param {HTMLElement|jQuery|function} callback Links the color picker to the selected elements.
		 *
		 * @returns {jQuery._farbtastic} The Farbtastic instance.
		 */
		fb.linkTo = function (callback) {
		  // Unbind previous nodes
		  if (typeof fb.callback == 'object') {
			$(fb.callback).unbind('keyup', fb.updateValue);
		  }

		  // Reset color
		  fb.color = null;

		  // Bind callback or elements
		  if (typeof callback == 'function') {
			fb.callback = callback;
		  }
		  else if (typeof callback == 'object' || typeof callback == 'string') {
			fb.callback = $(callback);
			fb.callback.bind('keyup', fb.updateValue);
			if (fb.callback.get(0).value) {
			  fb.setColor(fb.callback.get(0).value);
			}
		  }
		  return this;
		};

		/**
		 * Updates the color if it is different than the currently set color.
		 *
		 * @param {Event} event The event that triggered ths function.
		 *
		 * @returns {void}
		 */
		fb.updateValue = function (event) {
		  if (this.value && this.value != fb.color) {
			fb.setColor(this.value);
		  }
		};

		/**
		 * Changes the display's color based on the passed hex color code.
		 *
		 * @param {string} color The hex color code to apply.
		 *
		 * @returns {jQuery._farbtastic} The Farbtastic instance.
		 */
		fb.setColor = function (color) {
		var unpack = fb.unpack(color);
		if (fb.color != color && unpack) {
		  fb.color = color;
		  fb.rgb = unpack;
		  fb.hsl = fb.RGBToHSL(fb.rgb);
		  fb.updateDisplay();
		}
		return this;
	  };

		/**
		 * Changes the display's color based on the passed HSL color code.
		 *
		 * @param {Array} hsl The HSL color code to apply.
		 * @returns {jQuery._farbtastic} The Farbtastic instance.
		 */
		fb.setHSL = function (hsl) {
			fb.hsl = hsl;
			fb.rgb = fb.HSLToRGB(hsl);
			fb.color = fb.pack(fb.rgb);
			fb.updateDisplay();
			return this;
		};

		/**
		 * Retrieves the coordinates of the given event relative to the center of the widget.
		 *
		 * @param {Event} event The event to retrieve the coordinates from.
		 *
		 * @returns {Object} An object containing the x and y coordinates of the widget.
		 */
	  fb.widgetCoords = function (event) {
		var offset = $(fb.wheel).offset();
		return { x: (event.pageX - offset.left) - fb.width / 2, y: (event.pageY - offset.top) - fb.width / 2 };
	  };

		/**
		 * Binds the applicable events to the widget color selector.
		 *
		 * @param {Event} event The mousedown event to process.
		 *
		 * @returns {boolean} Always returns false.
		 */
	  fb.mousedown = function (event) {
		// Capture mouse.
		if (!document.dragging) {
		  $(document).bind('mousemove', fb.mousemove).bind('mouseup', fb.mouseup);
		  document.dragging = true;
		}

		// Check which area is being dragged.
		var pos = fb.widgetCoords(event);
		fb.circleDrag = Math.max(Math.abs(pos.x), Math.abs(pos.y)) * 2 > fb.square;

		// Process the mouse move event.
		fb.mousemove(event);
		return false;
	  };

		/**
		 * Handles the moving event within the color picker widget and sets the HSL value.
		 *
		 * @param {Event} event The mouse moving event.
		 *
		 * @returns {boolean} Always returns false.
		 */
	  fb.mousemove = function (event) {
		// Get coordinates relative to the color picker center.
		var pos = fb.widgetCoords(event);

		// Set the new HSL parameters.
		if (fb.circleDrag) {
		  var hue = Math.atan2(pos.x, -pos.y) / 6.28;
		  if (hue < 0) hue += 1;
		  fb.setHSL([hue, fb.hsl[1], fb.hsl[2]]);
		}
		else {
		  var sat = Math.max(0, Math.min(1, -(pos.x / fb.square) + .5));
		  var lum = Math.max(0, Math.min(1, -(pos.y / fb.square) + .5));
		  fb.setHSL([fb.hsl[0], sat, lum]);
		}
		return false;
	  };

	  /**
	   * Mouseup handler
	   */
	  fb.mouseup = function () {
		// Uncapture mouse
		$(document).unbind('mousemove', fb.mousemove);
		$(document).unbind('mouseup', fb.mouseup);
		document.dragging = false;
	  };

	  /**
	   * Update the markers and styles
	   */
	  fb.updateDisplay = function () {
		// Markers
		var angle = fb.hsl[0] * 6.28;
		$('.h-marker', e).css({
		  left: Math.round(Math.sin(angle) * fb.radius + fb.width / 2) + 'px',
		  top: Math.round(-Math.cos(angle) * fb.radius + fb.width / 2) + 'px'
		});

		$('.sl-marker', e).css({
		  left: Math.round(fb.square * (.5 - fb.hsl[1]) + fb.width / 2) + 'px',
		  top: Math.round(fb.square * (.5 - fb.hsl[2]) + fb.width / 2) + 'px'
		});

		// Saturation/Luminance gradient
		$('.color', e).css('backgroundColor', fb.pack(fb.HSLToRGB([fb.hsl[0], 1, 0.5])));

		// Linked elements or callback
		if (typeof fb.callback == 'object') {
		  // Set background/foreground color
		  $(fb.callback).css({
			backgroundColor: fb.color,
			color: fb.hsl[2] > 0.5 ? '#000' : '#fff'
		  });

		  // Change linked value
		  $(fb.callback).each(function() {
			if (this.value && this.value != fb.color) {
			  this.value = fb.color;
			}
		  });
		}
		else if (typeof fb.callback == 'function') {
		  fb.callback.call(fb, fb.color);
		}
	  };

	  /* Various color utility functions */
	  fb.pack = function (rgb) {
		var r = Math.round(rgb[0] * 255);
		var g = Math.round(rgb[1] * 255);
		var b = Math.round(rgb[2] * 255);
		return '#' + (r < 16 ? '0' : '') + r.toString(16) +
			   (g < 16 ? '0' : '') + g.toString(16) +
			   (b < 16 ? '0' : '') + b.toString(16);
	  };

	  fb.unpack = function (color) {
		if (color.length == 7) {
		  return [parseInt('0x' + color.substring(1, 3)) / 255,
			parseInt('0x' + color.substring(3, 5)) / 255,
			parseInt('0x' + color.substring(5, 7)) / 255];
		}
		else if (color.length == 4) {
		  return [parseInt('0x' + color.substring(1, 2)) / 15,
			parseInt('0x' + color.substring(2, 3)) / 15,
			parseInt('0x' + color.substring(3, 4)) / 15];
		}
	  };

	  fb.HSLToRGB = function (hsl) {
		var m1, m2, r, g, b;
		var h = hsl[0], s = hsl[1], l = hsl[2];
		m2 = (l <= 0.5) ? l * (s + 1) : l + s - l*s;
		m1 = l * 2 - m2;
		return [this.hueToRGB(m1, m2, h+0.33333),
			this.hueToRGB(m1, m2, h),
			this.hueToRGB(m1, m2, h-0.33333)];
	  };

	  fb.hueToRGB = function (m1, m2, h) {
		h = (h < 0) ? h + 1 : ((h > 1) ? h - 1 : h);
		if (h * 6 < 1) return m1 + (m2 - m1) * h * 6;
		if (h * 2 < 1) return m2;
		if (h * 3 < 2) return m1 + (m2 - m1) * (0.66666 - h) * 6;
		return m1;
	  };

	  fb.RGBToHSL = function (rgb) {
		var min, max, delta, h, s, l;
		var r = rgb[0], g = rgb[1], b = rgb[2];
		min = Math.min(r, Math.min(g, b));
		max = Math.max(r, Math.max(g, b));
		delta = max - min;
		l = (min + max) / 2;
		s = 0;
		if (l > 0 && l < 1) {
		  s = delta / (l < 0.5 ? (2 * l) : (2 - 2 * l));
		}
		h = 0;
		if (delta > 0) {
		  if (max == r && max != g) h += (g - b) / delta;
		  if (max == g && max != b) h += (2 + (b - r) / delta);
		  if (max == b && max != r) h += (4 + (r - g) / delta);
		  h /= 6;
		}
		return [h, s, l];
	  };

	  // Install mousedown handler (the others are set on the document on-demand)
	  $('*', e).mousedown(fb.mousedown);

		// Init color
	  fb.setColor('#000000');

	  // Set linked elements/callback
	  if (callback) {
		fb.linkTo(callback);
	  }
	};

})(jQuery);