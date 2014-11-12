<?php

namespace Microapp\Controllers;


class TestController extends ControllerBase {
	/**
	 * @api("httpMethod"="get")
	 */
    public function indexAction()
    {
	    $this->view->success = true;
	    $this->view->setVar('msg', 'Test controller index action ');
    }

	/**
	 * @api("httpMethod"="get", "query_params"={{"name":"id", "regexp":"[0-9]+"}})
	 */
		public function viewAction($params) {
			$this->view->setVar('id', $params['id']);
			$this->view->setVar('success', true);
			$this->view->setVar('msg', 'Test controller view action');
		}
}

