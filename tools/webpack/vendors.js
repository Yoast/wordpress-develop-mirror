/**
 * External dependencies
 */
const { join, basename } = require( 'path' );
const { get, isString } = require( 'lodash' );
const webpack = require( 'webpack' );

/**
 * WordPress dependencies
 */
const CustomTemplatedPathPlugin = require( '@wordpress/custom-templated-path-webpack-plugin' );

const baseDir = join( __dirname, '../../' );

module.exports = function( env = { environment: 'production', watch: false } ) {
	const mode = env.environment;
	const suffix = mode === 'production' ? '.min': '';

	const vendors = [
		'lodash',
		{
			packageName: '@babel/polyfill',
			global: 'wp-polyfill',
			filename: 'wp-polyfill',
		},
		{
			packageName: 'whatwg-fetch',
			global: 'wp-polyfill-fetch',
			filename: 'wp-polyfill-fetch',
		},
		{
			packageName: 'element-closest',
			global: 'wp-polyfill-element-closest',
			filename: 'wp-polyfill-element-closest',
		},
		{
			packageName: 'polyfill-library/polyfills/Node/prototype/contains/polyfill.js',
			global: 'wp-polyfill-node-contains',
			filename: 'wp-polyfill-node-contains',
		},
		{
			packageName: 'formdata-polyfill',
			global: 'wp-polyfill-formdata',
			filename: 'wp-polyfill-formdata',
		},
		'moment',
		{
			packageName: 'react',
			global: 'React',
		},
		{
			packageName: 'react-dom',
			global: 'ReactDOM',
		},
	];

	const entryPoints = {};
	const filenames = {};

	vendors.forEach( ( vendor ) => {
		if ( isString( vendor ) ) {
			vendor = { packageName: vendor };
		}

		if ( ! vendor.global ) {
			vendor.global = vendor.packageName;
		}

		if ( ! vendor.filename ) {
			vendor.filename = vendor.packageName;
		}

		const request = `./node_modules/${ vendor.packageName }`

		entryPoints[ vendor.global ] = request;
		filenames[ request ] = vendor.filename;
	} );

	const config = {
		mode,

		entry: entryPoints,
		output: {
			filename: `[customFilename]${ suffix }.js`,
			path: join( baseDir, 'build/js/dist/vendor' ),
			library: '[name]',
			libraryTarget: 'this',
		},
		resolve: {
			modules: [
				baseDir,
				'node_modules',
			],
		},
		plugins: [
			new CustomTemplatedPathPlugin( {
				customFilename( path, data ) {
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
						return filenames[ rawRequest ];
					}

					return path;
				},
			} ),
		],
		stats: {
			children: false,
		},

		watch: env.watch,
	};

	return config;
};
