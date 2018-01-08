window.wp = window.wp || {};
window.wp.media = window.wp.media || {};

Object.assign( window.wp.media, require( "../../../media/models.js" ) );

// Clean up. Prevents mobile browsers caching
$(window).on('unload', function(){
	window.wp = null;
});
