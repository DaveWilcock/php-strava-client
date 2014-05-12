# Roflcopter Strava v3 API Library

This is a single-file-library that you can use to interrogate the Strava v3 API. It handles authentication as well as whatever API calls you want to me (as long as they are valid)

## Usage

First of you need to construct a configuration array. The array should contain at least the following information:

* CLIENT_ID
* CLIENT_SECRET
* REDIRECT_URI

CLIENT_ID and CILENT_SECRET should be taken from the [https://www.strava.com/settings/api](My API Application) section of the site

Optionally, you can supply the following addition configuration options:

* CACHE_DIRECTORY
* ACCESS_TOKEN

If CACHE_DIRECTORY isn't supplied, the library falls back to writing to /tmp

If ACCESS_TOKEN is supplied, we bypass authorization and token exchange - assuming the ACCESS_TOKEN is correct.

## Examples

The following example GETs information about the authenticated athlete:

    $arrConfig = array(
       'CLIENT_ID' => 1354,
       'CLIENT_SECRET' => 'here is my client secret',
       'REDIRECT_URI' => 'http://localhost/example.php',
       'CACHE_DIRECTORY' => '/path/to/cache/dir/'
    );

    $objStrava = new \Roflcopter\Strava($arrConfig);
    print_r($objStrava->get('athlete', array()));

The following example PUTs (updates) the weight information for the current athlete:

    $arrConfig = array(
       'CLIENT_ID' => 1354,
       'CLIENT_SECRET' => 'here is my client secret',
       'REDIRECT_URI' => 'http://localhost/example.php',
       'CACHE_DIRECTORY' => '/path/to/cache/dir/'
    );

    $objStrava = new \Roflcopter\Strava($arrConfig);
    print_r($objStrava->put('athlete', array('weight' => 62.8)));

## References

[http://strava.github.io/api/v3](http://strava.github.io/api/v3) is a good place to start.