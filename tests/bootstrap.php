<?php

//echo "RUNNING (". __FILE__ .")!!!!\n";

// set the timezone to avoid spurious errors from PHP
date_default_timezone_set("America/Chicago");

require_once(dirname(__FILE__) .'/../vendor/crazedsanity/core/AutoLoader.class.php');

define('UNITTEST__LOCKFILE', __DIR__ .'/files/rw');
AutoLoader::registerDirectory(dirname(__FILE__) .'/../');

