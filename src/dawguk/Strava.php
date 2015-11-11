<?php
/**
 * Strava.php
 *
 * LICENSE: THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author David Wilcock <dave.wilcock@gmail.com>
 * @copyright David Wilcock &copy; 2014
 * @license http://opensource.org/licenses/MIT
 *
 */

namespace dawguk;

use Composer\Autoload\ClassLoader;

class Strava {

   /**
    * Some Strava constants
    */
   const AUTHORIZE_URI = 'https://www.strava.com/oauth/authorize'; // get
   const TOKEN_EXCHANGE_URI = 'https://www.strava.com/oauth/token'; // post
   const API_URI = 'https://www.strava.com/api/v3'; // mixed
   const ACCESS_TOKEN_FILENAME = 'strava-access-token';

   /**
    * _make_request array keys
    */
   const HTTP_INFO = 1;
   const RESPONSE_BODY = 2;

   /**
    * @var int intClientID
    */
   private $intClientID = -1;

   /**
    * @var string strClientSecret
    */
   private $strClientSecret = '';

   /**
    * @var bool|string
    */
   private $strAccessToken = '';

   /**
    * @var string
    */
   private $strAccessScope = '';

   /**
    * @var string
    */
   private $strRedirectUri = '';

   /**
    * @var string
    */
   private $strCacheDirectory = '/tmp/';

   /**
    * Handles the auth
    *
    * @param array $arrConfig
    * @throws \Exception
    */
   public function __construct(array $arrConfig) {

      $this->intClientID = $arrConfig['CLIENT_ID'];
      $this->strClientSecret = $arrConfig['CLIENT_SECRET'];
      $this->strRedirectUri = $arrConfig['REDIRECT_URI'];
      $this->strAccessScope = $arrConfig['ACCESS_SCOPE'];

      if (isset($arrConfig['ACCESS_TOKEN']) && !empty($arrConfig['ACCESS_TOKEN'])) {
         $this->strAccessToken = $arrConfig['ACCESS_TOKEN'];
      }

   }

   /**
    * Performs an API call using GET data, returns stdClass representation of json data
    *
    * @param $strEndpointUrl
    * @param $arrParams
    * @return mixed
    */
   public function get($strEndpointUrl, $arrParams) {

      $arrResponse = $this->_make_request(self::API_URI . '/' . $strEndpointUrl . '?' . http_build_query($arrParams), 'GET', $arrParams);
      $arrInfo = $arrResponse[self::HTTP_INFO];
      $objResponse = $arrResponse[self::RESPONSE_BODY];

      if ($arrInfo['http_code'] == "401") {
         $this->cleanupCacheAndRedirect();
      }

      return $objResponse;

   }

   /**
    * Performs an API call using PUT data, returns stdClass representation of json data
    *
    * @param $strEndpointUrl
    * @param $arrParams
    * @return mixed
    */
   public function put($strEndpointUrl, $arrParams) {

      $arrResponse = $this->_make_request(self::API_URI . '/' . $strEndpointUrl, 'PUT', $arrParams);
      $arrInfo = $arrResponse[self::HTTP_INFO];
      $objResponse = $arrResponse[self::RESPONSE_BODY];

      if ($arrInfo['http_code'] == "401") {
         $this->cleanupCacheAndRedirect();
      }

      return $objResponse;
   }

   /**
    * Posts a pre-generated file to Strava
    *
    * @param $strFilename
    * @param $strActivityType
    * @param $strDataType
    * @return mixed
    * @throws \Exception
    */
   public function postActivity($strFilename, $strActivityType, $strDataType) {

      /**
       * Bit of a hack for version of PHP < 5.5
       */
      if (!function_exists('curl_file_create')) {
         function curl_file_create($strFilename, $strMimeType = '', $strPostname = '') {
            return "@$strFilename;filename="
            . ($strPostname ?: basename($strFilename))
            . ($strMimeType ? ";type=$strMimeType" : '');
         }
      }

      $objCurlFile = curl_file_create($strFilename, 'application/xml');

      $arrParams = array(
         'activity_type' => $strActivityType,
         'file' => $objCurlFile,
         'data_type' => $strDataType
      );

      $arrResponse = $this->_make_request(self::API_URI . '/uploads', 'POST', $arrParams);
      $arrInfo = $arrResponse[self::HTTP_INFO];
      $objResponse = $arrResponse[self::RESPONSE_BODY];

      /**
       * 201 - CREATED
       */
      if ($arrInfo['http_code'] != "201") {
         throw new \Exception("Upload failed: " . $objResponse->message);
      }

      return $objResponse;

   }

