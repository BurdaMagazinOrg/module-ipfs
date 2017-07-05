(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Attach behaviour to load pages over Ajax
   *
   * @type {{attach: attach}}
   */
  Drupal.behaviors.loadContentOverAjax = {
    attach: function () {
      $('a').once('load-over-ajax').click(function () {
        // eslint-disable-next-line no-console
        console.log('Loading page', this.href);

        $.ajax({
          type: 'get',
          data: {},
          url: this.href,
          dataType: 'html',
          success: function (response) {
            $('body').html(response);
          }
        });

        history.replaceState({}, '', this.href);

        return false;
      });
    }
  };

}(jQuery, Drupal, drupalSettings));
