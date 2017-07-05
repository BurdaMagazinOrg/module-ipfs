<?php

namespace Drupal\ipfs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ipfs\IpfsHandler;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class IpfsMappingController.
 *
 * @package Drupal\ipfs\Controller
 */
class IpfsMappingController extends ControllerBase {

  /**
   * Drupal\ipfs\IpfsHandler definition.
   *
   * @var \Drupal\ipfs\IpfsHandler
   */
  protected $ipfsHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(IpfsHandler $ipfsHandler) {
    $this->ipfsHandler = $ipfsHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ipfs.handler')
    );
  }

  /**
   * Get IPFS mapping.
   *
   * @return mixed
   *   Return JSON of IPFS mapping.
   */
  public function getMapping() {
    return new JsonResponse($this->ipfsHandler->getMapping());
  }

}
