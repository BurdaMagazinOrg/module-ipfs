<?php

namespace Drupal\ipfs\Plugin\Field\FieldFormatter;

use Drupal\file\FileInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\ipfs\IpfsHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\image\Entity\ImageStyle;

/**
 * Plugin implementation of the 'ipfs_image_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "ipfs_image_formatter",
 *   label = @Translation("IPFS image formatter"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class IpfsImageFormatter extends ImageFormatter {

  /**
   * IPFS Handler service.
   *
   * @var \Drupal\ipfs\IpfsHandler
   */
  protected $ipfsHandler;

  /**
   * Constructs an ImageFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The entity storage for the image.
   * @param \Drupal\ipfs\IpfsHandler $ipfsHandler
   *   IPFS Handler service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, EntityStorageInterface $image_style_storage, IpfsHandler $ipfsHandler) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage);
    $this->ipfsHandler = $ipfsHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity.manager')->getStorage('image_style'),
      $container->get('ipfs.handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    $url = NULL;
    $image_link_setting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($image_link_setting == 'content') {
      $entity = $items->getEntity();
      if (!$entity->isNew()) {
        $url = $entity->urlInfo();
      }
    }
    elseif ($image_link_setting == 'file') {
      $link_file = TRUE;
    }

    $image_style_setting = $this->getSetting('image_style');

    // Collect cache tags to be added for each item in the field.
    $base_cache_tags = [];
    if (!empty($image_style_setting)) {
      $image_style = $this->imageStyleStorage->load($image_style_setting);
      $base_cache_tags = $image_style->getCacheTags();
    }

    foreach ($files as $delta => $file) {
      $cache_contexts = [];
      if (isset($link_file)) {
        $image_uri = $file->getFileUri();
        // @todo Wrap in file_url_transform_relative(). This is currently
        // impossible. As a work-around, we currently add the 'url.site' cache
        // context to ensure different file URLs are generated for different
        // sites in a multisite setup, including HTTP and HTTPS versions of the
        // same site. Fix in https://www.drupal.org/node/2646744.
        $url = Url::fromUri(file_create_url($image_uri));
        $cache_contexts[] = 'url.site';
      }
      $cache_tags = Cache::mergeTags($base_cache_tags, $file->getCacheTags());

      // Extract field item attributes for the theme function, and unset them
      // from the $item so that the field template does not re-render them.
      $item = $file->_referringItem;
      $item_attributes = $item->_attributes;
      unset($item->_attributes);

      // Add IPFS info to Img tag.
      $hash = $this->ipfsHandler->getHash($this->getUid($file), 'file');

      if (empty($hash)) {
        $hash = $this->ipfsAdd($file);
      }

      $item_attributes['data-ipfs-src-base64'] = $hash;

      $elements[$delta] = [
        '#theme' => 'image_formatter',
        '#item' => $item,
        '#item_attributes' => $item_attributes,
        '#image_style' => $image_style_setting,
        '#url' => $url,
        '#cache' => [
          'tags' => $cache_tags,
          'contexts' => $cache_contexts,
        ],
      ];
      $elements[$delta]['#attached']['library'][] = 'ipfs/ipfs.image';

      if ($this->currentUser->isAnonymous()) {
        $elements[$delta]['#attached']['library'][] = 'ipfs/ipfs.ajax-content-loader';
      }
    }

    return $elements;
  }

  /**
   * Add file to IPFS.
   *
   * @param \Drupal\file\FileInterface $image
   *   The file entity.
   *
   * @return string
   *   The file hash.
   */
  protected function ipfsAdd(FileInterface $image) {
    $mime = $image->getMimeType();

    if ($image_style = $this->getSetting('image_style')) {
      $style = ImageStyle::load('thumbnail');
      $uri = $style->buildUri($image->getFileUri());
    }
    else {
      $uri = $image->getFileUri($image_style);
    }

    $content = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($uri));

    /** @var \Drupal\ipfs\IpfsHandler $ipfs */
    $ipfs = \Drupal::service('ipfs.handler');
    return $ipfs->add($this->getUid($image), 'file', $content);
  }

  /**
   * Create a uid for a file with an image style.
   *
   * @param \Drupal\file\FileInterface $image
   *   The file entity.
   *
   * @return string
   *   The uid for this image.
   */
  protected function getUid(FileInterface $image) {
    $uid_parts = [$image->id()];

    if ($image_style = $this->getSetting('image_style')) {
      $uid_parts[] = $image_style;
    }

    return implode(':', $uid_parts);
  }

}
