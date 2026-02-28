const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'src-assets/js/admin.js' ),
		frontend: path.resolve( __dirname, 'src-assets/js/frontend.js' ),
		'admin-style': path.resolve(
			__dirname,
			'src-assets/scss/admin.scss'
		),
		'frontend-style': path.resolve(
			__dirname,
			'src-assets/scss/frontend.scss'
		),
	},
	output: {
		path: path.resolve( __dirname, 'assets/js' ),
		filename: '[name].js',
	},
	module: {
		...defaultConfig.module,
		rules: [
			...( defaultConfig.module?.rules || [] ),
			{
				test: /\.scss$/,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					'sass-loader',
				],
			},
		],
	},
	plugins: [
		...( defaultConfig.plugins || [] ).filter(
			( plugin ) =>
				plugin.constructor.name !== 'MiniCssExtractPlugin'
		),
		new MiniCssExtractPlugin( {
			filename: '../css/[name].css',
		} ),
	],
};
