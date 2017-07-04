/**
 * @file entity_browser.view.js
 *
 * Defines the behavior of the entity browser's view widget.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.ipfs = {
    node: null,
    status: false
  };


  var loadImageBase64 = function (hash, imgNode, isBase64) {
    console.log('Load image', hash);

    var node = Drupal.ipfs.node;

    node.files.cat(hash, function (err, stream) {
      var res = new Uint8Array([]);
      var resBase64 = '';

      stream.on('data', function (chunk) {
        if (isBase64) {
          resBase64 += chunk.toString();
        }
        else {
          var ui8 = new Uint8Array(chunk.buffer, chunk.byteOffset, chunk.byteLength / Uint8Array.BYTES_PER_ELEMENT);

          var tmpRes = new Uint8Array(res.length + ui8.length);
          tmpRes.set(res);
          tmpRes.set(ui8, res.length);

          res = tmpRes;
        }
      });

      stream.on('end', function () {
        if (isBase64) {
          imgNode.src = 'data:image/jpeg;base64,' + resBase64;
        }
        else {
          var b64encoded = btoa([].reduce.call(new Uint8Array(res), function (p, c) {return p + String.fromCharCode(c)}, ''));
          imgNode.src = 'data:image/jpeg;base64,' + b64encoded;
        }
      });

      stream.on('error', function (err) {
        console.error('Error - ipfs files cat ', err)
      });
    });
  };

  var loadIpfsImage = function (imgNode) {
    console.log(imgNode);

    if (Drupal.ipfs.status) {
      var hash = imgNode.getAttribute('data-ipfs-src');
      if (hash) {
        imgNode.removeAttribute('data-ipfs-src');
        loadImageBase64(hash, imgNode);

        return;
      }

      hash = imgNode.getAttribute('data-ipfs-src-base64');
      if (hash) {
        imgNode.removeAttribute('data-ipfs-src-base64');
        loadImageBase64(hash, imgNode, true);
      }
    }
  };

  /**
   * Registers behaviours related to view widget.
   */
  Drupal.behaviors.ipfsLoad = {
    attach: function (context) {
      var repoPath = 'ipfs-' + Math.random();

      // Create an IPFS node
      Drupal.ipfs.node = new Ipfs({
        init: false,
        start: false,
        repo: repoPath
      });
      var node = Drupal.ipfs.node;

      // Init the node
      node.init(handleInit);

      function handleInit(err) {
        if (err) {
          throw err;
        }

        node.start(function () {
          console.log('Online status: ', node.isOnline() ? 'online' : 'offline');

          Drupal.ipfs.status = node.isOnline();

          $('img').each(function () {
            loadIpfsImage(this);
          });
        });
      }
    }
  };

}(jQuery, Drupal, drupalSettings));
