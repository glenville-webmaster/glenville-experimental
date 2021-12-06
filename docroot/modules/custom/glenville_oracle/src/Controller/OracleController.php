<?php

namespace Drupal\glenville_oracle\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\glenville_oracle\PdfCourseRotation;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use Drupal\Core\Cache\DatabaseBackend;

/**
 * Class OracleController.
 */
class OracleController extends ControllerBase {

  /**
   * The key to the data.
   */
  const GLENVILLE_API_KEY = '98xs-sf23-8sfs-12sa';

  /**
   * GuzzleHttp\Client definition.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Drupal\Core\Cache\DatabaseBackend definition.
   *
   * @var \Drupal\Core\Cache\DatabaseBackend
   */
  protected $cache;

  /**
   * Constructs a new OracleController object.
   */
  public function __construct(Client $http_client, DatabaseBackend $cache_default) {
    $this->httpClient = $http_client;
    $this->cache = $cache_default;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('cache.default')
    );
  }

  /**
   * Generaterotation.
   *
   * @return mixed
   *   Return generated PDF.
   */
  public function generateRotation() {

    $courses = $this->getOracleData(GLENVILLE_ORACLE_REMOTE_URL . '?resource=rotation', 'rotation', 60);
    if (!$courses) {
      drupal_set_message('Unable to load courses at this time, please try again later', 'error');
      return [];
    }

    $pdf = new PdfCourseRotation($courses->data);
    $pdf->generateCourseRotation();

    return [];

  }

  protected function getOracleData($path, $cid, $expire = 20) {
    $cid = 'glenville_oracle:' . $cid;
    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
    }
    else {
      // Don't cache if bad response
      if ($data = $this->callAPI($path)) {
        $this->cache->set($cid, $data, time() + $expire);
      }
    }
    return $data;
  }

  protected function callAPI($path) {
    try {
      $request = $this->httpClient->post($path, ['headers' => ['glenville-key' => self::GLENVILLE_API_KEY]]);
      $response = json_decode((string) $request->getBody());
      if (empty($response->count)) {
        return FALSE;
      }
    } catch (RequestException $e) {
      return FALSE;
    }
    return $response;
  }

}
