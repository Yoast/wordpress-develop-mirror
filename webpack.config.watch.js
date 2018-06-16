var generateEntries = require( './tools/webpack/generateEntries' );

module.exports = [
	{
		cache: true,
		watch: true,
		entry: generateEntries({
			pattern: './src/js/_enqueues/**/*.js',
			globOptions: { ignore: './src/js/_enqueues/vendor/**/*.js' },
			prefix: './build/',
			minify: true /* Only generated minified file names */
		}),
		output: { filename: '[name]' }
	}
];
