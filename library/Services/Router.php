<?php
namespace Library\Services;

use Phalcon\Mvc\Router as PhRouter;

class Router extends AbstractService {


	public function init() {
		$di = $this->getDi();

		$di['router'] = function () {

		    $router = new PhRouter();

				$di = $this-getDi();

		    $router->setDefaultModule($this->_config->application->defaultModule);

				$namespace[] = \Engine\Config::getConfigValue($di, 'application->ns');
				$default_module = \Engine\Config::getConfigValue($di, 'application->defaultModule');

				if(!empty($default_module)) {
					$router->setDefaultModule($default_module);
					$namespace[] = ucfirst($default_module);
				}
				$namespace[] = 'Controllers';

		    $router->setDefaultNamespace(implode("\\", $namespace));

		    return $router;
		};

	}
}