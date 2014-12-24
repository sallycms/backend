module.exports = function (grunt) {
    grunt.initConfig({

        pkg: grunt.file.readJSON('package.json'),

        /************************************************************************\
         * clean                                                                *
        \************************************************************************/

        clean: {
            vendor:  ['assets/dist/vendor/'],
            images:  ['assets/dist/img/'],
            styles:  ['assets/dist/css/'],
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
                    'bootstrap-sass-official/assets/fonts/bootstrap/*.{eot,svg,ttf,woff}'
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
         * compass                                                              *
        \************************************************************************/

        compass: {
            app: {
                options: {
                    config: 'compass.rb'
                }
            }
        },

        /************************************************************************\
         * watch                                                                *
        \************************************************************************/

        watch: {
            scss: {
                files: ['assets/scss/**/*.scss'],
                tasks: ['compass:app']
            },
            js: {
                files: ['assets/js/**/*.js'],
                tasks: ['jshint:app', 'concat:app', 'uglify:app']
            },
            webfont: {
                files: ['assets/font/source/**/*.svg'],
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
         * concat                                                               *
        \************************************************************************/

        concat: {
            app: {
                options: {
                    separator: ';',
                },
                src: [
                    'assets/js/app.js'
                ],
                dest: 'assets/dist/js/app.js'
            },
            vendor: {
                options: {
                    separator: ';',
                },
                src: [
                    'assets/vendor/modernizr/modernizr.js',
                    'assets/vendor/jquery/dist/jquery.js',
                    'assets/vendor/select2/select2.js',
                    'assets/vendor/bootstrap-sass-official/assets/javascripts/bootstrap.js',
                    'assets/vendor/iCheck/icheck.js',
                    'assets/vendor/nprogress/nprogress.js',
                    'assets/vendor/magnific-popup/dist/jquery.magnific-popup.js'
                ],
                dest: 'assets/dist/js/vendor.js'
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
                    'assets/dist/js/app.min.js': ['assets/dist/js/app.js']
                }
            },
            vendor: {
                options: {
                    banner: '/*! <%= pkg.name %> app | version <%= pkg.version %> | <%= grunt.template.today("dd-mm-yyyy") %> */\n'
                },
                files: {
                    'assets/dist/js/vendor.min.js': ['assets/dist/js/vendor.js']
                }
            }
        },

        /************************************************************************\
         * cssmin                                                               *
        \************************************************************************/

        cssmin: {
            app: {
                options: {
                    banner: '/*! <%= pkg.name %> app | version <%= pkg.version %> | <%= grunt.template.today("dd-mm-yyyy") %> */',
                    keepSpecialComments: 0,
                    processImport: false
                },
                files: {
                    'assets/dist/css/app.min.css': ['assets/dist/css/app.css']
                }
            },
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
                src: 'assets/font/source/*.svg',
                dest: 'assets/scss/icon',
                options: {
                    font: 'sallycms',
                    hashes: false,
                    types: 'woff,ttf',
                    template: 'assets/font/source/template.css',
                    templateOptions: {
                        baseClass: 'sallycms-icon',
                        classPrefix: 'sallycms-icon-'
                    },
                    stylesheet: 'scss',
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
    grunt.loadNpmTasks('grunt-contrib-compass');
    grunt.loadNpmTasks('grunt-contrib-jshint');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
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
        'compass:app',
        'cssmin:vendor',
        'cssmin:app',
        'cssmin:vendor'
    ]);

    // scripts task
    grunt.registerTask('scripts', [
        'clean:scripts',
        'jshint:app',
        'concat:app',
        'concat:vendor',
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
