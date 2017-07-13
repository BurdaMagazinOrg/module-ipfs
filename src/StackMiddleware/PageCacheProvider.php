<?php

namespace Drupal\ipfs\StackMiddleware;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\PageCache\ResponsePolicyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ipfs\IpfsHandler;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Class for providing cached pages to IPFS system.
 *
 * @package Drupal\ipfs\StackMiddleware
 */
class PageCacheProvider implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Response policy.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicyInterface
   */
  protected $responsePolicy;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * IPFS Handler service.
   *
   * @var \Drupal\ipfs\IpfsHandler
   */
  protected $ipfsHandler;

  /**
   * Constructs a IPFS cache provider.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param \Drupal\Core\PageCache\ResponsePolicyInterface $responsePolicy
   *   Response policy service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\ipfs\IpfsHandler $ipfsHandler
   *   IPFS handler service.
   */
  public function __construct(HttpKernelInterface $http_kernel, ResponsePolicyInterface $responsePolicy, AccountInterface $account, IpfsHandler $ipfsHandler) {
    $this->httpKernel = $http_kernel;
    $this->account = $account;
    $this->ipfsHandler = $ipfsHandler;
    $this->responsePolicy = $responsePolicy;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    $response = $this->httpKernel->handle($request, $type, $catch);

    if ($this->account->isAnonymous() && $this->isCacheable($request, $response)) {
      $this->ipfsHandler->add($this->getCacheId($request), 'page', $response->getContent());
    }

    return $response;
  }

  /**
   * Is cacheable logic is take from PageCache class.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   Response object.
   *
   * @return bool
   *   Returns if page is cacheable.
   */
  protected function isCacheable(Request $request, Response $response) {
    if (!$response instanceof CacheableResponseInterface) {
      return FALSE;
    }

    // Currently it is not possible to cache binary file or streamed responses:
    // https://github.com/symfony/symfony/issues/9128#issuecomment-25088678.
    // Therefore exclude them, even for subclasses that implement
    // CacheableResponseInterface.
    if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
      return FALSE;
    }

    // Allow policy rules to further restrict which responses to cache.
    if ($this->responsePolicy->check($response, $request) === ResponsePolicyInterface::DENY) {
      return FALSE;
    }

    // Ignore storing mapping to IPFS.
    if ($request->getRequestUri() === '/ipfs/mapping') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Gets the page cache ID for this request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object.
   *
   * @return string
   *   The cache ID for this request.
   */
  protected function getCacheId(Request $request) {
    $cid_parts = [
      $request->getSchemeAndHttpHost() . $request->getRequestUri(),
      $request->getRequestFormat(),
    ];

    return implode(':', $cid_parts);
  }

}
