<?php

namespace Drupal\ipfs;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelInterface;
use Exception;
use PDO;

/**
 * IPFS Handler class.
 *
 * @package Drupal\ipfs
 */
class IpfsHandler {

  /**
   * IPFS Client.
   *
   * @var \Drupal\ipfs\IpfsClient
   */
  protected $ipfsClient;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagInvalidator;

  /**
   * IpfsHandler constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagInvalidator
   *   Cache tags invalidator service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   IPFS logger channel.
   */
  public function __construct(ConfigFactoryInterface $configFactory, CacheTagsInvalidatorInterface $cacheTagInvalidator, Connection $database, LoggerChannelInterface $logger) {
    $this->settings = $configFactory->get('ipfs.settings');
    $this->cacheTagInvalidator = $cacheTagInvalidator;
    $this->logger = $logger;
    $this->database = $database;
  }

  /**
   * Get IPFS client.
   *
   * @return \Drupal\ipfs\IpfsClient
   *   Return IPFS client.
   */
  protected function getClient() {
    if (!isset($this->ipfsClient)) {
      $settings = $this->settings;

      $this->ipfsClient = new IpfsClient(
        $settings->get('gateway.address'),
        $settings->get('gateway.port'),
        $settings->get('gateway.api_port')
      );
    }

    return $this->ipfsClient;
  }

  /**
   * Add content to IPFS system over gateway.
   *
   * @param string $uid
   *   Unique ID for page IPFS mapping.
   * @param string $type
   *   Type of mapping tyo save.
   * @param mixed $content
   *   Content that should be added to IPFS.
   *
   * @return mixed
   *   Returns hash of added content.
   */
  public function add($uid, $type, $content) {
    $hash = '';

    try {
      $hash = $this->getClient()->addData($content);
      $this->storeMapping($uid, $type, $hash);
    }
    catch (Exception $e) {
      $this->logger->error('Could not add content to IPFS: @message', ['@message' => $e->getMessage()]);
    }

    return $hash;
  }

  /**
   * Get hash if available for uid and type.
   *
   * @param string $uid
   *   Unique ID for page IPFS mapping.
   * @param string $type
   *   Type of mapping tyo save.
   *
   * @return mixed
   *   Returns hash for uid and type.
   */
  public function getHash($uid, $type) {
    $query = $this->database->select('ipfs_mapping', 'i');
    $query->addField('i', 'hash');
    $query->condition('i.uid', $uid);
    $query->condition('i.type', $type);

    return $query->execute()->fetchField();
  }

  /**
   * Store mapping for cached pages.
   *
   * @param string $uid
   *   Unique ID for page IPFS mapping.
   * @param string $type
   *   Type of mapping tyo save.
   * @param string $hash
   *   Hash of page.
   */
  protected function storeMapping($uid, $type, $hash) {
    if ($this->getHash($uid, $type)) {
      $this->database->update('ipfs_mapping')
        ->fields(['hash' => $hash])
        ->condition('uid', $uid)
        ->condition('type', $type)
        ->execute();
    }
    else {
      $fields = [
        'uid' => $uid,
        'type' => $type,
        'hash' => $hash,
      ];

      $this->database->insert('ipfs_mapping')->fields($fields)->execute();
    }

    // Invalidate cache for IPFS mapping, because there are new changes.
    $this->cacheTagInvalidator->invalidateTags(['ipfs_get_mapping']);
  }

  /**
   * Get IPFS mapping.
   *
   * @return mixed
   *   Stored IPFS mapping.
   */
  public function getMapping() {
    $query = $this->database->select('ipfs_mapping', 'i');
    $query->fields('i', ['uid', 'type', 'hash']);

    return $query->execute()->fetchAll(PDO::FETCH_ASSOC);
  }

}
