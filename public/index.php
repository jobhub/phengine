<?php
require_once __DIR__.'/../library/Engine/Application.php';

use Phalcon\DI\FactoryDefault;

error_reporting(E_ALL);

if (!defined('BASE_PATH')) {
	define('BASE_PATH', __DIR__.'/../');
}

define('PURGEABLE_CACHE_TTL', 60*30);

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

include_once(BASE_PATH . '/config/config.php');
$config = getConfig(APPLICATION_ENV);

try {

	/**
	 * Include services
	 */
	//require __DIR__ . '/../config/services.php';
	$di = new FactoryDefault();
	$di->setShared( 'config', $config );

	// требуется для incubator
	include __DIR__ . "/../vendor/autoload.php";

	/**
	 * Handle the request
	 */
	$application = new \Microapp\Application( $di );

	/**
	 * Assign the DI
	 */
	// $application->setDI($di);

	$application->run();

	//echo $application->handle()->getContent();
	$application->handle();

} catch (Phalcon\Exception $e) {
	$application->manageResponse($e, true);
} catch(\Exception $e) {
	$application->attachCorsHeaders();
	$application->manageResponse($e, true);
}
