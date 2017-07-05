/**
 * @file entity_browser.view.js
 *
 * Defines the behavior of the entity browser's view widget.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  // Create Global variable.
  if (typeof DrupalIPFS === 'undefined') {
    window.DrupalIPFS = {
      node: null,
      status: false,
      ajaxLoad: true
    };
  }

  // Global namespace for IPFS javascript.
  Drupal.ipfs = window.DrupalIPFS;

  var loadImageBase64Src = function (hash, imgNode, isBase64) {
    console.log('Loading image', hash);

    var node = Drupal.ipfs.node;

    node.files.cat(hash, function (err, stream) {
      var rawRes = new Uint8Array([]);
      var base64Res = '';

      if (err) {
        throw err;
      }

      stream.on('data', function (chunk) {
        if (isBase64) {
          base64Res += chunk.toString();
        }
        else {
          var ui8 = new Uint8Array(chunk.buffer, chunk.byteOffset, chunk.byteLength / Uint8Array.BYTES_PER_ELEMENT);

          var mergerRawRes = new Uint8Array(rawRes.length + ui8.length);
          mergerRawRes.set(rawRes);
          mergerRawRes.set(ui8, rawRes.length);

          rawRes = mergerRawRes;
        }
      });

      stream.on('end', function () {
        if (isBase64) {
          imgNode.src = base64Res;
        }
        else {
          var b64encoded = btoa([].reduce.call(new Uint8Array(rawRes), function (p, c) {return p + String.fromCharCode(c)}, ''));
          imgNode.src = 'data:image/jpeg;base64,' + b64encoded;
        }
      });

      stream.on('error', function (err) {
        console.error('Error - ipfs files cat ', err);
      });
    });
  };

  /**
   * Load single IPFS image.
   *
   * @param {Object} imgNode
   *   Image element that should be loaded from IPFS.
   */
  var loadIpfsImage = function (imgNode) {
    if (Drupal.ipfs.status) {
      var hash = imgNode.getAttribute('data-ipfs-src');
      if (hash) {
        imgNode.removeAttribute('data-ipfs-src');
        loadImageBase64Src(hash, imgNode);

        return;
      }

      hash = imgNode.getAttribute('data-ipfs-src-base64');
      if (hash) {
        imgNode.removeAttribute('data-ipfs-src-base64');
        loadImageBase64Src(hash, imgNode, true);
      }
    }
  };
  Drupal.ipfs.loadIpfsImage = loadIpfsImage;

  /**
   * Load all images on page from IPFS.
   */
  var loadAllIpfsImages = function () {
    $('img[data-ipfs-src],img[data-ipfs-src-base64]').each(function () {
      loadIpfsImage(this);
    });
  };
  Drupal.ipfs.loadAllIpfsImages = loadAllIpfsImages;

  /**
   * Attach behaviour to load pages over Ajax
   *
   * @type {{attach: attach}}
   */
  Drupal.behaviors.loadLinksOverAjax = {
    attach: function () {
      // If loading of pages over Ajax is desabled, just return.
      if (!Drupal.ipfs.ajaxLoad) {
        return;
      }

      $('a').once('load-over-ajax').click(function () {
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

  /**
   * Registers behaviour to load IPFS when page is loaded.
   *
   * It will be also triggered when AJAX request is executed.
   */
  Drupal.behaviors.ipfsLoad = {
    attach: function () {
      // If IPFS node is already initialized, just load all images on page.
      if (Drupal.ipfs.status) {
        loadAllIpfsImages();

        return;
      }

      var repoPath = 'ipfs-' + Math.random();

      // Create an IPFS node
      Drupal.ipfs.node = new Ipfs({
        init: false,
        start: false,
        repo: repoPath
      });
      var node = Drupal.ipfs.node;

      // Init the node
      node.init(function (err) {
        if (err) {
          throw err;
        }

        node.start(function () {
          console.log('Online status: ', node.isOnline() ? 'online' : 'offline');

          Drupal.ipfs.status = node.isOnline();
          loadAllIpfsImages();
        });
      });
    }
  };

}(jQuery, Drupal, drupalSettings));
