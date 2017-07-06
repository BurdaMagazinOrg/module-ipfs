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

  var loadFromIpfs = function (hash) {
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
  };

  var loadDirect = function (url) {
    // eslint-disable-next-line no-console
    console.log('Loading page', url);

    $.ajax({
      type: 'get',
      data: {},
      url: url,
      dataType: 'html',
      success: function (response) {
        $('body').html(response);
      }
    });
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
          loadFromIpfs(hash);
        }
        else {
          loadDirect(this.href);
        }

        // Set correct URL in address bar.
        history.replaceState({}, '', this.href);

        return false;
      });
    }
  };

}(jQuery, Drupal, drupalSettings));
