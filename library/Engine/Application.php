<?php
namespace Engine;

class Application extends \Phalcon\Mvc\Micro {

	protected $_services = array(
		'url', 'logger', 'cache', 'database', 'session'
	);

	protected $_config;

	protected $_defaultModule;

	protected $_serviceNamespace;

	protected $_loader = null;

	public function __construct(\Phalcon\DI $di)
	{
		if(null == $this->_loader) {
			$this->_loader = $this->getDefaultLoader();
		}

		$this->_loader->register();

		$di->set('loader', $this->_loader);

		$this->_config = $di->getShared('config');

		$this->_defaultModule = $this->_config->application->defaultModule;

		$this->_serviceNamespace = $this->_config->application->services_ns;

		parent::__construct($di);
	}

	public function getDefaultLoader() {
		$loader = new \Phalcon\Loader();

		$namespaces = array(
			'Library' => BASE_PATH.'/library',
			'Engine' => BASE_PATH.'/library/Engine'
		);


		$loader->registerNamespaces( $namespaces );
		$loader->register();
		return $loader;
	}

	public function isDebug () {
		return $this->_config->application->debug;
	}

	public function run() {
		$core_ns = $this->_config->application->ns;
		/**
		 * @var $di \Phalcon\Di
		 */
		$di = $this->getDi();

		// Set application event manager
		$eventsManager = new \Phalcon\Events\Manager();



		// Init services and engine system
		foreach ($this->_services as $name) {
			$serviceName = $this->_config->application->services_ns."\\".ucfirst($name);

			$service = new $serviceName($di, $eventsManager, $this->_config);
			if (!($service instanceof $core_ns.'\Library\Services\AbstractService')) {
				throw new \Engine\Exception('Service is not a child class of Abstractservice');
			}
			/**
			 * @var $service \Library\Services\AbstractService
			 */
			$service->init();
		}

		$this->_getTranslation();
		if(strtolower($_SERVER['REQUEST_METHOD'])!='options') {
			//$this->attachSecurity($eventsManager);
		}


		$this->setEventsManager($eventsManager);
		$di->setShared('eventsManager', $eventsManager);

		$this['view'] = function() {
			$view = new \Phalcon\Mvc\View();
			return $view;
		};

		$this->getAPI();


		$di->setShared('app', $this);

		\Phalcon\DI::setDefault($di);
		$this->setDI($di);

		$this->before(new \Engine\CacheMiddleware());
		$this->before(function()  {
			$this->attachCorsHeaders();

			$isJson = stripos($this->request->getServer('CONTENT_TYPE'), 'json')!==FALSE;
	    if($isJson){
	        $_POST = json_decode($this->request->getRawBody(), true);
	    }
	    return true;
		});

		$this->options('/{catch:(.*)}', function() {
		    $this->response->setStatusCode(200, "OK")->send();
		});

		$this->finish(new \Engine\ResponseMiddleware());
	}

	public function attachCorsHeaders() {
		if (isset($_SERVER['HTTP_ORIGIN'])) {
        $this->response->setHeader("Access-Control-Allow-Origin", $_SERVER['HTTP_ORIGIN']);
        $this->response->setHeader("Access-Control-Allow-Credentials", 'true');
      } else {
				$this->response->setHeader("Access-Control-Allow-Origin", '*');
			}

      $this->response->setHeader("Access-Control-Allow-Methods", 'POST, OPTIONS')
      ->setHeader("Access-Control-Allow-Headers", 'Authorization,Content-Type,Accept,Origin,User-Agent,DNT,Cache-Control,X-Mx-ReqToken,Keep-Alive,X-Requested-With,If-Modified-Since,Cookie')
			->setHeader('Access-Control-Max-Age', '1728000');
		return true;
	}

	protected function attachSecurity(\Phalcon\Events\Manager $eventsManager=null) {
		if(!$eventsManager) {
			$eventsManager = new \Phalcon\Events\Manager();
		}

		$security = new \Engine\Security($this->getDI());

    /**
     * We listen for events in the dispatcher using the Security plugin
     */
    $eventsManager->attach('micro', $security );
	}

	public function attachHandlers($className, $handle, $useCache=false) {
		$reader = $this->getDi()->get('annotations');

    $class_annotations = $reader->getMethods($className);
		if(empty($class_annotations)) {
			return;
		}

		$cache = $this->getDi()->get('cacheData');

		$cachedAPI = $cache->get('api');

		$api_data = array();

    foreach($class_annotations as $action_name=>$annotations) {
			$action_short = str_replace('Action', '', $action_name);

	    foreach($annotations as $annotation) {
		    if($annotation->getName()=='api') {

			    $args = $annotation->getArguments();

			    $route_string = '/'.$action_short;
			    if(isset($args['query_params']) && count($args['query_params'])) {
				    $params_string = '';
				    foreach($args['query_params'] as $p) {
					    if(is_string($p)) {
						    $params_string.='{'.$p.'}';
					    } else if (is_array($p)) {
						    $param_name = $p['name'];

						    if(!$param_name) {
							    throw new \Engine\Exception('WRONG_QPARAMS_ANNOTATION', array('controller'=>$className, 'action'=>$action_name));
						    }
						    $params_string.='{'.$p['name'];

						    if(isset($p['regexp'])) {
							    $params_string .= ":{$p['regexp']}";
						    }

						    $params_string .= '}';
					    }
				    }
				    if(strlen($params_string)) {
					    $route_string .= "/$params_string";
				    }
			    }
					$httpMethod = $args['httpMethod'];
			    $handle->$httpMethod($route_string, $action_name);
			    $api_data[$action_name] = array('httpMethod'=>$httpMethod, 'route'=>$route_string);
		    }

	    }
    }
		if(count($api_data)) {
			$this->mount( $handle );

			if($useCache) {
				$cachedAPI[$className] = $api_data;

				$cache->save('api', $cachedAPI);
			}
		}
	}

