<?php
/**
 * Copyright 2014 Facebook, Inc.
 *
 * You are hereby granted a non-exclusive, worldwide, royalty-free license to
 * use, copy, modify, and distribute this software in source code or binary
 * form for use in connection with the web services and APIs provided by
 * Facebook.
 *
 * As with any software that integrates with the Facebook platform, your use
 * of this software is subject to the Facebook Developer Principles and
 * Policies [http://developers.facebook.com/policy/]. This copyright notice
 * shall be included in all copies or substantial portions of the software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 */
namespace Facebook;

use Facebook\Entities\FacebookApp;
use Facebook\Entities\AccessToken;
use Facebook\Entities\FacebookRequest;
use Facebook\Entities\FacebookBatchRequest;
use Facebook\Entities\FacebookResponse;
use Facebook\Entities\FacebookBatchResponse;
use Facebook\FileUpload\FacebookFile;
use Facebook\FileUpload\FacebookVideo;
use Facebook\GraphNodes\GraphList;
use Facebook\Url\UrlDetectionInterface;
use Facebook\Url\FacebookUrlDetectionHandler;
use Facebook\HttpClients\FacebookHttpClientInterface;
use Facebook\HttpClients\FacebookCurlHttpClient;
use Facebook\HttpClients\FacebookStreamHttpClient;
use Facebook\HttpClients\FacebookGuzzleHttpClient;
use Facebook\PersistentData\PersistentDataInterface;
use Facebook\PersistentData\FacebookSessionPersistentDataHandler;
use Facebook\PersistentData\FacebookMemoryPersistentDataHandler;
use Facebook\Helpers\FacebookRedirectLoginHelper;
use Facebook\Exceptions\FacebookSDKException;

/**
 * Class Facebook
 * @package Facebook
 *
 * @TODO Add helpers to superclass
 */
class Facebook
{

  /**
   * @const string Version number of the Facebook PHP SDK.
   */
  const VERSION = '4.1.0-dev';

  /**
   * @const string Default Graph API version for requests.
   */
  const DEFAULT_GRAPH_VERSION = 'v2.2';

  /**
   * @const string The name of the environment variable
   *               that contains the app ID.
   */
  const APP_ID_ENV_NAME = 'FACEBOOK_APP_ID';

  /**
   * @const string The name of the environment variable
   *               that contains the app secret.
   */
  const APP_SECRET_ENV_NAME = 'FACEBOOK_APP_SECRET';

  /**
   * @var FacebookApp The FacebookApp entity.
   */
  protected $app;

  /**
   * @var FacebookClient The Facebook client service.
   */
  protected $client;

  /**
   * @var UrlDetectionInterface|null The URL detection handler.
   */
  protected $urlDetectionHandler;

  /**
   * @var AccessToken|null The default access token to use with requests.
   */
  protected $defaultAccessToken;

  /**
   * @var string|null The default Graph version we want to use.
   */
  protected $defaultGraphVersion;

  /**
   * @var PersistentDataInterface|null The persistent data handler.
   */
  protected $persistentDataHandler;

  /**
   * @var FacebookResponse|FacebookBatchResponse|null Stores the last request made to Graph.
   */
  protected $lastResponse;

  /**
   * @TODO Add FacebookInputInterface
   * @TODO Add FacebookRandomGeneratorInterface
   * @TODO Add FacebookRequestInterface
   * @TODO Add FacebookResponseInterface
   */

