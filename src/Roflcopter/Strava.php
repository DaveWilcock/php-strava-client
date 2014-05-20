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

namespace Roflcopter;

class Strava {

   /**
    * Some Strava constants
    */
   const AUTHORIZE_URI = 'https://www.strava.com/oauth/authorize'; // get
   const TOKEN_EXCHANGE_URI = 'https://www.strava.com/oauth/token'; // post
   const API_URI = 'https://www.strava.com/api/v3'; // mixed
   const ACCESS_TOKEN_FILENAME = 'strava-access-token';

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

      if (isset($arrConfig['CACHE_DIRECTORY'])) {
         if (is_dir($arrConfig['CACHE_DIRECTORY']) && is_writable($arrConfig['CACHE_DIRECTORY'])) {
            $this->strCacheDirectory = $arrConfig['CACHE_DIRECTORY'];
         }
      }

      /**
       * If the access token is passed in the config array, we can start using the API
       * straight away. If not, try it from cache, and if still no luck, then we have to do the whole OAUTH thing.
       */
      if (isset($arrConfig['ACCESS_TOKEN']) && !empty($arrConfig['ACCESS_TOKEN'])) {
         $this->strAccessToken = $arrConfig['ACCESS_TOKEN'];
      } elseif (($this->strAccessToken = $this->loadAccessTokenFromCache()) === FALSE) {
         if (!isset($_GET['code'])) {
            $this->redirectToAuthorize();
         } else {
            $this->strAccessToken = $this->performTokenExchange($_GET['code']);
         }
      }

      // Really should only get here if the Token Exchange request failed
      if (empty($this->strAccessToken)) {
         throw new \Exception("Unable to acquire a valid Access Token");
      }
   }

   /**
    * Performs an API call using GET data, returns stdClass representation of json data
    *
    * @param $strEndpointUrl
    * @param $arrParams
    * @return stdClass
    */
   public function get($strEndpointUrl, $arrParams) {
      $objCurl = curl_init(self::API_URI . '/' . $strEndpointUrl . '?' . http_build_query($arrParams));

      $arrCurlOptions = array(
         CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $this->strAccessToken),
         CURLOPT_RETURNTRANSFER => TRUE,
         CURLOPT_CONNECTTIMEOUT => 20
      );

      curl_setopt_array($objCurl, $arrCurlOptions);

      $objResponse = curl_exec($objCurl);
      $arrInfo = curl_getinfo($objCurl);
      if ($arrInfo['http_code'] == "401") {
          $this->cleanupCacheAndRedirect();
      }

      return json_decode($objResponse);

   }

    /**
     * Performs an API call using PUT data, returns stdClass representation of json data
     *
     * @param $strEndpointUrl
     * @param $arrParams
     * @return mixed
     */
   public function put($strEndpointUrl, $arrParams) {
       $objCurl = curl_init(self::API_URI . '/' . $strEndpointUrl);

       $arrCurlOptions = array(
           CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $this->strAccessToken),
           CURLOPT_CUSTOMREQUEST => 'PUT',
           CURLOPT_POSTFIELDS => http_build_query($arrParams),
           CURLOPT_RETURNTRANSFER => TRUE,
           CURLOPT_CONNECTTIMEOUT => 20,
           CURLOPT_VERBOSE => TRUE,
           CURLOPT_FOLLOWLOCATION => TRUE
       );

       curl_setopt_array($objCurl, $arrCurlOptions);

       $objResponse = curl_exec($objCurl);
       $arrInfo = curl_getinfo($objCurl);
       if ($arrInfo['http_code'] == "401") {
           $this->cleanupCacheAndRedirect();
       }

       return json_decode($objResponse);
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
    * Redirects to the application AUTH page
    */
   private function redirectToAuthorize() {
      $arrParams = array(
         'client_id' => $this->intClientID,
         'response_type' => 'code',
         'redirect_uri' => $this->strRedirectUri,
         'scope' => 'write'
      );
      $strLocation = self::AUTHORIZE_URI . '?' . http_build_query($arrParams);
      header("Location: " . $strLocation);
      exit();
   }

   /**
    * Turns an authorization code into an access token.
    *
    * @param $strCode
    * @return bool
    */
   private function performTokenExchange($strCode) {

      $objCurl = curl_init(self::TOKEN_EXCHANGE_URI);

      $arrParams = array(
         'client_id' => $this->intClientID,
         'client_secret' => $this->strClientSecret,
         'code' => $strCode
      );

      $arrCurlOptions = array(
         CURLOPT_POST => count($arrParams),
         CURLOPT_POSTFIELDS => http_build_query($arrParams),
         CURLOPT_RETURNTRANSFER => TRUE,
         CURLOPT_CONNECTTIMEOUT => 20
      );

      curl_setopt_array($objCurl, $arrCurlOptions);
      $strResponse = curl_exec($objCurl);
      $arrCurlInfo = curl_getinfo($objCurl);

      if ($arrCurlInfo['http_code'] == 200) {
         $objResponse = json_decode($strResponse);
         if (isset($objResponse->access_token)) {
            $this->saveAccessTokenToCache($objResponse->access_token);
            return $objResponse->access_token;
         }
      }

      // If the response wasn't a 200, the code has probably expired or has been used more than once.
      return FALSE;

   }

}