<?php
/**
 * Strava.php
 *
 * @author David Wilcock <dwilcock@docnet.nu>
 * @copyright Doctor Net Ltd &copy; 2014
 * @package
 */

namespace Roflcopter;

class Strava {

   /**
    * Some Strava consants
    */
   const AUTHORIZE_URI = 'https://www.strava.com/oauth/authorize'; // get
   const TOKEN_EXCHANGE_URI = 'https://www.strava.com/oauth/token'; // post
   const API_URI = 'https://www.strava.com/api/v3'; // mixed
   const CACHE_DIR = '/tmp/';

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
    * Handles the auth
    *
    * @param array $arrConfig
    * @throws \Exception
    */
   public function __construct(array $arrConfig) {

      $this->intClientID = $arrConfig['CLIENT_ID'];
      $this->strClientSecret = $arrConfig['CLIENT_SECRET'];
      $this->strRedirectUri = $arrConfig['REDIRECT_URI'];

      /**
       * If the access token is passed in the config array, we can start using the API
       * straight away. If not, try it from cache, and if still no luck, then  we have to do the whole OAUTH thing.
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
    * Performs an API call
    *
    * @param $strEndpointUrl
    * @param $arrParams
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

      echo "<pre>"  . print_r(json_decode($objResponse));
   }

   /**
    * Ronseal ...
    *
    * @return bool|string
    */
   private function loadAccessTokenFromCache() {
      if (!file_exists(self::CACHE_DIR . 'access-token')) {
         return FALSE;
      }
      return file_get_contents(self::CACHE_DIR . 'access-token');
   }

   /**
    * Ronseal ...
    *
    * @param $strAccessToken
    */
   private function saveAccessTokenToCache($strAccessToken) {
      file_put_contents(self::CACHE_DIR . 'access-token', $strAccessToken);
   }

   /**
    * Redirects to the applicatio AUTH page
    */
   private function redirectToAuthorize() {
      $arrParams = array(
         'client_id' => $this->intClientID,
         'response_type' => 'code',
         'redirect_uri' => $this->strRedirectUri,
         'scope' => 'view_private'
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

      return FALSE;

   }

}