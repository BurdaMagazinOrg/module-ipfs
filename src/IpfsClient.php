<?php

namespace Drupal\ipfs;

/**
 * Implementation of an IPFS client.
 */
class IpfsClient implements IpfsClientInterface {

  /**
   * The address of the ipfs gateway, defaults to localhost.
   *
   * @var string
   */
  private $gatewayAddress;

  /**
   * The readonly port of the gateway, defaults to 8080.
   *
   * @var int
   */
  private $gatewayPort;

  /**
   * The API port of the gateway, defaults to 5001.
   *
   * @var int
   */
  private $gatewayApiPort;

  /**
   * The API version to use, currently defaults to v0.
   *
   * @var string
   */
  private $gatewayApiVersion;

  /**
   * IpfsClient constructor.
   *
   * @param string $gateway_address
   *   The address of the ipfs gateway.
   * @param int $gateway_port
   *   The readonly port of the gateway.
   * @param int $gateway_api_port
   *   The API port of the gateway.
   * @param string $gateway_api_version
   *   The API version to use.
   */
  public function __construct($gateway_address = "localhost", $gateway_port = 8080, $gateway_api_port = 5001, $gateway_api_version = 'v0') {
    $this->gatewayAddress    = $gateway_address;
    $this->gatewayPort       = $gateway_port;
    $this->gatewayApiPort    = $gateway_api_port;
    $this->gatewayApiVersion = $gateway_api_version;
  }

  /**
   * {@inheritdoc}
   */
  public function cat($hash) {
    $url = 'http://' . $this->gatewayAddress . ':' . $this->gatewayPort . '/ipfs/' . $hash;
    return $this->curl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function addFiles(array $files, $pin = TRUE) {
    $query_vars = [
      'pin' => ($pin ? 'true' : 'false'),
    ];
    $response = $this->postFile($files, $query_vars);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function addData($data) {
    $content = ['data' => $data];
    $response = $this->apiCall('add?stream-channels=true', $content);
    return $response['Hash'];
  }

  /**
   * {@inheritdoc}
   */
  public function ls($hash) {
    $response = $this->apiCall("ls/$hash");
    return $response['Objects'][0]['Links'];
  }

  /**
   * {@inheritdoc}
   */
  public function size($hash) {
    $response = $this->apiCall("object/stat/$hash");
    return $response['CumulativeSize'];
  }

  /**
   * {@inheritdoc}
   */
  public function pinAdd($hash) {
    return $this->apiCall("add/$hash");
  }

  /**
   * {@inheritdoc}
   */
  public function pinRemove($hash) {
    return $this->apiCall("rm/$hash");
  }

  /**
   * {@inheritdoc}
   */
  public function version() {
    $response = $this->apiCall('version');
    return $response["Version"];
  }

  /**
   * Creates gateway "add" requests for one or multiple files to IPFS.
   *
   * @param array $files
   *   Array of local filepaths to add.
   * @param array $query_vars
   *   Array of additional query variables, that should be added to the request.
   *   The variables have to key-value pairs, that can be used by
   *   http_build_query.
   *
   * @return array
   *   Array of responses for all files.
   *
   * @TODO: The connection between local files and the response should be clearer.
   */
  protected function postFile(array $files, array $query_vars = []) {
    $query = $query_vars ? '?' . http_build_query($query_vars) : '';
    $content = ['files' => $files];
    $response = $this->apiCall("add" . $query, $content);

    $data = [];
    foreach (explode("\n", $response) as $json_data_line) {
      if (strlen(trim($json_data_line))) {
        $data[] = $json_data_line;
      }
    }

    return $data;
  }

  /**
   * Call to the API.
   *
   * @param string $query
   *   The query string to send to the API.
   * @param array $content
   *   The conrent array. This can have two different keys.
   *   Either 'files', which is an array of file paths to add to IPFS,
   *   or 'data', which is a blob of data, that should be stored in a file on
   *   IPFS.
   *   TODO: refactor to be more clear and to be able to upload multiple blobs.
   *
   * @return mixed
   *   The response.
   */
  protected function apiCall($query, array $content = []) {
    $url = 'http://' . $this->gatewayAddress . ':' . $this->gatewayApiPort . '/api/' . $this->gatewayApiVersion . '/' . $query;
    return $this->curl($url, $content);
  }

  /**
   * Create and handle the http call to the gateway.
   *
   * @param string $url
   *   The endpoint to call.
   * @param array $content
   *   The conrent array. This can have two different keys.
   *   Either 'files', which is an array of file paths to add to IPFS,
   *   or 'data', which is a blob of data, that should be stored in a file on
   *   IPFS.
   *   TODO: refactor to be more clear and to be able to upload multiple blobs.
   *
   * @return mixed
   *   The response to the call.
   *
   * @throws \Exception
   */
  protected function curl($url, array $content = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 240);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

    if ($content['files']) {
      $post_fields = [];
      foreach ($content['files'] as $offset => $filepath) {
        // Add the file.
        $cfile = curl_file_create($filepath, 'application/octet-stream', basename($filepath));
        $post_fields['file' . sprintf('%03d', $offset + 1)] = $cfile;
      }
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    }
    elseif ($content['data']) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data; boundary=a831rwxi1a3gzaorw1w2z49dlsor']);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, "--a831rwxi1a3gzaorw1w2z49dlsor\r\nContent-Type: application/octet-stream\r\nContent-Disposition: file; \r\n\r\n" . $content['data'] . "\r\n--a831rwxi1a3gzaorw1w2z49dlsor\r\n");
    }

    $output = curl_exec($ch);

    // Check HTTP response code.
    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $code_category = substr($response_code, 0, 1);
    if ($code_category == '5' or $code_category == '4') {
      $data = @json_decode($output, TRUE);
      if (!$data and json_last_error() != JSON_ERROR_NONE) {
        throw new \Exception("IPFS returned response code $response_code: " . substr($output, 0, 200), $response_code);
      }
      if (is_array($data)) {
        if (isset($data['Code']) and isset($data['Message'])) {
          throw new \Exception("IPFS Error {$data['Code']}: {$data['Message']}", $response_code);
        }
      }
    }
    // Handle empty response.
    if ($output === FALSE) {
      throw new \Exception("IPFS Error: No Response", 1);
    }
    curl_close($ch);

    return json_decode($output, TRUE);
  }

}
