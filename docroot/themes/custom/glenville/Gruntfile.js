module.exports = function(grunt) {
  "use strict";
  const imagemin = require('imagemin');
  const imageminMozjpeg = require('imagemin-mozjpeg');
  require('load-grunt-tasks')(grunt);
  require('time-grunt')(grunt);
  var theme_name = 'glenville';

  var global_vars = {
    theme_name: theme_name,
    theme_css: 'css',
    theme_scss: 'scss'
  };

  (async () => {
    await imagemin(['src/images/*.jpg'], 'dist/images/', {
      use: [
        imageminMozjpeg()
      ]
    });

    console.log('Images optimized');
  })();

  grunt.initConfig({
    global_vars: global_vars,
    pkg: grunt.file.readJSON('package.json'),

    sass_globbing: {
      dist: {
        files: {
          'src/scss/_init.scss': [
            'src/scss/presentation/**/*.scss'
          ],
          'src/scss/_base.scss': [
            'src/scss/fonts/**/*.scss',
            'src/scss/component/**/*.scss',
            'src/scss/jquery-ui/**/*.scss',
            'src/scss/_overrides.scss',
            'src/scss/layout/**/*.scss',
            'src/scss/node/**/**/*.scss',
            'src/scss/block/**/*.scss'
          ]
        }
      }
    },

    'dart-sass': {
      target: {
        options: {
          includePaths: [
            'scss',
            'node_modules/bootstrap/scss/',
            'node_modules/breakpoint-sass/stylesheets'
          ],
          outputStyle: 'compressed',
          sourceMap: true,
          implementation: 'dart-sass'
        },
        files: {
          'dist/css/style-glenville.css': 'src/scss/style.scss'
        }
      }
    },

    uglify: {
      dist: {
        options: {
          sourceMap: true,
          includeSources: true
        },
        files: [{
          expand: true,
          cwd: 'src/js',
          src: ['*.js', '*.min.js'],
          dest: 'dist/js',
          rename: function (dst, src) {
            return dst + '/' + src.replace('.js', '.min.js');
          }
        }]
      }
    },

    postcss: {
      dist: {
        src: [
          'src/css/*.css'
        ],
        dest: 'dist/css',
        expand: true,
        flatten: true,
        dest: 'css'
      },
      options: {
        map: true,
        processors: [
          require('pixrem')(),
          require('autoprefixer')({Browserslist: 'last 2 versions'}),
          require('cssnano')()
        ]
      }
    },

    sasslint: {
      options: {
        configFile: '.sass-lint.yml'
      },
      files: ['src/scss/**/*.scss']
    },

    jshint: {
      options: {
        curly: true,
        eqeqeq: true,
        eqnull: true,
        browser: true,
        globals: {
          jQuery: true
        }
      },
      files: ['src/js/scripts.js']
    },

    watch: {
      grunt: { files: ['Gruntfile.js'] },
      'dart-sass': {
        files: ['<%= sasslint.files %>'],
        tasks: ['dart-sass', 'grunt-postcss', 'sasslint'],
        options: {
          livereload: true
        }
      },
      js: {
        files: ['<%= jshint.files %>'],
        tasks: ['jshint']
      }
    },

    webfont: {
      icons: {
        src: 'src/icons/*.svg',
        dest: 'dist/fonts',
        destScss: 'src/scss/fonts',
        options: {
          stylesheet: 'scss',
          syntax: 'bem',
          relativeFontPath: '../../dist/fonts',
          fontFilename: 'material-icons-social',
          fontFamilyName: 'Material Icons Social',
          fontHeight: 700,
          templateOptions: {
            baseClass: 'material-icons-social',
            classPrefix: 'mis_'
          }
        }
      }
    },

    imagemin: {
      dist: {
        options: {
          optimizationLevel: 3,
          svgoPlugins: [{removeViewBox: false}],
          use: [imageminMozjpeg()] // Example plugin usage
        },
        files: [{
          expand: true,
          cwd: 'src/images',
          src: ['**/*.{png,jpg,gif,svg}'],
          dest: 'dist/images/'
        }]
      }
    }

  });

  grunt.loadNpmTasks('grunt-sass-globbing');
  grunt.loadNpmTasks('grunt-dart-sass');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-sass-lint');
  grunt.loadNpmTasks('grunt-contrib-jshint');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-contrib-imagemin');
  grunt.loadNpmTasks('grunt-webfonts');
  grunt.loadNpmTasks('grunt-postcss');

  grunt.registerTask('build', ['sasslint', 'sass_globbing', 'dart-sass', 'uglify', 'imagemin']);
  grunt.registerTask('default', ['build', 'watch']);
};
