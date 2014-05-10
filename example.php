<?php

require_once 'Strava.php';

$arrConfig = array(
   'CLIENT_ID' => 1354,
   'CLIENT_SECRET' => 'xxx',
   'REDIRECT_URI' => 'http://roflcopter.dwilcock/strava'
);

$objStrava = new \Roflcopter\Strava($arrConfig);