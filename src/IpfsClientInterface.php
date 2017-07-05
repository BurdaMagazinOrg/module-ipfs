<?php

namespace Drupal\ipfs;

interface IpfsClientInterface {

  public function cat($hash);

  public function add($content);

  public function ls($hash);

  public function size($hash);

  public function pin($hash);

  public function version();
}
