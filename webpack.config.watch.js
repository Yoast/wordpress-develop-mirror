var generateEntries = require( './tools/webpack/generateEntries' );
var minifyAssetsPlugin = require( './tools/webpack/minifyAssetsPlugin' );
var copyEmbedPlugin = require( './tools/webpack/copyEmbedPlugin' );

module.exports = [
	{
		cache: true,
		watch: true,
		entry: generateEntries({
			pattern: './src/js/_enqueues/**/*.js',
			globOptions: { ignore: './src/js/_enqueues/vendor/**/*.js' },
			prefix: './build/',
			minify: true
		}),
		output: { filename: '[name]' },
		plugins: [ minifyAssetsPlugin ]
	}
];
