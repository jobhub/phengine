<?php
/**
 * @namespace
 */
namespace Library\Tools;

/**
 * Class Config
 *
 * @category   Engine
 * @package    Tools
 */
class Config {

	public static function getConfigValue($path, $default=null) {
		$di = \Phalcon\DI::getDefault();

		if($di->has('config')) {
			$config = $di->get('config')->toArray();

			$path = explode('->', $path);

		  foreach($path as $p)
		  {
		    $p = trim($p);
		    if (!isset($config[$p]))
		    {
		      return $default;
		    }
		    $config = $config[$p];
		  }
		  return $config;

		}

		return $default;
	}
}