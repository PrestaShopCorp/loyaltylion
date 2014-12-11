
var gulp = require('gulp');
var sass = require('gulp-sass');
var autoprefixer = require('gulp-autoprefixer');
var csso = require('gulp-csso');
var uglify = require('gulp-uglify');
var rename = require('gulp-rename');

gulp.task('css', function () {
  gulp.src('./css/loyaltylion.scss')
    .pipe(sass())
    .pipe(autoprefixer({ browsers: ['last 2 versions'] }))
    .pipe(csso())
    .pipe(rename('loyaltylion.min.css'))
    .pipe(gulp.dest('./css'));
});

gulp.task('js', function () {
  gulp.src('./js/loyaltylion.js')
    .pipe(uglify())
    .pipe(rename('loyaltylion.min.js'))
    .pipe(gulp.dest('./js'));
});

gulp.task('watch', function () {
  gulp.watch('./css/loyaltylion.scss', ['css']);
  gulp.watch('./js/loyaltylion.js', ['js']);
});

gulp.task('default', ['css', 'js']);