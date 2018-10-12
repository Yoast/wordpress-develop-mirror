/**
 * External dependencies
 */
const LiveReloadPlugin = require( 'webpack-livereload-plugin' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const postcss = require( 'postcss' );
const UglifyJS = require( 'uglify-js' );

const { join, basename } = require( 'path' );
const { get } = require( 'lodash' );

/**
 * WordPress dependencies
 */
const CustomTemplatedPathPlugin = require( '@wordpress/custom-templated-path-webpack-plugin' );
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

/**
 * Maps vendors to copy commands for the CopyWebpackPlugin.
 *
 * @param {Object} vendors Vendors to include in the vendor folder.
 *
 * @return {Object[]} Copy object suitable for the CopyWebpackPlugin.
 */
function mapVendorCopies( vendors ) {
	return Object.keys( vendors ).map( ( filename ) => ( {
		from: join( baseDir, `node_modules/${ vendors[ filename ] }` ),
		to: join( baseDir, `build/js/dist/vendor/${ filename }` ),
	} ) );
}

module.exports = function( env = { environment: 'production', watch: false } ) {
	const mode = env.environment;
	const suffix = mode === 'production' ? '.min': '';

	const packages = [
		'api-fetch',
		'a11y',
		'autop',
		'blob',
		'blocks',
		'block-library',
		'block-serialization-default-parser',
		'components',
		'compose',
		'core-data',
		'data',
		'date',
		'deprecated',
		'dom',
		'dom-ready',
		'edit-post',
		'editor',
		'element',
		'escape-html',
		'hooks',
		'html-entities',
		'i18n',
		'is-shallow-equal',
		'keycodes',
		'list-reusable-blocks',
		'nux',
		'plugins',
		'redux-routine',
		'rich-text',
		'shortcode',
		'token-list',
		'url',
		'viewport',
		'wordcount',
	];

	const vendors = {
		'lodash.js': 'lodash/lodash.js',
		'wp-polyfill-ecmascript.js': '@babel/polyfill/dist/polyfill.js',
		'wp-polyfill-fetch.js': 'whatwg-fetch/dist/fetch.umd.js',
		'wp-polyfill-element-closest.js': 'element-closest/element-closest.js',
		'wp-polyfill-node-contains.js': 'polyfill-library/polyfills/Node/prototype/contains/polyfill.js',
		'wp-polyfill-formdata.js': 'formdata-polyfill/FormData.js',
		'moment.js': 'moment/moment.js',
		'react.js': 'react/umd/react.development.js',
		'react-dom.js': 'react-dom/umd/react-dom.development.js',
	};

	const minifiedVendors = {
		'lodash.min.js': 'lodash/lodash.min.js',
		'wp-polyfill-ecmascript.min.js': '@babel/polyfill/dist/polyfill.min.js',
		'wp-polyfill-formdata.min.js': 'formdata-polyfill/formdata.min.js',
		'moment.min.js': 'moment/min/moment.min.js',
		'react.min.js': 'react/umd/react.production.min.js',
		'react-dom.min.js': 'react-dom/umd/react-dom.production.min.js',
	};

	const minifyVendors = {
		'wp-polyfill-fetch.min.js': 'whatwg-fetch/dist/fetch.umd.js',
		'wp-polyfill-element-closest.min.js': 'element-closest/element-closest.js',
		'wp-polyfill-node-contains.min.js': 'polyfill-library/polyfills/Node/prototype/contains/polyfill.js',
	};

	const externals = {
		react: 'React',
		'react-dom': 'ReactDOM',
		tinymce: 'tinymce',
		moment: 'moment',
		jquery: 'jQuery',
		lodash: 'lodash',
		'lodash-es': 'lodash',
	};

	packages.forEach( ( name ) => {
		externals[ `@wordpress/${ name }` ] = {
			this: [ 'wp', camelCaseDash( name ) ],
		};
	} );

	const developmentCopies = mapVendorCopies( vendors );
	const minifiedCopies = mapVendorCopies( minifiedVendors );
	const minifyCopies = mapVendorCopies( minifyVendors ).map( ( copyCommand ) => {
		return {
			...copyCommand,
			transform: ( content ) => {
				return UglifyJS.minify( content.toString() ).code;
			},
		};
	} );

	let vendorCopies = mode === "development" ? developmentCopies : [ ...minifiedCopies, ...minifyCopies ];

	let cssCopies = packages.map( ( packageName ) => ( {
		from: join( baseDir, `node_modules/@wordpress/${ packageName }/build-style/*.css` ),
		to: join( baseDir, `build/styles/dist/${ packageName }/` ),
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
	} ) );

	const config = {
		mode,

		entry: packages.reduce( ( memo, packageName ) => {
			const name = camelCaseDash( packageName );
			memo[ name ] = join( baseDir, `node_modules/@wordpress/${ packageName }` );
			return memo;
		}, {} ),
		output: {
			filename: `[basename]${ suffix }.js`,
			path: join( baseDir, 'build/js/dist' ),
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
			new CustomTemplatedPathPlugin( {
				basename( path, data ) {
					let rawRequest;

					const entryModule = get( data, [ 'chunk', 'entryModule' ], {} );
					switch ( entryModule.type ) {
						case 'javascript/auto':
							rawRequest = entryModule.rawRequest;
							break;

						case 'javascript/esm':
							rawRequest = entryModule.rootModule.rawRequest;
							break;
					}

					if ( rawRequest ) {
						return basename( rawRequest );
					}

					return path;
				},
			} ),
			new CopyWebpackPlugin(
				[
					...vendorCopies,
					...cssCopies,
				],
			),
		],
		stats: {
			children: false,
		},

		watch: env.watch,
	};

	if ( config.mode !== 'production' ) {
		config.devtool = process.env.SOURCEMAP || 'source-map';
	}

	if ( config.mode === 'development' ) {
		config.plugins.push( new LiveReloadPlugin( { port: process.env.WORDPRESS_LIVE_RELOAD_PORT || 35729 } ) );
	}

	return config;
};
