watch:
	sass --watch css:css

build:
	sass --update css:css && autoprefixer css/*.css

.PHONY: watch build
