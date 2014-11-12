<?php
namespace Microapp;

require_once('../library/Engine/Application.php');

class Application extends \Engine\Application {

	protected $_services = array(
		'url', 'logger', 'cache', 'database', 'session'
	);

	public function __construct(\Phalcon\DI $di)
	{
		$loader = $this->getDefaultLoader();

		$namespaces = array(
			'Microapp\Controllers'=>realpath(__DIR__.'/controllers/'),
			'Microapp\Models'=>realpath(__DIR__.'/models/')
		);

		$reg_namespaces = array_merge($loader->getNamespaces(), $namespaces);

		$loader->registerNamespaces( $reg_namespaces, true );

		$this->_loader = $loader;

		parent::__construct($di);
	}

}