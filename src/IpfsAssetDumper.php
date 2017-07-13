<?php

namespace Drupal\ipfs;

use Drupal\Core\Asset\AssetDumper;

/**
 * Extension of asset dumper class to store generated files to IPFS.
 *
 * @package Drupal\ipfs
 */
class IpfsAssetDumper extends AssetDumper {

  /**
   * IPFS Handler service.
   *
   * @var \Drupal\ipfs\IpfsHandler
   */
  protected $ipfsHandler;

  /**
   * IpfsAssetDumper constructor.
   */
  public function __construct(IpfsHandler $ipfsHandler) {
    $this->ipfsHandler = $ipfsHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function dump($data, $file_extension) {
    $uri = parent::dump($data, $file_extension);

    $uid = preg_replace('/^public:\//', '', $uri);
    $type = trim(substr($uid, 0, 4), '/');

    $this->ipfsHandler->add($uid, $type, $data);

    return $uri;
  }

}
