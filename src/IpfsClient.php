<?php

namespace Drupal\ipfs;

/**
 *
 */
class IpfsClient implements IpfsClientInterface {
  private $apiVersion;
  private $gatewayIP;
  private $gatewayPort;
  private $gatewayApiPort;

  /**
   * IpfsClient constructor.
   *
   * @param string $ip
   * @param int $port
   * @param int $apiPort
   * @param string $apiVersion
   */
  public function __construct($ip = "localhost", $port = 8080, $apiPort = 5001, $apiVersion = 'v0') {
    $this->gatewayIP      = $ip;
    $this->gatewayPort    = $port;
    $this->gatewayApiPort = $apiPort;
    $this->apiVersion     = $apiVersion;
  }

  /**
   *
   */
  public function cat($hash) {
    $url = 'http://' . $this->gatewayIP . ':' . $this->gatewayPort . '/ipfs/' . $hash;
    return $this->curl($url);
  }

  /**
   *
   */
  public function addFiles(array $files, $pin = TRUE) {
    $query_vars = [
      'pin' => ($pin ? 'true' : 'false'),
    ];
    $response = $this->postFile($files, $query_vars);
    return $response['Hash'];
  }

  /**
   * @param $content
   * @return string
   */
  public function addData($data) {
    $content = ['data' => $data];
    $response = $this->apiCall('add?stream-channels=true', $content);
    return $response['Hash'];
  }

  /**
   *
   */
  public function ls($hash) {
    $response = $this->apiCall("ls/$hash");
    return $response['Objects'][0]['Links'];
  }

  /**
   *
   */
  public function size($hash) {
    $response = $this->apiCall("object/stat/$hash");
    return $response['CumulativeSize'];
  }

  /**
   *
   */
  public function pinAdd($hash) {
    return $this->apiCall("add/$hash");
  }

  /**
   *
   */
  public function pinRemove($hash) {
    return $this->apiCall("rm/$hash");
  }

  /**
   *
   */
  public function version() {
    $response = $this->apiCall('version');
    return $response["Version"];
  }

  /**
   *
   */
  public function get(array $query_vars = []) {
    $query_string = $query_vars ? '?' . http_build_query($query_vars) : '';
    return $this->apiCall("get" . $query_string);
  }

  /**
   *
   */
  protected function postFile(array $files, array $query_vars = [], $expect_multiple = FALSE) {
    $query = $query_vars ? '?' . http_build_query($query_vars) : '';
    $content = ['files' => $files];
    $response = $this->apiCall("add" . $query, $content);
    if ($expect_multiple) {
      $data = [];
      foreach (explode("\n", $response) as $json_data_line) {
        if (strlen(trim($json_data_line))) {
          $data[] = $json_data_line;
        }
      }
    }
    else {
      $data = $response;
    }
    return $data;
  }

  /**
   *
   */
  protected function apiCall($query, array $content = []) {
    $url = 'http://' . $this->gatewayIP . ':' . $this->gatewayApiPort . '/api/' . $this->apiVersion . '/' . $query;
    return $this->curl($url, $content);
  }


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
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data; boundary=a831rwxi1a3gzaorw1w2z49dlsor'));
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, "--a831rwxi1a3gzaorw1w2z49dlsor\r\nContent-Type: application/octet-stream\r\nContent-Disposition: file; \r\n\r\n" . $content['data'] . "\r\n--a831rwxi1a3gzaorw1w2z49dlsor");
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
