var webpack = require( 'webpack' );
var generateEntries = require( './tools/webpack/generateEntries' );
var minifyAssetsPlugin = require( './tools/webpack/minifyAssetsPlugin' );
var copyEmbedPlugin = require( './tools/webpack/copyEmbedPlugin' );
var copyPackagesPlugin = require( './tools/webpack/copyPackagesPlugin' );
var copyVendorPlugin = require( './tools/webpack/copyVendorPlugin' );
var concatTinymcePlugin = require( './tools/webpack/concatTinymcePlugin' );
var concatEmojiPlugin = require( './tools/webpack/concatEmojiPlugin' );
var compressTinymcePlugin = require( './tools/webpack/compressTinymcePlugin' );

module.exports = [
	{
		cache: true,
		watch: false,
		entry: generateEntries({
			pattern: './src/js/_enqueues/**/*.js',
			globOptions: { ignore: './src/js/_enqueues/vendor/**/*.js' },
			prefix: './build/',
			minify: true
		}),
		output: { filename: '[name]' },
		plugins: [
			new webpack.optimize.ModuleConcatenationPlugin(),
			minifyAssetsPlugin,
			copyEmbedPlugin,
			copyPackagesPlugin,
			copyVendorPlugin,
			concatTinymcePlugin,
			concatEmojiPlugin,
			compressTinymcePlugin
		]
	}
];
