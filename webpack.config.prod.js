var path        	= require( 'path' ),
	webpack       	= require( 'webpack' ),
	admin_files   	= {},
	include_files 	= {};

include_files = {
	'build/wp-includes/js/media-audiovideo.js': ['./src/js/_enqueues/wp/media/audiovideo.js'],
	'build/wp-includes/js/media-grid.js': ['./src/js/_enqueues/wp/media/grid.js'],
	'build/wp-includes/js/media-models.js': ['./src/js/_enqueues/wp/media/models.js'],
	'build/wp-includes/js/media-views.js': ['./src/js/_enqueues/wp/media/views.js'],
};

module.exports = [
	{
		cache: true,
		watch: false,
		entry: Object.assign( admin_files, include_files ),
		output: {
			filename: '[name]',
		},
		plugins: [
			new webpack.optimize.ModuleConcatenationPlugin()
		]
	}
];