  /**
   * Instantiates a new Facebook super-class object.
   *
   * @param array $config
   *
   * @throws FacebookSDKException
   */
  public function __construct(array $config = [])
  {
    $appId = isset($config['app_id'])
      ? $config['app_id']
      : getenv(static::APP_ID_ENV_NAME);
    if ( ! $appId) {
      throw new FacebookSDKException(
        'Required "app_id" key not supplied in config and'
        . ' could not find fallback environment variable "' . static::APP_ID_ENV_NAME . '"'
      );
    }

    $appSecret = isset($config['app_secret'])
      ? $config['app_secret']
      : getenv(static::APP_SECRET_ENV_NAME);
    if ( ! $appSecret) {
      throw new FacebookSDKException(
        'Required "app_secret" key not supplied in config and'
        . ' could not find fallback environment variable "' . static::APP_SECRET_ENV_NAME . '"'
      );
    }

    $this->app = new FacebookApp($appId, $appSecret);

    $httpClientHandler = null;
    if (isset($config['http_client_handler'])) {
      if ($config['http_client_handler'] instanceof FacebookHttpClientInterface) {
        $httpClientHandler = $config['http_client_handler'];
      } elseif ($config['http_client_handler'] === 'curl') {
        $httpClientHandler = new FacebookCurlHttpClient();
      } elseif ($config['http_client_handler'] === 'stream') {
        $httpClientHandler = new FacebookStreamHttpClient();
      } elseif ($config['http_client_handler'] === 'guzzle') {
        $httpClientHandler = new FacebookGuzzleHttpClient();
      } else {
        throw new \InvalidArgumentException(
          'The http_client_handler must be set to "curl", "stream", "guzzle", '
          . ' or be an instance of Facebook\HttpClients\FacebookHttpClientInterface'
        );
      }
    }
    $enableBeta = isset($config['enable_beta_mode']) && $config['enable_beta_mode'] === true;
    $caCertificateBundle = isset($config['ca_bundle']) ? $config['ca_bundle'] : null;
    $this->client = new FacebookClient($httpClientHandler, $enableBeta, $caCertificateBundle);

    if (isset($config['url_detection_handler'])) {
      if ($config['url_detection_handler'] instanceof UrlDetectionInterface) {
        $this->urlDetectionHandler = $config['url_detection_handler'];
      } else {
        throw new \InvalidArgumentException(
          'The url_detection_handler must be an instance of Facebook\Url\UrlDetectionInterface'
          );
      }
    }

    if (isset($config['persistent_data_handler'])) {
      if ( $config['persistent_data_handler'] instanceof PersistentDataInterface) {
        $this->persistentDataHandler = $config['persistent_data_handler'];
      } elseif ($config['persistent_data_handler'] === 'session') {
        $this->persistentDataHandler = new FacebookSessionPersistentDataHandler();
      } elseif ($config['persistent_data_handler'] === 'memory') {
        $this->persistentDataHandler = new FacebookMemoryPersistentDataHandler();
      } else {
        throw new \InvalidArgumentException(
          'The persistent_data_handler must be set to "session", "memory", '
          . ' or be an instance of Facebook\PersistentData\PersistentDataInterface'
        );
      }
    }

    if (isset($config['default_access_token'])) {
      if (is_string($config['default_access_token'])) {
        $this->defaultAccessToken = new AccessToken($config['default_access_token']);
      } elseif ( ! $config['default_access_token'] instanceof AccessToken) {
        throw new \InvalidArgumentException(
          'The "default_access_token" provided must be of type "string"'
          . ' or Facebook\Entities\AccessToken'
        );
      }
    }

    $this->defaultGraphVersion = isset($config['default_graph_version'])
      ? $config['default_graph_version']
      : static::DEFAULT_GRAPH_VERSION;
  }

  /**
   * Returns the FacebookApp entity.
   *
   * @return FacebookApp
   */
  public function getApp()
  {
    return $this->app;
  }

  /**
   * Returns the FacebookClient service.
   *
   * @return FacebookClient
   */
  public function getClient()
  {
    return $this->client;
  }

  /**
   * Returns the last response returned from Graph.
   *
   * @return FacebookResponse|FacebookBatchResponse|null
   */
  public function getLastResponse()
  {
    return $this->lastResponse;
  }

  /**
   * Returns the URL detection handler.
   *
   * @return UrlDetectionInterface
   */
  public function getUrlDetectionHandler()
  {
    if ( ! $this->urlDetectionHandler instanceof UrlDetectionInterface) {
      $this->urlDetectionHandler = new FacebookUrlDetectionHandler();
    }

    return $this->urlDetectionHandler;
  }

  /**
   * Returns the default AccessToken entity.
   *
   * @return AccessToken|null
   */
  public function getDefaultAccessToken()
  {
    return $this->defaultAccessToken;
  }

  /**
   * Returns the default Graph version.
   *
   * @return string
   */
  public function getDefaultGraphVersion()
  {
    return $this->defaultGraphVersion;
  }

  /**
   * Returns the redirect login helper.
   *
   * @return FacebookRedirectLoginHelper
   */
  public function getRedirectLoginHelper()
  {
    return new FacebookRedirectLoginHelper(
      $this->app,
      $this->persistentDataHandler,
      $this->urlDetectionHandler
    );
  }

  /**
   * Sends a GET request to Graph and returns the result.
   *
   * @param string $endpoint
   * @param AccessToken|string|null $accessToken
   * @param string|null $eTag
   * @param string|null $graphVersion
   *
   * @return FacebookResponse
   *
   * @throws FacebookSDKException
   */
  public function get(
    $endpoint,
    $accessToken = null,
    $eTag = null,
    $graphVersion = null)
  {
    return $this->sendRequest(
      'GET',
      $endpoint,
      $params = [],
      $accessToken,
      $eTag,
      $graphVersion);
  }

  /**
   * Sends a POST request to Graph and returns the result.
   *
   * @param string $endpoint
   * @param array $params
   * @param AccessToken|string|null $accessToken
   * @param string|null $eTag
   * @param string|null $graphVersion
   *
   * @return FacebookResponse
   *
   * @throws FacebookSDKException
   */
  public function post(
    $endpoint,
    array $params = [],
    $accessToken = null,
    $eTag = null,
    $graphVersion = null)
  {
    return $this->sendRequest(
      'POST',
      $endpoint,
      $params,
      $accessToken,
      $eTag,
      $graphVersion);
  }

