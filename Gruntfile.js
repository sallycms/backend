module.exports = function (grunt) {
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		/************************************************************************\
		 * clean                                                                *
		 \************************************************************************/

		clean: {
			vendor: ['assets/dist/vendor/'],
			images: ['assets/dist/img/'],
			styles: ['assets/dist/css/'],
			scripts: ['assets/dist/js/']
		},
		/************************************************************************\
		 * copy                                                                 *
		 \************************************************************************/

		copy: {
			vendor: {
				expand: true,
				cwd: 'assets/vendor/',
				src: [
					'select2/*.{gif,png}',
					'bootstrap/fonts/*',
					'roboto-fontface/fonts/*'
				],
				dest: 'assets/dist/vendor/'
			},
			images: {
				expand: true,
				cwd: 'assets/img/',
				src: '**/**',
				dest: 'assets/dist/img'
			}
		},
		/************************************************************************\
		 * less                                                                 *
		 \************************************************************************/

		less: {
			app: {
				options: {
					banner: '/*! <%= pkg.name %> app | version <%= pkg.version %> | <%= grunt.template.today("dd-mm-yyyy") %> */',
					compress: true,
					cleancss: true,
					ieCompat: true,
					paths: ['assets/less']
				},
				files: {
					'assets/dist/css/app.min.css': 'assets/less/app.less'
				}
			}
		},
		/************************************************************************\
		 * autoprefixer                                                         *
		 \************************************************************************/

		autoprefixer: {
			options: {
				browsers: ['> 1%', 'last 2 versions', 'Android 4.0', 'Firefox ESR', 'Opera 12.1', 'ie 9']
			},
			files: {
				expand: true,
				flatten: true,
				src: 'assets/css/**/*.css',
				dest: 'assets/dist/css/'
			}
		},
		/************************************************************************\
		 * watch                                                                *
		 \************************************************************************/

		watch: {
			less: {
				files: [
					'assets/less/**/*.less'
				],
				tasks: ['less:app']
			},
			js: {
				files: ['assets/js/**/*.js'],
				tasks: ['jshint:app', 'uglify:app']
			},
			webfont: {
				files: ['assets/font/svg/**/*.svg'],
				tasks: ['webfont:icons']
			},
			livereload: {
				files: [
					'assets/dist/css/*.css'
				],
				options: {
					livereload: true
				}
			}
		},
		/************************************************************************\
		 * jshint                                                               *
		 \************************************************************************/

		jshint: {
			app: {
				src: [
					'assets/js/app.js'
				]
			}
		},
		/************************************************************************\
		 * uglify                                                               *
		 \************************************************************************/

		uglify: {
			app: {
				options: {
					banner: '/*! <%= pkg.name %> app | version <%= pkg.version %> | <%= grunt.template.today("dd-mm-yyyy") %> */\n'
				},
				files: {
					'assets/dist/js/app.min.js': ['assets/js/app.js']
				}
			},
			vendor: {
				options: {
					banner: '/*! <%= pkg.name %> app | version <%= pkg.version %> | <%= grunt.template.today("dd-mm-yyyy") %> */\n'
				},
				files: {
					'assets/dist/js/vendor.min.js': [
						'assets/vendor/modernizr/modernizr.js',
						'assets/vendor/jquery/dist/jquery.js',
						'assets/vendor/jquery-migrate/index.js',
						'assets/vendor/select2/select2.js',
						'assets/vendor/bootstrap/js/collapse.js',
						'assets/vendor/bootstrap/js/transition.js',
						'assets/vendor/iCheck/icheck.js',
//						'assets/vendor/nprogress/nprogress.js',
						'assets/vendor/magnific-popup/dist/jquery.magnific-popup.js'
					]
				}
			}
		},
		/************************************************************************\
		 * cssmin                                                               *
		 \************************************************************************/

		cssmin: {
			vendor: {
				options: {
					keepSpecialComments: '*',
					processImport: true,
					target: 'assets/dist/vendor',
					relativeTo: '../../vendor'
				},
				src: [
					'assets/vendor/select2/select2.css',
					'assets/vendor/magnific-popup/dist/magnific-popup.css'

				],
				dest: 'assets/dist/css/vendor.min.css'
			}
		},
		/************************************************************************\
		 * webfont                                                              *
		 \************************************************************************/

		webfont: {
			icons: {
				src: 'assets/font/svg/*.svg',
				dest: 'assets/less/font',
				options: {
					font: 'sallycms',
					hashes: false,
					types: 'woff,ttf',
					template: 'assets/font/template/template.css',
					templateOptions: {
						baseClass: 'sallycms-icon',
						classPrefix: 'sallycms-icon-'
					},
					stylesheet: 'less',
					htmlDemo: false,
					embed: 'woff,ttf',
					engine: 'node'
				}
			}
		}
	});

	// load tasks
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-clean');
	grunt.loadNpmTasks('grunt-contrib-less');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-copy');
	grunt.loadNpmTasks('grunt-contrib-cssmin');
	grunt.loadNpmTasks('grunt-autoprefixer');
	grunt.loadNpmTasks('grunt-webfont');

	// assets tasks
	grunt.registerTask('assets', [
		'clean:vendor',
		'clean:images',
		'copy:vendor',
		'copy:images'
	]);

	// styles task
	grunt.registerTask('styles', [
		'clean:styles',
		'less:app',
		'autoprefixer',
		'cssmin:vendor',
		'cssmin:vendor'
	]);

	// scripts task
	grunt.registerTask('scripts', [
		'clean:scripts',
		'jshint:app',
		'uglify:app',
		'uglify:vendor'
	]);

	// fonts task
	grunt.registerTask('fonts', [
		'webfont:icons'
	]);

	// default task
	grunt.registerTask('default', [
		'assets',
		'styles',
		'scripts'
	]);
};
