<?php

namespace Drupal\ipfs;

/**
 *
 */
interface IpfsClientInterface {

  /**
   *
   */
  public function cat($hash);

  /**
   * Adds a file directly to IPFS.
   *
   * @param array $files
   *   A filepath to upload.
   * @param bool $pin
   *   Pin the file (true)
   *
   * @return string
   *   The content hash.
   */
  public function addFiles(array $files, $pin);

  /**
   * @param $content
   * @return mixed
   */
  public function addData($content);

  /**
   *
   */
  public function ls($hash);

  /**
   *
   */
  public function size($hash);

  /**
   *
   */
  public function pinAdd($hash);

  /**
   *
   */
  public function pinRemove($hash);

  /**
   *
   */
  public function get(array $query_vars);

  /**
   *
   */
  public function version();

}
