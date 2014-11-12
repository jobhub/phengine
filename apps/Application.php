<?php
namespace Microapp;

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

		$loader->registerNamespaces( $namespaces, true );

		$this->_loader = $loader;

		parent::__construct($di);
	}

}