	protected function _parseApi () {
		$path = $this->_config->application->controllersDir;
		$core_ns = $this->config->application->ns;

		$useCache = !$this->isDebug();

		foreach (\Library\Tools\File::getDirEntries($path) as $c) {
	    if (preg_match('/^(.+)Controller\.php$/', $c, $matches)) {
		    $className = $core_ns.'\Controllers\\'.$matches[1].'Controller';

				$handle = new \Phalcon\Mvc\Micro\Collection();

				// Устанавливаем главный обработчик, например, экземпляр объекта контроллера
				$handle->setHandler(new $className($this->getDi(), $this->getEventsManager()));

				// Устанавливаем общий префикс для всех маршрутов
				$handle->setPrefix('/'.strtolower($matches[1]));
				$this->attachHandlers($className, $handle, $useCache);
	    }
	  }
	}

	protected function _readCachedApi() {
		$cache = $this->getDi()->get('cacheData');

		$cachedAPI = $cache->get('api');

		if(!$cachedAPI || empty($cachedAPI)) {
			$this->_parseApi();
			return;
		}

		foreach($cachedAPI as $className=>$api) {
			$handle = new \Phalcon\Mvc\Micro\Collection();

			// Устанавливаем главный обработчик, например, экземпляр объекта контроллера
			$handle->setHandler(new $className($this->getDi(), $this->getEventsManager()));

			// Вытаскиваем префикс из имени класса
			$tmp_arr = explode('\\', $className);
			$controller_name = $tmp_arr[count($tmp_arr)-1];
			preg_match('/^(.+)Controller$/', $controller_name, $matches);

			// Устанавливаем общий префикс для всех маршрутов
			$handle->setPrefix('/'.strtolower($matches[1]));

			foreach($api as $action_name=>$dt) {
				$handle->$dt['httpMethod']($dt['route'], $action_name);
			}
			$this->mount( $handle );
		}
	}

	public function getAPI() {
		$useCache = !$this->isDebug();

		if($useCache) {
			$this->_readCachedApi();
		} else {
			$this->_parseApi();
		}
		$indexController = $this->_config->application->ns.'\Controllers\\IndexController';

		$this->get('/', array(new $indexController(), 'indexAction'));

		$this->notFound(function () {
	    $this->response->setStatusCode(404, "Not Found")->sendHeaders();
	    echo $this->getDI()->get('translate')->_('NO_ACTION_TO_SERVE');
		});
	}

	/**
	 * Clear application cache
	 */
	public function clearCache($keys = null, $folders = null)
	{
		/**
		 * @var $di \Phalcon\Di
		 */
		$di = $this->_dependencyInjector;

		$service = new \Library\Services\Cache($di, $this->getEventsManager(), $this->_config);
		$service->clearCache($keys, $folders);
	}

	public function manageResponse($data, $isException = false) {


		if(!$isException) {
			if (is_array($data)) {
        /*$data['success'] = isset($data['success']) ?: true;
        $data['message'] = isset($data['message']) ?: '';*/
        $data = json_encode($data);
	    }

	    $this->response->setContent($data);
		} else {
			$err_data = array('success' => false);
			/**
			 * @var $data \Phalcon\Exception
			 */
			$err_data['code'] = $data->getCode();
			$t = $this->getDI()->get('translate');
			$err_data['message'] = ($t) ? $t->_($data->getMessage()) : $data->getMessage();
			$this->attachCorsHeaders();
			$this->response->setContent(json_encode($err_data));
		}

		return $this->response->send();
	}

	protected function _getTranslation()
  {
	  $default_lang = \Engine\Config::getConfigValue($this->getDI(), 'application->default_lang', 'ru');

	  $language = $default_lang;
	  $auth = $this->session->get('lang');
	  if($auth) {
		  $language = $auth['lang'];
	  }

	  $localesDir = $this->_config->application->localesDir;

	  /**
	   * @var $messages array
	   */
    // Проверка существования перевода для полученного языка
    if (file_exists($localesDir."/{$language}.php")) {
       require $localesDir."/{$language}.php";
    } else {
       // Переключение на язык по умолчанию
       require $localesDir."/{$default_lang}.php";
    }

    // Возвращение объекта работы с переводом
    $this->getDi()->set('translate', function() use ($messages) {
	    return new \Phalcon\Translate\Adapter\NativeArray(array(
       "content" => $messages
      ));
    }) ;

  }

}