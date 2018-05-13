/**
 * Manages the build of this plugin.
 */
module.exports = function(grunt) {

    require('load-grunt-tasks')(grunt);

    grunt.initConfig({

	pkg : grunt.file.readJSON('package.json'),
	changelog : grunt.file.read('CHANGELOG.md'),

	'phpunit-runner' : {
	    all : {
		options : {
		    phpunit : 'vendor/bin/phpunit',
		    bootstrap : 'vendor/autoload.php',
		    testdoxText : true
		},
		testFolder : 'tests/'
	    }
	},

	webpack : {
	    app : {
		entry : "./public/assets/js/src/app.module.js",
		output : {
		    path : "./public/assets/js/dist",
		    filename : "app.js",
		}
	    }
	},

	uglify : {
	    options : {
		compress : {
		// drop_console : true
		}
	    },
	    app : {
		files : {
		    './public/assets/js/dist/app.min.js' : [ './public/assets/js/dist/app.js' ]
		}
	    }
	},

	less : {
	    styles : {
		options : {
		    paths : [ "css/src" ]
		},
		files : {
		    "./public/assets/css/dist/app.css" : "./public/assets/css/src/app.less"
		}
	    }
	},

	clean : {
	    contents : [ './wordpress/wp-content/plugins/mendeleyplugin/*' ],
	},

	copy : {
	    main : {
		expand : true,
		cwd : '.',
		src : [ '*.php', 'img/**/*', 'public/**/*', 'includes/**/*', 'languages/**/*', 'admin/**/*',
			'plugin-update-checker/**/*', 'assets/**/*', 'doc/**/*' ],
		dest : './wordpress/wp-content/plugins/mendeleyplugin/',
	    },
	},

	compress : {
	    main : {
		options : {
		    archive : './wordpress/wp-content/plugins/mendeleyplugin.zip'
		},
		files : [ {
		    expand : true,
		    cwd : '.',
		    src : [ '*.php', 'img/**/*', 'public/**/*', 'includes/**/*', 'languages/**/*', 'admin/**/*',
			    'assets/**/*', 'doc/**/*' ],
		    dest : '.'
		} ]
	    }
	},

	watch : {

	    gui : {
		files : [ 'public/assets/css/src/**/*.less', 'includes/*.html', 'public/assets/js/dist/*.js',
			'**/*.php' ],
		tasks : [ 'deploy' ],
		options : {
		    spawn : false,
		}
	    }
	},

	bump : {
	    options : {
		files : [ 'package.json' ],
		updateConfigs : [ 'pkg', 'component' ],
		commit : true,
		commitMessage : 'Release v%VERSION%',
		commitFiles : [ '-a' ],
		createTag : true,
		tagName : 'v%VERSION%',
		tagMessage : 'Version %VERSION% \n\n' + grunt.file.read('CHANGELOG.md'),
		push : true,
		pushTo : 'origin',
		globalReplace : true
	    }
	},

	availabletasks : {
	    tasks : {
		options : {
		    filter : 'exclude',
		    tasks : [ 'availabletasks', 'tasks' ]
		}
	    }
	},

	'git-archive' : {
	    archive : {
		options : {
		    'output' : 'mendeleyplugin.zip',
		    'tree-ish' : 'master',
		    'worktree-attributes' : true,
		    'format' : 'zip'
		}
	    }
	},

	changelog : {
	    sample : {
		options : {
		    fileHeader : '',
		    after : 'v<%= pkg.version %>',
		    dest : 'CHANGELOG.md',
		    partials : {
			features : '\n\n{{#if features}}{{#each features}}{{> feature}}{{/each}}{{else}}{{> empty}}{{/if}}'
		    },
		    featureRegex : /^LOG: (.*)$/gim,
		    template : '{{date}}\t{{> features}}'
		}
	    }
	},
	
	"regex-replace" : {
	    rootplugin : {
		src : [ './waau-mendeley-plugin.php' ],
		actions : [ {
		    name : 'version',
		    search : ' * Version.*$',
		    replace : ' Version: <%= pkg.version %>',
		    flags : 'mg'
		} ]
	    },
	    pluginv : {
		src : [ './public/WaauMendeleyPlugin.php' ],
		actions : [ {
		    name : 'version2',
		    search : 'const VERSION = \'[0-9]*.[0-9]*.[0-9]*\'',
		    replace : 'const VERSION = \'<%= pkg.version %>\'',
		    flags : 'mg'
		} ]
	    }
	}

    });

    grunt.registerTask("js", [ "webpack", "uglify" ]);
    grunt.registerTask("css", [ 'less' ]);
    grunt.registerTask("build", [ "js", "css" ]);
    grunt.registerTask("test", [ "build", "clean", "copy" ]);
    grunt.registerTask("cleantest", [ "clean" ]);
    grunt.registerTask("bumpversion", [ 'bump-only', 'regex-replace' ]);
    grunt.registerTask('deploy', [ 'build', 'changelog', 'bumpversion', 'bump-commit' ]);
    grunt.registerTask('release', [ 'deploy' ]);

};