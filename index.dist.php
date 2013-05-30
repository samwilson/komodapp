<?php

date_default_timezone_set('Australia/Perth');
define('KOHANA_COOKIE_SALT', 'random-string-here');
#define('KOHANA_LANG', 'en-au');
#define('KOHANA_BASE_URL', '/komodapp/');
#define('KOHANA_ENVIRONMENT', 'production');
#define('KOHANA_LOCALE', 'en_US.utf-8')

// List the names of modules that should NOT be loaded.
$disabled_modules = array();

require_once 'komodapp.php';
