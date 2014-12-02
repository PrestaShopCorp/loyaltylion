var gulp = require('gulp');
var sass = require('gulp-sass');
var autoprefixer = require('gulp-autoprefixer');
var csso = require('gulp-csso');

gulp.task('css', function () {
  gulp.src('./css/*.scss')
    .pipe(sass())
    .pipe(autoprefixer({ browsers: ['last 2 versions'] }))
    .pipe(csso())
    .pipe(gulp.dest('./css'));
});

gulp.task('watch', function () {
  gulp.watch('./css/*.scss', ['css']);
});

gulp.task('default', ['css']);