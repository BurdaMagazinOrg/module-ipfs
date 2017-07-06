<?php

namespace Drupal\ipfs;

/**
 * The interface for an IPFS client.
 */
interface IpfsClientInterface {

  /**
   * Retrieve the contents of a file.
   *
   * @param string $hash
   *   The hash of the file.
   *
   * @return string
   *   The content of the file.
   */
  public function cat($hash);

  /**
   * Adds files to IPFS.
   *
   * @param array $files
   *   Array of local filepath to upload.
   * @param bool $pin
   *   Whether to pin the file or not.
   *
   * @return string
   *   The gateway response.
   */
  public function addFiles(array $files, $pin);

  /**
   * Add a data blob to IPFS to create a new file.
   *
   * @param string $content
   *   The data that gets put into the file.
   *
   * @return string
   *   The hash of the created file.
   */
  public function addData($content);

  /**
   * List directory contents.
   *
   * @param string $hash
   *   The hash of the path to list contents from.
   *
   * @return array
   *   The content of the given directory path.
   */
  public function ls($hash);

  /**
   * Get the size of an object.
   *
   * @param string $hash
   *   The hash of the object.
   *
   * @return string
   *   The size of the object.
   */
  public function size($hash);

  /**
   * Pin an object.
   *
   * @param string $hash
   *   The hash of the object.
   *
   * @return array
   *   The gateway response.
   */
  public function pinAdd($hash);

  /**
   * Unpin an object.
   *
   * @param string $hash
   *   The hash of the object.
   *
   * @return array
   *   The gateway response.
   */
  public function pinRemove($hash);

  /**
   * Get the version of the gateway.
   *
   * @return string
   *   The version of the gateway.
   */
  public function version();

}
