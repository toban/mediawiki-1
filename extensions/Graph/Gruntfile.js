/*!
 * Grunt file
 *
 * @package Graph
 */

/* eslint-env node, es6 */

module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true,
				fix: grunt.option( 'fix' )
			},
			all: [
				'**/*.{js,json}',
				'!lib/**',
				'!{node_modules,vendor}/**'
			]
		},
		stylelint: {
			options: {
				syntax: 'less'
			},
			all: [
				'**/*.{css,less}',
				'!lib/**',
				'!{node_modules,vendor}/**'
			]
		},
		banana: conf.MessagesDirs
	} );

	grunt.registerTask( 'lint', [ 'eslint', 'banana', 'stylelint' ] );
	grunt.registerTask( 'test', 'lint' );
	grunt.registerTask( 'default', 'test' );
};
