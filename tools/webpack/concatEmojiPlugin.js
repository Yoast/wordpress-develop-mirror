var ConcatPlugin = require('webpack-concat-plugin');

module.exports = new ConcatPlugin({
	filesToConcat: [
		'./node_modules/twemoji/2/twemoji.js',
		'./src/js/_enqueues/wp/emoji.js'
	],
	outputPath: './build/wp-includes/js/',
	fileName: 'wp-emoji-release.min.js'
});
