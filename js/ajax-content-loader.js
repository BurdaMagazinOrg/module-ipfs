(function ($, Drupal, drupalSettings) {

  'use strict';

  /* global DrupalIPFSMapping */

  var getAvailablePageHash = function (url) {
    var hash = '';

    if (typeof DrupalIPFSMapping !== 'undefined') {
      hash = DrupalIPFSMapping.getPageHash(url);
    }

    return hash;
  };

  /**
   * Attach behaviour to load pages over Ajax
   *
   * @type {{attach: attach}}
   */
  Drupal.behaviors.loadContentOverAjax = {
    attach: function () {
      $('a').once('load-over-ajax').click(function () {
        var hash = getAvailablePageHash(this.href);
        if (hash && Drupal.ipfs.status) {
          // eslint-disable-next-line no-console
          console.log('Loading page', hash);

          var node = Drupal.ipfs.node;

          node.files.cat(hash, function (err, stream) {
            var pageResult = '';

            if (err) {
              throw err;
            }

            stream.on('data', function (chunk) {
              pageResult += chunk.toString();
            });

            stream.on('end', function () {
              $('body').html(pageResult);
            });

            stream.on('error', function (err) {
              // eslint-disable-next-line no-console
              console.error('Error - ipfs files cat ', err);
            });
          });
        }
        else {
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
        }

        history.replaceState({}, '', this.href);

        return false;
      });
    }
  };

}(jQuery, Drupal, drupalSettings));
