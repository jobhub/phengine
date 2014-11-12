<?php
namespace Library\Services;

use Phalcon\Mvc\Router as PhRouter;

class Router extends AbstractService {


	public function init() {
		$di = $this->getDi();

		$di['router'] = function () {

		    $router = new PhRouter();

		    $router->setDefaultModule($this->_config->application->defaultModule);
		    $router->setDefaultNamespace("Microapp\Frontend\Controllers");

		    return $router;
		};

	}
}