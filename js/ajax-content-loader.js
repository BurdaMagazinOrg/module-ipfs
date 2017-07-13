(function ($, Drupal, drupalSettings) {

  'use strict';

  /* global DrupalIPFSMapping */

  var loadFromIpfs = function (hash, responseHandler) {
    // eslint-disable-next-line no-console
    console.log('Loading content from IPFS', hash);

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
        responseHandler(pageResult);
      });

      stream.on('error', function (err) {
        // eslint-disable-next-line no-console
        console.error('Error - ipfs files cat ', err);
      });
    });
  };

  var loadFromUrl = function (url, responseHandler) {
    // eslint-disable-next-line no-console
    console.log('Loading content from URL', url);

    $.ajax({
      type: 'get',
      data: {},
      url: url,
      dataType: 'html',
      success: function (response) {
        responseHandler(response);
      }
    });
  };

  var getAvailableAssetHash = function (url) {
    var hash = '';

    if (typeof DrupalIPFSMapping !== 'undefined') {
      hash = DrupalIPFSMapping.getAssetHash(url);
    }

    return hash;
  };

  var getAvailablePageHash = function (url) {
    var hash = '';

    if (typeof DrupalIPFSMapping !== 'undefined') {
      hash = DrupalIPFSMapping.getPageHash(url);
    }

    return hash;
  };

  var processCssResponse = function (response) {
    var s = document.createElement('style');
    s.innerHTML = response;

    $('head').append(s);
  };

  var processPageResponse = function (response) {
    var el = document.createElement('html');
    el.innerHTML = response;
    var $element = $(el);

    // Load CSS for new page and remove old one.
    $element.find('head link[rel="stylesheet"][media="all"]').each(loadCss);
    $('head link[rel="stylesheet"][media="all"]').remove();

    // Prepare JS links.
    var jsUrls = [];
    $element.find('script[src]').each(function () {
      jsUrls.push(this.src);
      $(this).remove();
    });

    // Load body.
    $('body').replaceWith($element.find('body'));

    // Load Javascript files after body content is placed.
    jsUrls.forEach(loadJs);
  };

  /**
   * Loading of javascript over ajax requeest or IPFS.
   *
   * jQuery.globalEval() is used to execute fetched JS code.
   *
   * @param {string} url
   *   Url to JS asset.
   */
  var loadJs = function (url) {
    var hash = getAvailableAssetHash(url);
    if (hash && Drupal.ipfs.status) {
      loadFromIpfs(hash, $.globalEval);
    }
    else {
      loadFromUrl(url, $.globalEval);
    }
  };

  var loadCss = function () {
    var url = this.href;

    var hash = getAvailableAssetHash(url);
    if (hash && Drupal.ipfs.status) {
      loadFromIpfs(hash, processCssResponse);
    }
    else {
      loadFromUrl(url, processCssResponse);
    }
  };

  var loadPage = function () {
    var hash = getAvailablePageHash(this.href);
    if (hash && Drupal.ipfs.status) {
      loadFromIpfs(hash, processPageResponse);
    }
    else {
      loadFromUrl(this.href, processPageResponse);
    }

    // Set correct URL in address bar.
    history.replaceState({}, '', this.href);

    return false;
  };

  /**
   * Attach behaviour to load pages over Ajax
   *
   * @type {{attach: attach}}
   */
  Drupal.behaviors.registerLoadPagesOverAjax = {
    attach: function () {
      $('a').once('load-over-ajax').click(loadPage);
    }
  };

}(jQuery, Drupal, drupalSettings));
