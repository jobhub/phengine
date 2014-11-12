<?php

use Phalcon\DI,
    Phalcon\DI\FactoryDefault;

ini_set('display_errors',1);
error_reporting(E_ALL);

define('ROOT_PATH', __DIR__);

if (!defined('BASE_PATH')) {
	define('BASE_PATH', __DIR__.'/../');
}

require_once(BASE_PATH . '/config/config.php');
$config = getConfig();
define('PATH_LIBRARY', __DIR__ . '/../library/');
define('PATH_SERVICES', __DIR__ . '/../library/Services/');
define('PATH_RESOURCES', __DIR__ . '/../library/Tools/');

set_include_path(
    ROOT_PATH . PATH_SEPARATOR . get_include_path()
);

// требуется для phalcon/incubator
include __DIR__ . "/../vendor/autoload.php";

// Используем автозагрузчик приложений для автозагрузки классов.
// Автозагрузка зависимостей, найденных в composer.
$loader = new \Phalcon\Loader();

$loader->registerDirs(array(
    ROOT_PATH
));


$loader->register();


$di = new FactoryDefault();
DI::reset();


DI::setDefault($di);