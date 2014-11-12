<?php

namespace Microapp\Controllers;


class IndexController extends ControllerBase {
	/**
	 * @api("httpMethod"="post")
	 */
    public function indexAction()
    {
	    $this->view->success = true;
	    $this->view->setVar('msg', 'Index controller index action ');
    }

	/** 
	 * @api("httpMethod"="get", "query_params" = { 
	 *  {"name":"id", "regexp":"[0-9]+"}}) 
	 **/

	public function viewAction($params) {
		$this->view->setVar('id', $params['id']);
		$this->view->setVar('success', true);
		$this->view->setVar('msg', 'Index controller view action');
	}
}

