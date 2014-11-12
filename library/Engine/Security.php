<?php
/**
 * @namespace
 */
namespace Engine;

use Phalcon\Events\Event,
	Phalcon\Mvc\User\Plugin,
	Phalcon\Mvc\Dispatcher,
	Phalcon\Acl as PhAcl;

/**
 * Security
 *
 * This is the security plugin which controls that users only have access to the modules they're assigned to
 */
class Security extends Plugin
{

	public function __construct($dependencyInjector)
	{
		$this->_dependencyInjector = $dependencyInjector;
	}

	public function getAcl()
	{
		$acls = $this->getDi()->get('cacheData')->get('acl');

		if(!$acls) {
			$m = new Acl();
			return $m->build();
		}

		return unserialize($acls);
	}

	/**
	 * This action is executed before execute any action in the application
	 *
	 * @param Event $event
	 * @param Dispatcher $dispatcher
	 */
	public function beforeExecuteRoute(Event $event, Application $app)
	{

		$auth = $this->session->get('auth');
		if (!$auth){
			$role = array(\Engine\AclRole::GUEST);
		} else {
			$role = $auth['role'];
		}

		$router = $app->getRouter();

		$route = $router->getMatchedRoute()->getPattern();

		if($route == '/') {
			$tmp = array('index', 'index');
		} else {
			$tmp = explode('/', $route);

			if(count($tmp)<2) {
				throw new Exception('NO_ACTION_IN_ROUTE');
			}
		}

		$start_index = (count($tmp)==3) ? 1 : 0;
		$controller = $tmp[$start_index];
		$action = $tmp[$start_index+1];

		$acl = $this->getAcl();

		$allowed = PhAcl::DENY;

		foreach($role as $r) {
			if(is_numeric($r)) {
				$roleModel = new \Engine\AclRole();
				$r = $roleModel->getRoleCodeById($r);
			}

			$allowed = $acl->isAllowed($r, $controller, $action);

			if($allowed==PhAcl::ALLOW) {
				break;
			}
		}

		if ($allowed != PhAcl::ALLOW) {
			throw new Exception('PERMISSION_DENIED', 123);
		}

	}

}