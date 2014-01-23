<?php
/*
 * This file should be included from index.php after defining a couple of
 * configuration values there.
 */

define('EXT', '.php');
define('DOCROOT', realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR);

/*
 * Path to application data
 */
if ( ! defined('APPPATH'))
{
	define('APPPATH', DOCROOT.'var'.DIRECTORY_SEPARATOR);
}
if ( ! is_dir(APPPATH))
{
	// Create directory, after the precedent of Kohana_Core::init();
	mkdir(APPPATH, 0755, TRUE) OR die('Unable to make directory '.APPPATH);
	chmod(APPPATH, 0755);
}

define('MODPATH', DOCROOT.'modules'.DIRECTORY_SEPARATOR);
define('SYSPATH', DOCROOT.'vendor'.DIRECTORY_SEPARATOR.'kohana'.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR);
if (!defined('KOHANA_BASE_URL')) define('KOHANA_BASE_URL', '/komodapp/');
if (!defined('KOHANA_ENVIRONMENT')) define('KOHANA_ENVIRONMENT', 'production');
if (substr(KOHANA_BASE_URL, -1) != '/') 
{
	echo 'KOHANA_BASE_URL must have trailing slash';
	exit(1);
}
if (!defined('KOHANA_COOKIE_SALT'))
{
	echo 'Please define KOHANA_COOKIE_SALT in '.APPPATH.'config'.DIRECTORY_SEPARATOR.'local.php';
	exit(1);
}

/*
 * Set locale and language
 */
if (!defined('KOHANA_LANG')) define('KOHANA_LANG', 'en-au');
if (!defined('KOHANA_LOCALE')) define('KOHANA_LOCALE', 'en_US.utf-8');
setlocale(LC_ALL, KOHANA_LOCALE);

/*
 * Load and configure Kohana Core
 */
if (!defined('KOHANA_START_TIME')) define('KOHANA_START_TIME', microtime(TRUE));
if (!defined('KOHANA_START_MEMORY')) define('KOHANA_START_MEMORY', memory_get_usage());
require SYSPATH . 'classes/Kohana/Core'.EXT;
require SYSPATH . 'classes/Kohana'.EXT;
require DOCROOT . 'vendor/autoload.php';
spl_autoload_register(array('Kohana', 'auto_load'));
ini_set('unserialize_callback_func', 'spl_autoload_call');
I18n::lang(KOHANA_LANG);
Kohana::$environment = constant('Kohana::'.strtoupper(KOHANA_ENVIRONMENT));

/*
 * Try to create log directory.
 */
$cache_dir = APPPATH.'cache';
if ( ! file_exists($cache_dir))
{
	// Create directory, after the precedent of Kohana_Core::init();
	mkdir($cache_dir, 0755, TRUE) OR die('Unable to make directory '.$cache_dir);
	chmod($cache_dir, 0755);
}

/**
 * Shutdown for CLI, can be removed when http://dev.kohanaframework.org/issues/4537 is resolved.
 */
if (PHP_SAPI == 'cli')
{
	register_shutdown_function(function()
	{
		if (Kohana::$errors AND $error = error_get_last() AND in_array($error['type'], Kohana::$shutdown_errors))
		{
			exit(1);
		}
		
	});
}

/*
 * Kohana initialisation.
 */
Kohana::init(array(
	'base_url' => KOHANA_BASE_URL,
	'index_file' => FALSE,
	'cache_dir' => $cache_dir,
	'profile' => Kohana::$environment != Kohana::PRODUCTION,
	'errors' => Kohana::$environment != Kohana::PRODUCTION,
	'caching' => Kohana::$environment == Kohana::PRODUCTION,
));

/*
 * Try to create log directory.
 */
$log_dir = APPPATH.'logs';
if (!file_exists($log_dir))
{
	// Create directory, after the precedent of Kohana_Core::init();
	mkdir($log_dir, 0755, TRUE);
	chmod($log_dir, 0755);
}
Kohana::$log->attach(new Log_File($log_dir));
unset($log_dir);

Kohana::$config->attach(new Config_File);

Cookie::$salt = KOHANA_COOKIE_SALT;

/**
 * Enable all modules that are not listed in $disabled_modules.
 */
$modules = array();
foreach (scandir(MODPATH) as $mod)
{
	// Ignore disabled modules
	$disabled = (isset($disabled_modules) AND in_array($mod, $disabled_modules));
	// Ignore core and any hidden directories and files
	$nonmodule = ($mod == 'core' OR substr($mod, 0, 1) == '.' OR !is_dir(MODPATH.$mod));
	if ($nonmodule OR $disabled)
	{
		continue;
	}
	// Otherwise, enable the module.
	$modules[$mod] = MODPATH.$mod;
}
Kohana::modules($modules);
unset($modules, $disabled_modules);

if (PHP_SAPI == 'cli')
{
	/**
	 * Include the Unit Test module and leave the rest to PHPunit.
	 */
	if (substr(basename($_SERVER['PHP_SELF']), 0, 7) == 'phpunit')
	{
		// Disable output buffering
		if (($ob_len = ob_get_length()) !== FALSE)
		{
			// flush_end on an empty buffer causes headers to be sent. Only flush if needed.
			if ($ob_len > 0) ob_end_flush();
			else ob_end_clean();
		}
		Kohana::modules(Kohana::modules() + array('unittest' => MODPATH.'unittest'));
		return; // Execution will be continued by phpunit
	}

	/*
	 * Execute minion if this is a command line request.
	 */
	set_exception_handler(array('Minion_Exception', 'handler'));
	Minion_Task::factory(Minion_CLI::options())->execute();
} else
{
	/*
	 * Otherwise, execute the main request.
	 */
	echo Request::factory(TRUE, array(), FALSE)
		->execute()
		->send_headers(TRUE)
		->body();
}
