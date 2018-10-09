/**
 * External dependencies
 */
const { join } = require( 'path' );

const baseDir = join( __dirname, '../../' );

module.exports = function( env = { environment: 'production', watch: false } ) {
	const mode = env.environment;
	const suffix = mode === 'production' ? '.min': '';

	const vendors = {
		'lodash': 'lodash',
		'wp-polyfill': '@babel/polyfill',
		'moment': 'moment',
		'react': 'react',
		'react-dom': 'react-dom',
	};
	const config = {
		mode,

		entry: Object.keys( vendors ).reduce( ( memo, entry ) => {
			const packageName = vendors[ entry ];
			memo[ entry ] = `./node_modules/${ packageName }`;
			return memo;
		}, {} ),
		output: {
			filename: `[name]${ suffix }.js`,
			path: join( baseDir, 'build/js/dist/vendor' ),
		},
		resolve: {
			modules: [
				baseDir,
				'node_modules',
			],
		},
		stats: {
			children: false,
		},

		watch: env.watch,
	};

	return config;
};
