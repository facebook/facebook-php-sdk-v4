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

/**
 * Class FacebookRequestException
 * @package Facebook
 * @author Fosco Marotto <fjm@fb.com>
 * @author David Poll <depoll@fb.com>
 */
class FacebookRequestException extends \Exception
{

  /**
   * @var int Status code for the response causing the exception
   */
  private $statusCode;

  /**
   * @var string Raw response
   */
  private $rawResponse;

  /**
   * @var array Decoded response
   */
  private $responseData;

  /**
   * Creates a FacebookRequestException.
   *
   * @param string $rawResponse The raw response from the Graph API
   * @param array $responseData The decoded response from the Graph API
   * @param int $statusCode
   */
  public function __construct($rawResponse, $responseData, $statusCode)
  {
    $this->rawResponse = $rawResponse;
    $this->statusCode = $statusCode;
    $this->responseData = static::convertToArray($responseData);
    parent::__construct(
      $this->get('message', 'Unknown Exception'), $this->get('code', -1), null
    );
  }

  /**
   * Process an error payload from the Graph API and return the appropriate
   *   exception subclass.
   *
   * @param string $raw the raw response from the Graph API
   * @param array $data the decoded response from the Graph API
   * @param int $statusCode the HTTP response code
   *
   * @return FacebookRequestException
   */
  public static function create($raw, $data, $statusCode)
  {
    $data = static::convertToArray($data);
    if (!isset($data['error']['code']) && isset($data['code'])) {
      $data = array('error' => $data);
    }
    $code = (isset($data['error']['code']) ? $data['error']['code'] : null);

    // Login status or token expired, revoked, or invalid
    if ($code == 102 || $code == 190 || $code == 100) {
      return new FacebookAuthorizationException($raw, $data, $statusCode);
    }

    // Server issue, possible downtime
    if ($code == 1 || $code == 2) {
      return new FacebookServerException($raw, $data, $statusCode);
    }

    // API Throttling
    if ($code == 4 || $code == 17 || $code == 341) {
      return new FacebookThrottleException($raw, $data, $statusCode);
    }

    // Missing Permissions
    if ($code == 10 || ($code >= 200 && $code <= 299)) {
      return new FacebookPermissionException($raw, $data, $statusCode);
    }

    // Duplicate Post
    if ($code == 506) {
      return new FacebookClientException($raw, $data, $statusCode);
    }
    // All others
    return new FacebookOtherException($raw, $data, $statusCode);
  }

  /**
   * Checks isset and returns that or a default value.
   */
  private function get($key, $default = null)
  {
    if (isset($this->responseData['error'][$key])) {
      return $this->responseData['error'][$key];
    }
    return $default;
  }

  /**
   * Returns the HTTP status code
   *
   * @return int
   */
  public function getHttpStatusCode()
  {

    return $this->statusCode;
  }

  /**
   * Returns the sub-error code
   *
   * @return int
   */
  public function getSubErrorCode()
  {
    return $this->get('error_subcode', -1);
  }

  /**
   * Returns the error type
   *
   * @return string
   */
  public function getErrorType()
  {
    return $this->get('type', '');
  }

  /**
   * Returns the raw response used to create the exception.
   *
   * @return string
   */
  public function getRawResponse()
  {
    return $this->rawResponse;
  }

  /**
   * Returns the decoded response used to create the exception.
   */
  public function getResponse()
  {
    return $this->responseData;
  }

  private static function convertToArray($object)
  {
    if ($object instanceof \stdClass) {
      return get_object_vars($object);
    }
    return $object;
  }

}