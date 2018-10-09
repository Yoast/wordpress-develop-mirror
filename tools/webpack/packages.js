/**
 * External dependencies
 */
const LiveReloadPlugin = require( 'webpack-livereload-plugin' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const postcss = require( 'postcss' );

const { join } = require( 'path' );

/**
 * WordPress dependencies
 */
const LibraryExportDefaultPlugin = require( '@wordpress/library-export-default-webpack-plugin' );

const baseDir = join( __dirname, '../../' );

/**
 * Given a string, returns a new string with dash separators converedd to
 * camel-case equivalent. This is not as aggressive as `_.camelCase` in
 * converting to uppercase, where Lodash will convert letters following
 * numbers.
 *
 * @param {string} string Input dash-delimited string.
 *
 * @return {string} Camel-cased string.
 */
function camelCaseDash( string ) {
	return string.replace(
		/-([a-z])/g,
		( match, letter ) => letter.toUpperCase()
	);
}

module.exports = function( env = { environment: 'production' } ) {
	const mode = env.environment;
	const suffix = mode === 'production' ? '.min': '';

	const gutenbergPackages = [
		'a11y',
		'api-fetch',
		'autop',
		'blob',
		'blocks',
//		'block-library', // Not on npm yet.
		'block-serialization-default-parser',
//		'block-serialization-spec-parser', // No build-module folder.
		'components',
		'compose',
		'core-data',
		'data',
		'date',
		'deprecated',
		'dom',
		'dom-ready',
//		'edit-post', // Not on npm yet.
		'editor',
		'element',
//		'escape-html', // Not on npm yet.
		'hooks',
		'html-entities',
		'i18n',
//		'is-shallow-equal', // No build-module folder.
		'keycodes',
//		'list-reusable-blocks', // Not on npm yet.
		'nux',
		'plugins',
		'redux-routine',
//		'rich-text', // Not on npm yet.
		'shortcode',
		'token-list',
		'url',
		'viewport',
		'wordcount',
	];

	const packagesStyles = [
		'nux',
		'components',
		'editor',
	];

	const externals = {
		react: 'React',
		'react-dom': 'ReactDOM',
		tinymce: 'tinymce',
		moment: 'moment',
		jquery: 'jQuery',
		lodash: 'lodash',
		'lodash-es': 'lodash',
	};

	gutenbergPackages.forEach( ( name ) => {
		externals[ `@wordpress/${ name }` ] = {
			this: [ 'wp', camelCaseDash( name ) ],
		};
	} );

	const config = {
		mode,

		entry: gutenbergPackages.reduce( ( memo, packageName ) => {
			const name = camelCaseDash( packageName );
			memo[ name ] = `@wordpress/${ packageName }/build-module/index.js`;
			return memo;
		}, {} ),
		output: {
			filename: `[name]${ suffix }.js`,
			path: join( baseDir, 'js/dist' ),
			library: {
				root: [ 'wp', '[name]' ]
			},
			libraryTarget: 'this',
		},
		externals,
		resolve: {
			modules: [
				baseDir,
				'node_modules',
			],
			alias: {
				'lodash-es': 'lodash',
			},
		},
		module: {
			rules: [
				{
					test: /\.js$/,
					use: [ 'source-map-loader' ],
					enforce: 'pre',
				},
			],
		},
		plugins: [
			new LibraryExportDefaultPlugin( [
				'api-fetch',
				'deprecated',
				'dom-ready',
				'redux-routine',
			].map( camelCaseDash ) ),
			new CopyWebpackPlugin(
				packagesStyles.map( ( packageName ) => ( {
					from: join( baseDir, `node_modules/@wordpress/${ packageName }/build-style/*.css` ),
					to: join( baseDir, `styles/dist/${ packageName }/` ),
					flatten: true,
					transform: ( content ) => {
						if ( config.mode === 'production' ) {
							return postcss( [
								require( 'cssnano' )( {
									preset: 'default',
								} ),
							] )
								.process( content, { from: 'src/app.css', to: 'dest/app.css' } )
								.then( ( result ) => result.css );
						}

						return content;
					}
				} ) ),
			),
		],
		stats: {
			children: false,
		},
	};

	if ( config.mode !== 'production' ) {
		config.devtool = process.env.SOURCEMAP || 'source-map';
	}

	if ( config.mode === 'development' ) {
		config.plugins.push( new LiveReloadPlugin( { port: process.env.GUTENBERG_LIVE_RELOAD_PORT || 35729 } ) );
	}

	return config;
};
