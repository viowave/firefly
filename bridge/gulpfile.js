const gulp = require("gulp");
const sass = require("gulp-sass")(require("sass"));

// Paths
const scssPath = "scss/**/*.scss";
const cssDest = "css/";

// SCSS Compilation Task
gulp.task("scss", function () {
    return gulp.src(scssPath)
        .pipe(sass().on("error", sass.logError))
        .pipe(gulp.dest(cssDest));
});

// Watch for Changes
gulp.task("watch", function () {
    gulp.watch(scssPath, gulp.series("scss"));
});

// Default Task (Run with `gulp`)
gulp.task("default", gulp.series("scss", "watch"));
