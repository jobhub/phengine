<?php

namespace Microapp\Controllers;

use Phalcon\Mvc\Controller,
	Phalcon\Mvc\View;

class ControllerBase extends Controller
{
	public function initialize() {
		if(null === $this->getDI()) {
			$this->setDI(\Phalcon\DI::getDefault());
		}
	}

	public function _($message, $params = array()) {
		$di = $this->getDI();

		if($di->has('translate')) {
			return $di->get('translate')->_($message, $params);
		}
		return $message;
	}

	public function cfg() {
		$di = $this->getDI();
		if($di->has('config')) {
			return $di->get('config');
		}
		return false;
	}
}
