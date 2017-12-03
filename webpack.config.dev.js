var path        	= require( 'path' ),
	webpack       	= require( 'webpack' ),
	admin_files   	= {},
	include_files 	= {};

include_files = {
	'build/wp-includes/js/media-audiovideo.js': ['./src/js/enqueues/wp/media/audiovideo.js'],
	'build/wp-includes/js/media-grid.js': ['./src/js/enqueues/wp/media/grid.js'],
	'build/wp-includes/js/media-models.js': ['./src/js/enqueues/wp/media/models.js'],
	'build/wp-includes/js/media-views.js': ['./src/js/enqueues/wp/media/views.js'],
};

module.exports = [
	{
		cache: true,
		watch: true,
		entry: Object.assign( npm_packages, admin_files, include_files ),
		output: {
			filename: '[name]',
		}
	},
	{
		cache: true,
		watch: true,
		entry: {
			'build/wp-includes/js/wp-a11y.js': ['@wordpress/a11y'],
		},
		output: {
			filename: 'build/wp-includes/js/wp-a11y.js',
			library: [ 'wp', 'a11y', 'speak' ],
		}
	}
];
