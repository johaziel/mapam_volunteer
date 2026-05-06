(function ($, Drupal, once) {
  Drupal.behaviors.fixModalClose = {
    attach: function (context) {
      once('fixClose', '.ui-dialog-titlebar-close', context).forEach(function () {
        if (!$(this).find('.ui-icon').length) {
          $(this).html('<span class="ui-icon ui-icon-closethick"></span>');
        }

      });
    }
  };
})(jQuery, Drupal, once);