   /**
    * Tries to retrieve the access token from local storage
    *
    * @return bool|string
    */
   private function loadAccessTokenFromCache() {
      if (!file_exists($this->strCacheDirectory . self::ACCESS_TOKEN_FILENAME)) {
         return FALSE;
      }

      return file_get_contents($this->strCacheDirectory . self::ACCESS_TOKEN_FILENAME);
   }

   /**
    * Saves the access token to local storage
    *
    * @param $strAccessToken
    */
   private function saveAccessTokenToCache($strAccessToken) {
      file_put_contents($this->strCacheDirectory . self::ACCESS_TOKEN_FILENAME, $strAccessToken);
   }

   private function cleanupCacheAndRedirect() {
      unlink($this->strCacheDirectory . self::ACCESS_TOKEN_FILENAME);
      $this->redirectToAuthorize();
   }

   /**
    * Returns the authorization URL
    *
    * @return string
    */
   public function getAuthorizeURL() {
      $arrParams = array('client_id' => $this->intClientID, 'response_type' => 'code', 'redirect_uri' => $this->strRedirectUri, 'scope' => $this->strAccessScope);
      return self::AUTHORIZE_URI . '?' . http_build_query($arrParams);
   }

   /**
    * Redirects to the application AUTH page
    */
   private function redirectToAuthorize() {
      header("Location: " . $this->getAuthorizeURL());
      exit();
   }

   /**
    * Turns an authorization code into an access token.
    *
    * @param $strCode
    * @return bool
    */
   private function performTokenExchange($strCode) {

      $arrParams = array('client_id' => $this->intClientID, 'client_secret' => $this->strClientSecret, 'code' => $strCode);
      $arrResponse = $this->_make_request(self::TOKEN_EXCHANGE_URI, 'POST', $arrParams);
      $arrInfo = $arrResponse[self::HTTP_INFO];
      $objResponse = $arrResponse[self::RESPONSE_BODY];

      if ($arrInfo['http_code'] == 200) {
         if (isset($objResponse->access_token)) {
            $this->saveAccessTokenToCache($objResponse->access_token);

            return $objResponse->access_token;
         }
      }

      // If the response wasn't a 200, the code has probably expired or has been used more than once.
      return FALSE;

   }

   /**
    * Makes the actual API request
    *
    * @param $strUri
    * @param $strType
    * @param $arrParams
    * @return array
    */
   private function _make_request($strUri, $strType, $arrParams) {

      $objCurl = curl_init($strUri);
      $arrCurlOptions = array(
         CURLOPT_FRESH_CONNECT => TRUE,
         CURLOPT_RETURNTRANSFER => TRUE,
         CURLOPT_CONNECTTIMEOUT => 20,
         CURLOPT_FOLLOWLOCATION => TRUE
      );

      if (!empty($this->strAccessToken)) {
         $arrCurlOptions[CURLOPT_HTTPHEADER] = array('Authorization: Bearer ' . $this->strAccessToken);
      }

      switch ($strType) {

         case 'PUT':
            $arrCurlOptions[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $arrCurlOptions[CURLOPT_POSTFIELDS] = http_build_query($arrParams);
            break;

         case 'POST':
            $arrCurlOptions[CURLOPT_POST] = TRUE;
            $arrCurlOptions[CURLOPT_POSTFIELDS] = $arrParams;
            break;

      }

      curl_setopt_array($objCurl, $arrCurlOptions);

      $objResponse = curl_exec($objCurl);
      $arrInfo = curl_getinfo($objCurl);
      if ($arrInfo['http_code'] == "401") {
         $this->cleanupCacheAndRedirect();
      }

      return array(self::HTTP_INFO => $arrInfo, self::RESPONSE_BODY => json_decode($objResponse));
   }

}