  /**
   * Sends a DELETE request to Graph and returns the result.
   *
   * @param string $endpoint
   * @param AccessToken|string|null $accessToken
   * @param string|null $eTag
   * @param string|null $graphVersion
   *
   * @return FacebookResponse
   *
   * @throws FacebookSDKException
   */
  public function delete(
    $endpoint,
    $accessToken = null,
    $eTag = null,
    $graphVersion = null)
  {
    return $this->sendRequest(
      'DELETE',
      $endpoint,
      $params = [],
      $accessToken,
      $eTag,
      $graphVersion);
  }

  /**
   * Sends a request to Graph for the next page of results.
   *
   * @param GraphList $graphList The GraphList to paginate over.
   *
   * @return GraphList|null
   *
   * @throws FacebookSDKException
   */
  public function next(GraphList $graphList)
  {
    return $this->getPaginationResults($graphList, 'next');
  }

  /**
   * Sends a request to Graph for the previous page of results.
   *
   * @param GraphList $graphList The GraphList to paginate over.
   *
   * @return GraphList|null
   *
   * @throws FacebookSDKException
   */
  public function previous(GraphList $graphList)
  {
    return $this->getPaginationResults($graphList, 'previous');
  }

  /**
   * Sends a request to Graph for the next page of results.
   *
   * @param GraphList $graphList The GraphList to paginate over.
   * @param string $direction The direction of the pagination: next|previous.
   *
   * @return GraphList|null
   *
   * @throws FacebookSDKException
   */
  public function getPaginationResults(GraphList $graphList, $direction)
  {
    $paginationRequest = $graphList->getPaginationRequest($direction);
    if ( ! $paginationRequest) {
      return null;
    }

    $this->lastResponse = $this->client->sendRequest($paginationRequest);

    // Keep the same GraphObject subclass
    $subClassName = $graphList->getSubClassName();
    $graphList = $this->lastResponse->getGraphList($subClassName, false);

    return count($graphList) > 0 ? $graphList : null;
  }

  /**
   * Sends a request to Graph and returns the result.
   *
   * @param string $method
   * @param string $endpoint
   * @param array $params
   * @param AccessToken|string|null $accessToken
   * @param string|null $eTag
   * @param string|null $graphVersion
   *
   * @return FacebookResponse
   *
   * @throws FacebookSDKException
   */
  public function sendRequest(
    $method,
    $endpoint,
    array $params = [],
    $accessToken = null,
    $eTag = null,
    $graphVersion = null)
  {
    $accessToken = $accessToken ?: $this->defaultAccessToken;
    $graphVersion = $graphVersion ?: $this->defaultGraphVersion;
    $request = $this->request($method, $endpoint, $params, $accessToken, $eTag, $graphVersion);
    return $this->lastResponse = $this->client->sendRequest($request);
  }

  /**
   * Sends a batched request to Graph and returns the result.
   *
   * @param array $requests
   * @param AccessToken|string|null $accessToken
   * @param string|null $graphVersion
   *
   * @return FacebookBatchResponse
   *
   * @throws FacebookSDKException
   */
  public function sendBatchRequest(
    array $requests,
    $accessToken = null,
    $graphVersion = null)
  {
    $accessToken = $accessToken ?: $this->defaultAccessToken;
    $graphVersion = $graphVersion ?: $this->defaultGraphVersion;
    $batchRequest = new FacebookBatchRequest(
      $this->app,
      $requests,
      $accessToken,
      $graphVersion
    );

    return $this->lastResponse = $this->client->sendBatchRequest($batchRequest);
  }

  /**
   * Instantiates a new FacebookRequest entity.
   *
   * @param string $method
   * @param string $endpoint
   * @param array $params
   * @param AccessToken|string|null $accessToken
   * @param string|null $eTag
   * @param string|null $graphVersion
   *
   * @return FacebookRequest
   *
   * @throws FacebookSDKException
   */
  public function request(
    $method,
    $endpoint,
    array $params = [],
    $accessToken = null,
    $eTag = null,
    $graphVersion = null)
  {
    $accessToken = $accessToken ?: $this->defaultAccessToken;
    $graphVersion = $graphVersion ?: $this->defaultGraphVersion;
    return new FacebookRequest(
      $this->app,
      $accessToken,
      $method,
      $endpoint,
      $params,
      $eTag,
      $graphVersion
    );
  }

  /**
   * Factory to create FacebookFile's.
   *
   * @param string $pathToFile
   *
   * @return FacebookFile
   *
   * @throws FacebookSDKException
   */
  public function fileToUpload($pathToFile)
  {
    return new FacebookFile($pathToFile);
  }

  /**
   * Factory to create FacebookVideo's.
   *
   * @param string $pathToFile
   *
   * @return FacebookVideo
   *
   * @throws FacebookSDKException
   */
  public function videoToUpload($pathToFile)
  {
    return new FacebookVideo($pathToFile);
  }

}
