const gulp = require('gulp');

// Include our plugins
const argv = require('yargs').argv;
const autoprefixer = require('gulp-autoprefixer');
const clean = require('gulp-clean');
const named = require('vinyl-named');
const path = require('path');
const rename = require('gulp-rename');
const sass = require('gulp-dart-sass');
const sourcemaps = require('gulp-sourcemaps');
const webpack = require('webpack-stream');

// Build utilities
const theme = require('../.theme.json');
const print = require('./print')('gulp');

/**
 * The file patterns of all styles and scripts to compile.
 */
const assetPaths = {
    global: {
        stylePaths: ['styles/*.{sass,scss}'],
        scriptPaths: ['scripts/app.js'],
    },
    static: {
        stylePaths: ['styles/static/*.{sass,scss}'],
        scriptPaths: ['scripts/static/*.js'],
    },
};

// Add content paths.
for (const key in theme.contentPaths) {
    assetPaths[key] = {
        groupName: key,
        stylePaths: theme.contentPaths[key].map(p => `${p}/**/*.{sass,scss}`),
        scriptPaths: theme.contentPaths[key].map(p => `${p}/**/*.js`),
    };
}

/**
 * Utilities meant to be used within gulp actions.
 *
 * @type {object}
 */
const util = {
    /**
     * Adds the specified asset name as a prefix to a file path.
     */
    addAssetNameToFileName: groupName => path => {
        path.dirname = '';
        path.basename = `${groupName}/${path.basename}`;
    },

    /**
     * Creates an action to compile styles matching the specified pattern.
     */
    createCompileStylesAction: (srcPatterns, groupName, assetType = '') => {
        const action = () => {
            const renameCb = groupName ? util.addAssetNameToFileName(groupName) : p => p;

            return gulp
                .src(srcPatterns)
                .pipe(sourcemaps.init())
                .pipe(
                    sass({
                        outputStyle: 'compressed',
                        includePaths: ['node_modules', 'styles'],
                    }).on('error', sass.logError)
                )
                .pipe(autoprefixer())
                .pipe(rename(renameCb))
                .pipe(sourcemaps.write('.'))
                .pipe(gulp.dest('../public'));
        };

        action.displayName = `styles:${assetType ? assetType : groupName}`;

        return action;
    },

    /**
     * Creates an action to compile scripts matching the specified pattern.
     */
    createCompileScriptsAction: (srcPattern, groupName, assetType = '') => {
        const action = () => {
            const renameCb = groupName ? util.addAssetNameToFileName(groupName) : p => p;

            return gulp
                .src(srcPattern)
                .pipe(
                    named(file => {
                        const p = {basename: path.basename(file.path, path.extname(file.path))};
                        renameCb(p);
                        return p.basename;
                    })
                )
                .pipe(webpack(require('./webpack.config.js')))
                .pipe(gulp.dest('../public'));
        };
        action.displayName = `scripts:${assetType ? assetType : groupName}`;

        return action;
    }
};

/**
 * The list of tasks to compile all styles and scripts.
 */
const compileActions = Object.values(assetPaths).reduce(
    (tasks, asset, i) => {
        /**
         * The name of the asset type.
         * E.g. "global", "static", "blocks", "components".
         */
        const assetType = Object.keys(assetPaths)[i];

        // Save styles.
        tasks.styles[assetType] = util.createCompileStylesAction(
            asset.stylePaths.filter(p => p),
            asset.groupName,
            assetType
        );

        // Save scripts.
        tasks.scripts[assetType] = util.createCompileScriptsAction(
            asset.scriptPaths.filter(p => p),
            asset.groupName,
            assetType
        );

        return tasks;
    },
    {
        styles: {
            global: [],
            static: [],
        },
        scripts: {
            global: [],
            static: [],
        },
    }
);

/**
 * The list of gulp actions that can be executed.
 *
 * @type {object}
 */
const actions = {
    /**
     * Clears the public directory before a clean build.
     */
    clearPublicDir: () => {
        return gulp.src('../public', {read: false, allowEmpty: true}).pipe(clean({force: true}));
    },

    /**
     * Compiles all styles.
     */
    compileAllStyles: gulp.series(...Object.values(compileActions.styles)),

    /**
     * Compiles all scripts.
     */
    compileAllScripts: gulp.series(...Object.values(compileActions.scripts)),

    /**
     * Compiles static styles.
     */
    compileStaticStyles: gulp.series(compileActions.styles.static),

    /**
     * Compiles static scripts.
     */
    compileStaticScripts: gulp.series(compileActions.scripts.static),

    /**
     * Watches files for changes and runs the appropriate actions.
     */
    watchFiles: done => {
        gulp.watch(['./styles/**/*.{sass,scss}', '!./styles/static/**/*.{sass,scss}'], actions.compileAllStyles);
        gulp.watch('./styles/static/**/*.{sass,scss}', actions.compileStaticStyles);

        gulp.watch(['./scripts/**/*.js', '!./scripts/static/**/*.js'], actions.compileAllScripts);
        gulp.watch('./scripts/static/**/*.js', actions.compileStaticScripts);

        // Watch content files.
        // This will only re-compile changed or added files.
        for (const type in theme.contentPaths) {
            gulp.watch(theme.contentPaths[type].map(p => `${p}/**/*.{sass,scss}`))
                .on('all', (event, filePath) => {
                    // Ignore events other than "add" and "change".
                    if (!['add', 'change'].includes(event)) return;

                    gulp.series(util.createCompileStylesAction(path.resolve(filePath), type))();
                });

            gulp.watch(theme.contentPaths[type].map(p => `${p}/**/*.js`))
                .on('all', (event, filePath) => {
                    // Ignore events other than "add" and "change".
                    if (!['add', 'change'].includes(event)) return;

                    gulp.series(util.createCompileScriptsAction(path.resolve(filePath), type))();
                });
        }

        done();

        print('Now watching files.', 'success');
    },
};

/**
 * The list of available gulp tasks.
 *
 * @type {object}
 */
const tasks = {
    /**
     * Runs a development build and watches files.
     *
     * @command <code>npm start</code> | <code>gulp</code>
     */
    default: () => {
        const {rebuild = true} = argv;

        const taskActions = [
            rebuild ? actions.clearPublicDir : null,
            rebuild ? gulp.series(actions.compileAllStyles, actions.compileAllScripts) : null,
            gulp.parallel(actions.watchFiles),
        ].filter(a => a !== null);

        return gulp.series(taskActions);
    },
};

// Export tasks.
exports.default = tasks.default();
exports.build = gulp.series(actions.clearPublicDir, actions.compileAllStyles, actions.compileAllScripts);
