(function ($, Drupal, drupalSettings) {

  'use strict';

  /* global DrupalIPFSMapping */

  // Create Global variable.
  if (typeof DrupalIPFSMapping === 'undefined') {
    window.DrupalIPFSMapping = {
      loader: null,
      data: []
    };
  }

  /**
   * Get page hash from IPFS mapping.
   *
   * @param {string} url
   *   Url for page.
   *
   * @return {string}
   *   IPFS hash for page.
   */
  DrupalIPFSMapping.getPageHash = function (url) {
    var mappingData = DrupalIPFSMapping.data;
    var cid = url + ':html';

    for (var i = 0; i < mappingData.length; i++) {
      var mappingEntry = mappingData[i];

      if (mappingEntry.type === 'page' && mappingEntry.uid === cid) {
        return mappingEntry.hash;
      }
    }

    return '';
  };

  DrupalIPFSMapping.getAssetHash = function (url) {
    var mappingData = DrupalIPFSMapping.data;

    for (var i = 0; i < mappingData.length; i++) {
      var mappingEntry = mappingData[i];

      if ((mappingEntry.type === 'css' || mappingEntry.type === 'js') && url.indexOf(mappingEntry.uid) !== -1) {
        return mappingEntry.hash;
      }
    }

    return '';
  };

  var loadMapping = function () {
    $.ajax({
      type: 'get',
      data: {},
      url: '/ipfs/mapping',
      dataType: 'html',
      success: function (response) {
        DrupalIPFSMapping.data = JSON.parse(response);
      }
    });
  };

  var initMapLoader = function () {
    loadMapping();

    // Load mapping every 10sec
    DrupalIPFSMapping.loader = setInterval(loadMapping, 10000);
  };

  /**
   * Attach behaviour to load pages over Ajax
   *
   * @type {{attach: attach}}
   */
  Drupal.behaviors.loadIpfsMapping = {
    attach: function () {
      if (!DrupalIPFSMapping.loader) {
        initMapLoader();
      }
    }
  };

}(jQuery, Drupal, drupalSettings));
