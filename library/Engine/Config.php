<?php
/**
 * Created by PhpStorm.
 * User: patris
 * Date: 26.09.14
 * Time: 9:28
 */

namespace Engine;


class Config {

	public static function getConfigValue(\Phalcon\DiInterface $di, $path, $default=null) {
		$config = $di->getShared('config');

		$path = explode('->', $path);

	  foreach($path as $p)
	  {
	    $p = trim($p);
	    if (!isset($config->$p))
	    {
	      return $default;
	    }
	    $config = $config->$p;
	  }
	  return $config;
	}
} 