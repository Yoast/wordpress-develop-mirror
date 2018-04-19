/* global _zxcvbnSettings */
/**
 * Loads the zxcvbnSettings asynchronously by inserting async script tag before
 * the first one.
 */
(function() {
  var async_load = function() {
    var first, s;
    s = document.createElement('script');
    s.src = _zxcvbnSettings.src;
    s.type = 'text/javascript';
    s.async = true;
    first = document.getElementsByTagName('script')[0];
    return first.parentNode.insertBefore(s, first);
  };

  if (window.attachEvent != null) {
    window.attachEvent('onload', async_load);
  } else {
    window.addEventListener('load', async_load, false);
  }
}).call(this);
