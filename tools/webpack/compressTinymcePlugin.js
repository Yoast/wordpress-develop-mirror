var CompressionPlugin = require("compression-webpack-plugin");

module.exports = new CompressionPlugin({
	include: './build/wp-includes/js/tinymce/wp-tinymce.js'
});
