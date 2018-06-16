var ConcatPlugin = require('webpack-concat-plugin');

module.exports = new ConcatPlugin({
	filesToConcat: [
		'./src/js/_enqueues/vendor/tinymce/tinymce.min.js',
		'./src/js/_enqueues/vendor/tinymce/themes/modern/theme.min.js',
		'./src/js/_enqueues/vendor/tinymce/plugins/**/plugin.min.js'
	],
	outputPath: './build/wp-includes/js/tinymce/',
	fileName: 'wp-tinymce.js'
});
