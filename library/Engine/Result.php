<?php

namespace Engine;

class Result {
	/**
	 * @var array $_vars
	 * Переменные результата
	 */
	protected $_vars = array();

	/**
	 * @var string $message
	 */
	protected $_message = null;

	/**
	 * @var string $_redirect_url
	 */
	protected $_redirect_url = null;

	/**
	 * @var bool $success
	 */
	public $success = true;

	/**
	 * @var string $_error_code
	 */
	public $_error_code = null;

	/**
	 * @var Result $_instance
	 */
	protected static$_instance = null;

	/**
	 * Singleton
	 */
	private function __construct() {

	}

	public static function getInstance() {
		if (null === self::$_instance) {
      self::$_instance = new self();
    }

    return self::$_instance;
	}

	public function setVars(array $vars) {
		$this->_vars = array_merge($this->_vars, $vars);
	}

	public function getVars() {
		return $this->_vars;
	}

	public function setVar($name, $value) {
		$this->_vars[$name] = $value;
	}

	public function getVar($name) {
		return $this->_vars[$name];
	}

	public function getMessage() {
		$di = \Phalcon\DI::getDefault();
		if($di->has('translate')) {
			$message = $di->get('translate')->_($this->_message, $this->_vars);
			return $message;
		}
		return $this->_message;
	}

	public function setMessage($message, $vars=array()) {
		$this->setVars($vars);
		$this->_message = $message;
	}

	public function getRedirectUrl() {
		return $this->_redirect_url;
	}

	public function setRedirectUrl($url) {
		$this->_redirect_url = $url;
	}

	public function getErrorCode() {
		return $this->_error_code;
	}

	public function setErrorCode($code) {
		$this->_error_code = $code;
	}

	public function process($format=null) {
		$redirect = $this->getRedirectUrl();

		if(!empty($redirect)) {
			header("Location:$redirect");
			return true;
		}

		$resp = null;
		switch($format) {
			case 'json':
				$resp = json_encode($this->getVars());
				break;
			case 'array':
				$resp = array_merge(array('message'=>$this->getMessage()), $this->getVars());
		}
		return $resp;
	}

}