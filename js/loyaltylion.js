(function() {
  $(document).ready(function () {

    var screenshot_width = 1024;
    var screenshot_height = 768;

    $('a[data-loyaltylion-screenshot]').each(function() {
      var elem = $(this);

      elem.on('click', function(e) {
        e.preventDefault();

        var href = $(this).attr('href');
        var viewport_width = $(window).width();
        var viewport_height = $(window).height();

        var width = screenshot_width;
        var height = screenshot_height;

        if (viewport_width < screenshot_width || viewport_height < screenshot_height) {
          var ratio = Math.min(viewport_width / screenshot_width, viewport_height / screenshot_height);
          width = screenshot_width * ratio;
          height = screenshot_height * ratio;
        }

        var lightbox = $("<div id='loyaltylion-lightbox'></div>").css({
          width: width,
          height: height,
          'margin-left': -(width/2),
          'margin-top': -(height/2)
        });
        var close_btn = $("<a href='#' class='loyaltylion-lightbox-close-btn'>&times;</a>").appendTo(lightbox);
        $("<img src='" + href + "' width='100%' height='100%'>").appendTo(lightbox);

        var background = $("<div id='loyaltylion-lightbox-background'></div>");

        $('body').append(lightbox).append(background);

        setTimeout(function () {
          lightbox.addClass('visible');
          background.addClass('visible');
        }, 20);

        close_btn.on('click', function(e) {
          e.preventDefault();

          lightbox.removeClass('visible');
          background.removeClass('visible');

          setTimeout(function() {
            lightbox.remove();
            background.remove();
          }, 250);
        });
      });
    });
  });
})();